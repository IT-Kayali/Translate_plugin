<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class ITKT_Admin {
    private static $instance = null;
    private const PRODUCT_IMPORT_MAX_UPLOAD_BYTES = 26214400; // 25 MiB.
    private const PRODUCT_IMPORT_MAX_XML_BYTES = 33554432; // 32 MiB per parsed XLSX XML part.
    private const PRODUCT_IMPORT_MAX_ROWS = 10000;
    private const PRODUCT_IMPORT_MAX_COLUMNS = 512;
    private const PRODUCT_IMPORT_MAX_CELL_BYTES = 2097152; // 2 MiB per cell.
    private const PRODUCT_IMPORT_MAX_SHARED_STRINGS = 100000;
    public static function instance() { if ( null === self::$instance ) { self::$instance = new self(); } return self::$instance; }

    private function __construct() {
        add_action( 'admin_menu', array( $this, 'menu' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
        add_action( 'admin_post_itkt_setup_save', array( $this, 'save_setup' ) );
        add_action( 'admin_post_itkt_toggle_language', array( $this, 'toggle_language' ) );
        add_action( 'admin_post_itkt_add_language', array( $this, 'add_language' ) );
        add_action( 'admin_post_itkt_save_language_settings', array( $this, 'save_language_settings' ) );
        add_action( 'admin_post_itkt_save_settings', array( $this, 'save_settings' ) );
        add_action( 'admin_post_itkt_save_switcher_settings', array( $this, 'save_switcher_settings' ) );
        add_action( 'admin_post_itkt_create_translation', array( $this, 'create_translation' ) );
        add_action( 'admin_post_itkt_link_translation', array( $this, 'link_translation' ) );
        add_action( 'admin_post_itkt_export_products', array( $this, 'export_products' ) );
        add_action( 'admin_post_itkt_export_products_csv', array( $this, 'export_products_csv' ) );
        add_action( 'admin_post_itkt_import_products', array( $this, 'import_products' ) );
        add_action( 'admin_post_itkt_repair_elementor_layout', array( $this, 'repair_elementor_layout' ) );
        add_action( 'admin_post_itkt_save_editor', array( $this, 'save_editor' ) );
        add_action( 'admin_post_itkt_save_menus', array( $this, 'save_menus' ) );
        add_action( 'admin_post_itkt_clone_menu', array( $this, 'clone_menu' ) );
        add_action( 'admin_post_itkt_scan_strings', array( $this, 'scan_strings' ) );
        add_action( 'wp_ajax_itkt_scan_strings_start', array( $this, 'ajax_scan_strings_start' ) );
        add_action( 'wp_ajax_itkt_scan_strings_batch', array( $this, 'ajax_scan_strings_batch' ) );
        add_action( 'admin_post_itkt_save_strings', array( $this, 'save_strings' ) );
        add_action( 'admin_post_itkt_clear_diagnostics', array( $this, 'clear_diagnostics' ) );
        add_action( 'admin_post_itkt_repair_slug_indexes', array( $this, 'repair_slug_indexes' ) );
        add_action( 'admin_post_itkt_repair_search_indexes', array( $this, 'repair_search_indexes' ) );
        add_action( 'admin_post_itkt_flush_rewrites', array( $this, 'flush_rewrites' ) );
        add_action( 'admin_post_itkt_save_acceptance', array( $this, 'save_acceptance' ) );
        add_action( 'admin_post_itkt_reset_acceptance', array( $this, 'reset_acceptance' ) );
        add_action( 'admin_post_itkt_export_acceptance', array( $this, 'export_acceptance' ) );
        add_filter( 'plugin_action_links_' . ITKT_BASENAME, array( $this, 'plugin_links' ) );
    }

    public function plugin_links( $links ) {
        array_unshift( $links, '<a href="' . esc_url( admin_url( 'admin.php?page=itkt-dashboard' ) ) . '">Dashboard</a>' );
        return $links;
    }

    public function menu() {
        add_menu_page( 'IT-Kayali Translate', 'IT-Kayali Translate', 'manage_options', 'itkt-dashboard', array( $this, 'dashboard' ), 'dashicons-translation', 58 );
        add_submenu_page( 'itkt-dashboard', 'Dashboard', 'Dashboard', 'manage_options', 'itkt-dashboard', array( $this, 'dashboard' ) );
        add_submenu_page( 'itkt-dashboard', 'Übersetzungen', 'Übersetzungen', 'edit_posts', 'itkt-translations', array( $this, 'translations' ) );
        add_submenu_page( 'itkt-dashboard', 'Sprachen', 'Sprachen', 'manage_options', 'itkt-languages', array( $this, 'languages' ) );
        add_submenu_page( 'itkt-dashboard', 'Seiten', 'Seiten', 'edit_pages', 'itkt-pages', array( $this, 'pages' ) );
        add_submenu_page( 'itkt-dashboard', 'Beiträge', 'Beiträge', 'edit_posts', 'itkt-posts', array( $this, 'posts' ) );
        $env = ITKT_Environment::detect();
        if ( ( ITKT_Plugin::module_enabled( 'elementor' ) && ! empty( $env['elementor']['active'] ) ) || ( ITKT_Plugin::module_enabled( 'woodmart' ) && ! empty( $env['woodmart']['active'] ) ) ) {
            add_submenu_page( 'itkt-dashboard', 'Builder Inhalte', 'Builder Inhalte', 'edit_pages', 'itkt-builders', array( $this, 'builders' ) );
        }
        if ( ITKT_Plugin::module_enabled( 'woocommerce' ) && post_type_exists( 'product' ) ) { add_submenu_page( 'itkt-dashboard', 'Produkte', 'Produkte', 'edit_products', 'itkt-products', array( $this, 'products' ) ); }
        add_submenu_page( 'itkt-dashboard', 'Menüs', 'Menüs', 'edit_theme_options', 'itkt-menus', array( $this, 'menus' ) );
        add_submenu_page( 'itkt-dashboard', 'Sprachumschalter', 'Sprachumschalter', 'edit_theme_options', 'itkt-switcher', array( $this, 'switcher' ) );
        add_submenu_page( 'itkt-dashboard', 'Shortcodes', 'Shortcodes', 'edit_theme_options', 'itkt-shortcodes', array( $this, 'shortcodes' ) );
        add_submenu_page( 'itkt-dashboard', 'Plugin & Theme Texte', 'Plugin & Theme Texte', 'manage_options', 'itkt-strings', array( $this, 'strings' ) );
        add_submenu_page( 'itkt-dashboard', 'Systemstatus', 'Systemstatus', 'manage_options', 'itkt-system', array( $this, 'system' ) );
        add_submenu_page( 'itkt-dashboard', 'Einstellungen', 'Einstellungen', 'manage_options', 'itkt-settings', array( $this, 'settings' ) );
        add_submenu_page( null, 'Setup', 'Setup', 'manage_options', 'itkt-setup', array( $this, 'setup' ) );
        add_submenu_page( null, 'Übersetzungseditor', 'Übersetzungseditor', 'edit_posts', 'itkt-editor', array( $this, 'editor' ) );
    }

    public function assets( $hook ) {
        if ( false === strpos( $hook, 'itkt' ) ) { return; }
        wp_enqueue_style( 'itkt-admin', ITKT_URL . 'admin/assets/admin.css', array(), ITKT_VERSION );
        wp_enqueue_script( 'itkt-admin', ITKT_URL . 'admin/assets/admin.js', array(), ITKT_VERSION, true );
        wp_localize_script( 'itkt-admin', 'ITKT_ADMIN', array(
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'scanNonce' => wp_create_nonce( 'itkt_ajax_string_scan' ),
            'stringsUrl' => admin_url( 'admin.php?page=itkt-strings' ),
        ) );
    }

    private function header( $title, $description = '' ) {
        echo '<div class="wrap itkt-wrap"><div class="itkt-topbar"><div><div class="itkt-brand"><span class="itkt-brand-mark">ITK</span><span>IT-Kayali Translate</span></div><h1>' . esc_html( $title ) . '</h1>';
        if ( $description ) { echo '<p class="itkt-lead">' . esc_html( $description ) . '</p>'; }
        echo '</div><a class="itkt-site-link" href="https://it-kayali.de" target="_blank" rel="noopener">it-kayali.de ↗</a></div>';
        if ( ! empty( $_GET['saved'] ) ) {
            echo '<div class="notice notice-success inline itkt-saved-notice"><p><strong>Gespeichert.</strong> Die Übersetzungen wurden aktualisiert.</p></div>';
        }
    }
    private function footer() { echo '</div>'; }

    public function setup() {
        if ( ! current_user_can( 'manage_options' ) ) { return; }
        $catalog = ITKT_Languages::instance()->catalog(); $env = ITKT_Environment::detect();
        $this->header( 'Ersteinrichtung', 'Sprachen und passende Module auswählen. WooCommerce-Produkte werden niemals pro Sprache dupliziert.' );
        ?>
        <form class="itkt-wizard" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post">
            <?php wp_nonce_field( 'itkt_setup_save' ); ?><input type="hidden" name="action" value="itkt_setup_save">
            <section class="itkt-card itkt-wizard-hero"><span class="itkt-kicker">SCHRITT 1</span><h2>Website erkannt</h2><div class="itkt-detection-grid">
                <?php foreach ( $env as $item ) : ?><div class="itkt-detect <?php echo $item['active'] ? 'is-active' : ''; ?>"><span class="itkt-dot"></span><strong><?php echo esc_html( $item['label'] ); ?></strong><span><?php echo $item['active'] ? 'Erkannt' : 'Nicht aktiv'; ?> <?php echo esc_html( $item['version'] ?? '' ); ?></span></div><?php endforeach; ?>
            </div></section>
            <section class="itkt-card"><span class="itkt-kicker">SCHRITT 2</span><h2>Sprachen auswählen</h2>
                <label class="itkt-field"><span>Standardsprache</span><select name="default_language"><?php foreach ( $catalog as $code => $lang ) : ?><option value="<?php echo esc_attr( $code ); ?>" <?php selected( $code, 'de' ); ?>><?php echo esc_html( $lang['flag'] . ' ' . $lang['native_name'] ); ?></option><?php endforeach; ?></select></label>
                <h3>Weitere Sprachen</h3><div class="itkt-language-picker">
                <?php foreach ( $catalog as $code => $lang ) : if ( 'de' === $code ) continue; ?><label class="itkt-check-card"><input type="checkbox" name="additional_languages[]" value="<?php echo esc_attr( $code ); ?>" <?php checked( in_array( $code, array( 'ar','en' ), true ) ); ?>><span class="itkt-check-ui"></span><span class="itkt-flag"><?php echo esc_html( $lang['flag'] ); ?></span><span><strong><?php echo esc_html( $lang['native_name'] ); ?></strong><small><?php echo esc_html( strtoupper( $code ) . ' · ' . strtoupper( $lang['direction'] ) ); ?></small></span></label><?php endforeach; ?>
                </div>
            </section>
            <section class="itkt-card"><span class="itkt-kicker">SCHRITT 3</span><h2>Module aktivieren</h2><div class="itkt-module-grid">
            <?php $modules = array(
                'pages'=>array('Seiten','Normale Seiten und Elementor-Seiten.',true), 'posts'=>array('Beiträge','Blogbeiträge und redaktionelle Inhalte.',true), 'strings'=>array('Plugin & Theme Texte','Getrennte String-Verwaltung.',true),
                'elementor'=>array('Elementor','Textfelder erkennen; HTML/Code wird markiert.',$env['elementor']['active']), 'woocommerce'=>array('WooCommerce','Produkte ohne Duplikate, Kategorien und Merkmale.',$env['woocommerce']['active']), 'woodmart'=>array('WoodMart Adapter','WoodMart/XTemos-Kompatibilität.',$env['woodmart']['active']) );
                foreach ( $modules as $key=>$module ) : ?><label class="itkt-module-card <?php echo $module[2] ? '' : 'is-unavailable'; ?>"><input type="checkbox" name="modules[]" value="<?php echo esc_attr( $key ); ?>" <?php checked( $module[2] ); ?> <?php disabled( ! $module[2] ); ?>><div><strong><?php echo esc_html( $module[0] ); ?></strong><p><?php echo esc_html( $module[1] ); ?></p></div><span class="itkt-module-state"><?php echo $module[2] ? 'Verfügbar' : 'Nicht erkannt'; ?></span></label><?php endforeach; ?>
            </div></section>
            <section class="itkt-card"><span class="itkt-kicker">SCHRITT 4</span><h2>Vorhandene Seiten und Beiträge</h2><p>Bestehende Seiten und Beiträge werden der Standardsprache zugeordnet. Produkte bleiben unverändert dieselben WooCommerce-Produkte.</p><label class="itkt-switch-row"><input type="checkbox" name="assign_existing" value="1" checked><span class="itkt-switch"></span><span><strong>Bestehende Seiten und Beiträge zuordnen</strong><small>Keine Inhalte werden gelöscht oder veröffentlicht.</small></span></label></section>
            <div class="itkt-wizard-actions"><span>Produkte werden nicht dupliziert.</span><button class="button button-primary itkt-primary" type="submit">Einrichtung abschließen</button></div>
        </form><?php $this->footer();
    }

    public function save_setup() {
        if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Unauthorized' ); }
        check_admin_referer( 'itkt_setup_save' ); $catalog = ITKT_Languages::instance()->catalog();
        $default = sanitize_key( $_POST['default_language'] ?? 'de' ); if ( ! isset( $catalog[ $default ] ) ) { $default = 'de'; }
        $additional = array_map( 'sanitize_key', (array) ( $_POST['additional_languages'] ?? array() ) ); $modules = array_map( 'sanitize_key', (array) ( $_POST['modules'] ?? array() ) );
        ITKT_Languages::instance()->save_from_setup( $default, $additional );
        update_option( 'itkt_settings', array( 'setup_complete'=>true, 'default_language'=>$default, 'enabled_modules'=>$modules, 'url_mode'=>'directory', 'fallback_mode'=>'default', 'remember_language'=>true, 'redirect_saved_home'=>true, 'diagnostic_logging'=>true ) );
        if ( ! empty( $_POST['assign_existing'] ) ) {
            $types = array(); if ( in_array( 'pages', $modules, true ) ) { $types[]='page'; } if ( in_array( 'posts', $modules, true ) ) { $types[]='post'; }
            ITKT_Content::instance()->assign_existing_default_language( $types );
        }
        wp_safe_redirect( admin_url( 'admin.php?page=itkt-dashboard&setup=done' ) ); exit;
    }

    public function dashboard() {
        $settings = get_option( 'itkt_settings', array() ); if ( empty( $settings['setup_complete'] ) ) { wp_safe_redirect( admin_url( 'admin.php?page=itkt-setup' ) ); exit; }
        $languages = ITKT_Languages::instance()->get_all(); $env=ITKT_Environment::detect(); $page_count=wp_count_posts('page'); $post_count=wp_count_posts('post'); $product_count=(ITKT_Plugin::module_enabled('woocommerce')&&post_type_exists('product'))?wp_count_posts('product'):null;
        $this->header( 'Dashboard', 'Zentrale Übersicht. Fehlende und fertige Übersetzungen sind jetzt separat erreichbar.' ); ?>
        <div class="itkt-stats-grid"><div class="itkt-stat-card"><span>Aktive Sprachen</span><strong><?php echo count( ITKT_Languages::instance()->get_active() ); ?></strong><small><?php echo esc_html( implode( ' · ', array_keys( ITKT_Languages::instance()->get_active() ) ) ); ?></small></div><div class="itkt-stat-card"><span>Seiten</span><strong><?php echo intval($page_count->publish??0); ?></strong><small>Elementor wird automatisch erkannt</small></div><div class="itkt-stat-card"><span>Beiträge</span><strong><?php echo intval($post_count->publish??0); ?></strong><small>separater Bereich</small></div><?php if($product_count):?><div class="itkt-stat-card"><span>Produkte</span><strong><?php echo intval($product_count->publish??0); ?></strong><small>eine Produkt-ID für alle Sprachen</small></div><?php endif;?></div>
        <section class="itkt-card"><div class="itkt-card-head"><div><span class="itkt-kicker">SCHNELLZUGRIFF</span><h2>Übersetzungsstatus</h2></div></div><div class="itkt-shortcut-grid"><a href="<?php echo esc_url(admin_url('admin.php?page=itkt-translations&status=missing')); ?>"><span class="dashicons dashicons-warning"></span><strong>Nicht übersetzt</strong><small>Fehlend, teilweise oder veraltet</small></a><a href="<?php echo esc_url(admin_url('admin.php?page=itkt-translations&status=translated')); ?>"><span class="dashicons dashicons-yes-alt"></span><strong>Bereits übersetzt</strong><small>vollständige Inhalte</small></a><a href="<?php echo esc_url(admin_url('admin.php?page=itkt-pages')); ?>"><span class="dashicons dashicons-admin-page"></span><strong>Seiten</strong><small>Elementor oder WordPress Editor</small></a><?php if(ITKT_Plugin::module_enabled('strings')):?><a href="<?php echo esc_url(admin_url('admin.php?page=itkt-strings')); ?>"><span class="dashicons dashicons-editor-code"></span><strong>Plugin & Theme Texte</strong><small>WooCommerce, WoodMart und weitere Quellen</small></a><?php endif;?><?php if(ITKT_Plugin::module_enabled('woocommerce')&&post_type_exists('product')):?><a href="<?php echo esc_url(admin_url('admin.php?page=itkt-products')); ?>"><span class="dashicons dashicons-cart"></span><strong>Produkte</strong><small>Produkte, Kategorien, Merkmale</small></a><?php endif;?></div></section>
        <div class="itkt-grid-2"><section class="itkt-card"><div class="itkt-card-head"><div><span class="itkt-kicker">SPRACHEN</span><h2>Aktive Konfiguration</h2></div><a href="<?php echo esc_url(admin_url('admin.php?page=itkt-languages')); ?>">Verwalten →</a></div><div class="itkt-language-list"><?php foreach($languages as $lang):?><div><span class="itkt-flag"><?php echo esc_html($lang['flag']);?></span><strong><?php echo esc_html($lang['native_name']);?></strong><span class="itkt-pill <?php echo !empty($lang['active'])?'green':'gray';?>"><?php echo !empty($lang['active'])?'Aktiv':'Deaktiviert';?></span><?php if(!empty($lang['default'])):?><span class="itkt-pill orange">Standard</span><?php endif;?></div><?php endforeach;?></div></section><section class="itkt-card"><div class="itkt-card-head"><div><span class="itkt-kicker">KOMPATIBILITÄT</span><h2>Erkannte Umgebung</h2></div></div><div class="itkt-compat-list"><?php foreach($env as $item):?><div><span class="itkt-dot <?php echo $item['active']?'ok':'';?>"></span><strong><?php echo esc_html($item['label']);?></strong><span><?php echo $item['active']?'Kompatibel':'Nicht aktiv';?></span></div><?php endforeach;?></div></section></div>
        <?php $health=ITKT_Diagnostics::instance()->self_test();$failed=array_filter($health,function($c){return empty($c['ok']);});$translation_stats=ITKT_Diagnostics::instance()->stats();?>
        <section class="itkt-card itkt-health-summary"><div class="itkt-card-head"><div><span class="itkt-kicker">SYSTEMGESUNDHEIT</span><h2><?php echo $failed?'Prüfung empfohlen':'Alles sieht gut aus';?></h2></div><a href="<?php echo esc_url(admin_url('admin.php?page=itkt-system'));?>">Diagnose öffnen →</a></div><div class="itkt-health-metrics"><div><strong><?php echo intval(count($health)-count($failed));?>/<?php echo intval(count($health));?></strong><span>Systemchecks OK</span></div><div><strong><?php echo intval($translation_stats['complete']??0);?></strong><span>Fertige Übersetzungen</span></div><div><strong><?php echo intval(($translation_stats['missing']??0)+($translation_stats['partial']??0));?></strong><span>Fehlend/teilweise</span></div><div><strong><?php echo intval($translation_stats['outdated']??0);?></strong><span>Veraltet</span></div></div></section>
        <?php $this->footer();
    }

    public function languages() {
        $languages = ITKT_Languages::instance()->get_all();
        $catalog   = ITKT_Languages::instance()->catalog();
        $available = array_diff_key( $catalog, $languages );
        $this->header( 'Sprachen', 'Sprachen aktivieren und RTL getrennt für Text, Inhaltsbereich oder die komplette Website steuern.' );
        ?>
        <section class="itkt-card">
            <div class="itkt-card-head"><div><span class="itkt-kicker">KONFIGURATION</span><h2>Installierte Sprachen</h2></div><span class="itkt-pill orange">RTL flexibel</span></div>
            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <?php wp_nonce_field( 'itkt_save_language_settings' ); ?>
                <input type="hidden" name="action" value="itkt_save_language_settings">
                <div class="itkt-table-wrap"><table class="itkt-table itkt-language-settings-table">
                    <thead><tr><th>Sprache</th><th>Code</th><th>Text-Richtung</th><th>RTL-Layout</th><th>Status</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ( $languages as $code => $lang ) :
                        $effective = ITKT_Languages::instance()->effective_direction( $code );
                        $auto = ! isset( $lang['direction_auto'] ) || ! empty( $lang['direction_auto'] );
                        ?>
                        <tr>
                            <td><span class="itkt-flag"><?php echo esc_html( $lang['flag'] ); ?></span><strong><?php echo esc_html( $lang['native_name'] ); ?></strong></td>
                            <td><code><?php echo esc_html( $code ); ?></code></td>
                            <td>
                                <div class="itkt-direction-control">
                                    <label class="itkt-inline-check"><input type="checkbox" name="language_settings[<?php echo esc_attr( $code ); ?>][direction_auto]" value="1" <?php checked( $auto ); ?>><span>Automatisch</span></label>
                                    <select name="language_settings[<?php echo esc_attr( $code ); ?>][direction_override]">
                                        <option value="ltr" <?php selected( $lang['direction_override'] ?? $lang['direction'], 'ltr' ); ?>>LTR</option>
                                        <option value="rtl" <?php selected( $lang['direction_override'] ?? $lang['direction'], 'rtl' ); ?>>RTL</option>
                                    </select>
                                    <small>Aktuell: <?php echo esc_html( strtoupper( $effective ) ); ?></small>
                                </div>
                            </td>
                            <td>
                                <div class="itkt-direction-control">
                                    <label class="itkt-switch-row itkt-compact-switch">
                                        <input type="checkbox" name="language_settings[<?php echo esc_attr( $code ); ?>][content_rtl_layout]" value="1" <?php checked( ! empty( $lang['content_rtl_layout'] ) ); ?>>
                                        <span class="itkt-switch"></span><span><strong>Nur Inhalt spiegeln</strong><small>Shop/Seiten RTL · Header & Footer bleiben LTR</small></span>
                                    </label>
                                    <label class="itkt-switch-row itkt-compact-switch">
                                        <input type="checkbox" name="language_settings[<?php echo esc_attr( $code ); ?>][full_rtl_layout]" value="1" <?php checked( ! empty( $lang['full_rtl_layout'] ) ); ?>>
                                        <span class="itkt-switch"></span><span><strong>Ganze Seite spiegeln</strong><small>Header, Inhalt und Footer RTL</small></span>
                                    </label>
                                </div>
                            </td>
                            <td><?php if ( ! empty( $lang['default'] ) ) : ?><span class="itkt-pill orange">Standard</span><?php else : ?><span class="itkt-pill <?php echo ! empty( $lang['active'] ) ? 'green' : 'gray'; ?>"><?php echo ! empty( $lang['active'] ) ? 'Aktiv' : 'Deaktiviert'; ?></span><?php endif; ?></td>
                            <td class="itkt-actions"><?php if ( empty( $lang['default'] ) ) : ?><a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=itkt_toggle_language&code=' . rawurlencode( $code ) ), 'itkt_toggle_language_' . $code ) ); ?>"><?php echo ! empty( $lang['active'] ) ? 'Deaktivieren' : 'Aktivieren'; ?></a><?php endif; ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table></div>
                <p><button class="button button-primary itkt-primary" type="submit">RTL-Einstellungen speichern</button></p>
            </form>
            <div class="itkt-notice blue"><strong>Hinweis:</strong> Für Arabisch: „Automatisch“ erkennt RTL. Mit „Nur Inhalt spiegeln“ werden Shop, Produkt, Warenkorb, Checkout und Seiten RTL, während Header und Footer unverändert LTR bleiben. „Ganze Seite spiegeln“ hat Vorrang, falls beide Optionen gewählt werden.</div>
        </section>
        <section class="itkt-card"><span class="itkt-kicker">HINZUFÜGEN</span><h2>Weitere Sprache</h2>
            <?php if ( $available ) : ?><form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><?php wp_nonce_field( 'itkt_add_language' ); ?><input type="hidden" name="action" value="itkt_add_language"><label class="itkt-field"><span>Sprache</span><select name="code"><?php foreach ( $available as $code => $lang ) : ?><option value="<?php echo esc_attr( $code ); ?>"><?php echo esc_html( $lang['flag'] . ' ' . $lang['native_name'] ); ?></option><?php endforeach; ?></select></label><button class="button button-primary itkt-primary">Sprache hinzufügen</button></form><?php else : ?><p>Alle vorgesehenen Sprachen sind bereits hinzugefügt.</p><?php endif; ?>
        </section>
        <?php $this->footer();
    }

    public function toggle_language(){ if(!current_user_can('manage_options'))wp_die('Unauthorized');$code=sanitize_key($_GET['code']??'');check_admin_referer('itkt_toggle_language_'.$code);ITKT_Languages::instance()->toggle_language($code);wp_safe_redirect(admin_url('admin.php?page=itkt-languages'));exit; }
    public function add_language(){ if(!current_user_can('manage_options'))wp_die('Unauthorized');check_admin_referer('itkt_add_language');ITKT_Languages::instance()->add_language(sanitize_key($_POST['code']??''));wp_safe_redirect(admin_url('admin.php?page=itkt-languages'));exit; }

    public function save_language_settings() {
        if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Unauthorized' ); }
        check_admin_referer( 'itkt_save_language_settings' );
        ITKT_Languages::instance()->save_direction_settings( (array) ( $_POST['language_settings'] ?? array() ) );
        wp_safe_redirect( admin_url( 'admin.php?page=itkt-languages&saved=1' ) );
        exit;
    }


    public function translations() {
        $filter=sanitize_key($_GET['status']??'missing'); if(!in_array($filter,array('missing','translated','all'),true))$filter='missing';
        $this->header('Übersetzungen','Alle Bereiche zentral prüfen. Seiten, Beiträge, Produkte, Kategorien und Produktmerkmale bleiben als getrennte Abschnitte sichtbar.');
        $this->status_tabs('itkt-translations',$filter);
        $this->render_post_matrix('page','Seiten',$filter,50);
        $this->render_post_matrix('post','Beiträge / Blog',$filter,50);
        if(ITKT_Plugin::module_enabled('woocommerce')&&post_type_exists('product')){ $this->render_product_matrix('Produkte',$filter,60); if(taxonomy_exists('product_cat'))$this->render_term_matrix('product_cat','Produktkategorien',$filter,50); if(taxonomy_exists('product_tag'))$this->render_term_matrix('product_tag','Produkt-Tags',$filter,50); $this->render_attribute_matrix('Produktmerkmale',$filter); $this->render_attribute_terms($filter); }
        $this->footer();
    }

    private function status_tabs($page,$filter){ echo '<nav class="itkt-tabs"><a class="'.($filter==='missing'?'is-active':'').'" href="'.esc_url(admin_url('admin.php?page='.$page.'&status=missing')).'">Nicht übersetzt</a><a class="'.($filter==='translated'?'is-active':'').'" href="'.esc_url(admin_url('admin.php?page='.$page.'&status=translated')).'">Bereits übersetzt</a><a class="'.($filter==='all'?'is-active':'').'" href="'.esc_url(admin_url('admin.php?page='.$page.'&status=all')).'">Alle</a></nav>'; }
    private function row_visible($statuses,$filter){ if('all'===$filter)return true; if(!$statuses)return false; $all_complete=true; foreach($statuses as $s){ if('complete'!==$s){$all_complete=false;break;} } return 'translated'===$filter?$all_complete:!$all_complete; }
    private function active_targets(){ $langs=ITKT_Languages::instance()->get_active(); unset($langs[ITKT_Languages::instance()->get_default_code()]); return $langs; }

    public function pages(){ $filter=sanitize_key($_GET['status']??'all'); $this->header('Seiten','Elementor-Seiten öffnen aus IT-Kayali Translate automatisch im Elementor Editor.'); $this->status_tabs('itkt-pages',$filter); $this->render_post_matrix('page','Seiten',$filter,100,true); $this->footer(); }
    public function posts(){ $filter=sanitize_key($_GET['status']??'all'); $this->header('Beiträge','Blogbeiträge separat von Seiten und Produkten übersetzen.'); $this->status_tabs('itkt-posts',$filter); $this->render_post_matrix('post','Beiträge',$filter,100,true); $this->footer(); }

    public function builders(){
        $filter=sanitize_key($_GET['status']??'all');
        $this->header('Builder Inhalte','Elementor Templates, WoodMart HTML-Blöcke, Layouts und weitere erkannte Builder-Inhalte getrennt übersetzen. Layout- und Designwerte bleiben unverändert.');
        $this->status_tabs('itkt-builders',$filter);
        $types=array();
        if(post_type_exists('elementor_library')){$obj=get_post_type_object('elementor_library');$types['elementor_library']=$obj&&$obj->labels?$obj->labels->name:'Elementor Templates';}
        if(class_exists('ITKT_WoodMart_Adapter')){$types=array_merge($types,ITKT_WoodMart_Adapter::builder_post_types());}
        $types=apply_filters('itkt_builder_admin_post_types',$types);
        if(!$types){echo '<section class="itkt-card"><div class="itkt-empty"><span class="dashicons dashicons-layout"></span><strong>Keine zusätzlichen Builder-Inhalte erkannt.</strong><small>Normale Elementor-Seiten findest du weiterhin unter „Seiten“.</small></div></section>';}
        foreach($types as $post_type=>$label){if(post_type_exists($post_type))$this->render_post_matrix($post_type,$label,$filter,80,true,'builder');}
        $this->footer();
    }

    private function render_post_matrix($post_type,$title,$filter='all',$limit=100,$standalone=false,$editor_type=''){
        $items=ITKT_Content::instance()->get_items($post_type,$limit);$targets=$this->active_targets();$rows=0;$editor_type=$editor_type?:$post_type;ob_start();
        foreach($items as $post){
            $translations=ITKT_Content::instance()->translations_for($post->ID);$statuses=array();
            foreach($targets as $code=>$lang){$statuses[$code]=ITKT_Content::instance()->translation_status($post->ID,$translations[$code]??null);}
            if(!$this->row_visible($statuses,$filter))continue;$rows++;
            $candidates=ITKT_Content::instance()->link_candidates($post_type,$post->ID);
            ?>
        <tr><td><strong><?php echo esc_html(get_the_title($post)?:'(ohne Titel)');?></strong><small>#<?php echo intval($post->ID);?> · <?php echo ITKT_Content::instance()->is_elementor($post->ID)?'Elementor':'WordPress';?></small></td>
        <?php foreach($targets as $code=>$lang):$tr=$translations[$code]??null;$status=$statuses[$code];?><td>
            <?php if(!$tr):?>
                <div class="itkt-missing-actions">
                    <a class="itkt-create-link" target="_blank" rel="noopener" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=itkt_create_translation&source='.$post->ID.'&language='.rawurlencode($code)),'itkt_create_translation_'.$post->ID.'_'.$code));?>">Erstellen</a>
                    <?php if($candidates):?>
                    <details class="itkt-link-existing"><summary>Vorhandenen Inhalt zuordnen</summary>
                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php'));?>" target="_blank">
                            <?php wp_nonce_field('itkt_link_translation_'.$post->ID.'_'.$code);?>
                            <input type="hidden" name="action" value="itkt_link_translation">
                            <input type="hidden" name="source" value="<?php echo intval($post->ID);?>">
                            <input type="hidden" name="language" value="<?php echo esc_attr($code);?>">
                            <select name="target" required><option value="">— Seite auswählen —</option>
                            <?php foreach($candidates as $candidate):?><option value="<?php echo intval($candidate->ID);?>">#<?php echo intval($candidate->ID);?> · <?php echo esc_html(get_the_title($candidate)?:'(ohne Titel)');?></option><?php endforeach;?>
                            </select><button class="button">Zuordnen</button>
                        </form>
                    </details>
                    <?php endif;?>
                </div>
            <?php else:?><span class="itkt-status <?php echo esc_attr($status);?>"><?php echo esc_html($this->status_label($status));?></span><a class="itkt-mini-link" target="_blank" rel="noopener" href="<?php echo esc_url(ITKT_Content::instance()->edit_url($tr->ID));?>"><?php echo ITKT_Content::instance()->is_elementor($tr->ID)?'Mit Elementor bearbeiten':'Bearbeiten';?></a><?php endif;?></td><?php endforeach;?>
        <td class="itkt-actions"><a class="button button-primary itkt-inline-primary" href="<?php echo esc_url(admin_url('admin.php?page=itkt-editor&type='.rawurlencode($editor_type).'&id='.$post->ID));?>">Übersetzen</a> <a class="button" target="_blank" rel="noopener" href="<?php echo esc_url(ITKT_Content::instance()->edit_url($post->ID));?>"><?php echo ITKT_Content::instance()->is_elementor($post->ID)?'Original mit Elementor bearbeiten':'Original bearbeiten';?></a></td></tr><?php }
        $body=ob_get_clean();?><section class="itkt-card"><div class="itkt-card-head"><div><span class="itkt-kicker">INHALTE</span><h2><?php echo esc_html($title);?></h2></div><span class="itkt-pill blue"><?php echo intval($rows);?> angezeigt</span></div><div class="itkt-table-wrap"><table class="itkt-table itkt-translation-table"><thead><tr><th>Original</th><?php foreach($targets as $code=>$lang):?><th><?php echo esc_html($lang['flag'].' '.strtoupper($code));?></th><?php endforeach;?><th>Aktionen</th></tr></thead><tbody><?php echo $rows?$body:'<tr><td colspan="12">Keine passenden Inhalte gefunden.</td></tr>';?></tbody></table></div></section><?php
    }

    public function products(){
        $sub=sanitize_key($_GET['sub']??'products'); if(!in_array($sub,array('products','categories','tags','attributes','import-export'),true))$sub='products'; $filter=sanitize_key($_GET['status']??'all');
        $this->header('Produkte','WooCommerce-Produkte bleiben dieselben Produkte. Texte, Kategorien, Tags und Merkmale werden sprachabhängig ausgegeben.');
        echo '<nav class="itkt-tabs itkt-subtabs"><a class="'.($sub==='products'?'is-active':'').'" href="'.esc_url(admin_url('admin.php?page=itkt-products&sub=products')).'">Produkte</a><a class="'.($sub==='categories'?'is-active':'').'" href="'.esc_url(admin_url('admin.php?page=itkt-products&sub=categories')).'">Kategorien</a><a class="'.($sub==='tags'?'is-active':'').'" href="'.esc_url(admin_url('admin.php?page=itkt-products&sub=tags')).'">Tags</a><a class="'.($sub==='attributes'?'is-active':'').'" href="'.esc_url(admin_url('admin.php?page=itkt-products&sub=attributes')).'">Produktmerkmale</a><a class="'.($sub==='import-export'?'is-active':'').'" href="'.esc_url(admin_url('admin.php?page=itkt-products&sub=import-export')).'">Import / Export</a></nav>';
        if('import-export'!==$sub)$this->status_tabs('itkt-products&sub='.$sub,$filter);
        if('products'===$sub)$this->render_product_matrix('Produkte',$filter,100); elseif('categories'===$sub)$this->render_term_matrix('product_cat','Produktkategorien',$filter,100); elseif('tags'===$sub)$this->render_term_matrix('product_tag','Produkt-Tags',$filter,100); elseif('attributes'===$sub){ $this->render_attribute_matrix('Produktmerkmale',$filter); $this->render_attribute_terms($filter); } else { $this->product_import_export(); }
        $this->footer();
    }

    private function render_product_matrix($title,$filter='all',$limit=100){
        $q=new WP_Query(array('post_type'=>'product','post_status'=>array('publish','draft','private','pending'),'posts_per_page'=>$limit,'orderby'=>'modified','order'=>'DESC'));$targets=$this->active_targets();$rows=0;ob_start();
        foreach($q->posts as $p){$statuses=array();foreach($targets as $code=>$l)$statuses[$code]=ITKT_Product_Translations::instance()->status($p->ID,$code);if(!$this->row_visible($statuses,$filter))continue;$rows++;?>
        <tr><td><strong><?php echo esc_html(get_the_title($p));?></strong><small>#<?php echo intval($p->ID);?> · SKU: <?php echo esc_html(get_post_meta($p->ID,'_sku',true)?:'—');?></small></td><?php foreach($targets as $code=>$l):?><td><span class="itkt-status <?php echo esc_attr($statuses[$code]);?>"><?php echo esc_html($this->status_label($statuses[$code]));?></span></td><?php endforeach;?><td class="itkt-actions"><a class="button button-primary itkt-inline-primary" href="<?php echo esc_url(admin_url('admin.php?page=itkt-editor&type=product&id='.$p->ID));?>">Übersetzen</a> <a class="button" href="<?php echo esc_url(get_edit_post_link($p->ID,'raw'));?>">Produkt öffnen</a></td></tr><?php }
        $body=ob_get_clean();?><section class="itkt-card"><div class="itkt-card-head"><div><span class="itkt-kicker">WOOCOMMERCE</span><h2><?php echo esc_html($title);?></h2></div><span class="itkt-pill orange">Keine Produkt-Duplikate</span></div><div class="itkt-table-wrap"><table class="itkt-table"><thead><tr><th>Produkt</th><?php foreach($targets as $code=>$l):?><th><?php echo esc_html($l['flag'].' '.strtoupper($code));?></th><?php endforeach;?><th>Aktionen</th></tr></thead><tbody><?php echo $rows?$body:'<tr><td colspan="12">Keine passenden Produkte gefunden.</td></tr>';?></tbody></table></div></section><?php
    }

    private function render_term_matrix($taxonomy,$title,$filter='all',$limit=100){
        if(!taxonomy_exists($taxonomy))return;$terms=get_terms(array('taxonomy'=>$taxonomy,'hide_empty'=>false,'number'=>$limit));if(is_wp_error($terms))return;$targets=$this->active_targets();$rows=0;ob_start();foreach($terms as $term){$statuses=array();foreach($targets as $code=>$l)$statuses[$code]=ITKT_Term_Translations::instance()->status($term->term_id,$code);if(!$this->row_visible($statuses,$filter))continue;$rows++;?>
        <tr><td><strong><?php echo esc_html($term->name);?></strong><small><?php echo esc_html($taxonomy);?> · #<?php echo intval($term->term_id);?></small></td><?php foreach($targets as $code=>$l):?><td><span class="itkt-status <?php echo esc_attr($statuses[$code]);?>"><?php echo esc_html($this->status_label($statuses[$code]));?></span></td><?php endforeach;?><td class="itkt-actions"><a class="button button-primary itkt-inline-primary" href="<?php echo esc_url(admin_url('admin.php?page=itkt-editor&type=term&id='.$term->term_id));?>">Übersetzen</a></td></tr><?php }$body=ob_get_clean();?>
        <section class="itkt-card"><div class="itkt-card-head"><div><span class="itkt-kicker">BEGRIFFE</span><h2><?php echo esc_html($title);?></h2></div></div><div class="itkt-table-wrap"><table class="itkt-table"><thead><tr><th>Original</th><?php foreach($targets as $code=>$l):?><th><?php echo esc_html($l['flag'].' '.strtoupper($code));?></th><?php endforeach;?><th>Aktion</th></tr></thead><tbody><?php echo $rows?$body:'<tr><td colspan="12">Keine passenden Begriffe gefunden.</td></tr>';?></tbody></table></div></section><?php
    }

    private function render_attribute_matrix($title,$filter='all'){
        if(!function_exists('wc_get_attribute_taxonomies'))return;$attrs=wc_get_attribute_taxonomies();$targets=$this->active_targets();$rows=0;ob_start();foreach($attrs as $attr){$tax='pa_'.$attr->attribute_name;$statuses=array();foreach($targets as $code=>$l)$statuses[$code]=ITKT_Term_Translations::instance()->attribute_status($tax,$code);if(!$this->row_visible($statuses,$filter))continue;$rows++;?>
        <tr><td><strong><?php echo esc_html($attr->attribute_label);?></strong><small><?php echo esc_html($tax);?></small></td><?php foreach($targets as $code=>$l):?><td><span class="itkt-status <?php echo esc_attr($statuses[$code]);?>"><?php echo esc_html($this->status_label($statuses[$code]));?></span></td><?php endforeach;?><td class="itkt-actions"><a class="button button-primary itkt-inline-primary" href="<?php echo esc_url(admin_url('admin.php?page=itkt-editor&type=attribute&taxonomy='.rawurlencode($tax)));?>">Übersetzen</a></td></tr><?php }$body=ob_get_clean();?>
        <section class="itkt-card"><div class="itkt-card-head"><div><span class="itkt-kicker">ATTRIBUTE</span><h2><?php echo esc_html($title);?></h2></div></div><div class="itkt-table-wrap"><table class="itkt-table"><thead><tr><th>Merkmal</th><?php foreach($targets as $code=>$l):?><th><?php echo esc_html($l['flag'].' '.strtoupper($code));?></th><?php endforeach;?><th>Aktion</th></tr></thead><tbody><?php echo $rows?$body:'<tr><td colspan="12">Keine Produktmerkmale gefunden.</td></tr>';?></tbody></table></div></section><?php
    }

    private function render_attribute_terms($filter='all'){
        if(!function_exists('wc_get_attribute_taxonomies'))return;$attrs=wc_get_attribute_taxonomies();foreach($attrs as $attr){$tax='pa_'.$attr->attribute_name;if(taxonomy_exists($tax))$this->render_term_matrix($tax,'Werte: '.$attr->attribute_label,$filter,60);}
    }

    public function editor(){
        $type=sanitize_key($_GET['type']??'');$id=absint($_GET['id']??0);$taxonomy=sanitize_key($_GET['taxonomy']??'');$langs=ITKT_Languages::instance()->get_active();$default=ITKT_Languages::instance()->get_default_code();
        if(in_array($type,array('page','post','product','builder'),true)&&!current_user_can('edit_post',$id))wp_die('Unauthorized'); if(in_array($type,array('term','attribute'),true)&&!current_user_can('manage_woocommerce'))wp_die('Unauthorized');
        if('product'===$type){$this->product_editor($id,$langs,$default);return;} if('term'===$type){$this->term_editor($id,$langs,$default);return;} if('attribute'===$type){$this->attribute_editor($taxonomy,$langs,$default);return;} if(in_array($type,array('page','post','builder'),true)){$this->post_editor($id,$type,$langs,$default);return;} wp_die('Invalid editor type.');
    }

    private function post_editor($id,$type,$langs,$default){
        $source=get_post($id);if(!$source)wp_die('Content not found.');$translations=ITKT_Content::instance()->translations_for($id);$is_elementor=ITKT_Content::instance()->is_elementor($id);$segments=$is_elementor?ITKT_Elementor::instance()->segments($id):array();$stats=$is_elementor?ITKT_Elementor::instance()->segment_stats($id):array();$this->header('Übersetzen: '.($source->post_title?:'#'.$id),$is_elementor?'Elementor erkannt: normale Widget-Texte können hier übersetzt werden; HTML/Code bleibt zur manuellen Prüfung.':'Inhalte direkt nebeneinander übersetzen.'); ?>
        <?php if($is_elementor):?><div class="itkt-builder-summary"><span><strong><?php echo intval($stats['automatic']??0);?></strong> automatisch übersetzbare Felder</span><span><strong><?php echo intval($stats['widgets']??0);?></strong> Widget-Typen</span><span><strong><?php echo intval($stats['manual']??0);?></strong> manuell prüfen</span><?php if(!empty($stats['woodmart'])):?><span class="is-woodmart"><strong><?php echo intval($stats['woodmart']);?></strong> WoodMart-Felder erkannt</span><?php endif;?></div><?php endif;?>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php'));?>" class="itkt-editor-form"><?php wp_nonce_field('itkt_save_editor_'.$type.'_'.$id);?><input type="hidden" name="action" value="itkt_save_editor"><input type="hidden" name="type" value="<?php echo esc_attr($type);?>"><input type="hidden" name="id" value="<?php echo intval($id);?>">
        <section class="itkt-card"><div class="itkt-card-head"><div><span class="itkt-kicker">SPRACHMATRIX</span><h2>Seiteninhalt</h2></div><a class="button" href="<?php echo esc_url(ITKT_Content::instance()->edit_url($id));?>"><?php echo $is_elementor?'Original mit Elementor öffnen':'Original öffnen';?></a></div>
        <div class="itkt-editor-scroll"><table class="itkt-editor-table"><thead><tr><th>Abschnitt</th><?php foreach($langs as $code=>$lang):?><th><?php echo esc_html($lang['flag'].' '.$lang['native_name']);?><?php if($code===$default):?><small>Original</small><?php elseif(isset($translations[$code])):?><a href="<?php echo esc_url(ITKT_Content::instance()->edit_url($translations[$code]->ID));?>"><?php echo $is_elementor?'Elementor öffnen':'Öffnen';?> ↗</a><?php if($is_elementor):?><a class="itkt-repair-link" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=itkt_repair_elementor_layout&source='.$id.'&translation='.$translations[$code]->ID),'itkt_repair_elementor_layout_'.$id.'_'.$translations[$code]->ID));?>">Layout neu übernehmen</a><?php endif;?><?php endif;?></th><?php endforeach;?></tr></thead><tbody>
        <?php $fields=$is_elementor?array('title'=>'Titel','excerpt'=>'Kurztext'):array('title'=>'Titel','excerpt'=>'Kurztext','content'=>'Inhalt');foreach($fields as $field=>$label):?><tr><th><?php echo esc_html($label);?></th><?php foreach($langs as $code=>$lang): if($code===$default){$val=$field==='title'?$source->post_title:($field==='excerpt'?$source->post_excerpt:$source->post_content);?><td class="is-source"><div class="itkt-source-text"><?php echo nl2br(esc_html(wp_strip_all_tags($val)));?></div></td><?php } else {$tr=$translations[$code]??null;$val=$tr?($field==='title'?$tr->post_title:($field==='excerpt'?$tr->post_excerpt:$tr->post_content)):'';?><td><?php if($field==='title'):?><input type="text" name="fields[<?php echo esc_attr($code);?>][<?php echo esc_attr($field);?>]" value="<?php echo esc_attr($val);?>"><?php else:?><textarea name="fields[<?php echo esc_attr($code);?>][<?php echo esc_attr($field);?>]" rows="<?php echo $field==='content'?7:3;?>"><?php echo esc_textarea($val);?></textarea><?php endif;?></td><?php } endforeach;?></tr><?php endforeach;?>
        <?php if($is_elementor&&$segments):?><tr class="itkt-section-row"><th colspan="<?php echo count($langs)+1;?>">Elementor-Abschnitte</th></tr><?php foreach($segments as $segment):?><tr><th><?php echo esc_html($segment['label']);?></th><?php foreach($langs as $code=>$lang):if($code===$default):?><td class="is-source"><?php if(!empty($segment['manual'])):?><span class="itkt-status manual">Manuell prüfen</span><small>HTML/Shortcode/Code wird nicht automatisch verändert.</small><?php else:?><div class="itkt-source-text"><?php echo esc_html(wp_strip_all_tags($segment['source']));?></div><?php endif;?></td><?php else:$tr=$translations[$code]??null;if(!empty($segment['manual'])):?><td><span class="itkt-status manual">Manuell</span><?php if($tr):?><a class="itkt-mini-link" href="<?php echo esc_url(ITKT_Content::instance()->edit_url($tr->ID));?>">Mit Elementor bearbeiten</a><?php else:?><a class="itkt-mini-link" target="_blank" rel="noopener" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=itkt_create_translation&source='.$id.'&language='.rawurlencode($code)),'itkt_create_translation_'.$id.'_'.$code));?>">Sprachversion in Elementor anlegen ↗</a><?php endif;?></td><?php else:$tv=$tr?ITKT_Elementor::instance()->translated_values($tr->ID):array();$val=$tv[$segment['key']]??'';?><td><textarea rows="3" name="elementor[<?php echo esc_attr($code);?>][<?php echo esc_attr($segment['key']);?>]"><?php echo esc_textarea($val);?></textarea></td><?php endif;endif;endforeach;?></tr><?php endforeach;endif;?>
        <tr class="itkt-section-row"><th colspan="<?php echo count($langs)+1;?>">SEO & öffentliche URL</th></tr>
        <?php foreach(array('slug'=>'URL-Slug','seo_title'=>'SEO-Titel','seo_description'=>'Meta-Beschreibung') as $seo_field=>$seo_label):?><tr><th><?php echo esc_html($seo_label);?></th><?php foreach($langs as $code=>$lang):if($code===$default):$source_seo=ITKT_SEO::instance()->post_seo($source->ID);$sv='slug'===$seo_field?$source->post_name:($source_seo[$seo_field]??'');?><td class="is-source"><div class="itkt-source-text"><?php echo esc_html($sv?:('slug'===$seo_field?$source->post_name:'WordPress / SEO-Plugin Standard'));?></div></td><?php else:$tr=$translations[$code]??null;$seo_data=$tr?ITKT_SEO::instance()->post_seo($tr->ID):array();$val=$seo_data[$seo_field]??'';?><td><?php if('seo_description'===$seo_field):?><textarea rows="3" name="seo[<?php echo esc_attr($code);?>][<?php echo esc_attr($seo_field);?>]"><?php echo esc_textarea($val);?></textarea><?php else:?><input type="text" name="seo[<?php echo esc_attr($code);?>][<?php echo esc_attr($seo_field);?>]" value="<?php echo esc_attr($val);?>" placeholder="<?php echo 'slug'===$seo_field?'wird aus Titel erzeugt, wenn leer':'';?>"><?php endif;?></td><?php endif;endforeach;?></tr><?php endforeach;?>
        </tbody></table></div></section><div class="itkt-savebar"><span>Speichern erstellt fehlende Sprachseiten nur dann, wenn du Inhalte eingetragen hast.</span><button class="button button-primary itkt-primary" type="submit">Übersetzungen speichern</button></div></form><?php $this->footer();
    }

    private function product_editor($id,$langs,$default){
        $p=get_post($id);if(!$p||'product'!==$p->post_type)wp_die('Product not found.');
        $product=function_exists('wc_get_product')?wc_get_product($id):null;
        $custom=ITKT_Product_Translations::instance()->custom_attributes($id);
        $this->header('Produkt übersetzen: '.$p->post_title,'Eine Produkt-ID. Preis, Bestand, SKU, Bilder und Varianten bleiben unveränderte WooCommerce-Daten.');?>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php'));?>" class="itkt-editor-form"><?php wp_nonce_field('itkt_save_editor_product_'.$id);?><input type="hidden" name="action" value="itkt_save_editor"><input type="hidden" name="type" value="product"><input type="hidden" name="id" value="<?php echo intval($id);?>">
        <section class="itkt-card"><div class="itkt-card-head"><div><span class="itkt-kicker">PRODUKTINHALT</span><h2>Sprachen nebeneinander</h2></div><a class="button" target="_blank" rel="noopener" href="<?php echo esc_url(get_edit_post_link($id,'raw'));?>">WooCommerce-Produkt öffnen ↗</a></div>
        <div class="itkt-editor-scroll"><table class="itkt-editor-table"><thead><tr><th>Feld</th><?php foreach($langs as $code=>$lang):?><th><?php echo esc_html($lang['flag'].' '.$lang['native_name']);?><?php if($code===$default):?><small>Original</small><?php endif;?></th><?php endforeach;?></tr></thead><tbody>
        <?php $fields=array('title'=>'Produktname','short_description'=>'Kurzbeschreibung','description'=>'Lange Beschreibung');foreach($fields as $field=>$label):?><tr><th><?php echo esc_html($label);?></th><?php foreach($langs as $code=>$lang):if($code===$default){$val=$field==='title'?$p->post_title:($field==='short_description'?$p->post_excerpt:$p->post_content);?><td class="is-source"><div class="itkt-source-text"><?php echo nl2br(esc_html(wp_strip_all_tags($val)));?></div></td><?php }else{$d=ITKT_Product_Translations::instance()->get($id,$code);$val=$d[$field]??'';?><td><?php if($field==='title'):?><input type="text" name="fields[<?php echo esc_attr($code);?>][<?php echo esc_attr($field);?>]" value="<?php echo esc_attr($val);?>"><?php else:?><textarea rows="<?php echo $field==='description'?7:4;?>" name="fields[<?php echo esc_attr($code);?>][<?php echo esc_attr($field);?>]"><?php echo esc_textarea($val);?></textarea><?php endif;?></td><?php }endforeach;?></tr><?php endforeach;?>
        <tr class="itkt-section-row"><th colspan="<?php echo count($langs)+1;?>">SEO & öffentliche URL</th></tr>
        <?php foreach(array('slug'=>'URL-Slug','seo_title'=>'SEO-Titel','seo_description'=>'Meta-Beschreibung') as $field=>$label):?><tr><th><?php echo esc_html($label);?></th><?php foreach($langs as $code=>$lang):if($code===$default){$val='slug'===$field?$p->post_name:'';?><td class="is-source"><div class="itkt-source-text"><?php echo esc_html($val?:('slug'===$field?$p->post_name:'WooCommerce / SEO-Plugin Standard'));?></div></td><?php }else{$d=ITKT_Product_Translations::instance()->get($id,$code);$val='slug'===$field?ITKT_Product_Translations::instance()->explicit_slug($id,$code):($d[$field]??'');?><td><?php if('seo_description'===$field):?><textarea rows="3" name="fields[<?php echo esc_attr($code);?>][<?php echo esc_attr($field);?>]"><?php echo esc_textarea($val);?></textarea><?php else:?><input type="text" name="fields[<?php echo esc_attr($code);?>][<?php echo esc_attr($field);?>]" value="<?php echo esc_attr($val);?>" placeholder="<?php echo 'slug'===$field?'leer = Standardsprache verwenden':'';?>"><?php endif;?></td><?php }endforeach;?></tr><?php endforeach;?>
        <?php if($custom):?><tr class="itkt-section-row"><th colspan="<?php echo count($langs)+1;?>">Individuelle Produktmerkmale</th></tr><?php foreach($custom as $key=>$attribute):?>
            <tr><th><?php echo esc_html($attribute['name']);?><small><?php echo esc_html(implode(' · ',$attribute['values']));?></small></th><?php foreach($langs as $code=>$lang):if($code===$default):?><td class="is-source"><div class="itkt-source-text"><strong><?php echo esc_html($attribute['name']);?></strong><br><?php echo esc_html(implode(', ',$attribute['values']));?></div></td><?php else:$d=ITKT_Product_Translations::instance()->get($id,$code);$td=(array)($d['custom_attributes'][$key]??array());?><td><input type="text" name="fields[<?php echo esc_attr($code);?>][custom_attributes][<?php echo esc_attr($key);?>][label]" placeholder="Merkmalname" value="<?php echo esc_attr($td['label']??'');?>"><textarea rows="2" name="fields[<?php echo esc_attr($code);?>][custom_attributes][<?php echo esc_attr($key);?>][values]" placeholder="Werte, z. B. Rot, Blau"><?php echo esc_textarea($td['values']??'');?></textarea></td><?php endif;endforeach;?></tr>
        <?php endforeach;endif;?></tbody></table></div></section>
        <?php if($product):$cats=wp_get_post_terms($id,'product_cat');$tags=taxonomy_exists('product_tag')?wp_get_post_terms($id,'product_tag'):array();?>
        <section class="itkt-card"><div class="itkt-card-head"><div><span class="itkt-kicker">ZUORDNUNGEN</span><h2>Kategorien, Tags & globale Merkmale</h2></div><span class="itkt-pill blue">Bestehende Begriffe – keine Duplikate</span></div>
        <div class="itkt-product-relations">
            <div><h3>Kategorien</h3><?php if($cats&&!is_wp_error($cats)):foreach($cats as $term):?><a class="itkt-relation-chip" href="<?php echo esc_url(admin_url('admin.php?page=itkt-editor&type=term&id='.$term->term_id));?>"><?php echo esc_html($term->name);?> <span>übersetzen →</span></a><?php endforeach;else:?><small>Keine Kategorien zugewiesen.</small><?php endif;?></div>
            <div><h3>Tags</h3><?php if($tags&&!is_wp_error($tags)):foreach($tags as $term):?><a class="itkt-relation-chip" href="<?php echo esc_url(admin_url('admin.php?page=itkt-editor&type=term&id='.$term->term_id));?>"><?php echo esc_html($term->name);?> <span>übersetzen →</span></a><?php endforeach;else:?><small>Keine Tags zugewiesen.</small><?php endif;?></div>
            <div><h3>Globale Produktmerkmale</h3><?php $global_found=false;foreach($product->get_attributes() as $attribute):if(!is_a($attribute,'WC_Product_Attribute')||!$attribute->is_taxonomy())continue;$global_found=true;$tax=$attribute->get_name();?><a class="itkt-relation-chip" href="<?php echo esc_url(admin_url('admin.php?page=itkt-editor&type=attribute&taxonomy='.rawurlencode($tax)));?>"><?php echo esc_html(ITKT_Term_Translations::instance()->attribute_source_label($tax));?> <span>Merkmal →</span></a><?php $terms=wp_get_post_terms($id,$tax);if($terms&&!is_wp_error($terms)):foreach($terms as $term):?><a class="itkt-relation-chip is-value" href="<?php echo esc_url(admin_url('admin.php?page=itkt-editor&type=term&id='.$term->term_id));?>"><?php echo esc_html($term->name);?> <span>Wert →</span></a><?php endforeach;endif;endforeach;if(!$global_found):?><small>Keine globalen Merkmale zugewiesen.</small><?php endif;?></div>
        </div></section><?php endif;?>
        <div class="itkt-savebar"><span>Es wird kein neues Produkt angelegt. Technische WooCommerce-Daten bleiben am Original.</span><button class="button button-primary itkt-primary">Produktübersetzungen speichern</button></div></form><?php $this->footer();}

    private function term_editor($id,$langs,$default){$term=get_term($id);if(!$term||is_wp_error($term))wp_die('Term not found.');$this->header('Begriff übersetzen: '.$term->name,'Der bestehende Kategorie-/Merkmalwert bleibt derselbe Begriff; Übersetzungen werden daran gespeichert.');?>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php'));?>"><?php wp_nonce_field('itkt_save_editor_term_'.$id);?><input type="hidden" name="action" value="itkt_save_editor"><input type="hidden" name="type" value="term"><input type="hidden" name="id" value="<?php echo intval($id);?>"><section class="itkt-card"><div class="itkt-editor-scroll"><table class="itkt-editor-table"><thead><tr><th>Feld</th><?php foreach($langs as $code=>$l):?><th><?php echo esc_html($l['flag'].' '.$l['native_name']);?></th><?php endforeach;?></tr></thead><tbody><?php foreach(array('name'=>'Name','description'=>'Beschreibung','slug'=>'URL-Slug','seo_title'=>'SEO-Titel','seo_description'=>'Meta-Beschreibung') as $field=>$label):?><tr><th><?php echo esc_html($label);?></th><?php foreach($langs as $code=>$l):if($code===$default){$v='name'===$field?$term->name:('description'===$field?$term->description:('slug'===$field?$term->slug:''));?><td class="is-source"><div class="itkt-source-text"><?php echo esc_html(wp_strip_all_tags($v?:('seo_title'===$field||'seo_description'===$field?'WooCommerce / SEO-Plugin Standard':'')));?></div></td><?php }else{$d=ITKT_Term_Translations::instance()->get($id,$code);$v=$d[$field]??'';?><td><?php if(in_array($field,array('description','seo_description'),true)):?><textarea rows="<?php echo 'description'===$field?4:3;?>" name="fields[<?php echo esc_attr($code);?>][<?php echo esc_attr($field);?>]"><?php echo esc_textarea($v);?></textarea><?php else:?><input type="text" name="fields[<?php echo esc_attr($code);?>][<?php echo esc_attr($field);?>]" value="<?php echo esc_attr($v);?>"><?php endif;?></td><?php }endforeach;?></tr><?php endforeach;?></tbody></table></div></section><div class="itkt-savebar"><span>Keine Kategorie wird dupliziert.</span><button class="button button-primary itkt-primary">Speichern</button></div></form><?php $this->footer();}

    private function attribute_editor($taxonomy,$langs,$default){if(!function_exists('wc_attribute_label'))wp_die('WooCommerce unavailable.');$label=wc_attribute_label($taxonomy);$this->header('Produktmerkmal übersetzen: '.$label,'Übersetzt wird die sichtbare Bezeichnung des vorhandenen WooCommerce-Merkmals.');?>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php'));?>"><?php wp_nonce_field('itkt_save_editor_attribute_'.$taxonomy);?><input type="hidden" name="action" value="itkt_save_editor"><input type="hidden" name="type" value="attribute"><input type="hidden" name="taxonomy" value="<?php echo esc_attr($taxonomy);?>"><section class="itkt-card"><div class="itkt-editor-scroll"><table class="itkt-editor-table"><thead><tr><th>Feld</th><?php foreach($langs as $code=>$l):?><th><?php echo esc_html($l['flag'].' '.$l['native_name']);?></th><?php endforeach;?></tr></thead><tbody><tr><th>Merkmalname</th><?php foreach($langs as $code=>$l):if($code===$default):?><td class="is-source"><div class="itkt-source-text"><?php echo esc_html($label);?></div></td><?php else:?><td><input type="text" name="fields[<?php echo esc_attr($code);?>][label]" value="<?php echo esc_attr(ITKT_Term_Translations::instance()->get_attribute($taxonomy,$code));?>"></td><?php endif;endforeach;?></tr></tbody></table></div></section><div class="itkt-savebar"><span>Werte dieses Merkmals werden separat unter Produkte → Produktmerkmale übersetzt.</span><button class="button button-primary itkt-primary">Speichern</button></div></form><?php $this->footer();}

    public function save_editor(){
        $type=sanitize_key($_POST['type']??'');$id=absint($_POST['id']??0);$taxonomy=sanitize_key($_POST['taxonomy']??'');$fields=(array)($_POST['fields']??array());$elementor=(array)($_POST['elementor']??array());$seo=(array)($_POST['seo']??array());$default=ITKT_Languages::instance()->get_default_code();
        if(in_array($type,array('page','post','product','builder'),true)){if(!current_user_can('edit_post',$id))wp_die('Unauthorized');check_admin_referer('itkt_save_editor_'.$type.'_'.$id);}elseif('term'===$type){if(!current_user_can('manage_woocommerce'))wp_die('Unauthorized');check_admin_referer('itkt_save_editor_term_'.$id);}elseif('attribute'===$type){if(!current_user_can('manage_woocommerce'))wp_die('Unauthorized');check_admin_referer('itkt_save_editor_attribute_'.$taxonomy);}else wp_die('Invalid type.');
        $targets=$this->active_targets();
        if(in_array($type,array('page','post','builder'),true)){
            $translations=ITKT_Content::instance()->translations_for($id);
            foreach($targets as $code=>$lang){$d=(array)($fields[$code]??array());$el=(array)($elementor[$code]??array());$seo_d=(array)($seo[$code]??array());$has=false;foreach($d as $v){if(trim(wp_strip_all_tags((string)$v))!==''){$has=true;break;}}if(!$has){foreach($el as $v){if(trim(wp_strip_all_tags((string)$v))!==''){$has=true;break;}}}if(!$has){foreach($seo_d as $v){if(trim(wp_strip_all_tags((string)$v))!==''){$has=true;break;}}}if(!$has&&!isset($translations[$code]))continue;$tid=isset($translations[$code])?$translations[$code]->ID:ITKT_Content::instance()->create_translation($id,$code);if(is_wp_error($tid))continue;$update=array('ID'=>$tid);if(array_key_exists('title',$d))$update['post_title']=sanitize_text_field(wp_unslash($d['title']));if(array_key_exists('excerpt',$d))$update['post_excerpt']=wp_kses_post(wp_unslash($d['excerpt']));if(!ITKT_Content::instance()->is_elementor($id)&&array_key_exists('content',$d))$update['post_content']=wp_kses_post(wp_unslash($d['content']));wp_update_post($update);if($el)ITKT_Elementor::instance()->apply($id,$tid,$el);if($seo_d){if(empty($seo_d['slug'])&&!empty($d['title'])&&!get_post_meta($tid,ITKT_SEO::POST_SLUG_META,true))$seo_d['slug']=sanitize_title(wp_unslash($d['title']));ITKT_SEO::instance()->save_post_seo($tid,$seo_d);}ITKT_Content::instance()->mark_synced($id,$tid);}
        } elseif('product'===$type){foreach($targets as $code=>$lang){$d=(array)($fields[$code]??array());if($d)ITKT_Product_Translations::instance()->save($id,$code,array_map('wp_unslash',$d));}}
        elseif('term'===$type){foreach($targets as $code=>$lang){$d=(array)($fields[$code]??array());if($d)ITKT_Term_Translations::instance()->save($id,$code,array_map('wp_unslash',$d));}}
        elseif('attribute'===$type){foreach($targets as $code=>$lang){$label=wp_unslash($fields[$code]['label']??'');if(''!==trim($label))ITKT_Term_Translations::instance()->save_attribute($taxonomy,$code,$label);}}
        $url=admin_url('admin.php?page=itkt-editor&type='.$type);if($id)$url.='&id='.$id;if($taxonomy)$url.='&taxonomy='.rawurlencode($taxonomy);$url.='&saved=1';wp_safe_redirect($url);exit;
    }

    public function create_translation(){ $source=absint($_GET['source']??0);$language=sanitize_key($_GET['language']??'');if(!current_user_can('edit_post',$source))wp_die('Unauthorized');check_admin_referer('itkt_create_translation_'.$source.'_'.$language);$new=ITKT_Content::instance()->create_translation($source,$language);if(is_wp_error($new))wp_die(esc_html($new->get_error_message()));wp_safe_redirect(ITKT_Content::instance()->edit_url($new));exit; }

    public function link_translation(){
        $source=absint($_POST['source']??0);$target=absint($_POST['target']??0);$language=sanitize_key($_POST['language']??'');
        if(!$source||!$target||!current_user_can('edit_post',$source)||!current_user_can('edit_post',$target))wp_die('Unauthorized');
        check_admin_referer('itkt_link_translation_'.$source.'_'.$language);
        $result=ITKT_Content::instance()->link_existing_translation($source,$target,$language);
        if(is_wp_error($result))wp_die(esc_html($result->get_error_message()));
        wp_safe_redirect(ITKT_Content::instance()->edit_url($target));exit;
    }

    private function product_import_export(){
        $active=$this->active_targets();
        $imported=absint($_GET['imported']??0);$skipped=absint($_GET['skipped']??0);
        if($imported||$skipped){echo '<div class="notice notice-success inline"><p><strong>Import abgeschlossen:</strong> '.intval($imported).' Produkte aktualisiert';if($skipped)echo ', '.intval($skipped).' Zeilen übersprungen';echo '.</p></div>';}
        ?>
        <div class="itkt-grid-2">
            <section class="itkt-card"><span class="itkt-kicker">EXCEL EXPORT</span><h2>Produktübersetzungen exportieren</h2>
                <p>Exportiert jetzt standardmäßig eine <strong>echte Excel-Datei (.xlsx)</strong>. Das ist robuster für Excel, Cloud-Speicher und das Hochladen zu ChatGPT als eine reine CSV-Datei.</p>
                <div class="itkt-export-columns"><strong>Enthalten:</strong><span>Produkt-ID / SKU</span><span>Original: Name / Kurzbeschreibung / Lange Beschreibung</span><span>Individuelle Merkmale: Name + Werte</span><?php foreach($active as $code=>$lang):?><span><?php echo esc_html($lang['flag'].' '.strtoupper($code));?>: Name / Kurzbeschreibung / Lange Beschreibung / URL-Slug / SEO / Merkmale</span><?php endforeach;?></div>
                <p class="itkt-export-actions"><a class="button button-primary itkt-primary" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=itkt_export_products'),'itkt_export_products'));?>">Excel (.xlsx) exportieren</a> <a class="button" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=itkt_export_products_csv'),'itkt_export_products_csv'));?>">CSV als Fallback</a></p>
                <p><small><strong>Empfohlen für ChatGPT:</strong> die <code>.xlsx</code>-Datei hochladen. Die Datei wird zuerst vollständig auf dem Server erzeugt und anschließend mit fester Dateigröße übertragen.</small></p>
            </section>
            <section class="itkt-card"><span class="itkt-kicker">EXCEL IMPORT</span><h2>Übersetzungen importieren</h2>
                <p>Du kannst die exportierte <strong>.xlsx</strong>-Datei direkt in Excel oder ChatGPT bearbeiten und anschließend wieder importieren. CSV bleibt ebenfalls unterstützt. Sicherheitsgrenzen: maximal 25 MB und 10.000 Datenzeilen.</p>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php'));?>" enctype="multipart/form-data">
                    <?php wp_nonce_field('itkt_import_products');?><input type="hidden" name="action" value="itkt_import_products">
                    <label class="itkt-file-drop"><span class="dashicons dashicons-media-spreadsheet"></span><strong>Excel- oder CSV-Datei auswählen</strong><small>.xlsx empfohlen · .csv weiterhin unterstützt</small><input type="file" name="itkt_file" accept=".xlsx,.csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,text/csv" required></label>
                    <label class="itkt-switch-row"><input type="checkbox" name="clear_empty" value="1"><span class="itkt-switch"></span><span><strong>Leere Zellen bewusst löschen</strong><small>Aus: Leere Excel-Zellen lassen vorhandene Übersetzungen unverändert.</small></span></label>
                    <p><button class="button button-primary itkt-primary">Datei importieren</button></p>
                </form>
            </section>
        </div>
        <section class="itkt-card"><span class="itkt-kicker">DATEIFORMAT</span><h2>Dynamisch nach aktiven Sprachen</h2>
            <p>Für jede aktuell aktive Zielsprache werden automatisch die editierbaren Produktfelder exportiert. Aktivierst du später eine weitere Sprache, erscheinen deren Spalten automatisch beim nächsten Export.</p>
            <div class="itkt-table-wrap"><table class="itkt-table"><thead><tr><th>Bereich</th><th>Beispielspalten</th></tr></thead><tbody>
                <tr><td><strong>Produkt</strong></td><td><code>source_title</code> · <code>source_short_description</code> · <code>source_long_description</code></td></tr>
                <tr><td><strong>Je aktive Sprache</strong></td><td><code>en_title</code> · <code>en_short_description</code> · <code>en_long_description</code> · <code>en_slug</code> · <code>en_seo_title</code> · <code>en_seo_description</code> …</td></tr>
                <tr><td><strong>Individuelle Merkmale</strong></td><td><code>attribute_1_key</code> · <code>source_attribute_1_name</code> · <code>source_attribute_1_values</code> · <code>en_attribute_1_name</code> · <code>en_attribute_1_values</code> …</td></tr>
            </tbody></table></div>
            <p><small><strong>Hinweis:</strong> Die <code>attribute_*_key</code>-Spalten bitte nicht verändern. Sie sorgen dafür, dass Übersetzungen wieder dem richtigen WooCommerce-Merkmal desselben Produkts zugeordnet werden.</small></p>
        </section>
        <?php
    }

    private function csv_safe_cell($value){
        $value=(string)$value;
        if(preg_match('/^[=+\-@]/u',$value))$value="'".$value;
        return $value;
    }

    private function csv_import_cell($value){
        $value=(string)$value;
        if(strlen($value)>1&&"'"===$value[0]&&preg_match('/^[=+\-@]/u',substr($value,1)))$value=substr($value,1);
        return $value;
    }

    private function product_export_ids(){
        return get_posts(array('post_type'=>'product','post_status'=>array('publish','draft','private','pending'),'numberposts'=>-1,'fields'=>'ids','orderby'=>'ID','order'=>'ASC','suppress_filters'=>true));
    }

    private function product_export_max_custom_attributes($ids){
        $max=0;
        foreach((array)$ids as $id){$max=max($max,count(ITKT_Product_Translations::instance()->custom_attributes($id)));}
        return $max;
    }

    private function product_export_headers($targets,$max_attrs){
        $headers=array('product_id','sku','source_title','source_short_description','source_long_description');
        for($slot=1;$slot<=$max_attrs;$slot++){$headers[]='attribute_'.$slot.'_key';$headers[]='source_attribute_'.$slot.'_name';$headers[]='source_attribute_'.$slot.'_values';}
        foreach($targets as $code=>$lang){
            $headers[]=$code.'_title';$headers[]=$code.'_short_description';$headers[]=$code.'_long_description';$headers[]=$code.'_slug';$headers[]=$code.'_seo_title';$headers[]=$code.'_seo_description';
            for($slot=1;$slot<=$max_attrs;$slot++){$headers[]=$code.'_attribute_'.$slot.'_name';$headers[]=$code.'_attribute_'.$slot.'_values';}
        }
        return $headers;
    }

    private function product_export_row($id,$targets,$max_attrs){
        $post=get_post($id);$custom=ITKT_Product_Translations::instance()->custom_attributes($id);$keys=array_keys($custom);
        $row=array($id,get_post_meta($id,'_sku',true),$post?$post->post_title:'',$post?$post->post_excerpt:'',$post?$post->post_content:'');
        for($i=0;$i<$max_attrs;$i++){$key=$keys[$i]??'';$attr=$key?(array)($custom[$key]??array()):array();$row[]=$key;$row[]=$attr['name']??'';$row[]=isset($attr['values'])?implode(', ',(array)$attr['values']):'';}
        foreach($targets as $code=>$lang){
            $d=ITKT_Product_Translations::instance()->get($id,$code);$row[]=$d['title']??'';$row[]=$d['short_description']??'';$row[]=$d['description']??'';$row[]=ITKT_Product_Translations::instance()->explicit_slug($id,$code);$row[]=$d['seo_title']??'';$row[]=$d['seo_description']??'';
            for($i=0;$i<$max_attrs;$i++){$key=$keys[$i]??'';$td=$key?(array)($d['custom_attributes'][$key]??array()):array();$row[]=$td['label']??'';$row[]=$td['values']??'';}
        }
        return $row;
    }

    private function stream_download_file($path,$content_type,$filename){
        if(!is_file($path))wp_die('Exportdatei konnte nicht erzeugt werden.');
        while(ob_get_level()){ob_end_clean();}
        nocache_headers();
        header('Content-Type: '.$content_type);
        header('Content-Disposition: attachment; filename="'.$filename.'"');
        header('Content-Transfer-Encoding: binary');
        header('X-Content-Type-Options: nosniff');
        header('Content-Length: '.filesize($path));
        readfile($path);
        @unlink($path);
        exit;
    }

    private function xlsx_column_name($number){
        $name='';$number=(int)$number;
        while($number>0){$number--; $name=chr(65+($number%26)).$name; $number=intdiv($number,26);}
        return $name;
    }

    private function xlsx_column_index($letters){
        $index=0;$letters=strtoupper((string)$letters);
        for($i=0,$len=strlen($letters);$i<$len;$i++){$index=$index*26+(ord($letters[$i])-64);}
        return max(0,$index-1);
    }

    private function xlsx_xml_text($value){
        $value=wp_check_invalid_utf8((string)$value,true);
        $value=preg_replace('/[^\x09\x0A\x0D\x20-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u','',$value);
        return htmlspecialchars($value,ENT_XML1|ENT_QUOTES,'UTF-8');
    }

    private function xlsx_cell_xml($ref,$value,$style=2){
        return '<c r="'.$ref.'" s="'.intval($style).'" t="inlineStr"><is><t xml:space="preserve">'.$this->xlsx_xml_text($value).'</t></is></c>';
    }

    private function xlsx_column_width($header){
        $header=(string)$header;
        if('product_id'===$header)return 12;
        if('sku'===$header)return 20;
        if(false!==strpos($header,'description'))return 58;
        if(false!==strpos($header,'values'))return 36;
        if(false!==strpos($header,'title')||false!==strpos($header,'_name'))return 32;
        if(false!==strpos($header,'_key'))return 24;
        return 24;
    }

    private function create_products_xlsx($ids,$targets,$max_attrs,$headers){
        if(!class_exists('ZipArchive'))return new WP_Error('itkt_zip_missing','Die PHP-Erweiterung ZipArchive fehlt. Bitte nutze den CSV-Fallback oder aktiviere php-zip beim Hosting.');
        $sheet_tmp=wp_tempnam('itkt-products-sheet.xml');$xlsx_tmp=wp_tempnam('it-kayali-product-translations.xlsx');
        if(!$sheet_tmp||!$xlsx_tmp)return new WP_Error('itkt_temp_failed','Temporäre Exportdatei konnte nicht angelegt werden.');
        $fh=fopen($sheet_tmp,'wb');if(!$fh)return new WP_Error('itkt_sheet_failed','Excel-Arbeitsblatt konnte nicht erzeugt werden.');
        $col_count=count($headers);$end_col=$this->xlsx_column_name($col_count);$end_row=count($ids)+1;
        fwrite($fh,'<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><dimension ref="A1:'.$end_col.$end_row.'"/><sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/><selection pane="bottomLeft" activeCell="A2" sqref="A2"/></sheetView></sheetViews><sheetFormatPr defaultRowHeight="18"/><cols>');
        foreach($headers as $i=>$header){$n=$i+1;fwrite($fh,'<col min="'.$n.'" max="'.$n.'" width="'.$this->xlsx_column_width($header).'" customWidth="1"/>');}
        fwrite($fh,'</cols><sheetData><row r="1" ht="28" customHeight="1">');
        foreach($headers as $i=>$header){fwrite($fh,$this->xlsx_cell_xml($this->xlsx_column_name($i+1).'1',$header,1));}
        fwrite($fh,'</row>');
        $excel_row=2;
        foreach($ids as $id){
            $row=$this->product_export_row($id,$targets,$max_attrs);fwrite($fh,'<row r="'.$excel_row.'">');
            foreach($headers as $i=>$header){$style=('product_id'===$header||'sku'===$header||0===strpos($header,'source_')||0===strpos($header,'attribute_'))?3:2;fwrite($fh,$this->xlsx_cell_xml($this->xlsx_column_name($i+1).$excel_row,$row[$i]??'',$style));}
            fwrite($fh,'</row>');$excel_row++;
        }
        fwrite($fh,'</sheetData><autoFilter ref="A1:'.$end_col.$end_row.'"/></worksheet>');fclose($fh);

        $content_types='<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/></Types>';
        $rels='<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>';
        $workbook='<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Produktübersetzungen" sheetId="1" r:id="rId1"/></sheets></workbook>';
        $workbook_rels='<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>';
        $styles='<?xml version="1.0" encoding="UTF-8" standalone="yes"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><fonts count="2"><font><sz val="11"/><name val="Calibri"/><family val="2"/></font><font><b/><color rgb="FFFFFFFF"/><sz val="11"/><name val="Calibri"/><family val="2"/></font></fonts><fills count="4"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FF071D32"/><bgColor indexed="64"/></patternFill></fill><fill><patternFill patternType="solid"><fgColor rgb="FFF3F6F9"/><bgColor indexed="64"/></patternFill></fill></fills><borders count="2"><border><left/><right/><top/><bottom/><diagonal/></border><border><left style="thin"><color rgb="FFD8E1EA"/></left><right style="thin"><color rgb="FFD8E1EA"/></right><top style="thin"><color rgb="FFD8E1EA"/></top><bottom style="thin"><color rgb="FFD8E1EA"/></bottom><diagonal/></border></borders><cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs><cellXfs count="4"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/><xf numFmtId="0" fontId="1" fillId="2" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment vertical="center" wrapText="1"/></xf><xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1" applyAlignment="1"><alignment vertical="top" wrapText="1"/></xf><xf numFmtId="0" fontId="0" fillId="3" borderId="1" xfId="0" applyFill="1" applyBorder="1" applyAlignment="1"><alignment vertical="top" wrapText="1"/></xf></cellXfs><cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles></styleSheet>';

        $zip=new ZipArchive();$opened=$zip->open($xlsx_tmp,ZipArchive::CREATE|ZipArchive::OVERWRITE);
        if(true!==$opened){@unlink($sheet_tmp);@unlink($xlsx_tmp);return new WP_Error('itkt_zip_failed','Excel-Datei konnte nicht gepackt werden.');}
        $zip->addFromString('[Content_Types].xml',$content_types);$zip->addFromString('_rels/.rels',$rels);$zip->addFromString('xl/workbook.xml',$workbook);$zip->addFromString('xl/_rels/workbook.xml.rels',$workbook_rels);$zip->addFromString('xl/styles.xml',$styles);$zip->addFile($sheet_tmp,'xl/worksheets/sheet1.xml');$zip->close();@unlink($sheet_tmp);
        return $xlsx_tmp;
    }

    public function export_products(){
        if(!current_user_can('edit_products'))wp_die('Unauthorized');check_admin_referer('itkt_export_products');
        $targets=$this->active_targets();$ids=$this->product_export_ids();$max_attrs=$this->product_export_max_custom_attributes($ids);$headers=$this->product_export_headers($targets,$max_attrs);
        $path=$this->create_products_xlsx($ids,$targets,$max_attrs,$headers);
        if(is_wp_error($path))wp_die(esc_html($path->get_error_message()));
        $this->stream_download_file($path,'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet','it-kayali-product-translations-'.gmdate('Y-m-d').'.xlsx');
    }

    public function export_products_csv(){
        if(!current_user_can('edit_products'))wp_die('Unauthorized');check_admin_referer('itkt_export_products_csv');
        $targets=$this->active_targets();$ids=$this->product_export_ids();$max_attrs=$this->product_export_max_custom_attributes($ids);$headers=$this->product_export_headers($targets,$max_attrs);
        $path=wp_tempnam('it-kayali-product-translations.csv');if(!$path)wp_die('CSV-Datei konnte nicht erzeugt werden.');$out=fopen($path,'wb');if(!$out)wp_die('CSV-Datei konnte nicht geschrieben werden.');
        fwrite($out,"\xEF\xBB\xBF");fputcsv($out,$headers,';','"','\\');foreach($ids as $id){fputcsv($out,array_map(array($this,'csv_safe_cell'),$this->product_export_row($id,$targets,$max_attrs)),';','"','\\');}fclose($out);
        $this->stream_download_file($path,'text/csv; charset=UTF-8','it-kayali-product-translations-'.gmdate('Y-m-d').'.csv');
    }

    private function import_cell_within_limit( $value ) {
        return strlen( (string) $value ) <= self::PRODUCT_IMPORT_MAX_CELL_BYTES;
    }

    private function read_csv_import_file($tmp){
        $fh=fopen($tmp,'r');if(!$fh)return new WP_Error('itkt_csv_read','CSV-Datei konnte nicht gelesen werden.');
        $first=fgets($fh);if(false===$first){fclose($fh);return new WP_Error('itkt_csv_empty','CSV-Datei ist leer.');}
        $first=preg_replace('/^\xEF\xBB\xBF/','',$first);$delimiter=substr_count($first,';')>=substr_count($first,',')?';':',';rewind($fh);
        $headers=fgetcsv($fh,0,$delimiter,'"','\\');if(!$headers){fclose($fh);return new WP_Error('itkt_csv_headers','CSV-Kopfzeile fehlt.');}
        if(count($headers)>self::PRODUCT_IMPORT_MAX_COLUMNS){fclose($fh);return new WP_Error('itkt_import_columns_limit','Die Importdatei enthält zu viele Spalten.');}
        foreach($headers as $value){if(!$this->import_cell_within_limit($value)){fclose($fh);return new WP_Error('itkt_import_cell_limit','Eine Zelle überschreitet das Sicherheitslimit.');}}
        $rows=array();$row_count=0;
        while(($row=fgetcsv($fh,0,$delimiter,'"','\\'))!==false){
            $row_count++;if($row_count>self::PRODUCT_IMPORT_MAX_ROWS){fclose($fh);return new WP_Error('itkt_import_rows_limit','Die Importdatei enthält mehr als 10.000 Datenzeilen.');}
            if(count($row)>self::PRODUCT_IMPORT_MAX_COLUMNS){fclose($fh);return new WP_Error('itkt_import_columns_limit','Die Importdatei enthält zu viele Spalten.');}
            foreach($row as $value){if(!$this->import_cell_within_limit($value)){fclose($fh);return new WP_Error('itkt_import_cell_limit','Eine Zelle überschreitet das Sicherheitslimit.');}}
            $rows[]=$row;
        }
        fclose($fh);return array($headers,$rows);
    }

    private function normalize_xlsx_path($base,$target){
        $target=str_replace('\\','/',(string)$target);if(0===strpos($target,'/'))return ltrim($target,'/');
        $parts=explode('/',trim(dirname($base).'/'.$target,'/'));$stack=array();foreach($parts as $part){if(''===$part||'.'===$part)continue;if('..'===$part){array_pop($stack);continue;}$stack[]=$part;}return implode('/',$stack);
    }

    private function xlsx_xml_part( $zip, $path, $required = true ) {
        $stat = $zip->statName( $path );
        if ( false === $stat ) {
            return $required ? new WP_Error( 'itkt_xlsx_part', 'Excel-Datei ist unvollständig: ' . $path ) : '';
        }
        $size = absint( $stat['size'] ?? 0 );
        if ( $size > self::PRODUCT_IMPORT_MAX_XML_BYTES ) {
            return new WP_Error( 'itkt_xlsx_part_size', 'Ein Excel-Bestandteil überschreitet das Sicherheitslimit.' );
        }
        $xml = $zip->getFromName( $path );
        if ( false === $xml ) {
            return $required ? new WP_Error( 'itkt_xlsx_part', 'Excel-Bestandteil konnte nicht gelesen werden: ' . $path ) : '';
        }
        if ( false !== stripos( $xml, '<!DOCTYPE' ) || false !== stripos( $xml, '<!ENTITY' ) ) {
            return new WP_Error( 'itkt_xlsx_unsafe_xml', 'Die Excel-Datei enthält nicht erlaubte XML-Deklarationen.' );
        }
        return $xml;
    }

    private function xlsx_parse_xml( $xml, $label ) {
        $previous = libxml_use_internal_errors( true );
        libxml_clear_errors();
        $flags = defined( 'LIBXML_NONET' ) ? LIBXML_NONET : 0;
        if ( defined( 'LIBXML_COMPACT' ) ) { $flags |= LIBXML_COMPACT; }
        $sx = simplexml_load_string( (string) $xml, 'SimpleXMLElement', $flags );
        libxml_clear_errors();
        libxml_use_internal_errors( $previous );
        return false === $sx ? new WP_Error( 'itkt_xlsx_xml', $label . ' ist ungültig.' ) : $sx;
    }

    private function xlsx_shared_strings($zip){
        $xml=$this->xlsx_xml_part($zip,'xl/sharedStrings.xml',false);if(is_wp_error($xml))return $xml;if(''===$xml)return array();
        $sx=$this->xlsx_parse_xml($xml,'Excel Shared-Strings');if(is_wp_error($sx))return $sx;$ns=$sx->getNamespaces(true);$main=$ns['']??'http://schemas.openxmlformats.org/spreadsheetml/2006/main';$out=array();$count=0;
        foreach($sx->children($main)->si as $si){$count++;if($count>self::PRODUCT_IMPORT_MAX_SHARED_STRINGS)return new WP_Error('itkt_xlsx_strings_limit','Die Excel-Datei enthält zu viele Shared-Strings.');$si->registerXPathNamespace('x',$main);$parts=$si->xpath('.//x:t');$text='';foreach((array)$parts as $part)$text.=(string)$part;if(!$this->import_cell_within_limit($text))return new WP_Error('itkt_import_cell_limit','Eine Excel-Zelle überschreitet das Sicherheitslimit.');$out[]=$text;}
        return $out;
    }

    private function xlsx_first_sheet_path($zip){
        $workbook=$this->xlsx_xml_part($zip,'xl/workbook.xml',true);if(is_wp_error($workbook))return $workbook;$rels=$this->xlsx_xml_part($zip,'xl/_rels/workbook.xml.rels',true);if(is_wp_error($rels))return $rels;
        $wb=$this->xlsx_parse_xml($workbook,'Excel-Arbeitsmappe');if(is_wp_error($wb))return $wb;$rx=$this->xlsx_parse_xml($rels,'Excel-Beziehungen');if(is_wp_error($rx))return $rx;$ns=$wb->getNamespaces(true);$main=$ns['']??'http://schemas.openxmlformats.org/spreadsheetml/2006/main';$sheets=$wb->children($main)->sheets;if(!$sheets||!isset($sheets->sheet[0]))return new WP_Error('itkt_xlsx_sheet','Excel-Arbeitsblatt wurde nicht gefunden.');
        $attrs=$sheets->sheet[0]->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships');$rid=(string)($attrs['id']??'');if(!$rid)return new WP_Error('itkt_xlsx_sheet','Excel-Arbeitsblatt-Verknüpfung fehlt.');$rns=$rx->getNamespaces(true);$rmain=$rns['']??'http://schemas.openxmlformats.org/package/2006/relationships';foreach($rx->children($rmain)->Relationship as $rel){$a=$rel->attributes();if((string)$a['Id']===$rid){$path=$this->normalize_xlsx_path('xl/workbook.xml',(string)$a['Target']);if(!preg_match('#^xl/worksheets/[^/]+\.xml$#i',$path))return new WP_Error('itkt_xlsx_sheet_path','Ungültiger Excel-Arbeitsblattpfad.');return $path;}}
        return new WP_Error('itkt_xlsx_sheet','Excel-Arbeitsblatt wurde nicht gefunden.');
    }

    private function xlsx_cell_value($cell,$main,$shared){
        $attrs=$cell->attributes();$type=(string)($attrs['t']??'');$children=$cell->children($main);
        if('s'===$type){$idx=(int)($children->v??0);return (string)($shared[$idx]??'');}
        if('inlineStr'===$type){$cell->registerXPathNamespace('x',$main);$parts=$cell->xpath('.//x:is//x:t');$text='';foreach((array)$parts as $part)$text.=(string)$part;return $text;}
        if('b'===$type)return ((string)($children->v??'0'))==='1'?'1':'0';
        return isset($children->v)?(string)$children->v:'';
    }

    private function read_xlsx_import_file($tmp){
        if(!class_exists('ZipArchive'))return new WP_Error('itkt_zip_missing','XLSX-Import benötigt die PHP-Erweiterung ZipArchive.');
        $zip=new ZipArchive();if(true!==$zip->open($tmp))return new WP_Error('itkt_xlsx_open','Excel-Datei konnte nicht geöffnet werden.');
        $shared=$this->xlsx_shared_strings($zip);if(is_wp_error($shared)){$zip->close();return $shared;}
        $sheet_path=$this->xlsx_first_sheet_path($zip);if(is_wp_error($sheet_path)){$zip->close();return $sheet_path;}
        $xml=$this->xlsx_xml_part($zip,$sheet_path,true);if(is_wp_error($xml)){$zip->close();return $xml;}
        $sx=$this->xlsx_parse_xml($xml,'Excel-Arbeitsblatt');if(is_wp_error($sx)){$zip->close();return $sx;}$ns=$sx->getNamespaces(true);$main=$ns['']??'http://schemas.openxmlformats.org/spreadsheetml/2006/main';$rows=array();$row_count=0;
        foreach($sx->children($main)->sheetData->row as $row_node){
            $row_count++;if($row_count>self::PRODUCT_IMPORT_MAX_ROWS+1){$zip->close();return new WP_Error('itkt_import_rows_limit','Die Excel-Datei enthält mehr als 10.000 Datenzeilen.');}
            $line=array();$fallback_col=0;
            foreach($row_node->children($main)->c as $cell){$ref=(string)($cell->attributes()['r']??'');if($ref&&preg_match('/^([A-Z]+)\d+$/i',$ref,$m))$col=$this->xlsx_column_index($m[1]);else $col=$fallback_col;if($col>=self::PRODUCT_IMPORT_MAX_COLUMNS){$zip->close();return new WP_Error('itkt_import_columns_limit','Die Excel-Datei enthält zu viele Spalten.');}$value=$this->xlsx_cell_value($cell,$main,$shared);if(!$this->import_cell_within_limit($value)){$zip->close();return new WP_Error('itkt_import_cell_limit','Eine Excel-Zelle überschreitet das Sicherheitslimit.');}$line[$col]=$value;$fallback_col=$col+1;}
            if($line){ksort($line);$max=max(array_keys($line));if($max>=self::PRODUCT_IMPORT_MAX_COLUMNS){$zip->close();return new WP_Error('itkt_import_columns_limit','Die Excel-Datei enthält zu viele Spalten.');}$full=array_fill(0,$max+1,'');foreach($line as $i=>$value)$full[$i]=$value;$rows[]=$full;}
        }
        $zip->close();if(!$rows)return new WP_Error('itkt_xlsx_empty','Excel-Datei enthält keine Daten.');$headers=array_shift($rows);return array($headers,$rows);
    }

    private function apply_product_import_rows($headers,$rows,$clear){
        $headers=array_map(function($h){return sanitize_key(trim(preg_replace('/^\xEF\xBB\xBF/','',(string)$h)));},$headers);if(count($headers)>self::PRODUCT_IMPORT_MAX_COLUMNS)return new WP_Error('itkt_import_columns_limit','Die Importdatei enthält zu viele Spalten.');
        $non_empty_headers=array_values(array_filter($headers,function($h){return ''!==$h;}));if(count($non_empty_headers)!==count(array_unique($non_empty_headers)))return new WP_Error('itkt_import_duplicate_columns','Die Importdatei enthält doppelte Spaltennamen.');
        $index=array_flip($headers);if(!isset($index['product_id'])&&!isset($index['sku']))return new WP_Error('itkt_import_columns','Datei benötigt product_id oder sku.');
        $attribute_slots=array();foreach($headers as $header){if(preg_match('/^attribute_(\d+)_key$/',$header,$m))$attribute_slots[(int)$m[1]]=true;}ksort($attribute_slots);$targets=$this->active_targets();$updated=0;$skipped=0;$seen=array();
        foreach((array)$rows as $row){if(!array_filter($row,function($v){return ''!==trim((string)$v);}))continue;
            $row_id=isset($index['product_id'])?absint($row[$index['product_id']]??0):0;$row_sku=isset($index['sku'])?sanitize_text_field($this->csv_import_cell($row[$index['sku']]??'')):'';$id=0;
            if($row_id&&'product'===get_post_type($row_id)){$current_sku=(string)get_post_meta($row_id,'_sku',true);if(''!==$row_sku&&!hash_equals($current_sku,$row_sku)){$skipped++;continue;}$id=$row_id;}
            elseif(''!==$row_sku&&function_exists('wc_get_product_id_by_sku')){$id=absint(wc_get_product_id_by_sku($row_sku));}
            if(!$id||'product'!==get_post_type($id)||!current_user_can('edit_post',$id)||isset($seen[$id])){$skipped++;continue;}$seen[$id]=true;
            $source_custom=ITKT_Product_Translations::instance()->custom_attributes($id);$source_keys=array_keys($source_custom);$changed=false;
            foreach($targets as $code=>$lang){$old=ITKT_Product_Translations::instance()->get($id,$code);$data=array('title'=>$old['title']??'','short_description'=>$old['short_description']??'','description'=>$old['description']??'','slug'=>$old['slug']??'','seo_title'=>$old['seo_title']??'','seo_description'=>$old['seo_description']??'','custom_attributes'=>(array)($old['custom_attributes']??array()));$lang_changed=false;
                foreach(array('title','short_description','description','slug','seo_title','seo_description') as $field){$key=$code.'_'.$field;if('description'===$field){$new_key=$code.'_long_description';$legacy_key=$code.'_description';$key=isset($index[$new_key])?$new_key:$legacy_key;}if(!isset($index[$key]))continue;$value=$this->csv_import_cell($row[$index[$key]]??'');if($clear||''!==trim($value)){$data[$field]=$value;$lang_changed=true;}}
                foreach(array_keys($attribute_slots) as $slot){$key_col='attribute_'.$slot.'_key';$attr_key=isset($index[$key_col])?sanitize_key($this->csv_import_cell($row[$index[$key_col]]??'')):'';if(!$attr_key){$attr_key=$source_keys[$slot-1]??'';}if(!$attr_key||!isset($source_custom[$attr_key]))continue;$current=(array)($data['custom_attributes'][$attr_key]??array('label'=>'','values'=>''));foreach(array('name'=>'label','values'=>'values') as $suffix=>$storage){$col=$code.'_attribute_'.$slot.'_'.$suffix;if(!isset($index[$col]))continue;$value=$this->csv_import_cell($row[$index[$col]]??'');if($clear||''!==trim($value)){$current[$storage]=$value;$lang_changed=true;}}$data['custom_attributes'][$attr_key]=$current;}
                if($lang_changed){ITKT_Product_Translations::instance()->save($id,$code,$data);$changed=true;}
            }
            if($changed)$updated++;else $skipped++;
        }
        return array($updated,$skipped);
    }

    public function import_products(){
        if(!current_user_can('edit_products'))wp_die('Unauthorized');check_admin_referer('itkt_import_products');
        $file=$_FILES['itkt_file']??null;if(!is_array($file)||UPLOAD_ERR_OK!==(int)($file['error']??UPLOAD_ERR_NO_FILE)||empty($file['tmp_name'])||!is_uploaded_file($file['tmp_name']))wp_die('Keine gültige Excel- oder CSV-Datei hochgeladen.');
        $size=absint($file['size']??0);if(!$size||$size>self::PRODUCT_IMPORT_MAX_UPLOAD_BYTES)wp_die('Die Importdatei ist leer oder größer als 25 MB.');
        $tmp=(string)$file['tmp_name'];$name=sanitize_file_name(wp_unslash($file['name']??''));$ext=strtolower(pathinfo($name,PATHINFO_EXTENSION));if(!in_array($ext,array('xlsx','csv'),true))wp_die('Bitte eine .xlsx- oder .csv-Datei verwenden.');
        $parsed=('xlsx'===$ext)?$this->read_xlsx_import_file($tmp):$this->read_csv_import_file($tmp);if(is_wp_error($parsed))wp_die(esc_html($parsed->get_error_message()));list($headers,$rows)=$parsed;$result=$this->apply_product_import_rows($headers,$rows,!empty($_POST['clear_empty']));if(is_wp_error($result))wp_die(esc_html($result->get_error_message()));list($updated,$skipped)=$result;wp_safe_redirect(admin_url('admin.php?page=itkt-products&sub=import-export&imported='.$updated.'&skipped='.$skipped));exit;
    }

    public function repair_elementor_layout(){ $source=absint($_GET['source']??0);$translation=absint($_GET['translation']??0);if(!$source||!$translation||!current_user_can('edit_post',$source)||!current_user_can('edit_post',$translation))wp_die('Unauthorized');check_admin_referer('itkt_repair_elementor_layout_'.$source.'_'.$translation);$result=ITKT_Content::instance()->repair_elementor_layout($source,$translation);if(is_wp_error($result))wp_die(esc_html($result->get_error_message()));wp_safe_redirect(add_query_arg('itkt_layout_repaired','1',ITKT_Content::instance()->edit_url($translation)));exit; }

    private function status_label($status){$labels=array('missing'=>'Fehlt','partial'=>'Teilweise','draft'=>'Entwurf','outdated'=>'Veraltet','complete'=>'Fertig','manual'=>'Manuell');return $labels[$status]??ucfirst($status);}

    public function menus(){
        if(!current_user_can('edit_theme_options'))return;
        $locations=get_registered_nav_menus();$theme_locations=get_nav_menu_locations();$menus=wp_get_nav_menus();$langs=ITKT_Languages::instance()->get_active();$default=ITKT_Languages::instance()->get_default_code();$map=get_option('itkt_menu_map',array());$direct=get_option('itkt_direct_menu_map',array());
        $this->header('Menüs','Menüs können über WordPress-Menüpositionen und zusätzlich direkt nach Menü-ID zugeordnet werden. Die direkte Zuordnung ist besonders für WoodMart Header Builder wichtig.');
        echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'">';wp_nonce_field('itkt_save_menus');echo '<input type="hidden" name="action" value="itkt_save_menus">';
        if($locations){echo '<section class="itkt-card"><div class="itkt-card-head"><div><span class="itkt-kicker">THEME POSITIONEN</span><h2>Menüs je Menüposition</h2></div><code>[itkt_language_switcher]</code></div><div class="itkt-table-wrap"><table class="itkt-table itkt-menu-table"><thead><tr><th>Menüposition</th>';foreach($langs as $code=>$lang)echo '<th>'.esc_html($lang['flag'].' '.$lang['native_name']).'</th>';echo '</tr></thead><tbody>';
            foreach($locations as $location=>$label){echo '<tr><td><strong>'.esc_html($label).'</strong><small>'.esc_html($location).'</small></td>';foreach($langs as $code=>$lang){$selected=absint($map[$location][$code]??0);if(!$selected&&$code===$default)$selected=absint($theme_locations[$location]??0);echo '<td><select name="menu_map['.esc_attr($location).']['.esc_attr($code).']"><option value="0">— Kein Menü —</option>';foreach($menus as $menu)echo '<option value="'.intval($menu->term_id).'" '.selected($selected,$menu->term_id,false).'>'.esc_html($menu->name).'</option>';echo '</select>';if($code!==$default&&!$selected){$source=absint($map[$location][$default]??($theme_locations[$location]??0));if($source){$url=wp_nonce_url(admin_url('admin-post.php?action=itkt_clone_menu&location='.rawurlencode($location).'&language='.rawurlencode($code).'&source='.$source),'itkt_clone_menu_'.$location.'_'.$code);echo '<a class="itkt-mini-link itkt-create-menu" href="'.esc_url($url).'">Menü erstellen</a>';}}echo '</td>';}echo '</tr>';}
            echo '</tbody></table></div></section>';}
        echo '<section class="itkt-card"><div class="itkt-card-head"><div><span class="itkt-kicker">WOODMART / DIREKTE MENÜS</span><h2>Direkt eingebundene Menüs</h2></div><span class="itkt-pill orange">WoodMart kompatibel</span></div><p>Wenn ein Header Builder ein Menü direkt auswählt und keine WordPress-Menüposition benutzt, ordnest du hier das Basis-Menü seinen Sprachmenüs zu.</p><div class="itkt-table-wrap"><table class="itkt-table itkt-menu-table"><thead><tr><th>Basis-Menü</th>';
        foreach($langs as $code=>$lang){if($code===$default)continue;echo '<th>'.esc_html($lang['flag'].' '.$lang['native_name']).'</th>';}
        echo '</tr></thead><tbody>';foreach($menus as $source_menu){echo '<tr><td><strong>'.esc_html($source_menu->name).'</strong><small>ID #'.intval($source_menu->term_id).'</small></td>';foreach($langs as $code=>$lang){if($code===$default)continue;$selected=absint($direct[$source_menu->term_id][$code]??0);echo '<td><select name="direct_menu_map['.intval($source_menu->term_id).']['.esc_attr($code).']"><option value="0">— Keine Zuordnung —</option>';foreach($menus as $menu){if($menu->term_id==$source_menu->term_id)continue;echo '<option value="'.intval($menu->term_id).'" '.selected($selected,$menu->term_id,false).'>'.esc_html($menu->name).'</option>';}echo '</select></td>';}echo '</tr>';}
        echo '</tbody></table></div></section><div class="itkt-savebar"><span>WordPress-Menüpositionen und direkte WoodMart-Menüs werden im Frontend automatisch gewechselt.</span><button class="button button-primary itkt-primary">Menüs speichern</button></div></form>';
        echo '<section class="itkt-card"><span class="itkt-kicker">SPRACHUMSCHALTER</span><h2>Flaggen im Frontend anzeigen</h2><p>Standardmäßig zeigt der Shortcode jetzt große Flaggen ohne Hintergrund:</p><p><code>[itkt_language_switcher]</code></p><p>Mit Sprachcode: <code>[itkt_language_switcher labels="1" style="pills"]</code></p></section>';
        $this->footer();
    }


    public function switcher() {
        if ( ! current_user_can( 'edit_theme_options' ) ) { return; }

        $config = ITKT_Frontend::instance()->switcher_settings();
        $this->header(
            'Sprachumschalter',
            'Globale Darstellung für Shortcodes, Elementor, Footer und neue WoodMart-Header-Elemente. WoodMart kann diese Werte bei Bedarf pro Element überschreiben.'
        );

        if ( ! empty( $_GET['switcher_saved'] ) ) {
            echo '<div class="notice notice-success inline"><p><strong>Gespeichert.</strong> Die globalen Sprachumschalter-Einstellungen wurden aktualisiert.</p></div>';
        }
        ?>
        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="itkt-switcher-settings-form">
            <?php wp_nonce_field( 'itkt_save_switcher_settings' ); ?>
            <input type="hidden" name="action" value="itkt_save_switcher_settings">

            <section class="itkt-card">
                <div class="itkt-card-head"><div><span class="itkt-kicker">GLOBAL</span><h2>Darstellung & Verhalten</h2></div><span class="itkt-pill blue">gilt auch für Shortcodes</span></div>
                <p>Der Shortcode <code>[itkt_language_switcher]</code> verwendet diese Werte automatisch. Mit <code>style="dropdown"</code> erhältst du einen normalen Dropdown-Umschalter im Layout; mit <code>style="floating" fixed="1"</code> einen separat fixierten Floating-Umschalter. Responsive Abstände bleiben zentral steuerbar.</p>
                <div class="itkt-field-grid">
                    <label class="itkt-field"><span>Darstellung</span>
                        <select name="switcher[style]">
                            <option value="flags" <?php selected( $config['style'], 'flags' ); ?>>Flaggen nebeneinander</option>
                            <option value="pills" <?php selected( $config['style'], 'pills' ); ?>>Pills</option>
                            <option value="text" <?php selected( $config['style'], 'text' ); ?>>Text</option>
                            <option value="dropdown" <?php selected( $config['style'], 'dropdown' ); ?>>Dropdown</option>
                            <option value="floating" <?php selected( $config['style'], 'floating' ); ?>>Floating Button</option>
                        </select>
                    </label>
                    <label class="itkt-field"><span>Dropdown-Öffnungsrichtung</span>
                        <select name="switcher[dropdown_direction]">
                            <option value="auto" <?php selected( $config['dropdown_direction'], 'auto' ); ?>>Automatisch je nach freiem Platz</option>
                            <option value="up" <?php selected( $config['dropdown_direction'], 'up' ); ?>>Immer nach oben</option>
                            <option value="down" <?php selected( $config['dropdown_direction'], 'down' ); ?>>Immer nach unten</option>
                        </select>
                    </label>
                    <label class="itkt-field"><span>Floating-Position vertikal</span>
                        <select name="switcher[floating_vertical]">
                            <option value="bottom" <?php selected( $config['floating_vertical'], 'bottom' ); ?>>Unten</option>
                            <option value="top" <?php selected( $config['floating_vertical'], 'top' ); ?>>Oben</option>
                        </select>
                    </label>
                </div>
                <div class="itkt-switcher-option-grid">
                    <label class="itkt-switch-row"><input type="checkbox" name="switcher[show_codes]" value="1" <?php checked( ! empty( $config['show_codes'] ) ); ?>><span class="itkt-switch"></span><span><strong>Sprachkürzel anzeigen</strong><small>DE, EN, AR usw.</small></span></label>
                    <label class="itkt-switch-row"><input type="checkbox" name="switcher[show_names]" value="1" <?php checked( ! empty( $config['show_names'] ) ); ?>><span class="itkt-switch"></span><span><strong>Sprachname am Trigger</strong><small>Deutsch, English, العربية usw.</small></span></label>
                    <label class="itkt-switch-row"><input type="checkbox" name="switcher[mobile_dropdown]" value="1" <?php checked( ! empty( $config['mobile_dropdown'] ) ); ?>><span class="itkt-switch"></span><span><strong>Mobile immer als Dropdown</strong><small>Auf dem Handy ist nur die aktive Sprache sichtbar.</small></span></label>
                    <label class="itkt-switch-row"><input type="checkbox" name="switcher[floating_fixed]" value="1" <?php checked( ! empty( $config['floating_fixed'] ) ); ?>><span class="itkt-switch"></span><span><strong>Am Bildschirm fixieren</strong><small>Bleibt beim Scrollen sichtbar.</small></span></label>
                </div>
            </section>

            <section class="itkt-card">
                <div class="itkt-card-head"><div><span class="itkt-kicker">RESPONSIVE</span><h2>Abstände je Gerät</h2></div><span class="itkt-pill orange">px</span></div>
                <p>Desktop, Tablet und Mobile werden getrennt gespeichert. Für einen Floating Button sind besonders <strong>Horizontaler Randabstand</strong>, <strong>Vertikaler Randabstand</strong> und <strong>Trigger → Dropdown</strong> wichtig.</p>
                <div class="itkt-tabs itkt-switcher-device-tabs" role="tablist">
                    <button type="button" class="is-active" data-itkt-device-tab="desktop">Desktop</button>
                    <button type="button" data-itkt-device-tab="tablet">Tablet</button>
                    <button type="button" data-itkt-device-tab="mobile">Mobile</button>
                </div>
                <?php foreach ( array( 'desktop'=>'Desktop', 'tablet'=>'Tablet', 'mobile'=>'Mobile' ) as $device => $label ) :
                    $row = $config['devices'][ $device ]; ?>
                    <div class="itkt-switcher-device-panel <?php echo 'desktop' === $device ? 'is-active' : ''; ?>" data-itkt-device-panel="<?php echo esc_attr( $device ); ?>">
                        <h3><?php echo esc_html( $label ); ?></h3>
                        <div class="itkt-field-grid">
                            <label class="itkt-field"><span>Ausrichtung</span><select name="switcher[devices][<?php echo esc_attr($device); ?>][align]"><option value="left" <?php selected($row['align'],'left');?>>Links</option><option value="center" <?php selected($row['align'],'center');?>>Mitte</option><option value="right" <?php selected($row['align'],'right');?>>Rechts</option></select></label>
                            <label class="itkt-field"><span>Abstand zwischen Sprachen</span><input type="number" min="0" max="100" step="1" name="switcher[devices][<?php echo esc_attr($device); ?>][gap]" value="<?php echo esc_attr($row['gap']); ?>"></label>
                            <label class="itkt-field"><span>Innenabstand horizontal</span><input type="number" min="0" max="80" step="1" name="switcher[devices][<?php echo esc_attr($device); ?>][padding_x]" value="<?php echo esc_attr($row['padding_x']); ?>"></label>
                            <label class="itkt-field"><span>Innenabstand vertikal</span><input type="number" min="0" max="80" step="1" name="switcher[devices][<?php echo esc_attr($device); ?>][padding_y]" value="<?php echo esc_attr($row['padding_y']); ?>"></label>
                            <label class="itkt-field"><span>Floating-Randabstand horizontal</span><input type="number" min="0" max="300" step="1" name="switcher[devices][<?php echo esc_attr($device); ?>][offset_x]" value="<?php echo esc_attr($row['offset_x']); ?>"></label>
                            <label class="itkt-field"><span>Floating-Randabstand vertikal</span><input type="number" min="0" max="300" step="1" name="switcher[devices][<?php echo esc_attr($device); ?>][offset_y]" value="<?php echo esc_attr($row['offset_y']); ?>"></label>
                            <label class="itkt-field"><span>Trigger → Dropdown</span><input type="number" min="0" max="80" step="1" name="switcher[devices][<?php echo esc_attr($device); ?>][menu_gap]" value="<?php echo esc_attr($row['menu_gap']); ?>"></label>
                            <label class="itkt-field"><span>Flaggengröße</span><input type="number" min="14" max="64" step="1" name="switcher[devices][<?php echo esc_attr($device); ?>][flag_size]" value="<?php echo esc_attr($row['flag_size']); ?>"></label>
                            <label class="itkt-field"><span>Außenabstand oben</span><input type="number" min="-300" max="500" step="1" name="switcher[devices][<?php echo esc_attr($device); ?>][margin_top]" value="<?php echo esc_attr($row['margin_top']); ?>"></label>
                            <label class="itkt-field"><span>Außenabstand rechts</span><input type="number" min="-300" max="500" step="1" name="switcher[devices][<?php echo esc_attr($device); ?>][margin_right]" value="<?php echo esc_attr($row['margin_right']); ?>"></label>
                            <label class="itkt-field"><span>Außenabstand unten</span><input type="number" min="-300" max="500" step="1" name="switcher[devices][<?php echo esc_attr($device); ?>][margin_bottom]" value="<?php echo esc_attr($row['margin_bottom']); ?>"></label>
                            <label class="itkt-field"><span>Außenabstand links</span><input type="number" min="-300" max="500" step="1" name="switcher[devices][<?php echo esc_attr($device); ?>][margin_left]" value="<?php echo esc_attr($row['margin_left']); ?>"></label>
                        </div>
                    </div>
                <?php endforeach; ?>
            </section>

            <section class="itkt-card">
                <span class="itkt-kicker">VERWENDUNG</span><h2>Globale Maße, getrennte Darstellung</h2>
                <div class="itkt-grid-2">
                    <div><strong>Shortcode / Footer / Elementor</strong><p><code>[itkt_language_switcher]</code> übernimmt diese Einstellungen automatisch.</p></div>
                    <div><strong>WoodMart Header Builder</strong><p>Beim Element <strong>IT-Kayali Sprachen</strong> kannst du die globalen Abstände/Größen übernehmen. <strong>Flaggen, Dropdown oder Floating Button wählst du trotzdem pro Element separat.</strong></p></div>
                </div>
            </section>

            <div class="itkt-savebar"><span>Änderungen wirken auf alle global angebundenen Sprachumschalter.</span><button class="button button-primary itkt-primary" type="submit">Sprachumschalter speichern</button></div>
        </form>
        <?php
        $this->footer();
    }

    public function save_switcher_settings() {
        if ( ! current_user_can( 'edit_theme_options' ) ) { wp_die( 'Unauthorized' ); }
        check_admin_referer( 'itkt_save_switcher_settings' );

        $raw = isset( $_POST['switcher'] ) && is_array( $_POST['switcher'] ) ? wp_unslash( $_POST['switcher'] ) : array();
        $defaults = ITKT_Frontend::instance()->switcher_defaults();
        $config = array_replace_recursive( $defaults, $raw );

        $config['show_codes'] = ! empty( $raw['show_codes'] );
        $config['show_names'] = ! empty( $raw['show_names'] );
        $config['mobile_dropdown'] = ! empty( $raw['mobile_dropdown'] );
        $config['floating_fixed'] = ! empty( $raw['floating_fixed'] );
        $config = ITKT_Frontend::instance()->sanitize_switcher_config( $config );

        $settings = get_option( 'itkt_settings', array() );
        $settings['switcher'] = $config;
        update_option( 'itkt_settings', $settings );

        wp_safe_redirect( admin_url( 'admin.php?page=itkt-switcher&switcher_saved=1' ) );
        exit;
    }


    public function shortcodes() {
        if ( ! current_user_can( 'edit_theme_options' ) ) { return; }

        $languages = ITKT_Languages::instance()->get_active();
        $active_count = count( $languages );
        $active_labels = array();
        foreach ( $languages as $code => $lang ) {
            $active_labels[] = ( ! empty( $lang['flag'] ) ? $lang['flag'] . ' ' : '' ) . strtoupper( $code );
        }

        $this->header(
            'Shortcodes',
            'Alle öffentlichen IT-Kayali-Shortcodes mit Beispielen, Optionen und Hinweisen zur Verwendung.'
        );

        echo '<div class="itkt-shortcode-status ' . ( $active_count >= 2 ? 'is-ok' : 'is-warning' ) . '">';
        echo '<div><span class="dashicons ' . ( $active_count >= 2 ? 'dashicons-yes-alt' : 'dashicons-warning' ) . '"></span><div><strong>' . intval( $active_count ) . ' aktive Sprache' . ( 1 === $active_count ? '' : 'n' ) . '</strong>';
        if ( $active_labels ) {
            echo '<small>' . esc_html( implode( ' · ', $active_labels ) ) . '</small>';
        }
        echo '</div></div>';
        if ( $active_count < 2 ) {
            echo '<p>Der Sprachumschalter gibt im Frontend absichtlich nichts aus, solange weniger als zwei Sprachen aktiv sind. Aktiviere zuerst mindestens eine weitere Sprache unter <a href="' . esc_url( admin_url( 'admin.php?page=itkt-languages' ) ) . '">Sprachen</a>.</p>';
        } else {
            echo '<p>Der Sprachumschalter kann im Frontend ausgegeben werden. Änderungen an aktiven Sprachen werden automatisch übernommen.</p>';
        }
        echo '</div>';

        $examples = array(
            array(
                'title' => 'Nur Flaggen',
                'code' => '[itkt_language_switcher]',
                'description' => 'Standarddarstellung. Zeigt die Flaggen aller aktiven Sprachen.',
            ),
            array(
                'title' => 'Flaggen + Sprachkürzel',
                'code' => '[itkt_language_switcher labels="1"]',
                'description' => 'Zeigt zusätzlich DE, EN, AR usw.',
            ),
            array(
                'title' => 'Flaggen + Sprachnamen',
                'code' => '[itkt_language_switcher names="1"]',
                'description' => 'Zeigt zusätzlich Deutsch, English, العربية usw.',
            ),
            array(
                'title' => 'Flaggen + Kürzel + Namen',
                'code' => '[itkt_language_switcher labels="1" names="1"]',
                'description' => 'Vollständige Darstellung mit Flagge, Kürzel und Sprachname.',
            ),
            array(
                'title' => 'Pills',
                'code' => '[itkt_language_switcher labels="1" style="pills"]',
                'description' => 'Kompakte Schaltflächen-Darstellung.',
            ),
            array(
                'title' => 'Text-Stil',
                'code' => '[itkt_language_switcher labels="1" style="text"]',
                'description' => 'Reduzierte Textdarstellung ohne Pill-Hintergrund.',
            ),
            array(
                'title' => 'Dropdown',
                'code' => '[itkt_language_switcher style="dropdown"]',
                'description' => 'Für Header/Topbar: nur die aktive Sprache ist sichtbar; die anderen öffnen sich als Dropdown, ohne am Bildschirm fixiert zu sein.',
            ),
            array(
                'title' => 'Floating Button',
                'code' => '[itkt_language_switcher style="floating" fixed="1" position="bottom"]',
                'description' => 'Separater Floating-Umschalter, der beim Scrollen am Bildschirm bleibt.',
            ),
        );

        echo '<section class="itkt-card"><span class="itkt-kicker">SPRACHUMSCHALTER</span><h2>[itkt_language_switcher]</h2><p>Der Sprachumschalter bleibt auf derselben Seite bzw. demselben Produkt, Kategorie-, Tag-, Warenkorb-, Checkout- oder Konto-Kontext und wechselt nur die Sprache. Ohne Attribute übernimmt der Shortcode die globalen Werte unter <strong>Sprachumschalter</strong>.</p>';
        echo '<div class="itkt-shortcode-grid">';
        foreach ( $examples as $example ) {
            echo '<article class="itkt-shortcode-card">';
            echo '<div><strong>' . esc_html( $example['title'] ) . '</strong><p>' . esc_html( $example['description'] ) . '</p></div>';
            echo '<div class="itkt-shortcode-copy-row"><code>' . esc_html( $example['code'] ) . '</code><button type="button" class="button itkt-copy-shortcode" data-copy="' . esc_attr( $example['code'] ) . '"><span class="dashicons dashicons-admin-page"></span> Kopieren</button></div>';
            echo '</article>';
        }
        echo '</div></section>';

        echo '<section class="itkt-card"><span class="itkt-kicker">OPTIONEN</span><h2>Parameter</h2><div class="itkt-table-wrap"><table class="itkt-table"><thead><tr><th>Parameter</th><th>Werte</th><th>Beschreibung</th></tr></thead><tbody>';
        echo '<tr><td><code>labels</code></td><td><code>0</code> / <code>1</code></td><td>Sprachkürzel wie DE, EN oder AR anzeigen.</td></tr>';
        echo '<tr><td><code>names</code></td><td><code>0</code> / <code>1</code></td><td>Native Sprachnamen anzeigen.</td></tr>';
        echo '<tr><td><code>style</code></td><td><code>flags</code> / <code>pills</code> / <code>text</code> / <code>dropdown</code> / <code>floating</code></td><td><code>dropdown</code> ist für Header/Topbar gedacht. <code>floating</code> nutzt dieselbe Dropdown-Logik als Floating-Variante.</td></tr>';
        echo '<tr><td><code>fixed</code></td><td><code>0</code> / <code>1</code></td><td>Fixiert den Umschalter am Bildschirm. Typisch: <code>style="floating" fixed="1"</code>.</td></tr>';
        echo '<tr><td><code>position</code></td><td><code>top</code> / <code>bottom</code></td><td>Vertikale Floating-Position bei fixiertem Umschalter.</td></tr>';
        echo '<tr><td><code>direction</code></td><td><code>auto</code> / <code>up</code> / <code>down</code></td><td>Öffnungsrichtung des Dropdowns.</td></tr>';
        echo '</tbody></table></div></section>';

        echo '<div class="itkt-grid-2">';
        echo '<section class="itkt-card"><span class="itkt-kicker">ELEMENTOR / WORDPRESS</span><h2>Einfügen</h2><p>In Elementor das Widget <strong>Shortcode</strong> verwenden und den gewünschten Code einfügen. Im WordPress Block Editor kann der Block <strong>Shortcode</strong> verwendet werden. Globale Position, Mobile-Dropdown und Abstände stellst du unter <a href="' . esc_url( admin_url( 'admin.php?page=itkt-switcher' ) ) . '"><strong>Sprachumschalter</strong></a> ein.</p></section>';
        echo '<section class="itkt-card"><span class="itkt-kicker">WOODMART</span><h2>Header Builder</h2><p>Im WoodMart Header Builder ist das native Element <strong>IT-Kayali Sprachen</strong> die bevorzugte Methode. Für den normalen Header wählst du <strong>Dropdown</strong>. Für einen zusätzlichen schwebenden Umschalter wählst du bei einer zweiten Instanz <strong>Floating Button</strong> und aktivierst „Floating am Bildschirm fixieren“. Globale Abstände/Größen können übernommen werden, die Darstellung bleibt aber pro Element getrennt.</p></section>';
        echo '</div>';

        $this->footer();
    }

    public function save_menus(){
        if(!current_user_can('edit_theme_options'))wp_die('Unauthorized');check_admin_referer('itkt_save_menus');$raw=(array)($_POST['menu_map']??array());$clean=array();$valid_locations=array_keys(get_registered_nav_menus());$valid_langs=array_keys(ITKT_Languages::instance()->get_all());
        foreach($raw as $location=>$langs){$location=sanitize_key($location);if(!in_array($location,$valid_locations,true))continue;foreach((array)$langs as $code=>$menu_id){$code=sanitize_key($code);if(!in_array($code,$valid_langs,true))continue;$clean[$location][$code]=absint($menu_id);}}
        $raw_direct=(array)($_POST['direct_menu_map']??array());$direct=array();foreach($raw_direct as $source=>$langs){$source=absint($source);if(!$source||!wp_get_nav_menu_object($source))continue;foreach((array)$langs as $code=>$menu_id){$code=sanitize_key($code);$menu_id=absint($menu_id);if(!in_array($code,$valid_langs,true)||($menu_id&&!wp_get_nav_menu_object($menu_id)))continue;$direct[$source][$code]=$menu_id;}}
        update_option('itkt_menu_map',$clean);update_option('itkt_direct_menu_map',$direct);wp_safe_redirect(admin_url('admin.php?page=itkt-menus&saved=1'));exit;
    }

    public function clone_menu(){
        if(!current_user_can('edit_theme_options'))wp_die('Unauthorized');
        $location=sanitize_key($_GET['location']??'');
        $language=sanitize_key($_GET['language']??'');
        $source=absint($_GET['source']??0);
        check_admin_referer('itkt_clone_menu_'.$location.'_'.$language);

        $source_menu=wp_get_nav_menu_object($source);
        $langs=ITKT_Languages::instance()->get_all();
        if(!$source_menu||empty($langs[$language]))wp_die('Invalid menu or language.');

        $new_name=$source_menu->name.' · '.strtoupper($language);
        $existing=wp_get_nav_menu_object($new_name);
        $new_id=$existing?$existing->term_id:wp_create_nav_menu($new_name);
        if(is_wp_error($new_id))wp_die(esc_html($new_id->get_error_message()));

        if(!$existing){
            $items=wp_get_nav_menu_items($source,array('post_status'=>'any'));
            $id_map=array();
            foreach((array)$items as $item){
                $source_object_id=absint($item->object_id);
                $object_id=$source_object_id;
                $auto_title=false;

                if('post_type'===$item->type&&in_array($item->object,array('page','post','product'),true)){
                    $source_title=get_post_field('post_title',$source_object_id);
                    $auto_title=trim((string)$item->title)===trim((string)$source_title);
                    if(in_array($item->object,array('page','post'),true)){
                        $trs=ITKT_Content::instance()->translations_for($source_object_id);
                        if(isset($trs[$language])&&'publish'===$trs[$language]->post_status)$object_id=$trs[$language]->ID;
                    }
                } elseif('taxonomy'===$item->type&&$source_object_id&&taxonomy_exists($item->object)) {
                    $source_term=get_term($source_object_id,$item->object);
                    if($source_term&&!is_wp_error($source_term))$auto_title=trim((string)$item->title)===trim((string)$source_term->name);
                }

                $menu_title=$item->title;
                if($auto_title&&$object_id!==$source_object_id){
                    $target_title=get_post_field('post_title',$object_id);
                    if(''!==trim((string)$target_title))$menu_title=$target_title;
                } elseif($auto_title&&'post_type'===$item->type&&'product'===$item->object&&ITKT_Plugin::module_enabled('woocommerce')) {
                    $product_translation=ITKT_Product_Translations::instance()->get($source_object_id,$language);
                    if(!empty($product_translation['title']))$menu_title=$product_translation['title'];
                } elseif($auto_title&&'taxonomy'===$item->type&&ITKT_Plugin::module_enabled('woocommerce')) {
                    $term_translation=ITKT_Term_Translations::instance()->get($source_object_id,$language);
                    if(!empty($term_translation['name']))$menu_title=$term_translation['name'];
                }

                $args=array(
                    'menu-item-title'=>$menu_title,
                    'menu-item-status'=>'publish',
                    'menu-item-type'=>$item->type,
                    'menu-item-object'=>$item->object,
                    'menu-item-object-id'=>$object_id,
                    'menu-item-url'=>$item->url,
                    'menu-item-description'=>$item->description,
                    'menu-item-attr-title'=>$item->attr_title,
                    'menu-item-target'=>$item->target,
                    'menu-item-classes'=>implode(' ',(array)$item->classes),
                    'menu-item-xfn'=>$item->xfn
                );
                if($item->menu_item_parent&&!empty($id_map[$item->menu_item_parent]))$args['menu-item-parent-id']=$id_map[$item->menu_item_parent];
                $created=wp_update_nav_menu_item($new_id,0,$args);
                if(!is_wp_error($created)){
                    $id_map[$item->ID]=$created;
                    update_post_meta($created,'_itkt_menu_language',$language);
                    update_post_meta($created,'_itkt_source_menu_item',absint($item->ID));
                    if($source_object_id)update_post_meta($created,'_itkt_source_object_id',$source_object_id);
                    if($auto_title)update_post_meta($created,'_itkt_auto_title','1');
                }
            }
        }

        $map=get_option('itkt_menu_map',array());
        $map[$location][$language]=absint($new_id);
        update_option('itkt_menu_map',$map);
        wp_safe_redirect(admin_url('admin.php?page=itkt-menus&created=1'));
        exit;
    }

    public function strings(){
        if ( ! ITKT_Plugin::module_enabled( 'strings' ) ) {
            $this->header('Plugin & Theme Texte','Das String-Modul ist aktuell deaktiviert.');
            echo '<section class="itkt-card"><h2>String Translation aktivieren</h2><p>Öffne den Setup-Assistenten und aktiviere das Modul „Plugin & Theme Texte“.</p><a class="button button-primary itkt-primary" href="'.esc_url(admin_url('admin.php?page=itkt-setup')).'">Setup öffnen</a></section>';
            $this->footer(); return;
        }
        $service = ITKT_Strings::instance();
        $targets = $this->active_targets();
        $target_codes = array_keys($targets);
        $plugins = $service->plugin_sources();
        $themes  = $service->theme_sources();
        $quick_plugin_key = '';
        foreach ( $plugins as $key => $item ) {
            if ( ! empty( $item['active'] ) && ( 'woocommerce/woocommerce.php' === $key || false !== stripos( (string) $item['label'], 'WooCommerce' ) ) ) {
                $quick_plugin_key = $key;
                break;
            }
        }
        if ( ! $quick_plugin_key ) {
            foreach ( $plugins as $key => $item ) { if ( ! empty( $item['active'] ) ) { $quick_plugin_key = $key; break; } }
        }
        $quick_theme_key = '';
        foreach ( $themes as $key => $item ) {
            if ( ! empty( $item['active'] ) && ( false !== stripos( (string) $item['label'], 'WoodMart' ) || $key === get_stylesheet() ) ) {
                $quick_theme_key = $key;
                break;
            }
        }
        if ( ! $quick_theme_key ) {
            foreach ( $themes as $key => $item ) { if ( ! empty( $item['active'] ) ) { $quick_theme_key = $key; break; } }
        }
        $type = sanitize_key($_GET['source_type']??'plugin');
        if(!in_array($type,array('plugin','theme'),true))$type='plugin';
        $sources = 'theme'===$type ? $themes : $plugins;
        $source_key = sanitize_text_field(wp_unslash($_GET['source_key']??''));
        if($source_key && !isset($sources[$source_key]))$source_key='';
        $status = sanitize_key($_GET['status']??'missing');
        if(!in_array($status,array('missing','translated','all'),true))$status='missing';
        $search = sanitize_text_field(wp_unslash($_GET['s']??''));
        $paged = max(1,absint($_GET['paged']??1));
        $result = $service->query_strings(array('page'=>$paged,'per_page'=>40,'search'=>$search,'source_type'=>$type,'source_key'=>$source_key,'status'=>$status,'targets'=>$target_codes));
        $rows=$result['rows']; $ids=array_map(function($row){return absint($row->id);},$rows);
        $translations=$service->get_translations_for_ids($ids,$target_codes);
        $counts=$service->counts($target_codes);
        $this->header('Plugin & Theme Texte','WooCommerce-, WoodMart-, Theme- und Plugin-Texte zentral übersetzen. Originaldateien werden niemals verändert.');
        if(isset($_GET['scanned'])){
            echo '<div class="notice notice-success inline"><p><strong>Scan abgeschlossen:</strong> '.intval($_GET['files']??0).' Dateien geprüft, '.intval($_GET['scanned']??0).' Textstellen erkannt, '.intval($_GET['new']??0).' neue Strings.';
            if(!empty($_GET['truncated']))echo ' <strong>Hinweis:</strong> Sicherheitslimit erreicht; große Quellen können später per Hintergrundscan erweitert werden.';
            echo '</p></div>';
        }
        if(isset($_GET['saved']))echo '<div class="notice notice-success inline"><p>String-Übersetzungen gespeichert.</p></div>';
        ?>
        <?php if ( 0 === intval( $counts['total'] ) ) : ?>
        <section class="itkt-card itkt-first-scan">
            <div class="itkt-card-head"><div><span class="itkt-kicker">ERSTER SCAN</span><h2>Noch keine Texte eingelesen</h2></div></div>
            <p>Der untere Übersetzungsbereich bleibt leer, bis mindestens eine Quelle gescannt wurde. Du kannst direkt mit WooCommerce bzw. deinem aktiven Theme starten.</p>
            <div class="itkt-quick-scan-actions">
                <?php if ( $quick_plugin_key && isset( $plugins[ $quick_plugin_key ] ) ) : ?>
                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><?php wp_nonce_field( 'itkt_scan_strings' ); ?><input type="hidden" name="action" value="itkt_scan_strings"><input type="hidden" name="source_type" value="plugin"><input type="hidden" name="source_key" value="<?php echo esc_attr( $quick_plugin_key ); ?>"><button class="button button-primary itkt-primary">WooCommerce scannen</button></form>
                <?php endif; ?>
                <?php if ( $quick_theme_key && isset( $themes[ $quick_theme_key ] ) ) : ?>
                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><?php wp_nonce_field( 'itkt_scan_strings' ); ?><input type="hidden" name="action" value="itkt_scan_strings"><input type="hidden" name="source_type" value="theme"><input type="hidden" name="source_key" value="<?php echo esc_attr( $quick_theme_key ); ?>"><button class="button"><?php echo esc_html( ( false !== stripos( (string) $themes[ $quick_theme_key ]['label'], 'WoodMart' ) ? 'WoodMart' : $themes[ $quick_theme_key ]['label'] ) . ' scannen' ); ?></button></form>
                <?php endif; ?>
            </div>
            <small>Nach dem Scan wirst du automatisch zum unteren Übersetzungsmanager zurückgeführt und die gefundenen Texte erscheinen dort.</small>
        </section>
        <?php endif; ?>
        <div class="itkt-stats-grid itkt-string-stats">
            <div class="itkt-stat-card"><span>Gefundene Strings</span><strong><?php echo intval($counts['total']);?></strong><small>aus gescannten Quellen</small></div>
            <div class="itkt-stat-card"><span>Nicht vollständig</span><strong><?php echo intval($counts['missing']);?></strong><small>mindestens eine aktive Sprache fehlt</small></div>
            <div class="itkt-stat-card"><span>Vollständig</span><strong><?php echo intval($counts['translated']);?></strong><small>alle aktiven Zielsprachen gepflegt</small></div>
            <div class="itkt-stat-card"><span>Zielsprachen</span><strong><?php echo count($targets);?></strong><small><?php echo esc_html(implode(' · ',array_map('strtoupper',$target_codes)));?></small></div>
        </div>
        <div class="itkt-grid-2">
            <section class="itkt-card"><span class="itkt-kicker">PLUGIN SCANNER</span><h2>Plugin-Texte einlesen</h2><p>Scannt PHP-Gettext-Aufrufe wie <code>__()</code>, <code>_e()</code>, <code>_x()</code> und die Escaping-Varianten. Die Quelldateien werden nur gelesen.</p>
                <form class="itkt-string-scan-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php'));?>"><?php wp_nonce_field('itkt_scan_strings');?><input type="hidden" name="action" value="itkt_scan_strings"><input type="hidden" name="source_type" value="plugin"><select name="source_key" required><option value="">Plugin auswählen …</option><?php foreach($plugins as $key=>$item):?><option value="<?php echo esc_attr($key);?>"><?php echo esc_html(($item['active']?'● ':'○ ').$item['label'].($item['version']?' '.$item['version']:''));?></option><?php endforeach;?></select><button class="button button-primary itkt-primary">Plugin scannen</button></form>
            </section>
            <section class="itkt-card"><span class="itkt-kicker">THEME SCANNER</span><h2>Theme-Texte einlesen</h2><p>WoodMart und andere Themes werden über denselben generischen Scanner unterstützt. Vorhandene Builder-/Theme-Adapter ergänzen den Scanner dort, wo zusätzliche Quellen sicher erkannt werden können.</p>
                <form class="itkt-string-scan-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php'));?>"><?php wp_nonce_field('itkt_scan_strings');?><input type="hidden" name="action" value="itkt_scan_strings"><input type="hidden" name="source_type" value="theme"><select name="source_key" required><option value="">Theme auswählen …</option><?php foreach($themes as $key=>$item):?><option value="<?php echo esc_attr($key);?>"><?php echo esc_html(($item['active']?'● ':'○ ').$item['label'].($item['version']?' '.$item['version']:''));?></option><?php endforeach;?></select><button class="button button-primary itkt-primary">Theme scannen</button></form>
            </section>
        </div>
        <section class="itkt-card itkt-string-manager"><div class="itkt-card-head"><div><span class="itkt-kicker">STRING TRANSLATION</span><h2>Übersetzungsmanager</h2></div><span class="itkt-pill orange"><?php echo intval($result['total']);?> Treffer</span></div>
            <div class="itkt-tabs">
                <a class="<?php echo 'plugin'===$type?'is-active':'';?>" href="<?php echo esc_url(add_query_arg(array('page'=>'itkt-strings','source_type'=>'plugin','status'=>$status),admin_url('admin.php')));?>">Plugins</a>
                <a class="<?php echo 'theme'===$type?'is-active':'';?>" href="<?php echo esc_url(add_query_arg(array('page'=>'itkt-strings','source_type'=>'theme','status'=>$status),admin_url('admin.php')));?>">Themes</a>
            </div>
            <form method="get" class="itkt-string-filters"><input type="hidden" name="page" value="itkt-strings"><input type="hidden" name="source_type" value="<?php echo esc_attr($type);?>">
                <select name="source_key"><option value="">Alle <?php echo 'theme'===$type?'Themes':'Plugins';?></option><?php foreach($sources as $key=>$item):?><option value="<?php echo esc_attr($key);?>" <?php selected($source_key,$key);?>><?php echo esc_html($item['label']);?></option><?php endforeach;?></select>
                <select name="status"><option value="missing" <?php selected($status,'missing');?>>Nicht übersetzt / unvollständig</option><option value="translated" <?php selected($status,'translated');?>>Bereits vollständig</option><option value="all" <?php selected($status,'all');?>>Alle</option></select>
                <input type="search" name="s" value="<?php echo esc_attr($search);?>" placeholder="Text, Textdomain oder Datei suchen …"><button class="button">Filtern</button>
            </form>
            <?php if(!$targets):?><div class="itkt-notice blue"><strong>Keine Zielsprache aktiv.</strong> Aktiviere mindestens eine weitere Sprache.</div><?php elseif(!$rows):?><div class="itkt-empty-state"><span class="dashicons dashicons-translation"></span><strong><?php echo $source_key ? 'Für diese Quelle wurden noch keine Texte gefunden.' : 'Noch keine Strings in diesem Filter.'; ?></strong><p><?php echo $source_key ? 'Scanne die ausgewählte Quelle jetzt. Danach erscheinen die Texte direkt hier.' : 'Wähle oben eine Quelle und starte den Scan.'; ?></p><?php if($source_key):?><form class="itkt-inline-scan" method="post" action="<?php echo esc_url(admin_url('admin-post.php'));?>"><?php wp_nonce_field('itkt_scan_strings');?><input type="hidden" name="action" value="itkt_scan_strings"><input type="hidden" name="source_type" value="<?php echo esc_attr($type);?>"><input type="hidden" name="source_key" value="<?php echo esc_attr($source_key);?>"><button class="button button-primary itkt-primary">Jetzt <?php echo 'theme'===$type?'Theme':'Plugin';?> scannen</button></form><?php endif;?></div><?php else:?>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php'));?>"><?php wp_nonce_field('itkt_save_strings');?><input type="hidden" name="action" value="itkt_save_strings"><input type="hidden" name="return_url" value="<?php echo esc_attr(wp_unslash($_SERVER['REQUEST_URI']??''));?>">
                <div class="itkt-editor-scroll"><table class="itkt-editor-table itkt-string-table"><thead><tr><th>Original / Quelle</th><?php foreach($targets as $code=>$lang):?><th><?php echo esc_html($lang['flag'].' '.$lang['native_name']);?><small><?php echo esc_html(strtoupper($code));?></small></th><?php endforeach;?></tr></thead><tbody>
                <?php foreach($rows as $row):$row_trans=$translations[absint($row->id)]??array();$done=0;foreach($target_codes as $c){if(trim((string)($row_trans[$c]??''))!=='')$done++;}?><tr>
                    <td class="is-source"><div class="itkt-string-original"><?php echo esc_html($row->original);?></div><div class="itkt-string-meta"><code><?php echo esc_html($row->domain?:'default');?></code><?php if($row->context):?><span>Kontext: <?php echo esc_html($row->context);?></span><?php endif;?><span><?php echo esc_html(($row->source_file?:$row->source_key).($row->source_line?' :'.$row->source_line:''));?></span><span class="itkt-status <?php echo $done===count($target_codes)?'complete':($done?'partial':'missing');?>"><?php echo esc_html($done.'/'.count($target_codes)); ?></span></div></td>
                    <?php foreach($targets as $code=>$lang):?><td><textarea rows="3" name="translations[<?php echo absint($row->id);?>][<?php echo esc_attr($code);?>]" placeholder="<?php echo esc_attr('Übersetzung '.strtoupper($code));?>"><?php echo esc_textarea($row_trans[$code]??'');?></textarea></td><?php endforeach;?>
                </tr><?php endforeach;?></tbody></table></div>
                <div class="itkt-savebar"><span><?php echo count($rows);?> Strings auf dieser Seite · Originaldateien bleiben unverändert.</span><button class="button button-primary itkt-primary">Übersetzungen speichern</button></div>
            </form>
            <?php if($result['pages']>1):?><div class="itkt-pagination"><?php for($i=1;$i<=$result['pages'];$i++):$url=add_query_arg(array('page'=>'itkt-strings','source_type'=>$type,'source_key'=>$source_key,'status'=>$status,'s'=>$search,'paged'=>$i),admin_url('admin.php'));?><a class="<?php echo $i===$paged?'is-current':'';?>" href="<?php echo esc_url($url);?>"><?php echo intval($i);?></a><?php endfor;?></div><?php endif;?>
            <?php endif;?>
            <div class="itkt-notice blue"><strong>Runtime aktiv:</strong> PHP-Gettext, JavaScript-i18n und Pluralformen werden zur Laufzeit unterstützt. Der Scanner liest weiterhin nur sichere, statisch erkennbare PHP-Gettext-Quellen; dynamische Frontend-Texte werden zusätzlich über „Frontend Texte“ erfasst.</div>
        </section>
        <?php $this->footer();
    }

    public function scan_strings(){
        if(!current_user_can('manage_options'))wp_die('Unauthorized');
        check_admin_referer('itkt_scan_strings');
        $type=sanitize_key($_POST['source_type']??'');$key=sanitize_text_field(wp_unslash($_POST['source_key']??''));
        if(!in_array($type,array('plugin','theme'),true)||''===$key)wp_die('Ungültige Quelle.');
        // No long synchronous scan here. Start a resumable job and return immediately.
        $job=ITKT_Strings::instance()->start_scan_job($type,$key,get_current_user_id());
        if(is_wp_error($job))wp_die(esc_html($job->get_error_message()));
        $url=add_query_arg(array('page'=>'itkt-strings','source_type'=>$type,'source_key'=>$key,'status'=>'missing','scan_job'=>$job['id']),admin_url('admin.php'));
        wp_safe_redirect($url);exit;
    }

    public function ajax_scan_strings_start(){
        if(!current_user_can('manage_options'))wp_send_json_error(array('message'=>'Keine Berechtigung.'),403);
        check_ajax_referer('itkt_ajax_string_scan','nonce');
        $type=sanitize_key($_POST['source_type']??'');
        $key=sanitize_text_field(wp_unslash($_POST['source_key']??''));
        if(!in_array($type,array('plugin','theme'),true)||''===$key)wp_send_json_error(array('message'=>'Ungültige Quelle.'),400);
        $job=ITKT_Strings::instance()->start_scan_job($type,$key,get_current_user_id());
        if(is_wp_error($job))wp_send_json_error(array('message'=>$job->get_error_message()),400);
        wp_send_json_success(array('job_id'=>$job['id'],'source_type'=>$type,'source_key'=>$key));
    }

    public function ajax_scan_strings_batch(){
        if(!current_user_can('manage_options'))wp_send_json_error(array('message'=>'Keine Berechtigung.'),403);
        check_ajax_referer('itkt_ajax_string_scan','nonce');
        $job_id=sanitize_text_field(wp_unslash($_POST['job_id']??''));
        if(''===$job_id)wp_send_json_error(array('message'=>'Scan-Auftrag fehlt.'),400);
        $result=ITKT_Strings::instance()->process_scan_job($job_id,get_current_user_id(),18,1.6);
        if(is_wp_error($result))wp_send_json_error(array('message'=>$result->get_error_message()),400);
        $result['redirect']=add_query_arg(array(
            'page'=>'itkt-strings',
            'source_type'=>$result['source_type'],
            'source_key'=>$result['source_key'],
            'status'=>'missing',
            'scanned'=>$result['stats']['strings'],
            'files'=>$result['stats']['files'],
            'new'=>$result['stats']['new'],
            'truncated'=>!empty($result['stats']['truncated'])?1:0,
        ),admin_url('admin.php'));
        wp_send_json_success($result);
    }

    public function save_strings(){
        if(!current_user_can('manage_options'))wp_die('Unauthorized');
        check_admin_referer('itkt_save_strings');
        $posted=(array)($_POST['translations']??array());
        $targets=$this->active_targets();
        foreach($posted as $id=>$langs){$id=absint($id);if(!$id||!is_array($langs))continue;foreach($langs as $code=>$value){$code=sanitize_key($code);if(!isset($targets[$code]))continue;ITKT_Strings::instance()->save_translation($id,$code,wp_unslash($value));}}
        $return=wp_unslash($_POST['return_url']??'');
        $return=wp_validate_redirect($return,admin_url('admin.php?page=itkt-strings'));
        $return=add_query_arg('saved','1',$return);
        wp_safe_redirect($return);exit;
    }


    public function clear_diagnostics() {
        if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Unauthorized' ); }
        check_admin_referer( 'itkt_clear_diagnostics' );
        ITKT_Diagnostics::instance()->clear_log();
        wp_safe_redirect( admin_url( 'admin.php?page=itkt-system&diag=cleared' ) ); exit;
    }

    public function repair_search_indexes() {
        if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Unauthorized' ); }
        check_admin_referer( 'itkt_repair_search_indexes' );
        $result = ITKT_Diagnostics::instance()->repair_search_indexes();
        wp_safe_redirect( add_query_arg( array( 'page'=>'itkt-system', 'diag'=>'search-repaired', 'checked'=>intval($result['checked']), 'repaired'=>intval($result['repaired']) ), admin_url( 'admin.php' ) ) ); exit;
    }

    public function repair_slug_indexes() {
        if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Unauthorized' ); }
        check_admin_referer( 'itkt_repair_slug_indexes' );
        $result = ITKT_Diagnostics::instance()->repair_slug_indexes();
        wp_safe_redirect( add_query_arg( array( 'page'=>'itkt-system', 'diag'=>'slug-repaired', 'checked'=>intval($result['checked']), 'repaired'=>intval($result['repaired']) ), admin_url( 'admin.php' ) ) ); exit;
    }

    public function flush_rewrites() {
        if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Unauthorized' ); }
        check_admin_referer( 'itkt_flush_rewrites' );
        flush_rewrite_rules( false );
        ITKT_Diagnostics::instance()->log( 'success', 'rewrite_flush', 'WordPress Rewrite-Regeln wurden neu geladen.' );
        wp_safe_redirect( admin_url( 'admin.php?page=itkt-system&diag=rewrites' ) ); exit;
    }

    public function save_acceptance() {
        if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Unauthorized' ); }
        check_admin_referer( 'itkt_save_acceptance' );
        ITKT_Diagnostics::instance()->save_acceptance_state( wp_unslash( $_POST['acceptance'] ?? array() ) );
        wp_safe_redirect( admin_url( 'admin.php?page=itkt-system&diag=acceptance-saved' ) ); exit;
    }

    public function reset_acceptance() {
        if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Unauthorized' ); }
        check_admin_referer( 'itkt_reset_acceptance' );
        ITKT_Diagnostics::instance()->reset_acceptance_state();
        wp_safe_redirect( admin_url( 'admin.php?page=itkt-system&diag=acceptance-reset' ) ); exit;
    }

    public function export_acceptance() {
        if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Unauthorized' ); }
        check_admin_referer( 'itkt_export_acceptance' );
        $report = ITKT_Diagnostics::instance()->acceptance_report();
        nocache_headers();
        header( 'Content-Type: application/json; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename="itkt-abnahmebericht-' . sanitize_file_name( ITKT_VERSION ) . '.json"' );
        echo wp_json_encode( $report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
        exit;
    }

    public function system(){
        $env=ITKT_Environment::detect(); global $wpdb;
        $checks=ITKT_Diagnostics::instance()->self_test();
        $log=ITKT_Diagnostics::instance()->get_log();
        $stats=ITKT_Diagnostics::instance()->stats();
        $acceptance_catalog=ITKT_Diagnostics::instance()->acceptance_catalog();
        $acceptance_state=ITKT_Diagnostics::instance()->acceptance_state();
        $acceptance_summary=ITKT_Diagnostics::instance()->acceptance_summary();
        $failed=array_filter($checks,function($c){return empty($c['ok']);});
        $critical_failed=array_filter($checks,function($c){return empty($c['ok']) && ('warning' !== ($c['severity'] ?? 'error'));});
        $production_ready=empty($critical_failed) && !empty($acceptance_summary['complete']);
        $this->header('Systemstatus','Selbsttest, Routing-Diagnose, Übersetzungszustand und Support-Protokoll.');
        if(!empty($_GET['diag'])){
            $diag=sanitize_key($_GET['diag']);
            if('slug-repaired'===$diag){echo '<div class="notice notice-success inline"><p><strong>Slug-Indizes geprüft.</strong> '.intval($_GET['checked']??0).' geprüft, '.intval($_GET['repaired']??0).' repariert.</p></div>';}
            elseif('search-repaired'===$diag){echo '<div class="notice notice-success inline"><p><strong>Produkt-Suchindex geprüft.</strong> '.intval($_GET['checked']??0).' geprüft, '.intval($_GET['repaired']??0).' aktualisiert.</p></div>';}
            elseif('rewrites'===$diag){echo '<div class="notice notice-success inline"><p><strong>Rewrite-Regeln wurden neu geladen.</strong></p></div>';}
            elseif('cleared'===$diag){echo '<div class="notice notice-success inline"><p><strong>Diagnose-Protokoll wurde geleert.</strong></p></div>';}
            elseif('acceptance-saved'===$diag){echo '<div class="notice notice-success inline"><p><strong>Abnahmestand gespeichert.</strong> Der Fortschritt gilt für Plugin-Version '.esc_html(ITKT_VERSION).'.</p></div>';}
            elseif('acceptance-reset'===$diag){echo '<div class="notice notice-success inline"><p><strong>Abnahmecheckliste wurde zurückgesetzt.</strong></p></div>';}
        }
        ?>
        <div class="itkt-stats-grid itkt-diagnostic-stats">
            <div class="itkt-stat-card"><span>Systemchecks</span><strong><?php echo intval(count($checks)-count($failed));?>/<?php echo intval(count($checks));?></strong><small><?php echo $failed?intval(count($failed)).' Hinweis(e) prüfen':'Keine kritischen Hinweise';?></small></div>
            <div class="itkt-stat-card"><span>Fertige Übersetzungen</span><strong><?php echo intval($stats['complete']??0);?></strong><small>über Seiten, Beiträge und Produkte</small></div>
            <div class="itkt-stat-card"><span>Fehlend / teilweise</span><strong><?php echo intval(($stats['missing']??0)+($stats['partial']??0));?></strong><small>noch zu bearbeiten</small></div>
            <div class="itkt-stat-card"><span>Veraltet</span><strong><?php echo intval($stats['outdated']??0);?></strong><small>Original wurde nachträglich geändert</small></div>
            <div class="itkt-stat-card"><span>Produktionsabnahme</span><strong><?php echo intval($acceptance_summary['done']);?>/<?php echo intval($acceptance_summary['total']);?></strong><small><?php echo $production_ready?'Bereit zur Produktivbestätigung':intval($acceptance_summary['percent']).'% des Realtests bestätigt';?></small></div>
        </div>
        <div class="itkt-grid-2">
            <section class="itkt-card"><div class="itkt-card-head"><div><span class="itkt-kicker">SELBSTTEST</span><h2>Systemgesundheit</h2></div><span class="itkt-pill <?php echo $failed?'orange':'green';?>"><?php echo $failed?'Prüfen':'OK';?></span></div>
                <div class="itkt-check-list"><?php foreach($checks as $check):?><div class="itkt-check-row <?php echo $check['ok']?'is-ok':'is-problem';?>"><span class="dashicons <?php echo $check['ok']?'dashicons-yes-alt':'dashicons-warning';?>"></span><div><strong><?php echo esc_html($check['label']);?></strong><small><?php echo esc_html($check['detail']);?></small></div></div><?php endforeach;?></div>
            </section>
            <section class="itkt-card"><span class="itkt-kicker">WERKZEUGE</span><h2>Reparatur & Wartung</h2><p>Diese Aktionen verändern keine Übersetzungstexte.</p>
                <div class="itkt-tool-actions">
                    <a class="button button-primary itkt-primary" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=itkt_flush_rewrites'),'itkt_flush_rewrites'));?>">Rewrite-Regeln neu laden</a>
                    <?php if(ITKT_Plugin::module_enabled('woocommerce')&&post_type_exists('product')):?><a class="button" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=itkt_repair_slug_indexes'),'itkt_repair_slug_indexes'));?>">Produkt-Slug-Index reparieren</a><?php endif;?>
                    <?php if(ITKT_Plugin::module_enabled('woocommerce')&&post_type_exists('product')):?><a class="button" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=itkt_repair_search_indexes'),'itkt_repair_search_indexes'));?>">Produkt-Suchindex reparieren</a><?php endif;?>
                    <a class="button" href="<?php echo esc_url(home_url('/itkt-translations-sitemap.xml'));?>" target="_blank" rel="noopener">Übersetzungs-Sitemap öffnen ↗</a>
                </div>
                <div class="itkt-notice blue"><strong>Routing-Hinweis:</strong> Wenn eine Sprach-Produktseite nach einem Update 404 zeigt, zuerst „Rewrite-Regeln neu laden“ und anschließend den Produkt-Slug-Index prüfen.</div>
            </section>
        </div>
        <section class="itkt-card">
            <div class="itkt-card-head"><div><span class="itkt-kicker">FINALER REALTEST</span><h2>Produktionsabnahme</h2></div><span class="itkt-pill <?php echo $production_ready?'green':'orange';?>"><?php echo $production_ready?'BEREIT':intval($acceptance_summary['percent']).'%';?></span></div>
            <p>Diese Checkliste dokumentiert nur echte Browser-Tests. Ein Häkchen bedeutet: Der Punkt wurde in der aktuellen Version <strong><?php echo esc_html(ITKT_VERSION);?></strong> real geprüft. Nach einem Versionswechsel beginnt die Abnahme bewusst wieder bei 0 %.</p>
            <?php if(!$acceptance_summary['valid_version'] && !empty($acceptance_state['checks'])):?><div class="itkt-notice orange"><strong>Neue Plugin-Version erkannt.</strong> Alte Häkchen werden nicht als aktuelle Abnahme gewertet.</div><?php endif;?>
            <?php if($production_ready):?><div class="itkt-notice green"><strong>Abnahmematrix vollständig.</strong> Alle manuellen Punkte sind bestätigt und es gibt keinen fehlgeschlagenen kritischen Selbsttest.</div><?php elseif($critical_failed):?><div class="itkt-notice orange"><strong>Noch nicht produktionsbereit:</strong> <?php echo intval(count($critical_failed));?> kritische(r) Selbsttest(s) müssen zuerst gelöst werden.</div><?php endif;?>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php'));?>">
                <?php wp_nonce_field('itkt_save_acceptance');?><input type="hidden" name="action" value="itkt_save_acceptance">
                <div class="itkt-acceptance-list">
                    <?php foreach($acceptance_catalog as $section_key=>$section):?>
                        <details class="itkt-acceptance-section" <?php echo 0===strpos($section_key,'lang_')?'open':'';?>>
                            <summary><strong><?php echo esc_html($section['label']);?></strong></summary>
                            <div class="itkt-check-list">
                                <?php foreach((array)$section['items'] as $id=>$label):$checked=$acceptance_summary['valid_version']&&!empty($acceptance_state['checks'][$id]);?>
                                    <label class="itkt-check-row <?php echo $checked?'is-ok':'';?>"><input type="checkbox" name="acceptance[<?php echo esc_attr($id);?>]" value="1" <?php checked($checked);?>><div><strong><?php echo esc_html($label);?></strong><small><?php echo $checked?'Bestätigt':'Noch offen';?></small></div></label>
                                <?php endforeach;?>
                            </div>
                        </details>
                    <?php endforeach;?>
                </div>
                <p><button class="button button-primary itkt-primary" type="submit">Abnahmestand speichern</button> <a class="button" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=itkt_export_acceptance'),'itkt_export_acceptance'));?>">Abnahmebericht exportieren</a> <a class="button" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=itkt_reset_acceptance'),'itkt_reset_acceptance'));?>" onclick="return confirm('Abnahmecheckliste wirklich zurücksetzen?');">Checkliste zurücksetzen</a></p>
                <?php if(!empty($acceptance_summary['updated_at'])):?><small>Zuletzt gespeichert: <?php echo esc_html($acceptance_summary['updated_at']);?></small><?php endif;?>
            </form>
        </section>
        <div class="itkt-grid-2">
            <section class="itkt-card"><span class="itkt-kicker">UMGEBUNG</span><h2>Kompatibilität</h2><div class="itkt-compat-list"><?php foreach($env as $item):?><div><span class="itkt-dot <?php echo $item['active']?'ok':'';?>"></span><strong><?php echo esc_html($item['label']);?></strong><span><?php echo $item['active']?'Aktiv':'Nicht aktiv';?> <?php echo esc_html($item['version']);?></span></div><?php endforeach;?></div></section>
            <section class="itkt-card"><span class="itkt-kicker">SERVER</span><h2>Technische Daten</h2><dl class="itkt-system-list"><div><dt>Plugin</dt><dd><?php echo esc_html(ITKT_VERSION);?></dd></div><div><dt>PHP</dt><dd><?php echo esc_html(PHP_VERSION);?></dd></div><div><dt>WordPress</dt><dd><?php echo esc_html(get_bloginfo('version'));?></dd></div><div><dt>Datenbank</dt><dd><?php echo esc_html($wpdb->db_version());?></dd></div><div><dt>Theme</dt><dd><?php echo esc_html(ITKT_Environment::theme_name());?></dd></div><div><dt>Permalinks</dt><dd><?php echo esc_html(get_option('permalink_structure')?:'Einfach');?></dd></div></dl></section>
        </div>
        <section class="itkt-card"><div class="itkt-card-head"><div><span class="itkt-kicker">ÜBERSETZUNGSSTATUS</span><h2>Bereiche</h2></div><a href="<?php echo esc_url(admin_url('admin.php?page=itkt-translations'));?>">Übersetzungen öffnen →</a></div><div class="itkt-status-area-grid"><?php foreach((array)($stats['by_type']??array()) as $row):$done=intval($row['complete']??0);$total=max(1,intval($row['total']??0));$pct=round(($done/$total)*100);?><div><div class="itkt-status-area-head"><strong><?php echo esc_html($row['label']);?></strong><span><?php echo intval($pct);?>%</span></div><div class="itkt-progress"><i style="width:<?php echo intval($pct);?>%"></i></div><small><?php echo intval($done);?> fertig · <?php echo intval($row['missing']??0);?> fehlt · <?php echo intval($row['partial']??0);?> teilweise · <?php echo intval($row['outdated']??0);?> veraltet</small></div><?php endforeach;?></div></section>
        <section class="itkt-card"><div class="itkt-card-head"><div><span class="itkt-kicker">DIAGNOSE-PROTOKOLL</span><h2>Letzte Ereignisse</h2></div><?php if($log):?><a class="button" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=itkt_clear_diagnostics'),'itkt_clear_diagnostics'));?>">Protokoll leeren</a><?php endif;?></div>
            <?php if(!$log):?><div class="itkt-empty-state"><span class="dashicons dashicons-shield-alt"></span><strong>Noch keine Diagnose-Ereignisse.</strong><p>Routing-Fehler und Wartungsaktionen erscheinen hier automatisch.</p></div><?php else:?><div class="itkt-log-list"><?php foreach(array_slice($log,0,30) as $entry):?><div class="itkt-log-row is-<?php echo esc_attr($entry['level']??'info');?>"><span class="itkt-log-time"><?php echo esc_html($entry['time']??'');?></span><div><strong><?php echo esc_html($entry['message']??'');?></strong><small><?php echo esc_html($entry['event']??'');?><?php if(!empty($entry['context'])):?> · <?php echo esc_html(implode(' · ',array_map(function($k,$v){return $k.': '.$v;},array_keys($entry['context']),array_values($entry['context']))));?><?php endif;?></small></div></div><?php endforeach;?></div><?php endif;?>
        </section>
        <?php $this->footer();
    }

    public function settings() {
        $s = get_option( 'itkt_settings', array() );
        $this->header( 'Einstellungen', 'Sprachverhalten im Frontend und globale Konfiguration.' );
        ?>
        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
            <?php wp_nonce_field( 'itkt_save_settings' ); ?><input type="hidden" name="action" value="itkt_save_settings">
            <div class="itkt-grid-2">
                <section class="itkt-card"><span class="itkt-kicker">SPRACHNAVIGATION</span><h2>Besuchersprache</h2>
                    <label class="itkt-switch-row"><input type="checkbox" name="remember_language" value="1" <?php checked( ! isset( $s['remember_language'] ) || ! empty( $s['remember_language'] ) ); ?>><span class="itkt-switch"></span><span><strong>Ausgewählte Sprache merken</strong><small>Speichert die Auswahl für ein Jahr im Browser.</small></span></label>
                    <label class="itkt-switch-row"><input type="checkbox" name="redirect_saved_home" value="1" <?php checked( ! isset( $s['redirect_saved_home'] ) || ! empty( $s['redirect_saved_home'] ) ); ?>><span class="itkt-switch"></span><span><strong>Homepage in gespeicherter Sprache öffnen</strong><small>Beispiel: Arabisch gewählt → Logo/Home führt zu /ar/.</small></span></label>
                    <label class="itkt-field"><span>Wenn eine Übersetzung fehlt</span><select name="fallback_mode"><option value="default" <?php selected( $s['fallback_mode'] ?? 'default', 'default' ); ?>>Originalinhalt unter der aktiven Sprach-URL anzeigen</option><option value="404" <?php selected( $s['fallback_mode'] ?? 'default', '404' ); ?>>404 anzeigen</option></select></label>
                    <label class="itkt-switch-row"><input type="checkbox" name="product_slug_fallback_default" value="1" <?php checked( ! isset( $s['product_slug_fallback_default'] ) || ! empty( $s['product_slug_fallback_default'] ) ); ?>><span class="itkt-switch"></span><span><strong>Fehlender Produkt-Slug → Standardsprache</strong><small>Ist z. B. der arabische Produkt-Slug leer, wird automatisch die deutsche Produktseite geöffnet statt einer 404-Seite.</small></span></label>
                    <label class="itkt-switch-row"><input type="checkbox" name="diagnostic_logging" value="1" <?php checked( ! isset( $s['diagnostic_logging'] ) || ! empty( $s['diagnostic_logging'] ) ); ?>><span class="itkt-switch"></span><span><strong>Diagnose-Protokoll aktivieren</strong><small>Speichert maximal 80 technische ITKT-Ereignisse wie Sprach-404 und Routing-Reparaturen. Keine Formulardaten oder Kundendaten.</small></span></label>
                    <label class="itkt-switch-row"><input type="checkbox" name="inline_editor_enabled" value="1" <?php checked( ! isset( $s['inline_editor_enabled'] ) || ! empty( $s['inline_editor_enabled'] ) ); ?>><span class="itkt-switch"></span><span><strong>Frontend-Übersetzungsmodus für Administratoren</strong><small>Zeigt angemeldeten Administratoren in der Admin-Leiste „Übersetzen“. Erkannte Produkt- und Builder-Texte können direkt auf der Website bearbeitet werden.</small></span></label>
                </section>
                <section class="itkt-card"><span class="itkt-kicker">SEO</span><h2>Mehrsprachige Suchmaschinen-URLs</h2><p>Canonical- und hreflang-Tags, sprachabhängige Slugs sowie eigene SEO-Titel und Meta-Beschreibungen werden unterstützt.</p><dl class="itkt-system-list"><div><dt>Canonical</dt><dd>Automatisch je Sprache</dd></div><div><dt>hreflang</dt><dd>Aktive veröffentlichte Sprachen</dd></div><div><dt>Yoast / Rank Math</dt><dd>Grundintegration aktiv</dd></div><div><dt>Übersetzungs-Sitemap</dt><dd><a href="<?php echo esc_url(home_url('/itkt-translations-sitemap.xml'));?>" target="_blank" rel="noopener">Öffnen ↗</a></dd></div></dl></section>
                <section class="itkt-card"><span class="itkt-kicker">SYSTEM</span><h2>Aktuelle Konfiguration</h2><dl class="itkt-system-list"><div><dt>Plugin-Version</dt><dd><?php echo esc_html( ITKT_VERSION ); ?></dd></div><div><dt>Standardsprache</dt><dd><?php echo esc_html( strtoupper( $s['default_language'] ?? 'de' ) ); ?></dd></div><div><dt>Produkte</dt><dd>Eine Produkt-ID pro Produkt</dd></div><div><dt>Sprach-URLs</dt><dd>/en/ · /ar/ · /fr/ …</dd></div><div><dt>Website</dt><dd><a href="https://it-kayali.de" target="_blank" rel="noopener">it-kayali.de</a></dd></div></dl><p><a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=itkt-setup' ) ); ?>">Setup-Assistent erneut öffnen</a></p></section>
            </div>
            <p><button class="button button-primary itkt-primary" type="submit">Einstellungen speichern</button></p>
        </form>
        <?php $this->footer();
    }

    public function save_settings() {
        if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Unauthorized' ); }
        check_admin_referer( 'itkt_save_settings' );
        $s = get_option( 'itkt_settings', array() );
        $s['remember_language'] = ! empty( $_POST['remember_language'] );
        $s['redirect_saved_home'] = ! empty( $_POST['redirect_saved_home'] );
        $s['product_slug_fallback_default'] = ! empty( $_POST['product_slug_fallback_default'] );
        $s['diagnostic_logging'] = ! empty( $_POST['diagnostic_logging'] );
        $s['inline_editor_enabled'] = ! empty( $_POST['inline_editor_enabled'] );
        $fallback = sanitize_key( $_POST['fallback_mode'] ?? 'default' );
        $s['fallback_mode'] = in_array( $fallback, array( 'default', '404' ), true ) ? $fallback : 'default';
        update_option( 'itkt_settings', $s );
        wp_safe_redirect( admin_url( 'admin.php?page=itkt-settings&saved=1' ) );
        exit;
    }

}
