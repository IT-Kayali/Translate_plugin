<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Read-only access to WordPress/plugin language catalogs without switching the global locale.
 *
 * Language selection and RTL/layout direction are deliberately independent. Native catalogs
 * provide the default translation; ITKT string overrides can sit on top of the canonical msgid.
 */
class ITKT_Native_Translations {
    private static $instance = null;
    private $catalog_cache = array();
    private $reverse_cache = array();
    private $loaded_reverse_cache = array();

    public static function instance() {
        if ( null === self::$instance ) { self::$instance = new self(); }
        return self::$instance;
    }

    private function __construct() {}

    public function locale_for_code( $code ) {
        $code = sanitize_key( (string) $code );
        $all = ITKT_Languages::instance()->get_all();
        if ( ! empty( $all[ $code ]['locale'] ) ) { return (string) $all[ $code ]['locale']; }
        $catalog = ITKT_Languages::instance()->catalog();
        return (string) ( $catalog[ $code ]['locale'] ?? $code );
    }

    private function locale_candidates( $locale ) {
        $locale = preg_replace( '/[^A-Za-z0-9_@-]/', '', (string) $locale );
        $out = array();
        if ( $locale ) { $out[] = $locale; }
        if ( false !== strpos( $locale, '_' ) ) {
            $short = strtok( $locale, '_' );
            if ( $short && ! in_array( $short, $out, true ) ) { $out[] = $short; }
        }
        return $out;
    }

    /** Candidate catalog files in the same order WordPress normally prefers them. */
    private function catalog_paths( $domain, $locale ) {
        $domain = sanitize_key( (string) $domain ) ?: 'default';
        $paths = array();
        global $wp_textdomain_registry;

        foreach ( $this->locale_candidates( $locale ) as $loc ) {
            if ( 'default' === $domain ) {
                $base = trailingslashit( WP_LANG_DIR ) . $loc;
                $paths[] = $base . '.l10n.php';
                $paths[] = $base . '.mo';
                continue;
            }

            // Ask WordPress's public registry first. This also honors custom plugin/theme paths.
            if ( is_object( $wp_textdomain_registry ) && method_exists( $wp_textdomain_registry, 'get' ) ) {
                $registered = $wp_textdomain_registry->get( $domain, $loc );
                if ( $registered ) {
                    $base = trailingslashit( $registered ) . $domain . '-' . $loc;
                    $paths[] = $base . '.l10n.php';
                    $paths[] = $base . '.mo';
                }
            }

            foreach ( array( 'plugins', 'themes' ) as $bucket ) {
                $base = trailingslashit( WP_LANG_DIR ) . $bucket . '/' . $domain . '-' . $loc;
                $paths[] = $base . '.l10n.php';
                $paths[] = $base . '.mo';
            }

            // Conservative bundled-language fallbacks for plugins/themes that ship translations.
            if ( defined( 'WP_PLUGIN_DIR' ) ) {
                foreach ( array(
                    WP_PLUGIN_DIR . '/' . $domain . '/languages/' . $domain . '-' . $loc,
                    WP_PLUGIN_DIR . '/' . $domain . '/languages/' . $loc,
                ) as $base ) {
                    $paths[] = $base . '.l10n.php';
                    $paths[] = $base . '.mo';
                }
            }
            if ( function_exists( 'get_stylesheet_directory' ) && function_exists( 'get_template_directory' ) ) {
                foreach ( array_unique( array( get_stylesheet_directory(), get_template_directory() ) ) as $theme_dir ) {
                    foreach ( array(
                        trailingslashit( $theme_dir ) . 'languages/' . $domain . '-' . $loc,
                        trailingslashit( $theme_dir ) . 'languages/' . $loc,
                    ) as $base ) {
                        $paths[] = $base . '.l10n.php';
                        $paths[] = $base . '.mo';
                    }
                }
            }
        }
        return array_values( array_unique( array_filter( $paths ) ) );
    }

    /** @return array{entries:array,file:mixed,mo:mixed,path:string} */
    private function load_catalog( $domain, $locale ) {
        $domain = sanitize_key( (string) $domain ) ?: 'default';
        $key = $domain . '|' . (string) $locale;
        if ( isset( $this->catalog_cache[ $key ] ) ) { return $this->catalog_cache[ $key ]; }

        $catalog = array( 'entries'=>array(), 'file'=>null, 'mo'=>null, 'path'=>'' );
        foreach ( $this->catalog_paths( $domain, $locale ) as $path ) {
            if ( ! is_readable( $path ) ) { continue; }

            // WordPress 6.5+ parser handles both .l10n.php and .mo consistently.
            if ( class_exists( 'WP_Translation_File' ) && method_exists( 'WP_Translation_File', 'create' ) ) {
                $file = WP_Translation_File::create( $path );
                if ( $file && method_exists( $file, 'entries' ) ) {
                    $entries = $file->entries();
                    if ( is_array( $entries ) && $entries ) {
                        $catalog['entries'] = $entries;
                        $catalog['file'] = $file;
                        $catalog['path'] = $path;
                        break;
                    }
                }
            }

            // WordPress 6.4 fallback for PHP translation catalogs, if present.
            if ( str_ends_with( $path, '.l10n.php' ) ) {
                $data = include $path;
                if ( is_array( $data ) && ! empty( $data['messages'] ) && is_array( $data['messages'] ) ) {
                    $catalog['entries'] = $data['messages'];
                    $catalog['path'] = $path;
                    break;
                }
            }

            // WordPress 6.4 fallback for MO catalogs.
            if ( str_ends_with( $path, '.mo' ) ) {
                if ( ! class_exists( 'MO' ) ) { require_once ABSPATH . WPINC . '/pomo/mo.php'; }
                $mo = new MO();
                if ( $mo->import_from_file( $path ) ) {
                    $catalog['mo'] = $mo;
                    $catalog['path'] = $path;
                    break;
                }
            }
        }
        $this->catalog_cache[ $key ] = $catalog;
        return $catalog;
    }

    public function has_catalog( $domain, $language ) {
        $cat = $this->load_catalog( $domain, $this->locale_for_code( $language ) );
        return ! empty( $cat['path'] );
    }

    private function message_key( $text, $context = '' ) {
        return '' !== (string) $context ? (string) $context . "\4" . (string) $text : (string) $text;
    }

    private function first_translation_form( $value ) {
        if ( is_array( $value ) ) { $value = reset( $value ); }
        if ( ! is_string( $value ) ) { return ''; }
        if ( false !== strpos( $value, "\0" ) ) { $value = explode( "\0", $value, 2 )[0]; }
        return (string) $value;
    }

    public function translate( $text, $domain, $language, $context = '' ) {
        $text = (string) $text;
        if ( '' === $text ) { return null; }
        $language = sanitize_key( (string) $language );
        if ( 'en' === $language ) { return $text; }
        $cat = $this->load_catalog( $domain, $this->locale_for_code( $language ) );
        $key = $this->message_key( $text, $context );

        if ( ! empty( $cat['entries'] ) && array_key_exists( $key, $cat['entries'] ) ) {
            $value = $this->first_translation_form( $cat['entries'][ $key ] );
            if ( '' !== $value ) { return $value; }
        }
        if ( $cat['mo'] instanceof MO ) {
            $translated = $cat['mo']->translate( $text, (string) $context );
            if ( is_string( $translated ) && '' !== $translated && $translated !== $text ) { return $translated; }
        }
        return null;
    }

    public function translate_plural( $single, $plural, $number, $domain, $language, $context = '' ) {
        $language = sanitize_key( (string) $language );
        if ( 'en' === $language ) { return 1 === (int) $number ? (string) $single : (string) $plural; }
        $cat = $this->load_catalog( $domain, $this->locale_for_code( $language ) );
        $raw_key = $this->message_key( (string) $single, (string) $context ) . "\0" . (string) $plural;

        if ( ! empty( $cat['entries'] ) && array_key_exists( $raw_key, $cat['entries'] ) ) {
            $raw = $cat['entries'][ $raw_key ];
            if ( is_array( $raw ) ) { $forms = array_values( $raw ); }
            else { $forms = explode( "\0", (string) $raw ); }
            $index = 1 === (int) $number ? 0 : 1;
            if ( $cat['file'] && method_exists( $cat['file'], 'get_plural_form' ) ) {
                $index = absint( $cat['file']->get_plural_form( (int) $number ) );
            }
            if ( isset( $forms[ $index ] ) && '' !== (string) $forms[ $index ] ) { return (string) $forms[ $index ]; }
            if ( isset( $forms[0] ) && '' !== (string) $forms[0] ) { return (string) $forms[0]; }
        }
        if ( $cat['mo'] instanceof MO && method_exists( $cat['mo'], 'translate_plural' ) ) {
            $translated = $cat['mo']->translate_plural( (string) $single, (string) $plural, (int) $number, (string) $context );
            if ( is_string( $translated ) && '' !== $translated ) { return $translated; }
        }
        return null;
    }

    /** Convert printf placeholders to stable {{n}} tokens used by the live editor. */
    public function templateize( $text ) {
        $text = (string) $text;
        $seq = 0;
        return preg_replace_callback(
            '/%(?:(\d+)\$)?[+\-0\' #]*\d*(?:\.\d+)?[bcdeEfFgGosuxX]/',
            function( $m ) use ( &$seq ) {
                $n = ! empty( $m[1] ) ? absint( $m[1] ) : ++$seq;
                if ( ! $n ) { $n = ++$seq; }
                return '{{' . $n . '}}';
            },
            $text
        );
    }

    private function normalize_visible( $text ) {
        $text = html_entity_decode( wp_strip_all_tags( (string) $text, true ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
        $text = $this->templateize( $text );
        $text = preg_replace( '/[\x{061C}\x{200E}\x{200F}\x{202A}-\x{202E}\x{2066}-\x{2069}]/u', '', $text );
        $text = preg_replace( '/\s+/u', ' ', trim( (string) $text ) );
        return (string) $text;
    }

    private function parse_catalog_source_key( $raw_key ) {
        $raw_key = (string) $raw_key;
        $context = '';
        if ( false !== strpos( $raw_key, "\4" ) ) {
            list( $context, $raw_key ) = explode( "\4", $raw_key, 2 );
        }
        $plural = '';
        if ( false !== strpos( $raw_key, "\0" ) ) {
            list( $raw_key, $plural ) = explode( "\0", $raw_key, 2 );
        }
        return array( 'source'=>(string)$raw_key, 'context'=>(string)$context, 'plural'=>(string)$plural );
    }

    private function translation_forms( $translation ) {
        if ( is_array( $translation ) ) {
            $forms = array();
            foreach ( $translation as $item ) {
                foreach ( explode( "\0", (string) $item ) as $form ) { $forms[] = $form; }
            }
            return $forms;
        }
        return explode( "\0", (string) $translation );
    }

    /** Compile an editor template such as "Einschließlich {{1}}" to a safe full-string regex. */
    private function template_regex( $template ) {
        $template = $this->normalize_visible( $template );
        if ( false === strpos( $template, '{{' ) ) { return ''; }
        $parts = preg_split( '/(\{\{\d+\}\})/', $template, -1, PREG_SPLIT_DELIM_CAPTURE );
        if ( ! is_array( $parts ) ) { return ''; }
        $static = preg_replace( '/\{\{\d+\}\}/', '', $template );
        if ( function_exists( 'mb_strlen' ) ? mb_strlen( trim( $static ), 'UTF-8' ) < 2 : strlen( trim( $static ) ) < 2 ) { return ''; }
        $regex = '^';
        foreach ( $parts as $part ) {
            if ( preg_match( '/^\{\{\d+\}\}$/', $part ) ) { $regex .= '(.+?)'; }
            else { $regex .= preg_quote( $part, '~' ); }
        }
        return '~' . $regex . '$~u';
    }

    private function pattern_matches( $template, $actual ) {
        $regex = $this->template_regex( $template );
        if ( ! $regex ) { return false; }
        return 1 === preg_match( $regex, $this->normalize_visible( $actual ) );
    }

    /**
     * Canonical source registry for stable WooCommerce Blocks components.
     *
     * These mappings are not visual translations. They identify the actual JavaScript gettext
     * msgid used by WooCommerce so ITKT overrides can hook into wp.i18n before React renders.
     */
    private function resolve_known_frontend_target( $scope, $locator ) {
        $scope = sanitize_key( (string) $scope );
        $locator = (string) $locator;
        if ( ! in_array( $scope, array( 'woocommerce', 'cart', 'checkout' ), true ) ) { return null; }

        // WooCommerce Blocks checkout terms are rendered as one HTML sentence with the
        // Terms and Privacy links injected through %1$s / %2$s. Treat the complete sentence as
        // one canonical JS gettext string so RTL languages may reorder the protected links.
        if ( false !== strpos( $locator, 'wc-block-checkout__terms' ) && false !== strpos( $locator, 'wc-block-components-checkbox__label' ) ) {
            if ( false !== strpos( $locator, 'terms-and-conditions' ) ) {
                return array(
                    'domain'=>'woocommerce',
                    'source'=>'You must accept our %1$s and %2$s to continue with your purchase.',
                    'context'=>'', 'plural'=>'', 'runtime'=>'js'
                );
            }
            return array(
                'domain'=>'woocommerce',
                'source'=>'By proceeding with your purchase you agree to our %1$s and %2$s',
                'context'=>'', 'plural'=>'', 'runtime'=>'js'
            );
        }

        $known = array(
            // WooCommerce Blocks: TotalsFooterItem description.
            'wc-block-components-totals-footer-item-tax' => array(
                'domain' => 'woocommerce',
                'source' => 'Including %s',
                'context' => '',
                'plural' => '',
                'runtime' => 'js',
            ),
        );

        // WoodMart renders its shared Cart -> Checkout -> Complete progress in the page-title
        // area, outside the normal WooCommerce wrapper. Identify the canonical theme gettext
        // strings by the stable step classes so the live editor can use the WoodMart language
        // catalog rather than storing the already translated German wording as a random string.
        if ( false !== strpos( $locator, 'wd-checkout-steps' ) || false !== strpos( $locator, 'woodmart-checkout-steps' ) || false !== strpos( $locator, 'checkout-steps' ) ) {
            if ( false !== strpos( $locator, 'step-cart' ) ) {
                return array( 'domain'=>'woodmart', 'source'=>'Shopping cart', 'context'=>'', 'plural'=>'', 'runtime'=>'php' );
            }
            if ( false !== strpos( $locator, 'step-checkout' ) ) {
                return array( 'domain'=>'woodmart', 'source'=>'Checkout', 'context'=>'', 'plural'=>'', 'runtime'=>'php' );
            }
            if ( false !== strpos( $locator, 'step-complete' ) ) {
                return array( 'domain'=>'woodmart', 'source'=>'Order complete', 'context'=>'', 'plural'=>'', 'runtime'=>'php' );
            }
        }
        foreach ( $known as $class_fragment => $match ) {
            if ( false !== strpos( $locator, $class_fragment ) ) { return $match; }
        }
        return null;
    }

    /** Public list of canonical JS gettext strings needed before WooCommerce Blocks render. */
    public function known_js_sources() {
        return array(
            array( 'domain'=>'woocommerce', 'source'=>'Including %s', 'context'=>'', 'plural'=>'' ),
            // Checkout Block legal/consent text. The two links are inserted by WooCommerce via
            // sprintf, therefore the sentence and the link labels must all run through the JS
            // i18n adapter rather than being replaced as flattened DOM text.
            array( 'domain'=>'woocommerce', 'source'=>'By proceeding with your purchase you agree to our %1$s and %2$s', 'context'=>'', 'plural'=>'' ),
            array( 'domain'=>'woocommerce', 'source'=>'You must accept our %1$s and %2$s to continue with your purchase.', 'context'=>'', 'plural'=>'' ),
            array( 'domain'=>'woocommerce', 'source'=>'Terms and Conditions', 'context'=>'', 'plural'=>'' ),
            array( 'domain'=>'woocommerce', 'source'=>'Privacy Policy', 'context'=>'', 'plural'=>'' ),
            array( 'domain'=>'woocommerce', 'source'=>'Please read and accept the terms and conditions.', 'context'=>'', 'plural'=>'' ),
        );
    }

    /** Canonical PHP/theme strings that ITKT can safely translate in the rendered storefront DOM. */
    public function known_dom_sources() {
        return array(
            array( 'domain'=>'woodmart', 'source'=>'Shopping cart', 'context'=>'', 'plural'=>'' ),
            array( 'domain'=>'woodmart', 'source'=>'Checkout', 'context'=>'', 'plural'=>'' ),
            array( 'domain'=>'woodmart', 'source'=>'Order complete', 'context'=>'', 'plural'=>'' ),
        );
    }

    /**
     * Resolve a rendered label back to its canonical gettext msgid.
     *
     * $visible may already be templateized by the browser. $actual_visible should contain the
     * untouched rendered label where available (e.g. "Einschließlich 8,64 € MwSt."). The latter
     * allows a native catalog template such as "Einschließlich %s" to match the complete dynamic
     * value before ITKT stores an override under the canonical source key.
     */
    public function resolve_visible( $visible, $scope = 'woocommerce', $mode = 'exact', $actual_visible = '', $locator = '' ) {
        $visible = $this->normalize_visible( $visible );
        $actual_visible = $this->normalize_visible( $actual_visible );
        if ( '' === $visible && '' === $actual_visible ) { return null; }

        // WooCommerce Blocks are rendered by JavaScript and their script translations do not
        // necessarily exist in the PHP gettext catalog loaded for this request. For stable block
        // components use the component's canonical source string instead of guessing backwards
        // from the already translated DOM text.
        $known = $this->resolve_known_frontend_target( $scope, $locator );
        if ( $known ) { return $known; }

        $default = ITKT_Languages::instance()->get_default_code();
        $domains = in_array( $scope, array( 'woocommerce','cart','checkout','account' ), true )
            ? array( 'woocommerce', 'woodmart' )
            : array( 'woodmart', 'woocommerce' );
        $languages = array_unique( array_merge(
            array( $default, ITKT_Languages::instance()->current_code() ),
            array_keys( ITKT_Languages::instance()->get_active() )
        ) );
        $candidates = array_values( array_unique( array_filter( array( $actual_visible, $visible ) ) ) );

        // First use the text domains WordPress has already loaded for the current request.
        // This is the most reliable way to reverse-resolve the visible German shop wording,
        // because it is literally the catalog WooCommerce/WoodMart used to render the page.
        foreach ( $domains as $domain ) {
            $reverse = $this->loaded_reverse_map( $domain );
            $match = $this->match_reverse_map( $reverse, $candidates );
            if ( $match ) { return $match; }
        }

        // Fallback: inspect language files directly for every active storefront language.
        foreach ( $domains as $domain ) {
            foreach ( $languages as $lang ) {
                $reverse = $this->reverse_map( $domain, $lang );
                $match = $this->match_reverse_map( $reverse, $candidates );
                if ( $match ) { return $match; }
            }
        }
        return null;
    }

    private function match_reverse_map( $reverse, $candidates ) {
        foreach ( (array) $candidates as $candidate ) {
            if ( isset( $reverse['exact'][ $candidate ] ) ) { return $reverse['exact'][ $candidate ]; }
        }
        foreach ( (array) ( $reverse['patterns'] ?? array() ) as $row ) {
            foreach ( (array) $candidates as $candidate ) {
                if ( $this->pattern_matches( (string) ( $row['template'] ?? '' ), $candidate ) ) {
                    return $row['match'] ?? null;
                }
            }
        }
        return null;
    }

    /** Build a reverse map from the translations already loaded by WordPress on this request. */
    private function loaded_reverse_map( $domain ) {
        $domain = sanitize_key( (string) $domain ) ?: 'default';
        if ( isset( $this->loaded_reverse_cache[ $domain ] ) ) { return $this->loaded_reverse_cache[ $domain ]; }
        $out = array( 'exact'=>array(), 'patterns'=>array() );
        if ( ! function_exists( 'get_translations_for_domain' ) ) {
            $this->loaded_reverse_cache[ $domain ] = $out;
            return $out;
        }

        $translations = get_translations_for_domain( $domain );
        $entries = is_object( $translations ) ? $translations->entries : array();
        foreach ( (array) $entries as $entry ) {
            if ( ! is_object( $entry ) || empty( $entry->singular ) ) { continue; }
            $forms = ! empty( $entry->translations ) ? (array) $entry->translations : array();
            if ( ! $forms ) { continue; }
            foreach ( $forms as $form ) {
                $this->add_reverse_entry(
                    $out,
                    $domain,
                    (string) $entry->singular,
                    (string) ( $entry->context ?? '' ),
                    (string) ( $entry->plural ?? '' ),
                    (string) $form
                );
            }
        }

        $out['patterns'] = array_values( $out['patterns'] );
        usort( $out['patterns'], function( $a, $b ) {
            return (int) ( $b['specificity'] ?? 0 ) <=> (int) ( $a['specificity'] ?? 0 );
        } );
        $this->loaded_reverse_cache[ $domain ] = $out;
        return $out;
    }

    private function add_reverse_entry( &$out, $domain, $source, $context, $plural, $translation ) {
        $translation = $this->normalize_visible( $translation );
        if ( '' === $translation ) { return; }
        $match = array(
            'domain'  => sanitize_key( $domain ) ?: 'default',
            'source'  => (string) $source,
            'context' => (string) $context,
            'plural'  => (string) $plural,
        );
        if ( false !== strpos( $translation, '{{' ) ) {
            $key = hash( 'sha256', $translation . "\0" . (string) $source . "\0" . (string) $context );
            $static = preg_replace( '/\{\{\d+\}\}/', '', $translation );
            $specificity = function_exists( 'mb_strlen' ) ? mb_strlen( (string) $static, 'UTF-8' ) : strlen( (string) $static );
            $out['patterns'][ $key ] = array( 'template'=>$translation, 'match'=>$match, 'specificity'=>$specificity );
        } elseif ( ! isset( $out['exact'][ $translation ] ) ) {
            $out['exact'][ $translation ] = $match;
        }
    }

    /** @return array{exact:array,patterns:array} */
    private function reverse_map( $domain, $language ) {
        $cache_key = sanitize_key( $domain ) . '|' . sanitize_key( $language );
        if ( isset( $this->reverse_cache[ $cache_key ] ) ) { return $this->reverse_cache[ $cache_key ]; }
        $out = array( 'exact'=>array(), 'patterns'=>array() );
        $cat = $this->load_catalog( $domain, $this->locale_for_code( $language ) );


        if ( $cat['mo'] instanceof MO && ! empty( $cat['mo']->entries ) ) {
            foreach ( $cat['mo']->entries as $entry ) {
                if ( ! is_object( $entry ) || empty( $entry->singular ) ) { continue; }
                $translations = ! empty( $entry->translations ) ? (array) $entry->translations : array( (string) $entry->singular );
                foreach ( $translations as $translation ) {
                    $this->add_reverse_entry( $out, $domain, (string)$entry->singular, (string)($entry->context ?? ''), (string)($entry->plural ?? ''), (string)$translation );
                }
            }
        }

        if ( ! empty( $cat['entries'] ) ) {
            foreach ( $cat['entries'] as $raw_key => $translation ) {
                if ( '' === (string) $raw_key ) { continue; }
                $parsed = $this->parse_catalog_source_key( $raw_key );
                if ( '' === $parsed['source'] ) { continue; }
                foreach ( $this->translation_forms( $translation ) as $form ) {
                    if ( '' !== (string) $form ) {
                        $this->add_reverse_entry( $out, $domain, $parsed['source'], $parsed['context'], $parsed['plural'], (string)$form );
                    }
                }
            }
        }

        $out['patterns'] = array_values( $out['patterns'] );
        usort( $out['patterns'], function( $a, $b ) {
            return (int) ( $b['specificity'] ?? 0 ) <=> (int) ( $a['specificity'] ?? 0 );
        } );
        $this->reverse_cache[ $cache_key ] = $out;
        return $out;
    }

    /** Convert {{n}} editor tokens back to the printf placeholders expected by gettext/sprintf. */
    public function materialize_tokens( $translation, $source ) {
        $translation = (string) $translation;
        if ( false === strpos( $translation, '{{' ) ) { return $translation; }
        preg_match_all( '/%(?:(\d+)\$)?[+\-0\' #]*\d*(?:\.\d+)?[bcdeEfFgGosuxX]/', (string) $source, $matches, PREG_SET_ORDER );
        if ( ! $matches ) { return $translation; }
        $seq = 0; $tokens = array();
        foreach ( $matches as $m ) {
            $n = ! empty( $m[1] ) ? absint( $m[1] ) : ++$seq;
            if ( ! isset( $tokens[ $n ] ) ) { $tokens[ $n ] = $m[0]; }
        }
        return preg_replace_callback( '/\{\{(\d+)\}\}/', function( $m ) use ( $tokens ) {
            $n = absint( $m[1] );
            return $tokens[ $n ] ?? $m[0];
        }, $translation );
    }

    public function editor_value( $text ) {
        return $this->templateize( (string) $text );
    }
}
