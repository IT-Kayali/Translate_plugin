<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class ITKT_Term_Translations {
    private static $instance = null;
    const META_KEY = '_itkt_term_translations';
    const ATTR_OPTION = 'itkt_attribute_translations';
    const RUNTIME_VERSION_OPTION = 'itkt_taxonomy_runtime_version';

    public static function instance() {
        if ( null === self::$instance ) { self::$instance = new self(); }
        return self::$instance;
    }

    private function __construct() {
        add_filter( 'get_term', array( $this, 'filter_term' ), 20, 2 );
        // WooCommerce/WoodMart filter widgets commonly fetch complete term collections rather
        // than calling get_term() one by one. Cover that path as well, including storefront AJAX.
        add_filter( 'get_terms', array( $this, 'filter_terms' ), 20, 4 );
        add_filter( 'term_description', array( $this, 'filter_term_description' ), 20, 2 );
        add_filter( 'woocommerce_attribute_label', array( $this, 'filter_attribute_label' ), 20, 3 );
        add_filter( 'woocommerce_page_title', array( $this, 'filter_woocommerce_page_title' ), 20 );
        add_filter( 'single_term_title', array( $this, 'filter_single_term_title' ), 20 );
        add_filter( 'get_the_archive_title', array( $this, 'filter_archive_title' ), 20 );

        // Keep the lightweight frontend runtime map fresh after taxonomy content changes.
        add_action( 'deleted_term', array( $this, 'bump_runtime_version' ), 20 );
    }

    public function get( $term_id, $language ) {
        $all = get_term_meta( $term_id, self::META_KEY, true );
        return is_array( $all ) && isset( $all[ $language ] ) ? (array) $all[ $language ] : array();
    }

    public function save( $term_id, $language, $data ) {
        $term = get_term( $term_id );
        if ( ! $term || is_wp_error( $term ) ) { return new WP_Error( 'invalid_term', 'Invalid term.' ); }
        $all = get_term_meta( $term_id, self::META_KEY, true );
        if ( ! is_array( $all ) ) { $all = array(); }
        $previous = isset( $all[ $language ] ) && is_array( $all[ $language ] ) ? $all[ $language ] : array();
        $name = array_key_exists( 'name', $data ) ? sanitize_text_field( $data['name'] ) : (string) ( $previous['name'] ?? '' );
        $description = array_key_exists( 'description', $data ) ? wp_kses_post( $data['description'] ) : (string) ( $previous['description'] ?? '' );
        $slug = array_key_exists( 'slug', $data ) ? sanitize_title( $data['slug'] ) : sanitize_title( $previous['slug'] ?? '' );
        if ( ! $slug && $name ) { $slug = sanitize_title( $name ); }
        $seo_title = array_key_exists( 'seo_title', $data ) ? sanitize_text_field( $data['seo_title'] ) : (string) ( $previous['seo_title'] ?? '' );
        $seo_description = array_key_exists( 'seo_description', $data ) ? sanitize_textarea_field( $data['seo_description'] ) : (string) ( $previous['seo_description'] ?? '' );
        $all[ $language ] = array(
            'name'        => $name,
            'description' => $description,
            'slug'        => $slug,
            'seo_title'   => $seo_title,
            'seo_description' => $seo_description,
            'source_hash' => $this->source_hash( $term_id ),
            'updated_at'  => current_time( 'mysql' ),
        );
        update_term_meta( $term_id, self::META_KEY, $all );
        if ( $slug ) { update_term_meta( $term_id, '_itkt_slug_' . sanitize_key( $language ), $slug ); }
        else { delete_term_meta( $term_id, '_itkt_slug_' . sanitize_key( $language ) ); }
        $this->bump_runtime_version();
        do_action( 'itkt_term_translation_saved', $term_id, $language, $all[ $language ] );
        return true;
    }

    public function source_hash( $term_id ) {
        $term = get_term( $term_id );
        return ( ! $term || is_wp_error( $term ) ) ? '' : hash( 'sha256', $term->name . '|' . $term->description . '|' . $term->slug );
    }

    public function status( $term_id, $language ) {
        $data = $this->get( $term_id, $language );
        if ( empty( $data['name'] ) ) { return 'missing'; }
        if ( ! empty( $data['source_hash'] ) && $data['source_hash'] !== $this->source_hash( $term_id ) ) { return 'outdated'; }
        return 'complete';
    }

    public function get_attribute_data( $taxonomy, $language ) {
        $all = get_option( self::ATTR_OPTION, array() );
        $value = $all[ sanitize_key( $taxonomy ) ][ sanitize_key( $language ) ] ?? array();
        if ( is_string( $value ) ) {
            // Upgrade data written by V0.2-V0.4.
            return array( 'label' => $value, 'source_hash' => '', 'updated_at' => '' );
        }
        return is_array( $value ) ? $value : array();
    }

    public function get_attribute( $taxonomy, $language ) {
        $data = $this->get_attribute_data( $taxonomy, $language );
        return (string) ( $data['label'] ?? '' );
    }

    public function save_attribute( $taxonomy, $language, $label ) {
        $taxonomy = sanitize_key( $taxonomy );
        $language = sanitize_key( $language );
        $all = get_option( self::ATTR_OPTION, array() );
        if ( ! is_array( $all ) ) { $all = array(); }
        $all[ $taxonomy ][ $language ] = array(
            'label'       => sanitize_text_field( $label ),
            'source_hash' => $this->attribute_source_hash( $taxonomy ),
            'updated_at'  => current_time( 'mysql' ),
        );
        update_option( self::ATTR_OPTION, $all );
        $this->bump_runtime_version();
        do_action( 'itkt_attribute_translation_saved', $taxonomy, $language, $all[ $taxonomy ][ $language ] );
    }

    /** Increment the taxonomy-runtime generation used by request-local/object caches. */
    public function bump_runtime_version() {
        $version = absint( get_option( self::RUNTIME_VERSION_OPTION, 1 ) );
        update_option( self::RUNTIME_VERSION_OPTION, $version + 1, false );
    }

    public function attribute_source_label( $taxonomy ) {
        if ( ! function_exists( 'wc_get_attribute_taxonomies' ) ) { return (string) $taxonomy; }
        $slug = preg_replace( '/^pa_/', '', sanitize_key( $taxonomy ) );
        foreach ( (array) wc_get_attribute_taxonomies() as $attribute ) {
            if ( isset( $attribute->attribute_name ) && $slug === sanitize_key( $attribute->attribute_name ) ) {
                return (string) $attribute->attribute_label;
            }
        }
        return $slug;
    }

    public function attribute_source_hash( $taxonomy ) {
        return hash( 'sha256', $this->attribute_source_label( $taxonomy ) . '|' . sanitize_key( $taxonomy ) );
    }

    public function attribute_status( $taxonomy, $language ) {
        $data = $this->get_attribute_data( $taxonomy, $language );
        if ( empty( $data['label'] ) ) { return 'missing'; }
        if ( ! empty( $data['source_hash'] ) && $data['source_hash'] !== $this->attribute_source_hash( $taxonomy ) ) { return 'outdated'; }
        return 'complete';
    }

    private function is_woocommerce_taxonomy( $taxonomy ) {
        return 'product_cat' === $taxonomy || 'product_tag' === $taxonomy || 0 === strpos( (string) $taxonomy, 'pa_' );
    }

    /** Frontend + storefront AJAX only; ordinary wp-admin screens keep the administrator language. */
    private function should_translate_request() {
        if ( ! is_admin() ) { return true; }
        if ( ! wp_doing_ajax() ) { return false; }

        // admin-ajax.php serves both the storefront and wp-admin. An admin referer is the safest
        // signal that this request belongs to a backend Select2/editor operation and must stay raw.
        $referer = isset( $_SERVER['HTTP_REFERER'] ) ? (string) wp_unslash( $_SERVER['HTTP_REFERER'] ) : '';
        if ( $referer ) {
            $admin = (string) admin_url();
            if ( $admin && 0 === strpos( $referer, $admin ) ) { return false; }
        }
        return true;
    }

    /** Apply the active language to one term without ever changing its canonical slug. */
    private function translated_term_copy( $term, $language ) {
        if ( ! $term instanceof WP_Term || ! $this->is_woocommerce_taxonomy( $term->taxonomy ) ) { return $term; }
        $data = $this->get( $term->term_id, $language );
        if ( empty( $data['name'] ) && empty( $data['description'] ) ) { return $term; }
        $copy = clone $term;
        if ( ! empty( $data['name'] ) ) { $copy->name = $data['name']; }
        if ( ! empty( $data['description'] ) ) { $copy->description = $data['description']; }
        return $copy;
    }

    /** Raw canonical name from wp_terms, bypassing our own get_term filter. */
    private function source_term_name( $term_id, $fallback = '' ) {
        global $wpdb;
        $name = $wpdb->get_var( $wpdb->prepare( "SELECT name FROM {$wpdb->terms} WHERE term_id=%d", absint( $term_id ) ) );
        return is_string( $name ) && '' !== trim( $name ) ? (string) $name : (string) $fallback;
    }

    public function filter_term( $term, $taxonomy ) {
        if ( ! $this->should_translate_request() || ! $term instanceof WP_Term || ! $this->is_woocommerce_taxonomy( $taxonomy ) ) { return $term; }
        $language = ITKT_Languages::instance()->current_code();
        if ( $language === ITKT_Languages::instance()->get_default_code() ) { return $term; }
        return $this->translated_term_copy( $term, $language );
    }

    /** Translate term collections used by WooCommerce layered nav and WoodMart AJAX filters. */
    public function filter_terms( $terms, $taxonomies, $args, $term_query ) {
        if ( ! $this->should_translate_request() || ! is_array( $terms ) || empty( $terms ) ) { return $terms; }
        $language = ITKT_Languages::instance()->current_code();
        if ( $language === ITKT_Languages::instance()->get_default_code() ) { return $terms; }

        foreach ( $terms as $index => $term ) {
            if ( $term instanceof WP_Term && $this->is_woocommerce_taxonomy( $term->taxonomy ) ) {
                $terms[ $index ] = $this->translated_term_copy( $term, $language );
            }
        }
        return $terms;
    }

    public function filter_term_description( $description, $term_id ) {
        if ( ! $this->should_translate_request() || ! $term_id ) { return $description; }
        $term = get_term( $term_id );
        if ( ! $term || is_wp_error( $term ) || ! $this->is_woocommerce_taxonomy( $term->taxonomy ) ) { return $description; }
        $language = ITKT_Languages::instance()->current_code();
        if ( $language === ITKT_Languages::instance()->get_default_code() ) { return $description; }
        $data = $this->get( $term_id, $language );
        return ! empty( $data['description'] ) ? $data['description'] : $description;
    }

    public function filter_attribute_label( $label, $name, $product = null ) {
        if ( ! $this->should_translate_request() ) { return $label; }
        $language = ITKT_Languages::instance()->current_code();
        if ( $language === ITKT_Languages::instance()->get_default_code() ) { return $label; }
        // Product-owned custom attributes are translated on the same product ID.
        if ( is_object( $product ) && method_exists( $product, 'get_id' ) && 0 !== strpos( (string) $name, 'pa_' ) ) {
            $product_id = absint( $product->get_id() );
            if ( method_exists( $product, 'get_parent_id' ) && $product->get_parent_id() ) { $product_id = absint( $product->get_parent_id() ); }
            $data = ITKT_Product_Translations::instance()->get( $product_id, $language );
            foreach ( ITKT_Product_Translations::instance()->custom_attributes( $product_id ) as $key => $attribute ) {
                if ( (string) $attribute['name'] === (string) $name && ! empty( $data['custom_attributes'][ $key ]['label'] ) ) {
                    return $data['custom_attributes'][ $key ]['label'];
                }
            }
        }

        $taxonomy = 0 === strpos( (string) $name, 'pa_' ) ? (string) $name : 'pa_' . sanitize_title( (string) $name );
        $translated = $this->get_attribute( $taxonomy, $language );
        return $translated ?: $label;
    }

    public function filter_woocommerce_page_title( $title ) {
        if ( ! $this->should_translate_request() || ! function_exists( 'is_product_taxonomy' ) || ! is_product_taxonomy() ) { return $title; }
        $term = get_queried_object();
        if ( ! $term instanceof WP_Term ) { return $title; }
        $language = ITKT_Languages::instance()->current_code();
        if ( $language === ITKT_Languages::instance()->get_default_code() ) { return $title; }
        $data = $this->get( $term->term_id, $language );
        return ! empty( $data['name'] ) ? $data['name'] : $title;
    }

    /** WordPress archive-title fallback used by themes that do not call woocommerce_page_title(). */
    public function filter_single_term_title( $title ) {
        if ( ! $this->should_translate_request() ) { return $title; }
        $term = get_queried_object();
        if ( ! $term instanceof WP_Term || ! $this->is_woocommerce_taxonomy( $term->taxonomy ) ) { return $title; }
        $language = ITKT_Languages::instance()->current_code();
        if ( $language === ITKT_Languages::instance()->get_default_code() ) { return $title; }
        $data = $this->get( $term->term_id, $language );
        return ! empty( $data['name'] ) ? $data['name'] : $title;
    }

    /** Replace only the term-name part while preserving theme prefixes such as “Category:”. */
    public function filter_archive_title( $title ) {
        if ( ! $this->should_translate_request() ) { return $title; }
        $term = get_queried_object();
        if ( ! $term instanceof WP_Term || ! $this->is_woocommerce_taxonomy( $term->taxonomy ) ) { return $title; }
        $language = ITKT_Languages::instance()->current_code();
        if ( $language === ITKT_Languages::instance()->get_default_code() ) { return $title; }
        $data = $this->get( $term->term_id, $language );
        if ( empty( $data['name'] ) ) { return $title; }
        $source = $this->source_term_name( $term->term_id, (string) $term->name );
        return $source && false !== strpos( (string) $title, $source )
            ? str_replace( $source, (string) $data['name'], (string) $title )
            : (string) $data['name'];
    }

    /**
     * Compact source→target map for cached/WoodMart-rendered filter markup.
     * Slugs are intentionally omitted: WooCommerce filter query args must continue using the
     * canonical source slug even while the visitor sees the translated label.
     */
    public function frontend_runtime_map( $language ) {
        $language = sanitize_key( (string) $language );
        if ( ! $language || $language === ITKT_Languages::instance()->get_default_code() ) {
            return array( 'terms' => array(), 'attributes' => array() );
        }

        $version = absint( get_option( self::RUNTIME_VERSION_OPTION, 1 ) );
        $cache_key = 'itkt_tax_runtime_' . md5( $language . '|' . $version );
        $cached = wp_cache_get( $cache_key, 'itkt' );
        if ( is_array( $cached ) ) { return $cached; }

        global $wpdb;
        $out = array( 'terms' => array(), 'attributes' => array() );
        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT tm.term_id, tm.meta_value, t.name AS source_name, tt.taxonomy
             FROM {$wpdb->termmeta} tm
             INNER JOIN {$wpdb->terms} t ON t.term_id = tm.term_id
             INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_id = tm.term_id
             WHERE tm.meta_key = %s",
            self::META_KEY
        ) );

        foreach ( (array) $rows as $row ) {
            $taxonomy = (string) ( $row->taxonomy ?? '' );
            if ( ! $this->is_woocommerce_taxonomy( $taxonomy ) ) { continue; }
            $all = maybe_unserialize( $row->meta_value ?? '' );
            $target = is_array( $all ) && isset( $all[ $language ]['name'] ) ? trim( (string) $all[ $language ]['name'] ) : '';
            $source = trim( (string) ( $row->source_name ?? '' ) );
            if ( '' === $source || '' === $target || $source === $target ) { continue; }
            $out['terms'][] = array(
                'termId'   => absint( $row->term_id ?? 0 ),
                'taxonomy' => $taxonomy,
                'source'   => $source,
                'target'   => $target,
            );
        }

        if ( function_exists( 'wc_get_attribute_taxonomies' ) ) {
            foreach ( (array) wc_get_attribute_taxonomies() as $attribute ) {
                if ( empty( $attribute->attribute_name ) ) { continue; }
                $taxonomy = 'pa_' . sanitize_key( $attribute->attribute_name );
                $source = trim( (string) ( $attribute->attribute_label ?? $attribute->attribute_name ) );
                $target = trim( $this->get_attribute( $taxonomy, $language ) );
                if ( '' === $source || '' === $target || $source === $target ) { continue; }
                $out['attributes'][] = array(
                    'taxonomy' => $taxonomy,
                    'source'   => $source,
                    'target'   => $target,
                );
            }
        }

        wp_cache_set( $cache_key, $out, 'itkt', HOUR_IN_SECONDS );
        return $out;
    }
}
