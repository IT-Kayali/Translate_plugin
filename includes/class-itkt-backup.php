<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class ITKT_Backup {
    private static $instance = null;
    const FORMAT = 'itkt-backup';
    const SCHEMA = 1;
    const MAX_UPLOAD_BYTES = 26214400;

    public static function instance() {
        if ( null === self::$instance ) { self::$instance = new self(); }
        return self::$instance;
    }

    private function __construct() {
        if ( ! is_admin() ) { return; }
        add_action( 'admin_menu', array( $this, 'admin_menu' ), 35 );
        add_action( 'admin_enqueue_scripts', array( $this, 'admin_assets' ) );
        add_action( 'admin_post_itkt_export_backup', array( $this, 'export_backup' ) );
        add_action( 'admin_post_itkt_restore_backup', array( $this, 'restore_backup' ) );
    }

    public function admin_menu() {
        add_submenu_page( 'itkt-dashboard', 'Backup / Restore', 'Backup / Restore', 'manage_options', 'itkt-backup', array( $this, 'render_page' ) );
    }

    public function admin_assets( $hook ) {
        if ( false !== strpos( (string) $hook, 'itkt-backup' ) ) {
            wp_enqueue_style( 'itkt-admin', ITKT_URL . 'admin/assets/admin.css', array(), ITKT_VERSION );
        }
    }

    private function excluded_options() {
        return array( 'itkt_rewrite_version','itkt_product_search_index_version','itkt_taxonomy_runtime_version','itkt_strings_db_version','itkt_diagnostic_log','itkt_diagnostic_stats','itkt_runtime_data_version' );
    }

    private function tables() {
        global $wpdb;
        return array(
            'strings' => $wpdb->prefix . 'itkt_strings',
            'string_sources' => $wpdb->prefix . 'itkt_string_sources',
            'string_translations' => $wpdb->prefix . 'itkt_string_translations',
        );
    }

    private function options() {
        global $wpdb;
        $like = $wpdb->esc_like( 'itkt_' ) . '%';
        $names = $wpdb->get_col( $wpdb->prepare( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s ORDER BY option_name", $like ) );
        $exclude = array_flip( $this->excluded_options() );
        $out = array();
        foreach ( (array) $names as $name ) {
            if ( ! isset( $exclude[ $name ] ) ) { $out[ $name ] = get_option( $name ); }
        }
        return $out;
    }

    private function table_rows() {
        global $wpdb;
        $out = array();
        foreach ( $this->tables() as $key => $table ) {
            $exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
            $out[ $key ] = $exists === $table ? (array) $wpdb->get_results( "SELECT * FROM {$table} ORDER BY id", ARRAY_A ) : array();
        }
        return $out;
    }

    private function meta_rows( $table, $id_column ) {
        global $wpdb;
        $like = $wpdb->esc_like( '_itkt_' ) . '%';
        return (array) $wpdb->get_results( $wpdb->prepare(
            "SELECT {$id_column},meta_key,meta_value FROM {$table} WHERE meta_key LIKE %s ORDER BY {$id_column},meta_key",
            $like
        ), ARRAY_A );
    }

    private function content_snapshot( $postmeta ) {
        $ids = array();
        foreach ( (array) $postmeta as $row ) {
            if ( in_array( (string) ( $row['meta_key'] ?? '' ), array( '_itkt_language','_itkt_group' ), true ) ) {
                $ids[ absint( $row['post_id'] ?? 0 ) ] = true;
            }
        }
        $out = array();
        foreach ( array_keys( $ids ) as $id ) {
            $post = get_post( $id );
            if ( ! $post ) { continue; }
            $out[] = array(
                'ID'=>(int)$post->ID,'post_type'=>(string)$post->post_type,'post_status'=>(string)$post->post_status,
                'post_title'=>(string)$post->post_title,'post_content'=>(string)$post->post_content,
                'post_excerpt'=>(string)$post->post_excerpt,'post_name'=>(string)$post->post_name,
                'post_parent'=>(int)$post->post_parent,'menu_order'=>(int)$post->menu_order,
            );
        }
        return $out;
    }

    public function build_backup() {
        global $wpdb;
        $postmeta = $this->meta_rows( $wpdb->postmeta, 'post_id' );
        return array(
            'format'=>self::FORMAT,'schema'=>self::SCHEMA,'plugin_version'=>ITKT_VERSION,
            'created_at'=>gmdate('c'),'site_url'=>home_url('/'),'blog_id'=>(int)get_current_blog_id(),
            'data'=>array(
                'options'=>$this->options(),'tables'=>$this->table_rows(),'postmeta'=>$postmeta,
                'termmeta'=>$this->meta_rows( $wpdb->termmeta, 'term_id' ),
                'content_posts'=>$this->content_snapshot( $postmeta ),
            ),
        );
    }

    public function export_backup() {
        if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Keine Berechtigung.' ); }
        check_admin_referer( 'itkt_export_backup' );
        $json = wp_json_encode( $this->build_backup(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
        if ( false === $json ) { wp_die( 'Backup konnte nicht erstellt werden.' ); }
        $host = sanitize_file_name( (string) wp_parse_url( home_url('/'), PHP_URL_HOST ) ?: 'site' );
        nocache_headers();
        header( 'Content-Type: application/json; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename="it-kayali-translate-backup-' . $host . '-' . gmdate('Y-m-d-His') . '.json"' );
        header( 'X-Content-Type-Options: nosniff' );
        echo $json; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        exit;
    }

    private function normalized_site_identity( $url ) {
        $parts = wp_parse_url( (string) $url );
        if ( ! is_array( $parts ) ) { return ''; }
        $host = strtolower( (string) ( $parts['host'] ?? '' ) );
        $path = trim( (string) ( $parts['path'] ?? '' ), '/' );
        return $host . ( $path ? '/' . $path : '' );
    }

    private function validate_string_table_relations( $tables ) {
        $string_ids = array();
        foreach ( (array) ( $tables['strings'] ?? array() ) as $row ) {
            if ( ! is_array( $row ) ) { return false; }
            $id = absint( $row['id'] ?? 0 );
            if ( ! $id || isset( $string_ids[ $id ] ) ) { return false; }
            $string_ids[ $id ] = true;
        }
        foreach ( array( 'string_sources', 'string_translations' ) as $key ) {
            foreach ( (array) ( $tables[ $key ] ?? array() ) as $row ) {
                if ( ! is_array( $row ) ) { return false; }
                $string_id = absint( $row['string_id'] ?? 0 );
                if ( ! $string_id || ! isset( $string_ids[ $string_id ] ) ) { return false; }
            }
        }
        return true;
    }

    private function validate( $payload ) {
        if ( ! is_array( $payload ) || self::FORMAT !== ( $payload['format'] ?? '' ) ) { return new WP_Error( 'invalid_backup', 'Die Datei ist kein gültiges IT-Kayali-Translate-Backup.' ); }
        if ( self::SCHEMA !== absint( $payload['schema'] ?? 0 ) ) { return new WP_Error( 'invalid_schema', 'Diese Backup-Version wird nicht unterstützt.' ); }
        if ( empty( $payload['data'] ) || ! is_array( $payload['data'] ) ) { return new WP_Error( 'missing_data', 'Backup-Daten fehlen.' ); }

        $backup_site = $this->normalized_site_identity( $payload['site_url'] ?? '' );
        $current_site = $this->normalized_site_identity( home_url( '/' ) );
        if ( ! $backup_site || ! $current_site || ! hash_equals( $current_site, $backup_site ) ) {
            return new WP_Error( 'site_mismatch', 'Dieses Backup gehört zu einer anderen Website. Die Wiederherstellung wurde zum Schutz vorhandener Daten abgebrochen.' );
        }
        if ( isset( $payload['blog_id'] ) && absint( $payload['blog_id'] ) !== (int) get_current_blog_id() ) {
            return new WP_Error( 'blog_mismatch', 'Dieses Backup gehört zu einer anderen WordPress-Site im Netzwerk.' );
        }

        $data = $payload['data'];
        foreach ( array( 'options', 'tables', 'postmeta', 'termmeta', 'content_posts' ) as $key ) {
            if ( ! array_key_exists( $key, $data ) || ! is_array( $data[ $key ] ) ) {
                return new WP_Error( 'incomplete_backup', 'Das Backup ist unvollständig und wurde nicht wiederhergestellt.' );
            }
        }
        foreach ( array( 'strings', 'string_sources', 'string_translations' ) as $key ) {
            if ( ! array_key_exists( $key, $data['tables'] ) || ! is_array( $data['tables'][ $key ] ) ) {
                return new WP_Error( 'incomplete_tables', 'Das Backup enthält nicht alle erforderlichen String-Tabellen.' );
            }
        }
        if ( ! $this->validate_string_table_relations( $data['tables'] ) ) {
            return new WP_Error( 'invalid_relations', 'Die String-Daten im Backup sind inkonsistent. Es wurden keine Daten verändert.' );
        }
        return true;
    }

    private function restore_options( $options ) {
        $count = 0;
        $exclude = array_flip( $this->excluded_options() );
        foreach ( (array) $options as $name => $value ) {
            $name = sanitize_key( (string) $name );
            if ( 0 !== strpos( $name, 'itkt_' ) || isset( $exclude[ $name ] ) ) { continue; }
            update_option( $name, $value, false );
            $count++;
        }
        return $count;
    }

    private function restore_tables( $tables ) {
        global $wpdb;
        $names = $this->tables();
        foreach ( array( 'string_translations','string_sources','strings' ) as $key ) { $wpdb->query( 'DELETE FROM ' . $names[ $key ] ); }
        $count = 0;
        foreach ( array( 'strings','string_sources','string_translations' ) as $key ) {
            foreach ( (array) ( $tables[ $key ] ?? array() ) as $row ) {
                if ( ! is_array( $row ) || ! $row ) { continue; }
                $clean = array();
                foreach ( $row as $column => $value ) {
                    if ( is_scalar( $value ) || null === $value ) { $clean[ sanitize_key( (string) $column ) ] = $value; }
                }
                if ( $clean && false === $wpdb->insert( $names[ $key ], $clean ) ) { throw new RuntimeException( 'String-Tabellen konnten nicht vollständig wiederhergestellt werden.' ); }
                if ( $clean ) { $count++; }
            }
        }
        return $count;
    }

    private function restore_meta( $table, $id_column, $rows, $entity_type ) {
        global $wpdb;
        $like = $wpdb->esc_like( '_itkt_' ) . '%';
        $wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE meta_key LIKE %s", $like ) );
        $restored = 0;
        $skipped = 0;
        foreach ( (array) $rows as $row ) {
            $id = absint( $row[ $id_column ] ?? 0 );
            $key = (string) ( $row['meta_key'] ?? '' );
            if ( ! $id || 0 !== strpos( $key, '_itkt_' ) ) { $skipped++; continue; }

            $exists = 'term' === $entity_type ? term_exists( $id ) : get_post( $id );
            if ( ! $exists ) { $skipped++; continue; }

            if ( false === $wpdb->insert( $table, array( $id_column=>$id,'meta_key'=>$key,'meta_value'=>(string)($row['meta_value'] ?? '') ), array('%d','%s','%s') ) ) {
                throw new RuntimeException( 'ITKT-Metadaten konnten nicht vollständig wiederhergestellt werden.' );
            }
            $restored++;
        }
        return array( 'restored'=>$restored, 'skipped'=>$skipped );
    }

    private function restore_posts( $posts ) {
        $updated = 0; $skipped = 0;
        foreach ( (array) $posts as $row ) {
            $id = absint( $row['ID'] ?? 0 );
            $current = $id ? get_post( $id ) : null;
            if ( ! $current ) { $skipped++; continue; }
            if ( ! empty( $row['post_type'] ) && (string) $row['post_type'] !== (string) $current->post_type ) { $skipped++; continue; }
            $data = array( 'ID'=>$id );
            foreach ( array('post_title','post_content','post_excerpt','post_name','post_status') as $field ) {
                if ( array_key_exists( $field, $row ) ) { $data[ $field ] = (string) $row[ $field ]; }
            }
            if ( isset( $row['post_parent'] ) ) { $data['post_parent'] = absint( $row['post_parent'] ); }
            if ( isset( $row['menu_order'] ) ) { $data['menu_order'] = intval( $row['menu_order'] ); }
            $result = wp_update_post( wp_slash( $data ), true );
            if ( is_wp_error( $result ) ) { $skipped++; } else { $updated++; }
        }
        return array( 'updated'=>$updated,'skipped'=>$skipped );
    }

    public function apply_backup( $payload ) {
        global $wpdb;
        $valid = $this->validate( $payload );
        if ( is_wp_error( $valid ) ) { return $valid; }
        $data = $payload['data'];

        // dbDelta may execute DDL, which can implicitly commit in MySQL. Ensure the schema before
        // starting the data transaction so a failed restore can still roll back its destructive writes.
        ITKT_Strings::install_schema();
        $wpdb->query( 'START TRANSACTION' );
        try {
            $result = array(
                'options'=>$this->restore_options( $data['options'] ?? array() ),
                'table_rows'=>$this->restore_tables( $data['tables'] ?? array() ),
                'postmeta'=>$this->restore_meta( $wpdb->postmeta, 'post_id', $data['postmeta'] ?? array(), 'post' ),
                'termmeta'=>$this->restore_meta( $wpdb->termmeta, 'term_id', $data['termmeta'] ?? array(), 'term' ),
                'content_posts'=>$this->restore_posts( $data['content_posts'] ?? array() ),
            );
            delete_option( 'itkt_rewrite_version' );
            delete_option( 'itkt_product_search_index_version' );
            delete_option( 'itkt_taxonomy_runtime_version' );
            delete_option( 'itkt_runtime_data_version' );
            $wpdb->query( 'COMMIT' );
            wp_cache_flush();
            do_action( 'itkt_backup_restored', $result );
            return $result;
        } catch ( Throwable $e ) {
            $wpdb->query( 'ROLLBACK' );
            // WordPress may already have primed object-cache entries during the failed transaction.
            wp_cache_flush();
            return new WP_Error( 'restore_failed', 'Restore fehlgeschlagen: ' . $e->getMessage() );
        }
    }

    private function redirect( $type, $message ) {
        wp_safe_redirect( add_query_arg( array( 'page'=>'itkt-backup','itkt_notice'=>sanitize_key($type),'itkt_message'=>rawurlencode($message) ), admin_url('admin.php') ) );
        exit;
    }

    public function restore_backup() {
        if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Keine Berechtigung.' ); }
        check_admin_referer( 'itkt_restore_backup' );
        if ( empty( $_POST['confirm_restore'] ) ) { $this->redirect( 'error', 'Bitte die Sicherheitsbestätigung aktivieren.' ); }
        $file = $_FILES['itkt_backup_file'] ?? null;
        if ( ! is_array( $file ) || UPLOAD_ERR_OK !== (int)($file['error'] ?? UPLOAD_ERR_NO_FILE) ) { $this->redirect( 'error', 'Die Backup-Datei konnte nicht hochgeladen werden.' ); }
        if ( absint( $file['size'] ?? 0 ) > self::MAX_UPLOAD_BYTES ) { $this->redirect( 'error', 'Die Backup-Datei ist größer als 25 MB.' ); }
        $tmp = (string) ( $file['tmp_name'] ?? '' );
        if ( ! $tmp || ! is_uploaded_file( $tmp ) ) { $this->redirect( 'error', 'Ungültiger Datei-Upload.' ); }
        $payload = json_decode( (string) file_get_contents( $tmp ), true );
        if ( JSON_ERROR_NONE !== json_last_error() ) { $this->redirect( 'error', 'Ungültiges JSON: ' . json_last_error_msg() ); }
        $result = $this->apply_backup( $payload );
        if ( is_wp_error( $result ) ) { $this->redirect( 'error', $result->get_error_message() ); }
        $this->redirect( 'success', sprintf(
            'Restore abgeschlossen: %d Optionen, %d Tabellenzeilen, %d Post-Metadaten, %d Term-Metadaten, %d Inhalte aktualisiert. Übersprungen: %d Post-Metadaten, %d Term-Metadaten, %d Inhalte.',
            absint($result['options']),absint($result['table_rows']),
            absint($result['postmeta']['restored'] ?? 0),absint($result['termmeta']['restored'] ?? 0),
            absint($result['content_posts']['updated'] ?? 0),absint($result['postmeta']['skipped'] ?? 0),
            absint($result['termmeta']['skipped'] ?? 0),absint($result['content_posts']['skipped'] ?? 0)
        ) );
    }

    private function counts() {
        global $wpdb;
        $tables = $this->table_rows();
        $postmeta = $this->meta_rows( $wpdb->postmeta, 'post_id' );
        return array(
            'options'=>count($this->options()),'strings'=>count($tables['strings'] ?? array()),
            'translations'=>count($tables['string_translations'] ?? array()),'postmeta'=>count($postmeta),
            'termmeta'=>count($this->meta_rows($wpdb->termmeta,'term_id')),'posts'=>count($this->content_snapshot($postmeta)),
        );
    }

    public function render_page() {
        if ( ! current_user_can( 'manage_options' ) ) { return; }
        $c = $this->counts();
        $type = sanitize_key( $_GET['itkt_notice'] ?? '' );
        $message = isset($_GET['itkt_message']) ? rawurldecode( sanitize_text_field( wp_unslash($_GET['itkt_message']) ) ) : '';
        ?>
        <div class="wrap itkt-wrap">
            <div class="itkt-topbar"><div><div class="itkt-brand"><span class="itkt-brand-mark">ITK</span><span>IT-Kayali Translate</span></div><h1>Backup / Restore</h1><p class="itkt-lead">Plugin-Konfiguration und Übersetzungsdaten sichern und kontrolliert wiederherstellen.</p></div><a class="itkt-site-link" href="https://it-kayali.de" target="_blank" rel="noopener">it-kayali.de ↗</a></div>
            <?php if ( $message ) : ?><div class="notice <?php echo 'success' === $type ? 'notice-success' : 'notice-error'; ?> inline"><p><strong><?php echo esc_html($message); ?></strong></p></div><?php endif; ?>
            <section class="itkt-card">
                <div class="itkt-card-head"><div><span class="itkt-kicker">SICHERUNG</span><h2>ITKT-Backup exportieren</h2></div><span class="itkt-pill green">v<?php echo esc_html( ITKT_VERSION ); ?></span></div>
                <p>Sichert Einstellungen, Sprachen, Strings, Übersetzungen, Produkt-/Taxonomie-Metadaten und einen Snapshot verknüpfter übersetzter Inhalte.</p>
                <p><strong>Aktuell:</strong> <?php echo intval($c['options']); ?> Optionen · <?php echo intval($c['strings']); ?> Strings · <?php echo intval($c['translations']); ?> Übersetzungen · <?php echo intval($c['postmeta']); ?> Post-Metadaten · <?php echo intval($c['termmeta']); ?> Term-Metadaten · <?php echo intval($c['posts']); ?> Inhalte.</p>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"><input type="hidden" name="action" value="itkt_export_backup"><?php wp_nonce_field('itkt_export_backup'); ?><button class="button button-primary itkt-primary" type="submit">Backup herunterladen</button></form>
            </section>
            <section class="itkt-card">
                <div class="itkt-card-head"><div><span class="itkt-kicker">WIEDERHERSTELLUNG</span><h2>Backup wiederherstellen</h2></div><span class="itkt-pill gray">ohne Duplikate</span></div>
                <p>Restore ersetzt nur ITKT-verwaltete String-Daten und <code>_itkt_*</code>-Metadaten. Produkte, Seiten, Beiträge und Begriffe werden nicht neu erstellt. Fehlende IDs werden übersprungen. Aus Sicherheitsgründen muss das Backup von derselben WordPress-Site stammen.</p>
                <form method="post" enctype="multipart/form-data" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"><input type="hidden" name="action" value="itkt_restore_backup"><?php wp_nonce_field('itkt_restore_backup'); ?><p><input type="file" name="itkt_backup_file" accept="application/json,.json" required></p><label><input type="checkbox" name="confirm_restore" value="1" required> Ich bestätige, dass aktuelle ITKT-Übersetzungsdaten durch den Backup-Stand ersetzt werden dürfen.</label><p><button class="button" type="submit">Backup wiederherstellen</button></p></form>
            </section>
            <section class="itkt-card"><h2>Sicherheit</h2><p>Nur Administratoren können Export/Restore ausführen. Import akzeptiert nur das ITKT-JSON-Format bis 25 MB und prüft Site-Zuordnung, Pflichtbereiche sowie interne String-Beziehungen vor dem Überschreiben. Abgeleitete Routing-/Such-/Runtime-Caches werden anschließend neu aufgebaut.</p></section>
        </div>
        <?php
    }
}
