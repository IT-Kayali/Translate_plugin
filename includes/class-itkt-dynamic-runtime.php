<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Extra JavaScript runtime coverage for dynamic WooCommerce / WoodMart interfaces.
 *
 * This module deliberately extends the stable frontend runtime instead of changing theme or
 * WooCommerce source files. It adds plural @wordpress/i18n overrides and re-applies the published
 * ITKT maps after AJAX / Blocks / fragment events that can replace frontend markup.
 */
class ITKT_Dynamic_Runtime {
    private static $instance = null;

    public static function instance() {
        if ( null === self::$instance ) { self::$instance = new self(); }
        return self::$instance;
    }

    private function __construct() {
        add_action( 'wp_enqueue_scripts', array( $this, 'assets' ), 35 );
    }

    private function enabled() {
        return ITKT_Plugin::module_enabled( 'strings' ) && class_exists( 'ITKT_Native_Translations' );
    }

    private function table( $name ) {
        global $wpdb;
        return $wpdb->prefix . 'itkt_' . $name;
    }

    /**
     * Build a compact plural map for the active frontend language.
     * Existing manual ITKT overrides win; otherwise the installed native catalog is used.
     */
    private function plural_map( $language ) {
        global $wpdb;
        $language = sanitize_key( (string) $language );
        $default  = ITKT_Languages::instance()->get_default_code();
        $empty    = array( 'ngettext'=>array(), 'ngettextContext'=>array() );
        if ( ! $language || $language === $default || ! $this->enabled() ) { return $empty; }

        $native = ITKT_Native_Translations::instance();
        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT s.domain, s.context, s.original, s.plural, t.translation
             FROM " . $this->table( 'strings' ) . " s
             LEFT JOIN " . $this->table( 'string_translations' ) . " t
               ON t.string_id=s.id AND t.language=%s
             WHERE s.call_type='native_catalog' AND s.plural<>''",
            $language
        ), ARRAY_A );

        $by_key = array();
        foreach ( (array) $rows as $row ) {
            $key = (string) $row['domain'] . "\0" . (string) $row['context'] . "\0" . (string) $row['original'] . "\0" . (string) $row['plural'];
            $by_key[ $key ] = $row;
        }
        foreach ( (array) $native->known_js_sources() as $known ) {
            $plural = (string) ( $known['plural'] ?? '' );
            if ( '' === $plural ) { continue; }
            $key = (string) ( $known['domain'] ?? 'default' ) . "\0" . (string) ( $known['context'] ?? '' ) . "\0" . (string) ( $known['source'] ?? '' ) . "\0" . $plural;
            if ( ! isset( $by_key[ $key ] ) ) {
                $by_key[ $key ] = array(
                    'domain'      => $known['domain'] ?? 'default',
                    'context'     => $known['context'] ?? '',
                    'original'    => $known['source'] ?? '',
                    'plural'      => $plural,
                    'translation' => '',
                );
            }
        }

        $out = $empty;
        foreach ( $by_key as $row ) {
            $domain  = sanitize_key( (string) ( $row['domain'] ?? 'default' ) ) ?: 'default';
            $context = (string) ( $row['context'] ?? '' );
            $single  = (string) ( $row['original'] ?? '' );
            $plural  = (string) ( $row['plural'] ?? '' );
            if ( '' === $single || '' === $plural ) { continue; }

            $override = trim( (string) ( $row['translation'] ?? '' ) );
            if ( '' !== $override ) {
                $one = $native->materialize_tokens( $override, $single );
                $many = $one;
            } else {
                $one = (string) ( $native->translate_plural( $single, $plural, 1, $domain, $language, $context ) ?? '' );
                $many = (string) ( $native->translate_plural( $single, $plural, 2, $domain, $language, $context ) ?? '' );
            }
            if ( '' === $one ) { $one = $single; }
            if ( '' === $many ) { $many = $plural; }

            $key = $single . "\0" . $plural;
            $value = array( 'single'=>$one, 'plural'=>$many );
            if ( '' === $context ) {
                if ( ! isset( $out['ngettext'][ $domain ] ) ) { $out['ngettext'][ $domain ] = array(); }
                $out['ngettext'][ $domain ][ $key ] = $value;
            } else {
                if ( ! isset( $out['ngettextContext'][ $domain ] ) ) { $out['ngettextContext'][ $domain ] = array(); }
                if ( ! isset( $out['ngettextContext'][ $domain ][ $context ] ) ) { $out['ngettextContext'][ $domain ][ $context ] = array(); }
                $out['ngettextContext'][ $domain ][ $context ][ $key ] = $value;
            }
        }
        return $out;
    }

    public function assets() {
        if ( is_admin() || ! $this->enabled() ) { return; }
        $code = ITKT_Languages::instance()->current_code();
        if ( $code === ITKT_Languages::instance()->get_default_code() ) { return; }

        $plural = $this->plural_map( $code );
        wp_enqueue_script(
            'itkt-dynamic-runtime',
            ITKT_URL . 'public/assets/dynamic-runtime.js',
            array( 'itkt-frontend', 'itkt-i18n-runtime' ),
            ITKT_VERSION,
            true
        );
        wp_localize_script( 'itkt-dynamic-runtime', 'ITKTDynamicRuntime', array(
            'language'        => $code,
            'ngettext'        => $plural['ngettext'],
            'ngettextContext' => $plural['ngettextContext'],
        ) );
    }
}
