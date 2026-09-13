<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class ITKT_Content {
    private static $instance = null;
    public static function instance() { if ( null === self::$instance ) { self::$instance = new self(); } return self::$instance; }

    private function __construct() { add_action( 'save_post', array( $this, 'ensure_default_language_meta' ), 20, 2 ); }

    public function supported_post_types() { return apply_filters( 'itkt_supported_post_types', array( 'page', 'post' ) ); }

    public function ensure_default_language_meta( $post_id, $post ) {
        if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) || ! $post || ! in_array( $post->post_type, $this->supported_post_types(), true ) ) { return; }
        if ( ! get_post_meta( $post_id, '_itkt_language', true ) ) { update_post_meta( $post_id, '_itkt_language', ITKT_Languages::instance()->get_default_code() ); }
        if ( ! get_post_meta( $post_id, '_itkt_group', true ) ) { update_post_meta( $post_id, '_itkt_group', wp_generate_uuid4() ); }
    }

    public function assign_existing_default_language( $post_types ) {
        $default = ITKT_Languages::instance()->get_default_code();
        foreach ( array_intersect( (array) $post_types, $this->supported_post_types() ) as $post_type ) {
            $ids = get_posts( array( 'post_type' => $post_type, 'post_status' => array( 'publish','draft','private','pending','future' ), 'numberposts' => -1, 'fields' => 'ids', 'suppress_filters' => true ) );
            foreach ( $ids as $post_id ) {
                if ( ! get_post_meta( $post_id, '_itkt_language', true ) ) { update_post_meta( $post_id, '_itkt_language', $default ); }
                if ( ! get_post_meta( $post_id, '_itkt_group', true ) ) { update_post_meta( $post_id, '_itkt_group', wp_generate_uuid4() ); }
            }
        }
    }

    public function get_items( $post_type, $limit = 100 ) {
        $default = ITKT_Languages::instance()->get_default_code();
        $query = new WP_Query( array(
            'post_type' => $post_type,
            'post_status' => array( 'publish','draft','private','pending' ),
            'posts_per_page' => $limit,
            'meta_query' => array( 'relation' => 'OR', array( 'key' => '_itkt_language', 'compare' => 'NOT EXISTS' ), array( 'key' => '_itkt_language', 'value' => $default ) ),
            'orderby' => 'modified', 'order' => 'DESC',
        ) );
        return $query->posts;
    }

    public function translations_for( $post_id ) {
        $group = get_post_meta( $post_id, '_itkt_group', true );
        if ( ! $group ) { return array(); }
        $posts = get_posts( array( 'post_type' => get_post_type( $post_id ), 'post_status' => array( 'publish','draft','private','pending' ), 'numberposts' => -1, 'meta_key' => '_itkt_group', 'meta_value' => $group, 'suppress_filters' => true ) );
        $out = array();
        foreach ( $posts as $post ) { $lang = get_post_meta( $post->ID, '_itkt_language', true ); if ( $lang ) { $out[ $lang ] = $post; } }
        return $out;
    }

    /**
     * Link an already existing page/post as the translation of a source item.
     * No content, Elementor data or layout is copied or changed.
     */
    public function link_existing_translation( $source_id, $target_id, $language ) {
        $source_id = absint( $source_id );
        $target_id = absint( $target_id );
        $language  = sanitize_key( (string) $language );
        $source    = get_post( $source_id );
        $target    = get_post( $target_id );
        $languages = ITKT_Languages::instance()->get_all();

        if ( ! $source || ! $target || $source_id === $target_id ) {
            return new WP_Error( 'invalid_link', __( 'Invalid source or target content.', 'it-kayali-translate' ) );
        }
        if ( $source->post_type !== $target->post_type || ! in_array( $source->post_type, $this->supported_post_types(), true ) ) {
            return new WP_Error( 'wrong_type', __( 'Source and target must use the same content type.', 'it-kayali-translate' ) );
        }
        if ( empty( $languages[ $language ] ) || $language === ITKT_Languages::instance()->get_default_code() ) {
            return new WP_Error( 'invalid_language', __( 'Invalid target language.', 'it-kayali-translate' ) );
        }

        $existing = $this->translations_for( $source_id );
        if ( ! empty( $existing[ $language ] ) && absint( $existing[ $language ]->ID ) !== $target_id ) {
            return new WP_Error( 'translation_exists', __( 'A different translation is already linked for this language.', 'it-kayali-translate' ) );
        }

        $group = get_post_meta( $source_id, '_itkt_group', true );
        if ( ! $group ) {
            $group = wp_generate_uuid4();
            update_post_meta( $source_id, '_itkt_group', $group );
        }

        update_post_meta( $target_id, '_itkt_language', $language );
        update_post_meta( $target_id, '_itkt_group', $group );
        update_post_meta( $target_id, '_itkt_source_hash', $this->source_hash( $source_id ) );
        clean_post_cache( $target_id );

        do_action( 'itkt_existing_translation_linked', $source_id, $target_id, $language );
        return $target_id;
    }

    public function link_candidates( $post_type, $source_id ) {
        if ( ! in_array( $post_type, $this->supported_post_types(), true ) ) { return array(); }
        $exclude = array( absint( $source_id ) );
        foreach ( $this->translations_for( $source_id ) as $translation ) {
            $exclude[] = absint( $translation->ID );
        }
        $posts = get_posts( array(
            'post_type'        => $post_type,
            'post_status'      => array( 'publish', 'draft', 'private', 'pending' ),
            'numberposts'      => -1,
            'orderby'          => 'title',
            'order'            => 'ASC',
            'post__not_in'     => array_unique( array_filter( $exclude ) ),
            'suppress_filters' => true,
        ) );

        // Do not offer content that already belongs to another multi-item translation group.
        return array_values( array_filter( $posts, function( $post ) {
            $group = get_post_meta( $post->ID, '_itkt_group', true );
            if ( ! $group ) { return true; }
            $siblings = get_posts( array(
                'post_type'        => $post->post_type,
                'post_status'      => array( 'publish', 'draft', 'private', 'pending' ),
                'numberposts'      => 2,
                'fields'           => 'ids',
                'meta_key'         => '_itkt_group',
                'meta_value'       => $group,
                'suppress_filters' => true,
            ) );
            return count( $siblings ) <= 1;
        } ) );
    }

    public function create_translation( $source_id, $language ) {
        $source = get_post( $source_id );
        if ( ! $source || ! in_array( $source->post_type, $this->supported_post_types(), true ) ) { return new WP_Error( 'invalid_source', 'Invalid source content.' ); }
        $languages = ITKT_Languages::instance()->get_all();
        if ( empty( $languages[ $language ] ) ) { return new WP_Error( 'invalid_language', 'Invalid language.' ); }
        $existing = $this->translations_for( $source_id );
        if ( isset( $existing[ $language ] ) ) { return $existing[ $language ]->ID; }

        $group = get_post_meta( $source_id, '_itkt_group', true );
        if ( ! $group ) { $group = wp_generate_uuid4(); update_post_meta( $source_id, '_itkt_group', $group ); }
        $new_id = wp_insert_post( array(
            'post_type' => $source->post_type, 'post_status' => 'draft', 'post_title' => $source->post_title,
            'post_content' => $source->post_content, 'post_excerpt' => $source->post_excerpt, 'post_parent' => $source->post_parent,
            'menu_order' => $source->menu_order, 'comment_status' => $source->comment_status, 'ping_status' => $source->ping_status,
        ), true );
        if ( is_wp_error( $new_id ) ) { return $new_id; }
        $this->copy_meta( $source_id, $new_id );
        $this->copy_taxonomies( $source_id, $new_id, $source->post_type );
        update_post_meta( $new_id, '_itkt_language', $language );
        update_post_meta( $new_id, '_itkt_group', $group );
        update_post_meta( $new_id, '_itkt_source_hash', $this->source_hash( $source_id ) );
        do_action( 'itkt_translation_created', $source_id, $new_id, $language );
        return $new_id;
    }

    private function copy_meta( $source_id, $new_id ) {
        $all = get_post_meta( $source_id );
        $skip = array( '_edit_lock','_edit_last','_itkt_language','_itkt_group','_itkt_source_hash','_elementor_css' );

        foreach ( $all as $key => $values ) {
            if ( in_array( $key, $skip, true ) ) { continue; }
            delete_post_meta( $new_id, $key );

            foreach ( $values as $value ) {
                $value = maybe_unserialize( $value );

                /*
                 * WordPress strips slashes before writing meta. Elementor's document JSON may
                 * contain escaped HTML/JSON strings, so it must be re-slashed before saving.
                 * Without this, Elementor can normalize a duplicate into a different structure.
                 */
                if ( '_elementor_data' === $key && is_string( $value ) ) {
                    add_post_meta( $new_id, $key, wp_slash( $value ) );
                } else {
                    add_post_meta( $new_id, $key, wp_slash( $value ) );
                }
            }
        }

        // A duplicate must generate its own Elementor CSS cache from the copied document.
        delete_post_meta( $new_id, '_elementor_css' );
    }

    /**
     * Re-copy only the Elementor document/layout from the source while keeping translated text.
     * Useful for translations created with an older IT-Kayali Translate version.
     */
    public function repair_elementor_layout( $source_id, $translation_id ) {
        if ( ! $this->is_elementor( $source_id ) || ! $translation_id ) {
            return new WP_Error( 'not_elementor', __( 'The source is not an Elementor document.', 'it-kayali-translate' ) );
        }

        $raw = get_post_meta( $source_id, '_elementor_data', true );
        if ( ! is_string( $raw ) || '' === trim( $raw ) ) {
            return new WP_Error( 'missing_elementor_data', __( 'Elementor document data is missing.', 'it-kayali-translate' ) );
        }

        // Preserve translations by stable Elementor element-id + setting path keys.
        $translated_values = ITKT_Elementor::instance()->translated_values( $translation_id );

        // Keep all non-text/page data on the translation, but restore the source layout document exactly.
        update_post_meta( $translation_id, '_elementor_data', wp_slash( $raw ) );

        foreach ( array( '_elementor_edit_mode', '_elementor_template_type', '_elementor_page_settings', '_elementor_version', '_elementor_pro_version' ) as $key ) {
            $value = get_post_meta( $source_id, $key, true );
            if ( '' !== $value && null !== $value ) {
                update_post_meta( $translation_id, $key, wp_slash( $value ) );
            }
        }

        if ( $translated_values ) {
            ITKT_Elementor::instance()->apply( $source_id, $translation_id, $translated_values );
        }

        delete_post_meta( $translation_id, '_elementor_css' );
        clean_post_cache( $translation_id );

        do_action( 'itkt_elementor_layout_repaired', $source_id, $translation_id );
        return true;
    }

    private function copy_taxonomies( $source_id, $new_id, $post_type ) {
        foreach ( get_object_taxonomies( $post_type ) as $taxonomy ) {
            $terms = wp_get_object_terms( $source_id, $taxonomy, array( 'fields' => 'ids' ) );
            if ( ! is_wp_error( $terms ) ) { wp_set_object_terms( $new_id, $terms, $taxonomy ); }
        }
    }

    public function is_elementor( $post_id ) {
        if ( class_exists( '\\Elementor\\Plugin' ) ) {
            try {
                $doc = \Elementor\Plugin::$instance->documents->get( $post_id );
                if ( $doc && method_exists( $doc, 'is_built_with_elementor' ) && $doc->is_built_with_elementor() ) { return true; }
            } catch ( Throwable $e ) { /* fallback below */ }
        }
        return 'builder' === get_post_meta( $post_id, '_elementor_edit_mode', true ) || (bool) get_post_meta( $post_id, '_elementor_data', true );
    }

    public function edit_url( $post_id ) {
        if ( $this->is_elementor( $post_id ) && class_exists( '\\Elementor\\Plugin' ) ) {
            try {
                $doc = \Elementor\Plugin::$instance->documents->get( $post_id );
                if ( $doc && method_exists( $doc, 'get_edit_url' ) ) { return $doc->get_edit_url(); }
            } catch ( Throwable $e ) { /* fallback */ }
            return admin_url( 'post.php?post=' . absint( $post_id ) . '&action=elementor' );
        }
        return get_edit_post_link( $post_id, 'raw' );
    }

    public function source_hash( $post_id ) {
        $post = get_post( $post_id ); if ( ! $post ) { return ''; }
        return hash( 'sha256', $post->post_title . '|' . $post->post_content . '|' . $post->post_excerpt . '|' . $post->post_modified_gmt . '|' . (string) get_post_meta( $post_id, '_elementor_data', true ) );
    }

    public function translation_status( $source_id, $translation ) {
        if ( ! $translation ) { return 'missing'; }
        $stored = get_post_meta( $translation->ID, '_itkt_source_hash', true );
        if ( $stored && $stored !== $this->source_hash( $source_id ) ) { return 'outdated'; }
        if ( 'draft' === $translation->post_status ) { return 'draft'; }
        return 'complete';
    }

    public function mark_synced( $source_id, $translation_id ) { update_post_meta( $translation_id, '_itkt_source_hash', $this->source_hash( $source_id ) ); }
}
