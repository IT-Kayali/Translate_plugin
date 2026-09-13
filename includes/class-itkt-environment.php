<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class ITKT_Environment {
    public static function detect() {
        $theme = wp_get_theme();
        $template = strtolower( (string) $theme->get_template() );
        $stylesheet = strtolower( (string) $theme->get_stylesheet() );
        $is_woodmart = ( false !== strpos( $template, 'woodmart' ) || false !== strpos( $stylesheet, 'woodmart' ) || defined( 'WOODMART_VERSION' ) );

        return array(
            'wordpress' => array( 'label' => 'WordPress', 'active' => true, 'version' => get_bloginfo( 'version' ) ),
            'woocommerce' => array( 'label' => 'WooCommerce', 'active' => class_exists( 'WooCommerce' ), 'version' => defined( 'WC_VERSION' ) ? WC_VERSION : '' ),
            'elementor' => array( 'label' => 'Elementor', 'active' => did_action( 'elementor/loaded' ) || defined( 'ELEMENTOR_VERSION' ), 'version' => defined( 'ELEMENTOR_VERSION' ) ? ELEMENTOR_VERSION : '' ),
            'woodmart' => array( 'label' => 'WoodMart', 'active' => $is_woodmart, 'version' => defined( 'WOODMART_VERSION' ) ? WOODMART_VERSION : $theme->get( 'Version' ) ),
            'rankmath' => array( 'label' => 'Rank Math SEO', 'active' => defined( 'RANK_MATH_VERSION' ), 'version' => defined( 'RANK_MATH_VERSION' ) ? RANK_MATH_VERSION : '' ),
            'yoast' => array( 'label' => 'Yoast SEO', 'active' => defined( 'WPSEO_VERSION' ), 'version' => defined( 'WPSEO_VERSION' ) ? WPSEO_VERSION : '' ),
            'gutenberg' => array( 'label' => 'Gutenberg / Block Editor', 'active' => true, 'version' => '' ),
        );
    }

    public static function theme_name() {
        return wp_get_theme()->get( 'Name' );
    }
}
