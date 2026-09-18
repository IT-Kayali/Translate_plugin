<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class ITKT_Product_Translations {
    private static $instance = null;
    const META_KEY = '_itkt_product_translations';
    const SEARCH_META_PREFIX = '_itkt_search_';
    const SEARCH_INDEX_VERSION = '1';

    public static function instance() {
        if ( null === self::$instance ) { self::$instance = new self(); }
        return self::$instance;
    }

    private function __construct() {
        add_filter( 'woocommerce_product_get_name', array( $this, 'filter_name' ), 20, 2 );
        add_filter( 'woocommerce_product_variation_get_name', array( $this, 'filter_variation_name' ), 20, 2 );
        add_filter( 'woocommerce_product_get_description', array( $this, 'filter_description' ), 20, 2 );
        add_filter( 'woocommerce_product_get_short_description', array( $this, 'filter_short_description' ), 20, 2 );
        // WooCommerce's single-product short-description template reads $post->post_excerpt
        // directly and then applies this filter. WoodMart follows the same path in several
        // product layouts, so this hook is required in addition to the WC product getter.
        // Run before WooCommerce formatting filters (wpautop/do_shortcode) so the translated
        // text receives exactly the same formatting as the source excerpt.
        add_filter( 'woocommerce_short_description', array( $this, 'filter_woocommerce_short_description' ), 5 );
        add_filter( 'the_title', array( $this, 'filter_post_title' ), 19, 2 );
        add_filter( 'get_the_excerpt', array( $this, 'filter_post_excerpt' ), 19, 2 );
        add_filter( 'the_excerpt', array( $this, 'filter_the_excerpt' ), 19 );
        add_filter( 'the_content', array( $this, 'filter_post_content' ), 19 );
        add_filter( 'woocommerce_display_product_attributes', array( $this, 'filter_display_product_attributes' ), 20, 2 );
        add_filter( 'woocommerce_variation_option_name', array( $this, 'filter_variation_option_name' ), 20, 4 );

        // Cart / mini-cart / checkout templates do not all rely on WC_Product::get_name().
        // Force the visible cart item name and formatted variation data through the same
        // translation layer so the selected storefront language is preserved end-to-end.
        add_filter( 'woocommerce_cart_item_product', array( $this, 'filter_cart_item_product' ), 99, 3 );
        add_filter( 'woocommerce_cart_item_name', array( $this, 'filter_cart_item_name' ), 99, 3 );
        add_filter( 'woocommerce_get_item_data', array( $this, 'filter_cart_item_data' ), 99, 2 );
        add_filter( 'woocommerce_order_item_name', array( $this, 'filter_order_item_name' ), 99, 3 );
        add_filter( 'woocommerce_order_item_display_meta_key', array( $this, 'filter_order_meta_key' ), 99, 3 );
        add_filter( 'woocommerce_order_item_display_meta_value', array( $this, 'filter_order_meta_value' ), 99, 3 );

        // Frontend product search should also understand translated product content. A compact
        // per-language index avoids scanning the serialized translation blob on every request.
        add_filter( 'posts_search', array( $this, 'filter_translated_product_search' ), 25, 2 );
        add_action( 'itkt_rebuild_product_search_index', array( $this, 'rebuild_search_index_batch' ), 10, 1 );
        add_action( 'itkt_term_translation_saved', array( $this, 'invalidate_search_index_version' ), 10, 3 );
        add_action( 'itkt_attribute_translation_saved', array( $this, 'invalidate_search_index_version' ), 10, 3 );
        $this->maybe_schedule_search_index_rebuild();
    }

    public function fields() {
        return apply_filters( 'itkt_product_translation_fields', array( 'title', 'short_description', 'description', 'slug', 'seo_title', 'seo_description', 'custom_attributes' ) );
    }

    public function get_all( $product_id ) {
        $value = get_post_meta( $product_id, self::META_KEY, true );
        return is_array( $value ) ? $value : array();
    }

    public function get( $product_id, $language ) {
        $all = $this->get_all( $product_id );
        $row = isset( $all[ $language ] ) && is_array( $all[ $language ] ) ? $all[ $language ] : array();
        // Legacy builds stored non-Latin slugs through sanitize_title(), which turns Arabic
        // characters into percent-encoded octets. Keep the editor/API human-readable while
        // remaining backward compatible with those existing rows.
        if ( isset( $row['slug'] ) ) {
            $row['slug'] = self::normalize_public_slug( $row['slug'] );
        }
        return $row;
    }

    /**
     * Normalize a translated public slug without transliterating/percent-encoding Unicode.
     * WordPress post_name values use sanitize_title(), but our translated product slug lives in
     * dedicated metadata and can safely remain readable (e.g. من-السلوى-نوجا). URL encoding is
     * handled by the browser/HTTP layer, not persisted back into the translation editor.
     */
    /** Stable ASCII lookup key for any Unicode public slug. */
    public static function public_slug_hash( $value ) {
        $slug = self::normalize_public_slug( $value );
        return $slug ? hash( 'sha256', $slug ) : '';
    }

    public static function normalize_public_slug( $value ) {
        $slug = trim( (string) $value );
        if ( '' === $slug ) { return ''; }

        // Decode legacy %D9%85... values. Two passes also repair accidentally double-encoded data.
        for ( $i = 0; $i < 2 && preg_match( '/%[0-9A-Fa-f]{2}/', $slug ); $i++ ) {
            $decoded = rawurldecode( $slug );
            if ( $decoded === $slug ) { break; }
            $slug = $decoded;
        }

        $slug = html_entity_decode( wp_strip_all_tags( $slug ), ENT_QUOTES, 'UTF-8' );
        $slug = preg_replace( '/[\s\x{00A0}]+/u', '-', $slug );
        $slug = preg_replace( '~[/\\?#%]+~u', '-', $slug );
        if ( null === $slug ) { return ''; }
        // Letters, combining marks and numbers from every language are valid. Everything else
        // becomes a separator so CSS/HTML/URL control characters can never enter the path.
        $slug = preg_replace( '/[^\p{L}\p{M}\p{N}_-]+/u', '-', $slug );
        $slug = preg_replace( '/-+/u', '-', $slug );
        $slug = trim( $slug, '-_' );
        if ( function_exists( 'mb_strtolower' ) ) { $slug = mb_strtolower( $slug, 'UTF-8' ); }
        return (string) $slug;
    }

    /**
     * Whether a translated product slug was explicitly entered by the editor/import.
     * Early development versions auto-generated a slug from the translated title. Those
     * legacy values must not force a translated public URL, because an empty slug now means
     * "use the default-language product URL".
     */
    public function has_explicit_slug( $product_id, $language ) {
        $data = $this->get( $product_id, $language );
        $slug = self::normalize_public_slug( (string) ( $data['slug'] ?? '' ) );
        if ( ! $slug ) { return false; }
        if ( array_key_exists( 'slug_explicit', $data ) ) { return ! empty( $data['slug_explicit'] ); }

        // Backward compatibility: a legacy slug identical to the auto-generated title slug is
        // treated as implicit. A different slug was most likely manually supplied and remains valid.
        $title_slug = self::normalize_public_slug( (string) ( $data['title'] ?? '' ) );
        return ! $title_slug || ! hash_equals( $title_slug, $slug );
    }

    /** Public translated slug, or an empty string when the default-language slug must be used. */
    public function explicit_slug( $product_id, $language ) {
        if ( ! $this->has_explicit_slug( $product_id, $language ) ) { return ''; }
        $data = $this->get( $product_id, $language );
        return self::normalize_public_slug( (string) ( $data['slug'] ?? '' ) );
    }

    public function save( $product_id, $language, $data ) {
        if ( 'product' !== get_post_type( $product_id ) ) { return new WP_Error( 'not_product', 'Invalid product.' ); }

        $all = $this->get_all( $product_id );
        $previous = isset( $all[ $language ] ) && is_array( $all[ $language ] ) ? $all[ $language ] : array();
        $custom_attributes = isset( $data['custom_attributes'] ) ? array() : (array) ( $previous['custom_attributes'] ?? array() );
        foreach ( (array) ( $data['custom_attributes'] ?? array() ) as $key => $row ) {
            $key = sanitize_key( $key );
            if ( ! $key || ! is_array( $row ) ) { continue; }
            $custom_attributes[ $key ] = array(
                'label'  => sanitize_text_field( $row['label'] ?? '' ),
                'values' => sanitize_text_field( $row['values'] ?? '' ),
            );
        }

        $title = array_key_exists( 'title', $data ) ? sanitize_text_field( $data['title'] ) : (string) ( $previous['title'] ?? '' );
        $short_description = array_key_exists( 'short_description', $data ) ? wp_kses_post( $data['short_description'] ) : (string) ( $previous['short_description'] ?? '' );
        $description = array_key_exists( 'description', $data ) ? wp_kses_post( $data['description'] ) : (string) ( $previous['description'] ?? '' );

        // A language slug is optional. Do NOT generate it from the translated title: when the
        // field is empty the frontend intentionally falls back to the default-language product URL.
        if ( array_key_exists( 'slug', $data ) ) {
            $slug = self::normalize_public_slug( $data['slug'] );
            $slug_explicit = ( '' !== $slug );
        } else {
            $slug = self::normalize_public_slug( $previous['slug'] ?? '' );
            if ( array_key_exists( 'slug_explicit', $previous ) ) {
                $slug_explicit = ! empty( $previous['slug_explicit'] );
            } else {
                $legacy_title_slug = self::normalize_public_slug( (string) ( $previous['title'] ?? '' ) );
                $slug_explicit = ( '' !== $slug && ( ! $legacy_title_slug || ! hash_equals( $legacy_title_slug, $slug ) ) );
            }
        }
        $seo_title = array_key_exists( 'seo_title', $data ) ? sanitize_text_field( $data['seo_title'] ) : (string) ( $previous['seo_title'] ?? '' );
        $seo_description = array_key_exists( 'seo_description', $data ) ? sanitize_textarea_field( $data['seo_description'] ) : (string) ( $previous['seo_description'] ?? '' );

        $all[ $language ] = array(
            'title'             => $title,
            'short_description' => $short_description,
            'description'       => $description,
            'slug'              => $slug,
            'slug_explicit'     => (bool) $slug_explicit,
            'seo_title'         => $seo_title,
            'seo_description'   => $seo_description,
            'custom_attributes' => $custom_attributes,
            'source_hash'       => $this->source_hash( $product_id ),
            'updated_at'        => current_time( 'mysql' ),
        );
        update_post_meta( $product_id, self::META_KEY, $all );
        $this->update_search_index( $product_id, $language, $all[ $language ] );
        $slug_meta_key = '_itkt_slug_' . sanitize_key( $language );
        $hash_meta_key = '_itkt_slug_hash_' . sanitize_key( $language );
        if ( $slug && $slug_explicit ) {
            update_post_meta( $product_id, $slug_meta_key, $slug );
            update_post_meta( $product_id, $hash_meta_key, self::public_slug_hash( $slug ) );
        } else {
            delete_post_meta( $product_id, $slug_meta_key );
            delete_post_meta( $product_id, $hash_meta_key );
        }
        do_action( 'itkt_product_translation_saved', $product_id, $language, $all[ $language ] );
        return true;
    }

    /** Stable post-meta key used for one language's product search index. */
    public function search_meta_key( $language ) {
        return self::SEARCH_META_PREFIX . sanitize_key( (string) $language );
    }

    /**
     * Build a searchable, presentation-only text index for one physical WooCommerce product.
     * The index contains translated product content plus translated categories/tags/attributes.
     * It never changes the underlying WooCommerce product, stock, SKU or variation data.
     */
    public function build_search_index( $product_id, $language, $row = null ) {
        $product_id = absint( $product_id );
        $language   = sanitize_key( (string) $language );
        if ( ! $product_id || ! $language || 'product' !== get_post_type( $product_id ) ) { return ''; }
        if ( null === $row ) { $row = $this->get( $product_id, $language ); }
        $row = is_array( $row ) ? $row : array();

        $parts = array(
            (string) ( $row['title'] ?? '' ),
            (string) ( $row['short_description'] ?? '' ),
            (string) ( $row['description'] ?? '' ),
            (string) ( $row['seo_title'] ?? '' ),
            (string) ( $row['seo_description'] ?? '' ),
        );

        foreach ( (array) ( $row['custom_attributes'] ?? array() ) as $translated_attribute ) {
            if ( ! is_array( $translated_attribute ) ) { continue; }
            $parts[] = (string) ( $translated_attribute['label'] ?? '' );
            $parts[] = (string) ( $translated_attribute['values'] ?? '' );
        }

        // Product categories and tags are useful search terms in a multilingual storefront.
        if ( class_exists( 'ITKT_Term_Translations' ) ) {
            foreach ( array( 'product_cat', 'product_tag' ) as $taxonomy ) {
                foreach ( (array) wp_get_post_terms( $product_id, $taxonomy, array( 'fields' => 'all' ) ) as $term ) {
                    if ( ! $term instanceof WP_Term ) { continue; }
                    $translated_term = ITKT_Term_Translations::instance()->get( $term->term_id, $language );
                    if ( ! empty( $translated_term['name'] ) ) { $parts[] = (string) $translated_term['name']; }
                }
            }
        }

        if ( function_exists( 'wc_get_product' ) && class_exists( 'ITKT_Term_Translations' ) ) {
            $product = wc_get_product( $product_id );
            if ( $product ) {
                foreach ( (array) $product->get_attributes() as $attribute ) {
                    if ( ! is_a( $attribute, 'WC_Product_Attribute' ) || ! $attribute->is_taxonomy() ) { continue; }
                    $taxonomy = (string) $attribute->get_name();
                    $translated_label = ITKT_Term_Translations::instance()->get_attribute( $taxonomy, $language );
                    if ( $translated_label ) { $parts[] = (string) $translated_label; }
                    foreach ( (array) wc_get_product_terms( $product_id, $taxonomy, array( 'fields' => 'all' ) ) as $term ) {
                        if ( ! $term instanceof WP_Term ) { continue; }
                        $translated_term = ITKT_Term_Translations::instance()->get( $term->term_id, $language );
                        if ( ! empty( $translated_term['name'] ) ) { $parts[] = (string) $translated_term['name']; }
                    }
                }
            }
        }

        $clean = array();
        foreach ( $parts as $part ) {
            $part = strip_shortcodes( (string) $part );
            $part = html_entity_decode( wp_strip_all_tags( $part ), ENT_QUOTES, 'UTF-8' );
            $part = preg_replace( '/\s+/u', ' ', trim( $part ) );
            if ( ! $part ) { continue; }
            if ( function_exists( 'mb_strtolower' ) ) { $part = mb_strtolower( $part, 'UTF-8' ); }
            $clean[] = $part;
        }
        return implode( "\n", array_values( array_unique( $clean ) ) );
    }

    /** Update/remove one product's translated search index. */
    public function update_search_index( $product_id, $language, $row = null ) {
        $product_id = absint( $product_id );
        $language = sanitize_key( (string) $language );
        if ( ! $product_id || ! $language ) { return false; }
        if ( $language === ITKT_Languages::instance()->get_default_code() ) {
            delete_post_meta( $product_id, $this->search_meta_key( $language ) );
            return true;
        }
        $index = $this->build_search_index( $product_id, $language, $row );
        $key = $this->search_meta_key( $language );
        if ( '' === trim( $index ) ) { delete_post_meta( $product_id, $key ); }
        else { update_post_meta( $product_id, $key, $index ); }
        return true;
    }

    /** Rebuild translated product indexes when category/tag/attribute translations change. */
    public function invalidate_search_index_version() {
        delete_option( 'itkt_product_search_index_version' );
        $this->maybe_schedule_search_index_rebuild();
    }

    /** Schedule a one-time background migration for products saved before search indexes existed. */
    public function maybe_schedule_search_index_rebuild() {
        if ( get_option( 'itkt_product_search_index_version' ) === self::SEARCH_INDEX_VERSION ) { return; }
        $hook = 'itkt_rebuild_product_search_index';
        if ( function_exists( 'as_has_scheduled_action' ) && as_has_scheduled_action( $hook, null, 'itkt-translate' ) ) { return; }
        if ( function_exists( 'as_enqueue_async_action' ) ) {
            as_enqueue_async_action( $hook, array( 0 ), 'itkt-translate', true );
            return;
        }
        if ( ! wp_next_scheduled( $hook, array( 0 ) ) ) {
            wp_schedule_single_event( time() + 5, $hook, array( 0 ) );
        }
    }

    /** Process translated product search indexes in bounded batches. */
    public function rebuild_search_index_batch( $after_id = 0 ) {
        global $wpdb;
        $after_id = absint( $after_id );
        $limit = 80;
        $ids = $wpdb->get_col( $wpdb->prepare(
            "SELECT ID FROM {$wpdb->posts} WHERE post_type='product' AND post_status IN ('publish','draft','private','pending') AND ID > %d ORDER BY ID ASC LIMIT %d",
            $after_id,
            $limit
        ) );
        $targets = ITKT_Languages::instance()->get_active();
        unset( $targets[ ITKT_Languages::instance()->get_default_code() ] );
        foreach ( (array) $ids as $product_id ) {
            foreach ( $targets as $code => $language ) {
                $this->update_search_index( $product_id, $code, $this->get( $product_id, $code ) );
            }
        }
        if ( count( $ids ) >= $limit ) {
            $next = absint( end( $ids ) );
            if ( function_exists( 'as_enqueue_async_action' ) ) {
                as_enqueue_async_action( 'itkt_rebuild_product_search_index', array( $next ), 'itkt-translate', true );
            } else {
                wp_schedule_single_event( time() + 5, 'itkt_rebuild_product_search_index', array( $next ) );
            }
            return;
        }
        update_option( 'itkt_product_search_index_version', self::SEARCH_INDEX_VERSION, false );
        if ( class_exists( 'ITKT_Diagnostics' ) ) {
            ITKT_Diagnostics::instance()->log( 'success', 'search_index_rebuild', 'Produkt-Suchindex wurde vollständig aufgebaut.' );
        }
    }

    /** Whether the current WP_Query may legitimately return products. */
    private function query_can_include_products( $query ) {
        $post_type = $query instanceof WP_Query ? $query->get( 'post_type' ) : '';
        if ( empty( $post_type ) || 'any' === $post_type ) { return true; }
        if ( is_array( $post_type ) ) { return in_array( 'product', $post_type, true ); }
        return 'product' === $post_type;
    }

    /** Search terms as parsed by WordPress, with a conservative fallback. */
    private function query_search_terms( $query ) {
        $terms = array();
        if ( $query instanceof WP_Query && ! empty( $query->query_vars['search_terms'] ) && is_array( $query->query_vars['search_terms'] ) ) {
            $terms = $query->query_vars['search_terms'];
        }
        if ( ! $terms && $query instanceof WP_Query ) {
            $raw = trim( (string) $query->get( 's' ) );
            if ( $raw ) { $terms = preg_split( '/\s+/u', $raw ); }
        }
        $terms = array_values( array_filter( array_map( static function( $term ) {
            $term = trim( wp_strip_all_tags( (string) $term ) );
            return function_exists( 'mb_strtolower' ) ? mb_strtolower( $term, 'UTF-8' ) : strtolower( $term );
        }, (array) $terms ), 'strlen' ) );
        return array_slice( $terms, 0, 10 );
    }

    /**
     * Add the active language's compact product index as an OR branch to WordPress search.
     * This works for normal search pages and most WooCommerce/WoodMart AJAX searches using WP_Query.
     */
    public function filter_translated_product_search( $search, $query ) {
        global $wpdb;
        if ( ! $query instanceof WP_Query || '' === (string) $query->get( 's' ) ) { return $search; }
        if ( is_admin() && ! wp_doing_ajax() ) { return $search; }
        if ( ! $this->query_can_include_products( $query ) ) { return $search; }

        $language = ITKT_Languages::instance()->current_code();
        if ( ! $language || $language === ITKT_Languages::instance()->get_default_code() ) { return $search; }
        $terms = $this->query_search_terms( $query );
        if ( ! $terms ) { return $search; }

        $conditions = array();
        foreach ( $terms as $term ) {
            $conditions[] = $wpdb->prepare( 'itkt_si.meta_value LIKE %s', '%' . $wpdb->esc_like( $term ) . '%' );
        }
        if ( ! $conditions ) { return $search; }
        $meta_key = $this->search_meta_key( $language );
        $quoted_meta_key = $wpdb->prepare( '%s', $meta_key );
        $extra = " OR ({$wpdb->posts}.post_type='product' AND EXISTS (SELECT 1 FROM {$wpdb->postmeta} itkt_si WHERE itkt_si.post_id={$wpdb->posts}.ID AND itkt_si.meta_key={$quoted_meta_key} AND " . implode( ' AND ', $conditions ) . '))';

        // WordPress can append a separate post-password clause after its search expression.
        // Inject only into the search group, never into that suffix.
        $password_marker = " AND ({$wpdb->posts}.post_password = '')";
        $marker_pos = strpos( $search, $password_marker );
        $main = false !== $marker_pos ? substr( $search, 0, $marker_pos ) : $search;
        $suffix = false !== $marker_pos ? substr( $search, $marker_pos ) : '';
        $last = strrpos( $main, ')' );
        if ( false === $last ) { return $search; }
        $main = substr( $main, 0, $last ) . $extra . substr( $main, $last );
        return $main . $suffix;
    }

    public function source_hash( $product_id ) {
        $post = get_post( $product_id );
        if ( ! $post ) { return ''; }

        $custom = array();
        if ( function_exists( 'wc_get_product' ) ) {
            $product = wc_get_product( $product_id );
            if ( $product ) {
                foreach ( $product->get_attributes() as $key => $attribute ) {
                    if ( ! is_a( $attribute, 'WC_Product_Attribute' ) || $attribute->is_taxonomy() ) { continue; }
                    $custom[] = array(
                        'key'     => (string) $key,
                        'name'    => (string) $attribute->get_name(),
                        'options' => array_values( array_map( 'strval', $attribute->get_options() ) ),
                        'visible' => (bool) $attribute->get_visible(),
                    );
                }
            }
        }

        return hash( 'sha256', wp_json_encode( array(
            $post->post_title,
            $post->post_excerpt,
            $post->post_content,
            $custom,
        ) ) );
    }

    private function legacy_source_hash( $product_id ) {
        $post = get_post( $product_id );
        if ( ! $post ) { return ''; }
        return hash( 'sha256', $post->post_title . '|' . $post->post_excerpt . '|' . $post->post_content . '|' . $post->post_modified_gmt );
    }

    public function status( $product_id, $language ) {
        $data = $this->get( $product_id, $language );
        if ( ! $data || ( '' === trim( $data['title'] ?? '' ) && '' === trim( $data['short_description'] ?? '' ) && '' === trim( $data['description'] ?? '' ) ) ) {
            return 'missing';
        }
        if ( ! empty( $data['source_hash'] ) && $data['source_hash'] !== $this->source_hash( $product_id ) && $data['source_hash'] !== $this->legacy_source_hash( $product_id ) ) { return 'outdated'; }

        $post = get_post( $product_id );
        $required = array( 'title' );
        if ( $post && trim( $post->post_excerpt ) !== '' ) { $required[] = 'short_description'; }
        if ( $post && trim( $post->post_content ) !== '' ) { $required[] = 'description'; }
        foreach ( $required as $field ) {
            if ( trim( wp_strip_all_tags( $data[ $field ] ?? '' ) ) === '' ) { return 'partial'; }
        }

        // Custom non-taxonomy product attributes are product-owned text and therefore belong here.
        foreach ( $this->custom_attributes( $product_id ) as $key => $attribute ) {
            if ( empty( $attribute['visible'] ) ) { continue; }
            $translated = (array) ( $data['custom_attributes'][ $key ] ?? array() );
            if ( trim( $translated['label'] ?? '' ) === '' ) { return 'partial'; }
            if ( ! empty( $attribute['values'] ) && trim( $translated['values'] ?? '' ) === '' ) { return 'partial'; }
        }
        return 'complete';
    }

    public function custom_attributes( $product_id ) {
        $out = array();
        if ( ! function_exists( 'wc_get_product' ) ) { return $out; }
        $product = wc_get_product( $product_id );
        if ( ! $product ) { return $out; }

        foreach ( $product->get_attributes() as $attribute ) {
            if ( ! is_a( $attribute, 'WC_Product_Attribute' ) || $attribute->is_taxonomy() ) { continue; }
            $key = 'custom_' . substr( md5( (string) $attribute->get_name() ), 0, 12 );
            $out[ $key ] = array(
                'name'    => (string) $attribute->get_name(),
                'values'  => array_values( array_map( 'strval', $attribute->get_options() ) ),
                'visible' => (bool) $attribute->get_visible(),
                'variation'=> (bool) $attribute->get_variation(),
            );
        }
        return $out;
    }

    private function active_translation( $product_id ) {
        if ( is_admin() && ! wp_doing_ajax() ) { return array(); }
        $language = ITKT_Languages::instance()->current_code();
        if ( $language === ITKT_Languages::instance()->get_default_code() ) { return array(); }
        return $this->get( $product_id, $language );
    }

    public function filter_name( $value, $product ) {
        $data = $this->active_translation( $product->get_id() );
        return ! empty( $data['title'] ) ? $data['title'] : $value;
    }

    /** Return the translated parent product title for the active storefront language. */
    public function translated_title( $product_id, $language = '' ) {
        $product_id = absint( $product_id );
        if ( ! $product_id ) { return ''; }
        if ( ! $language ) { $language = ITKT_Languages::instance()->current_code(); }
        $language = sanitize_key( (string) $language );
        if ( ! $language || $language === ITKT_Languages::instance()->get_default_code() ) {
            $post = get_post( $product_id );
            return $post ? (string) $post->post_title : '';
        }
        $data = $this->get( $product_id, $language );
        if ( ! empty( $data['title'] ) ) { return (string) $data['title']; }
        $post = get_post( $product_id );
        return $post ? (string) $post->post_title : '';
    }

    /**
     * WooCommerce cart/mini-cart/checkout compatibility. The cart item can contain a variation
     * object whose own ID has no ITKT translation row; translations always belong to the one
     * physical parent product ID.
     */
    /**
     * Return a presentation-only clone with the translated name for cart/checkout consumers.
     * Some WoodMart templates and WooCommerce Store API code read the cart item's WC_Product
     * object directly and never call woocommerce_cart_item_name. Never mutate the real cart
     * product object; changing the clone only affects display.
     */
    public function filter_cart_item_product( $product, $cart_item, $cart_item_key = '' ) {
        if ( is_admin() && ! wp_doing_ajax() ) { return $product; }
        if ( ! is_object( $product ) || ! method_exists( $product, 'get_id' ) || ! method_exists( $product, 'set_name' ) ) { return $product; }
        $language = ITKT_Languages::instance()->current_code();
        if ( $language === ITKT_Languages::instance()->get_default_code() ) { return $product; }
        $product_id = absint( is_array( $cart_item ) ? ( $cart_item['product_id'] ?? 0 ) : 0 );
        if ( ! $product_id && method_exists( $product, 'get_parent_id' ) && $product->get_parent_id() ) { $product_id = absint( $product->get_parent_id() ); }
        if ( ! $product_id ) { $product_id = absint( $product->get_id() ); }
        $translated = $this->translated_title( $product_id, $language );
        if ( '' === trim( $translated ) ) { return $product; }
        try {
            $copy = clone $product;
            $copy->set_name( $translated );
            return $copy;
        } catch ( Throwable $e ) {
            return $product;
        }
    }

    public function filter_cart_item_name( $name, $cart_item, $cart_item_key = '' ) {
        if ( is_admin() && ! wp_doing_ajax() ) { return $name; }
        if ( ! is_array( $cart_item ) ) { return $name; }
        $product_id = absint( $cart_item['product_id'] ?? 0 );
        if ( ! $product_id && ! empty( $cart_item['data'] ) && is_object( $cart_item['data'] ) ) {
            $product = $cart_item['data'];
            if ( method_exists( $product, 'get_parent_id' ) && $product->get_parent_id() ) { $product_id = absint( $product->get_parent_id() ); }
            elseif ( method_exists( $product, 'get_id' ) ) { $product_id = absint( $product->get_id() ); }
        }
        if ( ! $product_id ) { return $name; }
        $language = ITKT_Languages::instance()->current_code();
        if ( $language === ITKT_Languages::instance()->get_default_code() ) { return $name; }
        $translated = $this->translated_title( $product_id, $language );
        if ( '' === trim( $translated ) ) { return $name; }

        // Some themes pass an already-linked HTML string through this filter. Replace only the
        // visible anchor content and preserve href/classes/data attributes. Otherwise return text.
        if ( false !== stripos( (string) $name, '<a' ) ) {
            return preg_replace_callback( '#(<a\b[^>]*>)(.*?)(</a>)#is', function( $m ) use ( $translated ) {
                return $m[1] . esc_html( $translated ) . $m[3];
            }, (string) $name, 1 );
        }
        return $translated;
    }

    /** Translate variation/custom attribute labels and values shown below cart item names. */
    public function filter_cart_item_data( $item_data, $cart_item ) {
        if ( is_admin() && ! wp_doing_ajax() ) { return $item_data; }
        if ( ! is_array( $item_data ) || ! is_array( $cart_item ) || empty( $item_data ) ) { return $item_data; }
        $language = ITKT_Languages::instance()->current_code();
        if ( $language === ITKT_Languages::instance()->get_default_code() ) { return $item_data; }

        $product_id = absint( $cart_item['product_id'] ?? 0 );
        if ( ! $product_id ) { return $item_data; }
        $product = function_exists( 'wc_get_product' ) ? wc_get_product( $product_id ) : null;
        $variation = isset( $cart_item['variation'] ) && is_array( $cart_item['variation'] ) ? $cart_item['variation'] : array();
        $translated_product = $this->get( $product_id, $language );

        foreach ( $item_data as $idx => $row ) {
            if ( ! is_array( $row ) ) { continue; }
            $row_key = wp_strip_all_tags( (string) ( $row['key'] ?? '' ) );
            $row_value = wp_strip_all_tags( (string) ( $row['value'] ?? ( $row['display'] ?? '' ) ) );

            foreach ( $variation as $variation_key => $variation_value ) {
                $taxonomy = preg_replace( '/^attribute_/', '', (string) $variation_key );
                $taxonomy = (string) $taxonomy;
                if ( '' === $taxonomy ) { continue; }

                if ( 0 === strpos( $taxonomy, 'pa_' ) ) {
                    $source_label = ITKT_Term_Translations::instance()->attribute_source_label( $taxonomy );
                    $translated_label = ITKT_Term_Translations::instance()->get_attribute( $taxonomy, $language );
                    $term = get_term_by( 'slug', (string) $variation_value, $taxonomy );
                    if ( ! $term || is_wp_error( $term ) ) { $term = get_term_by( 'name', (string) $row_value, $taxonomy ); }
                    $translated_value = '';
                    $source_value = '';
                    if ( $term && ! is_wp_error( $term ) ) {
                        $source_value = (string) $term->name;
                        $term_translation = ITKT_Term_Translations::instance()->get( $term->term_id, $language );
                        $translated_value = (string) ( $term_translation['name'] ?? '' );
                    }
                    if ( $this->cart_row_matches_attribute( $row_key, $source_label, $translated_label, $row_value, $source_value, (string) $variation_value ) ) {
                        if ( $translated_label ) { $item_data[ $idx ]['key'] = $translated_label; }
                        if ( $translated_value ) {
                            $item_data[ $idx ]['value'] = $translated_value;
                            $item_data[ $idx ]['display'] = esc_html( $translated_value );
                        }
                        break;
                    }
                    continue;
                }

                // Product-owned custom attribute: map it by source attribute name and option order.
                if ( ! $product ) { continue; }
                $source_name = wc_attribute_label( $taxonomy, $product );
                $matched_key = '';
                foreach ( $this->custom_attributes( $product_id ) as $custom_key => $attribute ) {
                    if ( sanitize_title( (string) $attribute['name'] ) === sanitize_title( $taxonomy ) || (string) $attribute['name'] === (string) $source_name ) {
                        $matched_key = $custom_key;
                        break;
                    }
                }
                if ( ! $matched_key || empty( $translated_product['custom_attributes'][ $matched_key ] ) ) { continue; }
                $translated = (array) $translated_product['custom_attributes'][ $matched_key ];
                if ( ! $this->cart_row_matches_attribute( $row_key, $source_name, (string) ( $translated['label'] ?? '' ), $row_value, (string) $variation_value, (string) $variation_value ) ) { continue; }
                if ( ! empty( $translated['label'] ) ) { $item_data[ $idx ]['key'] = $translated['label']; }
                if ( ! empty( $translated['values'] ) ) {
                    $source_options = array_values( array_map( 'strval', $this->custom_attributes( $product_id )[ $matched_key ]['values'] ?? array() ) );
                    $translated_options = array_map( 'trim', explode( ',', (string) $translated['values'] ) );
                    $position = array_search( (string) $variation_value, $source_options, true );
                    if ( false === $position ) { $position = array_search( (string) $row_value, $source_options, true ); }
                    if ( false !== $position && isset( $translated_options[ $position ] ) && '' !== $translated_options[ $position ] ) {
                        $item_data[ $idx ]['value'] = $translated_options[ $position ];
                        $item_data[ $idx ]['display'] = esc_html( $translated_options[ $position ] );
                    }
                }
                break;
            }
        }
        return $item_data;
    }

    /** Translate product names in customer order details while browsing the selected language. */
    public function filter_order_item_name( $name, $item, $is_visible = false ) {
        if ( is_admin() && ! wp_doing_ajax() ) { return $name; }
        if ( ! is_object( $item ) || ! method_exists( $item, 'get_product_id' ) ) { return $name; }
        $product_id = absint( $item->get_product_id() );
        if ( ! $product_id ) { return $name; }
        $language = ITKT_Languages::instance()->current_code();
        if ( $language === ITKT_Languages::instance()->get_default_code() ) { return $name; }
        $translated = $this->translated_title( $product_id, $language );
        if ( '' === trim( $translated ) ) { return $name; }

        // WooCommerce order/account templates commonly pass an already-linked name here.
        // Replace only the visible anchor label so the translated frontend keeps the product link.
        if ( false !== stripos( (string) $name, '<a' ) ) {
            return preg_replace_callback( '#(<a\b[^>]*>)(.*?)(</a>)#is', function( $m ) use ( $translated ) {
                return $m[1] . esc_html( $translated ) . $m[3];
            }, (string) $name, 1 );
        }
        return $translated;
    }

    public function filter_order_meta_key( $display_key, $meta, $item ) {
        if ( is_admin() && ! wp_doing_ajax() ) { return $display_key; }
        $language = ITKT_Languages::instance()->current_code();
        if ( $language === ITKT_Languages::instance()->get_default_code() ) { return $display_key; }
        $key = is_object( $meta ) && isset( $meta->key ) ? (string) $meta->key : '';
        $taxonomy = preg_replace( '/^attribute_/', '', $key );
        if ( 0 === strpos( (string) $taxonomy, 'pa_' ) ) {
            $translated = ITKT_Term_Translations::instance()->get_attribute( $taxonomy, $language );
            if ( $translated ) { return $translated; }
        }

        // Custom (non-taxonomy) variation attributes are stored on order items as plain text
        // keys such as "Packung". They are not covered by the pa_* taxonomy path above.
        // Match the order meta back to the parent product's custom attribute definition and use
        // the already-saved per-product translation. This is presentation-only: order meta and
        // the historic order snapshot are never rewritten.
        $custom = $this->translated_custom_order_attribute( $item, $meta, $display_key, '', $language );
        if ( ! empty( $custom['label'] ) ) { return $custom['label']; }
        return $display_key;
    }

    public function filter_order_meta_value( $display_value, $meta, $item ) {
        if ( is_admin() && ! wp_doing_ajax() ) { return $display_value; }
        $language = ITKT_Languages::instance()->current_code();
        if ( $language === ITKT_Languages::instance()->get_default_code() ) { return $display_value; }
        $key = is_object( $meta ) && isset( $meta->key ) ? (string) $meta->key : '';
        $value = is_object( $meta ) && isset( $meta->value ) ? (string) $meta->value : wp_strip_all_tags( (string) $display_value );
        $taxonomy = preg_replace( '/^attribute_/', '', $key );
        if ( 0 === strpos( (string) $taxonomy, 'pa_' ) ) {
            $term = get_term_by( 'slug', $value, $taxonomy );
            if ( ! $term || is_wp_error( $term ) ) { $term = get_term_by( 'name', wp_strip_all_tags( (string) $display_value ), $taxonomy ); }
            if ( $term && ! is_wp_error( $term ) ) {
                $translated = ITKT_Term_Translations::instance()->get( $term->term_id, $language );
                if ( ! empty( $translated['name'] ) ) { return $translated['name']; }
            }
        }

        $custom = $this->translated_custom_order_attribute( $item, $meta, '', $display_value, $language );
        if ( ! empty( $custom['value'] ) ) { return $custom['value']; }
        return $display_value;
    }

    /**
     * Resolve a custom product attribute used in an order-line meta row and return translated
     * presentation text. This closes the gap between cart translations and historic order/email
     * output for non-taxonomy attributes such as "Packung: 1000 Gramm".
     */
    private function translated_custom_order_attribute( $item, $meta, $display_key, $display_value, $language ) {
        if ( ! is_object( $item ) || ! method_exists( $item, 'get_product_id' ) ) { return array(); }
        $product_id = absint( $item->get_product_id() );
        if ( ! $product_id ) { return array(); }

        $translated_product = $this->get( $product_id, $language );
        if ( empty( $translated_product['custom_attributes'] ) ) { return array(); }
        $source_attributes = $this->custom_attributes( $product_id );
        if ( ! $source_attributes ) { return array(); }

        $meta_key = is_object( $meta ) && isset( $meta->key ) ? (string) $meta->key : (string) $display_key;
        $meta_value = is_object( $meta ) && isset( $meta->value ) ? (string) $meta->value : wp_strip_all_tags( (string) $display_value );
        $norm = static function( $value ) {
            $value = html_entity_decode( wp_strip_all_tags( (string) $value ), ENT_QUOTES, 'UTF-8' );
            $value = preg_replace( '/\s+/u', ' ', trim( $value ) );
            return function_exists( 'mb_strtolower' ) ? mb_strtolower( $value, 'UTF-8' ) : strtolower( $value );
        };
        $needle_key = $norm( preg_replace( '/^attribute_/', '', $meta_key ) );

        foreach ( $source_attributes as $attribute_key => $source ) {
            $source_name = (string) ( $source['name'] ?? '' );
            if ( '' === $source_name ) { continue; }
            $translated = (array) ( $translated_product['custom_attributes'][ $attribute_key ] ?? array() );
            if ( ! $translated ) { continue; }

            $candidate_names = array_filter( array(
                $norm( $source_name ),
                $norm( sanitize_title( $source_name ) ),
                $norm( (string) ( $translated['label'] ?? '' ) ),
            ) );
            if ( $needle_key && ! in_array( $needle_key, $candidate_names, true ) ) { continue; }

            $out = array();
            if ( '' !== trim( (string) ( $translated['label'] ?? '' ) ) ) {
                $out['label'] = (string) $translated['label'];
            }

            $source_values = array_values( array_map( 'strval', (array) ( $source['values'] ?? array() ) ) );
            $target_values = array_map( 'trim', explode( ',', (string) ( $translated['values'] ?? '' ) ) );
            if ( $meta_value && $source_values && $target_values ) {
                $needle_value = $norm( $meta_value );
                foreach ( $source_values as $index => $source_value ) {
                    if ( $needle_value === $norm( $source_value ) && isset( $target_values[ $index ] ) && '' !== $target_values[ $index ] ) {
                        $out['value'] = $target_values[ $index ];
                        break;
                    }
                }
            }
            return $out;
        }
        return array();
    }

    private function cart_row_matches_attribute( $row_key, $source_label, $translated_label, $row_value, $source_value, $raw_value ) {
        $norm = static function( $v ) { return strtolower( trim( wp_strip_all_tags( (string) $v ) ) ); };
        $key = $norm( $row_key );
        $labels = array_filter( array_map( $norm, array( $source_label, $translated_label ) ) );
        if ( $key && in_array( $key, $labels, true ) ) { return true; }
        $value = $norm( $row_value );
        foreach ( array_filter( array_map( $norm, array( $source_value, $raw_value ) ) ) as $candidate ) {
            if ( $value && ( $value === $candidate || false !== strpos( $value, $candidate ) ) ) { return true; }
        }
        return false;
    }

    /**
     * Runtime translation map for products currently present in the WooCommerce cart.
     *
     * WooCommerce Cart/Checkout Blocks render large parts of their UI in JavaScript through the
     * Store API and can bypass classic PHP filters such as woocommerce_cart_item_name. Exposing a
     * small map for products that are actually in the current cart lets the frontend keep product
     * titles and displayed attribute labels/values in the selected ITKT language without cloning
     * products or changing the underlying cart data.
     */
    /** Avoid creating/loading a WooCommerce session for anonymous runtime-map probes. */
    private function has_frontend_cart_session_hint() {
        foreach ( array_keys( (array) $_COOKIE ) as $cookie_name ) {
            $cookie_name = (string) $cookie_name;
            if ( 'woocommerce_items_in_cart' === $cookie_name || 'woocommerce_cart_hash' === $cookie_name || 0 === strpos( $cookie_name, 'wp_woocommerce_session_' ) ) {
                return true;
            }
        }
        return false;
    }

    public function frontend_cart_translation_map( $language = '' ) {
        $language = sanitize_key( (string) $language );
        if ( ! $language ) { $language = ITKT_Languages::instance()->current_code(); }
        if ( ! $language || $language === ITKT_Languages::instance()->get_default_code() ) { return array(); }
        if ( ! function_exists( 'WC' ) || ! WC() ) { return array(); }
        if ( ( ! isset( WC()->cart ) || ! WC()->cart ) && function_exists( 'wc_load_cart' ) ) {
            // The public runtime endpoint must not initialize WooCommerce sessions for crawlers or
            // unrelated anonymous requests. Only load a cart when WooCommerce/cart cookies show
            // that this browser already has storefront session state.
            if ( ! $this->has_frontend_cart_session_hint() ) { return array(); }
            try { wc_load_cart(); } catch ( Throwable $e ) { /* keep runtime read-only */ }
        }
        if ( ! isset( WC()->cart ) || ! WC()->cart ) { return array(); }

        $out = array();
        foreach ( (array) WC()->cart->get_cart() as $cart_item ) {
            $product_id = absint( $cart_item['product_id'] ?? 0 );
            if ( ! $product_id || isset( $out[ $product_id ] ) ) { continue; }
            $post = get_post( $product_id );
            $product = function_exists( 'wc_get_product' ) ? wc_get_product( $product_id ) : null;
            if ( ! $post || ! $product ) { continue; }

            $translated = $this->get( $product_id, $language );
            $title = trim( (string) ( $translated['title'] ?? '' ) );
            $replacements = array();

            foreach ( (array) $product->get_attributes() as $attribute ) {
                if ( ! is_a( $attribute, 'WC_Product_Attribute' ) ) { continue; }

                if ( $attribute->is_taxonomy() ) {
                    $taxonomy = (string) $attribute->get_name();
                    $source_label = ITKT_Term_Translations::instance()->attribute_source_label( $taxonomy );
                    $target_label = ITKT_Term_Translations::instance()->get_attribute( $taxonomy, $language );
                    if ( $source_label && $target_label && $source_label !== $target_label ) {
                        $replacements[ $source_label ] = $target_label;
                    }

                    $terms = wc_get_product_terms( $product_id, $taxonomy, array( 'fields' => 'all' ) );
                    foreach ( (array) $terms as $term ) {
                        if ( ! $term instanceof WP_Term ) { continue; }
                        $term_translation = ITKT_Term_Translations::instance()->get( $term->term_id, $language );
                        $target_value = trim( (string) ( $term_translation['name'] ?? '' ) );
                        if ( $target_value && $target_value !== (string) $term->name ) {
                            $replacements[ (string) $term->name ] = $target_value;
                        }
                    }
                    continue;
                }

                $key = 'custom_' . substr( md5( (string) $attribute->get_name() ), 0, 12 );
                $row = (array) ( $translated['custom_attributes'][ $key ] ?? array() );
                $source_label = (string) $attribute->get_name();
                $target_label = trim( (string) ( $row['label'] ?? '' ) );
                if ( $target_label && $source_label !== $target_label ) { $replacements[ $source_label ] = $target_label; }

                $source_values = array_values( array_map( 'strval', (array) $attribute->get_options() ) );
                $target_values = array_map( 'trim', explode( ',', (string) ( $row['values'] ?? '' ) ) );
                foreach ( $source_values as $index => $source_value ) {
                    $target_value = isset( $target_values[ $index ] ) ? trim( (string) $target_values[ $index ] ) : '';
                    if ( $source_value && $target_value && $source_value !== $target_value ) {
                        $replacements[ $source_value ] = $target_value;
                    }
                }
            }

            if ( ! $title && ! $replacements ) { continue; }
            $out[ $product_id ] = array(
                'id'           => $product_id,
                'sourceTitle'  => (string) $post->post_title,
                'title'        => $title,
                'replacements' => $replacements,
            );
        }
        return array_values( $out );
    }

    public function filter_variation_name( $value, $variation ) {
        if ( ! is_object( $variation ) || ! method_exists( $variation, 'get_parent_id' ) ) { return $value; }
        $parent_id = absint( $variation->get_parent_id() );
        if ( ! $parent_id ) { return $value; }
        $data = $this->active_translation( $parent_id );
        if ( empty( $data['title'] ) ) { return $value; }

        $parent = get_post( $parent_id );
        if ( $parent && $parent->post_title && str_starts_with( (string) $value, (string) $parent->post_title ) ) {
            return $data['title'] . substr( (string) $value, strlen( (string) $parent->post_title ) );
        }
        return $value;
    }

    public function filter_description( $value, $product ) {
        $data = $this->active_translation( $product->get_id() );
        return ! empty( $data['description'] ) ? $data['description'] : $value;
    }

    public function filter_short_description( $value, $product ) {
        $data = $this->active_translation( $product->get_id() );
        return ! empty( $data['short_description'] ) ? $data['short_description'] : $value;
    }

    /**
     * Translate the short description in WooCommerce/ WoodMart templates that use
     * apply_filters( 'woocommerce_short_description', $post->post_excerpt ).
     */
    public function filter_woocommerce_short_description( $excerpt ) {
        if ( is_admin() && ! wp_doing_ajax() ) { return $excerpt; }

        // IMPORTANT: This filter is also invoked by some WoodMart archive/header builders.
        // Never infer a product from the global $post on Shop/category/search pages: during an
        // archive render the global post can temporarily be the first product in the loop and
        // its translated excerpt would then leak into the Shop page header. The WooCommerce
        // product getter already translates card/loop short descriptions, so this compatibility
        // hook is intentionally limited to the real single-product request.
        if ( ! function_exists( 'is_product' ) || ! is_product() ) { return $excerpt; }

        $product_id = absint( get_queried_object_id() );
        if ( ! $product_id || 'product' !== get_post_type( $product_id ) ) { return $excerpt; }

        $data = $this->active_translation( $product_id );
        return ! empty( $data['short_description'] ) ? $data['short_description'] : $excerpt;
    }

    /**
     * Extra compatibility for themes that render a single product excerpt through the_excerpt.
     * Keep this singular-only for the same reason as filter_woocommerce_short_description():
     * archive builders can temporarily expose a product as the global post.
     */
    public function filter_the_excerpt( $excerpt ) {
        if ( is_admin() && ! wp_doing_ajax() ) { return $excerpt; }
        if ( ! function_exists( 'is_product' ) || ! is_product() ) { return $excerpt; }
        $product_id = absint( get_queried_object_id() );
        if ( ! $product_id || 'product' !== get_post_type( $product_id ) ) { return $excerpt; }
        $data = $this->active_translation( $product_id );
        return ! empty( $data['short_description'] ) ? $data['short_description'] : $excerpt;
    }

    public function filter_post_title( $title, $post_id ) {
        if ( is_admin() || 'product' !== get_post_type( $post_id ) ) { return $title; }
        $data = $this->active_translation( $post_id );
        return ! empty( $data['title'] ) ? $data['title'] : $title;
    }

    public function filter_post_excerpt( $excerpt, $post ) {
        $post_id = $post instanceof WP_Post ? $post->ID : absint( $post );
        if ( is_admin() || 'product' !== get_post_type( $post_id ) ) { return $excerpt; }

        // get_the_excerpt() is used very broadly by themes/page builders. On WooCommerce
        // archives only the WC product object getter should translate loop excerpts. Limiting
        // this generic WordPress filter to the queried single product prevents an arbitrary
        // loop product excerpt from replacing Shop/archive page content.
        if ( ! function_exists( 'is_product' ) || ! is_product() || absint( get_queried_object_id() ) !== $post_id ) {
            return $excerpt;
        }

        $data = $this->active_translation( $post_id );
        return ! empty( $data['short_description'] ) ? $data['short_description'] : $excerpt;
    }

    public function filter_post_content( $content ) {
        if ( is_admin() ) { return $content; }
        $post_id = get_the_ID();
        if ( ! $post_id || 'product' !== get_post_type( $post_id ) ) { return $content; }
        $data = $this->active_translation( $post_id );
        return ! empty( $data['description'] ) ? $data['description'] : $content;
    }

    public function filter_variation_option_name( $option, $term = null, $taxonomy = '', $product = null ) {
        if ( is_admin() && ! wp_doing_ajax() ) { return $option; }
        $language = ITKT_Languages::instance()->current_code();
        if ( $language === ITKT_Languages::instance()->get_default_code() ) { return $option; }

        // WooCommerce Store API / Cart & Checkout Blocks pass the term and taxonomy here.
        // Translate taxonomy variation values directly so Blocks do not need a DOM-only fallback.
        if ( $term instanceof WP_Term ) {
            $translated_term = ITKT_Term_Translations::instance()->get( $term->term_id, $language );
            if ( ! empty( $translated_term['name'] ) ) { return (string) $translated_term['name']; }
        } elseif ( $taxonomy && 0 === strpos( (string) $taxonomy, 'pa_' ) ) {
            $found = get_term_by( 'slug', (string) $option, (string) $taxonomy );
            if ( ! $found || is_wp_error( $found ) ) { $found = get_term_by( 'name', (string) $option, (string) $taxonomy ); }
            if ( $found && ! is_wp_error( $found ) ) {
                $translated_term = ITKT_Term_Translations::instance()->get( $found->term_id, $language );
                if ( ! empty( $translated_term['name'] ) ) { return (string) $translated_term['name']; }
            }
        }

        // Resolve the one physical parent product. Store API supplies $product; normal product
        // pages can fall back to the queried product ID.
        $product_id = 0;
        if ( is_object( $product ) && method_exists( $product, 'get_id' ) ) {
            $product_id = absint( $product->get_id() );
            if ( method_exists( $product, 'get_parent_id' ) && $product->get_parent_id() ) {
                $product_id = absint( $product->get_parent_id() );
            }
        }
        if ( ! $product_id && function_exists( 'is_singular' ) && is_singular( 'product' ) ) {
            $product_id = absint( get_queried_object_id() );
        }
        if ( ! $product_id ) { return $option; }

        $data = $this->get( $product_id, $language );
        if ( empty( $data['custom_attributes'] ) ) { return $option; }

        foreach ( $this->custom_attributes( $product_id ) as $key => $attribute ) {
            if ( empty( $attribute['variation'] ) || empty( $data['custom_attributes'][ $key ]['values'] ) ) { continue; }
            if ( $taxonomy ) {
                $source_name = (string) ( $attribute['name'] ?? '' );
                $tax_norm = sanitize_title( preg_replace( '/^attribute_/', '', (string) $taxonomy ) );
                if ( $tax_norm && sanitize_title( $source_name ) !== $tax_norm ) { continue; }
            }
            $translated_values = array_map( 'trim', explode( ',', (string) $data['custom_attributes'][ $key ]['values'] ) );
            $source_values = array_values( array_map( 'strval', (array) ( $attribute['values'] ?? array() ) ) );
            foreach ( $source_values as $index => $source_value ) {
                if ( (string) $source_value === (string) $option && isset( $translated_values[ $index ] ) && '' !== $translated_values[ $index ] ) {
                    return $translated_values[ $index ];
                }
            }
        }
        return $option;
    }

    /**
     * Translate custom, non-taxonomy attributes in the Additional information table without
     * changing the underlying WooCommerce attribute values used for variations or filtering.
     */
    public function filter_display_product_attributes( $rows, $product ) {
        if ( is_admin() || ! is_object( $product ) || ! method_exists( $product, 'get_id' ) || ! is_array( $rows ) ) { return $rows; }
        $data = $this->active_translation( $product->get_id() );
        if ( empty( $data['custom_attributes'] ) ) { return $rows; }

        $custom = $this->custom_attributes( $product->get_id() );
        foreach ( $product->get_attributes() as $attribute ) {
            if ( ! is_a( $attribute, 'WC_Product_Attribute' ) || $attribute->is_taxonomy() ) { continue; }
            $key = 'custom_' . substr( md5( (string) $attribute->get_name() ), 0, 12 );
            if ( empty( $data['custom_attributes'][ $key ] ) ) { continue; }
            $translated = (array) $data['custom_attributes'][ $key ];

            // WooCommerce commonly keys custom attribute rows by sanitized attribute name.
            $row_key = 'attribute_' . sanitize_title_with_dashes( $attribute->get_name() );
            if ( ! isset( $rows[ $row_key ] ) ) {
                foreach ( array_keys( $rows ) as $possible ) {
                    if ( sanitize_title( (string) $possible ) === $row_key ) { $row_key = $possible; break; }
                }
            }
            if ( ! isset( $rows[ $row_key ] ) ) { continue; }

            if ( ! empty( $translated['label'] ) ) { $rows[ $row_key ]['label'] = esc_html( $translated['label'] ); }
            if ( ! empty( $translated['values'] ) ) { $rows[ $row_key ]['value'] = esc_html( $translated['values'] ); }
        }
        return $rows;
    }
}
