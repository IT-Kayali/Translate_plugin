<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Built-in builder adapters. The translation core stays builder-agnostic;
 * adapters only declare supported content types and safe text controls.
 */
class ITKT_Elementor_Adapter implements ITKT_Adapter_Interface {
    public function id() { return 'elementor'; }
    public function label() { return 'Elementor'; }
    public function is_available() { return ITKT_Plugin::module_enabled( 'elementor' ) && ( did_action( 'elementor/loaded' ) || defined( 'ELEMENTOR_VERSION' ) || class_exists( '\\Elementor\\Plugin' ) ); }

    public function boot() {
        add_filter( 'itkt_supported_post_types', array( $this, 'post_types' ) );
        add_filter( 'itkt_elementor_widget_profiles', array( $this, 'profiles' ) );
        add_filter( 'itkt_elementor_manual_widgets', array( $this, 'manual_widgets' ) );
        add_filter( 'itkt_elementor_translatable_keys', array( $this, 'extra_keys' ), 10, 2 );
    }

    public function post_types( $types ) {
        if ( post_type_exists( 'elementor_library' ) ) { $types[] = 'elementor_library'; }
        return array_values( array_unique( $types ) );
    }

    public function manual_widgets( $widgets ) {
        return array_values( array_unique( array_merge( (array) $widgets, array( 'html', 'shortcode', 'code' ) ) ) );
    }

    public function extra_keys( $keys, $widget ) {
        $extra = array(
            'title_text','description_text','button_text','btn_text','field_label','field_placeholder',
            'success_message','error_message','required_message','invalid_message','before_text',
            'highlighted_text','after_text','testimonial_name','testimonial_job','inner_text',
            'blockquote_content','tweet_button_label','item_text','item_label','menu_title','read_more_text',
        );
        return array_values( array_unique( array_merge( (array) $keys, $extra ) ) );
    }

    public function profiles( $profiles ) {
        $profiles = (array) $profiles;
        $map = array(
            'heading'          => array( 'label'=>'Überschrift', 'keys'=>array('title') ),
            'text-editor'      => array( 'label'=>'Texteditor', 'keys'=>array('editor') ),
            'button'           => array( 'label'=>'Button', 'keys'=>array('text') ),
            'icon-box'         => array( 'label'=>'Icon Box', 'keys'=>array('title_text','description_text') ),
            'image-box'        => array( 'label'=>'Bild Box', 'keys'=>array('title_text','description_text') ),
            'testimonial'      => array( 'label'=>'Testimonial', 'keys'=>array('testimonial_content','testimonial_name','testimonial_job') ),
            'tabs'             => array( 'label'=>'Tabs', 'keys'=>array('tab_title','tab_content') ),
            'accordion'        => array( 'label'=>'Akkordeon', 'keys'=>array('tab_title','tab_content') ),
            'toggle'           => array( 'label'=>'Toggle', 'keys'=>array('tab_title','tab_content') ),
            'counter'          => array( 'label'=>'Zähler', 'keys'=>array('title','prefix','suffix') ),
            'progress'         => array( 'label'=>'Fortschritt', 'keys'=>array('title','inner_text') ),
            'alert'            => array( 'label'=>'Hinweis', 'keys'=>array('title','description') ),
            'call-to-action'   => array( 'label'=>'Call to Action', 'keys'=>array('title','description','button') ),
            'animated-headline'=> array( 'label'=>'Animierte Überschrift', 'keys'=>array('before_text','highlighted_text','after_text') ),
            'blockquote'       => array( 'label'=>'Zitat', 'keys'=>array('blockquote_content','tweet_button_label') ),
            'form'             => array( 'label'=>'Formular', 'keys'=>array('field_label','placeholder','field_placeholder','button_text','success_message','error_message','required_message','invalid_message') ),
            'slides'           => array( 'label'=>'Slides', 'keys'=>array('heading','description','button_text') ),
            'nested-tabs'      => array( 'label'=>'Nested Tabs', 'keys'=>array('tab_title','title') ),
            'nested-accordion' => array( 'label'=>'Nested Accordion', 'keys'=>array('title') ),
            'menu-anchor'      => array( 'label'=>'Menü-Anker', 'keys'=>array() ),
        );
        return array_replace_recursive( $profiles, $map );
    }
}

class ITKT_WoodMart_Adapter implements ITKT_Adapter_Interface {
    public function id() { return 'woodmart'; }
    public function label() { return 'WoodMart / XTemos'; }
    public function is_available() {
        $env = ITKT_Environment::detect();
        return ITKT_Plugin::module_enabled( 'woodmart' ) && ! empty( $env['woodmart']['active'] );
    }

    public function boot() {
        add_filter( 'itkt_supported_post_types', array( $this, 'post_types' ) );
        add_filter( 'itkt_elementor_widget_profiles', array( $this, 'profiles' ) );
        add_filter( 'itkt_elementor_translatable_keys', array( $this, 'extra_keys' ), 10, 2 );
    }

    /**
     * WoodMart creates "Edit current header" from the current frontend request. On a translated
     * storefront URL that link must always point to the language-neutral source page; admin/editor
     * routes must never receive /en/, /ar/, ... prefixes.
     */
    public static function normalize_header_editor_admin_bar( $admin_bar ) {
        if ( is_admin() || ! is_object( $admin_bar ) || ! method_exists( $admin_bar, 'get_node' ) ) { return; }
        if ( ! current_user_can( 'edit_theme_options' ) || ! function_exists( 'whb_get_header' ) ) { return; }

        $node = $admin_bar->get_node( 'xts_header_builder_edit' );
        $header = whb_get_header();
        if ( ! $node || ! is_object( $header ) || ! method_exists( $header, 'get_id' ) ) { return; }

        $default = ITKT_Languages::instance()->get_default_code();
        $base = ITKT_Frontend::instance()->language_url( $default );

        // A stale/broken request such as /en/wp-admin/admin-ajax.php must not be recycled into
        // another Header Builder link. Fall back to the canonical storefront home in that case.
        $path = strtolower( (string) wp_parse_url( $base, PHP_URL_PATH ) );
        if ( false !== strpos( $path, '/wp-admin/' ) || false !== strpos( $path, 'admin-ajax.php' ) || false !== strpos( $path, '/wp-login.php' ) ) {
            $base = trailingslashit( untrailingslashit( (string) get_option( 'home' ) ) );
        }

        $base = remove_query_arg( array( 'whb-header-frontend', 'itkt_lang', 'lang' ), $base );
        $href = add_query_arg( 'whb-header-frontend', sanitize_key( (string) $header->get_id() ), $base );

        $args = get_object_vars( $node );
        $args['id'] = 'xts_header_builder_edit';
        $args['href'] = $href;
        $admin_bar->add_node( $args );
    }

    /**
     * A translated page inherits the source page's WoodMart header assignment until an explicit
     * different header is saved on that translation. This keeps one header/configuration shared
     * by DE/EN/AR by default while still allowing an intentional per-language override.
     */
    public static function inherit_source_header_assignment( $value, $object_id, $meta_key, $single, $meta_type ) {
        if ( 'post' !== $meta_type || '_woodmart_whb_header' !== $meta_key ) { return $value; }

        static $guard = false;
        if ( $guard ) { return $value; }

        $post_id = absint( $object_id );
        if ( ! $post_id ) { return $value; }

        $guard = true;
        $lang = sanitize_key( (string) get_post_meta( $post_id, '_itkt_language', true ) );
        $default = ITKT_Languages::instance()->get_default_code();

        if ( ! $lang || $lang === $default || get_post_meta( $post_id, '_itkt_woodmart_header_override', true ) ) {
            $guard = false;
            return $value;
        }

        $group = (string) get_post_meta( $post_id, '_itkt_group', true );
        if ( ! $group ) {
            $guard = false;
            return $value;
        }

        $siblings = get_posts( array(
            'post_type'        => get_post_type( $post_id ),
            'post_status'      => array( 'publish', 'draft', 'private', 'pending', 'future' ),
            'numberposts'      => -1,
            'fields'           => 'ids',
            'meta_key'         => '_itkt_group',
            'meta_value'       => $group,
            'suppress_filters' => true,
        ) );

        $source_id = 0;
        foreach ( (array) $siblings as $sibling_id ) {
            if ( sanitize_key( (string) get_post_meta( $sibling_id, '_itkt_language', true ) ) === $default ) {
                $source_id = absint( $sibling_id );
                break;
            }
        }

        if ( ! $source_id ) {
            $guard = false;
            return $value;
        }

        $source_value = get_post_meta( $source_id, '_woodmart_whb_header', $single );
        $guard = false;

        // Return even an empty source value so an old copied translation meta value cannot keep
        // selecting a stale header after the source page was returned to the global default.
        return $single ? $source_value : (array) $source_value;
    }

    /**
     * Mark a translated page only when the administrator actually saves a different WoodMart
     * header assignment there. Translation creation copies meta before _itkt_language is set, so
     * normal duplicated pages remain in inherited/global mode.
     */
    public static function track_explicit_header_override( $meta_id, $object_id, $meta_key, $meta_value ) {
        if ( '_woodmart_whb_header' !== $meta_key ) { return; }

        $post_id = absint( $object_id );
        $lang = sanitize_key( (string) get_post_meta( $post_id, '_itkt_language', true ) );
        $default = ITKT_Languages::instance()->get_default_code();
        if ( ! $lang || $lang === $default ) { return; }

        // "none"/empty means no special translated-page header: return to inherited source/global.
        if ( '' === trim( (string) $meta_value ) || 'none' === sanitize_key( (string) $meta_value ) ) {
            delete_post_meta( $post_id, '_itkt_woodmart_header_override' );
            return;
        }

        update_post_meta( $post_id, '_itkt_woodmart_header_override', 1 );
    }

    public static function clear_header_override_on_delete( $meta_ids, $object_id, $meta_key, $meta_value ) {
        if ( '_woodmart_whb_header' !== $meta_key ) { return; }
        delete_post_meta( absint( $object_id ), '_itkt_woodmart_header_override' );
    }

    /**
     * Register a native IT-Kayali language selector in WoodMart's Header Builder.
     *
     * WoodMart loads its built-in element classes on init priority 8 and snapshots the
     * available frontend element map on priority 10. ITKT registers at priority 9 so the
     * element is available to both the builder AJAX list and frontend rendering without
     * modifying theme files.
     */
    public static function register_header_builder_element() {
        // This integration must follow the actually active WoodMart Header Builder, not the
        // module selection saved during ITKT's first-run wizard. A site may enable/switch to
        // WoodMart later, and the language element still needs to become available immediately.
        if ( ! class_exists( '\\XTS\\Modules\\Header_Builder' ) || ! class_exists( '\\XTS\\Modules\\Header_Builder\\Element' ) ) { return; }

        $builder = \XTS\Modules\Header_Builder::get_instance();
        if ( ! is_object( $builder ) || ! isset( $builder->elements ) || ! is_object( $builder->elements ) || ! isset( $builder->elements->elements_classes ) || ! is_array( $builder->elements->elements_classes ) ) { return; }

        $key = 'Itktlanguages';
        if ( isset( $builder->elements->elements_classes[ $key ] ) ) { return; }

        $builder->elements->elements_classes[ $key ] = new class extends \XTS\Modules\Header_Builder\Element {
            private function selector_options( $values ) {
                $options = array();
                foreach ( $values as $value => $label ) {
                    $options[ $value ] = array(
                        'value' => $value,
                        'label' => esc_html__( $label, 'it-kayali-translate' ),
                    );
                }
                return $options;
            }

            private function responsive_number( $params, $key, $default, $min = -300, $max = 500 ) {
                if ( ! isset( $params[ $key ] ) || '' === trim( (string) $params[ $key ] ) ) { return (float) $default; }
                $value = (float) str_replace( ',', '.', (string) $params[ $key ] );
                return max( (float) $min, min( (float) $max, $value ) );
            }

            private function css_number( $value ) {
                $value = (float) $value;
                if ( abs( $value - round( $value ) ) < 0.001 ) { return (string) (int) round( $value ); }
                return rtrim( rtrim( number_format( $value, 2, '.', '' ), '0' ), '.' );
            }

            public function map() {
                $params = array(
                    'use_global_settings' => array(
                        'id'          => 'use_global_settings',
                        'title'       => esc_html__( 'Globale Abstände/Größen verwenden', 'it-kayali-translate' ),
                        'description' => esc_html__( 'Übernimmt nur responsive Größen und Abstände aus IT-Kayali Translate → Sprachumschalter. Darstellung, Dropdown/Floating-Verhalten und Beschriftung bleiben pro Header-Element getrennt einstellbar.', 'it-kayali-translate' ),
                        'type'        => 'switcher',
                        'tab'         => esc_html__( 'Allgemein', 'it-kayali-translate' ),
                        'value'       => true,
                    ),
                    'show_codes' => array(
                        'id'          => 'show_codes',
                        'title'       => esc_html__( 'Sprachkürzel anzeigen', 'it-kayali-translate' ),
                        'description' => esc_html__( 'Zeigt zusätzlich DE, EN, AR usw. neben der Flagge.', 'it-kayali-translate' ),
                        'type'        => 'switcher',
                        'tab'         => esc_html__( 'Allgemein', 'it-kayali-translate' ),
                        'value'       => false,
                    ),
                    'show_names' => array(
                        'id'          => 'show_names',
                        'title'       => esc_html__( 'Sprachnamen anzeigen', 'it-kayali-translate' ),
                        'description' => esc_html__( 'Zeigt zusätzlich Deutsch, English, العربية usw.', 'it-kayali-translate' ),
                        'type'        => 'switcher',
                        'tab'         => esc_html__( 'Allgemein', 'it-kayali-translate' ),
                        'value'       => false,
                    ),
                    'style' => array(
                        'id'      => 'style',
                        'title'   => esc_html__( 'Darstellung', 'it-kayali-translate' ),
                        'type'    => 'selector',
                        'tab'     => esc_html__( 'Allgemein', 'it-kayali-translate' ),
                        'value'   => 'flags',
                        'options' => $this->selector_options( array(
                            'flags'    => 'Flaggen',
                            'pills'    => 'Pills',
                            'text'     => 'Text',
                            'dropdown' => 'Dropdown',
                            'floating' => 'Floating Button',
                        ) ),
                    ),
                    'floating_fixed' => array(
                        'id'          => 'floating_fixed',
                        'title'       => esc_html__( 'Floating am Bildschirm fixieren', 'it-kayali-translate' ),
                        'description' => esc_html__( 'Der Sprachumschalter bleibt beim Scrollen sichtbar.', 'it-kayali-translate' ),
                        'type'        => 'switcher',
                        'tab'         => esc_html__( 'Allgemein', 'it-kayali-translate' ),
                        'value'       => false,
                        'requires'    => array(
                            'style' => array(
                                'comparison' => 'equal',
                                'value'      => 'floating',
                            ),
                        ),
                    ),
                    'floating_vertical' => array(
                        'id'      => 'floating_vertical',
                        'title'   => esc_html__( 'Floating-Position vertikal', 'it-kayali-translate' ),
                        'type'    => 'selector',
                        'tab'     => esc_html__( 'Allgemein', 'it-kayali-translate' ),
                        'value'   => 'bottom',
                        'options' => $this->selector_options( array(
                            'top'    => 'Oben',
                            'bottom' => 'Unten',
                        ) ),
                        'requires' => array(
                            'style' => array(
                                'comparison' => 'equal',
                                'value'      => 'floating',
                            ),
                            'floating_fixed' => array(
                                'comparison' => 'equal',
                                'value'      => true,
                            ),
                        ),
                    ),
                    'mobile_dropdown' => array(
                        'id'          => 'mobile_dropdown',
                        'title'       => esc_html__( 'Auf Mobile als Dropdown', 'it-kayali-translate' ),
                        'description' => esc_html__( 'Nur für Floating Button: Auf Smartphones wird nur die aktive Sprache angezeigt; die anderen Sprachen öffnen sich per Klick.', 'it-kayali-translate' ),
                        'type'        => 'switcher',
                        'tab'         => esc_html__( 'Allgemein', 'it-kayali-translate' ),
                        'value'       => true,
                        'requires'    => array(
                            'style' => array(
                                'comparison' => 'equal',
                                'value'      => 'floating',
                            ),
                        ),
                    ),
                    'dropdown_direction' => array(
                        'id'      => 'dropdown_direction',
                        'title'   => esc_html__( 'Dropdown-Öffnungsrichtung', 'it-kayali-translate' ),
                        'type'    => 'selector',
                        'tab'     => esc_html__( 'Allgemein', 'it-kayali-translate' ),
                        'value'   => 'auto',
                        'options' => $this->selector_options( array(
                            'auto' => 'Automatisch',
                            'up'   => 'Nach oben',
                            'down' => 'Nach unten',
                        ) ),
                        'requires' => array(
                            'style' => array(
                                'comparison' => 'equal',
                                'value'      => 'floating',
                            ),
                        ),
                    ),
                    'css_class' => array(
                        'id'          => 'css_class',
                        'title'       => esc_html__( 'Zusätzliche CSS-Klasse', 'it-kayali-translate' ),
                        'description' => esc_html__( 'Optional für eigenes Styling des Sprachumschalters.', 'it-kayali-translate' ),
                        'type'        => 'text',
                        'tab'         => esc_html__( 'Allgemein', 'it-kayali-translate' ),
                        'value'       => '',
                    ),
                );

                $devices = array(
                    'desktop' => array( 'tab' => 'Desktop', 'gap' => '12', 'pad_x' => '10', 'pad_y' => '6', 'offset' => '24' ),
                    'tablet'  => array( 'tab' => 'Tablet',  'gap' => '10', 'pad_x' => '9',  'pad_y' => '5', 'offset' => '18' ),
                    'mobile'  => array( 'tab' => 'Mobile',  'gap' => '8',  'pad_x' => '8',  'pad_y' => '5', 'offset' => '14' ),
                );

                foreach ( $devices as $device => $settings ) {
                    $tab = esc_html__( $settings['tab'], 'it-kayali-translate' );
                    $params[ 'align_' . $device ] = array(
                        'id'          => 'align_' . $device,
                        'title'       => esc_html__( 'Ausrichtung', 'it-kayali-translate' ),
                        'description' => esc_html__( 'Position innerhalb des verfügbaren Header-Bereichs.', 'it-kayali-translate' ),
                        'type'        => 'selector',
                        'tab'         => $tab,
                        'value'       => 'left',
                        'options'     => $this->selector_options( array(
                            'left'   => 'Links',
                            'center' => 'Mitte',
                            'right'  => 'Rechts',
                        ) ),
                    );
                    $params[ 'gap_' . $device ] = array(
                        'id'          => 'gap_' . $device,
                        'title'       => esc_html__( 'Abstand zwischen Sprachen (px)', 'it-kayali-translate' ),
                        'description' => esc_html__( 'Freier Pixelwert, z. B. 8, 12 oder 20.', 'it-kayali-translate' ),
                        'type'        => 'text',
                        'tab'         => $tab,
                        'value'       => $settings['gap'],
                    );
                    $params[ 'padding_x_' . $device ] = array(
                        'id'          => 'padding_x_' . $device,
                        'title'       => esc_html__( 'Innenabstand horizontal (px)', 'it-kayali-translate' ),
                        'description' => esc_html__( 'Innenabstand links/rechts für Pills und Floating Button.', 'it-kayali-translate' ),
                        'type'        => 'text',
                        'tab'         => $tab,
                        'value'       => $settings['pad_x'],
                    );
                    $params[ 'padding_y_' . $device ] = array(
                        'id'          => 'padding_y_' . $device,
                        'title'       => esc_html__( 'Innenabstand vertikal (px)', 'it-kayali-translate' ),
                        'description' => esc_html__( 'Innenabstand oben/unten für Pills und Floating Button.', 'it-kayali-translate' ),
                        'type'        => 'text',
                        'tab'         => $tab,
                        'value'       => $settings['pad_y'],
                    );

                    foreach ( array( 'top' => 'Oben', 'right' => 'Rechts', 'bottom' => 'Unten', 'left' => 'Links' ) as $side => $label ) {
                        $params[ 'margin_' . $side . '_' . $device ] = array(
                            'id'          => 'margin_' . $side . '_' . $device,
                            'title'       => sprintf( esc_html__( 'Außenabstand %s (px)', 'it-kayali-translate' ), esc_html__( $label, 'it-kayali-translate' ) ),
                            'description' => esc_html__( 'Auch negative Werte sind möglich, z. B. -8.', 'it-kayali-translate' ),
                            'type'        => 'text',
                            'tab'         => $tab,
                            'value'       => '0',
                        );
                    }

                    $params[ 'floating_offset_' . $device ] = array(
                        'id'          => 'floating_offset_' . $device,
                        'title'       => esc_html__( 'Floating-Abstand (Legacy, px)', 'it-kayali-translate' ),
                        'description' => esc_html__( 'Kompatibilitätswert aus v0.12.28. Neue Installationen verwenden X/Y getrennt.', 'it-kayali-translate' ),
                        'type'        => 'text',
                        'tab'         => $tab,
                        'value'       => $settings['offset'],
                    );
                    $params[ 'floating_offset_x_' . $device ] = array(
                        'id'          => 'floating_offset_x_' . $device,
                        'title'       => esc_html__( 'Floating-Abstand horizontal (px)', 'it-kayali-translate' ),
                        'description' => esc_html__( 'Abstand zur linken/rechten Bildschirmkante.', 'it-kayali-translate' ),
                        'type'        => 'text',
                        'tab'         => $tab,
                        'value'       => $settings['offset'],
                    );
                    $params[ 'floating_offset_y_' . $device ] = array(
                        'id'          => 'floating_offset_y_' . $device,
                        'title'       => esc_html__( 'Floating-Abstand vertikal (px)', 'it-kayali-translate' ),
                        'description' => esc_html__( 'Abstand zur oberen/unteren Bildschirmkante.', 'it-kayali-translate' ),
                        'type'        => 'text',
                        'tab'         => $tab,
                        'value'       => $settings['offset'],
                    );
                    $params[ 'menu_gap_' . $device ] = array(
                        'id'          => 'menu_gap_' . $device,
                        'title'       => esc_html__( 'Abstand Trigger → Dropdown (px)', 'it-kayali-translate' ),
                        'description' => esc_html__( 'Abstand zwischen aktiver Sprache und aufgeklapptem Menü.', 'it-kayali-translate' ),
                        'type'        => 'text',
                        'tab'         => $tab,
                        'value'       => 'desktop' === $device ? '10' : ( 'tablet' === $device ? '9' : '8' ),
                    );
                    $params[ 'flag_size_' . $device ] = array(
                        'id'          => 'flag_size_' . $device,
                        'title'       => esc_html__( 'Flaggengröße (px)', 'it-kayali-translate' ),
                        'description' => esc_html__( 'Größe der sichtbaren Flagge im Trigger und in der Liste.', 'it-kayali-translate' ),
                        'type'        => 'text',
                        'tab'         => $tab,
                        'value'       => 'desktop' === $device ? '28' : ( 'tablet' === $device ? '26' : '25' ),
                    );
                }

                $this->args = array(
                    'type'            => 'itktlanguages',
                    'title'           => esc_html__( 'IT-Kayali Sprachen', 'it-kayali-translate' ),
                    'text'            => esc_html__( 'Sprachumschalter mit Flaggen', 'it-kayali-translate' ),
                    'icon'            => 'xts-i-translate',
                    'editable'        => true,
                    'container'       => false,
                    'edit_on_create'  => true,
                    'drag_target_for' => array(),
                    'drag_source'     => 'content_element',
                    'removable'       => true,
                    'addable'         => true,
                    'params'          => $params,
                );
            }

            public function render( $el, $children = '' ) {
                if ( ! class_exists( 'ITKT_Frontend' ) ) { return; }

                $parsed = $this->parse_args( $el );
                $params = isset( $parsed['params'] ) && is_array( $parsed['params'] ) ? $parsed['params'] : array();

                $use_global = array_key_exists( 'use_global_settings', $params ) && ! empty( $params['use_global_settings'] );
                $global = ITKT_Frontend::instance()->switcher_settings();

                $style = isset( $params['style'] ) ? sanitize_key( (string) $params['style'] ) : 'flags';
                if ( ! in_array( $style, array( 'flags', 'pills', 'text', 'dropdown', 'floating' ), true ) ) { $style = 'flags'; }

                // Element-specific behavior is always respected. Global inheritance only supplies
                // responsive spacing/sizing so one header dropdown and a second fixed floating
                // switcher can coexist on the same page without fighting over one global style.
                $is_floating = 'floating' === $style;

                $local = array(
                    'style'              => $style,
                    'show_codes'         => ! empty( $params['show_codes'] ),
                    'show_names'         => ! empty( $params['show_names'] ),
                    // Floating-only controls must never alter normal flags/pills/text/dropdown.
                    'mobile_dropdown'    => $is_floating && ( ! array_key_exists( 'mobile_dropdown', $params ) || ! empty( $params['mobile_dropdown'] ) ),
                    'floating_fixed'     => $is_floating && ! empty( $params['floating_fixed'] ),
                    'floating_vertical'  => $is_floating && isset( $params['floating_vertical'] ) ? sanitize_key( (string) $params['floating_vertical'] ) : 'bottom',
                    'dropdown_direction' => $is_floating && isset( $params['dropdown_direction'] ) ? sanitize_key( (string) $params['dropdown_direction'] ) : 'auto',
                    'devices'            => array(),
                );

                foreach ( array( 'desktop', 'tablet', 'mobile' ) as $device ) {
                    if ( $use_global && isset( $global['devices'][ $device ] ) ) {
                        $local['devices'][ $device ] = $global['devices'][ $device ];
                        continue;
                    }

                    $legacy_offset = isset( $params[ 'floating_offset_' . $device ] ) ? $params[ 'floating_offset_' . $device ] : ( 'desktop' === $device ? 24 : ( 'tablet' === $device ? 18 : 14 ) );
                    $local['devices'][ $device ] = array(
                        'align'         => isset( $params[ 'align_' . $device ] ) ? sanitize_key( (string) $params[ 'align_' . $device ] ) : 'left',
                        'gap'           => $params[ 'gap_' . $device ] ?? ( 'desktop' === $device ? 12 : ( 'tablet' === $device ? 10 : 8 ) ),
                        'padding_x'     => $params[ 'padding_x_' . $device ] ?? ( 'desktop' === $device ? 10 : ( 'tablet' === $device ? 9 : 8 ) ),
                        'padding_y'     => $params[ 'padding_y_' . $device ] ?? ( 'desktop' === $device ? 6 : 5 ),
                        'offset_x'      => $params[ 'floating_offset_x_' . $device ] ?? $legacy_offset,
                        'offset_y'      => $params[ 'floating_offset_y_' . $device ] ?? $legacy_offset,
                        'menu_gap'      => $params[ 'menu_gap_' . $device ] ?? ( 'desktop' === $device ? 10 : ( 'tablet' === $device ? 9 : 8 ) ),
                        'flag_size'     => $params[ 'flag_size_' . $device ] ?? ( 'desktop' === $device ? 28 : ( 'tablet' === $device ? 26 : 25 ) ),
                        'margin_top'    => $params[ 'margin_top_' . $device ] ?? 0,
                        'margin_right'  => $params[ 'margin_right_' . $device ] ?? 0,
                        'margin_bottom' => $params[ 'margin_bottom_' . $device ] ?? 0,
                        'margin_left'   => $params[ 'margin_left_' . $device ] ?? 0,
                    );
                }

                $atts = array( 'config_source'=>'local', 'config'=>$local );

                $html = ITKT_Frontend::instance()->shortcode_switcher( $atts );
                if ( '' === trim( (string) $html ) ) { return; }

                $classes = array( 'wd-header-itkt-languages' );
                if ( ! empty( $parsed['id'] ) ) { $classes[] = 'whb-' . sanitize_html_class( (string) $parsed['id'] ); }
                if ( ! empty( $params['css_class'] ) ) {
                    foreach ( preg_split( '/\\s+/', trim( (string) $params['css_class'] ) ) as $class_name ) {
                        $class_name = sanitize_html_class( $class_name );
                        if ( $class_name ) { $classes[] = $class_name; }
                    }
                }

                echo '<div class="' . esc_attr( implode( ' ', array_unique( $classes ) ) ) . '">' . $html . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- shortcode_switcher escapes generated attributes/content.

            }
        };
    }

    /**
     * Late frontend safety net for WoodMart versions/installations with a different init order.
     * Re-register the element and refresh WoodMart's public frontend element snapshot before
     * the header is rendered.
     */
    public static function ensure_header_builder_frontend_element() {
        self::register_header_builder_element();

        if ( class_exists( '\\XTS\\Modules\\Header_Builder\\Frontend' ) ) {
            $frontend = \XTS\Modules\Header_Builder\Frontend::get_instance();
            if ( is_object( $frontend ) && method_exists( $frontend, 'get_elements' ) ) {
                $frontend->get_elements();
            }
        }
    }

    public function post_types( $types ) {
        foreach ( self::builder_post_types() as $post_type => $label ) { $types[] = $post_type; }
        return array_values( array_unique( $types ) );
    }

    public static function builder_post_types() {
        $out = array();
        $known = array(
            'cms_block'          => 'WoodMart HTML-Blöcke',
            'woodmart_layout'    => 'WoodMart Layouts',
            'woodmart_slide'     => 'WoodMart Slides',
            'woodmart_popup'     => 'WoodMart Pop-ups',
            'woodmart_size_guide'=> 'WoodMart Größentabellen',
            'woodmart_sidebar'   => 'WoodMart Seitenleisten',
            'woodmart_woo_layout'=> 'WoodMart WooCommerce Layouts',
            'wd_woo_layout'      => 'WoodMart WooCommerce Layouts',
        );
        foreach ( $known as $name => $label ) { if ( post_type_exists( $name ) ) { $out[ $name ] = $label; } }

        // Future-proof discovery for WoodMart/XTemos content types without hard-coding every version.
        foreach ( get_post_types( array( 'show_ui' => true ), 'objects' ) as $name => $object ) {
            if ( isset( $out[ $name ] ) || in_array( $name, array('post','page','product','attachment','elementor_library'), true ) ) { continue; }
            $haystack = strtolower( $name . ' ' . ( $object->label ?? '' ) . ' ' . ( $object->labels->name ?? '' ) . ' ' . ( $object->labels->singular_name ?? '' ) );
            if ( preg_match( '/woodmart|xtemos|html.?block|floating.?block|mega.?menu|woo.?layout/', $haystack ) ) {
                $out[ $name ] = $object->labels->name ?: $object->label ?: $name;
            }
        }
        return apply_filters( 'itkt_woodmart_builder_post_types', $out );
    }

    public function extra_keys( $keys, $widget ) {
        if ( 0 === strpos( (string) $widget, 'wd_' ) || false !== strpos( (string) $widget, 'woodmart' ) || false !== strpos( (string) $widget, 'xts' ) ) {
            $keys = array_merge( (array) $keys, array(
                'title','subtitle','text','content','description','button_text','btn_text','label','caption',
                'after_title','before_title','extra_title','read_more_text','link_text','item_title','item_text',
            ) );
        }
        return array_values( array_unique( $keys ) );
    }

    public function profiles( $profiles ) {
        $profiles = (array) $profiles;
        $map = array(
            'wd_title'              => array( 'label'=>'WoodMart Titel', 'keys'=>array('title','subtitle','after_title','before_title') ),
            'woodmart_title'        => array( 'label'=>'WoodMart Titel', 'keys'=>array('title','subtitle','after_title','before_title') ),
            'wd_text_block'         => array( 'label'=>'WoodMart Textblock', 'keys'=>array('text','content','title') ),
            'woodmart_text_block'   => array( 'label'=>'WoodMart Textblock', 'keys'=>array('text','content','title') ),
            'wd_button'             => array( 'label'=>'WoodMart Button', 'keys'=>array('title','text','button_text','btn_text') ),
            'woodmart_button'       => array( 'label'=>'WoodMart Button', 'keys'=>array('title','text','button_text','btn_text') ),
            'wd_infobox'            => array( 'label'=>'WoodMart Info Box', 'keys'=>array('title','subtitle','text','content','description','button_text','btn_text') ),
            'woodmart_infobox'      => array( 'label'=>'WoodMart Info Box', 'keys'=>array('title','subtitle','text','content','description','button_text','btn_text') ),
            'wd_banner'             => array( 'label'=>'WoodMart Banner', 'keys'=>array('title','subtitle','text','content','description','button_text','btn_text') ),
            'woodmart_banner'       => array( 'label'=>'WoodMart Banner', 'keys'=>array('title','subtitle','text','content','description','button_text','btn_text') ),
            'wd_list'               => array( 'label'=>'WoodMart Liste', 'keys'=>array('title','text','item_title','item_text') ),
            'wd_testimonials'       => array( 'label'=>'WoodMart Testimonials', 'keys'=>array('title','text','content','name','job','testimonial_content') ),
            'wd_products'           => array( 'label'=>'WoodMart Produkte', 'keys'=>array('title','subtitle') ),
            'wd_product_categories' => array( 'label'=>'WoodMart Kategorien', 'keys'=>array('title','subtitle') ),
            'wd_mega_menu'          => array( 'label'=>'WoodMart Mega-Menü', 'keys'=>array('title','menu_title') ),
        );
        return array_replace_recursive( $profiles, $map );
    }
}
