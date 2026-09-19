<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class ITKT_Languages {
    private static $instance = null;
    private $locale_filters_registered = false;
    private $forced_context_codes = array();

    public static function instance() {
        if ( null === self::$instance ) { self::$instance = new self(); }
        return self::$instance;
    }

    /**
     * Compatibility no-op. Early locale switching before WordPress init caused HTTP 500
     * on some managed hosts. Storefront strings are translated through the normal ITKT
     * runtime/string layers; layout direction remains independent.
     */
    public function register_locale_filters() {
        return;
    }

    public function runtime_locale( $locale ) {
        // Backward-compatible no-op. Storefront language must never be coupled to the global
        // WordPress locale because that also changes theme/bootstrap behavior and previously
        // caused fatal recursion on managed hosts. ITKT reads target catalogs explicitly.
        return $locale;
    }

    public function catalog() {
        return array(
            'de' => array( 'name' => 'German', 'native_name' => 'Deutsch', 'locale' => 'de_DE', 'direction' => 'ltr', 'flag' => '🇩🇪' ),
            'ar' => array( 'name' => 'Arabic', 'native_name' => 'العربية', 'locale' => 'ar', 'direction' => 'rtl', 'flag' => '🇸🇦' ),
            'en' => array( 'name' => 'English', 'native_name' => 'English', 'locale' => 'en_US', 'direction' => 'ltr', 'flag' => '🇬🇧' ),
            'fr' => array( 'name' => 'French', 'native_name' => 'Français', 'locale' => 'fr_FR', 'direction' => 'ltr', 'flag' => '🇫🇷' ),
            'es' => array( 'name' => 'Spanish', 'native_name' => 'Español', 'locale' => 'es_ES', 'direction' => 'ltr', 'flag' => '🇪🇸' ),
            'tr' => array( 'name' => 'Turkish', 'native_name' => 'Türkçe', 'locale' => 'tr_TR', 'direction' => 'ltr', 'flag' => '🇹🇷' ),
            'sv' => array( 'name' => 'Swedish', 'native_name' => 'Svenska', 'locale' => 'sv_SE', 'direction' => 'ltr', 'flag' => '🇸🇪' ),
            'nl' => array( 'name' => 'Dutch', 'native_name' => 'Nederlands', 'locale' => 'nl_NL', 'direction' => 'ltr', 'flag' => '🇳🇱' ),
        );
    }

    public function get_all() {
        $stored  = get_option( 'itkt_languages', array() );
        $catalog = $this->catalog();
        $out     = array();
        if ( ! is_array( $stored ) ) { return $out; }
        foreach ( $stored as $code => $lang ) {
            if ( empty( $catalog[ $code ] ) ) { continue; }
            $out[ $code ] = array_merge( $catalog[ $code ], array( 'direction_auto' => true, 'direction_override' => $catalog[ $code ]['direction'], 'content_rtl_layout' => false, 'full_rtl_layout' => false ), (array) $lang, array( 'code' => $code ) );
        }
        return $out;
    }

    public function get_active() {
        return array_filter( $this->get_all(), function( $lang ) { return ! empty( $lang['active'] ); } );
    }

    public function get_default_code() {
        $settings = get_option( 'itkt_settings', array() );
        $code = ! empty( $settings['default_language'] ) ? sanitize_key( $settings['default_language'] ) : 'de';
        return isset( $this->catalog()[ $code ] ) ? $code : 'de';
    }

    /**
     * Detect language from parsed query vars, legacy query args, clean URL prefix, then the
     * queried translated post. URL-prefix detection also works early enough for locale filters.
     */
    public function current_code() {
        $active  = $this->get_active();
        $default = $this->get_default_code();

        // Request-independent internal context. It never switches the global WordPress locale.
        // WooCommerce e-mail rendering uses this stack only to force ITKT back to the configured
        // default language so frontend selections cannot leak into emails.
        if ( ! empty( $this->forced_context_codes ) ) {
            $forced = sanitize_key( (string) end( $this->forced_context_codes ) );
            if ( $forced && isset( $active[ $forced ] ) ) { return $forced; }
        }

        $code = '';

        if ( function_exists( 'get_query_var' ) ) {
            $query_code = sanitize_key( (string) get_query_var( 'itkt_lang', '' ) );
            if ( $query_code ) { $code = $query_code; }
        }

        if ( ! $code && isset( $_GET['itkt_lang'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $code = sanitize_key( wp_unslash( $_GET['itkt_lang'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        } elseif ( ! $code && isset( $_GET['lang'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $code = sanitize_key( wp_unslash( $_GET['lang'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        }

        if ( ! $code && ! is_admin() && ! empty( $_SERVER['REQUEST_URI'] ) ) {
            $uri  = wp_unslash( $_SERVER['REQUEST_URI'] );
            $path = trim( (string) wp_parse_url( $uri, PHP_URL_PATH ), '/' );
            $home = trim( (string) wp_parse_url( (string) get_option( 'home' ), PHP_URL_PATH ), '/' );
            if ( $home && 0 === strpos( $path, $home ) ) { $path = trim( substr( $path, strlen( $home ) ), '/' ); }
            if ( $path ) {
                $first = sanitize_key( explode( '/', $path )[0] );
                if ( isset( $this->catalog()[ $first ] ) ) { $code = $first; }
            }
        }

        // Only singular posts/pages/products own the _itkt_language post meta. On taxonomy
// archives get_queried_object_id() may build the queried WP_Term via get_term(). Because
// ITKT also filters get_term() and that filter calls current_code(), doing this lookup on
// a category/tag archive can recurse until PHP exhausts memory and returns HTTP 500.
        if ( ! $code && ! is_admin() && function_exists( 'is_singular' ) && is_singular() && function_exists( 'get_queried_object_id' ) ) {
            $queried_id = absint( get_queried_object_id() );
            if ( $queried_id ) { $code = sanitize_key( (string) get_post_meta( $queried_id, '_itkt_language', true ) ); }
        }

        $settings = get_option( 'itkt_settings', array() );
        // WooCommerce refreshes cart/checkout through admin-ajax.php, ?wc-ajax=... and the Store API.
        // Those requests frequently have no language prefix of their own. The current storefront
        // language therefore needs a short-lived browser/session signal even when the optional
        // "remember language" preference is disabled.
        $frontend_or_ajax = ! is_admin() || wp_doing_ajax() || isset( $_REQUEST['wc-ajax'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if ( ! $code && $frontend_or_ajax && ! empty( $_COOKIE['itkt_language'] ) ) {
            $cookie_code = sanitize_key( wp_unslash( $_COOKIE['itkt_language'] ) );
            if ( isset( $active[ $cookie_code ] ) ) { $code = $cookie_code; }
        }

        // WooCommerce session is a second fallback for checkout/cart fragment requests and REST
        // Store API traffic where the request URI itself no longer contains /ar/, /en/, etc.
        if ( ! $code && $frontend_or_ajax && function_exists( 'WC' ) && WC() && isset( WC()->session ) && WC()->session ) {
            $session_code = sanitize_key( (string) WC()->session->get( 'itkt_language' ) );
            if ( isset( $active[ $session_code ] ) ) { $code = $session_code; }
        }
        return ( $code && isset( $active[ $code ] ) ) ? $code : $default;
    }


    /**
     * Push a temporary ITKT language context without touching the WordPress locale.
     * Used for tightly scoped runtime isolation, e.g. keeping email output on the default language.
     */
    public function push_context_code( $code ) {
        $code = sanitize_key( (string) $code );
        $active = $this->get_active();
        if ( ! $code || ! isset( $active[ $code ] ) ) { return false; }
        $this->forced_context_codes[] = $code;
        return true;
    }

    /** Pop one temporary language context. */
    public function pop_context_code() {
        if ( empty( $this->forced_context_codes ) ) { return ''; }
        return (string) array_pop( $this->forced_context_codes );
    }

    /** Current forced context, primarily for diagnostics/tests. */
    public function forced_context_code() {
        if ( empty( $this->forced_context_codes ) ) { return ''; }
        return sanitize_key( (string) end( $this->forced_context_codes ) );
    }

    public function effective_direction( $code ) {
        $code = sanitize_key( (string) $code );
        $languages = $this->get_all();
        $catalog = $this->catalog();
        $lang = $languages[ $code ] ?? ( $catalog[ $code ] ?? array() );
        if ( ! $lang ) { return 'ltr'; }
        $auto = ! isset( $lang['direction_auto'] ) || ! empty( $lang['direction_auto'] );
        if ( $auto ) { return ( $catalog[ $code ]['direction'] ?? 'ltr' ) === 'rtl' ? 'rtl' : 'ltr'; }
        return ( $lang['direction_override'] ?? 'ltr' ) === 'rtl' ? 'rtl' : 'ltr';
    }


    public function content_rtl_layout( $code ) {
        $code = sanitize_key( (string) $code );
        $languages = $this->get_all();
        if ( 'rtl' !== $this->effective_direction( $code ) ) { return false; }
        if ( ! empty( $languages[ $code ]['full_rtl_layout'] ) ) { return false; }
        return ! empty( $languages[ $code ]['content_rtl_layout'] );
    }

    public function full_rtl_layout( $code ) {
        $code = sanitize_key( (string) $code );
        $languages = $this->get_all();
        return 'rtl' === $this->effective_direction( $code ) && ! empty( $languages[ $code ]['full_rtl_layout'] );
    }

    public function save_direction_settings( $posted ) {
        $languages = $this->get_all();
        foreach ( $languages as $code => $lang ) {
            $row = isset( $posted[ $code ] ) && is_array( $posted[ $code ] ) ? $posted[ $code ] : array();
            $languages[ $code ]['direction_auto'] = ! empty( $row['direction_auto'] );
            $override = sanitize_key( $row['direction_override'] ?? ( $lang['direction'] ?? 'ltr' ) );
            $languages[ $code ]['direction_override'] = in_array( $override, array( 'ltr', 'rtl' ), true ) ? $override : 'ltr';
            $languages[ $code ]['full_rtl_layout'] = ! empty( $row['full_rtl_layout'] );
            $languages[ $code ]['content_rtl_layout'] = ! $languages[ $code ]['full_rtl_layout'] && ! empty( $row['content_rtl_layout'] );
        }
        update_option( 'itkt_languages', $languages );
        return true;
    }

    public function save_from_setup( $default_code, $additional_codes ) {
        $catalog = $this->catalog();
        $default_code = isset( $catalog[ $default_code ] ) ? $default_code : 'de';
        $codes = array_unique( array_merge( array( $default_code ), array_map( 'sanitize_key', (array) $additional_codes ) ) );
        $languages = array();
        foreach ( $codes as $code ) {
            if ( empty( $catalog[ $code ] ) ) { continue; }
            $languages[ $code ] = array_merge( $catalog[ $code ], array(
                'code' => $code,
                'active' => true,
                'default' => ( $code === $default_code ),
                'direction_auto' => true,
                'direction_override' => $catalog[ $code ]['direction'],
                'content_rtl_layout' => false,
                'full_rtl_layout' => false,
            ) );
        }
        update_option( 'itkt_languages', $languages );
        return $languages;
    }

    public function add_language( $code ) {
        $catalog = $this->catalog();
        if ( empty( $catalog[ $code ] ) ) { return new WP_Error( 'invalid_language', __( 'Unknown language.', 'it-kayali-translate' ) ); }
        $languages = $this->get_all();
        if ( isset( $languages[ $code ] ) ) {
            $languages[ $code ]['active'] = true;
        } else {
            $languages[ $code ] = array_merge( $catalog[ $code ], array( 'code' => $code, 'active' => true, 'default' => false, 'direction_auto' => true, 'direction_override' => $catalog[ $code ]['direction'], 'content_rtl_layout' => false, 'full_rtl_layout' => false ) );
        }
        update_option( 'itkt_languages', $languages );
        return true;
    }

    public function toggle_language( $code ) {
        $languages = $this->get_all();
        if ( empty( $languages[ $code ] ) || ! empty( $languages[ $code ]['default'] ) ) { return false; }
        $languages[ $code ]['active'] = empty( $languages[ $code ]['active'] );
        update_option( 'itkt_languages', $languages );
        return true;
    }
}
