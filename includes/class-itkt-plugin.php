<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class ITKT_Plugin {
    private static $instance = null;
    public static function instance() { if ( null === self::$instance ) { self::$instance = new self(); } return self::$instance; }

    public static function activate() {
        if ( false === get_option( 'itkt_settings', false ) ) {
            add_option( 'itkt_settings', array( 'setup_complete' => false, 'default_language' => 'de', 'enabled_modules' => array( 'pages','posts','strings' ), 'url_mode' => 'directory', 'fallback_mode' => 'default', 'remember_language' => true, 'redirect_saved_home' => true, 'product_slug_fallback_default' => true, 'diagnostic_logging' => true, 'inline_editor_enabled' => true ) );
        }
        if ( false === get_option( 'itkt_languages', false ) ) {
            add_option( 'itkt_languages', array( 'de' => array( 'code'=>'de','name'=>'German','native_name'=>'Deutsch','locale'=>'de_DE','direction'=>'ltr','flag'=>'🇩🇪','active'=>true,'default'=>true ) ) );
        }
        ITKT_Strings::install_schema();
        delete_option( 'itkt_rewrite_version' );
        set_transient( 'itkt_activation_redirect', 1, 60 );
    }

    public static function deactivate() {
        delete_transient( 'itkt_activation_redirect' );
        flush_rewrite_rules();
    }

    public function boot() {
        add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );
        add_action( 'init', array( $this, 'init' ) );
        add_action( 'admin_init', array( $this, 'maybe_redirect_to_setup' ) );
    }

    public function load_textdomain() { load_plugin_textdomain( 'it-kayali-translate', false, dirname( ITKT_BASENAME ) . '/languages' ); }

    public static function module_enabled( $module ) {
        $settings = get_option( 'itkt_settings', array() );
        if ( empty( $settings['setup_complete'] ) ) { return false; }
        $modules = isset( $settings['enabled_modules'] ) && is_array( $settings['enabled_modules'] ) ? $settings['enabled_modules'] : array();
        // Backward compatibility for early development builds that did not persist module choices.
        if ( ! $modules ) { return true; }
        return in_array( sanitize_key( $module ), array_map( 'sanitize_key', $modules ), true );
    }

    public function init() {
        $settings = get_option( 'itkt_settings', array() );
        $changed = false;
        if ( ! array_key_exists( 'remember_language', $settings ) ) { $settings['remember_language'] = true; $changed = true; }
        if ( ! array_key_exists( 'redirect_saved_home', $settings ) ) { $settings['redirect_saved_home'] = true; $changed = true; }
        if ( ! array_key_exists( 'fallback_mode', $settings ) ) { $settings['fallback_mode'] = 'default'; $changed = true; }
        if ( ! array_key_exists( 'product_slug_fallback_default', $settings ) ) { $settings['product_slug_fallback_default'] = true; $changed = true; }
        if ( ! array_key_exists( 'diagnostic_logging', $settings ) ) { $settings['diagnostic_logging'] = true; $changed = true; }
        if ( ! array_key_exists( 'inline_editor_enabled', $settings ) ) { $settings['inline_editor_enabled'] = true; $changed = true; }
        if ( $changed ) { update_option( 'itkt_settings', $settings ); }

        ITKT_Languages::instance();
        ITKT_Strings::maybe_install_schema();

        // Built-in integrations use the same public adapter registry as third-party add-ons.
        $adapters = ITKT_Adapters::instance();
        $adapters->register( new ITKT_Elementor_Adapter() );
        $adapters->register( new ITKT_WoodMart_Adapter() );
        $adapters->boot_available();

        ITKT_Content::instance();
        if ( self::module_enabled( 'woocommerce' ) && ( class_exists( 'WooCommerce' ) || post_type_exists( 'product' ) ) ) {
            ITKT_Product_Translations::instance();
            ITKT_Term_Translations::instance();
            ITKT_WooCommerce::instance();
        }
        ITKT_Elementor::instance();
        ITKT_Frontend::instance();
        ITKT_SEO::instance();
        if ( self::module_enabled( 'strings' ) ) { ITKT_Strings::instance(); }
        ITKT_Diagnostics::instance();
        ITKT_Inline_Editor::instance();
        ITKT_Frontend_Catalog::instance();
        ITKT_Backup::instance();

        // Flush only once per routing version. Needed when updating an already-active plugin ZIP.
        if ( get_option( 'itkt_rewrite_version' ) !== ITKT_VERSION ) {
            flush_rewrite_rules( false );
            update_option( 'itkt_rewrite_version', ITKT_VERSION, false );
        }

        if ( is_admin() ) { ITKT_Admin::instance(); }
    }

    public function maybe_redirect_to_setup() {
        if ( ! get_transient( 'itkt_activation_redirect' ) ) { return; }
        delete_transient( 'itkt_activation_redirect' );
        if ( wp_doing_ajax() || isset( $_GET['activate-multi'] ) ) { return; }
        if ( current_user_can( 'manage_options' ) ) { wp_safe_redirect( admin_url( 'admin.php?page=itkt-setup' ) ); exit; }
    }
}
