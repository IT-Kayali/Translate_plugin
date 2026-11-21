<?php
require __DIR__ . '/ai-translation-guard.php';
require dirname( __DIR__ ) . '/includes/class-itkt-ai-translation-service.php';
class ITKT_Mock_AI_Provider implements ITKT_AI_Translation_Provider {
    public $calls = 0;
    public $mode = 'success';
    public function translate( $text, $source, $target ) {
        $this->calls++;
        if ( 'error' === $this->mode ) { return new WP_Error( 'limited', 'Provider error' ); }
        if ( 'exception' === $this->mode ) { throw new RuntimeException( 'Private diagnostic must not be returned' ); }
        if ( 'invalid' === $this->mode ) { return '<script>bad()</script>'; }
        return str_replace( 'Cart', 'Warenkorb', $text );
    }
}
$service = new ITKT_AI_Translation_Service();
$provider = new ITKT_Mock_AI_Provider();
$rows = array( array( 'id'=>1, 'text'=>'Cart %1$s' ), array( 'id'=>2, 'text'=>'Cart', 'existing_translation'=>'Manuell übersetzt' ) );
$result = $service->generate_suggestions( $rows, 'en', 'de', $provider );
check( 1 === $provider->calls && array(2) === $result['skipped'], 'existing translations skipped without provider call' );
check( 'Warenkorb %1$s' === $result['suggestions'][1]['translation'] && true === $result['suggestions'][1]['review_required'], 'mock generation returns protected review-only suggestion' );
check( hash( 'sha256', 'Cart %1$s' ) === $result['suggestions'][1]['source_hash'], 'source hash supports later stale-source checks' );
$provider = new ITKT_Mock_AI_Provider();
check( $service->generate_suggestions( array_fill( 0, 11, $rows[0] ), 'en', 'de', $provider ) instanceof WP_Error && 0 === $provider->calls, 'item limit enforced before calls' );
check( $service->generate_suggestions( array( $rows[0], array( 'id'=>3, 'text'=>str_repeat('x',4001) ) ), 'en', 'de', $provider ) instanceof WP_Error && 0 === $provider->calls, 'entire batch prevalidated before calls' );
check( $service->generate_suggestions( array( $rows[0], $rows[0] ), 'en', 'de', $provider ) instanceof WP_Error && 0 === $provider->calls, 'duplicate IDs rejected before calls' );
$large = array(); for($i=1;$i<=4;$i++) { $large[]=array('id'=>$i,'text'=>str_repeat('x',4000)); }
check( $service->generate_suggestions( $large, 'en', 'de', $provider ) instanceof WP_Error && 0 === $provider->calls, 'batch byte limit enforced before calls' );
check( $service->generate_suggestions( $rows, 'en', 'en', $provider ) instanceof WP_Error && 0 === $provider->calls, 'same-language request rejected' );
$provider->mode = 'error';
$result = $service->generate_suggestions( array( $rows[0], array('id'=>2,'text'=>'Cart') ), 'en', 'de', $provider );
check( 1 === $provider->calls && ! $result['suggestions'] && isset($result['errors'][1]), 'provider failure stops batch without retry' );
$provider = new ITKT_Mock_AI_Provider(); $provider->mode = 'exception';
$result = $service->generate_suggestions( array($rows[0]), 'en', 'de', $provider );
check( 'itkt_ai_provider_exception' === $result['errors'][1] && false === strpos( json_encode($result), 'Private diagnostic' ), 'provider exception details are not exposed' );
$provider = new ITKT_Mock_AI_Provider(); $provider->mode = 'invalid';
$result = $service->generate_suggestions( array($rows[0]), 'en', 'de', $provider );
check( ! $result['suggestions'] && 'itkt_ai_output_rejected' === $result['errors'][1], 'unsafe provider output cannot become a suggestion' );
check( ! function_exists('update_option'), 'suggestion service makes no WordPress writes' );
