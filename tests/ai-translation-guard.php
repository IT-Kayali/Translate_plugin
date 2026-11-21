<?php
// Unit tests with a minimal error stand-in. No WordPress install and no network calls.
define( 'ABSPATH', __DIR__ );
class WP_Error {
    public $code;
    public function __construct( $code, $message ) { $this->code = $code; }
}
require dirname( __DIR__ ) . '/includes/class-itkt-ai-translation-guard.php';
function check( $condition, $message ) {
    if ( ! $condition ) { fwrite( STDERR, 'FAIL ' . $message . PHP_EOL ); exit(1); }
    echo 'PASS ' . $message . PHP_EOL;
}
$source = '<strong>Hello %1$s</strong> [cart id="42"] {{1}} %2$d %%';
$protected = ITKT_AI_Translation_Guard::protect( $source );
$mock_response = str_replace( 'Hello', 'Hallo', $protected['text'] );
check( '<strong>Hallo %1$s</strong> [cart id="42"] {{1}} %2$d %%' === ITKT_AI_Translation_Guard::restore( $mock_response, $protected ), 'mock translation preserves HTML, shortcode, printf and ITKT variables' );
$markers = array_keys( $protected['tokens'] );
check( ITKT_AI_Translation_Guard::restore( str_replace( $markers[1], '', $mock_response ), $protected ) instanceof WP_Error, 'missing placeholder rejected' );
check( ITKT_AI_Translation_Guard::restore( $mock_response . $markers[1], $protected ) instanceof WP_Error, 'duplicate placeholder rejected' );
check( ITKT_AI_Translation_Guard::restore( $mock_response . '<script>bad()</script>', $protected ) instanceof WP_Error, 'new HTML rejected' );
check( ITKT_AI_Translation_Guard::restore( $mock_response . ' %s', $protected ) instanceof WP_Error, 'new format placeholder rejected' );
check( ITKT_AI_Translation_Guard::restore( $mock_response . ' ' . $protected['prefix'] . '999END', $protected ) instanceof WP_Error, 'unknown marker rejected' );
check( ITKT_AI_Translation_Guard::restore( '', $protected ) instanceof WP_Error, 'empty output rejected' );
$swapped = strtr( $mock_response, array( $markers[0] => $markers[2], $markers[2] => $markers[0] ) );
check( ITKT_AI_Translation_Guard::restore( $swapped, $protected ) instanceof WP_Error, 'reordered HTML rejected' );
$positional = ITKT_AI_Translation_Guard::protect( '%1$s has %2$d items' );
$keys = array_keys( $positional['tokens'] );
check( '%2$d Artikel für %1$s' === ITKT_AI_Translation_Guard::restore( $keys[1] . ' Artikel für ' . $keys[0], $positional ), 'numbered printf placeholders may move for target-language grammar' );
$plain = ITKT_AI_Translation_Guard::protect( 'Cart' );
check( 'السلة' === ITKT_AI_Translation_Guard::restore( 'السلة', $plain ), 'Arabic output supported' );
$nonpositional = ITKT_AI_Translation_Guard::protect( '%s: %d' );
$keys = array_keys( $nonpositional['tokens'] );
check( ITKT_AI_Translation_Guard::restore( $keys[1] . ': ' . $keys[0], $nonpositional ) instanceof WP_Error, 'non-positional printf order protected' );
check( $protected['prefix'] !== ITKT_AI_Translation_Guard::protect( $source )['prefix'], 'markers are unique per request' );
