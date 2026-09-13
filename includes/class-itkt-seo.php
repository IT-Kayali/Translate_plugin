<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * SEO, translated public slugs and alternate-language discovery.
 * This layer is intentionally optional and keeps the translation core independent
 * from Yoast, Rank Math or any particular theme.
 */
class ITKT_SEO {
    private static $instance = null;
    const POST_SLUG_META = '_itkt_public_slug';
    const POST_TITLE_META = '_itkt_seo_title';
    const POST_DESC_META = '_itkt_seo_description';
    private $building_term_url = false;

    public static function instance() {
        if ( null === self::$instance ) { self::$instance = new self(); }
        return self::$instance;
    }

    private function __construct() {
        add_action( 'init', array( $this, 'register_rewrites' ), 11 );
        add_filter( 'query_vars', array( $this, 'query_vars' ) );
        add_action( 'template_redirect', array( $this, 'maybe_render_sitemap' ), 0 );
        add_action( 'template_redirect', array( $this, 'redirect_to_translated_slug' ), 8 );
        add_filter( 'robots_txt', array( $this, 'robots_txt' ), 20, 2 );

        add_action( 'wp_head', array( $this, 'output_hreflang' ), 1 );
        add_filter( 'pre_get_document_title', array( $this, 'core_title' ), 20 );
        add_action( 'wp_head', array( $this, 'output_core_seo' ), 2 );
        add_filter( 'get_canonical_url', array( $this, 'core_canonical' ), 20, 2 );

        if ( ! defined( 'WPSEO_VERSION' ) && ! defined( 'RANK_MATH_VERSION' ) ) { remove_action( 'wp_head', 'rel_canonical' ); }

        // Yoast SEO basic integration.
        add_filter( 'wpseo_title', array( $this, 'seo_title_filter' ), 20 );
        add_filter( 'wpseo_metadesc', array( $this, 'seo_description_filter' ), 20 );
        add_filter( 'wpseo_canonical', array( $this, 'canonical_filter' ), 20 );

        // Rank Math basic integration.
        add_filter( 'rank_math/frontend/title', array( $this, 'seo_title_filter' ), 20 );
        add_filter( 'rank_math/frontend/description', array( $this, 'seo_description_filter' ), 20 );
        add_filter( 'rank_math/frontend/canonical', array( $this, 'canonical_filter' ), 20 );

        // Keep internal language duplicates out of the core WordPress post sitemap.
        add_filter( 'wp_sitemaps_posts_query_args', array( $this, 'core_sitemap_posts_query_args' ), 20, 2 );
    }

    public function register_rewrites() {
        add_rewrite_tag( '%itkt_translation_sitemap%', '1' );
        add_rewrite_rule( '^itkt-translations-sitemap\.xml$', 'index.php?itkt_translation_sitemap=1', 'top' );
    }

    public function query_vars( $vars ) {
        $vars[] = 'itkt_translation_sitemap';
        return array_unique( $vars );
    }

    public function save_post_seo( $post_id, $data ) {
        $post_id = absint( $post_id );
        if ( ! $post_id ) { return; }
        $slug = sanitize_title( wp_unslash( $data['slug'] ?? '' ) );
        $title = sanitize_text_field( wp_unslash( $data['seo_title'] ?? '' ) );
        $description = sanitize_textarea_field( wp_unslash( $data['seo_description'] ?? '' ) );
        if ( $slug ) { update_post_meta( $post_id, self::POST_SLUG_META, $slug ); } else { delete_post_meta( $post_id, self::POST_SLUG_META ); }
        if ( $title ) { update_post_meta( $post_id, self::POST_TITLE_META, $title ); } else { delete_post_meta( $post_id, self::POST_TITLE_META ); }
        if ( $description ) { update_post_meta( $post_id, self::POST_DESC_META, $description ); } else { delete_post_meta( $post_id, self::POST_DESC_META ); }
    }

    public function post_seo( $post_id ) {
        return array(
            'slug' => (string) get_post_meta( $post_id, self::POST_SLUG_META, true ),
            'seo_title' => (string) get_post_meta( $post_id, self::POST_TITLE_META, true ),
            'seo_description' => (string) get_post_meta( $post_id, self::POST_DESC_META, true ),
        );
    }

    private function source_post_id( $post_id ) {
        $post_id = absint( $post_id );
        if ( ! $post_id ) { return 0; }
        $type = get_post_type( $post_id );
        if ( ! in_array( $type, ITKT_Content::instance()->supported_post_types(), true ) ) { return $post_id; }
        $default = ITKT_Languages::instance()->get_default_code();
        $lang = sanitize_key( (string) get_post_meta( $post_id, '_itkt_language', true ) );
        if ( ! $lang || $lang === $default ) { return $post_id; }
        $translations = ITKT_Content::instance()->translations_for( $post_id );
        return isset( $translations[ $default ] ) ? absint( $translations[ $default ]->ID ) : $post_id;
    }

    private function target_post_id( $source_id, $code, $published_only = false ) {
        $source_id = $this->source_post_id( $source_id );
        $type = get_post_type( $source_id );
        if ( ! in_array( $type, ITKT_Content::instance()->supported_post_types(), true ) ) { return $source_id; }
        if ( $code === ITKT_Languages::instance()->get_default_code() ) { return $source_id; }
        $translations = ITKT_Content::instance()->translations_for( $source_id );
        if ( empty( $translations[ $code ] ) ) { return 0; }
        if ( $published_only && 'publish' !== $translations[ $code ]->post_status ) { return 0; }
        return absint( $translations[ $code ]->ID );
    }

    public function translated_slug_for_post( $post_id, $code ) {
        $post_id = absint( $post_id );
        $code = sanitize_key( $code );
        $source_id = $this->source_post_id( $post_id );
        $post = get_post( $source_id );
        if ( ! $post ) { return ''; }
        $default = ITKT_Languages::instance()->get_default_code();
        if ( $code === $default ) { return (string) $post->post_name; }

        if ( 'product' === $post->post_type && class_exists( 'ITKT_Product_Translations' ) ) {
            $slug = ITKT_Product_Translations::instance()->explicit_slug( $source_id, $code );
            if ( $slug ) { return $slug; }
            return (string) $post->post_name;
        }

        $target_id = $this->target_post_id( $source_id, $code );
        if ( $target_id ) {
            $stored = (string) get_post_meta( $target_id, self::POST_SLUG_META, true );
            if ( $stored ) { return sanitize_title( $stored ); }
        }
        return (string) $post->post_name;
    }

    public function translated_post_path( $post_id, $code ) {
        $source_id = $this->source_post_id( $post_id );
        $post = get_post( $source_id );
        if ( ! $post ) { return ''; }
        if ( 'page' === $post->post_type && $source_id === absint( get_option( 'page_on_front' ) ) ) { return ''; }

        $path = ITKT_Frontend::instance()->source_path_for_post( $source_id );
        if ( '' === $path && ! ( 'page' === $post->post_type && $source_id === absint( get_option( 'page_on_front' ) ) ) ) { return ''; }
        $path = $this->strip_language_prefix( $path );
        $slug = $this->translated_slug_for_post( $source_id, $code );
        if ( ! $slug ) { return $path; }

        // Hierarchical pages: translate every page segment when a linked translation exists.
        if ( 'page' === $post->post_type ) {
            $ids = array_reverse( get_post_ancestors( $source_id ) );
            $ids[] = $source_id;
            $segments = array();
            foreach ( $ids as $id ) { $segments[] = $this->translated_slug_for_post( $id, $code ); }
            return trim( implode( '/', array_filter( $segments ) ), '/' );
        }

        $parts = explode( '/', trim( $path, '/' ) );
        if ( $parts ) { $parts[ count( $parts ) - 1 ] = $slug; }
        return trim( implode( '/', $parts ), '/' );
    }

    public function is_building_term_url() { return (bool) $this->building_term_url; }

    public function translated_term_url( $term, $code ) {
        if ( is_numeric( $term ) ) { $term = get_term( absint( $term ) ); }
        if ( ! $term instanceof WP_Term ) { return ''; }
        $this->building_term_url = true;
        $url = get_term_link( $term );
        $this->building_term_url = false;
        if ( is_wp_error( $url ) || ! $url ) { return ''; }
        $path = $this->strip_language_prefix( $this->relative_home_path( $url ) );
        $data = class_exists( 'ITKT_Term_Translations' ) ? ITKT_Term_Translations::instance()->get( $term->term_id, $code ) : array();
        $slug = ! empty( $data['slug'] ) ? sanitize_title( $data['slug'] ) : $term->slug;
        if ( $code === ITKT_Languages::instance()->get_default_code() ) { $slug = $term->slug; }
        $parts = explode( '/', trim( $path, '/' ) );
        if ( $parts ) { $parts[ count( $parts ) - 1 ] = $slug; }
        return ITKT_Frontend::instance()->public_language_url( $code, implode( '/', $parts ), false );
    }

    /** Resolve translated slugs before WordPress tries the source-language URL. */
    public function resolve_translated_path( $path, $code ) {
        $path = trim( rawurldecode( (string) $path ), '/' );
        $code = sanitize_key( $code );
        if ( '' === $path || $code === ITKT_Languages::instance()->get_default_code() ) { return false; }
        $last = sanitize_title( rawurldecode( basename( $path ) ) );
        if ( ! $last ) { return false; }

        // Linked page/post language copies use a dedicated slug meta, never their internal WP slug.
        $ids = get_posts( array(
            'post_type' => ITKT_Content::instance()->supported_post_types(),
            'post_status' => 'publish',
            'numberposts' => 20,
            'fields' => 'ids',
            'meta_query' => array(
                array( 'key' => '_itkt_language', 'value' => $code ),
                array( 'key' => self::POST_SLUG_META, 'value' => $last ),
            ),
            'suppress_filters' => true,
        ) );
        foreach ( $ids as $id ) {
            $source = $this->source_post_id( $id );
            if ( trim( rawurldecode( $this->translated_post_path( $source, $code ) ), '/' ) === $path ) {
                return array( 'type' => 'post', 'id' => absint( $id ) );
            }
        }

        // Products remain one physical product. Translated slugs are stored as readable
        // Unicode in our metadata; legacy builds may still have sanitize_title() percent-encoded
        // values, so resolve both representations during the migration period.
        $product_leaf_raw = rawurldecode( basename( $path ) );
        $product_leaf = class_exists( 'ITKT_Product_Translations' ) ? ITKT_Product_Translations::normalize_public_slug( $product_leaf_raw ) : $last;
        $product_slug_values = array_values( array_unique( array_filter( array( $product_leaf, $last ) ) ) );
        $product_ids = array();
        if ( class_exists( 'ITKT_Product_Translations' ) && $product_leaf ) {
            $hash = ITKT_Product_Translations::public_slug_hash( $product_leaf );
            if ( $hash ) {
                $product_ids = get_posts( array(
                    'post_type' => 'product', 'post_status' => 'publish', 'numberposts' => 20, 'fields' => 'ids',
                    'meta_key' => '_itkt_slug_hash_' . $code, 'meta_value' => $hash, 'suppress_filters' => true,
                ) );
            }
        }
        if ( ! $product_ids ) {
            foreach ( $product_slug_values as $product_slug_value ) {
                $found = get_posts( array(
                    'post_type' => 'product', 'post_status' => 'publish', 'numberposts' => 20, 'fields' => 'ids',
                    'meta_key' => '_itkt_slug_' . $code, 'meta_value' => $product_slug_value, 'suppress_filters' => true,
                ) );
                $product_ids = array_merge( $product_ids, (array) $found );
            }
        }
        foreach ( array_unique( array_map( 'absint', $product_ids ) ) as $id ) {
            if ( trim( rawurldecode( $this->translated_post_path( $id, $code ) ), '/' ) === $path ) {
                return array( 'type' => 'post', 'id' => absint( $id ) );
            }
        }

        if ( class_exists( 'ITKT_Term_Translations' ) ) {
            $taxonomies = array_filter( get_taxonomies( array( 'public' => true ) ), function( $tax ) {
                return 'product_cat' === $tax || 'product_tag' === $tax || 0 === strpos( (string) $tax, 'pa_' );
            } );
            foreach ( $taxonomies as $taxonomy ) {
                $terms = get_terms( array( 'taxonomy' => $taxonomy, 'hide_empty' => false, 'number' => 20, 'meta_key' => '_itkt_slug_' . $code, 'meta_value' => $last ) );
                if ( is_wp_error( $terms ) ) { continue; }
                foreach ( $terms as $term ) {
                    $candidate = $this->translated_term_url( $term, $code );
                    if ( trim( rawurldecode( $this->relative_home_path( $candidate ) ), '/' ) === trim( $code . '/' . $path, '/' ) ) {
                        return array( 'type' => 'term', 'term_id' => $term->term_id, 'taxonomy' => $taxonomy, 'slug' => $term->slug );
                    }
                }
            }
        }
        return false;
    }

    public function current_seo_data() {
        $code = ITKT_Languages::instance()->current_code();
        $default = ITKT_Languages::instance()->get_default_code();
        if ( is_singular() ) {
            $id = absint( get_queried_object_id() );
            if ( 'product' === get_post_type( $id ) && $code !== $default && class_exists( 'ITKT_Product_Translations' ) ) {
                $data = ITKT_Product_Translations::instance()->get( $id, $code );
                return array( 'title' => $data['seo_title'] ?? '', 'description' => $data['seo_description'] ?? '' );
            }
            return array( 'title' => get_post_meta( $id, self::POST_TITLE_META, true ), 'description' => get_post_meta( $id, self::POST_DESC_META, true ) );
        }
        if ( is_tax() || is_category() || is_tag() ) {
            $term = get_queried_object();
            if ( $term instanceof WP_Term && $code !== $default && class_exists( 'ITKT_Term_Translations' ) ) {
                $data = ITKT_Term_Translations::instance()->get( $term->term_id, $code );
                return array( 'title' => $data['seo_title'] ?? '', 'description' => $data['seo_description'] ?? '' );
            }
        }
        return array( 'title' => '', 'description' => '' );
    }

    public function canonical_url() {
        $code = ITKT_Languages::instance()->current_code();
        if ( is_singular() ) {
            return ITKT_Frontend::instance()->language_url_for_post( get_queried_object_id(), $code, false );
        }
        if ( is_tax() || is_category() || is_tag() ) {
            $term = get_queried_object();
            if ( $term instanceof WP_Term ) {
                $url = $this->translated_term_url( $term, $code );
                if ( $url ) { return $url; }
            }
        }
        if ( function_exists( 'is_shop' ) && is_shop() && function_exists( 'wc_get_page_permalink' ) ) {
            return ITKT_Frontend::instance()->language_url_from_url( wc_get_page_permalink( 'shop' ), $code, false );
        }
        if ( is_front_page() || is_home() ) { return ITKT_Frontend::instance()->public_language_url( $code, '', false ); }
        return ITKT_Frontend::instance()->language_url( $code );
    }

    public function alternate_urls() {
        $out = array();
        foreach ( ITKT_Languages::instance()->get_active() as $code => $lang ) {
            $url = '';
            if ( is_singular() ) {
                $id = absint( get_queried_object_id() );
                $source = $this->source_post_id( $id );
                if ( in_array( get_post_type( $source ), ITKT_Content::instance()->supported_post_types(), true ) ) {
                    $target = $this->target_post_id( $source, $code, true );
                    if ( ! $target && $code !== ITKT_Languages::instance()->get_default_code() ) { continue; }
                } elseif ( 'product' === get_post_type( $source ) && $code !== ITKT_Languages::instance()->get_default_code() ) {
                    $data = ITKT_Product_Translations::instance()->get( $source, $code );
                    if ( empty( $data['title'] ) ) { continue; }
                }
                $url = ITKT_Frontend::instance()->language_url_for_post( $source, $code, false );
            } elseif ( is_tax() || is_category() || is_tag() ) {
                $term = get_queried_object();
                if ( ! $term instanceof WP_Term ) { continue; }
                if ( $code !== ITKT_Languages::instance()->get_default_code() ) {
                    $data = class_exists( 'ITKT_Term_Translations' ) ? ITKT_Term_Translations::instance()->get( $term->term_id, $code ) : array();
                    if ( empty( $data['name'] ) ) { continue; }
                }
                $url = $this->translated_term_url( $term, $code );
            } elseif ( is_front_page() || is_home() ) {
                $url = ITKT_Frontend::instance()->public_language_url( $code, '', false );
            }
            if ( $url ) { $out[ $code ] = $url; }
        }
        return $out;
    }

    public function output_hreflang() {
        if ( is_admin() || is_feed() || is_404() ) { return; }
        $urls = $this->alternate_urls();
        if ( count( $urls ) < 2 ) { return; }
        foreach ( $urls as $code => $url ) {
            echo '<link rel="alternate" hreflang="' . esc_attr( $code ) . '" href="' . esc_url( $url ) . '" />' . "\n";
        }
        $default = ITKT_Languages::instance()->get_default_code();
        if ( ! empty( $urls[ $default ] ) ) { echo '<link rel="alternate" hreflang="x-default" href="' . esc_url( $urls[ $default ] ) . '" />' . "\n"; }
    }

    public function seo_title_filter( $title ) {
        if ( is_admin() ) { return $title; }
        $data = $this->current_seo_data();
        return ! empty( $data['title'] ) ? $data['title'] : $title;
    }

    public function seo_description_filter( $description ) {
        if ( is_admin() ) { return $description; }
        $data = $this->current_seo_data();
        return ! empty( $data['description'] ) ? $data['description'] : $description;
    }

    public function canonical_filter( $url ) {
        if ( is_admin() ) { return $url; }
        $canonical = $this->canonical_url();
        return $canonical ?: $url;
    }

    public function core_title( $title ) {
        // Yoast/Rank Math own the title pipeline when active.
        if ( defined( 'WPSEO_VERSION' ) || defined( 'RANK_MATH_VERSION' ) ) { return $title; }
        return $this->seo_title_filter( $title );
    }

    public function core_canonical( $url, $post = null ) {
        if ( defined( 'WPSEO_VERSION' ) || defined( 'RANK_MATH_VERSION' ) ) { return $url; }
        return $this->canonical_filter( $url );
    }

    public function output_core_seo() {
        if ( is_admin() || is_feed() || is_404() || defined( 'WPSEO_VERSION' ) || defined( 'RANK_MATH_VERSION' ) ) { return; }
        $data = $this->current_seo_data();
        if ( ! empty( $data['description'] ) ) { echo '<meta name="description" content="' . esc_attr( $data['description'] ) . '" />' . "\n"; }
        $canonical = $this->canonical_url();
        if ( $canonical ) { echo '<link rel="canonical" href="' . esc_url( $canonical ) . '" />' . "\n"; }
    }

    public function core_sitemap_posts_query_args( $args, $post_type ) {
        if ( in_array( $post_type, ITKT_Content::instance()->supported_post_types(), true ) ) {
            $default = ITKT_Languages::instance()->get_default_code();
            $existing = isset( $args['meta_query'] ) && is_array( $args['meta_query'] ) ? $args['meta_query'] : array();
            $existing[] = array( 'relation' => 'OR', array( 'key' => '_itkt_language', 'compare' => 'NOT EXISTS' ), array( 'key' => '_itkt_language', 'value' => $default ) );
            $args['meta_query'] = $existing;
        }
        return $args;
    }


    public function redirect_to_translated_slug() {
        if ( is_admin() || wp_doing_ajax() || is_preview() || is_feed() || is_404() || headers_sent() ) { return; }
        $code = ITKT_Languages::instance()->current_code();
        if ( $code === ITKT_Languages::instance()->get_default_code() ) { return; }
        if ( ! ( is_singular() || is_tax() || is_category() || is_tag() ) ) { return; }
        $canonical = $this->canonical_url();
        if ( ! $canonical ) { return; }
        $request_uri = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
        $request_path = untrailingslashit( (string) wp_parse_url( $request_uri, PHP_URL_PATH ) );
        $canonical_path = untrailingslashit( (string) wp_parse_url( $canonical, PHP_URL_PATH ) );
        if ( rawurldecode( $request_path ) === rawurldecode( $canonical_path ) ) { return; }
        $query = (string) wp_parse_url( $request_uri, PHP_URL_QUERY );
        if ( $query ) {
            $args = array(); wp_parse_str( $query, $args );
            unset( $args['itkt_lang'], $args['lang'] );
            if ( $args ) { $canonical = add_query_arg( $args, $canonical ); }
        }
        wp_safe_redirect( $canonical, 301 );
        exit;
    }

    public function robots_txt( $output, $public ) {
        if ( $public ) {
            $url = trailingslashit( (string) get_option( 'home' ) ) . 'itkt-translations-sitemap.xml';
            if ( false === strpos( $output, $url ) ) { $output .= "\nSitemap: " . $url . "\n"; }
        }
        return $output;
    }

    public function maybe_render_sitemap() {
        if ( ! get_query_var( 'itkt_translation_sitemap' ) ) { return; }
        status_header( 200 );
        nocache_headers();
        header( 'Content-Type: application/xml; charset=UTF-8' );
        echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">' . "\n";
        foreach ( $this->sitemap_urls() as $row ) {
            echo '<url><loc>' . esc_url( $row['loc'] ) . '</loc>';
            if ( ! empty( $row['lastmod'] ) ) { echo '<lastmod>' . esc_html( $row['lastmod'] ) . '</lastmod>'; }
            echo '</url>' . "\n";
        }
        echo '</urlset>';
        exit;
    }

    private function sitemap_urls() {
        $rows = array();
        $active = ITKT_Languages::instance()->get_active();
        $default = ITKT_Languages::instance()->get_default_code();
        $nondefault = array_filter( array_keys( $active ), function( $code ) use ( $default ) { return $code !== $default; } );
        if ( ! $nondefault ) { return $rows; }

        foreach ( ITKT_Content::instance()->supported_post_types() as $type ) {
            $sources = ITKT_Content::instance()->get_items( $type, 5000 );
            foreach ( $sources as $source ) {
                if ( 'publish' !== $source->post_status ) { continue; }
                $translations = ITKT_Content::instance()->translations_for( $source->ID );
                foreach ( $nondefault as $code ) {
                    if ( empty( $translations[ $code ] ) || 'publish' !== $translations[ $code ]->post_status ) { continue; }
                    $rows[] = array( 'loc' => ITKT_Frontend::instance()->language_url_for_post( $source->ID, $code, false ), 'lastmod' => get_post_modified_time( 'c', true, $translations[ $code ] ) );
                }
            }
        }

        if ( ITKT_Plugin::module_enabled( 'woocommerce' ) && post_type_exists( 'product' ) ) {
            $ids = get_posts( array( 'post_type' => 'product', 'post_status' => 'publish', 'numberposts' => 10000, 'fields' => 'ids', 'suppress_filters' => true ) );
            foreach ( $ids as $id ) {
                foreach ( $nondefault as $code ) {
                    $data = ITKT_Product_Translations::instance()->get( $id, $code );
                    if ( empty( $data['title'] ) ) { continue; }
                    $rows[] = array( 'loc' => ITKT_Frontend::instance()->language_url_for_post( $id, $code, false ), 'lastmod' => get_post_modified_time( 'c', true, $id ) );
                }
            }
            foreach ( array( 'product_cat', 'product_tag' ) as $taxonomy ) {
                if ( ! taxonomy_exists( $taxonomy ) ) { continue; }
                $terms = get_terms( array( 'taxonomy' => $taxonomy, 'hide_empty' => false ) );
                if ( is_wp_error( $terms ) ) { continue; }
                foreach ( $terms as $term ) {
                    foreach ( $nondefault as $code ) {
                        $data = ITKT_Term_Translations::instance()->get( $term->term_id, $code );
                        if ( empty( $data['name'] ) ) { continue; }
                        $rows[] = array( 'loc' => $this->translated_term_url( $term, $code ), 'lastmod' => '' );
                    }
                }
            }
        }
        return $rows;
    }

    private function strip_language_prefix( $path ) {
        $path = trim( (string) $path, '/' );
        if ( ! $path ) { return ''; }
        $parts = explode( '/', $path );
        if ( isset( ITKT_Languages::instance()->catalog()[ sanitize_key( $parts[0] ) ] ) ) { array_shift( $parts ); }
        return trim( implode( '/', $parts ), '/' );
    }

    private function relative_home_path( $url ) {
        $path = (string) wp_parse_url( $url, PHP_URL_PATH );
        $home = (string) wp_parse_url( (string) get_option( 'home' ), PHP_URL_PATH );
        $home = '/' . trim( $home, '/' );
        if ( '/' !== $home && 0 === strpos( $path, $home ) ) { $path = substr( $path, strlen( $home ) ); }
        return trim( $path, '/' );
    }
}
