<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class ITKT_Diagnostics {
    const LOG_OPTION = 'itkt_diagnostic_log';
    const STATS_TRANSIENT = 'itkt_diagnostic_stats';
    const ACCEPTANCE_OPTION = 'itkt_acceptance_status';
    private static $instance = null;

    public static function instance() {
        if ( null === self::$instance ) { self::$instance = new self(); }
        return self::$instance;
    }

    private function __construct() {
        add_action( 'template_redirect', array( $this, 'capture_translated_404' ), 99 );
        add_action( 'itkt_product_translation_saved', array( $this, 'invalidate_stats' ), 10, 3 );
        add_action( 'itkt_translation_created', array( $this, 'invalidate_stats' ), 10, 3 );
        add_action( 'itkt_existing_translation_linked', array( $this, 'invalidate_stats' ), 10, 3 );
        add_action( 'itkt_elementor_layout_repaired', array( $this, 'invalidate_stats' ), 10, 2 );
    }

    public function logging_enabled() {
        $settings = get_option( 'itkt_settings', array() );
        return ! array_key_exists( 'diagnostic_logging', $settings ) || ! empty( $settings['diagnostic_logging'] );
    }

    public function log( $level, $event, $message, $context = array() ) {
        if ( ! $this->logging_enabled() ) { return; }
        $allowed = array( 'info', 'warning', 'error', 'success' );
        $level = in_array( $level, $allowed, true ) ? $level : 'info';
        $entry = array(
            'time'    => current_time( 'mysql' ),
            'level'   => $level,
            'event'   => sanitize_key( $event ),
            'message' => sanitize_text_field( $message ),
            'context' => $this->sanitize_context( $context ),
        );
        $log = get_option( self::LOG_OPTION, array() );
        if ( ! is_array( $log ) ) { $log = array(); }
        array_unshift( $log, $entry );
        $log = array_slice( $log, 0, 80 );
        update_option( self::LOG_OPTION, $log, false );
    }

    private function sanitize_context( $context ) {
        $clean = array();
        foreach ( (array) $context as $key => $value ) {
            $key = sanitize_key( (string) $key );
            if ( ! $key ) { continue; }
            if ( is_bool( $value ) ) { $clean[ $key ] = $value ? '1' : '0'; continue; }
            if ( is_scalar( $value ) ) { $text = sanitize_text_field( (string) $value ); $clean[ $key ] = function_exists( 'mb_substr' ) ? mb_substr( $text, 0, 300, 'UTF-8' ) : substr( $text, 0, 300 ); }
        }
        return $clean;
    }

    public function get_log() {
        $log = get_option( self::LOG_OPTION, array() );
        return is_array( $log ) ? $log : array();
    }

    public function clear_log() { delete_option( self::LOG_OPTION ); }

    public function invalidate_stats() { delete_transient( self::STATS_TRANSIENT ); }

    public function capture_translated_404() {
        if ( is_admin() || ! is_404() ) { return; }
        $uri = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
        $path = (string) wp_parse_url( $uri, PHP_URL_PATH );
        $parts = array_values( array_filter( explode( '/', trim( $path, '/' ) ), 'strlen' ) );
        if ( count( $parts ) < 2 ) { return; }
        $lang = sanitize_key( (string) $parts[0] );
        $active = ITKT_Languages::instance()->get_active();
        if ( empty( $active[ $lang ] ) ) { return; }
        $event = ( in_array( sanitize_key( (string) ( $parts[1] ?? '' ) ), array( 'product', 'produkt' ), true ) ) ? 'product_404' : 'translated_404';
        $this->log( 'warning', $event, 'Eine Sprach-URL endete auf einer 404-Seite.', array( 'lang' => $lang, 'path' => $path ) );
    }

    public function stats() {
        $cached = get_transient( self::STATS_TRANSIENT );
        if ( is_array( $cached ) ) { return $cached; }

        $targets = ITKT_Languages::instance()->get_active();
        unset( $targets[ ITKT_Languages::instance()->get_default_code() ] );
        $stats = array(
            'total'    => 0,
            'complete' => 0,
            'missing'  => 0,
            'partial'  => 0,
            'outdated' => 0,
            'draft'    => 0,
            'by_type'  => array(),
        );

        foreach ( array( 'page' => 'Seiten', 'post' => 'Beiträge' ) as $post_type => $label ) {
            $ids = get_posts( array(
                'post_type'        => $post_type,
                'post_status'      => array( 'publish', 'draft', 'private', 'pending' ),
                'numberposts'      => -1,
                'fields'           => 'ids',
                'meta_query'       => array( array( 'key' => '_itkt_language', 'value' => ITKT_Languages::instance()->get_default_code() ) ),
                'suppress_filters' => true,
            ) );
            $row = array( 'label' => $label, 'total' => 0, 'complete' => 0, 'missing' => 0, 'partial' => 0, 'outdated' => 0, 'draft' => 0 );
            foreach ( $ids as $id ) {
                $translations = ITKT_Content::instance()->translations_for( $id );
                foreach ( $targets as $code => $language ) {
                    $status = ITKT_Content::instance()->translation_status( $id, $translations[ $code ] ?? null );
                    $status = isset( $row[ $status ] ) ? $status : ( 'complete' === $status ? 'complete' : 'partial' );
                    $row['total']++; $row[ $status ]++;
                    $stats['total']++; $stats[ $status ]++;
                }
            }
            $stats['by_type'][ $post_type ] = $row;
        }

        if ( ITKT_Plugin::module_enabled( 'woocommerce' ) && post_type_exists( 'product' ) && class_exists( 'ITKT_Product_Translations' ) ) {
            $ids = get_posts( array( 'post_type'=>'product', 'post_status'=>array('publish','draft','private','pending'), 'numberposts'=>-1, 'fields'=>'ids', 'suppress_filters'=>true ) );
            $row = array( 'label' => 'Produkte', 'total' => 0, 'complete' => 0, 'missing' => 0, 'partial' => 0, 'outdated' => 0, 'draft' => 0 );
            foreach ( $ids as $id ) {
                foreach ( $targets as $code => $language ) {
                    $status = ITKT_Product_Translations::instance()->status( $id, $code );
                    if ( ! isset( $row[ $status ] ) ) { $status = 'partial'; }
                    $row['total']++; $row[ $status ]++;
                    $stats['total']++; $stats[ $status ]++;
                }
            }
            $stats['by_type']['product'] = $row;
        }

        set_transient( self::STATS_TRANSIENT, $stats, 5 * MINUTE_IN_SECONDS );
        return $stats;
    }

    public function self_test() {
        global $wpdb;
        $checks = array();
        $add = function( $id, $label, $ok, $detail, $severity = 'error' ) use ( &$checks ) {
            $checks[] = array( 'id'=>$id, 'label'=>$label, 'ok'=>(bool)$ok, 'detail'=>$detail, 'severity'=>$severity );
        };

        $settings = get_option( 'itkt_settings', array() );
        $add( 'setup', 'Plugin-Einrichtung', ! empty( $settings['setup_complete'] ), ! empty( $settings['setup_complete'] ) ? 'Setup abgeschlossen.' : 'Setup-Assistent wurde noch nicht abgeschlossen.' );

        $active = ITKT_Languages::instance()->get_active();
        $add( 'languages', 'Aktive Sprachen', count( $active ) >= 1, count( $active ) . ' aktive Sprache(n): ' . implode( ', ', array_keys( $active ) ) );

        $permalink = (string) get_option( 'permalink_structure' );
        $add( 'permalinks', 'Permalink-Struktur', '' !== $permalink, $permalink ?: 'Einfache Permalinks sind aktiv; Sprach-Routing ist damit eingeschränkt.' );

        $rules = get_option( 'rewrite_rules', array() );
        $rules_text = is_array( $rules ) ? implode( "\n", array_keys( $rules ) ) . "\n" . implode( "\n", $rules ) : '';
        $add( 'rewrite', 'ITKT Produkt-Routing', false !== strpos( $rules_text, 'itkt_product_slug' ), false !== strpos( $rules_text, 'itkt_product_slug' ) ? 'Übersetzte Produkt-Rewrite-Regel ist registriert.' : 'Produkt-Rewrite-Regel fehlt. Permalinks neu laden.' );

        if ( ITKT_Plugin::module_enabled( 'woocommerce' ) ) {
            $wc_ok = post_type_exists( 'product' ) && class_exists( 'ITKT_WooCommerce' );
            $add( 'woocommerce', 'WooCommerce Adapter', $wc_ok, $wc_ok ? 'WooCommerce-Übersetzungsschicht ist aktiv.' : 'WooCommerce-Modul ist aktiviert, Adapter oder Produkt-Post-Type fehlt.' );
        }

        if ( ITKT_Plugin::module_enabled( 'woocommerce' ) && ITKT_Plugin::module_enabled( 'strings' ) && class_exists( 'ITKT_Native_Translations' ) ) {
            $missing_catalogs = array();
            foreach ( $active as $code => $language ) {
                // English is WooCommerce's canonical source and needs no language-pack file.
                if ( 'en' === $code ) { continue; }
                if ( ! ITKT_Native_Translations::instance()->has_catalog( 'woocommerce', $code ) ) { $missing_catalogs[] = strtoupper( $code ); }
            }
            $catalog_ok = empty( $missing_catalogs );
            $detail = $catalog_ok
                ? 'Native WooCommerce-Sprachkataloge für die aktiven Sprachen wurden gefunden.'
                : 'Kein nativer WooCommerce-Sprachkatalog gefunden für: ' . implode( ', ', $missing_catalogs ) . '. Eigene ITKT-Overrides funktionieren weiterhin.';
            $add( 'woocommerce_catalogs', 'WooCommerce Sprachpakete', $catalog_ok, $detail, 'warning' );
        }

        $missing_hash = 0; $duplicate_hashes = 0; $translated_slugs = 0;
        if ( post_type_exists( 'product' ) ) {
            $rows = $wpdb->get_results( "SELECT post_id, meta_key, meta_value FROM {$wpdb->postmeta} WHERE meta_key LIKE '_itkt_slug_%' AND meta_key NOT LIKE '_itkt_slug_hash_%' AND meta_value <> ''" );
            foreach ( (array) $rows as $row ) {
                $translated_slugs++;
                $lang = sanitize_key( substr( $row->meta_key, strlen( '_itkt_slug_' ) ) );
                $expected = class_exists( 'ITKT_Product_Translations' ) ? ITKT_Product_Translations::public_slug_hash( $row->meta_value ) : '';
                $actual = get_post_meta( $row->post_id, '_itkt_slug_hash_' . $lang, true );
                if ( $expected && ! hash_equals( $expected, (string) $actual ) ) { $missing_hash++; }
            }
            $duplicates = $wpdb->get_results( "SELECT meta_key, meta_value, COUNT(*) AS c FROM {$wpdb->postmeta} WHERE meta_key LIKE '_itkt_slug_hash_%' AND meta_value <> '' GROUP BY meta_key, meta_value HAVING COUNT(*) > 1" );
            $duplicate_hashes = count( (array) $duplicates );
        }
        $add( 'slug_hashes', 'Produkt-Slug-Index', 0 === $missing_hash, $translated_slugs . ' übersetzte Slugs, ' . $missing_hash . ' fehlende/veraltete Hash-Indizes.', 'warning' );
        $add( 'slug_duplicates', 'Doppelte Produkt-Slugs', 0 === $duplicate_hashes, $duplicate_hashes ? $duplicate_hashes . ' doppelte sprachabhängige Slug-Indizes gefunden.' : 'Keine doppelten sprachabhängigen Slugs erkannt.', 'warning' );

        if ( ITKT_Plugin::module_enabled( 'woocommerce' ) && class_exists( 'ITKT_Product_Translations' ) ) {
            $search_version_ok = get_option( 'itkt_product_search_index_version' ) === ITKT_Product_Translations::SEARCH_INDEX_VERSION;
            $add( 'product_search_index', 'Produkt-Suchindex', $search_version_ok, $search_version_ok ? 'Sprachabhängiger Produkt-Suchindex ist aktuell.' : 'Produkt-Suchindex wird noch aufgebaut oder sollte manuell repariert werden.', 'warning' );
        }

        if ( ITKT_Plugin::module_enabled( 'strings' ) ) {
            $expected_tables = array(
                $wpdb->prefix . 'itkt_strings'             => 'Strings',
                $wpdb->prefix . 'itkt_string_sources'      => 'Quellen',
                $wpdb->prefix . 'itkt_string_translations' => 'Übersetzungen',
            );
            $missing_tables = array();
            foreach ( $expected_tables as $table => $label ) {
                $exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) );
                if ( $exists !== $table ) { $missing_tables[] = $label; }
            }
            $tables_ok = empty( $missing_tables );
            $add(
                'strings_schema',
                'String-Translation Datenbank',
                $tables_ok,
                $tables_ok ? 'Alle String-Translation Tabellen sind vorhanden.' : 'Fehlende Tabellen: ' . implode( ', ', $missing_tables ) . '. Plugin einmal deaktivieren/aktivieren oder Schema reparieren.'
            );

            $catalog_table = $wpdb->prefix . 'itkt_string_sources';
            $frontend_count = $tables_ok ? absint( $wpdb->get_var( "SELECT COUNT(DISTINCT string_id) FROM {$catalog_table} WHERE source_type='frontend'" ) ) : 0;
            $add(
                'frontend_catalog',
                'Frontend-Textkatalog',
                $frontend_count > 0,
                $frontend_count > 0 ? $frontend_count . ' Frontend-Text(e) wurden bereits automatisch erfasst.' : 'Noch keine Frontend-Texte erfasst. Als Administrator die Standardsprache einmal durch Shop, Checkout, Konto und Popups durchgehen.',
                'warning'
            );
        }

        if ( ITKT_Plugin::module_enabled( 'woocommerce' ) && function_exists( 'wc_get_page_id' ) ) {
            $required_pages = array(
                'shop'      => 'Shop',
                'cart'      => 'Warenkorb',
                'checkout'  => 'Checkout',
                'myaccount' => 'Mein Konto',
            );
            $missing_pages = array();
            foreach ( $required_pages as $key => $label ) {
                $page_id = (int) wc_get_page_id( $key );
                if ( $page_id <= 0 || ! get_post( $page_id ) || 'trash' === get_post_status( $page_id ) ) {
                    $missing_pages[] = $label;
                }
            }
            $pages_ok = empty( $missing_pages );
            $add(
                'woocommerce_pages',
                'WooCommerce Systemseiten',
                $pages_ok,
                $pages_ok ? 'Shop, Warenkorb, Checkout und Mein Konto sind gültig zugewiesen.' : 'Fehlende/ungültige WooCommerce-Seiten: ' . implode( ', ', $missing_pages ) . '.',
                'warning'
            );

            $endpoint_options = array(
                'orders'          => 'woocommerce_myaccount_orders_endpoint',
                'downloads'       => 'woocommerce_myaccount_downloads_endpoint',
                'edit-address'    => 'woocommerce_myaccount_edit_address_endpoint',
                'edit-account'    => 'woocommerce_myaccount_edit_account_endpoint',
                'customer-logout' => 'woocommerce_logout_endpoint',
            );
            $missing_endpoints = array();
            foreach ( $endpoint_options as $label => $option ) {
                if ( '' === trim( (string) get_option( $option, '' ) ) ) { $missing_endpoints[] = $label; }
            }
            $endpoints_ok = empty( $missing_endpoints );
            $add(
                'woocommerce_account_endpoints',
                'WooCommerce Konto-Endpunkte',
                $endpoints_ok,
                $endpoints_ok ? 'Die zentralen Mein-Konto-Endpunkte sind konfiguriert.' : 'Leere WooCommerce-Endpunkte: ' . implode( ', ', $missing_endpoints ) . '.',
                'warning'
            );

            if ( class_exists( 'ITKT_WooCommerce' ) ) {
                $wc_bridge = ITKT_WooCommerce::instance();
                $email_start = has_filter( 'woocommerce_allow_switching_email_locale', array( $wc_bridge, 'email_default_context_start' ) );
                $email_end   = has_filter( 'woocommerce_allow_restoring_email_locale', array( $wc_bridge, 'email_default_context_end' ) );
                $email_ok = false !== $email_start && false !== $email_end;
                $add(
                    'woocommerce_email_isolation',
                    'E-Mail Sprachisolation',
                    $email_ok,
                    $email_ok ? 'Transaktionale WooCommerce-E-Mails bleiben vom Frontend-Sprachkontext isoliert.' : 'E-Mail-Sprachisolation ist nicht vollständig registriert.',
                    'warning'
                );
            }
        }

        $runtime_modules = array(
            'ITKT_Dynamic_Runtime' => 'Dynamische Runtime',
            'ITKT_Backup'          => 'Backup / Restore',
            'ITKT_Cache'           => 'Cache-Invalidierung',
        );
        $missing_runtime = array();
        foreach ( $runtime_modules as $class => $label ) {
            if ( ! class_exists( $class ) ) { $missing_runtime[] = $label; }
        }
        $runtime_ok = empty( $missing_runtime );
        $add(
            'production_modules',
            'Produktionsmodule',
            $runtime_ok,
            $runtime_ok ? 'Dynamische Runtime, Backup/Restore und Cache-Invalidierung sind geladen.' : 'Nicht geladen: ' . implode( ', ', $missing_runtime ) . '.'
        );

        if ( function_exists( 'wpfc_clear_all_cache' ) || class_exists( 'WpFastestCache' ) ) {
            $add(
                'wp_fastest_cache',
                'WP Fastest Cache Integration',
                true,
                'WP Fastest Cache wurde erkannt; ITKT kann den Cache nach Übersetzungsänderungen automatisch leeren.',
                'warning'
            );
        }

        $memory = wp_convert_hr_to_bytes( ini_get( 'memory_limit' ) );
        $add( 'memory', 'PHP Memory Limit', $memory < 0 || $memory >= 128 * MB_IN_BYTES, ini_get( 'memory_limit' ) . ' PHP Memory Limit.', 'warning' );

        return $checks;
    }

    /**
     * Manual production acceptance matrix. These checks deliberately stay manual because they
     * describe real browser behaviour (AJAX, WoodMart drawers, checkout and language switching)
     * that cannot be proven safely by a server-side self-test alone.
     */
    public function acceptance_catalog() {
        $languages = ITKT_Languages::instance()->get_active();
        $sections = array();

        $journey = array(
            'shop'       => 'Shop öffnet korrekt und sichtbare Texte passen.',
            'search'     => 'Suche liefert passende Ergebnisse und bleibt in der Sprache.',
            'product'    => 'Produktseite bleibt beim Sprachwechsel beim selben Produkt.',
            'category'   => 'Kategorie/Archiv bleibt beim Sprachwechsel im selben Kontext.',
            'filters'    => 'Filter/Attribute funktionieren und dynamische Texte bleiben übersetzt.',
            'mini_cart'  => 'Mini-Cart/Drawer funktioniert nach AJAX und bleibt übersetzt.',
            'cart'       => 'Warenkorb funktioniert inklusive Mengen-/Entfernen-Refresh.',
            'checkout'   => 'Checkout funktioniert inklusive Versand, Zahlung und Validierung.',
            'account'    => 'Mein Konto und Endpunkte öffnen ohne Dashboard-Fallback.',
            'wishlist'   => 'Wishlist funktioniert und bleibt in der aktiven Sprache.',
            'popup'      => 'Popups/Offcanvas-Inhalte werden korrekt übersetzt.',
            'switch'     => 'Sprachwechsel behält dieselbe logische Seite bzw. denselben Endpoint.',
        );

        foreach ( $languages as $code => $language ) {
            $code = sanitize_key( (string) $code );
            if ( ! $code ) { continue; }
            $label = trim( (string) ( $language['native_name'] ?? strtoupper( $code ) ) );
            $items = array();
            foreach ( $journey as $key => $item_label ) {
                $items[ $code . '__' . $key ] = $item_label;
            }
            $sections[ 'lang_' . $code ] = array(
                'label' => strtoupper( $code ) . ' · ' . $label,
                'items' => $items,
            );
        }

        $sections['cross'] = array(
            'label' => 'Dynamik, Editor, Backup & Cache',
            'items' => array(
                'cross__frontend_catalog' => 'Frontend Texte nach dem Durchklicken prüfen und mindestens einen Text speichern.',
                'cross__ajax'             => 'WooCommerce AJAX/Blocks testen: Warenkorb, Coupon, Versand/Zahlung und Checkout-Fehler.',
                'cross__live_editor'      => 'Live-Editor an Header, Footer, Menü, Popup/Offcanvas, Wishlist und Notice testen.',
                'cross__backup_restore'   => 'Backup exportieren, harmlose Übersetzung ändern und per Restore wiederherstellen.',
                'cross__cache'            => 'Mit aktivem Cache eine Übersetzung ändern und in privatem Browser kontrollieren.',
                'cross__email'            => 'Transaktionale WooCommerce-E-Mail prüfen: Ausgabe bleibt in der Standardsprache.',
                'cross__mobile'           => 'Mindestens einen vollständigen Durchlauf auf Handy/Tablet prüfen.',
            ),
        );

        return $sections;
    }

    public function acceptance_state() {
        $state = get_option( self::ACCEPTANCE_OPTION, array() );
        if ( ! is_array( $state ) ) { $state = array(); }
        $checks = isset( $state['checks'] ) && is_array( $state['checks'] ) ? $state['checks'] : array();
        return array(
            'version'    => (string) ( $state['version'] ?? '' ),
            'checks'     => $checks,
            'updated_at' => (string) ( $state['updated_at'] ?? '' ),
        );
    }

    public function save_acceptance_state( $submitted ) {
        $allowed = array();
        foreach ( $this->acceptance_catalog() as $section ) {
            foreach ( (array) ( $section['items'] ?? array() ) as $id => $label ) {
                $allowed[ sanitize_key( (string) $id ) ] = true;
            }
        }

        $checks = array();
        foreach ( (array) $submitted as $id => $value ) {
            $id = sanitize_key( (string) $id );
            if ( isset( $allowed[ $id ] ) && ! empty( $value ) ) { $checks[ $id ] = 1; }
        }

        $state = array(
            'version'    => ITKT_VERSION,
            'checks'     => $checks,
            'updated_at' => current_time( 'mysql' ),
        );
        update_option( self::ACCEPTANCE_OPTION, $state, false );
        return $state;
    }

    public function reset_acceptance_state() {
        delete_option( self::ACCEPTANCE_OPTION );
    }

    public function acceptance_summary() {
        $catalog = $this->acceptance_catalog();
        $state = $this->acceptance_state();
        $valid_version = (string) $state['version'] === (string) ITKT_VERSION;
        $done = 0; $total = 0;
        foreach ( $catalog as $section ) {
            foreach ( (array) ( $section['items'] ?? array() ) as $id => $label ) {
                $total++;
                if ( $valid_version && ! empty( $state['checks'][ $id ] ) ) { $done++; }
            }
        }
        return array(
            'done'          => $done,
            'total'         => $total,
            'percent'       => $total ? (int) round( ( $done / $total ) * 100 ) : 0,
            'complete'      => $total > 0 && $done === $total,
            'valid_version' => $valid_version,
            'updated_at'    => $state['updated_at'],
        );
    }

    public function acceptance_report() {
        $catalog = $this->acceptance_catalog();
        $state = $this->acceptance_state();
        $summary = $this->acceptance_summary();
        $checks = array();
        foreach ( $catalog as $section_key => $section ) {
            $items = array();
            foreach ( (array) ( $section['items'] ?? array() ) as $id => $label ) {
                $items[] = array(
                    'id'     => $id,
                    'label'  => $label,
                    'passed' => $summary['valid_version'] && ! empty( $state['checks'][ $id ] ),
                );
            }
            $checks[] = array(
                'section' => $section_key,
                'label'   => (string) ( $section['label'] ?? $section_key ),
                'items'   => $items,
            );
        }

        return array(
            'plugin'        => 'IT-Kayali Translate',
            'version'       => ITKT_VERSION,
            'generated_at'  => current_time( 'mysql' ),
            'site'          => home_url( '/' ),
            'default_lang'  => ITKT_Languages::instance()->get_default_code(),
            'active_langs'  => array_keys( ITKT_Languages::instance()->get_active() ),
            'summary'       => $summary,
            'manual_checks' => $checks,
            'system_checks' => $this->self_test(),
        );
    }

    public function repair_search_indexes() {
        if ( ! class_exists( 'ITKT_Product_Translations' ) || ! post_type_exists( 'product' ) ) { return array( 'checked'=>0, 'repaired'=>0 ); }
        $ids = get_posts( array( 'post_type'=>'product', 'post_status'=>array('publish','draft','private','pending'), 'numberposts'=>-1, 'fields'=>'ids', 'suppress_filters'=>true ) );
        $langs = ITKT_Languages::instance()->get_active();
        unset( $langs[ ITKT_Languages::instance()->get_default_code() ] );
        $checked = 0; $repaired = 0;
        foreach ( $ids as $id ) {
            foreach ( $langs as $code => $lang ) {
                $checked++;
                $before = (string) get_post_meta( $id, ITKT_Product_Translations::instance()->search_meta_key( $code ), true );
                ITKT_Product_Translations::instance()->update_search_index( $id, $code, ITKT_Product_Translations::instance()->get( $id, $code ) );
                $after = (string) get_post_meta( $id, ITKT_Product_Translations::instance()->search_meta_key( $code ), true );
                if ( $before !== $after ) { $repaired++; }
            }
        }
        update_option( 'itkt_product_search_index_version', ITKT_Product_Translations::SEARCH_INDEX_VERSION, false );
        $this->log( 'success', 'search_index_repair', 'Produkt-Suchindex wurde geprüft.', array( 'checked'=>$checked, 'repaired'=>$repaired ) );
        return array( 'checked'=>$checked, 'repaired'=>$repaired );
    }

    public function repair_slug_indexes() {
        if ( ! class_exists( 'ITKT_Product_Translations' ) || ! post_type_exists( 'product' ) ) { return array( 'checked'=>0, 'repaired'=>0 ); }
        $ids = get_posts( array( 'post_type'=>'product', 'post_status'=>array('publish','draft','private','pending'), 'numberposts'=>-1, 'fields'=>'ids', 'suppress_filters'=>true ) );
        $langs = ITKT_Languages::instance()->get_active();
        unset( $langs[ ITKT_Languages::instance()->get_default_code() ] );
        $checked = 0; $repaired = 0;
        foreach ( $ids as $id ) {
            foreach ( $langs as $code => $lang ) {
                $slug = ITKT_Product_Translations::instance()->explicit_slug( $id, $code );
                if ( ! $slug ) { continue; }
                $checked++;
                $hash = ITKT_Product_Translations::public_slug_hash( $slug );
                $stored = (string) get_post_meta( $id, '_itkt_slug_hash_' . $code, true );
                if ( $hash && ! hash_equals( $hash, $stored ) ) {
                    update_post_meta( $id, '_itkt_slug_' . $code, $slug );
                    update_post_meta( $id, '_itkt_slug_hash_' . $code, $hash );
                    $repaired++;
                }
            }
        }
        $this->log( 'success', 'slug_index_repair', 'Produkt-Slug-Indizes wurden geprüft.', array( 'checked'=>$checked, 'repaired'=>$repaired ) );
        return array( 'checked'=>$checked, 'repaired'=>$repaired );
    }
}
