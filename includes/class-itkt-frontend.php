<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class ITKT_Frontend {
    private static $instance = null;
    private $in_title_filter = false;
    private $in_home_url_filter = false;
    private $in_permalink_filter = false;

    public static function instance() {
        if ( null === self::$instance ) { self::$instance = new self(); }
        return self::$instance;
    }

    private function __construct() {
        // We are instantiated during init, so register rewrite rules immediately.
        $this->register_rewrites();

        add_filter( 'query_vars', array( $this, 'query_vars' ) );
        add_filter( 'request', array( $this, 'resolve_language_request' ), 5 );
        // Final fail-safe after WordPress has parsed rewrite variables. This is especially
        // important for Unicode/Arabic WooCommerce slugs, which some server/rewrite stacks
        // hand to WordPress in a differently encoded representation.
        // WooCommerce My Account endpoints need to survive our virtual /LANG/ directory.
        // A dedicated parse_request rescue reads the real browser URL and writes WooCommerce's
        // canonical endpoint query vars directly. This runs after WC_Query::parse_request(), so
        // it is independent of server rewrite quirks and of translated/duplicated page slugs.
        add_action( 'parse_request', array( $this, 'force_woocommerce_account_endpoint_request' ), 90 );
        // Final runtime rescue for My Account. Some stacks/themes rebuild or normalize query vars
        // after parse_request. Re-apply the endpoint from REQUEST_URI immediately after the main
        // query and again before WooCommerce renders account navigation/content.
        add_action( 'wp', array( $this, 'force_woocommerce_account_endpoint_runtime' ), 0 );
        add_action( 'woocommerce_account_navigation', array( $this, 'force_woocommerce_account_endpoint_runtime' ), 0 );
        add_action( 'woocommerce_account_content', array( $this, 'force_woocommerce_account_endpoint_runtime' ), 0 );
        // Absolute content-level fallback. WooCommerce normally dispatches the endpoint from
        // $wp->query_vars inside woocommerce_account_content(). Some multilingual/theme stacks
        // still collapse that state to the dashboard after the main query. For a real /LANG/
        // My Account endpoint URL, dispatch the canonical WooCommerce endpoint action directly.
        add_action( 'woocommerce_account_content', array( $this, 'dispatch_woocommerce_account_endpoint_content' ), 1 );
        add_action( 'parse_request', array( $this, 'force_unicode_product_request' ), 99 );
        // Independent main-query rescue. Some managed-host rewrite stacks can let the generic
        // language directory resolve while still discarding the translated product capture.
        // Inspect the real browser URL before SQL and force the physical WooCommerce product.
        add_action( 'pre_get_posts', array( $this, 'force_translated_product_main_query' ), 0 );
        add_filter( 'redirect_canonical', array( $this, 'prevent_wp_canonical_redirect' ), 10, 2 );
        add_action( 'template_redirect', array( $this, 'remember_requested_language' ), 0 );
        add_action( 'template_redirect', array( $this, 'redirect_saved_language_home' ), 1 );
        add_action( 'template_redirect', array( $this, 'redirect_legacy_language_urls' ), 2 );
        add_action( 'template_redirect', array( $this, 'repair_product_language_404' ), 3 );
        add_action( 'template_redirect', array( $this, 'redirect_product_without_language_slug' ), 4 );

        add_shortcode( 'itkt_language_switcher', array( $this, 'shortcode_switcher' ) );
        add_filter( 'language_attributes', array( $this, 'language_attributes' ) );
        add_filter( 'body_class', array( $this, 'body_classes' ) );
        // Do not switch WordPress's global locale per storefront language. Native catalogs are
        // read explicitly by ITKT_Strings, while RTL/layout direction is handled separately.

        add_filter( 'home_url', array( $this, 'translated_home_url' ), 99, 4 );
        add_filter( 'page_link', array( $this, 'translated_page_link' ), 99, 3 );
        add_filter( 'post_link', array( $this, 'translated_post_link' ), 99, 3 );
        add_filter( 'post_type_link', array( $this, 'translated_post_type_link' ), 99, 4 );

        add_filter( 'theme_mod_nav_menu_locations', array( $this, 'menu_locations' ), 99 );
        add_filter( 'wp_nav_menu_args', array( $this, 'nav_menu_args' ), 99 );
        add_filter( 'wp_nav_menu_objects', array( $this, 'nav_menu_objects' ), 20, 2 );
        add_filter( 'nav_menu_link_attributes', array( $this, 'nav_menu_link_attributes' ), 20, 4 );

        // Register the JavaScript i18n bridge before WooCommerce Blocks enqueue/render.
        add_action( 'wp_enqueue_scripts', array( $this, 'i18n_assets' ), 1 );
        add_action( 'wp_enqueue_scripts', array( $this, 'assets' ) );
        // Read-only runtime string endpoint. It intentionally works for guests as well, because
        // translated storefronts must be able to refresh global strings even when the page HTML
        // itself was served from a full-page cache. Only already-published translation maps are returned.
        add_action( 'wp_ajax_itkt_runtime_strings', array( $this, 'ajax_runtime_strings' ) );
        add_action( 'wp_ajax_nopriv_itkt_runtime_strings', array( $this, 'ajax_runtime_strings' ) );
        add_action( 'wp', array( $this, 'apply_runtime_text_direction' ), 1 );
        $this->apply_runtime_text_direction();
    }

    /**
     * Public language URLs are virtual directories. The actual translated Elementor/WordPress
     * post may internally have a slug such as "home-2"; visitors never need to see that slug.
     */
    public function register_rewrites() {
        $codes = array_keys( ITKT_Languages::instance()->catalog() );
        $codes = array_values( array_filter( array_map( 'sanitize_key', $codes ) ) );
        if ( ! $codes ) { return; }

        $pattern = implode( '|', array_map( function( $code ) { return preg_quote( $code, '#' ); }, $codes ) );
        add_rewrite_tag( '%itkt_lang%', '(' . $pattern . ')' );
        add_rewrite_tag( '%itkt_path%', '(.+?)' );
        add_rewrite_tag( '%itkt_product_slug%', '([^/]+)' );

        // My Account endpoints are real WooCommerce states behind one system page. Give them
        // dedicated language-prefixed rewrite rules before the generic /LANG/... catch-all so
        // WordPress receives the endpoint query var as early as possible.
        $this->register_woocommerce_account_rewrites();

        // Register product routes before the generic language catch-all. Using a dedicated query
        // variable makes /en/product/test/ and /ar/product/من-السلوى-نوجا/ real product requests
        // instead of asking WordPress/WooCommerce to interpret a virtual translated post_name.
        foreach ( $this->product_base_segments() as $product_base ) {
            $base_regex = preg_quote( $product_base, '#' );
            add_rewrite_rule(
                '^(' . $pattern . ')/' . $base_regex . '/([^/]+)/?$',
                'index.php?itkt_lang=$matches[1]&itkt_product_slug=$matches[2]&post_type=product',
                'top'
            );
        }

        add_rewrite_rule( '^(' . $pattern . ')/?$', 'index.php?itkt_lang=$matches[1]&itkt_path=__itkt_home__', 'top' );
        add_rewrite_rule( '^(' . $pattern . ')/(.+?)/?$', 'index.php?itkt_lang=$matches[1]&itkt_path=$matches[2]', 'top' );
    }

    /**
     * Register concrete /LANG/my-account/ENDPOINT/ routes before the generic language router.
     *
     * This is intentionally redundant with the request/runtime rescues. On normal WordPress
     * installations these rules are sufficient on their own; the later rescues cover managed
     * hosting and theme/plugin stacks that normalize rewrite query vars unexpectedly.
     */
    private function register_woocommerce_account_rewrites() {
        if ( ! ITKT_Plugin::module_enabled( 'woocommerce' ) || ! function_exists( 'wc_get_page_id' ) ) { return; }

        $account_id = absint( wc_get_page_id( 'myaccount' ) );
        if ( ! $account_id || 'publish' !== get_post_status( $account_id ) ) { return; }

        $source_id   = $this->default_source_post_id( $account_id );
        $source_path = trim( (string) $this->post_source_path( $source_id ), '/' );
        $active      = ITKT_Languages::instance()->get_active();
        $endpoint_map = $this->woocommerce_account_endpoint_map();

        foreach ( array_keys( $active ) as $code ) {
            $code = sanitize_key( (string) $code );
            if ( ! $code ) { continue; }

            $bases = array();
            if ( $source_path ) { $bases[] = $source_path; }

            $target_id = $this->target_post_id( $source_id, $code );
            if ( $target_id && $target_id !== $source_id ) {
                $target_path = trim( (string) $this->post_source_path( $target_id ), '/' );
                if ( $target_path ) { $bases[] = $target_path; }
            }

            $bases = array_values( array_unique( array_filter( array_map( function( $value ) {
                return trim( rawurldecode( (string) $value ), '/' );
            }, $bases ) ) ) );

            foreach ( $bases as $base ) {
                $base_regex = preg_quote( $base, '#' );
                $lang_regex = preg_quote( $code, '#' );

                foreach ( $endpoint_map as $endpoint_key => $endpoint_slug ) {
                    $endpoint_key  = sanitize_key( (string) $endpoint_key );
                    $endpoint_slug = trim( rawurldecode( (string) $endpoint_slug ), '/' );
                    if ( ! $endpoint_key || '' === $endpoint_slug ) { continue; }

                    $endpoint_regex = preg_quote( $endpoint_slug, '#' );
                    $base_query = 'index.php?page_id=' . $account_id . '&itkt_lang=' . rawurlencode( $code ) . '&' . $endpoint_key . '=';

                    // Endpoints such as view-order/123 and edit-address/billing carry a value.
                    add_rewrite_rule(
                        '^' . $lang_regex . '/' . $base_regex . '/' . $endpoint_regex . '/(.+?)/?$',
                        $base_query . '$matches[1]',
                        'top'
                    );
                    // Endpoints such as orders, downloads and edit-account have no value.
                    add_rewrite_rule(
                        '^' . $lang_regex . '/' . $base_regex . '/' . $endpoint_regex . '/?$',
                        $base_query,
                        'top'
                    );
                }
            }
        }
    }

    public function query_vars( $vars ) {
        $vars[] = 'itkt_lang';
        $vars[] = 'itkt_path';
        $vars[] = 'itkt_product_slug';
        // Dedicated My Account rewrites below use WooCommerce's canonical endpoint keys. Make
        // them public query vars even if WC_Query registers its rewrite endpoints later on init.
        if ( ITKT_Plugin::module_enabled( 'woocommerce' ) ) {
            foreach ( array_keys( $this->woocommerce_account_endpoint_map() ) as $endpoint_key ) {
                $endpoint_key = sanitize_key( (string) $endpoint_key );
                if ( $endpoint_key ) { $vars[] = $endpoint_key; }
            }
        }
        return array_unique( $vars );
    }

    /**
     * Resolve /en/source-path/ to the real translated post ID. For WooCommerce products there
     * is deliberately no duplicate: the same product ID is loaded and its text fields are
     * translated by the product translation layer.
     */
    public function resolve_language_request( $query_vars ) {
        if ( empty( $query_vars['itkt_lang'] ) ) { return $query_vars; }

        $code   = sanitize_key( (string) $query_vars['itkt_lang'] );
        $active = ITKT_Languages::instance()->get_active();
        if ( empty( $active[ $code ] ) ) {
            $query_vars['error'] = '404';
            unset( $query_vars['itkt_path'], $query_vars['itkt_product_slug'] );
            return $query_vars;
        }

        // Dedicated translated-product rewrite. Resolve the public language slug to the single
        // physical WooCommerce product before any generic page/taxonomy routing can interfere.
        if ( ! empty( $query_vars['itkt_product_slug'] ) ) {
            $slug = $this->decode_public_path_component( (string) $query_vars['itkt_product_slug'] );
            unset( $query_vars['itkt_product_slug'], $query_vars['itkt_path'] );
            $product_id = $this->resolve_product_id_from_public_path( 'product/' . $slug, $code );
            if ( $product_id ) { return $this->query_for_post( $query_vars, $product_id ); }
            $query_vars['error'] = '404';
            return $query_vars;
        }

        $path = isset( $query_vars['itkt_path'] ) ? trim( (string) $query_vars['itkt_path'], '/' ) : '';
        // REQUEST_URI is the source of truth for non-Latin paths. Depending on the web server,
        // rewrite variables can arrive encoded once, encoded twice, or already decoded. Reading
        // the actual request path here makes Arabic/Unicode routing deterministic.
        $uri_path = $this->requested_path_after_language_prefix();
        if ( '' !== $uri_path ) { $path = $uri_path; }
        unset( $query_vars['itkt_path'] );

        if ( '__itkt_home__' === $path || '' === $path ) {
            if ( 'page' === get_option( 'show_on_front' ) ) {
                $source_id = absint( get_option( 'page_on_front' ) );
                if ( $source_id ) {
                    $target_id = $this->target_post_id( $source_id, $code );
                    if ( ! $target_id ) {
                        $settings = get_option( 'itkt_settings', array() );
                        if ( 'default' === ( $settings['fallback_mode'] ?? 'default' ) ) { $target_id = $source_id; }
                    }
                    if ( $target_id ) {
                        return $this->query_for_post( $query_vars, $target_id );
                    }
                    $query_vars['error'] = '404';
                    return $query_vars;
                }
            }
            // Blog-style front page: leave WordPress on the home query, language stays in query vars.
            return $query_vars;
        }

        // WooCommerce My Account endpoints (orders, downloads, addresses, account details,
        // logout, view-order, ... ) live *behind* the My Account page. Our generic language
        // directory router must therefore resolve the base page and preserve the endpoint query
        // variable instead of treating /en/my-account/orders/ as a separate WordPress page.
        $account_query = $this->woocommerce_account_query_for_path( $query_vars, $path, $code );
        if ( $account_query ) { return $account_query; }

        $woocommerce_archive = $this->woocommerce_archive_query_for_path( $query_vars, $path );
        if ( $woocommerce_archive ) { return $woocommerce_archive; }

        // V0.7: translated public slugs are resolved before falling back to source-language paths.
        if ( class_exists( 'ITKT_SEO' ) ) {
            $translated = ITKT_SEO::instance()->resolve_translated_path( $path, $code );
            if ( is_array( $translated ) && ! empty( $translated['type'] ) ) {
                if ( 'post' === $translated['type'] && ! empty( $translated['id'] ) ) {
                    return $this->query_for_post( $query_vars, absint( $translated['id'] ) );
                }
                if ( 'term' === $translated['type'] && ! empty( $translated['taxonomy'] ) && ! empty( $translated['slug'] ) ) {
                    foreach ( array( 'p', 'page_id', 'name', 'pagename', 'post_type', 'error' ) as $key ) { unset( $query_vars[ $key ] ); }
                    $query_vars['taxonomy'] = sanitize_key( $translated['taxonomy'] );
                    $query_vars['term'] = sanitize_title( $translated['slug'] );
                    return $query_vars;
                }
            }
        }

        // WooCommerce products are one physical post across all languages. Resolve the
        // requested public product path independently from WordPress's normal permalink parser.
        // This also supports legacy translations created before the fast _itkt_slug_LANG index
        // existed: the resolver can recover the slug from the serialized translation payload
        // and lazily rebuild the missing index.
        $product_id = $this->resolve_product_id_from_public_path( $path, $code );
        if ( $product_id ) { return $this->query_for_post( $query_vars, $product_id ); }

        $source_url = user_trailingslashit( $this->raw_home_url( '/' . ltrim( $path, '/' ) ) );
        $source_id  = absint( url_to_postid( $source_url ) );

        // Extra fallback for simple page paths when url_to_postid cannot resolve a customized setup.
        if ( ! $source_id ) {
            $page = get_page_by_path( rawurldecode( $path ), OBJECT, array( 'page', 'post' ) );
            if ( $page ) { $source_id = absint( $page->ID ); }
        }

        if ( ! $source_id ) {
            $taxonomy_query = $this->taxonomy_query_for_path( $query_vars, $path );
            if ( $taxonomy_query ) { return $taxonomy_query; }
            $query_vars['error'] = '404';
            return $query_vars;
        }

        $source_id = $this->default_source_post_id( $source_id );
        $target_id = $this->target_post_id( $source_id, $code );
        if ( ! $target_id ) {
            $settings = get_option( 'itkt_settings', array() );
            if ( 'default' === ( $settings['fallback_mode'] ?? 'default' ) ) {
                $target_id = $source_id;
            } else {
                $query_vars['error'] = '404';
                return $query_vars;
            }
        }

        return $this->query_for_post( $query_vars, $target_id );
    }

    /**
     * Final fail-safe for WooCommerce My Account endpoints behind a virtual language prefix.
     *
     * WordPress has already applied rewrite rules when this action runs. We intentionally ignore
     * the parsed endpoint state and inspect REQUEST_URI again, then write the canonical WooCommerce
     * query variable directly into $wp->query_vars. This makes /en/.../orders/ and /ar/.../orders/
     * work even when a host/server collapses the generic ITKT catch-all to the base account page.
     */
    public function force_woocommerce_account_endpoint_request( $wp ) {
        if ( is_admin() || ! is_object( $wp ) || ! ITKT_Plugin::module_enabled( 'woocommerce' ) || ! function_exists( 'wc_get_page_id' ) ) { return; }

        $code = $this->requested_language_code();
        if ( ! $code ) { $code = ITKT_Languages::instance()->current_code(); }
        if ( ! $code ) { return; }

        // v0.12.10: a short-lived rescue marker is carried on non-default-language account links.
        // It is independent from rewrite rules and therefore survives stacks that collapse
        // /LANG/my-account/orders/ back to the My Account dashboard query before WooCommerce runs.
        $marker = $this->woocommerce_account_endpoint_marker_state();
        if ( ! empty( $marker['key'] ) ) {
            $account_id = absint( wc_get_page_id( 'myaccount' ) );
            if ( ! $account_id ) { return; }
            $resolved = $this->query_for_post( (array) $wp->query_vars, $account_id );
            $resolved[ $marker['key'] ] = (string) ( $marker['value'] ?? '' );
            $resolved['itkt_lang'] = sanitize_key( (string) $code );
            unset( $resolved['error'], $resolved['itkt_path'] );
            $wp->query_vars = $resolved;
            return;
        }

        $path = $this->requested_path_after_language_prefix();
        if ( ! $path ) { return; }

        $resolved = $this->woocommerce_account_query_for_path( (array) $wp->query_vars, $path, $code );
        if ( ! is_array( $resolved ) ) { return; }

        $endpoint_keys = array_keys( $this->woocommerce_account_endpoint_map() );
        $has_endpoint = false;
        foreach ( $endpoint_keys as $endpoint_key ) {
            if ( array_key_exists( $endpoint_key, $resolved ) ) { $has_endpoint = true; break; }
        }
        if ( ! $has_endpoint ) { return; }

        $wp->query_vars = $resolved;
        unset( $wp->query_vars['error'], $wp->query_vars['itkt_path'] );
        $wp->query_vars['itkt_lang'] = $code;
    }

    /**
     * Rebuild the active WooCommerce My Account endpoint from the real browser URL at runtime.
     *
     * WooCommerce account templates decide which section to render from endpoint query vars. A
     * few managed-host/theme combinations can still leave the main page query intact while
     * dropping that endpoint after parse_request, which makes every click look like a dashboard
     * reload. Synchronizing both $wp and $wp_query here makes the endpoint authoritative again.
     */
    public function force_woocommerce_account_endpoint_runtime() {
        if ( is_admin() || ! ITKT_Plugin::module_enabled( 'woocommerce' ) || ! function_exists( 'wc_get_page_id' ) ) { return; }

        $state = $this->current_woocommerce_account_endpoint_state();
        if ( empty( $state['key'] ) ) { return; }
        $code = sanitize_key( (string) ( $state['code'] ?? '' ) );
        if ( ! $code ) { $code = ITKT_Languages::instance()->current_code(); }
        if ( ! $code ) { return; }

        global $wp, $wp_query;
        $endpoint_key = sanitize_key( (string) $state['key'] );
        $endpoint_value = (string) ( $state['value'] ?? '' );
        if ( ! $endpoint_key ) { return; }

        // Remove stale competing endpoint vars first; WooCommerce must see exactly the one from
        // the current browser URL.
        $all_endpoint_keys = array_map( 'sanitize_key', array_keys( $this->woocommerce_account_endpoint_map() ) );
        if ( isset( $wp ) && is_object( $wp ) ) {
            foreach ( $all_endpoint_keys as $key ) { unset( $wp->query_vars[ $key ] ); }
            $wp->query_vars[ $endpoint_key ] = $endpoint_value;
            $wp->query_vars['itkt_lang'] = $code;
            unset( $wp->query_vars['error'] );
        }

        if ( isset( $wp_query ) && $wp_query instanceof WP_Query ) {
            foreach ( $all_endpoint_keys as $key ) {
                unset( $wp_query->query_vars[ $key ], $wp_query->query[ $key ] );
            }
            $wp_query->query_vars[ $endpoint_key ] = $endpoint_value;
            $wp_query->query[ $endpoint_key ] = $endpoint_value;
            $wp_query->query_vars['itkt_lang'] = $code;
            $wp_query->query['itkt_lang'] = $code;
            unset( $wp_query->query_vars['error'], $wp_query->query['error'] );
            $wp_query->is_404 = false;
        }

        // get_query_var() reads the main WP_Query object. Keep its public API synchronized too,
        // because WooCommerce extensions/themes may use get_query_var() instead of $wp directly.
        if ( function_exists( 'set_query_var' ) ) {
            set_query_var( $endpoint_key, $endpoint_value );
            set_query_var( 'itkt_lang', $code );
        }
    }

    /**
     * Direct WooCommerce My Account content dispatcher for language-prefixed endpoints.
     *
     * This is intentionally limited to a URL that explicitly contains a known account endpoint.
     * The normal dashboard remains untouched. It prevents WoodMart/other account wrappers from
     * showing the dashboard when WordPress has lost the endpoint query-var despite a correct URL.
     */
    public function dispatch_woocommerce_account_endpoint_content() {
        if ( is_admin() || ! ITKT_Plugin::module_enabled( 'woocommerce' ) || ! function_exists( 'wc_get_page_id' ) ) { return; }

        $state = $this->current_woocommerce_account_endpoint_state();
        if ( empty( $state['key'] ) ) { return; }

        $hook = 'woocommerce_account_' . sanitize_key( (string) $state['key'] ) . '_endpoint';
        if ( ! has_action( $hook ) ) { return; }

        // WooCommerce core would normally perform this dispatch at priority 10 on the parent
        // woocommerce_account_content hook. Remove only that core callback for this request to
        // avoid duplicate output, then invoke the exact same endpoint action ourselves.
        if ( has_action( 'woocommerce_account_content', 'woocommerce_account_content' ) ) {
            remove_action( 'woocommerce_account_content', 'woocommerce_account_content', 10 );
        }

        do_action( $hook, (string) ( $state['value'] ?? '' ) );
    }

    /**
     * Read the independent My Account rescue marker used by v0.12.10.
     *
     * The marker carries a canonical endpoint key, never arbitrary action names. Every value is
     * validated against WooCommerce's endpoint map before it is allowed to influence rendering.
     */
    private function woocommerce_account_endpoint_marker_state() {
        if ( empty( $_GET['itkt_wc_endpoint'] ) ) { return array(); } // phpcs:ignore WordPress.Security.NonceVerification.Recommended

        $key = sanitize_key( wp_unslash( $_GET['itkt_wc_endpoint'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $map = $this->woocommerce_account_endpoint_map();
        if ( ! $key || ! array_key_exists( $key, $map ) || '' === trim( (string) $map[ $key ] ) ) { return array(); }

        $value = '';
        if ( isset( $_GET['itkt_wc_value'] ) && is_scalar( $_GET['itkt_wc_value'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $value = sanitize_text_field( wp_unslash( $_GET['itkt_wc_value'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        }
        $code = $this->requested_language_code();
        if ( ! $code ) { $code = ITKT_Languages::instance()->current_code(); }

        return array(
            'key'   => $key,
            'slug'  => trim( (string) $map[ $key ], '/' ),
            'value' => $value,
            'code'  => sanitize_key( (string) $code ),
            'marker'=> true,
        );
    }

    /**
     * Parse the current real browser URL into canonical WooCommerce My Account endpoint state.
     * Returns an empty key for the account dashboard and an empty array outside My Account.
     */
    private function current_woocommerce_account_endpoint_state() {
        if ( ! ITKT_Plugin::module_enabled( 'woocommerce' ) || ! function_exists( 'wc_get_page_id' ) ) { return array(); }

        // v0.12.10 server-side rescue marker. Prefer it over the path because this marker is
        // intentionally added to account links for exactly those environments where the pretty
        // endpoint path is not preserved in WordPress/WooCommerce query vars.
        $marker = $this->woocommerce_account_endpoint_marker_state();
        if ( $marker ) { return $marker; }

        $code = $this->requested_language_code();
        if ( ! $code ) { return array(); }
        $path = $this->requested_path_after_language_prefix();
        if ( '' === $path ) { return array(); }

        $resolved = $this->woocommerce_account_query_for_path( array(), $path, $code );
        if ( ! is_array( $resolved ) ) { return array(); }

        foreach ( $this->woocommerce_account_endpoint_map() as $key => $slug ) {
            $key = sanitize_key( (string) $key );
            if ( $key && array_key_exists( $key, $resolved ) ) {
                return array(
                    'key'   => $key,
                    'slug'  => trim( (string) $slug, '/' ),
                    'value' => (string) $resolved[ $key ],
                    'code'  => $code,
                );
            }
        }

        // The physical My Account dashboard is still an account request, but has no endpoint.
        $account_id = absint( wc_get_page_id( 'myaccount' ) );
        if ( $account_id && isset( $resolved['page_id'] ) && absint( $resolved['page_id'] ) === $account_id ) {
            return array( 'key' => '', 'slug' => '', 'value' => '', 'code' => $code );
        }
        return array();
    }

    /**
     * Preserve WooCommerce My Account endpoint state when the visitor switches language.
     */
    private function language_url_for_current_account_request( $code ) {
        $state = $this->current_woocommerce_account_endpoint_state();
        if ( ! $state ) { return ''; }
        if ( ! function_exists( 'wc_get_page_id' ) ) { return ''; }

        $account_id = absint( wc_get_page_id( 'myaccount' ) );
        if ( ! $account_id ) { return ''; }
        $base = $this->language_url_for_system_page( $account_id, $code, false );
        if ( ! $base ) { return ''; }

        if ( empty( $state['key'] ) ) { return $base; }

        $map  = $this->woocommerce_account_endpoint_map();
        $slug = trim( (string) ( $map[ $state['key'] ] ?? $state['slug'] ?? '' ), '/' );
        if ( '' === $slug ) { return $base; }

        $url = trailingslashit( $base ) . $slug;
        if ( '' !== (string) ( $state['value'] ?? '' ) ) {
            $url .= '/' . trim( (string) $state['value'], '/' );
        }
        return user_trailingslashit( $url );
    }

    /** Preserve Cart/Checkout/Shop state when switching language. */
    private function language_url_for_current_woocommerce_system_request( $code ) {
        if ( ! ITKT_Plugin::module_enabled( 'woocommerce' ) || ! function_exists( 'wc_get_page_id' ) ) { return ''; }
        $code = sanitize_key( (string) $code );

        $page_key = '';
        if ( function_exists( 'is_cart' ) && is_cart() ) { $page_key = 'cart'; }
        elseif ( function_exists( 'is_checkout' ) && is_checkout() ) { $page_key = 'checkout'; }
        elseif ( function_exists( 'is_shop' ) && is_shop() ) { $page_key = 'shop'; }

        // Some themes/builders make WooCommerce conditionals unreliable on virtual /LANG/ URLs.
        // Fall back to the real browser path and compare it to the physical WooCommerce pages.
        if ( '' === $page_key ) {
            $path = $this->requested_path_after_language_prefix();
            foreach ( array( 'cart','checkout','shop' ) as $candidate ) {
                $id = absint( wc_get_page_id( $candidate ) );
                if ( ! $id ) { continue; }
                $base = trim( $this->post_source_path( $id ), '/' );
                if ( $base && ( $path === $base || 0 === strpos( $path . '/', $base . '/' ) ) ) {
                    $page_key = $candidate;
                    break;
                }
            }
        }

        if ( '' === $page_key ) { return ''; }
        $page_id = absint( wc_get_page_id( $page_key ) );
        if ( ! $page_id ) { return ''; }
        $url = $this->language_url_for_system_page( $page_id, $code, false );
        if ( ! $url ) { return ''; }

        if ( 'checkout' === $page_key ) {
            $state = $this->current_woocommerce_checkout_endpoint_state();
            if ( $state && ! empty( $state['slug'] ) ) {
                $url = trailingslashit( $url ) . trim( (string) $state['slug'], '/' );
                if ( '' !== (string) ( $state['value'] ?? '' ) ) { $url .= '/' . trim( (string) $state['value'], '/' ); }
                $url = user_trailingslashit( $url );
            }
        }
        return $this->add_current_query_args( $url );
    }

    /** Resolve checkout endpoint state from query vars first, then from the real browser path. */
    private function current_woocommerce_checkout_endpoint_state() {
        $map = array(
            'order-pay'      => get_option( 'woocommerce_checkout_pay_endpoint', 'order-pay' ),
            'order-received' => get_option( 'woocommerce_checkout_order_received_endpoint', 'order-received' ),
        );
        if ( function_exists( 'WC' ) && WC() && isset( WC()->query ) && is_object( WC()->query ) && method_exists( WC()->query, 'get_query_vars' ) ) {
            foreach ( (array) WC()->query->get_query_vars() as $key => $slug ) {
                if ( in_array( $key, array( 'order-pay','order-received' ), true ) && '' !== trim( (string) $slug ) ) { $map[ $key ] = $slug; }
            }
        }
        foreach ( $map as $key => $slug ) {
            $value = function_exists( 'get_query_var' ) ? get_query_var( $key, null ) : null;
            if ( null !== $value && false !== $value && '' !== (string) $value ) {
                return array( 'key'=>$key, 'slug'=>trim( (string) $slug, '/' ), 'value'=>sanitize_text_field( (string) $value ) );
            }
        }

        $checkout_id = absint( wc_get_page_id( 'checkout' ) );
        $base = $checkout_id ? trim( $this->post_source_path( $checkout_id ), '/' ) : '';
        $path = $this->requested_path_after_language_prefix();
        if ( ! $base || ! $path || ( $path !== $base && 0 !== strpos( $path . '/', $base . '/' ) ) ) { return array(); }
        $tail = trim( substr( $path, strlen( $base ) ), '/' );
        if ( '' === $tail ) { return array(); }
        $parts = explode( '/', $tail );
        $first = rawurldecode( (string) ( $parts[0] ?? '' ) );
        foreach ( $map as $key => $slug ) {
            if ( trim( (string) $slug, '/' ) === trim( $first, '/' ) ) {
                return array( 'key'=>$key, 'slug'=>trim( (string) $slug, '/' ), 'value'=>sanitize_text_field( rawurldecode( (string) ( $parts[1] ?? '' ) ) ) );
            }
        }
        return array();
    }

    /** Add safe current query args to a URL without leaking language/preview controls. */
    private function add_current_query_args( $url ) {
        $args = array();
        foreach ( (array) $_GET as $key => $value ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $key = sanitize_key( $key );
            if ( in_array( $key, array( 'itkt_lang','lang','preview','preview_id','preview_nonce','itkt_wc_endpoint','itkt_wc_value' ), true ) ) { continue; }
            if ( is_scalar( $value ) ) { $args[ $key ] = sanitize_text_field( wp_unslash( $value ) ); }
        }
        return $args ? add_query_arg( $args, $url ) : $url;
    }

    /**
     * Last routing fail-safe for Unicode WooCommerce product URLs.
     *
     * The normal `request` filter already resolves language paths. Some nginx/Apache/PHP
     * combinations, however, normalize a percent-encoded Arabic rewrite capture differently
     * before it reaches that filter. At parse_request time we can inspect REQUEST_URI directly
     * and force the main query to the exact physical WooCommerce product ID.
     */
    public function force_unicode_product_request( $wp ) {
        if ( is_admin() || ! is_object( $wp ) || ! ITKT_Plugin::module_enabled( 'woocommerce' ) ) { return; }

        $code = $this->requested_language_code();
        if ( ! $code ) { return; }

        $path = $this->requested_path_after_language_prefix();
        if ( ! $path ) { return; }

        $product_id = $this->resolve_product_id_from_public_path( $path, $code );
        if ( ! $product_id ) { return; }

        $wp->query_vars = $this->query_for_post( (array) $wp->query_vars, $product_id );
        unset( $wp->query_vars['error'], $wp->query_vars['itkt_path'] );
        $wp->query_vars['itkt_lang'] = $code;
    }

    /**
     * Force a translated WooCommerce product onto the main query even when the hosting stack did
     * not preserve our rewrite variables. This is intentionally independent from the `request`
     * filter and works for ASCII as well as Unicode slugs.
     */
    public function force_translated_product_main_query( $query ) {
        if ( is_admin() || ! $query instanceof WP_Query || ! $query->is_main_query() || ! ITKT_Plugin::module_enabled( 'woocommerce' ) ) { return; }

        $route = $this->requested_product_route();
        if ( ! $route ) { return; }

        $product_id = $this->resolve_product_id_from_public_path( $route['base'] . '/' . $route['slug'], $route['lang'] );
        if ( ! $product_id ) { return; }

        // Remove every common permalink/taxonomy variable that can keep the old 404 query alive.
        foreach ( array(
            'name','pagename','page','page_id','attachment','attachment_id','error','taxonomy','term',
            'product','product_cat','product_tag','category_name','tag','author_name','year','monthnum','day'
        ) as $key ) {
            unset( $query->query[ $key ], $query->query_vars[ $key ] );
        }

        $query->set( 'p', absint( $product_id ) );
        $query->set( 'post_type', 'product' );
        $query->set( 'itkt_lang', $route['lang'] );
        $query->set( 'itkt_product_slug', $route['slug'] );

        // parse_query() has already calculated conditionals before pre_get_posts runs. Correct the
        // flags so WooCommerce/WoodMart template selection sees a normal single product request.
        $query->is_404 = false;
        $query->is_home = false;
        $query->is_front_page = false;
        $query->is_page = false;
        $query->is_archive = false;
        $query->is_post_type_archive = false;
        $query->is_tax = false;
        $query->is_category = false;
        $query->is_tag = false;
        $query->is_search = false;
        $query->is_singular = true;
        $query->is_single = true;
    }

    /** Parse /LANG/PRODUCT-BASE/SLUG/ directly from REQUEST_URI. */
    private function requested_product_route() {
        $uri = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
        $path = (string) wp_parse_url( $uri, PHP_URL_PATH );
        if ( '' === $path ) { return false; }

        $home_path = (string) wp_parse_url( $this->raw_home_url( '/' ), PHP_URL_PATH );
        $home_path = '/' . trim( $home_path, '/' );
        if ( '/' !== $home_path && 0 === strpos( $path, $home_path ) ) { $path = substr( $path, strlen( $home_path ) ); }

        $parts = array_values( array_filter( explode( '/', trim( $path, '/' ) ), 'strlen' ) );
        if ( count( $parts ) < 3 ) { return false; }

        $lang = sanitize_key( (string) $parts[0] );
        if ( empty( ITKT_Languages::instance()->get_active()[ $lang ] ) ) { return false; }

        $base = rawurldecode( (string) $parts[1] );
        $valid_bases = $this->product_base_segments();
        $base_cmp = sanitize_title( $base );
        $matched_base = '';
        foreach ( $valid_bases as $candidate ) {
            if ( sanitize_title( $candidate ) === $base_cmp ) { $matched_base = $candidate; break; }
        }
        if ( '' === $matched_base ) { return false; }

        // Current public product URLs use one slug segment. If a future WooCommerce setup uses a
        // category in the product base, the leaf remains the product slug and is still resolvable.
        $slug = $this->decode_public_path_component( (string) end( $parts ) );
        if ( '' === $slug ) { return false; }

        return array( 'lang' => $lang, 'base' => $matched_base, 'slug' => $slug );
    }

    /** Product base segments accepted by the language router. */
    private function product_base_segments() {
        $bases = array( 'product' );
        $permalinks = (array) get_option( 'woocommerce_permalinks', array() );
        $product_base = trim( (string) ( $permalinks['product_base'] ?? '' ), '/' );
        if ( $product_base ) {
            $segments = explode( '/', $product_base );
            foreach ( $segments as $segment ) {
                if ( '' === $segment || false !== strpos( $segment, '%' ) ) { continue; }
                $decoded = rawurldecode( $segment );
                if ( $decoded ) { $bases[] = $decoded; }
            }
        }
        // Common German base, useful when WooCommerce permalinks were changed after an ITKT update.
        $bases[] = 'produkt';
        return array_values( array_unique( array_filter( $bases ) ) );
    }

    /** Decode one public path component without ever sanitizing Unicode to percent-encoded post_name. */
    private function decode_public_path_component( $value ) {
        $value = (string) $value;
        for ( $i = 0; $i < 3 && false !== strpos( $value, '%' ); $i++ ) {
            $decoded = rawurldecode( $value );
            if ( $decoded === $value ) { break; }
            $value = $decoded;
        }
        return class_exists( 'ITKT_Product_Translations' )
            ? ITKT_Product_Translations::normalize_public_slug( $value )
            : sanitize_title( $value );
    }

    /**
     * Return the path following /LANG/ from the real browser request.
     * Repeated decoding safely handles both `%D9...` and accidentally double-encoded `%25D9...`
     * while leaving already-readable Unicode untouched.
     */
    private function requested_path_after_language_prefix() {
        $uri = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
        $path = (string) wp_parse_url( $uri, PHP_URL_PATH );
        if ( '' === $path ) { return ''; }

        $home_path = (string) wp_parse_url( $this->raw_home_url( '/' ), PHP_URL_PATH );
        $home_path = '/' . trim( $home_path, '/' );
        if ( '/' !== $home_path && 0 === strpos( $path, $home_path ) ) {
            $path = substr( $path, strlen( $home_path ) );
        }

        $path = trim( $path, '/' );
        if ( '' === $path ) { return ''; }
        $parts = explode( '/', $path );
        $first = sanitize_key( (string) ( $parts[0] ?? '' ) );
        if ( ! isset( ITKT_Languages::instance()->catalog()[ $first ] ) ) { return ''; }
        array_shift( $parts );
        $path = implode( '/', $parts );

        for ( $i = 0; $i < 2 && false !== strpos( $path, '%' ); $i++ ) {
            $decoded = rawurldecode( $path );
            if ( $decoded === $path ) { break; }
            $path = $decoded;
        }
        return trim( $path, '/' );
    }

    /**
     * Resolve a WooCommerce product from any public ITKT product path.
     *
     * The fast path uses the source post_name and the language-specific _itkt_slug_LANG
     * index. Older translations may predate that index, so a narrowly-scoped fallback
     * searches the serialized translation meta, verifies the exact language slug and then
     * writes the missing index back for all following requests.
     */
    private function resolve_product_id_from_public_path( $path, $hint_code = '' ) {
        if ( ! ITKT_Plugin::module_enabled( 'woocommerce' ) || ! post_type_exists( 'product' ) ) { return 0; }

        $path = trim( rawurldecode( (string) $path ), '/' );
        if ( '' === $path ) { return 0; }

        $leaf_raw    = rawurldecode( basename( $path ) );
        $leaf_public = class_exists( 'ITKT_Product_Translations' ) ? ITKT_Product_Translations::normalize_public_slug( $leaf_raw ) : sanitize_title( $leaf_raw );
        $leaf_wp     = sanitize_title( $leaf_raw );
        if ( ! $leaf_public && ! $leaf_wp ) { return 0; }

        $default = ITKT_Languages::instance()->get_default_code();
        $active  = ITKT_Languages::instance()->get_active();
        $codes   = array();
        $hint_code = sanitize_key( (string) $hint_code );
        if ( $hint_code && isset( $active[ $hint_code ] ) ) { $codes[] = $hint_code; }
        foreach ( array_keys( $active ) as $code ) {
            $code = sanitize_key( (string) $code );
            if ( $code && ! in_array( $code, $codes, true ) ) { $codes[] = $code; }
        }
        if ( ! in_array( $default, $codes, true ) ) { $codes[] = $default; }

        $candidates = array();

        // Strongest match: an explicit slug stored for the language requested in the URL.
        // If that slug uniquely identifies one product, the leaf itself is authoritative; do
        // not let a later WordPress path normalization reject the product because of Unicode.
        if ( $hint_code && $hint_code !== $default && class_exists( 'ITKT_Product_Translations' ) ) {
            $hint_matches = array();
            $slug_hash = ITKT_Product_Translations::public_slug_hash( $leaf_public );

            // Primary route: an ASCII SHA-256 index. This is deliberately independent from
            // database collation and from the percent-encoding representation used by the web server.
            if ( $slug_hash ) {
                $ids = get_posts( array(
                    'post_type'        => 'product',
                    'post_status'      => 'publish',
                    'numberposts'      => 20,
                    'fields'           => 'ids',
                    'meta_key'         => '_itkt_slug_hash_' . $hint_code,
                    'meta_value'       => $slug_hash,
                    'suppress_filters' => true,
                ) );
                foreach ( (array) $ids as $id ) {
                    $id = absint( $id );
                    $stored = ITKT_Product_Translations::instance()->explicit_slug( $id, $hint_code );
                    if ( $stored && $leaf_public && hash_equals( $stored, $leaf_public ) ) { $hint_matches[ $id ] = true; }
                }
            }

            // Existing installs do not have the hash index yet. Read the readable Unicode index
            // directly, compare in PHP, and lazily backfill the hash on the first request.
            if ( ! $hint_matches && $leaf_public ) {
                foreach ( $this->products_with_language_slug_index( $hint_code ) as $id => $stored_index ) {
                    $stored = ITKT_Product_Translations::normalize_public_slug( $stored_index );
                    if ( $stored && hash_equals( $stored, $leaf_public ) ) {
                        update_post_meta( $id, '_itkt_slug_hash_' . $hint_code, ITKT_Product_Translations::public_slug_hash( $stored ) );
                        $hint_matches[ $id ] = true;
                    }
                }
            }

            // Last compatibility path: the slug can exist only inside the serialized translation row.
            if ( ! $hint_matches && $leaf_public ) {
                foreach ( $this->products_with_translation_payload() as $id ) {
                    $data = ITKT_Product_Translations::instance()->get( $id, $hint_code );
                    $stored = ITKT_Product_Translations::normalize_public_slug( (string) ( $data['slug'] ?? '' ) );
                    if ( $stored && ! empty( $data['slug_explicit'] ) && hash_equals( $stored, $leaf_public ) ) {
                        update_post_meta( $id, '_itkt_slug_' . $hint_code, $stored );
                        update_post_meta( $id, '_itkt_slug_hash_' . $hint_code, ITKT_Product_Translations::public_slug_hash( $stored ) );
                        $hint_matches[ $id ] = true;
                    }
                }
            }

            if ( 1 === count( $hint_matches ) ) { return absint( array_key_first( $hint_matches ) ); }
        }

        // Source/default WooCommerce product slug.
        $source_ids = get_posts( array(
            'post_type'        => 'product',
            'post_status'      => 'publish',
            'numberposts'      => 50,
            'fields'           => 'ids',
            'name'             => $leaf_wp,
            'suppress_filters' => true,
        ) );
        foreach ( (array) $source_ids as $id ) {
            $candidates[] = array( 'id' => absint( $id ), 'code' => $default );
        }

        // Fast language-specific slug indexes.
        foreach ( $codes as $code ) {
            if ( $code === $default ) { continue; }
            $slug_values = array_values( array_unique( array_filter( array( $leaf_public, $leaf_wp ) ) ) );
            foreach ( $slug_values as $slug_value ) {
                $ids = get_posts( array(
                    'post_type'        => 'product',
                    'post_status'      => 'publish',
                    'numberposts'      => 50,
                    'fields'           => 'ids',
                    'meta_key'         => '_itkt_slug_' . $code,
                    'meta_value'       => $slug_value,
                    'suppress_filters' => true,
                ) );
                foreach ( (array) $ids as $id ) {
                    $candidates[] = array( 'id' => absint( $id ), 'code' => $code );
                }
            }
        }

        // Legacy fallback: older product translations can contain a slug in
        // _itkt_product_translations without a separate _itkt_slug_LANG index.
        // Search only rows containing this exact slug, then verify after unserializing.
        if ( class_exists( 'ITKT_Product_Translations' ) ) {
            $legacy_values = array_values( array_unique( array_filter( array( $leaf_public, $leaf_wp ) ) ) );
            $legacy_meta_query = array( 'relation' => 'OR' );
            foreach ( $legacy_values as $legacy_value ) {
                $legacy_meta_query[] = array(
                    'key'     => ITKT_Product_Translations::META_KEY,
                    'value'   => $legacy_value,
                    'compare' => 'LIKE',
                );
            }
            $legacy_ids = get_posts( array(
                'post_type'        => 'product',
                'post_status'      => 'publish',
                'numberposts'      => 100,
                'fields'           => 'ids',
                'meta_query'       => $legacy_meta_query,
                'suppress_filters' => true,
            ) );
            foreach ( (array) $legacy_ids as $id ) {
                $id = absint( $id );
                foreach ( $codes as $code ) {
                    if ( $code === $default ) { continue; }
                    $data = ITKT_Product_Translations::instance()->get( $id, $code );
                    $stored = ITKT_Product_Translations::normalize_public_slug( (string) ( $data['slug'] ?? '' ) );
                    if ( $stored && $leaf_public && hash_equals( $stored, $leaf_public ) ) {
                        update_post_meta( $id, '_itkt_slug_' . $code, $stored );
                        $candidates[] = array( 'id' => $id, 'code' => $code );
                    }
                }
            }
        }

        $seen = array();
        foreach ( $candidates as $candidate ) {
            $id   = absint( $candidate['id'] ?? 0 );
            $code = sanitize_key( (string) ( $candidate['code'] ?? '' ) );
            if ( ! $id || ! $code ) { continue; }
            $key = $id . ':' . $code;
            if ( isset( $seen[ $key ] ) ) { continue; }
            $seen[ $key ] = true;

            $expected = class_exists( 'ITKT_SEO' )
                ? ITKT_SEO::instance()->translated_post_path( $id, $code )
                : $this->source_path_for_post( $id );

            if ( $this->normalized_public_path( $expected ) === $this->normalized_public_path( $path ) ) {
                return $id;
            }
        }

        return 0;
    }

    /** Read all per-language slug indexes without relying on a Unicode SQL equality comparison. */
    private function products_with_language_slug_index( $code ) {
        global $wpdb;
        $code = sanitize_key( (string) $code );
        if ( ! $code || ! isset( $wpdb->postmeta ) ) { return array(); }
        $meta_key = '_itkt_slug_' . $code;
        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT pm.post_id, pm.meta_value FROM {$wpdb->postmeta} pm INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id WHERE pm.meta_key = %s AND p.post_type = 'product' AND p.post_status = 'publish'",
            $meta_key
        ) );
        $out = array();
        foreach ( (array) $rows as $row ) { $out[ absint( $row->post_id ) ] = (string) $row->meta_value; }
        return $out;
    }

    /** Published products carrying translation payloads; used only as a migration fallback. */
    private function products_with_translation_payload() {
        global $wpdb;
        if ( ! class_exists( 'ITKT_Product_Translations' ) || ! isset( $wpdb->postmeta ) ) { return array(); }
        $ids = $wpdb->get_col( $wpdb->prepare(
            "SELECT DISTINCT pm.post_id FROM {$wpdb->postmeta} pm INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id WHERE pm.meta_key = %s AND p.post_type = 'product' AND p.post_status = 'publish'",
            ITKT_Product_Translations::META_KEY
        ) );
        return array_values( array_filter( array_map( 'absint', (array) $ids ) ) );
    }

    /** Normalize encoded and Unicode paths to one comparison representation. */
    private function normalized_public_path( $path ) {
        $path = trim( rawurldecode( (string) $path ), '/' );
        if ( '' === $path ) { return ''; }
        $parts = array();
        foreach ( explode( '/', $path ) as $part ) {
            $part = rawurldecode( (string) $part );
            $parts[] = sanitize_title( $part );
        }
        return trim( implode( '/', $parts ), '/' );
    }

    /**
     * When the current request is already a 404 (for example an old malformed language URL),
     * recover the product ID from the URL so the language switcher can still point to the
     * correct DE/EN/AR version instead of carrying the wrong language slug forward.
     */
    private function current_product_id_from_request() {
        $path = $this->current_source_path();
        if ( '' === $path ) { return 0; }

        $hint = $this->requested_language_code();
        if ( ! $hint ) { $hint = ITKT_Languages::instance()->current_code(); }
        $id = $this->resolve_product_id_from_public_path( $path, $hint );
        if ( $id ) { return $id; }
        return $this->find_product_id_by_any_known_slug( $path );
    }

    /**
     * Loose recovery used only for a broken product URL. It accepts the source slug or a slug
     * stored in any language translation and returns a product only when the match is unique.
     * This lets us redirect stale/legacy URLs to a safe default-language product instead of 404.
     */
    private function find_product_id_by_any_known_slug( $path ) {
        if ( ! ITKT_Plugin::module_enabled( 'woocommerce' ) || ! post_type_exists( 'product' ) ) { return 0; }
        $leaf_raw    = rawurldecode( basename( trim( (string) $path, '/' ) ) );
        $leaf_public = class_exists( 'ITKT_Product_Translations' ) ? ITKT_Product_Translations::normalize_public_slug( $leaf_raw ) : sanitize_title( $leaf_raw );
        $leaf_wp     = sanitize_title( $leaf_raw );
        if ( ! $leaf_public && ! $leaf_wp ) { return 0; }

        $matches = array();
        $source_ids = get_posts( array(
            'post_type' => 'product', 'post_status' => 'publish', 'numberposts' => 20,
            'fields' => 'ids', 'name' => $leaf_wp, 'suppress_filters' => true,
        ) );
        foreach ( (array) $source_ids as $id ) { $matches[ absint( $id ) ] = true; }

        if ( class_exists( 'ITKT_Product_Translations' ) ) {
            $legacy_values = array_values( array_unique( array_filter( array( $leaf_public, $leaf_wp ) ) ) );
            $legacy_meta_query = array( 'relation' => 'OR' );
            foreach ( $legacy_values as $legacy_value ) {
                $legacy_meta_query[] = array( 'key' => ITKT_Product_Translations::META_KEY, 'value' => $legacy_value, 'compare' => 'LIKE' );
            }
            $legacy_ids = get_posts( array(
                'post_type' => 'product', 'post_status' => 'publish', 'numberposts' => 100,
                'fields' => 'ids', 'suppress_filters' => true,
                'meta_query' => $legacy_meta_query,
            ) );
            foreach ( (array) $legacy_ids as $id ) {
                $id = absint( $id );
                foreach ( ITKT_Product_Translations::instance()->get_all( $id ) as $data ) {
                    if ( ! is_array( $data ) ) { continue; }
                    $stored = ITKT_Product_Translations::normalize_public_slug( (string) ( $data['slug'] ?? '' ) );
                    if ( $stored && $leaf_public && hash_equals( $stored, $leaf_public ) ) { $matches[ $id ] = true; break; }
                }
            }
        }
        $ids = array_keys( $matches );
        return 1 === count( $ids ) ? absint( $ids[0] ) : 0;
    }

    /** Preserve the real WooCommerce shop archive instead of loading the Shop page as a normal page. */
    /**
     * Resolve virtual language URLs for WooCommerce My Account endpoints.
     *
     * Example: /en/my-account/orders/ must query the translated My Account page *and* expose
     * the `orders` endpoint to WC_Query. Without this, WordPress simply renders the dashboard
     * page again. Endpoint slugs remain WooCommerce configuration data; only the surrounding
     * page receives the storefront language directory.
     */
    /** Canonical WooCommerce endpoint key => configured public slug map. */
    private function woocommerce_account_endpoint_map() {
        $map = array();
        if ( function_exists( 'WC' ) && WC() && isset( WC()->query ) && is_object( WC()->query ) && method_exists( WC()->query, 'get_query_vars' ) ) {
            $map = (array) WC()->query->get_query_vars();
        }

        // WooCommerce may not have initialized WC_Query yet when ITKT's request filter executes.
        // Merge explicit option fallbacks so routing is deterministic at every init priority.
        $fallback = array(
            'orders'                     => get_option( 'woocommerce_myaccount_orders_endpoint', 'orders' ),
            'view-order'                 => get_option( 'woocommerce_myaccount_view_order_endpoint', 'view-order' ),
            'downloads'                  => get_option( 'woocommerce_myaccount_downloads_endpoint', 'downloads' ),
            'edit-account'               => get_option( 'woocommerce_myaccount_edit_account_endpoint', 'edit-account' ),
            'edit-address'               => get_option( 'woocommerce_myaccount_edit_address_endpoint', 'edit-address' ),
            'payment-methods'            => get_option( 'woocommerce_myaccount_payment_methods_endpoint', 'payment-methods' ),
            'lost-password'              => get_option( 'woocommerce_myaccount_lost_password_endpoint', 'lost-password' ),
            'customer-logout'            => get_option( 'woocommerce_logout_endpoint', 'customer-logout' ),
            'add-payment-method'         => get_option( 'woocommerce_myaccount_add_payment_method_endpoint', 'add-payment-method' ),
            'delete-payment-method'      => get_option( 'woocommerce_myaccount_delete_payment_method_endpoint', 'delete-payment-method' ),
            'set-default-payment-method' => get_option( 'woocommerce_myaccount_set_default_payment_method_endpoint', 'set-default-payment-method' ),
        );
        foreach ( $fallback as $key => $slug ) {
            if ( ! isset( $map[ $key ] ) || '' === trim( (string) $map[ $key ] ) ) { $map[ $key ] = $slug; }
        }
        return $map;
    }

    private function woocommerce_account_query_for_path( $query_vars, $path, $code ) {
        if ( ! ITKT_Plugin::module_enabled( 'woocommerce' ) || ! function_exists( 'wc_get_page_id' ) ) { return false; }

        $account_id = absint( wc_get_page_id( 'myaccount' ) );
        if ( ! $account_id || 'publish' !== get_post_status( $account_id ) ) { return false; }

        // IMPORTANT: My Account is a WooCommerce system page. Even when ITKT has a translated
        // duplicate mapped for normal pages, endpoint rendering must stay on the physical page
        // configured in WooCommerce. Otherwise WooCommerce can render the dashboard shortcode
        // but lose /orders/, /downloads/, /edit-address/, etc. The translated page path is still
        // accepted as a public URL alias; it is never used as the technical queried page here.
        // The exact page selected in WooCommerce is the technical authority for every account
        // endpoint. Do not replace it with an ITKT translation relation here. A translated copy
        // can still be accepted as a public alias, but the physical WooCommerce page path must
        // always be the first and guaranteed base. This fixes installations where the system page
        // itself was previously assigned translation metadata.
        $physical_id   = $account_id;
        $physical_path = $this->post_source_path( $physical_id );
        $source_id     = $this->default_source_post_id( $physical_id );
        $target_id     = $this->target_post_id( $source_id, $code );
        if ( ! $target_id ) { $target_id = $source_id; }

        $bases = array();
        if ( $physical_path ) { $bases[] = $physical_path; }

        $source_path = $this->post_source_path( $source_id );
        if ( $source_path ) { $bases[] = $source_path; }
        if ( $target_id && $target_id !== $source_id && $target_id !== $physical_id ) {
            $target_path = $this->post_source_path( $target_id );
            if ( $target_path ) { $bases[] = $target_path; }
        }
        if ( class_exists( 'ITKT_SEO' ) ) {
            $translated_path = ITKT_SEO::instance()->translated_post_path( $source_id, $code );
            if ( $translated_path || '' === $translated_path ) { $bases[] = $translated_path; }
        }
        $bases = array_values( array_unique( array_filter( array_map( function( $value ) {
            return trim( rawurldecode( (string) $value ), '/' );
        }, $bases ) ) ) );

        $requested_path = trim( rawurldecode( (string) $path ), '/' );

        // My Account is a WooCommerce system page, not a normal translated content page.
        // Always resolve the language-prefixed account base to WooCommerce's configured physical
        // page ID. This keeps is_account_page(), WoodMart account widgets and WC endpoint logic
        // identical to the default-language request. The storefront language remains in itkt_lang.
        foreach ( $bases as $base ) {
            if ( $this->normalized_public_path( $base ) === $this->normalized_public_path( $requested_path ) ) {
                $query_vars = $this->query_for_post( $query_vars, $account_id );
                $query_vars['itkt_lang'] = sanitize_key( (string) $code );
                unset( $query_vars['error'] );
                return $query_vars;
            }
        }

        $request_parts = array_values( array_filter( explode( '/', $requested_path ), 'strlen' ) );
        if ( ! $request_parts ) { return false; }

        $endpoint_parts = null;
        foreach ( $bases as $base ) {
            $base_parts = array_values( array_filter( explode( '/', trim( rawurldecode( (string) $base ), '/' ) ), 'strlen' ) );
            if ( ! $base_parts || count( $request_parts ) <= count( $base_parts ) ) { continue; }
            $matches = true;
            foreach ( $base_parts as $index => $segment ) {
                if ( sanitize_title( rawurldecode( $segment ) ) !== sanitize_title( rawurldecode( $request_parts[ $index ] ?? '' ) ) ) {
                    $matches = false; break;
                }
            }
            if ( $matches ) {
                $endpoint_parts = array_slice( $request_parts, count( $base_parts ) );
                break;
            }
        }
        if ( ! is_array( $endpoint_parts ) || ! $endpoint_parts ) { return false; }

        $endpoint_map = $this->woocommerce_account_endpoint_map();

        $requested_endpoint = sanitize_title( rawurldecode( (string) $endpoint_parts[0] ) );
        $endpoint_key = '';
        foreach ( $endpoint_map as $key => $slug ) {
            $slug = trim( (string) $slug, '/' );
            if ( '' === $slug ) { continue; }
            if ( sanitize_title( rawurldecode( $slug ) ) === $requested_endpoint ) {
                $endpoint_key = sanitize_key( (string) $key );
                break;
            }
        }
        if ( ! $endpoint_key ) { return false; }

        $value_parts = array_slice( $endpoint_parts, 1 );
        $endpoint_value = implode( '/', array_map( 'rawurldecode', $value_parts ) );

        // Always query the WooCommerce-configured physical My Account page. Language is a
        // storefront presentation concern; the endpoint remains canonical WooCommerce state.
        $query_vars = $this->query_for_post( $query_vars, $account_id );
        // WC checks for the existence of this query-var even when the endpoint has no value.
        $query_vars[ $endpoint_key ] = $endpoint_value;
        $query_vars['itkt_lang'] = sanitize_key( (string) $code );
        unset( $query_vars['error'] );
        return $query_vars;
    }

    private function woocommerce_archive_query_for_path( $query_vars, $path ) {
        if ( ! ITKT_Plugin::module_enabled( 'woocommerce' ) || ! function_exists( 'wc_get_page_id' ) ) { return false; }
        $shop_id = absint( wc_get_page_id( 'shop' ) );
        if ( ! $shop_id ) { return false; }

        $requested = trim( rawurldecode( (string) $path ), '/' );
        $paged = 0;
        if ( preg_match( '#^(.*?)/page/([0-9]+)$#', $requested, $matches ) ) {
            $requested = trim( $matches[1], '/' );
            $paged = max( 1, absint( $matches[2] ) );
        }

        $shop_url = get_permalink( $shop_id );
        if ( ! $shop_url ) { return false; }
        $shop_path = $this->strip_language_prefix_from_path( $this->relative_home_path( $shop_url ) );
        if ( trim( rawurldecode( $shop_path ), '/' ) !== $requested ) { return false; }

        foreach ( array( 'p', 'page_id', 'name', 'pagename', 'taxonomy', 'term', 'error' ) as $key ) { unset( $query_vars[ $key ] ); }
        $query_vars['post_type'] = 'product';
        if ( $paged ) { $query_vars['paged'] = $paged; }
        return $query_vars;
    }

    /** Resolve prefixed WooCommerce taxonomy archives such as /en/product-category/baklava/. */
    private function taxonomy_query_for_path( $query_vars, $path ) {
        if ( ! ITKT_Plugin::module_enabled( 'woocommerce' ) || ! taxonomy_exists( 'product_cat' ) ) { return false; }
        $path = trim( rawurldecode( (string) $path ), '/' );
        if ( '' === $path ) { return false; }
        $paged = 0;
        if ( preg_match( '#^(.*?)/page/([0-9]+)$#', $path, $matches ) ) {
            $path = trim( $matches[1], '/' );
            $paged = max( 1, absint( $matches[2] ) );
        }

        $taxonomies = get_taxonomies( array( 'public' => true ), 'objects' );
        foreach ( (array) $taxonomies as $taxonomy => $object ) {
            $is_wc = 'product_cat' === $taxonomy || 'product_tag' === $taxonomy || 0 === strpos( (string) $taxonomy, 'pa_' );
            if ( ! $is_wc || empty( $object->rewrite ) || empty( $object->rewrite['slug'] ) ) { continue; }

            $base = trim( (string) $object->rewrite['slug'], '/' );
            if ( '' === $base || ( $path !== $base && 0 !== strpos( $path, $base . '/' ) ) ) { continue; }
            $term_path = trim( substr( $path, strlen( $base ) ), '/' );
            if ( '' === $term_path ) { continue; }
            $parts = explode( '/', $term_path );
            $slug = sanitize_title( rawurldecode( end( $parts ) ) );
            if ( ! $slug ) { continue; }

            $term = get_term_by( 'slug', $slug, $taxonomy );
            if ( ! $term || is_wp_error( $term ) ) { continue; }

            // Validate the full generated source path. This protects hierarchical categories
            // and customized WooCommerce taxonomy bases from accidental slug collisions.
            $source_link = get_term_link( $term );
            if ( is_wp_error( $source_link ) ) { continue; }
            $source_path = $this->relative_home_path( $source_link );
            $source_path = $this->strip_language_prefix_from_path( $source_path );
            if ( trim( rawurldecode( $source_path ), '/' ) !== trim( rawurldecode( $path ), '/' ) ) { continue; }

            foreach ( array( 'p', 'page_id', 'name', 'pagename', 'post_type', 'error' ) as $key ) { unset( $query_vars[ $key ] ); }
            $query_vars['taxonomy'] = $taxonomy;
            $query_vars['term'] = $term->slug;
            if ( ! empty( $object->query_var ) && is_string( $object->query_var ) ) {
                $query_vars[ $object->query_var ] = $term->slug;
            }
            if ( $paged ) { $query_vars['paged'] = $paged; }
            return $query_vars;
        }
        return false;
    }

    private function query_for_post( $query_vars, $post_id ) {
        $post = get_post( $post_id );
        if ( ! $post ) { $query_vars['error'] = '404'; return $query_vars; }

        // Remove query keys that could conflict with our resolved post.
        foreach ( array( 'name', 'pagename', 'page', 'attachment', 'attachment_id', 'year', 'monthnum', 'day' ) as $key ) {
            unset( $query_vars[ $key ] );
        }

        if ( 'page' === $post->post_type ) {
            $query_vars['page_id'] = absint( $post_id );
            unset( $query_vars['p'], $query_vars['post_type'] );
        } else {
            $query_vars['p'] = absint( $post_id );
            $query_vars['post_type'] = $post->post_type;
            unset( $query_vars['page_id'] );
        }
        return $query_vars;
    }

    private function target_post_id( $source_id, $code ) {
        $source_id = absint( $source_id );
        if ( ! $source_id ) { return 0; }

        $default = ITKT_Languages::instance()->get_default_code();
        $type    = get_post_type( $source_id );

        // Products and unsupported custom post types stay one physical post.
        if ( ! in_array( $type, ITKT_Content::instance()->supported_post_types(), true ) ) {
            return $source_id;
        }

        $source_id = $this->default_source_post_id( $source_id );
        if ( $code === $default ) { return $source_id; }

        $translations = ITKT_Content::instance()->translations_for( $source_id );
        if ( isset( $translations[ $code ] ) && 'publish' === $translations[ $code ]->post_status ) {
            return absint( $translations[ $code ]->ID );
        }
        return 0;
    }

    private function default_source_post_id( $post_id ) {
        $post_id = absint( $post_id );
        if ( ! $post_id ) { return 0; }

        if ( ! in_array( get_post_type( $post_id ), ITKT_Content::instance()->supported_post_types(), true ) ) {
            return $post_id;
        }

        $default = ITKT_Languages::instance()->get_default_code();
        $lang    = sanitize_key( (string) get_post_meta( $post_id, '_itkt_language', true ) );
        if ( ! $lang || $default === $lang ) { return $post_id; }

        $translations = ITKT_Content::instance()->translations_for( $post_id );
        return isset( $translations[ $default ] ) ? absint( $translations[ $default ]->ID ) : $post_id;
    }

    public function prevent_wp_canonical_redirect( $redirect_url, $requested_url ) {
        if ( $this->requested_language_code() ) { return false; }
        return $redirect_url;
    }

    /** Store a clean language choice after a prefixed language URL was actually requested. */
    public function remember_requested_language() {
        if ( is_admin() || wp_doing_ajax() || headers_sent() ) { return; }
        $settings = get_option( 'itkt_settings', array() );
        $code = $this->requested_language_code();
        if ( ! $code || empty( ITKT_Languages::instance()->get_active()[ $code ] ) ) { return; }
        // Always keep a session cookie for WooCommerce AJAX/Store API continuity. The setting
        // controls only whether the language survives after the browser session ends.
        $this->set_language_cookie( $code, ! empty( $settings['remember_language'] ) );
    }

    /** Optional convenience: returning to the bare homepage restores the visitor's last language. */
    public function redirect_saved_language_home() {
        if ( is_admin() || wp_doing_ajax() || is_preview() || headers_sent() ) { return; }
        $settings = get_option( 'itkt_settings', array() );
        if ( empty( $settings['remember_language'] ) || empty( $settings['redirect_saved_home'] ) ) { return; }
        if ( $this->requested_language_code() ) { return; }
        $path = $this->current_source_path();
        if ( '' !== $path ) { return; }
        $cookie = ! empty( $_COOKIE['itkt_language'] ) ? sanitize_key( wp_unslash( $_COOKIE['itkt_language'] ) ) : '';
        $default = ITKT_Languages::instance()->get_default_code();
        $active = ITKT_Languages::instance()->get_active();
        if ( $cookie && $cookie !== $default && ! empty( $active[ $cookie ] ) ) {
            wp_safe_redirect( $this->build_directory_url( $cookie, '', false ), 302 );
            exit;
        }
    }

    private function set_language_cookie( $code, $persistent = true ) {
        $code = sanitize_key( (string) $code );
        if ( empty( ITKT_Languages::instance()->get_active()[ $code ] ) ) { return; }
        $path = (string) wp_parse_url( $this->raw_home_url( '/' ), PHP_URL_PATH );
        if ( '' === $path ) { $path = '/'; }
        setcookie( 'itkt_language', $code, array(
            'expires'  => $persistent ? time() + YEAR_IN_SECONDS : 0,
            'path'     => $path,
            'secure'   => is_ssl(),
            'httponly' => false,
            'samesite' => 'Lax',
        ) );
        $_COOKIE['itkt_language'] = $code;
    }

    /** Translate only the public homepage URL. This catches logo links from WoodMart and themes. */
    public function translated_home_url( $url, $path, $orig_scheme, $blog_id ) {
        if ( $this->in_home_url_filter || is_admin() || wp_doing_ajax() ) { return $url; }
        if ( null !== $path && '' !== (string) $path && '/' !== (string) $path ) { return $url; }
        $code = ITKT_Languages::instance()->current_code();
        $default = ITKT_Languages::instance()->get_default_code();
        if ( ! $code || $code === $default ) { return $url; }
        $this->in_home_url_filter = true;
        $translated = $this->build_directory_url( $code, '', false );
        $this->in_home_url_filter = false;
        return $translated;
    }

    public function translated_page_link( $link, $post_id, $sample = false ) {
        return $this->translate_content_permalink( $link, absint( $post_id ) );
    }

    public function translated_post_link( $link, $post, $leavename = false ) {
        return $this->translate_content_permalink( $link, $post instanceof WP_Post ? $post->ID : 0 );
    }

    public function translated_post_type_link( $link, $post, $leavename = false, $sample = false ) {
        if ( ! $post instanceof WP_Post || 'product' === $post->post_type ) { return $link; }
        return $this->translate_content_permalink( $link, $post->ID );
    }

    private function translate_content_permalink( $link, $post_id ) {
        if ( $this->in_permalink_filter || ( is_admin() && ! wp_doing_ajax() ) ) { return $link; }
        $post_id = absint( $post_id );
        if ( ! $post_id || ! in_array( get_post_type( $post_id ), ITKT_Content::instance()->supported_post_types(), true ) ) { return $link; }
        $code = ITKT_Languages::instance()->current_code();
        if ( ! $code || $code === ITKT_Languages::instance()->get_default_code() ) { return $link; }
        $this->in_permalink_filter = true;
        $translated = $this->language_url_for_post( $post_id, $code, false );
        $this->in_permalink_filter = false;
        return $translated;
    }

    /**
     * Upgrade old ?itkt_lang=en links and direct internal translated-post permalinks to the
     * public directory URL. This keeps /home-2/ etc. out of the visitor-facing site.
     */
    public function redirect_legacy_language_urls() {
        if ( is_admin() || wp_doing_ajax() || is_preview() || is_feed() || headers_sent() ) { return; }
        if ( ! get_option( 'permalink_structure' ) ) { return; }

        $requested_prefix = $this->requested_language_code();
        $default          = ITKT_Languages::instance()->get_default_code();

        // Default language never needs /de/ (or another default code) in the public URL.
        if ( $requested_prefix && $requested_prefix === $default ) {
            wp_safe_redirect( $this->language_url( $default ), 301 );
            exit;
        }

        // Old query-parameter links become directory URLs.
        if ( isset( $_GET['itkt_lang'] ) || isset( $_GET['lang'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $code = ITKT_Languages::instance()->current_code();
            wp_safe_redirect( $this->language_url( $code ), 302 );
            exit;
        }

        // A translated duplicate can still be opened through its internal WordPress permalink.
        // Redirect that internal slug to /LANG/source-slug/.
        if ( ! $requested_prefix && is_singular( ITKT_Content::instance()->supported_post_types() ) ) {
            $id   = get_queried_object_id();
            $lang = sanitize_key( (string) get_post_meta( $id, '_itkt_language', true ) );
            if ( $lang && $lang !== $default && isset( ITKT_Languages::instance()->get_active()[ $lang ] ) ) {
                wp_safe_redirect( $this->language_url_for_post( $id, $lang ), 301 );
                exit;
            }
        }
    }

    /**
     * Repair malformed/stale product-language URLs before the theme renders its 404 template.
     * This is especially useful after upgrading from older plugin versions where the switcher
     * could carry an AR/EN slug into another language URL.
     */
    public function repair_product_language_404() {
        if ( is_admin() || wp_doing_ajax() || is_preview() || is_feed() || headers_sent() || ! is_404() ) { return; }
        if ( ! ITKT_Plugin::module_enabled( 'woocommerce' ) ) { return; }

        $product_id = $this->current_product_id_from_request();
        if ( ! $product_id ) { return; }

        $code = $this->requested_language_code();
        if ( ! $code ) { $code = ITKT_Languages::instance()->current_code(); }
        $active = ITKT_Languages::instance()->get_active();
        $default = ITKT_Languages::instance()->get_default_code();
        if ( empty( $active[ $code ] ) ) { $code = $default; }

        $settings = get_option( 'itkt_settings', array() );
        if ( $code !== $default && ! empty( $settings['product_slug_fallback_default'] ) && class_exists( 'ITKT_Product_Translations' ) && ! ITKT_Product_Translations::instance()->has_explicit_slug( $product_id, $code ) ) {
            $code = $default;
        }

        $target = $this->language_url_for_post( $product_id, $code, false );
        if ( ! $target ) { return; }
        $current = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
        $current_path = untrailingslashit( rawurldecode( (string) wp_parse_url( $current, PHP_URL_PATH ) ) );
        $target_path  = untrailingslashit( rawurldecode( (string) wp_parse_url( $target, PHP_URL_PATH ) ) );
        if ( $current_path === $target_path ) { return; }

        wp_safe_redirect( $target, 302 );
        exit;
    }

    /**
     * A valid product can also be reached through /LANG/product/source-slug/. When the target
     * language has no explicit product slug and fallback is enabled, normalize that request to
     * the real default-language product URL so the visitor never lands on a language 404.
     */
    public function redirect_product_without_language_slug() {
        if ( is_admin() || wp_doing_ajax() || is_preview() || is_feed() || headers_sent() || ! is_singular( 'product' ) ) { return; }
        $requested = $this->requested_language_code();
        $default = ITKT_Languages::instance()->get_default_code();
        if ( ! $requested || $requested === $default ) { return; }
        $settings = get_option( 'itkt_settings', array() );
        if ( empty( $settings['product_slug_fallback_default'] ) || ! class_exists( 'ITKT_Product_Translations' ) ) { return; }

        $product_id = absint( get_queried_object_id() );
        if ( ! $product_id || ITKT_Product_Translations::instance()->has_explicit_slug( $product_id, $requested ) ) { return; }

        $target = $this->language_url_for_post( $product_id, $default, false );
        if ( ! $target ) { return; }
        $current = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
        $current_path = untrailingslashit( rawurldecode( (string) wp_parse_url( $current, PHP_URL_PATH ) ) );
        $target_path = untrailingslashit( rawurldecode( (string) wp_parse_url( $target, PHP_URL_PATH ) ) );
        if ( $current_path === $target_path ) { return; }
        wp_safe_redirect( $target, 302 );
        exit;
    }

    public function apply_runtime_text_direction() {
        if ( is_admin() && ! wp_doing_ajax() ) { return; }
        $code = ITKT_Languages::instance()->current_code();
        global $wp_locale;
        if ( is_object( $wp_locale ) ) {
            $wp_locale->text_direction = ITKT_Languages::instance()->full_rtl_layout( $code ) ? 'rtl' : 'ltr';
        }
    }

    /**
     * Return the latest visible/global translation maps for one active language.
     *
     * This endpoint is deliberately read-only and contains no private/customer information.
     * It solves a practical caching problem: WooCommerce/WoodMart fragments and page caches can
     * otherwise keep an older localized JavaScript payload even after a translation was saved.
     */
    public function ajax_runtime_strings() {
        $code = sanitize_key( isset( $_REQUEST['lang'] ) ? wp_unslash( $_REQUEST['lang'] ) : '' );
        $active = ITKT_Languages::instance()->get_active();
        if ( ! $code || empty( $active[ $code ] ) ) {
            wp_send_json_error( array( 'message' => 'Invalid language.' ), 400 );
        }

        $visual = array();
        $global = array( 'exact' => array(), 'patterns' => array() );
        $commerce = array();
        $taxonomy = array( 'terms' => array(), 'attributes' => array() );
        if ( class_exists( 'ITKT_Strings' ) ) {
            $visual = ITKT_Strings::instance()->visual_runtime_map( $code );
            $global = ITKT_Strings::instance()->global_runtime_map( $code );
        }
        if ( class_exists( 'ITKT_Product_Translations' ) && ITKT_Plugin::module_enabled( 'woocommerce' ) ) {
            $commerce = ITKT_Product_Translations::instance()->frontend_cart_translation_map( $code );
        }
        if ( class_exists( 'ITKT_Term_Translations' ) && ITKT_Plugin::module_enabled( 'woocommerce' ) ) {
            $taxonomy = ITKT_Term_Translations::instance()->frontend_runtime_map( $code );
        }

        nocache_headers();
        $i18n = class_exists( 'ITKT_Strings' ) ? ITKT_Strings::instance()->js_i18n_runtime_map( $code ) : array( 'gettext'=>array(), 'gettextContext'=>array() );
        wp_send_json_success( array(
            'language' => $code,
            'visualTranslations' => $visual,
            'globalTranslations' => $global,
            'commerceTranslations' => $commerce,
            'taxonomyTranslations' => $taxonomy,
            'i18nTranslations' => $i18n,
        ) );
    }

    /**
     * Early @wordpress/i18n bridge. WooCommerce Cart/Checkout Blocks call __() in JavaScript;
     * intercepting i18n.gettext here applies ITKT overrides before React renders the component.
     */
    public function i18n_assets() {
        if ( is_admin() ) { return; }
        $code = ITKT_Languages::instance()->current_code();
        $map = array( 'gettext'=>array(), 'gettextContext'=>array() );
        if ( class_exists( 'ITKT_Strings' ) && ITKT_Plugin::module_enabled( 'strings' ) ) {
            $map = ITKT_Strings::instance()->js_i18n_runtime_map( $code );
        }
        wp_enqueue_script(
            'itkt-i18n-runtime',
            ITKT_URL . 'public/assets/i18n-runtime.js',
            array( 'wp-hooks', 'wp-i18n' ),
            ITKT_VERSION,
            false
        );
        wp_localize_script( 'itkt-i18n-runtime', 'ITKTI18nRuntime', array(
            'language' => $code,
            'defaultLanguage' => ITKT_Languages::instance()->get_default_code(),
            'gettext' => (array)($map['gettext'] ?? array()),
            'gettextContext' => (array)($map['gettextContext'] ?? array()),
        ) );
    }

    /** Build canonical account links with a short-lived server rescue marker. */
    private function frontend_account_endpoint_urls( $code ) {
        $urls = array();
        if ( ! ITKT_Plugin::module_enabled( 'woocommerce' ) || ! function_exists( 'wc_get_page_id' ) ) { return $urls; }
        $code = sanitize_key( (string) $code );
        if ( ! $code || $code === ITKT_Languages::instance()->get_default_code() ) { return $urls; }

        $account_id = absint( wc_get_page_id( 'myaccount' ) );
        if ( ! $account_id ) { return $urls; }
        $base = $this->language_url_for_system_page( $account_id, $code, false );
        if ( ! $base ) { return $urls; }

        $urls['dashboard'] = $base;
        foreach ( $this->woocommerce_account_endpoint_map() as $key => $slug ) {
            $key  = sanitize_key( (string) $key );
            $slug = trim( (string) $slug, '/' );
            if ( ! $key || '' === $slug || 'customer-logout' === $key || 'lost-password' === $key ) { continue; }
            $pretty = user_trailingslashit( trailingslashit( $base ) . $slug );
            $urls[ $key ] = add_query_arg( 'itkt_wc_endpoint', $key, $pretty );
        }
        return array_filter( $urls );
    }

    public function assets() {
        wp_enqueue_style( 'itkt-frontend', ITKT_URL . 'public/assets/frontend.css', array(), ITKT_VERSION );
        wp_enqueue_script( 'itkt-frontend', ITKT_URL . 'public/assets/frontend.js', array(), ITKT_VERSION, true );
        $cookie_path = (string) wp_parse_url( $this->raw_home_url( '/' ), PHP_URL_PATH );
        if ( '' === $cookie_path ) { $cookie_path = '/'; }
        $current_lang = ITKT_Languages::instance()->current_code();
        $visual_map = array();
        $global_string_map = array( 'exact'=>array(), 'patterns'=>array() );
        $commerce_map = array();
        $taxonomy_map = array( 'terms'=>array(), 'attributes'=>array() );
        if ( class_exists( 'ITKT_Strings' ) ) {
            $visual_map = ITKT_Strings::instance()->visual_runtime_map( $current_lang );
            $global_string_map = ITKT_Strings::instance()->global_runtime_map( $current_lang );
        }
        if ( class_exists( 'ITKT_Product_Translations' ) && ITKT_Plugin::module_enabled( 'woocommerce' ) ) {
            $commerce_map = ITKT_Product_Translations::instance()->frontend_cart_translation_map( $current_lang );
        }
        if ( class_exists( 'ITKT_Term_Translations' ) && ITKT_Plugin::module_enabled( 'woocommerce' ) ) {
            $taxonomy_map = ITKT_Term_Translations::instance()->frontend_runtime_map( $current_lang );
        }
        $account_endpoint_urls = $this->frontend_account_endpoint_urls( $current_lang );
        wp_localize_script( 'itkt-frontend', 'ITKTFrontend', array(
            'cookieName'         => 'itkt_language',
            'cookiePath'         => $cookie_path,
            'remember'           => ! empty( get_option( 'itkt_settings', array() )['remember_language'] ),
            'currentLang'        => $current_lang,
            'defaultLang'        => ITKT_Languages::instance()->get_default_code(),
            'activeCodes'        => array_keys( ITKT_Languages::instance()->get_active() ),
            'homeUrl'            => $this->raw_home_url( '/' ),
            'runtimeAjaxUrl'     => admin_url( 'admin-ajax.php' ),
            'runtimeAction'      => 'itkt_runtime_strings',
            'visualTranslations' => $visual_map,
            'globalTranslations' => $global_string_map,
            'commerceTranslations' => $commerce_map,
            'taxonomyTranslations' => $taxonomy_map,
            'accountEndpointUrls' => $account_endpoint_urls,
            'accountEndpointMarker' => 'itkt_wc_endpoint',
            'accountEndpointValueMarker' => 'itkt_wc_value',
        ) );
    }

    public function locale( $locale ) {
        // Registered during init, not during bootstrap. This avoids fatal recursion on managed hosts.
        return ITKT_Languages::instance()->runtime_locale( $locale );
    }

    public function language_attributes( $output ) {
        if ( is_admin() ) { return $output; }
        $code = ITKT_Languages::instance()->current_code();
        $active = ITKT_Languages::instance()->get_active();
        if ( empty( $active[ $code ] ) ) { return $output; }

        $output = preg_replace( '/\blang=(?:"[^"]*"|\'[^\']*\')/i', 'lang="' . esc_attr( $code ) . '"', (string) $output );
        if ( false === stripos( (string) $output, 'lang=' ) ) { $output = 'lang="' . esc_attr( $code ) . '" ' . trim( (string) $output ); }
        $dir = ITKT_Languages::instance()->full_rtl_layout( $code ) ? 'rtl' : 'ltr';
        if ( preg_match( '/\bdir=(?:"[^"]*"|\'[^\']*\')/i', (string) $output ) ) {
            $output = preg_replace( '/\bdir=(?:"[^"]*"|\'[^\']*\')/i', 'dir="' . $dir . '"', (string) $output );
        } else { $output .= ' dir="' . $dir . '"'; }
        return trim( (string) $output );
    }

    public function body_classes( $classes ) {
        if ( is_admin() ) { return $classes; }
        $code = ITKT_Languages::instance()->current_code();
        $classes[] = 'itkt-lang-' . sanitize_html_class( $code );
        if ( 'rtl' === ITKT_Languages::instance()->effective_direction( $code ) ) {
            $classes[] = 'itkt-text-language-rtl';
            if ( ITKT_Languages::instance()->full_rtl_layout( $code ) ) {
                $classes[] = 'itkt-layout-rtl';
            } elseif ( ITKT_Languages::instance()->content_rtl_layout( $code ) ) {
                $classes[] = 'itkt-content-layout-rtl';
            } else {
                $classes[] = 'itkt-layout-preserve-ltr';
            }
        }
        return array_unique( $classes );
    }

    public function shortcode_switcher( $atts = array() ) {
        $atts = shortcode_atts( array( 'labels' => '0', 'style' => 'flags', 'names' => '0' ), $atts, 'itkt_language_switcher' );
        $languages = ITKT_Languages::instance()->get_active();
        if ( count( $languages ) < 2 ) { return ''; }
        $current = ITKT_Languages::instance()->current_code();
        $html = '<nav class="itkt-language-switcher itkt-style-' . esc_attr( sanitize_key( $atts['style'] ) ) . '" aria-label="Language switcher">';
        foreach ( $languages as $code => $lang ) {
            $label = '<span class="itkt-flag" aria-hidden="true">' . esc_html( $lang['flag'] ) . '</span>';
            if ( '1' === (string) $atts['labels'] ) { $label .= '<span class="itkt-code">' . esc_html( strtoupper( $code ) ) . '</span>'; }
            if ( '1' === (string) $atts['names'] ) { $label .= '<span class="itkt-name">' . esc_html( $lang['native_name'] ) . '</span>'; }
            $html .= '<a class="itkt-lang-link ' . ( $code === $current ? 'is-active' : '' ) . '" href="' . esc_url( $this->language_url( $code ) ) . '" data-itkt-lang="' . esc_attr( $code ) . '" hreflang="' . esc_attr( $code ) . '" lang="' . esc_attr( $code ) . '"' . ( $code === $current ? ' aria-current="page"' : '' ) . '>' . $label . '</a>';
        }
        return $html . '</nav>';
    }

    /** Build a language URL for the current request. */
    public function language_url( $code ) {
        $code = sanitize_key( (string) $code );
        $catalog = ITKT_Languages::instance()->catalog();
        if ( empty( $catalog[ $code ] ) ) { $code = ITKT_Languages::instance()->get_default_code(); }

        // My Account is stateful. When switching language from /orders/, /downloads/,
        // /edit-address/, /edit-account/, etc., keep the same WooCommerce endpoint instead of
        // sending the visitor back to the account dashboard.
        $account_url = $this->language_url_for_current_account_request( $code );
        if ( $account_url ) { return $account_url; }

        // Cart, Checkout (including order-pay/order-received) and Shop use their physical
        // WooCommerce system pages so language switching cannot jump to a translated duplicate.
        $system_url = $this->language_url_for_current_woocommerce_system_request( $code );
        if ( $system_url ) { return $system_url; }

        if ( is_singular() ) {
            $id = get_queried_object_id();
            if ( $id ) { return $this->language_url_for_post( $id, $code ); }
        }

        // A failed/legacy product language URL can be a WordPress 404 even though the product
        // itself exists. Recover it from the current path so every flag always links to the
        // same physical product in the selected target language.
        $request_product_id = $this->current_product_id_from_request();
        if ( $request_product_id ) {
            return $this->language_url_for_post( $request_product_id, $code );
        }

        if ( ( is_tax() || is_category() || is_tag() ) && class_exists( 'ITKT_SEO' ) ) {
            $term = get_queried_object();
            if ( $term instanceof WP_Term ) {
                $url = ITKT_SEO::instance()->translated_term_url( $term, $code );
                if ( $url ) { return $url; }
            }
        }

        $path = $this->current_source_path();
        return $this->build_directory_url( $code, $path );
    }

    /** Prefix any internal WordPress/WooCommerce URL with the requested language once. */
    public function language_url_from_url( $url, $code, $preserve_url_query = true ) {
        $url = (string) $url;
        if ( '' === $url ) { return $url; }
        $home_host = (string) wp_parse_url( $this->raw_home_url( '/' ), PHP_URL_HOST );
        $url_host  = (string) wp_parse_url( $url, PHP_URL_HOST );
        if ( $url_host && $home_host && strtolower( $url_host ) !== strtolower( $home_host ) ) { return $url; }

        $path = $this->relative_home_path( $url );
        $path = $this->strip_language_prefix_from_path( $path );
        $new_url = $this->build_directory_url( $code, $path, false );
        if ( $preserve_url_query ) {
            $query = (string) wp_parse_url( $url, PHP_URL_QUERY );
            if ( $query ) {
                $args = array();
                wp_parse_str( $query, $args );
                unset( $args['itkt_lang'], $args['lang'] );
                if ( $args ) { $new_url = add_query_arg( $args, $new_url ); }
            }
        }
        $fragment = (string) wp_parse_url( $url, PHP_URL_FRAGMENT );
        if ( $fragment ) { $new_url .= '#' . rawurlencode( rawurldecode( $fragment ) ); }
        return $new_url;
    }

    public function language_url_for_post( $post_id, $code, $preserve_current_query = true ) {
        $post_id = absint( $post_id );
        $code    = sanitize_key( (string) $code );
        if ( ! $post_id ) { return $this->build_directory_url( $code, '', $preserve_current_query ); }

        $source_id = $this->default_source_post_id( $post_id );

        // Product slugs are optional. If the requested target language has no explicitly
        // translated slug, use the default-language product URL instead of generating or
        // carrying a broken language slug into a 404 page.
        $default = ITKT_Languages::instance()->get_default_code();
        $settings = get_option( 'itkt_settings', array() );
        if ( 'product' === get_post_type( $source_id ) && $code !== $default && ! empty( $settings['product_slug_fallback_default'] ) && class_exists( 'ITKT_Product_Translations' ) ) {
            if ( ! ITKT_Product_Translations::instance()->has_explicit_slug( $source_id, $code ) ) {
                $code = $default;
            }
        }

        if ( class_exists( 'ITKT_SEO' ) ) {
            $path = ITKT_SEO::instance()->translated_post_path( $source_id, $code );
        } elseif ( 'page' === get_post_type( $source_id ) && $source_id === absint( get_option( 'page_on_front' ) ) ) {
            $path = '';
        } else {
            $path = $this->post_source_path( $source_id );
        }
        return $this->build_directory_url( $code, $path, $preserve_current_query );
    }


    /**
     * Build a language URL for a physical WordPress/WooCommerce system page without switching to
     * a translated duplicate or translated SEO slug.
     *
     * WooCommerce Cart, Checkout and especially My Account are stateful system pages. Their
     * endpoints are attached to the physical page configured in WooCommerce. Using the physical
     * page path as the public base keeps /LANG/my-account/orders/ tied to the same WooCommerce
     * endpoint state while the storefront language remains virtual in the /LANG/ prefix.
     */
    public function language_url_for_system_page( $post_id, $code, $preserve_current_query = false ) {
        $post_id = absint( $post_id );
        $code    = sanitize_key( (string) $code );
        if ( ! $post_id ) { return ''; }

        $path = $this->post_source_path( $post_id );
        return $this->build_directory_url( $code, $path, $preserve_current_query );
    }

    public function is_building_permalink() { return (bool) $this->in_permalink_filter; }

    public function source_path_for_post( $post_id ) { return $this->post_source_path( absint( $post_id ) ); }

    private function post_source_path( $post_id ) {
        $previous = $this->in_permalink_filter;
        $this->in_permalink_filter = true;
        $url = get_permalink( $post_id );
        $this->in_permalink_filter = $previous;
        if ( ! $url ) { return ''; }
        return $this->strip_language_prefix_from_path( $this->relative_home_path( $url ) );
    }

    /** Public URL builder used by SEO/adapters without exposing internal routing details. */
    public function public_language_url( $code, $path = '', $preserve_current_query = false ) {
        return $this->build_directory_url( sanitize_key( (string) $code ), $path, (bool) $preserve_current_query );
    }

    private function build_directory_url( $code, $path, $preserve_current_query = true ) {
        $default = ITKT_Languages::instance()->get_default_code();
        $path    = trim( (string) $path, '/' );

        // Plain permalinks cannot support clean virtual directories safely.
        if ( ! get_option( 'permalink_structure' ) ) {
            $base = $path ? $this->raw_home_url( '/' . $path ) : $this->raw_home_url( '/' );
            return $code === $default ? $base : add_query_arg( 'itkt_lang', $code, $base );
        }

        if ( $code === $default ) {
            $url = $path ? $this->raw_home_url( '/' . $path . '/' ) : $this->raw_home_url( '/' );
        } else {
            $url = $this->raw_home_url( '/' . $code . '/' . ( $path ? $path . '/' : '' ) );
        }

        // Preserve useful current query arguments while removing old language selectors.
        if ( ! $preserve_current_query ) { return $url; }
        $args = array();
        foreach ( (array) $_GET as $key => $value ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $key = sanitize_key( $key );
            if ( in_array( $key, array( 'itkt_lang', 'lang', 'preview', 'preview_id', 'preview_nonce' ), true ) ) { continue; }
            if ( is_scalar( $value ) ) { $args[ $key ] = sanitize_text_field( wp_unslash( $value ) ); }
        }
        return $args ? add_query_arg( $args, $url ) : $url;
    }

    private function raw_home_url( $path = '' ) {
        $base = untrailingslashit( (string) get_option( 'home' ) );
        if ( '' === $base ) { $base = untrailingslashit( (string) get_option( 'siteurl' ) ); }
        $path = ltrim( (string) $path, '/' );
        return $path ? $base . '/' . $path : $base . '/';
    }

    private function current_source_path() {
        $uri  = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '/';
        $path = (string) wp_parse_url( $uri, PHP_URL_PATH );
        $home = (string) wp_parse_url( $this->raw_home_url( '/' ), PHP_URL_PATH );
        $home = '/' . trim( $home, '/' );
        if ( '/' !== $home && 0 === strpos( $path, $home ) ) { $path = substr( $path, strlen( $home ) ); }
        $path = trim( $path, '/' );

        return $this->strip_language_prefix_from_path( trim( $path, '/' ) );
    }

    private function strip_language_prefix_from_path( $path ) {
        $path = trim( (string) $path, '/' );
        if ( ! $path ) { return ''; }
        $parts = explode( '/', $path );
        $catalog = ITKT_Languages::instance()->catalog();
        if ( isset( $catalog[ sanitize_key( $parts[0] ) ] ) ) { array_shift( $parts ); }
        return trim( implode( '/', $parts ), '/' );
    }

    private function relative_home_path( $url ) {
        $path = (string) wp_parse_url( $url, PHP_URL_PATH );
        $home = (string) wp_parse_url( $this->raw_home_url( '/' ), PHP_URL_PATH );
        $home = '/' . trim( $home, '/' );
        if ( '/' !== $home && 0 === strpos( $path, $home ) ) { $path = substr( $path, strlen( $home ) ); }
        return trim( $path, '/' );
    }

    /** Return a language prefix found in the actual browser URL, not the queried post language. */
    private function requested_language_code() {
        $uri  = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
        $path = trim( (string) wp_parse_url( $uri, PHP_URL_PATH ), '/' );
        $home = trim( (string) wp_parse_url( $this->raw_home_url( '/' ), PHP_URL_PATH ), '/' );
        if ( $home && 0 === strpos( $path, $home ) ) { $path = trim( substr( $path, strlen( $home ) ), '/' ); }
        if ( ! $path ) { return ''; }
        $first = sanitize_key( explode( '/', $path )[0] );
        return isset( ITKT_Languages::instance()->catalog()[ $first ] ) ? $first : '';
    }

    public function menu_locations( $locations ) {
        if ( is_admin() ) { return $locations; }
        $code = ITKT_Languages::instance()->current_code();
        $map = get_option( 'itkt_menu_map', array() );
        foreach ( (array) $locations as $location => $menu_id ) {
            if ( ! empty( $map[ $location ][ $code ] ) ) { $locations[ $location ] = absint( $map[ $location ][ $code ] ); }
        }
        return $locations;
    }

    public function nav_menu_args( $args ) {
        if ( is_admin() ) { return $args; }
        $code    = ITKT_Languages::instance()->current_code();
        $default = ITKT_Languages::instance()->get_default_code();
        $map     = get_option( 'itkt_menu_map', array() );

        // Standard WordPress theme_location mapping.
        if ( ! empty( $args['theme_location'] ) ) {
            $location = sanitize_key( $args['theme_location'] );
            if ( ! empty( $map[ $location ][ $code ] ) ) {
                $args['menu'] = absint( $map[ $location ][ $code ] );
                return $args;
            }
        }

        // WoodMart/Header builders often pass a menu ID directly and leave theme_location empty.
        $source_menu_id = $this->menu_id_from_arg( $args['menu'] ?? 0 );
        if ( $source_menu_id ) {
            $direct = get_option( 'itkt_direct_menu_map', array() );
            if ( ! empty( $direct[ $source_menu_id ][ $code ] ) ) {
                $args['menu'] = absint( $direct[ $source_menu_id ][ $code ] );
                return $args;
            }

            // Also infer direct-menu switching from an existing location mapping.
            $theme_locations = get_nav_menu_locations();
            foreach ( (array) $map as $location => $language_menus ) {
                $default_menu = absint( $language_menus[ $default ] ?? ( $theme_locations[ $location ] ?? 0 ) );
                if ( $default_menu === $source_menu_id && ! empty( $language_menus[ $code ] ) ) {
                    $args['menu'] = absint( $language_menus[ $code ] );
                    return $args;
                }
            }
        }
        return $args;
    }

    private function menu_id_from_arg( $menu ) {
        if ( is_numeric( $menu ) ) { return absint( $menu ); }
        if ( is_object( $menu ) && ! empty( $menu->term_id ) ) { return absint( $menu->term_id ); }
        if ( is_string( $menu ) && '' !== trim( $menu ) ) {
            $object = wp_get_nav_menu_object( $menu );
            if ( $object && ! is_wp_error( $object ) ) { return absint( $object->term_id ); }
        }
        return 0;
    }

    /**
     * Language menus and untouched default menus can follow translated object titles.
     * Manually customized menu labels are preserved unless the item was auto-created by ITKT.
     */
    public function nav_menu_objects( $items, $args ) {
        if ( is_admin() ) { return $items; }
        $code = ITKT_Languages::instance()->current_code();
        if ( $code === ITKT_Languages::instance()->get_default_code() ) { return $items; }

        foreach ( (array) $items as $item ) {
            if ( empty( $item->ID ) ) { continue; }
            $source_object = absint( get_post_meta( $item->ID, '_itkt_source_object_id', true ) );
            if ( ! $source_object && ! empty( $item->object_id ) ) { $source_object = absint( $item->object_id ); }
            if ( ! $source_object ) { continue; }

            $auto_title = '1' === (string) get_post_meta( $item->ID, '_itkt_auto_title', true );
            if ( ! $auto_title && isset( $item->type, $item->object ) && 'post_type' === $item->type ) {
                $source_id = $this->default_source_post_id( $source_object );
                $source_title = get_post_field( 'post_title', $source_id );
                $auto_title = '' !== trim( (string) $source_title ) && trim( (string) $item->title ) === trim( (string) $source_title );
                $source_object = $source_id ?: $source_object;
            } elseif ( ! $auto_title && isset( $item->type, $item->object ) && 'taxonomy' === $item->type && taxonomy_exists( $item->object ) ) {
                $term = get_term( $source_object, $item->object );
                $auto_title = $term && ! is_wp_error( $term ) && trim( (string) $item->title ) === trim( (string) $term->name );
            }
            if ( ! $auto_title ) { continue; }

            if ( isset( $item->type ) && 'taxonomy' === $item->type && ITKT_Plugin::module_enabled( 'woocommerce' ) ) {
                $data = ITKT_Term_Translations::instance()->get( $source_object, $code );
                if ( ! empty( $data['name'] ) ) { $item->title = $data['name']; }
                continue;
            }
            if ( isset( $item->type, $item->object ) && 'post_type' === $item->type && 'product' === $item->object && ITKT_Plugin::module_enabled( 'woocommerce' ) ) {
                $data = ITKT_Product_Translations::instance()->get( $source_object, $code );
                if ( ! empty( $data['title'] ) ) { $item->title = $data['title']; }
                continue;
            }
            $target_id = $this->target_post_id( $source_object, $code );
            if ( $target_id ) {
                $title = get_post_field( 'post_title', $target_id );
                if ( '' !== trim( (string) $title ) ) { $item->title = $title; }
            }
        }
        return $items;
    }

    /** Every WordPress/WoodMart wp_nav_menu object link stays in the active language. */
    public function nav_menu_link_attributes( $atts, $menu_item, $args, $depth ) {
        if ( is_admin() || empty( $atts['href'] ) ) { return $atts; }
        $code = ITKT_Languages::instance()->current_code();

        if ( isset( $menu_item->type ) && 'post_type' === $menu_item->type && ! empty( $menu_item->object_id ) ) {
            $atts['href'] = $this->language_url_for_post( absint( $menu_item->object_id ), $code, false );
            return $atts;
        }

        // Taxonomy menu items should use the translated public term slug as well, not merely
        // receive a language prefix in front of the source-language category/tag path.
        if ( isset( $menu_item->type, $menu_item->object ) && 'taxonomy' === $menu_item->type && ! empty( $menu_item->object_id ) && class_exists( 'ITKT_SEO' ) ) {
            $term = get_term( absint( $menu_item->object_id ), (string) $menu_item->object );
            if ( $term instanceof WP_Term ) {
                $translated = ITKT_SEO::instance()->translated_term_url( $term, $code );
                if ( $translated ) {
                    $atts['href'] = $translated;
                    return $atts;
                }
            }
        }

        // Internal custom links get the current language prefix as well; external URLs stay untouched.
        $href      = (string) $atts['href'];
        $href_host = wp_parse_url( $href, PHP_URL_HOST );
        $home_host = wp_parse_url( $this->raw_home_url( '/' ), PHP_URL_HOST );
        $internal  = ( ! $href_host && str_starts_with( $href, '/' ) ) || ( $href_host && $home_host && strtolower( $href_host ) === strtolower( $home_host ) );
        if ( $internal ) {
            $path = $this->relative_home_path( $href );
            $catalog = ITKT_Languages::instance()->catalog();
            $parts = $path ? explode( '/', $path ) : array();
            if ( $parts && isset( $catalog[ sanitize_key( $parts[0] ) ] ) ) { array_shift( $parts ); $path = implode( '/', $parts ); }
            $new_href = $this->build_directory_url( $code, $path, false );

            $query = (string) wp_parse_url( $href, PHP_URL_QUERY );
            if ( $query ) {
                $query_args = array();
                wp_parse_str( $query, $query_args );
                if ( $query_args ) { $new_href = add_query_arg( $query_args, $new_href ); }
            }
            $fragment = (string) wp_parse_url( $href, PHP_URL_FRAGMENT );
            if ( $fragment ) { $new_href .= '#' . rawurlencode( rawurldecode( $fragment ) ); }
            $atts['href'] = $new_href;
        }
        return $atts;
    }

    public function translated_product_title( $title, $post_id = 0 ) {
        if ( is_admin() || $this->in_title_filter || ! $post_id || 'product' !== get_post_type( $post_id ) ) { return $title; }
        $code = ITKT_Languages::instance()->current_code();
        if ( $code === ITKT_Languages::instance()->get_default_code() ) { return $title; }
        $data = ITKT_Product_Translations::instance()->get( $post_id, $code );
        if ( ! empty( $data['title'] ) ) { return $data['title']; }
        return $title;
    }

    public function translated_product_content( $content ) {
        if ( is_admin() || ! is_singular( 'product' ) ) { return $content; }
        $id = get_queried_object_id();
        $code = ITKT_Languages::instance()->current_code();
        if ( $code === ITKT_Languages::instance()->get_default_code() ) { return $content; }
        $data = ITKT_Product_Translations::instance()->get( $id, $code );
        return ! empty( $data['description'] ) ? $data['description'] : $content;
    }

    public function translated_product_short_description( $content ) {
        if ( is_admin() ) { return $content; }
        $id = get_the_ID();
        if ( ! $id || 'product' !== get_post_type( $id ) ) { return $content; }
        $code = ITKT_Languages::instance()->current_code();
        if ( $code === ITKT_Languages::instance()->get_default_code() ) { return $content; }
        $data = ITKT_Product_Translations::instance()->get( $id, $code );
        return ! empty( $data['short_description'] ) ? wpautop( $data['short_description'] ) : $content;
    }

    public function translated_term( $term, $taxonomy ) {
        if ( is_admin() || ! is_object( $term ) || empty( $term->term_id ) ) { return $term; }
        $code = ITKT_Languages::instance()->current_code();
        if ( $code === ITKT_Languages::instance()->get_default_code() ) { return $term; }
        $data = ITKT_Term_Translations::instance()->get( $term->term_id, $code );
        if ( ! empty( $data['name'] ) ) { $term->name = $data['name']; }
        if ( isset( $data['description'] ) && '' !== trim( $data['description'] ) ) { $term->description = $data['description']; }
        return $term;
    }

    public function translated_attribute_label( $label, $name, $product = null ) {
        if ( is_admin() ) { return $label; }
        $code = ITKT_Languages::instance()->current_code();
        if ( $code === ITKT_Languages::instance()->get_default_code() ) { return $label; }
        $translated = ITKT_Term_Translations::instance()->get_attribute( $name, $code );
        return $translated ? $translated : $label;
    }
}
