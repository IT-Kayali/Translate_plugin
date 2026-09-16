<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Cache invalidation coordinator for translation changes.
 *
 * Translation saves can happen in batches (for example the Frontend Texte table). Clearing a
 * page/object cache for every single row would be expensive, so all invalidation requests are
 * coalesced and executed once at shutdown.
 */
class ITKT_Cache {
    private static $instance = null;
    private $scheduled = false;
    private $reasons = array();

    public static function instance() {
        if ( null === self::$instance ) { self::$instance = new self(); }
        return self::$instance;
    }

    private function __construct() {
        foreach ( array(
            'itkt_string_translation_saved',
            'itkt_string_translation_deleted',
            'itkt_product_translation_saved',
            'itkt_term_translation_saved',
            'itkt_attribute_translation_saved',
            'itkt_translation_created',
            'itkt_existing_translation_linked',
            'itkt_elementor_translation_applied',
            'itkt_elementor_layout_repaired',
            'itkt_backup_restored',
        ) as $hook ) {
            add_action( $hook, array( $this, 'request_purge' ), 100, 4 );
        }
    }

    public function request_purge( ...$args ) {
        $hook = current_filter();
        if ( $hook ) { $this->reasons[ sanitize_key( $hook ) ] = true; }
        if ( $this->scheduled ) { return; }
        $this->scheduled = true;
        add_action( 'shutdown', array( $this, 'purge' ), PHP_INT_MAX );
    }

    /**
     * Bump the ITKT runtime generation and clear known cache layers.
     *
     * WP Fastest Cache exposes wpfc_clear_all_cache() as its documented programmatic hook.
     * Other cache/server layers can subscribe to itkt_after_cache_purge without ITKT depending on
     * vendor internals.
     */
    public function purge() {
        if ( ! $this->scheduled ) { return; }
        $this->scheduled = false;

        $version = absint( get_option( 'itkt_runtime_data_version', 1 ) );
        update_option( 'itkt_runtime_data_version', max( 1, $version + 1 ), false );

        // Clear WordPress object caches first so translated runtime maps cannot survive a save.
        if ( function_exists( 'wp_cache_flush' ) ) { wp_cache_flush(); }

        // Official WP Fastest Cache function. Guarded so ITKT has no hard dependency on the plugin.
        if ( function_exists( 'wpfc_clear_all_cache' ) ) {
            wpfc_clear_all_cache();
        } elseif ( class_exists( 'WpFastestCache' ) ) {
            // Compatibility with older WP Fastest Cache builds that expose only the class method.
            try {
                $wpfc = new WpFastestCache();
                if ( method_exists( $wpfc, 'deleteCache' ) ) { $wpfc->deleteCache(); }
            } catch ( Throwable $e ) {
                // Cache invalidation must never break a translation save.
            }
        }

        do_action( 'itkt_after_cache_purge', array_keys( $this->reasons ) );
        $this->reasons = array();
    }
}
