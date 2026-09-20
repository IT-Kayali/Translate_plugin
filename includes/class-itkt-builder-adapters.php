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
                            'floating' => 'Floating Button',
                        ) ),
                    ),
                    'floating_fixed' => array(
                        'id'          => 'floating_fixed',
                        'title'       => esc_html__( 'Floating am Bildschirm fixieren', 'it-kayali-translate' ),
                        'description' => esc_html__( 'Optional: Der Sprachumschalter bleibt beim Scrollen sichtbar. Empfohlen zusammen mit der Darstellung Floating Button.', 'it-kayali-translate' ),
                        'type'        => 'switcher',
                        'tab'         => esc_html__( 'Allgemein', 'it-kayali-translate' ),
                        'value'       => false,
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
                    'desktop' => array( 'tab' => 'Desktop', 'gap' => '12', 'offset' => '24' ),
                    'tablet'  => array( 'tab' => 'Tablet',  'gap' => '10', 'offset' => '18' ),
                    'mobile'  => array( 'tab' => 'Mobile',  'gap' => '8',  'offset' => '14' ),
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
                        'title'       => esc_html__( 'Floating-Abstand zum Bildschirmrand (px)', 'it-kayali-translate' ),
                        'description' => esc_html__( 'Wird nur verwendet, wenn „Floating am Bildschirm fixieren“ aktiv ist.', 'it-kayali-translate' ),
                        'type'        => 'text',
                        'tab'         => $tab,
                        'value'       => $settings['offset'],
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

                $style = isset( $params['style'] ) ? sanitize_key( (string) $params['style'] ) : 'flags';
                if ( ! in_array( $style, array( 'flags', 'pills', 'text', 'floating' ), true ) ) { $style = 'flags'; }

                $html = ITKT_Frontend::instance()->shortcode_switcher(
                    array(
                        'labels' => ! empty( $params['show_codes'] ) ? '1' : '0',
                        'names'  => ! empty( $params['show_names'] ) ? '1' : '0',
                        'style'  => $style,
                    )
                );
                if ( '' === trim( (string) $html ) ) { return; }

                $classes = array( 'wd-header-itkt-languages', 'itkt-responsive-switcher' );
                $css_vars = array();
                $align_map = array( 'left' => 'flex-start', 'center' => 'center', 'right' => 'flex-end' );

                foreach ( array( 'desktop', 'tablet', 'mobile' ) as $device ) {
                    $align = isset( $params[ 'align_' . $device ] ) ? sanitize_key( (string) $params[ 'align_' . $device ] ) : 'left';
                    if ( ! isset( $align_map[ $align ] ) ) { $align = 'left'; }
                    $classes[] = 'itkt-' . $device . '-align-' . $align;
                    $css_vars[] = '--itkt-align-' . $device . ':' . $align_map[ $align ];
                    $css_vars[] = '--itkt-gap-' . $device . ':' . $this->css_number( $this->responsive_number( $params, 'gap_' . $device, 12, 0, 100 ) ) . 'px';

                    foreach ( array( 'top', 'right', 'bottom', 'left' ) as $side ) {
                        $css_vars[] = '--itkt-margin-' . $side . '-' . $device . ':' . $this->css_number( $this->responsive_number( $params, 'margin_' . $side . '_' . $device, 0, -300, 500 ) ) . 'px';
                    }

                    $default_offset = 'desktop' === $device ? 24 : ( 'tablet' === $device ? 18 : 14 );
                    $css_vars[] = '--itkt-floating-offset-' . $device . ':' . $this->css_number( $this->responsive_number( $params, 'floating_offset_' . $device, $default_offset, 0, 300 ) ) . 'px';
                }

                if ( ! empty( $params['floating_fixed'] ) ) {
                    $classes[] = 'itkt-floating-fixed';
                    $vertical = isset( $params['floating_vertical'] ) ? sanitize_key( (string) $params['floating_vertical'] ) : 'bottom';
                    $classes[] = 'itkt-floating-vertical-' . ( 'top' === $vertical ? 'top' : 'bottom' );
                }

                if ( ! empty( $parsed['id'] ) ) {
                    $classes[] = 'whb-' . sanitize_html_class( (string) $parsed['id'] );
                }
                if ( ! empty( $params['css_class'] ) ) {
                    foreach ( preg_split( '/\\s+/', trim( (string) $params['css_class'] ) ) as $class_name ) {
                        $class_name = sanitize_html_class( $class_name );
                        if ( $class_name ) { $classes[] = $class_name; }
                    }
                }

                echo '<div class="' . esc_attr( implode( ' ', array_unique( $classes ) ) ) . '" style="' . esc_attr( implode( ';', $css_vars ) ) . '">' . $html . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- shortcode_switcher escapes all generated attributes/content.
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
