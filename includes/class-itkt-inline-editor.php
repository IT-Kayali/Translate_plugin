<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Admin-only frontend translation overlay.
 *
 * The overlay deliberately edits translation data only. It never writes CSS, layout settings,
 * URLs, Elementor containers or arbitrary HTML widgets.
 */
class ITKT_Inline_Editor {
    private static $instance = null;

    public static function instance() {
        if ( null === self::$instance ) { self::$instance = new self(); }
        return self::$instance;
    }

    private function __construct() {
        add_action( 'admin_bar_menu', array( $this, 'admin_bar' ), 100 );
        add_action( 'wp_enqueue_scripts', array( $this, 'assets' ), 120 );
        add_action( 'wp_ajax_itkt_inline_load', array( $this, 'ajax_load' ) );
        add_action( 'wp_ajax_itkt_inline_save', array( $this, 'ajax_save' ) );
    }

    private function enabled() {
        if ( is_admin() || ! is_user_logged_in() || ! current_user_can( 'manage_options' ) ) { return false; }
        $settings = get_option( 'itkt_settings', array() );
        return ! array_key_exists( 'inline_editor_enabled', $settings ) || ! empty( $settings['inline_editor_enabled'] );
    }

    public function admin_bar( $bar ) {
        if ( ! $this->enabled() || ! is_object( $bar ) ) { return; }
        $bar->add_node( array(
            'id'    => 'itkt-inline-translate',
            'title' => '<span class="ab-icon dashicons-translation" style="font:normal 20px/1 dashicons;margin-top:6px"></span><span class="ab-label">Übersetzen</span>',
            'href'  => '#itkt-inline-toggle',
            'meta'  => array( 'class' => 'itkt-inline-adminbar' ),
        ) );
    }

    public function assets() {
        if ( ! $this->enabled() ) { return; }

        $context = $this->context();
        wp_enqueue_style( 'itkt-inline-editor', ITKT_URL . 'public/assets/inline-editor.css', array(), ITKT_VERSION );
        wp_enqueue_script( 'itkt-inline-editor', ITKT_URL . 'public/assets/inline-editor.js', array(), ITKT_VERSION, true );
        wp_localize_script( 'itkt-inline-editor', 'ITKTInline', array(
            'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
            'nonce'        => wp_create_nonce( 'itkt_inline_editor' ),
            'context'      => $context,
            'languages'    => $this->languages_for_js(),
            'defaultLang'  => ITKT_Languages::instance()->get_default_code(),
            'currentLang'  => ITKT_Languages::instance()->current_code(),
            'strings'      => array(
                'toggle'       => 'Übersetzen',
                'active'       => 'Übersetzen aktiv',
                'loading'      => 'Übersetzungen werden geladen …',
                'save'         => 'Speichern',
                'saving'       => 'Wird gespeichert …',
                'saved'        => 'Gespeichert',
                'reload'       => 'Seite neu laden',
                'close'        => 'Schließen',
                'noFields'     => 'Auf dieser Ansicht wurden noch keine direkt bearbeitbaren Texte erkannt.',
                'manual'       => 'Dieses Element muss weiterhin manuell im Builder geprüft werden.',
                'error'        => 'Die Änderung konnte nicht gespeichert werden.',
            ),
        ) );
    }

    private function languages_for_js() {
        $out = array();
        foreach ( ITKT_Languages::instance()->get_active() as $code => $lang ) {
            $out[] = array(
                'code'      => $code,
                'name'      => (string) ( $lang['native_name'] ?? strtoupper( $code ) ),
                'flag'      => (string) ( $lang['flag'] ?? '' ),
                'direction' => ITKT_Languages::instance()->effective_direction( $code ),
                'default'   => ( $code === ITKT_Languages::instance()->get_default_code() ),
            );
        }
        return $out;
    }

    /** Describe only safe, recognized text targets for the current frontend request. */
    private function context() {
        if ( function_exists( 'is_product' ) && is_product() ) {
            $product_id = absint( get_queried_object_id() );
            if ( $product_id ) {
                return array(
                    'type'      => 'product',
                    'sourceId'  => $product_id,
                    'label'     => get_the_title( $product_id ),
                    'targets'   => array(
                        array( 'key'=>'title', 'label'=>'Produktname', 'selector'=>'.product_title, h1.product_title', 'kind'=>'plain' ),
                        array( 'key'=>'short_description', 'label'=>'Kurzbeschreibung', 'selector'=>'.woocommerce-product-details__short-description', 'kind'=>'rich' ),
                        array( 'key'=>'description', 'label'=>'Lange Beschreibung', 'selector'=>'#tab-description, .woocommerce-Tabs-panel--description', 'kind'=>'rich' ),
                    ),
                );
            }
        }

        if ( is_singular() ) {
            $current_id = absint( get_queried_object_id() );
            $source_id  = $this->default_source_post_id( $current_id );
            if ( $source_id && in_array( get_post_type( $source_id ), ITKT_Content::instance()->supported_post_types(), true ) ) {
                $targets = array();
                if ( ITKT_Content::instance()->is_elementor( $source_id ) ) {
                    $grouped = array();
                    foreach ( ITKT_Elementor::instance()->segments( $source_id ) as $segment ) {
                        if ( ! empty( $segment['manual'] ) ) { continue; }
                        $element = (string) ( $segment['element_id'] ?? '' );
                        if ( ! $element ) { continue; }
                        if ( ! isset( $grouped[ $element ] ) ) {
                            $grouped[ $element ] = array(
                                'key'       => 'element:' . $element,
                                'elementId' => $element,
                                'label'     => (string) ( $segment['widget_label'] ?? 'Elementor' ),
                                'selector'  => '.elementor-element-' . preg_replace( '/[^A-Za-z0-9_-]/', '', $element ),
                                'kind'      => 'elementor',
                                'segments'  => array(),
                            );
                        }
                        $grouped[ $element ]['segments'][] = array(
                            'key'    => (string) $segment['key'],
                            'label'  => (string) $segment['label'],
                            'source' => (string) $segment['source'],
                            'rich'   => ! empty( $segment['rich'] ),
                        );
                    }
                    $targets = array_values( $grouped );
                } else {
                    $targets = array(
                        array( 'key'=>'post_title', 'label'=>'Seitentitel', 'selector'=>'h1.entry-title, .page-title', 'kind'=>'plain' ),
                    );
                    $post = get_post( $source_id );
                    // WooCommerce runtime pages (Cart / Checkout / My Account) render most of their
                    // visible UI dynamically from shortcodes/blocks. Marking the whole .entry-content
                    // would swallow the individual WooCommerce labels in the inline editor.
                    if ( $post && ! $this->is_woocommerce_runtime_page( $source_id ) && '' !== trim( (string) $post->post_content ) ) {
                        $targets[] = array( 'key'=>'post_content', 'label'=>'Seiteninhalt', 'selector'=>'.entry-content', 'kind'=>'rich' );
                    }
                }
                return array(
                    'type'      => 'post',
                    'sourceId'  => $source_id,
                    'currentId' => $current_id,
                    'label'     => get_the_title( $source_id ),
                    'targets'   => $targets,
                );
            }
        }

        return array( 'type'=>'none', 'sourceId'=>0, 'targets'=>array() );
    }

    private function is_woocommerce_runtime_page( $post_id = 0 ) {
        if ( function_exists( 'is_cart' ) && is_cart() ) { return true; }
        if ( function_exists( 'is_checkout' ) && is_checkout() ) { return true; }
        if ( function_exists( 'is_account_page' ) && is_account_page() ) { return true; }

        $post = $post_id ? get_post( $post_id ) : null;
        if ( ! $post ) { return false; }
        $content = (string) $post->post_content;

        if ( has_shortcode( $content, 'woocommerce_cart' ) || has_shortcode( $content, 'woocommerce_checkout' ) || has_shortcode( $content, 'woocommerce_my_account' ) ) {
            return true;
        }

        foreach ( array( 'wp:woocommerce/cart', 'wp:woocommerce/checkout', 'wp:woocommerce/my-account' ) as $marker ) {
            if ( false !== strpos( $content, $marker ) ) { return true; }
        }
        return false;
    }

    private function default_source_post_id( $post_id ) {
        $post_id = absint( $post_id );
        if ( ! $post_id ) { return 0; }
        $default = ITKT_Languages::instance()->get_default_code();
        $lang = sanitize_key( (string) get_post_meta( $post_id, '_itkt_language', true ) );
        if ( ! $lang || $lang === $default ) { return $post_id; }
        $translations = ITKT_Content::instance()->translations_for( $post_id );
        return ! empty( $translations[ $default ] ) ? absint( $translations[ $default ]->ID ) : $post_id;
    }

    private function authorize_ajax() {
        check_ajax_referer( 'itkt_inline_editor', 'nonce' );
        if ( ! is_user_logged_in() || ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message'=>'Unauthorized' ), 403 );
        }
        $settings = get_option( 'itkt_settings', array() );
        if ( array_key_exists( 'inline_editor_enabled', $settings ) && empty( $settings['inline_editor_enabled'] ) ) {
            wp_send_json_error( array( 'message'=>'Inline editor disabled' ), 403 );
        }
    }

    public function ajax_load() {
        $this->authorize_ajax();
        $type      = sanitize_key( $_POST['type'] ?? '' );
        $source_id = absint( $_POST['source_id'] ?? 0 );
        $key       = sanitize_text_field( wp_unslash( $_POST['key'] ?? '' ) );

        if ( 'visual' === $type ) {
            $original = sanitize_textarea_field( wp_unslash( $_POST['original'] ?? '' ) );
            $actual_original = sanitize_textarea_field( wp_unslash( $_POST['actual_original'] ?? $original ) );
            $scope    = sanitize_key( $_POST['scope'] ?? 'global' );
            $locator  = sanitize_text_field( wp_unslash( $_POST['locator'] ?? '' ) );
            $mode     = sanitize_key( $_POST['mode'] ?? 'local' );
            if ( ! in_array( $mode, array( 'local','global','pattern' ), true ) ) { $mode = 'local'; }
            if ( ! $key || '' === trim( $original ) ) { wp_send_json_error( array( 'message'=>'Invalid visual target' ), 400 ); }
            $existing_visual = 'local' === $mode ? ITKT_Strings::instance()->visual_string( $key ) : ITKT_Strings::instance()->global_string( $original, 'pattern' === $mode ? 'pattern' : 'exact' );
            $native_match = ( 'local' !== $mode && class_exists('ITKT_Native_Translations') ) ? ITKT_Native_Translations::instance()->resolve_visible( $original, $scope, $mode, $actual_original, $locator ) : null;
            if ( ! $existing_visual && ! $native_match && ITKT_Languages::instance()->current_code() !== ITKT_Languages::instance()->get_default_code() ) {
                wp_send_json_error( array( 'message'=>'Diesen globalen Text bitte beim ersten Mal in der Standardsprache öffnen. Danach kann er aus jeder Sprache weiterbearbeitet werden.' ), 400 );
            }
            wp_send_json_success( $this->load_visual_field( $key, $original, $scope, $locator, $mode, $native_match ) );
        }

        if ( 'visual_group' === $type ) {
            $scope   = sanitize_key( $_POST['scope'] ?? 'global' );
            $locator = sanitize_text_field( wp_unslash( $_POST['locator'] ?? '' ) );
            $items   = json_decode( wp_unslash( $_POST['items'] ?? '[]' ), true );
            if ( ! $key || ! is_array( $items ) || ! $items ) { wp_send_json_error( array( 'message'=>'Invalid visual group' ), 400 ); }
            $result = $this->load_visual_group( $items, $scope, $locator );
            if ( is_wp_error( $result ) ) { wp_send_json_error( array( 'message'=>$result->get_error_message() ), 400 ); }
            wp_send_json_success( $result );
        }

        if ( ! $source_id || ! $key ) { wp_send_json_error( array( 'message'=>'Invalid request' ), 400 ); }

        if ( 'product' === $type ) {
            wp_send_json_success( $this->load_product_field( $source_id, $key ) );
        }
        if ( 'post' === $type ) {
            wp_send_json_success( $this->load_post_field( $source_id, $key ) );
        }
        wp_send_json_error( array( 'message'=>'Unsupported content' ), 400 );
    }

    private function load_visual_field( $fingerprint, $original, $scope, $locator, $mode = 'local', $native_match = null ) {
        $mode = in_array( $mode, array( 'local','global','pattern' ), true ) ? $mode : 'local';
        $global_mode = 'pattern' === $mode ? 'pattern' : 'exact';

        // WooCommerce/WoodMart system labels should use their canonical gettext source whenever
        // possible. Native language packs become the default; ITKT stores only editable overrides.
        if ( 'local' !== $mode && is_array( $native_match ) && ! empty( $native_match['source'] ) ) {
            $native = ITKT_Native_Translations::instance();
            $string_id = ITKT_Strings::instance()->ensure_canonical_string(
                (string) $native_match['source'], (string) ( $native_match['domain'] ?? 'woocommerce' ),
                (string) ( $native_match['context'] ?? '' ), (string) ( $native_match['plural'] ?? '' )
            );
            if ( ! $string_id ) { return array( 'fields'=>array() ); }
            $default = ITKT_Languages::instance()->get_default_code();
            $values = array();
            $defaults = array();
            foreach ( ITKT_Languages::instance()->get_active() as $lang => $row ) {
                $override = ITKT_Strings::instance()->get_translation( $string_id, $lang );
                $base = $native->translate( (string)$native_match['source'], (string)$native_match['domain'], $lang, (string)($native_match['context'] ?? '') );
                if ( $lang === $default ) {
                    if ( null === $base || '' === $base ) { $base = $original; }
                    $values[ $lang ] = $native->editor_value( $base );
                    $defaults[ $lang ] = '';
                } else {
                    // Only the user's override belongs in the editable field. Empty means:
                    // use the native WooCommerce/WoodMart language-pack translation.
                    $values[ $lang ] = '' !== $override ? $native->editor_value( $override ) : '';
                    $defaults[ $lang ] = ( null === $base || '' === $base ) ? '' : $native->editor_value( $base );
                }
            }
            $legacy = ITKT_Strings::instance()->global_string( $original, $global_mode );
            $native_domain = sanitize_key( (string) ( $native_match['domain'] ?? '' ) );
            $native_name = 'woodmart' === $native_domain ? 'WoodMart' : ( 'woocommerce' === $native_domain ? 'WooCommerce' : 'System' );
            $label = $native_name . ' Systemtext · Standard + Anpassung';
            $fields = array( array(
                'key' => 'visual_text',
                'label' => 'Systemtext',
                'source' => $native->editor_value( (string) $native_match['source'] ),
                'rich' => false,
                'values' => $values,
                'defaults' => $defaults,
                'defaultLabel' => $native_name . '-Standard',
            ) );

            // WooCommerce Blocks builds "Including %s" from a dynamic tax-line value such as
            // "8,64 € MwSt.". The tax-rate name ("MwSt.") is merchant configuration, not a
            // gettext string, so expose it as a separate editable system-data field in the same
            // panel instead of pretending it is part of {{1}}.
            if ( 'woocommerce' === (string) ( $native_match['domain'] ?? '' ) && 'Including %s' === (string) ( $native_match['source'] ?? '' ) && class_exists( 'ITKT_WooCommerce' ) ) {
                foreach ( ITKT_WooCommerce::instance()->current_cart_tax_rate_rows() as $tax_row ) {
                    $tax_string_id = absint( $tax_row['string_id'] ?? 0 );
                    $tax_source = (string) ( $tax_row['source'] ?? '' );
                    if ( ! $tax_string_id || '' === trim( $tax_source ) ) { continue; }
                    $tax_values = array();
                    $tax_defaults = array();
                    foreach ( ITKT_Languages::instance()->get_active() as $lang => $row ) {
                        if ( $lang === $default ) {
                            $tax_values[ $lang ] = $tax_source;
                            $tax_defaults[ $lang ] = '';
                        } else {
                            $tax_values[ $lang ] = ITKT_Strings::instance()->get_translation( $tax_string_id, $lang );
                            $tax_defaults[ $lang ] = ITKT_WooCommerce::instance()->default_tax_label_translation( $tax_source, $lang );
                        }
                    }
                    $fields[] = array(
                        'key' => 'tax_label_' . $tax_string_id,
                        'label' => 'Steuerbezeichnung: ' . $tax_source,
                        'source' => $tax_source,
                        'rich' => false,
                        'values' => $tax_values,
                        'defaults' => $tax_defaults,
                        'defaultLabel' => 'Standard',
                    );
                }
            }

            return array(
                'label' => $label,
                'contextLabel' => $label,
                'nativeStringId' => $string_id,
                'legacyVisualId' => absint( $legacy['id'] ?? 0 ),
                'nativeDomain' => (string) $native_match['domain'],
                'nativeSource' => (string) $native_match['source'],
                'fields' => $fields,
            );
        }

        $string_id = 'local' === $mode
            ? ITKT_Strings::instance()->ensure_visual_string( $fingerprint, $original, $scope, $locator )
            : ITKT_Strings::instance()->ensure_global_string( $original, $scope, $global_mode );
        if ( ! $string_id ) { return array( 'fields'=>array() ); }
        $stored = 'local' === $mode ? ITKT_Strings::instance()->visual_string( $fingerprint ) : ITKT_Strings::instance()->global_string( $original, $global_mode );
        if ( $stored && isset( $stored['original'] ) && '' !== trim( (string) $stored['original'] ) ) { $original = (string) $stored['original']; }
        $default = ITKT_Languages::instance()->get_default_code();
        $values = array();
        foreach ( ITKT_Languages::instance()->get_active() as $lang => $row ) {
            $values[ $lang ] = ( $lang === $default ) ? $original : ITKT_Strings::instance()->get_translation( $string_id, $lang );
        }
        $scope_labels = array(
            'header'  => 'Header / Navigation',
            'footer'  => 'Footer / Widgets',
            'menu'    => 'Menü',
            'cart'        => 'Warenkorb / Mini-Cart',
            'checkout'    => 'Checkout',
            'account'     => 'Mein Konto',
            'woocommerce' => 'WooCommerce',
            'filter'      => 'Filter / Plugin-Oberfläche',
            'widget'      => 'Widget',
            'global'      => 'Globaler Frontend-Text',
        );
        $label = $scope_labels[ $scope ] ?? 'Globaler Frontend-Text';
        if ( 'global' === $mode ) { $label .= ' · global'; }
        if ( 'pattern' === $mode ) { $label .= ' · global mit Variablen'; }
        return array(
            'label' => $label,
            'contextLabel' => $label,
            'visualId' => $string_id,
            'fields' => array( array(
                'key' => 'visual_text',
                'label' => 'Sichtbarer Text',
                'source' => $original,
                'rich' => false,
                'values' => $values,
            ) ),
        );
    }

    private function load_visual_group( $items, $scope, $locator ) {
        $default = ITKT_Languages::instance()->get_default_code();
        $current = ITKT_Languages::instance()->current_code();
        $fields  = array();
        $items   = array_slice( array_values( (array) $items ), 0, 30 );

        foreach ( $items as $item ) {
            if ( ! is_array( $item ) ) { continue; }
            $fingerprint = preg_replace( '/[^A-Fa-f0-9]/', '', (string) ( $item['key'] ?? '' ) );
            $original    = sanitize_text_field( (string) ( $item['original'] ?? '' ) );
            $item_scope  = sanitize_key( (string) ( $item['scope'] ?? $scope ) ) ?: $scope;
            $item_locator= sanitize_text_field( (string) ( $item['locator'] ?? $locator ) );
            $item_mode   = sanitize_key( (string) ( $item['mode'] ?? 'local' ) );
            if ( ! in_array( $item_mode, array( 'local','global','pattern' ), true ) ) { $item_mode = 'local'; }
            if ( strlen( $fingerprint ) < 8 || '' === trim( $original ) ) { continue; }

            $existing = 'local' === $item_mode
                ? ITKT_Strings::instance()->visual_string( $fingerprint )
                : ITKT_Strings::instance()->global_string( $original, 'pattern' === $item_mode ? 'pattern' : 'exact' );
            if ( ! $existing && $current !== $default ) {
                return new WP_Error( 'visual_source_missing', 'Diese Auswahltexte bitte beim ersten Mal in der Standardsprache öffnen. Danach können sie aus jeder Sprache weiterbearbeitet werden.' );
            }

            $string_id = 'local' === $item_mode
                ? ITKT_Strings::instance()->ensure_visual_string( $fingerprint, $original, $item_scope, $item_locator )
                : ITKT_Strings::instance()->ensure_global_string( $original, $item_scope, 'pattern' === $item_mode ? 'pattern' : 'exact' );
            if ( ! $string_id ) { continue; }
            $stored = 'local' === $item_mode ? ITKT_Strings::instance()->visual_string( $fingerprint ) : ITKT_Strings::instance()->global_string( $original, 'pattern' === $item_mode ? 'pattern' : 'exact' );
            if ( $stored && ! empty( $stored['original'] ) ) { $original = (string) $stored['original']; }

            $values = array();
            foreach ( ITKT_Languages::instance()->get_active() as $lang => $row ) {
                $values[ $lang ] = ( $lang === $default ) ? $original : ITKT_Strings::instance()->get_translation( $string_id, $lang );
            }

            $fields[] = array(
                'key'    => $fingerprint,
                'label'  => sanitize_text_field( (string) ( $item['label'] ?? ( 'Option: ' . $original ) ) ),
                'source' => $original,
                'rich'   => false,
                'values' => $values,
            );
        }

        $is_fragment_group = false;
        foreach ( $items as $item ) {
            if ( is_array( $item ) && 'fragment' === sanitize_key( (string) ( $item['group_kind'] ?? '' ) ) ) { $is_fragment_group = true; break; }
        }
        $scope_labels = array( 'footer'=>'Footer / gemeinsamer Bereich', 'header'=>'Header / gemeinsamer Bereich', 'checkout'=>'Checkout / gemeinsamer Bereich', 'menu'=>'Menü / gemeinsamer Bereich' );
        $group_label = $is_fragment_group ? ( $scope_labels[ $scope ] ?? 'Gemeinsamer Frontend-Textblock' ) : 'Auswahltexte';
        return array(
            'label'        => $group_label,
            'contextLabel' => $is_fragment_group ? $group_label : 'Auswahl / Dropdown',
            'fields'       => $fields,
        );
    }

    private function load_product_field( $product_id, $key ) {
        if ( 'product' !== get_post_type( $product_id ) ) { return array( 'fields'=>array() ); }
        $post = get_post( $product_id );
        $labels = array( 'title'=>'Produktname', 'short_description'=>'Kurzbeschreibung', 'description'=>'Lange Beschreibung' );
        if ( ! isset( $labels[ $key ] ) ) { return array( 'fields'=>array() ); }
        $source = 'title' === $key ? $post->post_title : ( 'short_description' === $key ? $post->post_excerpt : $post->post_content );
        $values = array();
        $default = ITKT_Languages::instance()->get_default_code();
        foreach ( ITKT_Languages::instance()->get_active() as $lang => $row ) {
            if ( $lang === $default ) { $values[ $lang ] = $source; continue; }
            $data = ITKT_Product_Translations::instance()->get( $product_id, $lang );
            $values[ $lang ] = (string) ( $data[ $key ] ?? '' );
        }
        return array( 'label'=>$labels[ $key ], 'fields'=>array( array( 'key'=>$key, 'label'=>$labels[ $key ], 'source'=>$source, 'rich'=>('title'!==$key), 'values'=>$values ) ) );
    }

    private function load_post_field( $source_id, $key ) {
        $source = get_post( $source_id );
        if ( ! $source ) { return array( 'fields'=>array() ); }
        $translations = ITKT_Content::instance()->translations_for( $source_id );
        $default = ITKT_Languages::instance()->get_default_code();

        if ( 0 === strpos( $key, 'element:' ) ) {
            $element_id = substr( $key, strlen( 'element:' ) );
            $source_segments = array_values( array_filter( ITKT_Elementor::instance()->segments( $source_id ), function( $segment ) use ( $element_id ) {
                return empty( $segment['manual'] ) && (string) ( $segment['element_id'] ?? '' ) === (string) $element_id;
            } ) );
            $translation_maps = array();
            foreach ( ITKT_Languages::instance()->get_active() as $lang => $row ) {
                if ( $lang === $default ) {
                    foreach ( $source_segments as $segment ) { $translation_maps[ $lang ][ $segment['key'] ] = (string) $segment['source']; }
                    continue;
                }
                $target = $translations[ $lang ] ?? null;
                if ( ! $target ) { continue; }
                foreach ( ITKT_Elementor::instance()->segments( $target->ID ) as $segment ) {
                    if ( empty( $segment['manual'] ) ) { $translation_maps[ $lang ][ $segment['key'] ] = (string) $segment['source']; }
                }
            }
            $fields = array();
            foreach ( $source_segments as $segment ) {
                $values = array();
                foreach ( ITKT_Languages::instance()->get_active() as $lang => $row ) {
                    $values[ $lang ] = (string) ( $translation_maps[ $lang ][ $segment['key'] ] ?? '' );
                }
                $fields[] = array(
                    'key'    => (string) $segment['key'],
                    'label'  => (string) $segment['label'],
                    'source' => (string) $segment['source'],
                    'rich'   => ! empty( $segment['rich'] ),
                    'values' => $values,
                );
            }
            return array( 'label'=>'Elementor / WoodMart', 'elementId'=>$element_id, 'fields'=>$fields );
        }

        $allowed = array( 'post_title'=>'Seitentitel', 'post_content'=>'Seiteninhalt', 'post_excerpt'=>'Kurztext' );
        if ( ! isset( $allowed[ $key ] ) ) { return array( 'fields'=>array() ); }
        $values = array();
        foreach ( ITKT_Languages::instance()->get_active() as $lang => $row ) {
            if ( $lang === $default ) { $values[ $lang ] = (string) $source->{$key}; continue; }
            $target = $translations[ $lang ] ?? null;
            $values[ $lang ] = $target ? (string) $target->{$key} : '';
        }
        return array( 'label'=>$allowed[ $key ], 'fields'=>array( array( 'key'=>$key, 'label'=>$allowed[ $key ], 'source'=>(string)$source->{$key}, 'rich'=>('post_title'!==$key), 'values'=>$values ) ) );
    }

    public function ajax_save() {
        $this->authorize_ajax();
        $type      = sanitize_key( $_POST['type'] ?? '' );
        $source_id = absint( $_POST['source_id'] ?? 0 );
        $key       = sanitize_text_field( wp_unslash( $_POST['key'] ?? '' ) );
        $raw       = isset( $_POST['values'] ) ? wp_unslash( $_POST['values'] ) : '{}';
        $values    = json_decode( $raw, true );

        if ( 'visual' === $type ) {
            $original = sanitize_textarea_field( wp_unslash( $_POST['original'] ?? '' ) );
            $scope    = sanitize_key( $_POST['scope'] ?? 'global' );
            $locator  = sanitize_text_field( wp_unslash( $_POST['locator'] ?? '' ) );
            $mode     = sanitize_key( $_POST['mode'] ?? 'local' );
            $native_string_id = absint( $_POST['native_string_id'] ?? 0 );
            $legacy_visual_id = absint( $_POST['legacy_visual_id'] ?? 0 );
            if ( ! in_array( $mode, array( 'local','global','pattern' ), true ) ) { $mode = 'local'; }
            if ( ! $key || '' === trim( $original ) || ! is_array( $values ) ) { wp_send_json_error( array( 'message'=>'Invalid visual request' ), 400 ); }
            $result = $native_string_id ? $this->save_native_string_field( $native_string_id, $values, $legacy_visual_id ) : $this->save_visual_field( $key, $original, $scope, $locator, $values, $mode );
        } elseif ( 'visual_group' === $type ) {
            $scope   = sanitize_key( $_POST['scope'] ?? 'global' );
            $locator = sanitize_text_field( wp_unslash( $_POST['locator'] ?? '' ) );
            $items   = json_decode( wp_unslash( $_POST['items'] ?? '[]' ), true );
            if ( ! $key || ! is_array( $items ) || ! is_array( $values ) ) { wp_send_json_error( array( 'message'=>'Invalid visual group request' ), 400 ); }
            $result = $this->save_visual_group( $items, $scope, $locator, $values );
        } else {
            if ( ! $source_id || ! $key || ! is_array( $values ) ) { wp_send_json_error( array( 'message'=>'Invalid request' ), 400 ); }
            $result = 'product' === $type
                ? $this->save_product_field( $source_id, $key, $values )
                : ( 'post' === $type ? $this->save_post_field( $source_id, $key, $values ) : new WP_Error( 'unsupported', 'Unsupported content.' ) );
        }
        if ( is_wp_error( $result ) ) { wp_send_json_error( array( 'message'=>$result->get_error_message() ), 400 ); }
        wp_send_json_success( array( 'message'=>'Gespeichert', 'reload'=>true ) );
    }

    private function save_visual_group( $items, $scope, $locator, $values ) {
        $default = ITKT_Languages::instance()->get_default_code();
        $active  = ITKT_Languages::instance()->get_active();
        $items   = array_slice( array_values( (array) $items ), 0, 30 );
        $by_key  = array();

        foreach ( $items as $item ) {
            if ( ! is_array( $item ) ) { continue; }
            $fingerprint = preg_replace( '/[^A-Fa-f0-9]/', '', (string) ( $item['key'] ?? '' ) );
            $original    = sanitize_text_field( (string) ( $item['original'] ?? '' ) );
            if ( strlen( $fingerprint ) < 8 || '' === trim( $original ) ) { continue; }
            $item_scope   = sanitize_key( (string) ( $item['scope'] ?? $scope ) ) ?: $scope;
            $item_locator = sanitize_text_field( (string) ( $item['locator'] ?? $locator ) );
            $item_mode    = sanitize_key( (string) ( $item['mode'] ?? 'local' ) );
            if ( ! in_array( $item_mode, array( 'local','global','pattern' ), true ) ) { $item_mode = 'local'; }
            $string_id = 'local' === $item_mode
                ? ITKT_Strings::instance()->ensure_visual_string( $fingerprint, $original, $item_scope, $item_locator )
                : ITKT_Strings::instance()->ensure_global_string( $original, $item_scope, 'pattern' === $item_mode ? 'pattern' : 'exact' );
            if ( $string_id ) { $by_key[ strtolower( $fingerprint ) ] = array( 'id'=>$string_id, 'mode'=>$item_mode, 'source'=>$original ); }
        }

        foreach ( (array) $values as $lang => $field_values ) {
            $lang = sanitize_key( $lang );
            if ( $lang === $default || empty( $active[ $lang ] ) || ! is_array( $field_values ) ) { continue; }
            foreach ( $field_values as $field_key => $translation ) {
                $field_key = strtolower( preg_replace( '/[^A-Fa-f0-9]/', '', (string) $field_key ) );
                if ( empty( $by_key[ $field_key ]['id'] ) ) { continue; }
                $translation = (string) $translation;
                if ( '' === trim( $translation ) ) {
                    ITKT_Strings::instance()->delete_translation( absint( $by_key[ $field_key ]['id'] ), $lang );
                    continue;
                }
                if ( 'pattern' === ( $by_key[ $field_key ]['mode'] ?? '' ) ) {
                    $translation = $this->normalize_pattern_tokens( $translation );
                    $valid = $this->validate_pattern_translation( (string) ( $by_key[ $field_key ]['source'] ?? '' ), $translation );
                    if ( is_wp_error( $valid ) ) { return $valid; }
                }
                $translation = sanitize_text_field( $translation );
                ITKT_Strings::instance()->save_translation( absint( $by_key[ $field_key ]['id'] ), $lang, $translation );
            }
        }
        return true;
    }

    private function save_native_string_field( $string_id, $values, $legacy_visual_id = 0 ) {
        $row = ITKT_Strings::instance()->canonical_string( $string_id );
        if ( ! $row || in_array( (string)($row['domain'] ?? ''), array('itkt-global','itkt-visual'), true ) ) {
            return new WP_Error( 'invalid_native_string', 'Der WooCommerce-Systemtext konnte nicht eindeutig zugeordnet werden.' );
        }
        $default = ITKT_Languages::instance()->get_default_code();
        $active = ITKT_Languages::instance()->get_active();
        $source_editor = class_exists('ITKT_Native_Translations') ? ITKT_Native_Translations::instance()->editor_value( (string)$row['original'] ) : (string)$row['original'];

        foreach ( (array) $values as $lang => $value ) {
            $lang = sanitize_key( $lang );
            if ( $lang === $default || empty( $active[ $lang ] ) ) { continue; }

            // V0.11.4 may submit more than one field for a WooCommerce system string. The main
            // gettext override remains visual_text; tax_label_ID fields are independent configured
            // WooCommerce data labels such as "MwSt.".
            $field_values = is_array( $value ) ? $value : array( 'visual_text' => $value );
            $main_value = array_key_exists( 'visual_text', $field_values ) ? (string) $field_values['visual_text'] : '';
            $main_value = $this->normalize_pattern_tokens( $main_value );

            // Empty is an explicit reset: delete both the canonical override and any legacy
            // DOM/global override that may have been created by older plugin versions.
            if ( '' === trim( $main_value ) ) {
                ITKT_Strings::instance()->delete_translation( $string_id, $lang );
                if ( $legacy_visual_id ) { ITKT_Strings::instance()->delete_translation( $legacy_visual_id, $lang ); }
            } else {
                $valid = $this->validate_pattern_translation( $source_editor, $main_value );
                if ( is_wp_error( $valid ) ) { return $valid; }
                ITKT_Strings::instance()->save_translation( $string_id, $lang, sanitize_textarea_field( $main_value ) );
                // Once a canonical gettext override exists, the old visual-pattern copy must not
                // compete with it in the browser runtime.
                if ( $legacy_visual_id ) { ITKT_Strings::instance()->delete_translation( $legacy_visual_id, $lang ); }
            }

            foreach ( $field_values as $field_key => $field_value ) {
                if ( ! preg_match( '/^tax_label_(\d+)$/', (string) $field_key, $m ) ) { continue; }
                $tax_string_id = absint( $m[1] );
                $tax_row = ITKT_Strings::instance()->canonical_string( $tax_string_id );
                if ( ! $tax_row || 'itkt-woocommerce-data' !== (string) ( $tax_row['domain'] ?? '' ) || 0 !== strpos( (string) ( $tax_row['context'] ?? '' ), 'tax_rate_' ) ) {
                    continue;
                }
                $field_value = sanitize_text_field( (string) $field_value );
                if ( '' === trim( $field_value ) ) {
                    ITKT_Strings::instance()->delete_translation( $tax_string_id, $lang );
                } else {
                    ITKT_Strings::instance()->save_translation( $tax_string_id, $lang, $field_value );
                }
            }
        }
        return true;
    }

    private function save_visual_field( $fingerprint, $original, $scope, $locator, $values, $mode = 'local' ) {
        $mode = in_array( $mode, array( 'local','global','pattern' ), true ) ? $mode : 'local';
        $string_id = 'local' === $mode
            ? ITKT_Strings::instance()->ensure_visual_string( $fingerprint, $original, $scope, $locator )
            : ITKT_Strings::instance()->ensure_global_string( $original, $scope, 'pattern' === $mode ? 'pattern' : 'exact' );
        if ( ! $string_id ) { return new WP_Error( 'visual_string_failed', 'Der sichtbare Text konnte nicht registriert werden.' ); }
        $default = ITKT_Languages::instance()->get_default_code();
        $active  = ITKT_Languages::instance()->get_active();
        foreach ( $values as $lang => $value ) {
            $lang = sanitize_key( $lang );
            if ( $lang === $default || empty( $active[ $lang ] ) ) { continue; }
            $value = (string) $value;
            if ( '' === trim( $value ) ) {
                ITKT_Strings::instance()->delete_translation( $string_id, $lang );
                continue;
            }
            if ( 'pattern' === $mode ) {
                $value = $this->normalize_pattern_tokens( $value );
                $valid = $this->validate_pattern_translation( $original, $value );
                if ( is_wp_error( $valid ) ) { return $valid; }
            }
            $value = sanitize_textarea_field( $value );
            ITKT_Strings::instance()->save_translation( $string_id, $lang, $value );
        }
        return true;
    }

    private function normalize_pattern_tokens( $value ) {
        $value = (string) $value;
        // RTL editors/browsers can inject invisible bidi control characters around {{1}}.
        // They must never make a visibly present protected token fail validation.
        $value = preg_replace( '/[\x{061C}\x{200E}\x{200F}\x{202A}-\x{202E}\x{2066}-\x{2069}]/u', '', $value );
        $value = str_replace( array( '｛', '｝', '﹛', '﹜' ), array( '{', '}', '{', '}' ), $value );
        $value = preg_replace_callback( '/\{\{\s*([0-9]+)\s*\}\}/u', function( $m ) { return '{{' . $m[1] . '}}'; }, $value );
        // Also accept a visually/reordered RTL copy such as }}1{{ and normalize it.
        $value = preg_replace_callback( '/\}\}\s*([0-9]+)\s*\{\{/u', function( $m ) { return '{{' . $m[1] . '}}'; }, $value );
        return $value;
    }

    private function validate_pattern_translation( $source, $translation ) {
        $source      = $this->normalize_pattern_tokens( $source );
        $translation = $this->normalize_pattern_tokens( $translation );
        preg_match_all( '/\{\{\d+\}\}/', (string) $source, $source_tokens );
        $tokens = array_values( array_unique( (array) ( $source_tokens[0] ?? array() ) ) );
        if ( ! $tokens ) { return true; }
        foreach ( $tokens as $token ) {
            if ( false === strpos( (string) $translation, $token ) ) {
                return new WP_Error( 'missing_variable_token', 'Die geschützte Variable ' . $token . ' muss in der Übersetzung erhalten bleiben.' );
            }
        }
        return true;
    }

    private function save_product_field( $product_id, $key, $values ) {
        if ( 'product' !== get_post_type( $product_id ) || ! in_array( $key, array( 'title','short_description','description' ), true ) ) {
            return new WP_Error( 'invalid_product_field', 'Ungültiges Produktfeld.' );
        }
        $default = ITKT_Languages::instance()->get_default_code();
        $active = ITKT_Languages::instance()->get_active();
        foreach ( $values as $lang => $value ) {
            $lang = sanitize_key( $lang );
            if ( $lang === $default || empty( $active[ $lang ] ) ) { continue; }
            $save = ITKT_Product_Translations::instance()->save( $product_id, $lang, array( $key => $value ) );
            if ( is_wp_error( $save ) ) { return $save; }
        }
        return true;
    }

    private function save_post_field( $source_id, $key, $values ) {
        if ( ! get_post( $source_id ) ) { return new WP_Error( 'invalid_post', 'Ungültiger Inhalt.' ); }
        $default = ITKT_Languages::instance()->get_default_code();
        $active = ITKT_Languages::instance()->get_active();

        if ( 0 === strpos( $key, 'element:' ) ) {
            $element_id = substr( $key, strlen( 'element:' ) );
            foreach ( $values as $lang => $field_values ) {
                $lang = sanitize_key( $lang );
                if ( $lang === $default || empty( $active[ $lang ] ) || ! is_array( $field_values ) ) { continue; }
                $target_id = $this->ensure_translation( $source_id, $lang );
                if ( is_wp_error( $target_id ) ) { return $target_id; }
                $allowed = array();
                foreach ( ITKT_Elementor::instance()->segments( $source_id ) as $segment ) {
                    if ( empty( $segment['manual'] ) && (string) ( $segment['element_id'] ?? '' ) === (string) $element_id ) {
                        $allowed[ (string) $segment['key'] ] = true;
                    }
                }
                $submitted = array();
                foreach ( $field_values as $segment_key => $value ) {
                    if ( isset( $allowed[ $segment_key ] ) ) { $submitted[ $segment_key ] = $value; }
                }
                if ( $submitted && ! ITKT_Elementor::instance()->apply( $source_id, $target_id, $submitted ) ) {
                    return new WP_Error( 'elementor_save_failed', 'Elementor-Inhalt konnte nicht sicher gespeichert werden.' );
                }
                ITKT_Content::instance()->mark_synced( $source_id, $target_id );
            }
            return true;
        }

        if ( ! in_array( $key, array( 'post_title','post_content','post_excerpt' ), true ) ) { return new WP_Error( 'invalid_post_field', 'Ungültiges Seitenfeld.' ); }
        foreach ( $values as $lang => $value ) {
            $lang = sanitize_key( $lang );
            if ( $lang === $default || empty( $active[ $lang ] ) ) { continue; }
            $target_id = $this->ensure_translation( $source_id, $lang );
            if ( is_wp_error( $target_id ) ) { return $target_id; }
            $update = array( 'ID'=>$target_id );
            if ( 'post_title' === $key ) { $update[ $key ] = sanitize_text_field( $value ); }
            else { $update[ $key ] = wp_kses_post( $value ); }
            $saved = wp_update_post( wp_slash( $update ), true );
            if ( is_wp_error( $saved ) ) { return $saved; }
            ITKT_Content::instance()->mark_synced( $source_id, $target_id );
        }
        return true;
    }

    private function ensure_translation( $source_id, $lang ) {
        $translations = ITKT_Content::instance()->translations_for( $source_id );
        if ( ! empty( $translations[ $lang ] ) ) { return absint( $translations[ $lang ]->ID ); }
        return ITKT_Content::instance()->create_translation( $source_id, $lang );
    }
}
