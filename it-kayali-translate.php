<?php
/**
 * Plugin Name: IT-Kayali Translate
 * Plugin URI: https://it-kayali.de
 * Description: Modular multilingual translation management for WordPress with optional WooCommerce, Elementor and theme adapters.
 * Version: 0.12.39
 * Author: IT-Kayali
 * Author URI: https://it-kayali.de
 * Text Domain: it-kayali-translate
 * Domain Path: /languages
 * Requires at least: 6.4
 * Requires PHP: 8.0
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

/*
 * Duplicate-copy migration.
 *
 * Older GitHub archives were commonly installed as Translate_plugin-main while official builds
 * use the canonical it-kayali-translate folder. V0.12.31 automatically promotes the canonical
 * copy when both are active, rewrites WordPress' active-plugin list, and removes only the known
 * legacy archive folder after the request finishes. Translation/configuration data is preserved
 * because it lives in WordPress options/post meta, not in the plugin directory.
 */
$itkt_bootstrap_file      = wp_normalize_path( __FILE__ );
$itkt_existing_file       = defined( 'ITKT_FILE' ) ? wp_normalize_path( (string) ITKT_FILE ) : '';
$itkt_current_basename    = plugin_basename( __FILE__ );
$itkt_canonical_basename  = 'it-kayali-translate/it-kayali-translate.php';
$itkt_legacy_basenames    = array(
    'Translate_plugin-main/it-kayali-translate.php',
    'Translate_plugin/it-kayali-translate.php',
);

$itkt_replace_active_copy = static function( $legacy_basename, $canonical_basename ) {
    $active = (array) get_option( 'active_plugins', array() );
    $next = array();
    $canonical_added = false;

    foreach ( $active as $plugin ) {
        if ( $plugin === $legacy_basename || $plugin === $canonical_basename ) {
            if ( ! $canonical_added ) {
                $next[] = $canonical_basename;
                $canonical_added = true;
            }
            continue;
        }
        $next[] = $plugin;
    }
    if ( ! $canonical_added ) { $next[] = $canonical_basename; }
    if ( $next !== $active ) { update_option( 'active_plugins', array_values( array_unique( $next ) ) ); }

    if ( is_multisite() ) {
        $network = (array) get_site_option( 'active_sitewide_plugins', array() );
        if ( isset( $network[ $legacy_basename ] ) || isset( $network[ $canonical_basename ] ) ) {
            $stamp = isset( $network[ $canonical_basename ] ) ? $network[ $canonical_basename ] : ( $network[ $legacy_basename ] ?? time() );
            unset( $network[ $legacy_basename ] );
            $network[ $canonical_basename ] = $stamp;
            update_site_option( 'active_sitewide_plugins', $network );
        }
    }
};

$itkt_schedule_legacy_cleanup = static function( $legacy_file ) use ( $itkt_legacy_basenames ) {
    $legacy_file = wp_normalize_path( (string) $legacy_file );
    $legacy_basename = plugin_basename( $legacy_file );
    if ( ! in_array( $legacy_basename, $itkt_legacy_basenames, true ) ) { return; }

    $legacy_dir = wp_normalize_path( dirname( $legacy_file ) );
    $plugin_root = trailingslashit( wp_normalize_path( WP_PLUGIN_DIR ) );
    $legacy_dir_slash = trailingslashit( $legacy_dir );
    if ( 0 !== strpos( $legacy_dir_slash, $plugin_root ) ) { return; }

    register_shutdown_function( static function() use ( $legacy_dir, $legacy_basename ) {
        if ( ! is_dir( $legacy_dir ) ) { return; }

        $ok = true;
        try {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator( $legacy_dir, FilesystemIterator::SKIP_DOTS ),
                RecursiveIteratorIterator::CHILD_FIRST
            );
            foreach ( $iterator as $item ) {
                $path = $item->getPathname();
                if ( $item->isLink() || $item->isFile() ) {
                    if ( ! @unlink( $path ) && file_exists( $path ) ) { $ok = false; }
                } elseif ( $item->isDir() ) {
                    if ( ! @rmdir( $path ) && is_dir( $path ) ) { $ok = false; }
                }
            }
            if ( ! @rmdir( $legacy_dir ) && is_dir( $legacy_dir ) ) { $ok = false; }
        } catch ( Throwable $e ) {
            $ok = false;
        }

        set_transient(
            'itkt_duplicate_migration_result',
            array(
                'success' => $ok && ! is_dir( $legacy_dir ),
                'legacy'  => $legacy_basename,
            ),
            10 * MINUTE_IN_SECONDS
        );
    } );
};

if ( $itkt_existing_file && $itkt_existing_file !== $itkt_bootstrap_file ) {
    $itkt_existing_basename = plugin_basename( $itkt_existing_file );

    // Canonical copy loaded second: promote it for the next request and retire the legacy copy.
    if ( $itkt_current_basename === $itkt_canonical_basename && in_array( $itkt_existing_basename, $itkt_legacy_basenames, true ) ) {
        $itkt_replace_active_copy( $itkt_existing_basename, $itkt_canonical_basename );
        $itkt_schedule_legacy_cleanup( $itkt_existing_file );
        return;
    }

    // Canonical copy already loaded first: simply retire this known legacy duplicate.
    if ( $itkt_existing_basename === $itkt_canonical_basename && in_array( $itkt_current_basename, $itkt_legacy_basenames, true ) ) {
        $itkt_replace_active_copy( $itkt_current_basename, $itkt_canonical_basename );
        $itkt_schedule_legacy_cleanup( $itkt_bootstrap_file );
        return;
    }

    // Unknown duplicate folder: keep the conservative safety notice instead of deleting anything.
    add_action( 'admin_notices', static function() use ( $itkt_existing_file, $itkt_bootstrap_file ) {
        if ( ! current_user_can( 'activate_plugins' ) ) { return; }
        echo '<div class="notice notice-error"><p><strong>IT-Kayali Translate:</strong> '
            . esc_html__( 'A second copy of the plugin is active in an unknown folder. Automatic cleanup was not attempted for safety.', 'it-kayali-translate' )
            . '</p><p><code>' . esc_html( $itkt_existing_file ) . '</code><br><code>' . esc_html( $itkt_bootstrap_file ) . '</code></p></div>';
    } );
    return;
}

// A second include of the same plugin in one request must be harmless.
if ( class_exists( 'ITKT_Plugin', false ) ) { return; }

// If the canonical copy is now the only active one but a known legacy directory survived a prior
// request (e.g. filesystem lock), retry cleanup automatically without requiring File Manager work.
if ( $itkt_current_basename === $itkt_canonical_basename ) {
    foreach ( $itkt_legacy_basenames as $legacy_basename ) {
        $legacy_file = wp_normalize_path( WP_PLUGIN_DIR . '/' . $legacy_basename );
        if ( file_exists( $legacy_file ) ) {
            $active = (array) get_option( 'active_plugins', array() );
            $network = is_multisite() ? (array) get_site_option( 'active_sitewide_plugins', array() ) : array();
            if ( ! in_array( $legacy_basename, $active, true ) && ! isset( $network[ $legacy_basename ] ) ) {
                $itkt_schedule_legacy_cleanup( $legacy_file );
            }
        }
    }

    add_action( 'admin_notices', static function() {
        if ( ! current_user_can( 'activate_plugins' ) ) { return; }
        $result = get_transient( 'itkt_duplicate_migration_result' );
        if ( ! is_array( $result ) ) { return; }
        delete_transient( 'itkt_duplicate_migration_result' );

        if ( ! empty( $result['success'] ) ) {
            echo '<div class="notice notice-success is-dismissible"><p><strong>IT-Kayali Translate:</strong> '
                . esc_html__( 'The duplicate legacy plugin copy was migrated and removed automatically. The official it-kayali-translate installation is now active.', 'it-kayali-translate' )
                . '</p></div>';
        } else {
            echo '<div class="notice notice-warning"><p><strong>IT-Kayali Translate:</strong> '
                . esc_html__( 'The official plugin copy is active, but the old inactive folder could not yet be deleted. Cleanup will be retried automatically.', 'it-kayali-translate' )
                . '</p></div>';
        }
    } );
}

if ( ! defined( 'ITKT_VERSION' ) ) { define( 'ITKT_VERSION', '0.12.39' ); }
if ( ! defined( 'ITKT_FILE' ) ) { define( 'ITKT_FILE', __FILE__ ); }
if ( ! defined( 'ITKT_DIR' ) ) { define( 'ITKT_DIR', plugin_dir_path( __FILE__ ) ); }
if ( ! defined( 'ITKT_URL' ) ) { define( 'ITKT_URL', plugin_dir_url( __FILE__ ) ); }
if ( ! defined( 'ITKT_BASENAME' ) ) { define( 'ITKT_BASENAME', plugin_basename( __FILE__ ) ); }

// Always load this copy's files from its own physical directory; never trust a pre-existing path constant.
$itkt_bootstrap_dir = plugin_dir_path( __FILE__ );

require_once $itkt_bootstrap_dir . 'includes/class-itkt-plugin.php';
require_once $itkt_bootstrap_dir . 'includes/class-itkt-languages.php';
require_once $itkt_bootstrap_dir . 'includes/class-itkt-environment.php';
require_once $itkt_bootstrap_dir . 'includes/class-itkt-adapters.php';
require_once $itkt_bootstrap_dir . 'includes/class-itkt-builder-adapters.php';
require_once $itkt_bootstrap_dir . 'includes/class-itkt-content.php';
require_once $itkt_bootstrap_dir . 'includes/class-itkt-product-translations.php';
require_once $itkt_bootstrap_dir . 'includes/class-itkt-term-translations.php';
require_once $itkt_bootstrap_dir . 'includes/class-itkt-woocommerce.php';
require_once $itkt_bootstrap_dir . 'includes/class-itkt-elementor.php';
require_once $itkt_bootstrap_dir . 'includes/class-itkt-frontend.php';
require_once $itkt_bootstrap_dir . 'includes/class-itkt-seo.php';
require_once $itkt_bootstrap_dir . 'includes/class-itkt-native-translations.php';
require_once $itkt_bootstrap_dir . 'includes/class-itkt-strings.php';
require_once $itkt_bootstrap_dir . 'includes/class-itkt-diagnostics.php';
require_once $itkt_bootstrap_dir . 'includes/class-itkt-inline-editor.php';
require_once $itkt_bootstrap_dir . 'includes/class-itkt-frontend-catalog.php';
require_once $itkt_bootstrap_dir . 'includes/class-itkt-dynamic-runtime.php';
require_once $itkt_bootstrap_dir . 'includes/class-itkt-backup.php';
require_once $itkt_bootstrap_dir . 'includes/class-itkt-cache.php';
require_once $itkt_bootstrap_dir . 'includes/class-itkt-admin.php';

register_activation_hook( __FILE__, array( 'ITKT_Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'ITKT_Plugin', 'deactivate' ) );
ITKT_Plugin::instance()->boot();
ITKT_Dynamic_Runtime::instance();
