<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * String translation service.
 *
 * V0.8 deliberately keeps translations outside plugin/theme source files. A scanner reads
 * literal WordPress gettext calls and stores them in dedicated plugin tables. Runtime gettext
 * filters then apply a saved override for the currently selected IT-Kayali language.
 */
class ITKT_Strings {
    private static $instance = null;
    private $runtime_cache = array();
    private $global_runtime_cache = array();

    public static function instance() {
        if ( null === self::$instance ) { self::$instance = new self(); }
        return self::$instance;
    }

    private function __construct() {
        add_filter( 'gettext', array( $this, 'filter_gettext' ), 100, 3 );
        add_filter( 'gettext_with_context', array( $this, 'filter_gettext_context' ), 100, 4 );
        add_filter( 'ngettext', array( $this, 'filter_ngettext' ), 100, 5 );
        add_filter( 'ngettext_with_context', array( $this, 'filter_ngettext_context' ), 100, 6 );
    }

    public static function install_schema() {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset = $wpdb->get_charset_collate();
        $strings = $wpdb->prefix . 'itkt_strings';
        $sources = $wpdb->prefix . 'itkt_string_sources';
        $translations = $wpdb->prefix . 'itkt_string_translations';

        dbDelta( "CREATE TABLE {$strings} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            domain varchar(191) NOT NULL DEFAULT 'default',
            context varchar(191) NOT NULL DEFAULT '',
            original longtext NOT NULL,
            plural longtext NULL,
            string_hash char(64) NOT NULL,
            call_type varchar(32) NOT NULL DEFAULT '__',
            last_seen datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY string_hash (string_hash),
            KEY domain (domain)
        ) {$charset};" );

        dbDelta( "CREATE TABLE {$sources} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            string_id bigint(20) unsigned NOT NULL,
            source_type varchar(20) NOT NULL,
            source_key varchar(191) NOT NULL,
            file varchar(255) NOT NULL DEFAULT '',
            line int(10) unsigned NOT NULL DEFAULT 0,
            source_hash char(64) NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY source_hash (source_hash),
            KEY string_id (string_id),
            KEY source_lookup (source_type, source_key)
        ) {$charset};" );

        dbDelta( "CREATE TABLE {$translations} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            string_id bigint(20) unsigned NOT NULL,
            language varchar(12) NOT NULL,
            translation longtext NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY string_language (string_id, language),
            KEY language (language)
        ) {$charset};" );

        update_option( 'itkt_strings_db_version', '1.0', false );
    }

    public static function maybe_install_schema() {
        if ( '1.0' !== (string) get_option( 'itkt_strings_db_version', '' ) ) {
            self::install_schema();
        }
    }

    private function table( $name ) {
        global $wpdb;
        return $wpdb->prefix . 'itkt_' . $name;
    }

    public function filter_gettext( $translation, $text, $domain ) {
        $override = $this->runtime_translation( (string) $text, (string) $domain, '' );
        if ( null !== $override ) { return $override; }
        $global = $this->runtime_global_exact_translation( (string) $translation, (string) $text );
        if ( null !== $global ) { return $global; }
        $code = ITKT_Languages::instance()->current_code();
        if ( $code !== ITKT_Languages::instance()->get_default_code() && class_exists( 'ITKT_Native_Translations' ) ) {
            $native = ITKT_Native_Translations::instance()->translate( (string) $text, (string) $domain, $code, '' );
            if ( null !== $native && '' !== $native ) { return $native; }
        }
        return $translation;
    }

    public function filter_gettext_context( $translation, $text, $context, $domain ) {
        $override = $this->runtime_translation( (string) $text, (string) $domain, (string) $context );
        if ( null !== $override ) { return $override; }
        $global = $this->runtime_global_exact_translation( (string) $translation, (string) $text );
        if ( null !== $global ) { return $global; }
        $code = ITKT_Languages::instance()->current_code();
        if ( $code !== ITKT_Languages::instance()->get_default_code() && class_exists( 'ITKT_Native_Translations' ) ) {
            $native = ITKT_Native_Translations::instance()->translate( (string) $text, (string) $domain, $code, (string) $context );
            if ( null !== $native && '' !== $native ) { return $native; }
        }
        return $translation;
    }

    public function filter_ngettext( $translation, $single, $plural, $number, $domain ) {
        $code = ITKT_Languages::instance()->current_code();
        if ( $code !== ITKT_Languages::instance()->get_default_code() && class_exists( 'ITKT_Native_Translations' ) ) {
            $native = ITKT_Native_Translations::instance()->translate_plural( (string) $single, (string) $plural, (int) $number, (string) $domain, $code, '' );
            if ( null !== $native && '' !== $native ) { return $native; }
        }
        return $translation;
    }

    public function filter_ngettext_context( $translation, $single, $plural, $number, $context, $domain ) {
        $code = ITKT_Languages::instance()->current_code();
        if ( $code !== ITKT_Languages::instance()->get_default_code() && class_exists( 'ITKT_Native_Translations' ) ) {
            $native = ITKT_Native_Translations::instance()->translate_plural( (string) $single, (string) $plural, (int) $number, (string) $domain, $code, (string) $context );
            if ( null !== $native && '' !== $native ) { return $native; }
        }
        return $translation;
    }

    private function runtime_translation( $text, $domain, $context ) {
        if ( ! ITKT_Plugin::module_enabled( 'strings' ) || '' === $text ) { return null; }
        $code = ITKT_Languages::instance()->current_code();
        if ( $code === ITKT_Languages::instance()->get_default_code() ) { return null; }

        if ( ! isset( $this->runtime_cache[ $code ] ) ) {
            $this->runtime_cache[ $code ] = $this->load_language_cache( $code );
        }
        $hash = $this->make_hash( $domain ?: 'default', $context, $text, '' );
        if ( ! array_key_exists( $hash, $this->runtime_cache[ $code ] ) ) { return null; }
        $value = $this->runtime_cache[ $code ][ $hash ];
        if ( class_exists( 'ITKT_Native_Translations' ) ) {
            $value = ITKT_Native_Translations::instance()->materialize_tokens( $value, $text );
        }
        return $value;
    }

    private function load_language_cache( $code ) {
        global $wpdb;
        $strings = $this->table( 'strings' );
        $translations = $this->table( 'string_translations' );
        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT s.string_hash, t.translation FROM {$translations} t INNER JOIN {$strings} s ON s.id=t.string_id WHERE t.language=%s AND t.translation<>''",
            sanitize_key( $code )
        ), ARRAY_A );
        $cache = array();
        foreach ( (array) $rows as $row ) { $cache[ $row['string_hash'] ] = (string) $row['translation']; }
        return $cache;
    }

    private function make_hash( $domain, $context, $original, $plural = '' ) {
        return hash( 'sha256', (string) $domain . "\0" . (string) $context . "\0" . (string) $original . "\0" . (string) $plural );
    }

    /** Ensure a canonical gettext source row exists so live/native overrides use the same key as gettext. */
    public function ensure_canonical_string( $original, $domain = 'default', $context = '', $plural = '' ) {
        global $wpdb;
        $original = (string) $original;
        if ( '' === $original ) { return 0; }
        $domain = sanitize_key( (string) $domain ) ?: 'default';
        $context = (string) $context;
        $plural = (string) $plural;
        $hash = $this->make_hash( $domain, $context, $original, $plural );
        $table = $this->table( 'strings' );
        $id = absint( $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE string_hash=%s", $hash ) ) );
        if ( $id ) {
            $wpdb->update( $table, array( 'last_seen'=>current_time('mysql') ), array( 'id'=>$id ), array('%s'), array('%d') );
            return $id;
        }
        $wpdb->insert( $table, array(
            'domain'=>$domain, 'context'=>$context, 'original'=>$original, 'plural'=>$plural,
            'string_hash'=>$hash, 'call_type'=>'native_catalog', 'last_seen'=>current_time('mysql'),
        ), array('%s','%s','%s','%s','%s','%s','%s') );
        return absint( $wpdb->insert_id );
    }

    public function canonical_string( $string_id ) {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . $this->table('strings') . ' WHERE id=%d', absint($string_id) ), ARRAY_A );
    }


    /** Normalize a visible system string without changing its actual wording. */
    private function normalize_global_source( $text ) {
        $text = trim( wp_strip_all_tags( (string) $text, true ) );
        return preg_replace( '/\s+/u', ' ', $text );
    }

    /**
     * Register a reusable frontend/system string. Unlike locator-based visual strings this
     * entry is keyed by its source wording, so one translation can be reused everywhere.
     * $mode is either exact or pattern. Pattern strings may contain {{1}}, {{2}}, ... tokens.
     */
    public function ensure_global_string( $original, $scope = 'global', $mode = 'exact' ) {
        global $wpdb;
        $mode = in_array( $mode, array( 'exact', 'pattern' ), true ) ? $mode : 'exact';
        $original = $this->normalize_global_source( $original );
        if ( '' === $original ) { return 0; }
        $hash = hash( 'sha256', "itkt-global\0" . $mode . "\0" . $original );
        $table = $this->table( 'strings' );
        $id = absint( $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE string_hash=%s", $hash ) ) );
        $data = array(
            'domain'      => 'itkt-global',
            'context'     => $mode,
            'original'    => $original,
            'plural'      => '',
            'string_hash' => $hash,
            'call_type'   => 'global_visual',
            'last_seen'   => current_time( 'mysql' ),
        );
        if ( $id ) {
            $wpdb->update( $table, array( 'last_seen'=>$data['last_seen'], 'call_type'=>'global_visual' ), array( 'id'=>$id ), array( '%s','%s' ), array( '%d' ) );
        } else {
            $wpdb->insert( $table, $data, array( '%s','%s','%s','%s','%s','%s','%s' ) );
            $id = absint( $wpdb->insert_id );
        }
        if ( $id ) {
            $this->upsert_source( $id, 'visual-global', sanitize_key( $scope ) ?: 'global', $mode, 0 );
        }
        return $id;
    }

    public function global_string( $original, $mode = 'exact' ) {
        global $wpdb;
        $mode = in_array( $mode, array( 'exact', 'pattern' ), true ) ? $mode : 'exact';
        $original = $this->normalize_global_source( $original );
        if ( '' === $original ) { return null; }
        $hash = hash( 'sha256', "itkt-global\0" . $mode . "\0" . $original );
        $row = $wpdb->get_row( $wpdb->prepare(
            'SELECT id, context, original FROM ' . $this->table( 'strings' ) . ' WHERE string_hash=%s AND domain=%s',
            $hash, 'itkt-global'
        ), ARRAY_A );
        return $row ?: null;
    }

    /** Global exact/pattern translations for DOM runtime replacement. */
    public function global_runtime_map( $language ) {
        global $wpdb;
        $language = sanitize_key( (string) $language );
        if ( ! $language || $language === ITKT_Languages::instance()->get_default_code() ) {
            return array( 'exact'=>array(), 'patterns'=>array() );
        }
        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT s.context AS mode, s.original, t.translation FROM " . $this->table( 'string_translations' ) . " t INNER JOIN " . $this->table( 'strings' ) . " s ON s.id=t.string_id WHERE s.domain=%s AND t.language=%s AND t.translation<>''",
            'itkt-global', $language
        ), ARRAY_A );
        $out = array( 'exact'=>array(), 'patterns'=>array() );
        foreach ( (array) $rows as $row ) {
            $source = $this->normalize_global_source( (string) ( $row['original'] ?? '' ) );
            $translation = (string) ( $row['translation'] ?? '' );
            if ( '' === $source || '' === $translation ) { continue; }
            if ( 'pattern' === ( $row['mode'] ?? '' ) ) {
                $out['patterns'][] = array( 'source'=>$source, 'translation'=>$translation );
            } else {
                $out['exact'][ $source ] = $translation;
            }
        }

        // Canonical WooCommerce/theme strings identified by the live editor also participate in
        // DOM runtime translation. This covers WooCommerce Blocks/JS fragments that bypass PHP
        // gettext while still keeping one canonical source key and one editable override.
        if ( class_exists( 'ITKT_Native_Translations' ) ) {
            $native = ITKT_Native_Translations::instance();
            $default = ITKT_Languages::instance()->get_default_code();
            $canonical_rows = $wpdb->get_results(
                "SELECT s.id, s.domain, s.context, s.original, s.plural, t.translation FROM " . $this->table('strings') . " s LEFT JOIN " . $this->table('string_translations') . " t ON t.string_id=s.id AND t.language='" . esc_sql($language) . "' WHERE s.call_type='native_catalog'",
                ARRAY_A
            );

            // Stable shared storefront labels (currently WoodMart checkout progress) should use
            // their native language pack immediately, even before an administrator clicks them
            // once in the live editor. Existing ITKT overrides continue to win.
            $by_native_key = array();
            foreach ( (array) $canonical_rows as $row ) {
                $key = (string)($row['domain'] ?? '') . "\0" . (string)($row['context'] ?? '') . "\0" . (string)($row['original'] ?? '');
                $by_native_key[$key] = $row;
            }
            if ( method_exists( $native, 'known_dom_sources' ) ) {
                foreach ( (array) $native->known_dom_sources() as $known ) {
                    $key = (string)($known['domain'] ?? '') . "\0" . (string)($known['context'] ?? '') . "\0" . (string)($known['source'] ?? '');
                    if ( ! isset( $by_native_key[$key] ) ) {
                        $by_native_key[$key] = array(
                            'id'=>0, 'domain'=>$known['domain'] ?? 'default', 'context'=>$known['context'] ?? '',
                            'original'=>$known['source'] ?? '', 'plural'=>$known['plural'] ?? '', 'translation'=>'',
                        );
                    }
                }
            }
            $canonical_rows = array_values( $by_native_key );
            $native_patterns = array();
            foreach ( (array) $canonical_rows as $row ) {
                $domain = (string)($row['domain'] ?? 'default');
                $context = (string)($row['context'] ?? '');
                $source_text = (string)($row['original'] ?? '');
                if ( '' === $source_text ) { continue; }
                $default_text = $native->translate( $source_text, $domain, $default, $context );
                if ( null === $default_text || '' === $default_text ) { $default_text = $source_text; }
                $target_text = (string)($row['translation'] ?? '');
                if ( '' === $target_text ) { $target_text = (string)($native->translate( $source_text, $domain, $language, $context ) ?? ''); }
                if ( '' === $target_text ) { continue; }
                $dom_source = $this->normalize_global_source( $native->editor_value( $default_text ) );
                $dom_target = $native->editor_value( $target_text );
                if ( '' === $dom_source || '' === $dom_target ) { continue; }
                if ( false !== strpos( $dom_source, '{{' ) ) {
                    $native_patterns[] = array( 'source'=>$dom_source, 'translation'=>$dom_target );
                } else {
                    $out['exact'][ $dom_source ] = $dom_target;
                }
            }
            if ( $native_patterns ) { $out['patterns'] = array_merge( $native_patterns, $out['patterns'] ); }
        }
        return $out;
    }

    /** Apply exact global strings inside gettext too, when source/default wording is available. */
    private function runtime_global_exact_translation( $translated_text, $source_text = '' ) {
        if ( ! ITKT_Plugin::module_enabled( 'strings' ) ) { return null; }
        $code = ITKT_Languages::instance()->current_code();
        if ( $code === ITKT_Languages::instance()->get_default_code() ) { return null; }
        if ( ! isset( $this->global_runtime_cache[ $code ] ) ) {
            $this->global_runtime_cache[ $code ] = $this->global_runtime_map( $code );
        }
        $exact = (array) ( $this->global_runtime_cache[ $code ]['exact'] ?? array() );
        foreach ( array( $translated_text, $source_text ) as $candidate ) {
            $candidate = $this->normalize_global_source( $candidate );
            if ( '' !== $candidate && array_key_exists( $candidate, $exact ) ) { return $exact[ $candidate ]; }
        }
        return null;
    }

    /**
     * JavaScript gettext overrides for @wordpress/i18n. WooCommerce Blocks render many labels
     * in React, so PHP gettext filters cannot affect them. This map is consumed by an early
     * wp.hooks i18n.gettext filter before the blocks mount.
     */
    public function js_i18n_runtime_map( $language ) {
        global $wpdb;
        $language = sanitize_key( (string) $language );
        $default = ITKT_Languages::instance()->get_default_code();
        if ( ! $language || $language === $default || ! class_exists( 'ITKT_Native_Translations' ) ) {
            return array( 'gettext'=>array(), 'gettextContext'=>array() );
        }

        $native = ITKT_Native_Translations::instance();
        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT s.id, s.domain, s.context, s.original, t.translation
             FROM " . $this->table('strings') . " s
             LEFT JOIN " . $this->table('string_translations') . " t
               ON t.string_id=s.id AND t.language=%s
             WHERE s.call_type='native_catalog'",
            $language
        ), ARRAY_A );

        // Include known WooCommerce Blocks sources even before the live editor has registered
        // a database row. Native target catalogs may already provide the desired language.
        $by_key = array();
        foreach ( (array) $rows as $row ) {
            $key = (string)$row['domain'] . "\0" . (string)$row['context'] . "\0" . (string)$row['original'];
            $by_key[$key] = $row;
        }
        foreach ( $native->known_js_sources() as $known ) {
            $key = (string)$known['domain'] . "\0" . (string)$known['context'] . "\0" . (string)$known['source'];
            if ( ! isset( $by_key[$key] ) ) {
                $by_key[$key] = array(
                    'id'=>0, 'domain'=>$known['domain'], 'context'=>$known['context'],
                    'original'=>$known['source'], 'translation'=>'',
                );
            }
        }

        $out = array( 'gettext'=>array(), 'gettextContext'=>array() );
        foreach ( $by_key as $row ) {
            $domain = sanitize_key( (string)($row['domain'] ?? 'default') ) ?: 'default';
            $context = (string)($row['context'] ?? '');
            $source = (string)($row['original'] ?? '');
            if ( '' === $source ) { continue; }

            $target = (string)($row['translation'] ?? '');
            $has_override = '' !== trim( $target );
            if ( $has_override ) {
                // Editor tokens {{1}} become the printf placeholder expected by wp.i18n.sprintf.
                $target = $native->materialize_tokens( $target, $source );
            } else {
                $target = (string)( $native->translate( $source, $domain, $language, $context ) ?? '' );
            }
            if ( '' === $target ) { continue; }

            // Important for English on a German WordPress installation: the desired target may be
            // exactly the original msgid (e.g. "Including %s"). We still MUST publish that mapping,
            // otherwise wp.i18n returns the globally loaded German translation before ITKT can
            // restore the English source wording. Explicit overrides that equal the source must also
            // remain valid for the same reason.
            if ( $target === $source && 'en' !== $language && ! $has_override ) { continue; }

            if ( '' === $context ) {
                if ( ! isset( $out['gettext'][$domain] ) ) { $out['gettext'][$domain] = array(); }
                $out['gettext'][$domain][$source] = $target;
            } else {
                if ( ! isset( $out['gettextContext'][$domain] ) ) { $out['gettextContext'][$domain] = array(); }
                if ( ! isset( $out['gettextContext'][$domain][$context] ) ) { $out['gettextContext'][$domain][$context] = array(); }
                $out['gettextContext'][$domain][$context][$source] = $target;
            }
        }
        return $out;
    }

    public function get_translation( $string_id, $language ) {
        global $wpdb;
        return (string) $wpdb->get_var( $wpdb->prepare(
            'SELECT translation FROM ' . $this->table( 'string_translations' ) . ' WHERE string_id=%d AND language=%s',
            absint( $string_id ), sanitize_key( $language )
        ) );
    }

    public function get_translations_for_ids( $ids, $languages ) {
        global $wpdb;
        $ids = array_values( array_filter( array_map( 'absint', (array) $ids ) ) );
        $languages = array_values( array_filter( array_map( 'sanitize_key', (array) $languages ) ) );
        if ( ! $ids || ! $languages ) { return array(); }
        $id_sql = implode( ',', $ids );
        $lang_sql = implode( ',', array_fill( 0, count( $languages ), '%s' ) );
        $sql = "SELECT string_id, language, translation FROM " . $this->table( 'string_translations' ) . " WHERE string_id IN ({$id_sql}) AND language IN ({$lang_sql})";
        $rows = $wpdb->get_results( $wpdb->prepare( $sql, ...$languages ), ARRAY_A );
        $out = array();
        foreach ( (array) $rows as $row ) { $out[ absint( $row['string_id'] ) ][ $row['language'] ] = (string) $row['translation']; }
        return $out;
    }

    public function save_translation( $string_id, $language, $translation ) {
        global $wpdb;
        $string_id = absint( $string_id );
        $language = sanitize_key( $language );
        if ( ! $string_id || ! isset( ITKT_Languages::instance()->get_active()[ $language ] ) ) { return false; }
        $translation = wp_kses_post( (string) $translation );

        // Empty means "remove my override". Do not persist an empty row: deleting it makes
        // fallback behavior deterministic and lets native WooCommerce/theme translations take over.
        if ( '' === trim( wp_strip_all_tags( $translation, true ) ) ) {
            return $this->delete_translation( $string_id, $language );
        }

        $table = $this->table( 'string_translations' );
        $existing = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE string_id=%d AND language=%s", $string_id, $language ) );
        $data = array( 'translation' => $translation, 'updated_at' => current_time( 'mysql' ) );
        if ( $existing ) {
            $wpdb->update( $table, $data, array( 'id' => absint( $existing ) ), array( '%s','%s' ), array( '%d' ) );
        } else {
            $wpdb->insert( $table, array_merge( array( 'string_id'=>$string_id, 'language'=>$language ), $data ), array( '%d','%s','%s','%s' ) );
        }
        unset( $this->runtime_cache[ $language ], $this->global_runtime_cache[ $language ] );
        return true;
    }

    public function delete_translation( $string_id, $language ) {
        global $wpdb;
        $string_id = absint( $string_id );
        $language = sanitize_key( $language );
        if ( ! $string_id || ! $language ) { return false; }
        $wpdb->delete(
            $this->table( 'string_translations' ),
            array( 'string_id'=>$string_id, 'language'=>$language ),
            array( '%d','%s' )
        );
        unset( $this->runtime_cache[ $language ], $this->global_runtime_cache[ $language ] );
        return true;
    }

    /**
     * Create or refresh a visual frontend string. Visual strings are intentionally stored
     * in the same translation tables as gettext strings, but use a locator-based fingerprint
     * so Widget/Header/Footer text can be translated without modifying theme/widget source data.
     */
    public function ensure_visual_string( $fingerprint, $original, $scope = 'global', $locator = '' ) {
        global $wpdb;
        $fingerprint = preg_replace( '/[^A-Fa-f0-9]/', '', (string) $fingerprint );
        if ( strlen( $fingerprint ) < 8 ) { return 0; }
        $fingerprint = substr( strtolower( $fingerprint ), 0, 64 );
        $original = trim( wp_strip_all_tags( (string) $original, true ) );
        if ( '' === $original ) { return 0; }

        $hash = hash( 'sha256', "itkt-visual\0" . $fingerprint );
        $table = $this->table( 'strings' );
        $id = absint( $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE string_hash=%s", $hash ) ) );
        $data = array(
            'domain'      => 'itkt-visual',
            'context'     => $fingerprint,
            'original'    => $original,
            'plural'      => '',
            'string_hash' => $hash,
            'call_type'   => 'visual',
            'last_seen'   => current_time( 'mysql' ),
        );
        if ( $id ) {
            $existing_original = (string) $wpdb->get_var( $wpdb->prepare( "SELECT original FROM {$table} WHERE id=%d", $id ) );
            $update = array( 'last_seen' => $data['last_seen'], 'call_type' => 'visual' );
            $formats = array( '%s','%s' );
            // A translated frontend page must never overwrite the source text stored for this
            // visual locator. Refresh the original only while the default language is active.
            if ( '' === $existing_original || ITKT_Languages::instance()->current_code() === ITKT_Languages::instance()->get_default_code() ) {
                $update['original'] = $original;
                $formats[] = '%s';
            }
            $wpdb->update( $table, $update, array( 'id' => $id ), $formats, array( '%d' ) );
        } else {
            $wpdb->insert( $table, $data, array( '%s','%s','%s','%s','%s','%s','%s' ) );
            $id = absint( $wpdb->insert_id );
        }

        if ( $id ) {
            $safe_locator = substr( sanitize_text_field( $locator ), 0, 240 );
            $this->upsert_source( $id, 'visual', sanitize_key( $scope ) ?: 'global', $safe_locator, 0 );
        }
        return $id;
    }

    /** Return one visual string plus its translations. */
    public function visual_string( $fingerprint ) {
        global $wpdb;
        $fingerprint = preg_replace( '/[^A-Fa-f0-9]/', '', (string) $fingerprint );
        if ( strlen( $fingerprint ) < 8 ) { return null; }
        $hash = hash( 'sha256', "itkt-visual\0" . substr( strtolower( $fingerprint ), 0, 64 ) );
        $row = $wpdb->get_row( $wpdb->prepare(
            'SELECT id, context, original FROM ' . $this->table( 'strings' ) . ' WHERE string_hash=%s AND domain=%s',
            $hash, 'itkt-visual'
        ), ARRAY_A );
        return $row ?: null;
    }

    /**
     * Lightweight runtime map for the current language. The browser applies these overrides
     * only to the exact locator fingerprint that was saved from the visual editor.
     */
    public function visual_runtime_map( $language ) {
        global $wpdb;
        $language = sanitize_key( (string) $language );
        if ( ! $language || $language === ITKT_Languages::instance()->get_default_code() ) { return array(); }
        $rows = $wpdb->get_results( $wpdb->prepare(
            'SELECT s.context AS fingerprint, t.translation FROM ' . $this->table( 'string_translations' ) . ' t INNER JOIN ' . $this->table( 'strings' ) . ' s ON s.id=t.string_id WHERE s.domain=%s AND t.language=%s AND t.translation<>\'\'',
            'itkt-visual', $language
        ), ARRAY_A );
        $out = array();
        foreach ( (array) $rows as $row ) {
            $fingerprint = preg_replace( '/[^A-Fa-f0-9]/', '', (string) ( $row['fingerprint'] ?? '' ) );
            if ( $fingerprint ) { $out[ strtolower( $fingerprint ) ] = (string) $row['translation']; }
        }
        return $out;
    }

    /** Installed plugins suitable for manual scanning. */
    public function plugin_sources() {
        if ( ! function_exists( 'get_plugins' ) ) { require_once ABSPATH . 'wp-admin/includes/plugin.php'; }
        $plugins = get_plugins();
        $out = array();
        foreach ( $plugins as $key => $data ) {
            $out[ $key ] = array(
                'key' => $key,
                'label' => $data['Name'] ?: $key,
                'version' => $data['Version'] ?? '',
                'active' => is_plugin_active( $key ),
            );
        }
        uasort( $out, function( $a, $b ) {
            if ( $a['active'] !== $b['active'] ) { return $a['active'] ? -1 : 1; }
            return strcasecmp( $a['label'], $b['label'] );
        } );
        return $out;
    }

    public function theme_sources() {
        $themes = wp_get_themes();
        $active_stylesheet = get_stylesheet();
        $active_template = get_template();
        $out = array();
        foreach ( $themes as $key => $theme ) {
            $out[ $key ] = array(
                'key' => $key,
                'label' => $theme->get( 'Name' ) ?: $key,
                'version' => $theme->get( 'Version' ),
                'active' => ( $key === $active_stylesheet || $key === $active_template ),
            );
        }
        uasort( $out, function( $a, $b ) {
            if ( $a['active'] !== $b['active'] ) { return $a['active'] ? -1 : 1; }
            return strcasecmp( $a['label'], $b['label'] );
        } );
        return $out;
    }

    public function source_path( $type, $key ) {
        $type = sanitize_key( $type );
        if ( 'plugin' === $type ) {
            $plugins = $this->plugin_sources();
            if ( empty( $plugins[ $key ] ) ) { return ''; }
            $full = WP_PLUGIN_DIR . '/' . $key;
            $dir = dirname( $full );
            return '.' === dirname( $key ) ? $full : $dir;
        }
        if ( 'theme' === $type ) {
            $themes = wp_get_themes();
            if ( empty( $themes[ $key ] ) ) { return ''; }
            return $themes[ $key ]->get_stylesheet_directory();
        }
        return '';
    }

    /**
     * Start a resumable scanner job. Heavy themes such as WoodMart must never be scanned
     * in one admin-post request because managed hosting proxies commonly terminate long PHP requests.
     */
    public function start_scan_job( $type, $key, $user_id = 0 ) {
        global $wpdb;
        $type = sanitize_key( $type );
        $key  = sanitize_text_field( $key );
        if ( ! in_array( $type, array( 'plugin', 'theme' ), true ) ) {
            return new WP_Error( 'invalid_source_type', 'Ungültiger Quellentyp.' );
        }
        $root = $this->source_path( $type, $key );
        if ( ! $root || ! file_exists( $root ) ) {
            return new WP_Error( 'source_missing', 'Quelle wurde nicht gefunden.' );
        }
        $root = realpath( $root );
        if ( ! $root ) { return new WP_Error( 'source_missing', 'Quelle wurde nicht gefunden.' ); }

        // Sources are rebuilt for this source. Saved translations stay untouched because they live
        // in a separate table and are linked by the stable gettext string hash.
        $wpdb->delete( $this->table( 'string_sources' ), array( 'source_type'=>$type, 'source_key'=>$key ), array( '%s','%s' ) );

        $job_id = wp_generate_uuid4();
        $job = array(
            'id' => $job_id,
            'user_id' => absint( $user_id ),
            'type' => $type,
            'key' => $key,
            'root' => $root,
            'dirs' => is_dir( $root ) ? array( $root ) : array(),
            'files' => is_file( $root ) && preg_match( '/\.php$/i', $root ) ? array( $root ) : array(),
            'stats' => array( 'files'=>0, 'strings'=>0, 'new'=>0, 'skipped'=>0, 'dirs'=>0, 'truncated'=>false ),
            'created' => time(),
        );
        set_transient( 'itkt_scan_' . $job_id, $job, HOUR_IN_SECONDS );
        return $job;
    }

    /** Process a small scanner slice and persist the queue for the next AJAX request. */
    public function process_scan_job( $job_id, $user_id = 0, $max_files = 18, $max_seconds = 1.75 ) {
        $job_id = sanitize_text_field( $job_id );
        $job = get_transient( 'itkt_scan_' . $job_id );
        if ( ! is_array( $job ) ) { return new WP_Error( 'scan_job_missing', 'Scan-Auftrag ist abgelaufen oder wurde nicht gefunden.' ); }
        if ( ! empty( $job['user_id'] ) && absint( $job['user_id'] ) !== absint( $user_id ) ) {
            return new WP_Error( 'scan_job_owner', 'Dieser Scan gehört zu einem anderen Benutzer.' );
        }

        $start = microtime( true );
        $processed_this_request = 0;
        $skip_dirs = array( 'node_modules', '.git', '.svn', 'vendor', 'vendor-bin', 'tests', 'test', 'cache', 'tmp', 'logs' );
        $root_real = realpath( $job['root'] );
        if ( ! $root_real ) { delete_transient( 'itkt_scan_' . $job_id ); return new WP_Error( 'source_missing', 'Quelle wurde während des Scans entfernt.' ); }

        while ( $processed_this_request < max( 1, absint( $max_files ) ) && ( microtime( true ) - $start ) < (float) $max_seconds ) {
            // Discover directories lazily instead of building a 2,500-file list up front.
            if ( empty( $job['files'] ) && ! empty( $job['dirs'] ) ) {
                $dir = array_shift( $job['dirs'] );
                $job['stats']['dirs']++;
                $items = @scandir( $dir );
                if ( false === $items ) { $job['stats']['skipped']++; continue; }
                foreach ( $items as $name ) {
                    if ( '.' === $name || '..' === $name ) { continue; }
                    $path = $dir . DIRECTORY_SEPARATOR . $name;
                    if ( is_link( $path ) ) { continue; }
                    $real = realpath( $path );
                    if ( ! $real || 0 !== strpos( $real, $root_real ) ) { continue; }
                    if ( is_dir( $real ) ) {
                        if ( in_array( basename( $real ), $skip_dirs, true ) ) { continue; }
                        $job['dirs'][] = $real;
                    } elseif ( is_file( $real ) && preg_match( '/\.php$/i', $real ) ) {
                        $job['files'][] = $real;
                    }
                }
                // Directory-only iterations are cheap, but still yield periodically on enormous trees.
                if ( ( microtime( true ) - $start ) >= (float) $max_seconds ) { break; }
                continue;
            }

            if ( empty( $job['files'] ) ) { break; }
            if ( $job['stats']['files'] >= 25000 || $job['stats']['strings'] >= 100000 ) {
                $job['stats']['truncated'] = true;
                $job['dirs'] = array(); $job['files'] = array();
                break;
            }

            $file = array_shift( $job['files'] );
            $processed_this_request++;
            $job['stats']['files']++;
            if ( @filesize( $file ) > 2500000 ) { $job['stats']['skipped']++; continue; }
            $contents = @file_get_contents( $file );
            if ( false === $contents ) { $job['stats']['skipped']++; continue; }
            $found = $this->extract_php_gettext( $contents );
            $relative = $this->relative_file( $job['root'], $file );
            foreach ( $found as $entry ) {
                $id_and_new = $this->upsert_string( $entry );
                if ( ! $id_and_new[0] ) { continue; }
                $this->upsert_source( $id_and_new[0], $job['type'], $job['key'], $relative, absint( $entry['line'] ?? 0 ) );
                $job['stats']['strings']++;
                if ( $id_and_new[1] ) { $job['stats']['new']++; }
            }
        }

        $done = empty( $job['dirs'] ) && empty( $job['files'] );
        $job['pending_dirs']  = count( (array) $job['dirs'] );
        $job['pending_files'] = count( (array) $job['files'] );
        if ( $done ) {
            delete_transient( 'itkt_scan_' . $job_id );
        } else {
            set_transient( 'itkt_scan_' . $job_id, $job, HOUR_IN_SECONDS );
        }
        return array(
            'done' => $done,
            'job_id' => $job_id,
            'source_type' => $job['type'],
            'source_key' => $job['key'],
            'stats' => $job['stats'],
            'pending_dirs' => $job['pending_dirs'],
            'pending_files' => $job['pending_files'],
        );
    }

    /**
     * Scan a plugin/theme for literal PHP gettext calls. Source files are read only.
     * Kept as a compatibility wrapper; the admin UI uses resumable AJAX batches.
     */
    public function scan_source( $type, $key ) {
        global $wpdb;
        $type = sanitize_key( $type );
        $key = sanitize_text_field( $key );
        $root = $this->source_path( $type, $key );
        if ( ! $root || ! file_exists( $root ) ) { return new WP_Error( 'source_missing', 'Quelle wurde nicht gefunden.' ); }

        $source_table = $this->table( 'string_sources' );
        $wpdb->delete( $source_table, array( 'source_type'=>$type, 'source_key'=>$key ), array( '%s','%s' ) );

        $files = $this->php_files( $root );
        $stats = array( 'files'=>0, 'strings'=>0, 'new'=>0, 'skipped'=>0, 'truncated'=>false );
        foreach ( $files as $file ) {
            if ( $stats['files'] >= 2500 || $stats['strings'] >= 50000 ) { $stats['truncated'] = true; break; }
            $stats['files']++;
            if ( @filesize( $file ) > 2500000 ) { $stats['skipped']++; continue; }
            $contents = @file_get_contents( $file );
            if ( false === $contents ) { $stats['skipped']++; continue; }
            $found = $this->extract_php_gettext( $contents );
            $relative = $this->relative_file( $root, $file );
            foreach ( $found as $entry ) {
                $id_and_new = $this->upsert_string( $entry );
                if ( ! $id_and_new[0] ) { continue; }
                $this->upsert_source( $id_and_new[0], $type, $key, $relative, absint( $entry['line'] ?? 0 ) );
                $stats['strings']++;
                if ( $id_and_new[1] ) { $stats['new']++; }
            }
        }
        return $stats;
    }

    private function php_files( $root ) {
        if ( is_file( $root ) ) { return preg_match( '/\.php$/i', $root ) ? array( $root ) : array(); }
        $root_real = realpath( $root );
        if ( ! $root_real ) { return array(); }
        $out = array();
        $skip_dirs = array( 'node_modules', '.git', '.svn', 'vendor', 'vendor-bin', 'tests', 'test', 'cache' );
        try {
            $iterator = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root_real, FilesystemIterator::SKIP_DOTS ) );
            foreach ( $iterator as $info ) {
                if ( $info->isLink() || ! $info->isFile() ) { continue; }
                $path = $info->getPathname();
                $real = realpath( $path );
                if ( ! $real || 0 !== strpos( $real, $root_real ) ) { continue; }
                $parts = preg_split( '#[\\\\/]#', substr( $real, strlen( $root_real ) ) );
                if ( array_intersect( $skip_dirs, $parts ) ) { continue; }
                if ( 'php' !== strtolower( $info->getExtension() ) ) { continue; }
                $out[] = $real;
                if ( count( $out ) >= 2500 ) { break; }
            }
        } catch ( Exception $e ) { return $out; }
        return $out;
    }

    private function relative_file( $root, $file ) {
        if ( is_file( $root ) ) { return basename( $file ); }
        $root_real = realpath( $root );
        $file_real = realpath( $file );
        if ( ! $root_real || ! $file_real ) { return basename( $file ); }
        return ltrim( str_replace( '\\', '/', substr( $file_real, strlen( $root_real ) ) ), '/' );
    }

    private function extract_php_gettext( $code ) {
        $tokens = token_get_all( $code );
        $functions = array(
            '__' => array( 0, null, 1 ),
            '_e' => array( 0, null, 1 ),
            'esc_html__' => array( 0, null, 1 ),
            'esc_html_e' => array( 0, null, 1 ),
            'esc_attr__' => array( 0, null, 1 ),
            'esc_attr_e' => array( 0, null, 1 ),
            '_x' => array( 0, 1, 2 ),
            '_ex' => array( 0, 1, 2 ),
            'esc_html_x' => array( 0, 1, 2 ),
            'esc_attr_x' => array( 0, 1, 2 ),
        );
        $out = array();
        $count = count( $tokens );
        for ( $i=0; $i<$count; $i++ ) {
            $token = $tokens[$i];
            if ( ! is_array( $token ) || T_STRING !== $token[0] ) { continue; }
            $fn = strtolower( $token[1] );
            if ( ! isset( $functions[$fn] ) ) { continue; }
            $line = absint( $token[2] );
            $j = $i + 1;
            while ( $j<$count && is_array($tokens[$j]) && in_array($tokens[$j][0], array(T_WHITESPACE,T_COMMENT,T_DOC_COMMENT), true) ) { $j++; }
            if ( $j >= $count || '(' !== $tokens[$j] ) { continue; }
            list( $args, $end ) = $this->parse_call_args( $tokens, $j );
            if ( null === $args ) { continue; }
            $map = $functions[$fn];
            $original = $this->literal_arg( $args[ $map[0] ] ?? array() );
            if ( null === $original || '' === $original ) { continue; }
            $context = null === $map[1] ? '' : $this->literal_arg( $args[ $map[1] ] ?? array() );
            if ( null === $context ) { $context = ''; }
            $domain = $this->literal_arg( $args[ $map[2] ] ?? array() );
            if ( null === $domain || '' === $domain ) { $domain = 'default'; }
            $out[] = array( 'original'=>$original, 'plural'=>'', 'context'=>$context, 'domain'=>$domain, 'call_type'=>$fn, 'line'=>$line );
            $i = max( $i, $end );
        }
        return $out;
    }

    private function parse_call_args( $tokens, $open_index ) {
        $args = array(); $current = array(); $depth = 0; $count = count( $tokens );
        for ( $i=$open_index; $i<$count; $i++ ) {
            $t = $tokens[$i];
            $char = is_string($t) ? $t : null;
            if ( '(' === $char ) { $depth++; if ( $depth>1 ) $current[]=$t; continue; }
            if ( ')' === $char ) {
                $depth--;
                if ( 0 === $depth ) { $args[]=$current; return array($args,$i); }
                $current[]=$t; continue;
            }
            if ( ',' === $char && 1 === $depth ) { $args[]=$current; $current=array(); continue; }
            if ( $depth>=1 ) { $current[]=$t; }
        }
        return array( null, $open_index );
    }

    private function literal_arg( $tokens ) {
        $meaningful = array();
        foreach ( (array) $tokens as $t ) {
            if ( is_array($t) && in_array($t[0], array(T_WHITESPACE,T_COMMENT,T_DOC_COMMENT), true) ) { continue; }
            $meaningful[] = $t;
        }
        if ( 1 !== count($meaningful) || ! is_array($meaningful[0]) || T_CONSTANT_ENCAPSED_STRING !== $meaningful[0][0] ) { return null; }
        $raw = $meaningful[0][1];
        if ( strlen($raw)<2 ) { return ''; }
        $quote = $raw[0];
        $body = substr($raw,1,-1);
        if ( "'" === $quote ) { return str_replace( array("\\\\","\\'"), array("\\","'"), $body ); }
        return stripcslashes( $body );
    }

    private function upsert_string( $entry ) {
        global $wpdb;
        $table = $this->table( 'strings' );
        $domain = sanitize_text_field( $entry['domain'] ?: 'default' );
        $context = sanitize_text_field( $entry['context'] ?? '' );
        $original = (string) $entry['original'];
        $plural = (string) ( $entry['plural'] ?? '' );
        $hash = $this->make_hash( $domain, $context, $original, $plural );
        $id = absint( $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE string_hash=%s", $hash ) ) );
        if ( $id ) {
            $wpdb->update( $table, array( 'last_seen'=>current_time('mysql'), 'call_type'=>sanitize_key($entry['call_type']??'__') ), array('id'=>$id), array('%s','%s'), array('%d') );
            return array($id,false);
        }
        $wpdb->insert( $table, array(
            'domain'=>$domain, 'context'=>$context, 'original'=>$original, 'plural'=>$plural,
            'string_hash'=>$hash, 'call_type'=>sanitize_key($entry['call_type']??'__'), 'last_seen'=>current_time('mysql')
        ), array('%s','%s','%s','%s','%s','%s','%s') );
        return array(absint($wpdb->insert_id),true);
    }

    private function upsert_source( $string_id, $type, $key, $file, $line ) {
        global $wpdb;
        $table = $this->table( 'string_sources' );
        $hash = hash( 'sha256', absint($string_id) . "\0" . $type . "\0" . $key . "\0" . $file );
        $wpdb->replace( $table, array(
            'string_id'=>absint($string_id), 'source_type'=>$type, 'source_key'=>$key,
            'file'=>sanitize_text_field($file), 'line'=>absint($line), 'source_hash'=>$hash
        ), array('%d','%s','%s','%s','%d','%s') );
    }

    public function counts( $target_languages = array() ) {
        global $wpdb;
        $strings = $this->table('strings');
        $translations = $this->table('string_translations');
        $total = absint( $wpdb->get_var("SELECT COUNT(*) FROM {$strings}") );
        $targets = array_values(array_filter(array_map('sanitize_key',(array)$target_languages)));
        if ( !$targets ) { return array('total'=>$total,'translated'=>0,'missing'=>$total); }
        $lang_sql = implode(',',array_fill(0,count($targets),'%s'));
        $sql = "SELECT COUNT(*) FROM (SELECT s.id, COUNT(DISTINCT CASE WHEN t.translation<>'' THEN t.language END) done FROM {$strings} s LEFT JOIN {$translations} t ON t.string_id=s.id AND t.language IN ({$lang_sql}) GROUP BY s.id HAVING done=%d) x";
        $args = array_merge($targets,array(count($targets)));
        $translated = absint($wpdb->get_var($wpdb->prepare($sql,...$args)));
        return array('total'=>$total,'translated'=>$translated,'missing'=>max(0,$total-$translated));
    }

    public function query_strings( $args = array() ) {
        global $wpdb;
        $strings = $this->table('strings');
        $sources = $this->table('string_sources');
        $translations = $this->table('string_translations');
        $defaults = array('page'=>1,'per_page'=>50,'search'=>'','source_type'=>'','source_key'=>'','status'=>'all','targets'=>array());
        $args = wp_parse_args($args,$defaults);
        $where=array('1=1'); $where_prepare=array(); $join_prepare=array();
        $joins=" LEFT JOIN {$sources} src ON src.string_id=s.id ";
        if($args['source_type']){$where[]='src.source_type=%s';$where_prepare[]=sanitize_key($args['source_type']);}
        if($args['source_key']){$where[]='src.source_key=%s';$where_prepare[]=sanitize_text_field($args['source_key']);}
        if($args['search']){
            $like='%'.$wpdb->esc_like($args['search']).'%';
            $where[]='(s.original LIKE %s OR s.domain LIKE %s OR s.context LIKE %s OR src.file LIKE %s)';
            array_push($where_prepare,$like,$like,$like,$like);
        }
        $targets=array_values(array_filter(array_map('sanitize_key',(array)$args['targets'])));
        $count_expr='0';
        if($targets){
            $lang_sql=implode(',',array_fill(0,count($targets),'%s'));
            $joins.=" LEFT JOIN {$translations} tr ON tr.string_id=s.id AND tr.language IN ({$lang_sql}) ";
            $join_prepare=$targets;
            $count_expr="COUNT(DISTINCT CASE WHEN tr.translation<>'' THEN tr.language END)";
        }
        $prepare=array_merge($join_prepare,$where_prepare);
        $having='';
        if($targets && 'missing'===$args['status']){$having=' HAVING translated_count < '.count($targets);}
        elseif($targets && 'translated'===$args['status']){$having=' HAVING translated_count = '.count($targets);}
        $base=" FROM {$strings} s {$joins} WHERE ".implode(' AND ',$where)." GROUP BY s.id {$having}";
        $select="SELECT s.*, {$count_expr} AS translated_count, MIN(src.source_type) source_type, MIN(src.source_key) source_key, MIN(src.file) source_file, MIN(src.line) source_line".$base;
        $count="SELECT COUNT(*) FROM (SELECT s.id, {$count_expr} AS translated_count".$base.") q";
        $offset=(max(1,absint($args['page']))-1)*max(1,absint($args['per_page']));
        $select.=$wpdb->prepare(' ORDER BY s.id DESC LIMIT %d OFFSET %d',absint($args['per_page']),$offset);
        if($prepare){$rows=$wpdb->get_results($wpdb->prepare($select,...$prepare));$total=absint($wpdb->get_var($wpdb->prepare($count,...$prepare)));}
        else{$rows=$wpdb->get_results($select);$total=absint($wpdb->get_var($count));}
        return array('rows'=>$rows,'total'=>$total,'pages'=>max(1,(int)ceil($total/max(1,absint($args['per_page'])))));
    }
}
