<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Explicit admin-only generation, short-lived per-user previews and separate apply action. */
class ITKT_AI_Admin {
    public static function boot() {
        add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
        add_action( 'admin_post_itkt_ai_generate', array( __CLASS__, 'generate' ) );
        add_action( 'admin_post_itkt_ai_apply', array( __CLASS__, 'apply' ) );
    }
    public static function menu() {
        add_submenu_page( 'itkt-dashboard', 'KI-Übersetzung', 'KI-Übersetzung', 'manage_options', 'itkt-ai', array( __CLASS__, 'render' ) );
    }
    private static function preview_key() { return 'itkt_ai_preview_' . get_current_user_id(); }
    private static function authorize( $action ) {
        if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Keine Berechtigung.' ); }
        check_admin_referer( $action );
    }
    private static function back( $code ) {
        wp_safe_redirect( add_query_arg( array( 'page'=>'itkt-ai', 'itkt_ai_notice'=>$code ), admin_url('admin.php') ) );
        exit;
    }
    private static function strings( $ids ) {
        global $wpdb;
        $ids = array_values( array_unique( array_filter( array_map('absint', (array)$ids) ) ) );
        if ( ! $ids || count($ids)>3 ) { return array(); }
        // Plural/visual records need a dedicated format-aware UI and are not silently flattened.
        return (array) $wpdb->get_results( 'SELECT id,original FROM ' . $wpdb->prefix . 'itkt_strings WHERE id IN (' . implode(',',$ids) . ") AND (plural='' OR plural IS NULL) AND call_type<>'visual'", ARRAY_A );
    }
    public static function generate() {
        self::authorize('itkt_ai_generate');
        if ( ! ITKT_Plugin::module_enabled('strings') || empty($_POST['confirm_transfer']) ) { self::back('consent'); }
        $provider = sanitize_key( wp_unslash($_POST['provider'] ?? '') );
        $source = sanitize_key( wp_unslash($_POST['source_language'] ?? '') );
        $target = sanitize_key( wp_unslash($_POST['target_language'] ?? '') );
        $model = sanitize_text_field( wp_unslash($_POST['model'] ?? '') );
        $languages = ITKT_Languages::instance()->get_active();
        if ( ! isset( ITKT_AI_HTTP_Provider::catalog()[$provider], $languages[$source], $languages[$target] ) || $source===$target ) { self::back('settings'); }
        $rows = self::strings( $_POST['string_ids'] ?? array() );
        if ( ! $rows ) { self::back('selection'); }
        // One-use generation ID blocks accidental double-submit without auto-retrying paid calls.
        $request_id = sanitize_key( wp_unslash($_POST['request_id'] ?? '') );
        if ( ! preg_match('/^[a-z0-9-]{20,50}$/',$request_id) ) { self::back('settings'); }
        $lock = '_itkt_ai_request_' . hash('sha256',get_current_user_id().'|'.$request_id);
        if ( ! add_option($lock,time(),'','no') ) { self::back('duplicate'); }
        // Expired request records are removed opportunistically, not by scheduled API calls.
        global $wpdb;
        $like = $wpdb->esc_like('_itkt_ai_request_') . '%';
        $wpdb->query( $wpdb->prepare("DELETE FROM {$wpdb->options} WHERE option_name LIKE %s AND CAST(option_value AS UNSIGNED)<%d",$like,time()-DAY_IN_SECONDS) );
        $catalog = ITKT_Strings::instance(); $batch = array(); $originals = array();
        foreach ($rows as $row) {
            $id = (int)$row['id']; $originals[$id]=(string)$row['original'];
            $batch[]=array('id'=>$id,'text'=>(string)$row['original'],'existing_translation'=>$catalog->get_translation($id,$target));
        }
        $service = new ITKT_AI_Translation_Service();
        $result = $service->generate_suggestions($batch,$source,$target,new ITKT_AI_HTTP_Provider($provider,$model));
        if (is_wp_error($result)) { self::back('failed'); }
        set_transient(self::preview_key(),array('result'=>$result,'originals'=>$originals,'provider'=>$provider,'target'=>$target,'created_at'=>time(),'id'=>wp_generate_uuid4()),15*MINUTE_IN_SECONDS);
        self::back('preview');
    }
    public static function apply() {
        self::authorize('itkt_ai_apply');
        if ( ! ITKT_Plugin::module_enabled('strings') ) { self::back('settings'); }
        if (empty($_POST['confirm_apply'])) { self::back('consent'); }
        $preview=get_transient(self::preview_key());
        if (!is_array($preview) || empty($preview['result']['suggestions']) || !hash_equals($preview['id'],sanitize_text_field(wp_unslash($_POST['preview_id'] ?? '')))) { self::back('expired'); }
        $selected=array_map('absint',(array)($_POST['apply_ids'] ?? array()));
        if (!$selected) { self::back('selection'); }
        $catalog=ITKT_Strings::instance(); $applied=0;
        foreach(self::strings($selected) as $row) {
            $id=(int)$row['id']; $suggestion=$preview['result']['suggestions'][$id] ?? null;
            if (!$suggestion || !hash_equals($suggestion['source_hash'],hash('sha256',(string)$row['original'])) || ''!==trim($catalog->get_translation($id,$preview['target']))) { continue; }
            // Generation already validated placeholders. This atomic insert cannot overwrite a
            // concurrent manual translation and checks the exact source again in the same query.
            if ($catalog->save_ai_translation_if_missing($id,$preview['target'],$suggestion['translation'],(string)$row['original'])) { $applied++; }
        }
        delete_transient(self::preview_key());
        self::back($applied ? 'applied' : 'stale');
    }
    public static function render() {
        if (!current_user_can('manage_options')) { return; }
        if ( ! ITKT_Plugin::module_enabled('strings') ) { echo '<div class="wrap"><h1>KI-Übersetzung</h1><p>Bitte zuerst das Modul Plugin &amp; Theme Texte aktivieren.</p></div>'; return; }
        $languages=ITKT_Languages::instance()->get_active();
        $default=ITKT_Languages::instance()->get_default_code();
        $notice=sanitize_key($_GET['itkt_ai_notice'] ?? '');
        $messages=array('consent'=>'Bitte die jeweilige Bestätigung aktivieren.','settings'=>'Anbieter, Modell und unterschiedliche Sprachen prüfen.','selection'=>'Bitte 1 bis 3 geeignete Texte auswählen.','duplicate'=>'Dieser Auftrag wurde bereits gestartet. Nicht erneut berechnet.','failed'=>'Auftrag konnte nicht erzeugt werden. Schlüssel, Modell und Limits prüfen.','expired'=>'Die Vorschau ist abgelaufen.','applied'=>'Gültige, weiterhin fehlende Übersetzungen wurden übernommen. Übersprungene Einträge bitte prüfen.','stale'=>'Nichts übernommen: Quelle geändert oder Übersetzung inzwischen vorhanden.','preview'=>'Vorschau erstellt. API-Fehler stoppen den Auftrag ohne automatische Wiederholung.');
        global $wpdb;
        $search = sanitize_text_field( wp_unslash($_GET['ai_search'] ?? '') );
        $where = "(plural='' OR plural IS NULL) AND call_type<>'visual'";
        $sql = "SELECT id,original FROM {$wpdb->prefix}itkt_strings WHERE {$where}";
        if ( '' !== $search ) { $sql .= $wpdb->prepare(' AND original LIKE %s','%' . $wpdb->esc_like(substr($search,0,100)) . '%'); }
        $rows=(array)$wpdb->get_results($sql . ' ORDER BY id DESC LIMIT 30',ARRAY_A);
        $preview=get_transient(self::preview_key());
        ?>
        <div class="wrap"><h1>KI-Übersetzung</h1>
        <p>Automatische Übersetzung ausgewählter Katalogtexte über OpenAI oder DeepL. Keine Aufrufe bei Shopbesuchen, keine automatische Veröffentlichung. Vorhandene Übersetzungen bleiben erhalten.</p>
        <?php if(isset($messages[$notice])): ?><div class="notice notice-info"><p><?php echo esc_html($messages[$notice]); ?></p></div><?php endif; ?>
        <h2>1. Anbieter serverseitig einrichten</h2>
        <p>Schlüssel nur in <code>wp-config.php</code> oder als Server-Umgebungsvariable setzen: <code>ITKT_OPENAI_API_KEY</code> und/oder <code>ITKT_DEEPL_API_KEY</code>. Niemals in Git, Frontend oder Chat. Hier wird kein Schlüssel ausgegeben oder in der WordPress-Datenbank gespeichert.</p>
        <ul><?php foreach(ITKT_AI_HTTP_Provider::catalog() as $key=>$label): ?><li><?php echo esc_html($label . ': ' . (ITKT_AI_HTTP_Provider::credential($key) ? 'Schlüssel vorhanden (nicht live geprüft)' : 'nicht eingerichtet')); ?></li><?php endforeach; ?></ul>
        <h2>2. Automatisch übersetzen</h2>
        <p>Maximal 3 Texte pro Auftrag, 4 KB je Text und 12 KB insgesamt. Modell-/Zeichenlimits begrenzen die Anfragegröße, nicht einen Geldbetrag. Kosten und Datenverarbeitung richten sich nach deinem Anbieter. Keine automatische Wiederholung.</p>
        <form method="get"><input type="hidden" name="page" value="itkt-ai"><p><label>Katalog durchsuchen <input name="ai_search" value="<?php echo esc_attr($search); ?>" maxlength="100"></label> <button type="submit" class="button">Suchen</button></p></form>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
        <input type="hidden" name="action" value="itkt_ai_generate"><input type="hidden" name="request_id" value="<?php echo esc_attr(wp_generate_uuid4()); ?>"><?php wp_nonce_field('itkt_ai_generate'); ?>
        <p><label>Anbieter <select name="provider"><?php foreach(ITKT_AI_HTTP_Provider::catalog() as $key=>$label): ?><option value="<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></option><?php endforeach; ?></select></label>
        <label> OpenAI Modell-ID <input name="model" type="text" maxlength="100" placeholder="Aktuell freigegebene Modell-ID"></label></p>
        <p><?php foreach(array('source_language'=>'Quellsprache','target_language'=>'Zielsprache') as $field=>$label): ?><label><?php echo esc_html($label); ?> <select name="<?php echo esc_attr($field); ?>"><?php foreach($languages as $code=>$lang): ?><option value="<?php echo esc_attr($code); ?>" <?php selected('source_language'===$field ? $default===$code : $default!==$code && $code===array_key_first(array_diff_key($languages,array($default=>true)))); ?>><?php echo esc_html($lang['name'].' ('.$code.')'); ?></option><?php endforeach; ?></select></label> <?php endforeach; ?></p>
        <p>Die neuesten 30 passenden nicht-pluralen Katalogtexte. Keine Bestell-/Kundendaten auswählen. Der Katalog kann zuvor erfasste persönliche Inhalte enthalten, daher Auswahl prüfen.</p>
        <table class="widefat striped"><thead><tr><th>Auswahl</th><th>ID</th><th>Quelltext, bitte vor Übertragung prüfen</th></tr></thead><tbody><?php foreach($rows as $row): ?><tr><td><input type="checkbox" name="string_ids[]" value="<?php echo absint($row['id']); ?>" aria-label="<?php echo esc_attr('Text '.$row['id'].' auswählen'); ?>"></td><td><?php echo absint($row['id']); ?></td><td style="white-space:pre-wrap;overflow-wrap:anywhere"><?php echo esc_html($row['original']); ?></td></tr><?php endforeach; ?></tbody></table>
        <p><label><input type="checkbox" name="confirm_transfer" value="1" required> Ich erlaube die Übertragung der ausgewählten Texte an den ausgewählten Anbieter und mögliche API-Kosten dieses Auftrags.</label></p>
        <?php submit_button('Auswahl automatisch übersetzen'); ?></form>
        <?php if(is_array($preview)): $result=$preview['result']; ?>
        <h2>3. Vorschläge prüfen und übernehmen</h2><p>Vorschau 15 Minuten gültig. <?php echo absint($result['requests']); ?> API-Anfragen versucht; <?php echo count($result['skipped']); ?> vorhandene Übersetzungen übersprungen; <?php echo count($result['errors']); ?> Fehler. Anbieter: <?php echo esc_html($preview['provider']); ?>, Ziel: <?php echo esc_html($preview['target']); ?>.</p>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"><input type="hidden" name="action" value="itkt_ai_apply"><input type="hidden" name="preview_id" value="<?php echo esc_attr($preview['id']); ?>"><?php wp_nonce_field('itkt_ai_apply'); ?>
        <table class="widefat striped"><thead><tr><th>Übernehmen</th><th>Quelle</th><th>Vorschlag</th></tr></thead><tbody><?php foreach($result['suggestions'] as $id=>$suggestion): ?><tr><td><input type="checkbox" name="apply_ids[]" value="<?php echo absint($id); ?>" aria-label="<?php echo esc_attr('Vorschlag '.$id.' übernehmen'); ?>"></td><td style="white-space:pre-wrap"><?php echo esc_html($preview['originals'][$id]); ?></td><td style="white-space:pre-wrap"><?php echo esc_html($suggestion['translation']); ?></td></tr><?php endforeach; ?></tbody></table>
        <p><label><input type="checkbox" name="confirm_apply" value="1" required> Ich habe die Vorschläge geprüft und möchte die ausgewählten Übersetzungen veröffentlichen.</label></p><?php submit_button('Geprüfte Auswahl übernehmen'); ?></form>
        <?php endif; ?>
        <p><strong>Grenzen dieser Version:</strong> Katalogtexte, keine Seiten-/Produkt-Bulkübersetzung, keine Plural- oder visuellen Records. API-Livezugang und Shop-Integration müssen auf Staging geprüft werden. Automatische Übersetzung ist keine Qualitätsgarantie.</p>
        </div><?php
    }
}
