<?php
// Local admin-flow fixture and tests. Every external boundary is mocked, no real HTTP/DB writes.
define('ABSPATH',__DIR__); define('ARRAY_A','ARRAY_A'); define('DAY_IN_SECONDS',86400); define('MINUTE_IN_SECONDS',60);
class WP_Error { public $code; public function __construct($code,$message){$this->code=$code;} }
class ITKT_Test_Redirect extends RuntimeException {}
function is_wp_error($v){return $v instanceof WP_Error;}
function wp_json_encode($v,$flags=0){return json_encode($v,$flags);}
function absint($v){return abs((int)$v);}
function sanitize_key($v){return preg_replace('/[^a-z0-9_-]/','',strtolower((string)$v));}
function sanitize_text_field($v){return strip_tags((string)$v);}
function wp_unslash($v){return $v;}
function current_user_can($v){return $GLOBALS['allowed'];}
function check_admin_referer($v){if(empty($GLOBALS['nonce_ok']))throw new RuntimeException('nonce rejected');}
function wp_die($v){throw new RuntimeException('permission denied');}
function get_current_user_id(){return $GLOBALS['user'];}
function admin_url($v){return 'https://example.test/wp-admin/'.$v;}
function add_query_arg($args,$url){return $url.'?'.http_build_query($args);}
function wp_safe_redirect($url){throw new ITKT_Test_Redirect($url);}
function add_option($k,$v,$x='',$autoload=''){if(isset($GLOBALS['options'][$k]))return false;$GLOBALS['options'][$k]=$v;return true;}
function set_transient($k,$v,$ttl){$GLOBALS['transients'][$k]=$v;return true;}
function get_transient($k){return $GLOBALS['transients'][$k]??false;}
function delete_transient($k){unset($GLOBALS['transients'][$k]);}
function wp_generate_uuid4(){static $i=0;return '12345678-1234-1234-1234-'.str_pad((string)++$i,12,'0',STR_PAD_LEFT);}
function esc_html($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
function esc_attr($v){return esc_html($v);}
function esc_url($v){return esc_html($v);}
function selected($v){if($v)echo 'selected';}
function wp_nonce_field($v){echo '<input type="hidden" name="_wpnonce" value="local-test-nonce">';}
function submit_button($text){echo '<p><button type="submit">'.esc_html($text).'</button></p>';}
function wp_remote_post($url,$args){$GLOBALS['calls']++;return array('status'=>200,'body'=>array('choices'=>array(array('finish_reason'=>'stop','message'=>array('content'=>'Warenkorb')))),'translations'=>array(array('text'=>'Warenkorb')));}
function wp_remote_retrieve_response_code($r){return $r['status'];}
function wp_remote_retrieve_body($r){return json_encode($r['body']);}
class ITKT_Plugin {static function module_enabled($v){return true;}}
class ITKT_Languages {
 static function instance(){return new self();}
 function get_active(){return array('en'=>array('name'=>'English'),'de'=>array('name'=>'Deutsch'),'ar'=>array('name'=>'Arabic'));}
 function get_default_code(){return 'en';}
}
class ITKT_Strings {
 static function instance(){return new self();}
 function get_translation($id,$target){return $GLOBALS['saved'][$id]??'';}
 function save_ai_translation_if_missing($id,$target,$translation,$source){if(isset($GLOBALS['saved'][$id])||$GLOBALS['rows'][$id]['original']!==$source)return false;$GLOBALS['saved'][$id]=$translation;return true;}
}
class ITKT_Test_DB {
 public $prefix='wp_'; public $options='wp_options';
 function esc_like($v){return $v;}
 function prepare($sql,...$args){return $sql;}
 function query($sql){return 0;}
 function get_results($sql,$format){
  if(preg_match('/id IN \(([0-9,]+)\)/',$sql,$m)){return array_values(array_intersect_key($GLOBALS['rows'],array_flip(explode(',',$m[1]))));}
  return array_values($GLOBALS['rows']);
 }
}
$GLOBALS['wpdb']=new ITKT_Test_DB();$GLOBALS['allowed']=true;$GLOBALS['nonce_ok']=true;$GLOBALS['user']=1;
$GLOBALS['rows']=array(1=>array('id'=>1,'original'=>'Cart'),2=>array('id'=>2,'original'=>'Checkout'));
$GLOBALS['saved']=array();$GLOBALS['transients']=array();$GLOBALS['options']=array();$GLOBALS['calls']=0;
foreach(array('guard','service') as $part)require dirname(__DIR__).'/includes/class-itkt-ai-translation-'.$part.'.php';
require dirname(__DIR__).'/includes/class-itkt-ai-http-provider.php';
require dirname(__DIR__).'/includes/class-itkt-ai-admin.php';
putenv('ITKT_OPENAI_API_KEY=local-test-dummy');
function run_generate($extra=array()){
 $_POST=array_merge(array('confirm_transfer'=>1,'provider'=>'openai','source_language'=>'en','target_language'=>'de','model'=>'configured-model','string_ids'=>array(1),'request_id'=>wp_generate_uuid4()),$extra);
 try{ITKT_AI_Admin::generate();}catch(ITKT_Test_Redirect $e){return $e->getMessage();}
}
function check($condition,$message){if(!$condition){fwrite(STDERR,'FAIL '.$message.PHP_EOL);exit(1);}echo 'PASS '.$message.PHP_EOL;}
if(isset($argv[1])&&'render'===$argv[1]){
 run_generate(); $_GET=array('itkt_ai_notice'=>'preview'); ob_start(); ITKT_AI_Admin::render(); $html=ob_get_clean();
 echo '<!doctype html><html lang="de"><meta charset="utf-8"><title>Lokaler KI-Admin-Test</title><style>body{font:16px/1.5 system-ui;background:#f2f5f9;color:#15283e;margin:0}aside{background:#15283e;color:white;padding:14px 28px}.wrap{max-width:1180px;margin:24px auto;padding:28px;background:white;border-radius:14px}h1{font-size:30px}h2{margin-top:32px;font-size:21px}table{border-collapse:collapse;width:100%}th,td{text-align:left;padding:12px;border:1px solid #dbe3ec}th{background:#eef3f9}input,select,button{font:inherit;padding:6px}button{background:#195a9b;color:white;border:0;border-radius:5px;padding:10px 16px}code{background:#eef3f9;padding:2px 4px}.notice{padding:6px 14px;background:#edf7f2;border-left:4px solid #268452}</style><aside>LOKALER PHP-TEST | Echte Plugin-Adminausgabe mit gemockter Datenbank/API. Kein Kundenshop, keine bezahlten Aufrufe.</aside>'.$html.'</html>';
 exit;
}
$GLOBALS['allowed']=false;try{run_generate();check(false,'permission');}catch(RuntimeException $e){check(0===$GLOBALS['calls'],'unauthorized admin cannot call provider');}$GLOBALS['allowed']=true;
$GLOBALS['nonce_ok']=false;try{run_generate();check(false,'nonce');}catch(RuntimeException $e){check(0===$GLOBALS['calls'],'invalid nonce cannot call provider');}$GLOBALS['nonce_ok']=true;
check(str_contains(run_generate(array('confirm_transfer'=>0)),'consent')&&0===$GLOBALS['calls'],'no consent makes no provider call');
check(str_contains(run_generate(array('provider'=>'custom_host')),'settings')&&0===$GLOBALS['calls'],'unknown provider rejected before network');
check(str_contains(run_generate(array('string_ids'=>array(1,2,3,4))),'selection')&&0===$GLOBALS['calls'],'admin limit is three texts');
$req='87654321-1234-1234-1234-123456789012';
check(str_contains(run_generate(array('request_id'=>$req)),'preview')&&1===$GLOBALS['calls'],'confirmed generation creates preview via mocked provider');
check(!isset($GLOBALS['saved'][1]),'generation does not publish');
check(str_contains(run_generate(array('request_id'=>$req)),'duplicate')&&1===$GLOBALS['calls'],'double submit does not repeat provider call');
$preview=get_transient('itkt_ai_preview_1');
check(!str_contains(json_encode($preview),'local-test-dummy'),'preview contains no API key');
$_POST=array('confirm_apply'=>1,'apply_ids'=>array(1),'preview_id'=>'wrong');try{ITKT_AI_Admin::apply();}catch(ITKT_Test_Redirect $e){check(str_contains($e->getMessage(),'expired')&&!$GLOBALS['saved'],'stale preview form cannot apply a newer preview');}
$GLOBALS['user']=2;$_POST['preview_id']=$preview['id'];try{ITKT_AI_Admin::apply();}catch(ITKT_Test_Redirect $e){check(str_contains($e->getMessage(),'expired')&&!$GLOBALS['saved'],'preview isolated to generating user');}$GLOBALS['user']=1;
$GLOBALS['rows'][1]['original']='Changed';try{ITKT_AI_Admin::apply();}catch(ITKT_Test_Redirect $e){check(str_contains($e->getMessage(),'stale')&&!$GLOBALS['saved'],'source change blocks apply');}
$GLOBALS['rows'][1]['original']='Cart';run_generate();$preview=get_transient('itkt_ai_preview_1');$_POST=array('confirm_apply'=>1,'apply_ids'=>array(1),'preview_id'=>$preview['id']);$GLOBALS['saved'][1]='Manual';try{ITKT_AI_Admin::apply();}catch(ITKT_Test_Redirect $e){check('Manual'===$GLOBALS['saved'][1],'concurrent manual translation preserved');}
unset($GLOBALS['saved'][1]);run_generate();$preview=get_transient('itkt_ai_preview_1');$_POST=array('confirm_apply'=>1,'apply_ids'=>array(1),'preview_id'=>$preview['id']);try{ITKT_AI_Admin::apply();}catch(ITKT_Test_Redirect $e){check('Warenkorb'===$GLOBALS['saved'][1]&&false===get_transient('itkt_ai_preview_1'),'explicit apply publishes reviewed suggestion and removes preview');}
putenv('ITKT_OPENAI_API_KEY');
