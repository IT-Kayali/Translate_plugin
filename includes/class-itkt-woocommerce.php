<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Optional WooCommerce bridge. No WooCommerce classes are referenced during plugin bootstrap,
 * so IT-Kayali Translate continues to work on normal WordPress sites.
 */
class ITKT_WooCommerce {
    private static $instance = null;
    private $email_default_context_depth = 0;

    public static function instance() {
        if ( null === self::$instance ) { self::$instance = new self(); }
        return self::$instance;
    }

    private function __construct() {
        add_filter( 'post_type_link', array( $this, 'product_permalink' ), 20, 4 );
        add_filter( 'woocommerce_loop_product_link', array( $this, 'loop_product_link' ), 20, 2 );
        add_filter( 'term_link', array( $this, 'term_link' ), 20, 3 );

        add_filter( 'woocommerce_get_cart_url', array( $this, 'woocommerce_page_url' ), 20 );
        add_filter( 'woocommerce_get_checkout_url', array( $this, 'woocommerce_page_url' ), 20 );
        add_filter( 'woocommerce_get_myaccount_page_permalink', array( $this, 'woocommerce_page_url' ), PHP_INT_MAX );
        add_filter( 'woocommerce_get_shop_page_permalink', array( $this, 'woocommerce_page_url' ), 20 );
        add_filter( 'woocommerce_get_endpoint_url', array( $this, 'endpoint_url' ), PHP_INT_MAX, 4 );
        add_filter( 'woocommerce_breadcrumb_home_url', array( $this, 'woocommerce_home_url' ), 20 );
        add_filter( 'woocommerce_return_to_shop_redirect', array( $this, 'woocommerce_page_url' ), 20 );
        add_filter( 'woocommerce_cart_item_permalink', array( $this, 'cart_item_permalink' ), 30, 3 );
        add_filter( 'woocommerce_order_item_permalink', array( $this, 'order_item_permalink' ), 30, 3 );
        // Core WooCommerce layered-nav and many WoodMart filters build links from the current
        // source URL plus query args. Normalize those links back onto /en/, /ar/, ... so an AJAX
        // filter click never drops the storefront language.
        add_filter( 'woocommerce_layered_nav_link', array( $this, 'layered_nav_link' ), 40, 3 );

        // Last-resort DOM correction for WooCommerce/WoodMart account navigation. PHP remains
        // authoritative, but this prevents theme-cached account tiles from retaining a base-only
        // /LANG/my-account/ URL after the visitor changes language.
        add_action( 'wp_footer', array( $this, 'account_endpoint_link_fallback' ), 100 );

        // Keep WooCommerce cart/checkout AJAX and Store API requests aware of the selected
        // storefront language. The saved order value is informational only and never controls
        // emails, invoices, packing slips or admin output.
        add_action( 'woocommerce_init', array( $this, 'sync_session_language' ), 20 );
        add_action( 'woocommerce_checkout_create_order', array( $this, 'store_order_language' ), 20, 2 );
        // Checkout Block / Store API does not rely on the classic checkout_create_order path.
        // Persist the same language metadata on block-based orders as well.
        add_action( 'woocommerce_store_api_checkout_update_order_from_request', array( $this, 'store_api_order_language' ), 20, 2 );
        add_action( 'woocommerce_store_api_checkout_update_order_meta', array( $this, 'store_api_order_language_meta' ), 20, 1 );
        add_action( 'woocommerce_store_api_checkout_order_created', array( $this, 'store_api_order_language_meta' ), 20, 1 );

        // ITKT is a storefront translator. WooCommerce e-mails must stay in the shop/site
        // language, regardless of the language a visitor selected in the frontend. WooCommerce
        // calls these locale filters around the whole e-mail render; use them only to force ITKT's
        // own runtime back to the configured default language. The WordPress locale itself is not
        // changed by ITKT.
        add_filter( 'woocommerce_allow_switching_email_locale', array( $this, 'email_default_context_start' ), 1, 2 );
        add_filter( 'woocommerce_allow_restoring_email_locale', array( $this, 'email_default_context_end' ), PHP_INT_MAX, 2 );

        // Make the stored order language visible to shop administrators without changing order data.
        add_action( 'woocommerce_admin_order_data_after_billing_address', array( $this, 'admin_order_language' ), 20, 1 );

        // Keep Store API / AJAX fragments on the selected storefront language. WooCommerce Blocks
        // may render outside classic cart-item filters, so the language cookie/session must be
        // authoritative for every request that rebuilds cart or checkout HTML/JSON.
        add_filter( 'woocommerce_get_cart_item_from_session', array( $this, 'cart_item_from_session' ), 20, 3 );

        // WooCommerce tax-rate labels (for example "MwSt.") are store configuration data,
        // not gettext strings. WooCommerce Blocks places that label inside the dynamic %s value
        // of "Including %s". Translate the rate label at its canonical WooCommerce hook so
        // classic templates, Store API responses and Blocks all receive the same language.
        add_filter( 'woocommerce_rate_label', array( $this, 'translate_rate_label' ), 40, 2 );

        // Invoices, packing slips and other generated documents intentionally receive no
        // storefront-language overrides from ITKT. Their plugin/site language and the original
        // WooCommerce order snapshot remain authoritative.
    }


    /**
     * Translate a configured WooCommerce tax-rate label without changing the stored rate.
     * User overrides are stored per tax-rate ID. A small standards fallback is used only for
     * well-known VAT labels such as the German "MwSt." when no override exists yet.
     */
    public function translate_rate_label( $label, $rate_id ) {
        $label = (string) $label;
        if ( '' === trim( $label ) || ( is_admin() && ! wp_doing_ajax() ) ) { return $label; }

        $language = ITKT_Languages::instance()->current_code();
        $default  = ITKT_Languages::instance()->get_default_code();
        if ( ! $language || $language === $default ) { return $label; }

        $source = $this->raw_tax_rate_label( $rate_id, $label );
        if ( '' === $source ) { $source = $label; }

        if ( class_exists( 'ITKT_Strings' ) && ITKT_Plugin::module_enabled( 'strings' ) ) {
            $string_id = $this->tax_rate_string_id( $rate_id, $source );
            if ( $string_id ) {
                $override = ITKT_Strings::instance()->get_translation( $string_id, $language );
                if ( '' !== trim( $override ) ) { return wp_strip_all_tags( $override ); }
            }
        }

        $fallback = $this->default_tax_label_translation( $source, $language );
        return '' !== $fallback ? $fallback : $label;
    }

    /** Read the untranslated configured name directly from WooCommerce's tax-rate table. */
    public function raw_tax_rate_label( $rate_id, $fallback = '' ) {
        global $wpdb;
        $rate_id = absint( $rate_id );
        if ( $rate_id ) {
            $name = $wpdb->get_var( $wpdb->prepare(
                "SELECT tax_rate_name FROM {$wpdb->prefix}woocommerce_tax_rates WHERE tax_rate_id=%d",
                $rate_id
            ) );
            if ( is_string( $name ) && '' !== trim( $name ) ) { return (string) $name; }
        }
        return (string) $fallback;
    }

    /** Stable ITKT string row for one configured WooCommerce tax-rate label. */
    public function tax_rate_string_id( $rate_id, $source = '' ) {
        if ( ! class_exists( 'ITKT_Strings' ) ) { return 0; }
        $rate_id = absint( $rate_id );
        $source = $this->raw_tax_rate_label( $rate_id, $source );
        if ( '' === trim( $source ) ) { return 0; }
        return ITKT_Strings::instance()->ensure_canonical_string(
            $source,
            'itkt-woocommerce-data',
            'tax_rate_' . $rate_id,
            ''
        );
    }

    /**
     * Defaults only for standard VAT labels. Arbitrary merchant-defined labels are never guessed.
     * These defaults remain editable through the frontend system-text editor.
     */
    public function default_tax_label_translation( $source, $language ) {
        $language = sanitize_key( (string) $language );
        $plain = function_exists( 'remove_accents' ) ? remove_accents( (string) $source ) : (string) $source;
        $plain = function_exists( 'mb_strtolower' ) ? mb_strtolower( $plain, 'UTF-8' ) : strtolower( $plain );
        $plain = preg_replace( '/[^\p{L}\p{N}]+/u', '', $plain );

        $vat_labels = array(
            'mwst', 'mehrwertsteuer', 'ust', 'umsatzsteuer', 'vat', 'valueaddedtax',
            'tva', 'iva', 'btw', 'moms', 'kdv', 'ضريبةالقيمةالمضافة',
        );
        if ( ! in_array( $plain, $vat_labels, true ) ) { return ''; }

        $map = array(
            'de' => 'MwSt.',
            'en' => 'VAT',
            'ar' => 'ضريبة القيمة المضافة',
            'fr' => 'TVA',
            'es' => 'IVA',
            'tr' => 'KDV',
            'sv' => 'moms',
            'nl' => 'btw',
        );
        return isset( $map[ $language ] ) ? (string) $map[ $language ] : '';
    }

    /** Tax labels currently used in the cart, for the inline editor. */
    public function current_cart_tax_rate_rows() {
        $rows = array();
        if ( ! function_exists( 'WC' ) || ! WC() || ! WC()->cart ) { return $rows; }
        foreach ( (array) WC()->cart->get_tax_totals() as $total ) {
            if ( ! is_object( $total ) ) { continue; }
            $rate_id = absint( $total->tax_rate_id ?? 0 );
            $source  = $this->raw_tax_rate_label( $rate_id, (string) ( $total->label ?? '' ) );
            if ( '' === trim( $source ) ) { continue; }
            $string_id = $this->tax_rate_string_id( $rate_id, $source );
            if ( ! $string_id ) { continue; }
            $rows[ $string_id ] = array(
                'string_id' => $string_id,
                'rate_id'   => $rate_id,
                'source'    => $source,
            );
        }
        return array_values( $rows );
    }



    /**
     * Resolve a WooCommerce order object for informational metadata display.
     * Compatible wrappers may expose get_order() / get_order_id(), but ITKT does not use this
     * metadata to translate emails or generated documents.
     */
    public function resolve_order_object( $object ) {
        if ( is_numeric( $object ) && function_exists( 'wc_get_order' ) ) {
            $candidate = wc_get_order( absint( $object ) );
            return $candidate && is_a( $candidate, 'WC_Order' ) ? $candidate : null;
        }
        if ( is_object( $object ) && is_a( $object, 'WC_Order' ) ) { return $object; }
        if ( ! is_object( $object ) ) { return null; }

        // Germanized Shipment and StoreaBill Invoice expose get_order(). Other compatible
        // document plugins often use the same convention, so keep this intentionally generic.
        if ( method_exists( $object, 'get_order' ) ) {
            try { $candidate = $object->get_order(); } catch ( Throwable $e ) { $candidate = null; }
            if ( $candidate && is_a( $candidate, 'WC_Order' ) ) { return $candidate; }
            if ( is_numeric( $candidate ) && function_exists( 'wc_get_order' ) ) {
                $candidate = wc_get_order( absint( $candidate ) );
                if ( $candidate && is_a( $candidate, 'WC_Order' ) ) { return $candidate; }
            }
        }
        if ( method_exists( $object, 'get_order_id' ) && function_exists( 'wc_get_order' ) ) {
            try { $order_id = absint( $object->get_order_id() ); } catch ( Throwable $e ) { $order_id = 0; }
            if ( $order_id ) {
                $candidate = wc_get_order( $order_id );
                if ( $candidate && is_a( $candidate, 'WC_Order' ) ) { return $candidate; }
            }
        }
        return null;
    }

    /** Return informational storefront-language metadata saved when the order was placed. */
    public function order_language( $order ) {
        $order = $this->resolve_order_object( $order );
        if ( ! $order || ! method_exists( $order, 'get_meta' ) ) { return ''; }
        $code = sanitize_key( (string) $order->get_meta( '_itkt_language', true ) );
        $active = ITKT_Languages::instance()->get_active();
        return ( $code && isset( $active[ $code ] ) ) ? $code : '';
    }

    /** Store language for Checkout Block / Store API requests. */
    public function store_api_order_language( $order, $request = null ) {
        $this->persist_order_language( $order );
    }

    public function store_api_order_language_meta( $order ) {
        $this->persist_order_language( $order );
    }

    private function persist_order_language( $order ) {
        if ( ! is_object( $order ) || ! method_exists( $order, 'update_meta_data' ) ) { return; }
        $code = ITKT_Languages::instance()->current_code();
        $active = ITKT_Languages::instance()->get_active();
        if ( ! $code || ! isset( $active[ $code ] ) ) { return; }
        $order->update_meta_data( '_itkt_language', $code );
    }

    /**
     * Force ITKT itself to the default storefront language while WooCommerce builds any e-mail.
     * This deliberately does not switch the WordPress locale and does not use _itkt_language.
     * It only prevents frontend-selected AR/EN/etc. overrides from leaking into transactional
     * or merchant e-mails generated during the same checkout/AJAX request.
     */
    public function email_default_context_start( $allow, $email = null ) {
        $default = ITKT_Languages::instance()->get_default_code();
        if ( $default && ITKT_Languages::instance()->push_context_code( $default ) ) {
            $this->email_default_context_depth++;
        }
        return $allow;
    }

    /** Restore ITKT's prior frontend context after WooCommerce finishes the e-mail. */
    public function email_default_context_end( $allow, $email = null ) {
        if ( $this->email_default_context_depth > 0 ) {
            ITKT_Languages::instance()->pop_context_code();
            $this->email_default_context_depth--;
        }
        return $allow;
    }

    /** Show order language in the WooCommerce admin order screen. */
    public function admin_order_language( $order ) {
        $code = $this->order_language( $order );
        if ( ! $code ) { return; }
        $all = ITKT_Languages::instance()->get_all();
        $name = isset( $all[ $code ] ) ? (string) ( $all[ $code ]['native_name'] ?? $all[ $code ]['name'] ?? strtoupper( $code ) ) : strtoupper( $code );
        echo '<p class="form-field form-field-wide"><strong>' . esc_html__( 'Website-Sprache bei Bestellung', 'it-kayali-translate' ) . ':</strong> ' . esc_html( $name ) . ' <code>' . esc_html( $code ) . '</code></p>';
    }

    // No StoreaBill/Germanized document translation hooks by design.

    public function cart_item_from_session( $cart_item, $values, $cart_item_key ) {
        // Touch the language resolver while the WC session is definitely available. This also
        // lets sync_session_language() and product getter filters see the same AR/EN context.
        ITKT_Languages::instance()->current_code();
        return $cart_item;
    }

    public function sync_session_language() {
        if ( ! function_exists( 'WC' ) || ! WC() || ! WC()->session ) { return; }
        $code = ITKT_Languages::instance()->current_code();
        if ( $code && isset( ITKT_Languages::instance()->get_active()[ $code ] ) ) {
            WC()->session->set( 'itkt_language', $code );
        }
    }

    public function store_order_language( $order, $data = array() ) {
        $this->persist_order_language( $order );
    }

    private function should_translate_urls() {
        if ( is_admin() && ! wp_doing_ajax() ) { return false; }
        $code = ITKT_Languages::instance()->current_code();
        return $code && $code !== ITKT_Languages::instance()->get_default_code();
    }

    private function is_product_taxonomy_name( $taxonomy ) {
        return 'product_cat' === $taxonomy || 'product_tag' === $taxonomy || 0 === strpos( (string) $taxonomy, 'pa_' );
    }

    public function product_permalink( $url, $post, $leavename = false, $sample = false ) {
        if ( ! $this->should_translate_urls() || ITKT_Frontend::instance()->is_building_permalink() || ! $post instanceof WP_Post || 'product' !== $post->post_type ) { return $url; }
        return class_exists( 'ITKT_SEO' ) ? ITKT_Frontend::instance()->language_url_for_post( $post->ID, ITKT_Languages::instance()->current_code(), false ) : ITKT_Frontend::instance()->language_url_from_url( $url, ITKT_Languages::instance()->current_code(), false );
    }

    public function loop_product_link( $url, $product ) {
        if ( ! $this->should_translate_urls() ) { return $url; }
        if ( class_exists( 'ITKT_SEO' ) && is_object( $product ) && method_exists( $product, 'get_id' ) ) {
            return ITKT_Frontend::instance()->language_url_for_post( $product->get_id(), ITKT_Languages::instance()->current_code(), false );
        }
        return ITKT_Frontend::instance()->language_url_from_url( $url, ITKT_Languages::instance()->current_code(), false );
    }

    public function term_link( $url, $term, $taxonomy ) {
        if ( ! $this->should_translate_urls() || ( class_exists( 'ITKT_SEO' ) && ITKT_SEO::instance()->is_building_term_url() ) || ! $this->is_product_taxonomy_name( $taxonomy ) ) { return $url; }
        if ( class_exists( 'ITKT_SEO' ) && $term instanceof WP_Term ) {
            $translated = ITKT_SEO::instance()->translated_term_url( $term, ITKT_Languages::instance()->current_code() );
            if ( $translated ) { return $translated; }
        }
        return ITKT_Frontend::instance()->language_url_from_url( $url, ITKT_Languages::instance()->current_code(), false );
    }

    /** Keep classic cart, mini-cart and Store API cart item links on the active storefront language. */
    public function cart_item_permalink( $url, $cart_item, $cart_item_key = '' ) {
        if ( ! $this->should_translate_urls() || ! class_exists( 'ITKT_Frontend' ) ) { return $url; }
        $product_id = is_array( $cart_item ) ? absint( $cart_item['product_id'] ?? 0 ) : 0;
        if ( ! $product_id && is_array( $cart_item ) && ! empty( $cart_item['data'] ) && is_object( $cart_item['data'] ) ) {
            $product = $cart_item['data'];
            if ( method_exists( $product, 'get_parent_id' ) && $product->get_parent_id() ) { $product_id = absint( $product->get_parent_id() ); }
            elseif ( method_exists( $product, 'get_id' ) ) { $product_id = absint( $product->get_id() ); }
        }
        if ( ! $product_id ) { return $url; }
        $translated = ITKT_Frontend::instance()->language_url_for_post( $product_id, ITKT_Languages::instance()->current_code(), false );
        return $translated ?: $url;
    }

    /** Keep product links in frontend My Account/order details on the currently selected language. */
    public function order_item_permalink( $url, $item, $order = null ) {
        if ( ! $this->should_translate_urls() || ! is_object( $item ) || ! method_exists( $item, 'get_product_id' ) ) { return $url; }
        $product_id = absint( $item->get_product_id() );
        if ( ! $product_id ) { return $url; }
        $translated = ITKT_Frontend::instance()->language_url_for_post( $product_id, ITKT_Languages::instance()->current_code(), false );
        return $translated ?: $url;
    }

    /** Preserve selected language + existing layered-nav query parameters. */
    public function layered_nav_link( $url, $term = null, $taxonomy = '' ) {
        if ( ! $this->should_translate_urls() || ! $url || ! class_exists( 'ITKT_Frontend' ) ) { return $url; }
        return ITKT_Frontend::instance()->language_url_from_url(
            $url,
            ITKT_Languages::instance()->current_code(),
            true
        );
    }

    public function woocommerce_page_url( $url ) {
        if ( ! $this->should_translate_urls() || ! $url ) { return $url; }
        return ITKT_Frontend::instance()->language_url_from_url( $url, ITKT_Languages::instance()->current_code(), false );
    }

    public function endpoint_url( $url, $endpoint, $value, $permalink ) {
        if ( ! $this->should_translate_urls() || ! $url ) { return $url; }

        $code = ITKT_Languages::instance()->current_code();

        // Account endpoints are rebuilt from the exact physical WooCommerce My Account page.
        // Do not rely on a translated page copy or on the permalink passed by another theme/plugin
        // filter: some WoodMart stacks normalize that permalink back to the account dashboard and
        // every menu click consequently points to /LANG/my-account/. We accept both physical and
        // translated account bases, and additionally confirm that the endpoint belongs to the
        // WooCommerce account endpoint map before rebuilding it.
        $is_account_endpoint = $this->is_account_endpoint_slug( $endpoint );
        $on_account_request  = function_exists( 'is_account_page' ) && is_account_page();
        // A language-prefixed My Account request is authoritative even when the queried object or
        // a theme-provided permalink has already been normalized back to the dashboard page.
        $request_path = isset( $_SERVER['REQUEST_URI'] ) ? rawurldecode( (string) wp_parse_url( wp_unslash( $_SERVER['REQUEST_URI'] ), PHP_URL_PATH ) ) : '';
        $account_base = $this->myaccount_language_base_url( $code );
        $account_path = $account_base ? rawurldecode( (string) wp_parse_url( $account_base, PHP_URL_PATH ) ) : '';
        $on_account_path = $account_path && 0 === strpos( untrailingslashit( $request_path ) . '/', untrailingslashit( $account_path ) . '/' );
        if ( $is_account_endpoint && ( $this->is_myaccount_permalink( $permalink, $code ) || $on_account_request || $on_account_path ) ) {
            $base = $this->myaccount_language_base_url( $code );
            $public_endpoint = trim( (string) $endpoint, '/' );
            if ( $base && $public_endpoint ) {
                $target = trailingslashit( $base ) . $public_endpoint;
                if ( '' !== (string) $value ) {
                    $target .= '/' . trim( (string) $value, '/' );
                }
                $target = user_trailingslashit( $target );

                // Preserve endpoint query parameters such as customer-logout nonces.
                $query = (string) wp_parse_url( $url, PHP_URL_QUERY );
                if ( $query ) {
                    $args = array();
                    wp_parse_str( $query, $args );
                    if ( $args ) { $target = add_query_arg( $args, $target ); }
                }
                // v0.12.10: carry an independent canonical endpoint marker for the server
                // request. frontend.js removes it from the visible address bar after the page has
                // loaded, so visitors keep the clean pretty endpoint URL.
                $endpoint_key = $this->account_endpoint_key_from_slug( $endpoint );
                if ( $endpoint_key && ! in_array( $endpoint_key, array( 'customer-logout', 'lost-password' ), true ) ) {
                    $rescue = array( 'itkt_wc_endpoint' => $endpoint_key );
                    if ( '' !== (string) $value ) { $rescue['itkt_wc_value'] = (string) $value; }
                    $target = add_query_arg( $rescue, $target );
                }

                $fragment = (string) wp_parse_url( $url, PHP_URL_FRAGMENT );
                if ( $fragment ) { $target .= '#' . rawurlencode( rawurldecode( $fragment ) ); }
                return $target;
            }
        }

        // Other WooCommerce endpoints (for example checkout/order-pay) retain their own base page.
        return ITKT_Frontend::instance()->language_url_from_url( $url, $code, true );
    }

    /** Canonical WooCommerce account endpoint key => configured public slug map. */
    private function account_endpoint_map() {
        $map = array();
        if ( function_exists( 'WC' ) && WC() && isset( WC()->query ) && is_object( WC()->query ) && method_exists( WC()->query, 'get_query_vars' ) ) {
            $map = (array) WC()->query->get_query_vars();
        }
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

    /** Resolve a public account endpoint slug back to WooCommerce's canonical endpoint key. */
    private function account_endpoint_key_from_slug( $endpoint ) {
        $endpoint = sanitize_title( rawurldecode( trim( (string) $endpoint, '/' ) ) );
        if ( ! $endpoint ) { return ''; }
        foreach ( $this->account_endpoint_map() as $key => $slug ) {
            $slug = sanitize_title( rawurldecode( trim( (string) $slug, '/' ) ) );
            if ( $slug && $slug === $endpoint ) { return sanitize_key( (string) $key ); }
        }
        return '';
    }

    /** Check whether wc_get_endpoint_url() is building one of the configured account endpoints. */
    private function is_account_endpoint_slug( $endpoint ) {
        $endpoint = sanitize_title( rawurldecode( trim( (string) $endpoint, '/' ) ) );
        if ( ! $endpoint ) { return false; }
        foreach ( $this->account_endpoint_map() as $slug ) {
            $slug = sanitize_title( rawurldecode( trim( (string) $slug, '/' ) ) );
            if ( $slug && $slug === $endpoint ) { return true; }
        }
        return false;
    }

    /** Public base URL for the exact physical WooCommerce My Account system page. */
    private function myaccount_language_base_url( $code ) {
        if ( ! function_exists( 'wc_get_page_id' ) || ! class_exists( 'ITKT_Frontend' ) ) { return ''; }
        $account_id = absint( wc_get_page_id( 'myaccount' ) );
        if ( ! $account_id ) { return ''; }
        return ITKT_Frontend::instance()->language_url_for_system_page( $account_id, $code, false );
    }

    /** Build a known account endpoint URL without depending on theme-cached endpoint links. */
    private function account_endpoint_url_for_key( $key, $code, $value = '' ) {
        $key = sanitize_key( (string) $key );
        $map = $this->account_endpoint_map();
        if ( ! $key || empty( $map[ $key ] ) ) { return ''; }
        $base = $this->myaccount_language_base_url( $code );
        if ( ! $base ) { return ''; }
        $slug = trim( (string) $map[ $key ], '/' );
        $url = trailingslashit( $base ) . $slug;
        if ( '' !== (string) $value ) { $url .= '/' . trim( (string) $value, '/' ); }
        $url = user_trailingslashit( $url );
        if ( ! in_array( $key, array( 'customer-logout', 'lost-password' ), true ) ) {
            $args = array( 'itkt_wc_endpoint' => $key );
            if ( '' !== (string) $value ) { $args['itkt_wc_value'] = (string) $value; }
            $url = add_query_arg( $args, $url );
        }
        return $url;
    }

    /** Check whether wc_get_endpoint_url() is currently building a My Account URL. */
    private function is_myaccount_permalink( $permalink, $code ) {
        if ( ! function_exists( 'wc_get_page_id' ) ) { return false; }
        $account_id = absint( wc_get_page_id( 'myaccount' ) );
        if ( ! $account_id ) { return false; }

        $normalize = static function( $candidate ) {
            $path = rawurldecode( (string) wp_parse_url( (string) $candidate, PHP_URL_PATH ) );
            return untrailingslashit( '/' . trim( $path, '/' ) );
        };
        $candidate = $normalize( $permalink );
        if ( ! $candidate ) { return false; }

        $expected = array();
        $system = $this->myaccount_language_base_url( $code );
        if ( $system ) { $expected[] = $system; }
        $translated = ITKT_Frontend::instance()->language_url_for_post( $account_id, $code, false );
        if ( $translated ) { $expected[] = $translated; }

        // Also accept the unprefixed physical permalink. Another plugin may have obtained it
        // before ITKT's final my-account permalink filter ran.
        $expected[] = ITKT_Frontend::instance()->language_url_for_system_page(
            $account_id,
            ITKT_Languages::instance()->get_default_code(),
            false
        );

        foreach ( array_unique( array_filter( $expected ) ) as $url ) {
            if ( $candidate === $normalize( $url ) ) { return true; }
        }
        return false;
    }

    /**
     * Frontend safety net for cached WooCommerce/WoodMart My Account links.
     *
     * Core WooCommerce uses classes such as --orders; WoodMart account tiles use classes such as
     * orders-link. Replacing only those existing anchor hrefs is layout-neutral and has no effect
     * on unrelated links.
     */
    public function account_endpoint_link_fallback() {
        if ( ! $this->should_translate_urls() || ! function_exists( 'wc_get_account_menu_items' ) || ! function_exists( 'wc_get_account_endpoint_url' ) ) { return; }

        $urls = array();
        $code = ITKT_Languages::instance()->current_code();
        foreach ( (array) wc_get_account_menu_items() as $endpoint => $label ) {
            $endpoint = sanitize_key( (string) $endpoint );
            if ( ! $endpoint ) { continue; }
            if ( 'dashboard' === $endpoint ) {
                $urls[ $endpoint ] = $this->myaccount_language_base_url( $code );
            } elseif ( 'customer-logout' === $endpoint && function_exists( 'wc_logout_url' ) ) {
                // Keep WooCommerce's logout nonce while forcing the redirect/base back to the
                // selected language system page.
                $urls[ $endpoint ] = wc_logout_url( $this->myaccount_language_base_url( $code ) );
            } else {
                $urls[ $endpoint ] = $this->account_endpoint_url_for_key( $endpoint, $code );
            }
        }
        $urls = array_filter( $urls );
        if ( ! $urls ) { return; }
        ?>
        <script id="itkt-account-endpoint-fallback">
        (function(){
            var urls = <?php echo wp_json_encode( $urls ); ?>;
            function selectorsFor(key){
                return [
                    '.woocommerce-MyAccount-navigation-link--' + key + ' a',
                    '.wd-my-account-links .' + key + '-link a',
                    '.wd-my-account-links .' + key + '-link > a'
                ];
            }
            function applyAccountUrls(root){
                root = root || document;
                Object.keys(urls).forEach(function(key){
                    root.querySelectorAll(selectorsFor(key).join(',')).forEach(function(a){
                        if (a && urls[key]) { a.setAttribute('href', urls[key]); }
                    });
                });
            }
            applyAccountUrls(document);

            // Some WoodMart/account scripts replace navigation markup after the footer has loaded.
            // Correct the href again at click time without blocking the normal browser navigation.
            document.addEventListener('click', function(event){
                var a = event.target && event.target.closest ? event.target.closest('a') : null;
                if (!a) { return; }
                Object.keys(urls).some(function(key){
                    if (selectorsFor(key).some(function(selector){ return a.matches(selector); })) {
                        if (urls[key]) {
                            a.setAttribute('href', urls[key]);
                            // WoodMart/account scripts may intercept the same click and navigate
                            // back to the dashboard. For these known account links, ITKT owns the
                            // final destination and performs a normal full-page navigation.
                            event.preventDefault();
                            event.stopImmediatePropagation();
                            window.location.assign(urls[key]);
                        }
                        return true;
                    }
                    return false;
                });
            }, true);
        })();
        </script>
        <?php
    }

    public function woocommerce_home_url( $url ) {
        if ( ! $this->should_translate_urls() ) { return $url; }
        return ITKT_Frontend::instance()->language_url_from_url( $url, ITKT_Languages::instance()->current_code(), false );
    }
}
