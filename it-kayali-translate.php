<?php
/**
 * Plugin Name: IT-Kayali Translate
 * Plugin URI: https://it-kayali.de
 * Description: Modular multilingual translation management for WordPress with optional WooCommerce, Elementor and theme adapters.
 * Version: 0.12.10
 * Author: IT-Kayali
 * Author URI: https://it-kayali.de
 * Text Domain: it-kayali-translate
 * Domain Path: /languages
 * Requires at least: 6.4
 * Requires PHP: 8.0
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
define( 'ITKT_VERSION', '0.12.10' );
define( 'ITKT_FILE', __FILE__ );
define( 'ITKT_DIR', plugin_dir_path( __FILE__ ) );
define( 'ITKT_URL', plugin_dir_url( __FILE__ ) );
define( 'ITKT_BASENAME', plugin_basename( __FILE__ ) );

require_once ITKT_DIR . 'includes/class-itkt-plugin.php';
require_once ITKT_DIR . 'includes/class-itkt-languages.php';
require_once ITKT_DIR . 'includes/class-itkt-environment.php';
require_once ITKT_DIR . 'includes/class-itkt-adapters.php';
require_once ITKT_DIR . 'includes/class-itkt-builder-adapters.php';
require_once ITKT_DIR . 'includes/class-itkt-content.php';
require_once ITKT_DIR . 'includes/class-itkt-product-translations.php';
require_once ITKT_DIR . 'includes/class-itkt-term-translations.php';
require_once ITKT_DIR . 'includes/class-itkt-woocommerce.php';
require_once ITKT_DIR . 'includes/class-itkt-elementor.php';
require_once ITKT_DIR . 'includes/class-itkt-frontend.php';
require_once ITKT_DIR . 'includes/class-itkt-seo.php';
require_once ITKT_DIR . 'includes/class-itkt-native-translations.php';
require_once ITKT_DIR . 'includes/class-itkt-strings.php';
require_once ITKT_DIR . 'includes/class-itkt-diagnostics.php';
require_once ITKT_DIR . 'includes/class-itkt-inline-editor.php';
require_once ITKT_DIR . 'includes/class-itkt-admin.php';

register_activation_hook( __FILE__, array( 'ITKT_Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'ITKT_Plugin', 'deactivate' ) );
ITKT_Plugin::instance()->boot();
