<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Provider adapters must return one translation string or WP_Error, never save/publish it. */
interface ITKT_AI_Translation_Provider {
    public function translate( $protected_text, $source_language, $target_language );
}

/** Bounded suggestion generation. Not wired to admin actions until provider/consent is configured. */
class ITKT_AI_Translation_Service {
    const MAX_ITEMS = 10;
    const MAX_SOURCE_BYTES = 4000;
    const MAX_BATCH_BYTES = 12000;

    /**
     * Accept catalog rows only, not posts/orders/customer objects. No database writes are made.
     * Each row is {id, text, existing_translation?}. The caller controls the actual catalog scope.
     * Adapters must not retry billable requests automatically. Retries require an explicit action.
     */
    public function generate_suggestions( $rows, $source_language, $target_language, $provider ) {
        if ( ! $provider instanceof ITKT_AI_Translation_Provider ) {
            return new WP_Error( 'itkt_ai_provider_missing', 'Kein Übersetzungsanbieter eingerichtet.' );
        }
        if ( ! is_string( $source_language ) || ! is_string( $target_language ) || ! preg_match( '/^[a-z]{2,3}(?:-[A-Za-z]{2,8})?$/', $source_language ) || ! preg_match( '/^[a-z]{2,3}(?:-[A-Za-z]{2,8})?$/', $target_language ) || $source_language === $target_language ) {
            return new WP_Error( 'itkt_ai_languages', 'Bitte unterschiedliche gültige Quell- und Zielsprachen wählen.' );
        }
        if ( ! is_array( $rows ) || ! $rows || count( $rows ) > self::MAX_ITEMS ) {
            return new WP_Error( 'itkt_ai_batch_limit', 'Ein Auftrag muss zwischen 1 und 10 Texte enthalten.' );
        }
        $ids = array(); $bytes = 0;
        // Validate the entire batch before the first provider request can incur a charge.
        foreach ( $rows as $row ) {
            if ( ! is_array( $row ) || ! isset( $row['id'], $row['text'] ) || ! is_int( $row['id'] ) || $row['id'] < 1 || isset( $ids[ $row['id'] ] ) || ! is_string( $row['text'] ) || 1 !== preg_match('//u', $row['text']) || '' === trim( $row['text'] ) || strlen( $row['text'] ) > self::MAX_SOURCE_BYTES || ( isset( $row['existing_translation'] ) && ! is_string( $row['existing_translation'] ) ) ) {
                return new WP_Error( 'itkt_ai_invalid_row', 'Ein Text ist ungültig, zu lang oder doppelt ausgewählt.' );
            }
            $ids[ $row['id'] ] = true;
            $bytes += strlen( $row['text'] );
        }
        if ( $bytes > self::MAX_BATCH_BYTES ) {
            return new WP_Error( 'itkt_ai_batch_bytes', 'Der Auftrag enthält zu viel Text. Bitte weniger Einträge wählen.' );
        }
        $result = array( 'suggestions' => array(), 'skipped' => array(), 'errors' => array(), 'requests' => 0 );
        foreach ( $rows as $row ) {
            $id = $row['id'];
            if ( isset( $row['existing_translation'] ) && '' !== trim( $row['existing_translation'] ) ) {
                $result['skipped'][] = $id;
                continue;
            }
            $protected = ITKT_AI_Translation_Guard::protect( $row['text'] );
            $result['requests']++;
            try {
                $translated = $provider->translate( $protected['text'], $source_language, $target_language );
            } catch ( Throwable $error ) {
                // Do not expose provider exception messages, which may contain credentials/content.
                $result['errors'][ $id ] = 'itkt_ai_provider_exception';
                break;
            }
            if ( $translated instanceof WP_Error ) {
                $result['errors'][ $id ] = 'itkt_ai_provider_error';
                break; // Stop on provider errors, including limits/auth failures; never retry here.
            }
            $restored = ITKT_AI_Translation_Guard::restore( $translated, $protected );
            if ( $restored instanceof WP_Error ) {
                $result['errors'][ $id ] = 'itkt_ai_output_rejected';
                continue;
            }
            $result['suggestions'][ $id ] = array(
                'source_hash' => hash( 'sha256', $row['text'] ),
                'language' => $target_language,
                'translation' => $restored,
                'review_required' => true,
            );
        }
        return $result;
    }
}
