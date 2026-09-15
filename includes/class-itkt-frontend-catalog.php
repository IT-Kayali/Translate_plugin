<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Frontend text catalog.
 *
 * Collects visible default-language storefront labels while an administrator browses the site,
 * groups them by functional area and exposes one backend table for direct translations.
 * Collected entries are stored as ITKT global strings, so existing runtime replacement can reuse
 * them without editing WooCommerce, WoodMart or theme files.
 */
class ITKT_Frontend_Catalog {
    private static $instance = null;

    public static function instance() {
        if ( null === self::$instance ) { self::$instance = new self(); }
        return self::$instance;
    }

    private function __construct() {
        add_action( 'wp_enqueue_scripts', array( $this, 'frontend_assets' ), 30 );
        add_action( 'wp_ajax_itkt_capture_frontend_strings', array( $this, 'ajax_capture' ) );

        if ( is_admin() ) {
            add_action( 'admin_menu', array( $this, 'admin_menu' ), 30 );
            add_action( 'admin_enqueue_scripts', array( $this, 'admin_assets' ) );
            add_action( 'admin_post_itkt_save_frontend_strings', array( $this, 'save_translations' ) );
        }
    }

    private function enabled() {
        return ITKT_Plugin::module_enabled( 'strings' ) && class_exists( 'ITKT_Strings' );
    }

    public function frontend_assets() {
        if ( is_admin() || ! $this->enabled() ) { return; }
        if ( ! is_user_logged_in() || ! current_user_can( 'manage_options' ) ) { return; }
        if ( ITKT_Languages::instance()->current_code() !== ITKT_Languages::instance()->get_default_code() ) { return; }

        wp_enqueue_script(
            'itkt-frontend-catalog',
            ITKT_URL . 'public/assets/frontend-catalog.js',
            array(),
            ITKT_VERSION,
            true
        );
        wp_localize_script( 'itkt-frontend-catalog', 'ITKTFrontendCatalog', array(
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'itkt_frontend_capture' ),
            'action'  => 'itkt_capture_frontend_strings',
            'maxText' => 500,
        ) );
    }

    public function admin_menu() {
        add_submenu_page(
            'itkt-dashboard',
            'Frontend Texte',
            'Frontend Texte',
            'manage_options',
            'itkt-frontend-strings',
            array( $this, 'render_page' )
        );
    }

    public function admin_assets( $hook ) {
        if ( false === strpos( (string) $hook, 'itkt-frontend-strings' ) ) { return; }
        wp_enqueue_style( 'itkt-admin', ITKT_URL . 'admin/assets/admin.css', array(), ITKT_VERSION );
        wp_enqueue_style( 'itkt-frontend-catalog-admin', ITKT_URL . 'admin/assets/frontend-catalog.css', array( 'itkt-admin' ), ITKT_VERSION );
    }

    private function source_table() {
        global $wpdb;
        return $wpdb->prefix . 'itkt_string_sources';
    }

    private function area_labels() {
        return array(
            'shop'      => 'Shop',
            'search'    => 'Suche',
            'product'   => 'Produktseite',
            'category'  => 'Kategorie / Archiv',
            'filters'   => 'Filter',
            'mini-cart' => 'Mini-Cart',
            'cart'      => 'Warenkorb',
            'checkout'  => 'Checkout',
            'account'   => 'Mein Konto',
            'wishlist'  => 'Wishlist',
            'popup'     => 'Popups / Offcanvas',
            'header'    => 'Header',
            'footer'    => 'Footer',
            'menu'      => 'Menü',
            'other'     => 'Sonstige',
        );
    }

    private function valid_area( $area ) {
        $area = sanitize_key( (string) $area );
        return isset( $this->area_labels()[ $area ] ) ? $area : 'other';
    }

    private function normalize_text( $text ) {
        $text = trim( wp_strip_all_tags( (string) $text, true ) );
        $text = preg_replace( '/\s+/u', ' ', $text );
        if ( '' === $text ) { return ''; }
        $len = function_exists( 'mb_strlen' ) ? mb_strlen( $text, 'UTF-8' ) : strlen( $text );
        if ( $len < 2 || $len > 500 ) { return ''; }
        if ( preg_match( '/^[\d\s.,+\-–—:%€$£¥()\/]+$/u', $text ) ) { return ''; }
        if ( preg_match( '/^(https?:|mailto:|tel:)/i', $text ) ) { return ''; }
        return $text;
    }

    private function register_source( $string_id, $area, $url ) {
        global $wpdb;
        $table = $this->source_table();
        $area = $this->valid_area( $area );
        $url  = esc_url_raw( (string) $url );
        $hash = hash( 'sha256', absint( $string_id ) . "\0frontend\0" . $area . "\0" . $url );
        $wpdb->replace( $table, array(
            'string_id'   => absint( $string_id ),
            'source_type' => 'frontend',
            'source_key'  => $area,
            'file'        => $url,
            'line'        => 0,
            'source_hash' => $hash,
        ), array( '%d','%s','%s','%s','%d','%s' ) );
    }

    public function ajax_capture() {
        if ( ! $this->enabled() || ! is_user_logged_in() || ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Keine Berechtigung.' ), 403 );
        }
        check_ajax_referer( 'itkt_frontend_capture', 'nonce' );

        if ( ITKT_Languages::instance()->current_code() !== ITKT_Languages::instance()->get_default_code() ) {
            wp_send_json_success( array( 'skipped' => true ) );
        }

        $area  = $this->valid_area( wp_unslash( $_POST['area'] ?? 'other' ) );
        $url   = esc_url_raw( wp_unslash( $_POST['url'] ?? '' ) );
        $items = json_decode( wp_unslash( $_POST['items'] ?? '[]' ), true );
        if ( ! is_array( $items ) ) { $items = array(); }

        $stored = 0;
        foreach ( array_slice( $items, 0, 150 ) as $item ) {
            $text = $this->normalize_text( is_array( $item ) ? ( $item['text'] ?? '' ) : $item );
            if ( '' === $text ) { continue; }
            $id = ITKT_Strings::instance()->ensure_global_string( $text, $area, 'exact' );
            if ( ! $id ) { continue; }
            $this->register_source( $id, $area, $url );
            $stored++;
        }

        wp_send_json_success( array( 'stored' => $stored, 'area' => $area ) );
    }

    private function target_languages() {
        $langs = ITKT_Languages::instance()->get_active();
        unset( $langs[ ITKT_Languages::instance()->get_default_code() ] );
        return $langs;
    }

    private function area_counts() {
        global $wpdb;
        $table = $this->source_table();
        $rows = $wpdb->get_results( "SELECT source_key AS area, COUNT(DISTINCT string_id) AS total FROM {$table} WHERE source_type='frontend' GROUP BY source_key ORDER BY total DESC", ARRAY_A );
        $out = array();
        foreach ( (array) $rows as $row ) {
            $area = $this->valid_area( $row['area'] ?? 'other' );
            $out[ $area ] = ( $out[ $area ] ?? 0 ) + absint( $row['total'] ?? 0 );
        }
        return $out;
    }

    private function header( $title, $description = '' ) {
        echo '<div class="wrap itkt-wrap"><div class="itkt-topbar"><div><div class="itkt-brand"><span class="itkt-brand-mark">ITK</span><span>IT-Kayali Translate</span></div><h1>' . esc_html( $title ) . '</h1>';
        if ( $description ) { echo '<p class="itkt-lead">' . esc_html( $description ) . '</p>'; }
        echo '</div><a class="itkt-site-link" href="https://it-kayali.de" target="_blank" rel="noopener">it-kayali.de ↗</a></div>';
        if ( ! empty( $_GET['saved'] ) ) { echo '<div class="notice notice-success inline"><p><strong>Gespeichert.</strong> Frontend-Übersetzungen wurden aktualisiert.</p></div>'; }
    }

    public function render_page() {
        if ( ! current_user_can( 'manage_options' ) || ! $this->enabled() ) { return; }

        $service = ITKT_Strings::instance();
        $targets = $this->target_languages();
        $target_codes = array_keys( $targets );
        $labels = $this->area_labels();
        $areas  = $this->area_counts();
        $area   = sanitize_key( $_GET['area'] ?? '' );
        if ( $area && ! isset( $labels[ $area ] ) ) { $area = ''; }
        $status = sanitize_key( $_GET['status'] ?? 'missing' );
        if ( ! in_array( $status, array( 'missing','translated','all' ), true ) ) { $status = 'missing'; }
        $search = sanitize_text_field( wp_unslash( $_GET['s'] ?? '' ) );
        $paged  = max( 1, absint( $_GET['paged'] ?? 1 ) );

        $result = $service->query_strings( array(
            'page'        => $paged,
            'per_page'    => 60,
            'search'      => $search,
            'source_type' => 'frontend',
            'source_key'  => $area,
            'status'      => $status,
            'targets'     => $target_codes,
        ) );
        $rows = (array) $result['rows'];
        $ids = array_map( function( $row ) { return absint( $row->id ); }, $rows );
        $translations = $service->get_translations_for_ids( $ids, $target_codes );

        $this->header( 'Frontend Texte', 'Sichtbare Texte automatisch nach Bereich sammeln und direkt je Sprache übersetzen. Erfassung läuft beim Durchklicken der Standardsprache als Administrator.' );
        ?>
        <section class="itkt-card">
            <div class="itkt-card-head"><div><span class="itkt-kicker">AUTOMATISCHE ERFASSUNG</span><h2>Frontend-Textkatalog</h2></div><span class="itkt-pill green">v0.12.11</span></div>
            <p>Gehe im Frontend in der Standardsprache als Administrator durch Shop, Produktseiten, Warenkorb, Checkout, Konto und Popups. Neue sichtbare Texte werden automatisch erfasst. Preise, Mengen und technische Werte werden ignoriert.</p>
            <div class="itkt-front-area-tabs">
                <a class="<?php echo '' === $area ? 'is-active' : ''; ?>" href="<?php echo esc_url( add_query_arg( array( 'page'=>'itkt-frontend-strings','status'=>$status ), admin_url( 'admin.php' ) ) ); ?>">Alle <strong><?php echo intval( array_sum( $areas ) ); ?></strong></a>
                <?php foreach ( $areas as $key=>$count ) : ?>
                    <a class="<?php echo $area === $key ? 'is-active' : ''; ?>" href="<?php echo esc_url( add_query_arg( array( 'page'=>'itkt-frontend-strings','area'=>$key,'status'=>$status ), admin_url( 'admin.php' ) ) ); ?>"><?php echo esc_html( $labels[ $key ] ?? $key ); ?> <strong><?php echo intval( $count ); ?></strong></a>
                <?php endforeach; ?>
            </div>
        </section>

        <section class="itkt-card">
            <form method="get" class="itkt-front-toolbar">
                <input type="hidden" name="page" value="itkt-frontend-strings">
                <?php if ( $area ) : ?><input type="hidden" name="area" value="<?php echo esc_attr( $area ); ?>"><?php endif; ?>
                <select name="status">
                    <option value="missing" <?php selected( $status, 'missing' ); ?>>Nicht vollständig</option>
                    <option value="translated" <?php selected( $status, 'translated' ); ?>>Vollständig</option>
                    <option value="all" <?php selected( $status, 'all' ); ?>>Alle</option>
                </select>
                <input type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="Frontend-Text suchen …">
                <button class="button">Filtern</button>
            </form>

            <?php if ( ! $rows ) : ?>
                <div class="itkt-empty-state"><span class="dashicons dashicons-visibility"></span><strong>Noch keine passenden Frontend-Texte.</strong><p>Öffne die Standardsprache im Frontend als Administrator und durchlaufe die gewünschten Bereiche.</p></div>
            <?php else : ?>
                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                    <?php wp_nonce_field( 'itkt_save_frontend_strings' ); ?>
                    <input type="hidden" name="action" value="itkt_save_frontend_strings">
                    <input type="hidden" name="return_url" value="<?php echo esc_attr( remove_query_arg( 'saved' ) ); ?>">
                    <div class="itkt-front-table-wrap"><table class="widefat striped itkt-front-table">
                        <thead><tr><th>Bereich / Original</th><?php foreach ( $targets as $code=>$lang ) : ?><th><?php echo esc_html( strtoupper( $code ) . ' · ' . ( $lang['native_name'] ?? $code ) ); ?></th><?php endforeach; ?></tr></thead>
                        <tbody>
                        <?php foreach ( $rows as $row ) : $row_area = $this->valid_area( $row->source_key ?? 'other' ); ?>
                            <tr>
                                <td class="itkt-front-source"><span class="itkt-pill gray"><?php echo esc_html( $labels[ $row_area ] ?? $row_area ); ?></span><strong><?php echo esc_html( $row->original ); ?></strong><?php if ( ! empty( $row->source_file ) ) : ?><small title="<?php echo esc_attr( $row->source_file ); ?>"><?php echo esc_html( wp_parse_url( $row->source_file, PHP_URL_PATH ) ?: $row->source_file ); ?></small><?php endif; ?></td>
                                <?php foreach ( $targets as $code=>$lang ) : $value = $translations[ absint( $row->id ) ][ $code ] ?? ''; ?>
                                    <td><textarea name="translations[<?php echo absint( $row->id ); ?>][<?php echo esc_attr( $code ); ?>]" rows="3" placeholder="Übersetzung …"><?php echo esc_textarea( $value ); ?></textarea></td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table></div>
                    <p><button class="button button-primary itkt-primary" type="submit">Frontend-Übersetzungen speichern</button></p>
                </form>
            <?php endif; ?>
        </section>
        <?php
        if ( intval( $result['pages'] ) > 1 ) {
            echo '<div class="tablenav"><div class="tablenav-pages">' . wp_kses_post( paginate_links( array( 'base'=>add_query_arg( 'paged', '%#%' ), 'format'=>'', 'current'=>$paged, 'total'=>intval( $result['pages'] ) ) ) ) . '</div></div>';
        }
        echo '</div>';
    }

    public function save_translations() {
        if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Unauthorized' ); }
        check_admin_referer( 'itkt_save_frontend_strings' );
        $targets = $this->target_languages();
        foreach ( (array) ( $_POST['translations'] ?? array() ) as $id=>$langs ) {
            $id = absint( $id );
            if ( ! $id || ! is_array( $langs ) ) { continue; }
            foreach ( $langs as $code=>$value ) {
                $code = sanitize_key( $code );
                if ( ! isset( $targets[ $code ] ) ) { continue; }
                ITKT_Strings::instance()->save_translation( $id, $code, wp_unslash( $value ) );
            }
        }
        $return = wp_validate_redirect( wp_unslash( $_POST['return_url'] ?? '' ), admin_url( 'admin.php?page=itkt-frontend-strings' ) );
        wp_safe_redirect( add_query_arg( 'saved', '1', $return ) );
        exit;
    }
}
