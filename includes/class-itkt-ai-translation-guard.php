<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Protect executable/template syntax before sending a catalog string to a translation service. */
class ITKT_AI_Translation_Guard {
    private const TOKEN_PATTERN = '~<!--.*?-->|<[^>]+>|\[\[?/?[A-Za-z_][^\]\r\n]*\]\]?|\{\{\d+\}\}|%(?:\d+\$)?[-+0\x20\#]*(?:\d+|\*)?(?:\.(?:\d+|\*))?[bcdeEfFgGosuxX%]~s';

    /** No source text or credential is persisted by this helper. */
    public static function protect( $source ) {
        $source = (string) $source;
        $prefix = 'ITKTKEEP' . bin2hex( random_bytes( 8 ) ) . 'X';
        $tokens = array();
        $ordered = array();
        $masked = preg_replace_callback( self::TOKEN_PATTERN, static function( $match ) use ( $prefix, &$tokens, &$ordered ) {
            $marker = $prefix . count( $tokens ) . 'END';
            $value = $match[0];
            $tokens[ $marker ] = $value;
            // HTML, shortcode syntax and non-positional printf placeholders must retain order.
            if ( '<' === $value[0] || '[' === $value[0] || ( '%' === $value[0] && '%%' !== $value && ! preg_match( '/^%\d+\$/', $value ) ) ) {
                $ordered[] = $marker;
            }
            return $marker;
        }, $source );
        return array( 'text' => $masked, 'prefix' => $prefix, 'tokens' => $tokens, 'ordered' => $ordered );
    }

    /** A failed validation never returns partially restored content for saving. */
    public static function restore( $translated, $protected ) {
        if ( ! is_string( $translated ) || 1 !== preg_match('//u', $translated) || '' === trim( $translated ) || strlen( $translated ) > 100000 ) {
            return new WP_Error( 'itkt_ai_invalid_output', 'Die API hat keinen gültigen Übersetzungstext geliefert.' );
        }
        if ( ! is_array( $protected ) || ! isset( $protected['prefix'], $protected['tokens'], $protected['ordered'] ) ) {
            return new WP_Error( 'itkt_ai_invalid_template', 'Ungültige Übersetzungsvorlage.' );
        }
        $tokens = $protected['tokens'];
        foreach ( $tokens as $marker => $value ) {
            if ( 1 !== substr_count( $translated, $marker ) ) {
                return new WP_Error( 'itkt_ai_placeholder_mismatch', 'Die API hat einen geschützten Platzhalter verändert, entfernt oder verdoppelt.' );
            }
        }
        $last = -1;
        foreach ( $protected['ordered'] as $marker ) {
            $position = strpos( $translated, $marker );
            if ( false === $position || $position <= $last ) {
                return new WP_Error( 'itkt_ai_markup_order', 'Die API hat die Reihenfolge von Markup oder Format-Platzhaltern verändert.' );
            }
            $last = $position;
        }
        $plain = str_replace( array_keys( $tokens ), '', $translated );
        if ( false !== strpos( $plain, $protected['prefix'] ) || preg_match( self::TOKEN_PATTERN, $plain ) ) {
            return new WP_Error( 'itkt_ai_unexpected_markup', 'Die API hat zusätzliche Formatierungen oder unbekannte Platzhalter eingefügt.' );
        }
        return strtr( trim( $translated ), $tokens );
    }
}
