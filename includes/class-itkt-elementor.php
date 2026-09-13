<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class ITKT_Elementor {
    private static $instance = null;
    public static function instance() { if ( null === self::$instance ) { self::$instance = new self(); } return self::$instance; }

    public function segments( $post_id ) {
        if ( ! ITKT_Content::instance()->is_elementor( $post_id ) ) { return array(); }
        $raw = get_post_meta( $post_id, '_elementor_data', true );
        if ( ! is_string( $raw ) || '' === trim( $raw ) ) { return array(); }
        $data = json_decode( $raw, true );
        if ( ! is_array( $data ) ) { return array(); }
        $segments = array();
        $this->walk_elements( $data, $segments );
        return apply_filters( 'itkt_elementor_segments', $segments, $post_id );
    }

    public function segment_stats( $post_id ) {
        $stats = array( 'automatic'=>0, 'manual'=>0, 'woodmart'=>0, 'widgets'=>array() );
        foreach ( $this->segments( $post_id ) as $segment ) {
            if ( ! empty( $segment['manual'] ) ) { $stats['manual']++; } else { $stats['automatic']++; }
            $widget = (string) ( $segment['widget'] ?? '' );
            if ( $widget ) { $stats['widgets'][ $widget ] = true; }
            if ( $this->is_woodmart_widget( $widget ) ) { $stats['woodmart']++; }
        }
        $stats['widgets'] = count( $stats['widgets'] );
        return $stats;
    }

    private function walk_elements( $elements, &$segments ) {
        $manual_widgets = apply_filters( 'itkt_elementor_manual_widgets', array( 'html','shortcode','code' ) );
        foreach ( (array) $elements as $element ) {
            if ( ! is_array( $element ) ) { continue; }
            $id = isset( $element['id'] ) ? (string) $element['id'] : '';
            $widget = isset( $element['widgetType'] ) ? sanitize_key( $element['widgetType'] ) : '';

            if ( $widget && in_array( $widget, (array) $manual_widgets, true ) ) {
                $segments[] = array(
                    'key' => 'manual-' . $id,
                    'element_id' => $id,
                    'widget' => $widget,
                    'widget_label' => $this->widget_label( $widget ),
                    'setting_path' => array(),
                    'label' => $this->widget_label( $widget ) . ' · manuell prüfen',
                    'source' => '',
                    'manual' => true,
                    'woodmart' => $this->is_woodmart_widget( $widget ),
                );
            } elseif ( ! empty( $element['settings'] ) && is_array( $element['settings'] ) ) {
                $this->walk_settings( $element['settings'], array(), $id, $widget, $segments );
            }

            if ( ! empty( $element['elements'] ) && is_array( $element['elements'] ) ) {
                $this->walk_elements( $element['elements'], $segments );
            }
        }
    }

    private function walk_settings( $settings, $path, $element_id, $widget, &$segments ) {
        foreach ( $settings as $key => $value ) {
            $key = (string) $key;
            $next = array_merge( $path, array( $key ) );
            if ( is_array( $value ) ) {
                $this->walk_settings( $value, $next, $element_id, $widget, $segments );
                continue;
            }
            if ( ! is_string( $value ) || ! $this->is_translatable_key( $key, $widget ) || ! $this->is_safe_text( $value ) ) { continue; }

            $segment_key = md5( $element_id . '|' . implode( '.', $next ) );
            $segments[] = array(
                'key'          => $segment_key,
                'element_id'   => $element_id,
                'widget'       => $widget ?: 'widget',
                'widget_label' => $this->widget_label( $widget ?: 'widget' ),
                'setting_path' => $next,
                'label'        => $this->widget_label( $widget ?: 'widget' ) . ' · ' . $this->field_label( $key ),
                'source'       => $value,
                'manual'       => false,
                'woodmart'     => $this->is_woodmart_widget( $widget ),
                'rich'         => $this->looks_like_rich_text( $value ),
            );
        }
    }

    private function profiles() {
        return apply_filters( 'itkt_elementor_widget_profiles', array() );
    }

    private function is_translatable_key( $key, $widget = '' ) {
        $key = strtolower( (string) $key );
        $widget = sanitize_key( (string) $widget );

        // Never translate structural, design, query, media or navigation destinations.
        $deny_exact = array(
            'url','href','link','link_url','custom_css','css_classes','css_id','html_tag','anchor','menu_id','template_id',
            'image','image_id','background_image','icon','selected_icon','query_id','post_id','product_id','taxonomy','term_id',
            'columns','rows','width','height','align','alignment','position','order','display','direction','color','background_color',
        );
        if ( in_array( $key, $deny_exact, true ) ) { return false; }
        if ( preg_match( '/(?:^|_)(url|href|css|class|id|color|image|icon|query|taxonomy|template|product|post|term)(?:$|_)/', $key ) ) { return false; }

        $profiles = $this->profiles();
        if ( isset( $profiles[ $widget ] ) && is_array( $profiles[ $widget ] ) ) {
            $profile_keys = array_map( 'strtolower', (array) ( $profiles[ $widget ]['keys'] ?? array() ) );
            return in_array( $key, $profile_keys, true );
        }

        $allow = array(
            'title','subtitle','text','editor','description','content','button_text','btn_text','link_text','caption','label',
            'placeholder','heading','name','job','prefix','suffix','tab_title','tab_content','item_title','item_description',
            'testimonial_content','title_text','description_text','field_label','field_placeholder','success_message',
            'error_message','required_message','invalid_message','before_text','highlighted_text','after_text','testimonial_name',
            'testimonial_job','inner_text','blockquote_content','tweet_button_label','item_text','item_label','menu_title',
            'read_more_text','after_title','before_title','extra_title',
        );
        $allow = apply_filters( 'itkt_elementor_translatable_keys', $allow, $widget );
        if ( in_array( $key, array_map( 'strtolower', (array) $allow ), true ) ) { return true; }
        return (bool) preg_match( '/(?:^|_)(title|subtitle|heading|text|description|content|caption|label|placeholder|message)$/', $key );
    }

    private function is_safe_text( $value ) {
        $trim = trim( $value );
        if ( '' === $trim || strlen( $trim ) > 30000 ) { return false; }
        if ( preg_match( '/^(https?:|mailto:|tel:|#|\.\/|\/)/i', $trim ) ) { return false; }
        if ( false !== stripos( $trim, '<script' ) || false !== stripos( $trim, '<style' ) ) { return false; }
        if ( preg_match( '/^\[[a-z0-9_-]+[^\]]*\]$/i', $trim ) ) { return false; }
        if ( preg_match( '/^\s*[\{\[]\s*"[^\n]+[\}\]]\s*$/s', $trim ) ) { return false; }
        if ( preg_match( '/(?:^|;)\s*[a-z-]+\s*:\s*[^;]+;?/i', $trim ) && false === strpos( $trim, '<' ) ) { return false; }
        return true;
    }

    private function looks_like_rich_text( $value ) {
        return (bool) preg_match( '/<\/?(?:p|strong|em|ul|ol|li|br|span|h[1-6]|a)\b/i', (string) $value );
    }

    private function widget_label( $widget ) {
        $profiles = $this->profiles();
        if ( isset( $profiles[ $widget ]['label'] ) && $profiles[ $widget ]['label'] ) { return (string) $profiles[ $widget ]['label']; }
        $widget = str_replace( array('wd_','woodmart_','xts_'), '', (string) $widget );
        return ucwords( str_replace( array('-','_'), ' ', $widget ?: 'Widget' ) );
    }

    private function field_label( $key ) {
        $labels = array(
            'title'=>'Titel','subtitle'=>'Untertitel','title_text'=>'Titel','heading'=>'Überschrift','text'=>'Text','editor'=>'Text',
            'content'=>'Inhalt','description'=>'Beschreibung','description_text'=>'Beschreibung','button_text'=>'Button-Text',
            'btn_text'=>'Button-Text','link_text'=>'Link-Text','caption'=>'Bildunterschrift','label'=>'Bezeichnung',
            'placeholder'=>'Platzhalter','field_placeholder'=>'Platzhalter','field_label'=>'Feldbezeichnung','name'=>'Name','job'=>'Position',
            'prefix'=>'Präfix','suffix'=>'Suffix','tab_title'=>'Tab-Titel','tab_content'=>'Tab-Inhalt','item_title'=>'Element-Titel',
            'item_description'=>'Element-Beschreibung','item_text'=>'Element-Text','testimonial_content'=>'Testimonial-Text',
            'testimonial_name'=>'Name','testimonial_job'=>'Position','success_message'=>'Erfolgsmeldung','error_message'=>'Fehlermeldung',
            'required_message'=>'Pflichtfeld-Meldung','invalid_message'=>'Ungültig-Meldung','before_text'=>'Text davor',
            'highlighted_text'=>'Hervorgehobener Text','after_text'=>'Text danach','inner_text'=>'Innentext','read_more_text'=>'Weiterlesen-Text',
            'before_title'=>'Text vor Titel','after_title'=>'Text nach Titel','extra_title'=>'Zusatz-Titel','menu_title'=>'Menütitel',
        );
        if ( isset( $labels[ $key ] ) ) { return $labels[ $key ]; }
        return ucwords( str_replace( array('_','-'), ' ', (string) $key ) );
    }

    public function is_woodmart_widget( $widget ) {
        $widget = strtolower( (string) $widget );
        return 0 === strpos( $widget, 'wd_' ) || false !== strpos( $widget, 'woodmart' ) || false !== strpos( $widget, 'xts' );
    }

    public function translated_values( $post_id ) {
        $out = array();
        foreach ( $this->segments( $post_id ) as $s ) { if ( empty( $s['manual'] ) ) { $out[ $s['key'] ] = $s['source']; } }
        return $out;
    }

    public function apply( $source_id, $translation_id, $submitted ) {
        $raw = get_post_meta( $translation_id, '_elementor_data', true );
        $data = is_string( $raw ) ? json_decode( $raw, true ) : null;
        if ( ! is_array( $data ) ) { return false; }

        $before_structure = $this->structure_fingerprint( $data );
        $segments = $this->segments( $source_id );
        foreach ( $segments as $segment ) {
            if ( ! empty( $segment['manual'] ) || ! array_key_exists( $segment['key'], (array) $submitted ) ) { continue; }
            $value = wp_kses_post( wp_unslash( $submitted[ $segment['key'] ] ) );
            $this->update_element( $data, $segment['element_id'], $segment['setting_path'], $value );
        }

        if ( $before_structure !== $this->structure_fingerprint( $data ) ) { return false; }
        update_post_meta( $translation_id, '_elementor_data', wp_slash( wp_json_encode( $data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) ) );
        delete_post_meta( $translation_id, '_elementor_css' );
        clean_post_cache( $translation_id );
        do_action( 'itkt_elementor_translation_applied', $source_id, $translation_id );
        return true;
    }

    private function structure_fingerprint( $elements ) {
        return hash( 'sha256', wp_json_encode( $this->structure_fingerprint_array( $elements ) ) );
    }

    private function structure_fingerprint_array( $elements ) {
        $tree = array();
        foreach ( (array) $elements as $element ) {
            if ( ! is_array( $element ) ) { continue; }
            $node = array(
                'id' => isset( $element['id'] ) ? (string) $element['id'] : '',
                'elType' => isset( $element['elType'] ) ? (string) $element['elType'] : '',
                'widgetType' => isset( $element['widgetType'] ) ? (string) $element['widgetType'] : '',
                'children' => array(),
            );
            if ( ! empty( $element['elements'] ) && is_array( $element['elements'] ) ) { $node['children'] = $this->structure_fingerprint_array( $element['elements'] ); }
            $tree[] = $node;
        }
        return $tree;
    }

    private function update_element( &$elements, $element_id, $path, $value ) {
        foreach ( $elements as &$element ) {
            if ( ! is_array( $element ) ) { continue; }
            if ( isset( $element['id'] ) && (string) $element['id'] === (string) $element_id ) {
                if ( ! isset( $element['settings'] ) || ! is_array( $element['settings'] ) ) { $element['settings'] = array(); }
                $ref =& $element['settings'];
                foreach ( $path as $i => $part ) {
                    if ( $i === count( $path ) - 1 ) { $ref[ $part ] = $value; break; }
                    if ( ! isset( $ref[ $part ] ) || ! is_array( $ref[ $part ] ) ) { $ref[ $part ] = array(); }
                    $ref =& $ref[ $part ];
                }
                unset( $ref ); return true;
            }
            if ( ! empty( $element['elements'] ) && is_array( $element['elements'] ) && $this->update_element( $element['elements'], $element_id, $path, $value ) ) { return true; }
        }
        unset( $element ); return false;
    }
}
