<?php
/**
 * Plugin Name: IT-Kayali Translate
 * Plugin URI: https://it-kayali.de
 * Description: Modular multilingual translation management for WordPress with optional WooCommerce, Elementor and theme adapters.
 * Version: 0.12.26
 * Author: IT-Kayali
 * Author URI: https://it-kayali.de
 * Text Domain: it-kayali-translate
 * Domain Path: /languages
 * Requires at least: 6.4
 * Requires PHP: 8.0
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

/*
 * Duplicate-copy safety.
 *
 * A GitHub source archive is extracted by WordPress as e.g. Translate_plugin-main while an
 * existing installable build may already live in it-kayali-translate/. Loading both copies used
 * to redefine ITKT_* constants and could make the second copy require files from the first
 * directory. Detect the collision before loading any classes so the admin can remove the
 * duplicate safely instead of receiving a fatal error.
 */
$itkt_bootstrap_file = wp_normalize_path( __FILE__ );
$itkt_existing_file  = defined( 'ITKT_FILE' ) ? wp_normalize_path( (string) ITKT_FILE ) : '';
if ( $itkt_existing_file && $itkt_existing_file !== $itkt_bootstrap_file ) {
    add_action( 'admin_notices', static function() use ( $itkt_existing_file, $itkt_bootstrap_file ) {
        if ( ! current_user_can( 'activate_plugins' ) ) { return; }
        echo '<div class="notice notice-error"><p><strong>IT-Kayali Translate:</strong> '
            . esc_html__( 'A second copy of the plugin is installed/active. Deactivate or remove the duplicate copy and install the official plugin ZIP so only one IT-Kayali Translate folder is loaded.', 'it-kayali-translate' )
            . '</p><p><code>' . esc_html( $itkt_existing_file ) . '</code><br><code>' . esc_html( $itkt_bootstrap_file ) . '</code></p></div>';
    } );
    return;
}

// A second include of the same plugin in one request must be harmless.
if ( class_exists( 'ITKT_Plugin', false ) ) { return; }

if ( ! defined( 'ITKT_VERSION' ) ) { define( 'ITKT_VERSION', '0.12.26' ); }
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
