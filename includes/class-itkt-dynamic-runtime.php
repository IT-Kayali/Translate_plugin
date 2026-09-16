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

    /** Representative counts for CLDR cardinal categories used by supported languages. */
    private function plural_samples( $language ) {
        $language = sanitize_key( (string) $language );
        if ( 'ar' === $language ) {
            return array( 'zero'=>0, 'one'=>1, 'two'=>2, 'few'=>3, 'many'=>11, 'other'=>100 );
        }
        if ( 'tr' === $language ) {
            return array( 'other'=>2 );
        }
        // German, English, French, Spanish, Swedish and Dutch are safely represented by
        // one/other for the native gettext catalogs bundled by WordPress/WooCommerce.
        return array( 'one'=>1, 'other'=>2 );
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
        if ( ! $language || ! $this->enabled() ) { return $empty; }

        $runtime_version = max( 1, absint( get_option( 'itkt_runtime_data_version', 1 ) ) );
        $cache_key = 'plural_' . md5( $language . '|' . $runtime_version );
        $cached = wp_cache_get( $cache_key, 'itkt-runtime' );
        if ( is_array( $cached ) ) { return $cached; }

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
            $forms = array();
            foreach ( $this->plural_samples( $language ) as $category => $number ) {
                if ( '' !== $override ) {
                    $translated = $native->materialize_tokens( $override, $single );
                } else {
                    $translated = (string) ( $native->translate_plural( $single, $plural, $number, $domain, $language, $context ) ?? '' );
                }
                if ( '' === $translated ) { $translated = ( 1 === (int) $number ) ? $single : $plural; }
                $forms[ $category ] = $translated;
            }
            $one  = (string) ( $forms['one'] ?? reset( $forms ) ?: $single );
            $many = (string) ( $forms['other'] ?? end( $forms ) ?: $plural );

            $key = $single . "\0" . $plural;
            $value = array( 'single'=>$one, 'plural'=>$many, 'forms'=>$forms );
            if ( '' === $context ) {
                if ( ! isset( $out['ngettext'][ $domain ] ) ) { $out['ngettext'][ $domain ] = array(); }
                $out['ngettext'][ $domain ][ $key ] = $value;
            } else {
                if ( ! isset( $out['ngettextContext'][ $domain ] ) ) { $out['ngettextContext'][ $domain ] = array(); }
                if ( ! isset( $out['ngettextContext'][ $domain ][ $context ] ) ) { $out['ngettextContext'][ $domain ][ $context ] = array(); }
                $out['ngettextContext'][ $domain ][ $context ][ $key ] = $value;
            }
        }
        wp_cache_set( $cache_key, $out, 'itkt-runtime', HOUR_IN_SECONDS );
        return $out;
    }

    public function assets() {
        if ( is_admin() || ! $this->enabled() ) { return; }
        $code = ITKT_Languages::instance()->current_code();
        $plural = $this->plural_map( $code );
        wp_enqueue_script(
            'itkt-dynamic-runtime',
            ITKT_URL . 'public/assets/dynamic-runtime.js',
            array( 'itkt-frontend', 'itkt-i18n-runtime' ),
            ITKT_VERSION,
            true
        );
        $active = ITKT_Languages::instance()->get_active();
        $locale = (string) ( $active[ $code ]['locale'] ?? $code );
        wp_localize_script( 'itkt-dynamic-runtime', 'ITKTDynamicRuntime', array(
            'language'        => $code,
            'locale'          => str_replace( '_', '-', $locale ),
            'ngettext'        => $plural['ngettext'],
            'ngettextContext' => $plural['ngettextContext'],
        ) );
    }
}
