<?php
// Test the actual atomic-insert method with a DB boundary double; not a MySQL integration test.
define('ABSPATH',__DIR__);
function absint($v){return abs((int)$v);}
function sanitize_key($v){return preg_replace('/[^a-z0-9_-]/','',strtolower($v));}
function wp_kses_post($v){return strip_tags($v,'<b><strong><em><span>');}
function wp_strip_all_tags($v,$x=false){return strip_tags($v);}
function current_time($v){return '2026-11-21 17:00:00';}
function get_option($k,$default){return $default;}
function update_option($k,$v,$autoload){$GLOBALS['version']=$v;}
function do_action(...$args){$GLOBALS['action']=$args;}
class ITKT_Languages{static function instance(){return new self();}function get_active(){return array('de'=>array());}}
class ITKT_AI_Test_DB{public $prefix='wp_';public $inserted=1;public $calls=0;public $sql;public $params;function prepare($sql,...$params){$this->sql=$sql;$this->params=$params;return $sql;}function query($sql){$this->calls++;return $this->inserted;}}
require dirname(__DIR__).'/includes/class-itkt-strings.php';
$db=new ITKT_AI_Test_DB();$GLOBALS['wpdb']=$db;
$ref=new ReflectionClass('ITKT_Strings');$catalog=$ref->newInstanceWithoutConstructor();
function check($c,$m){if(!$c){fwrite(STDERR,'FAIL '.$m.PHP_EOL);exit(1);}echo 'PASS '.$m.PHP_EOL;}
check($catalog->save_ai_translation_if_missing(1,'de','Warenkorb','Cart'),'actual catalog method accepts successful insert');
check(str_contains($db->sql,'INSERT IGNORE')&&str_contains($db->sql,'BINARY original=BINARY %s')&&!str_contains($db->sql,'UPDATE'),'SQL never updates existing translation and compares exact source atomically');
check(array('de','Warenkorb','2026-11-21 17:00:00',1,'Cart')===$db->params,'SQL data is parameterized');
check('itkt_string_translation_saved'===$GLOBALS['action'][0]&&2===$GLOBALS['version'],'successful apply triggers cache-invalidating save event');
$db->inserted=0;unset($GLOBALS['action']);check(!$catalog->save_ai_translation_if_missing(1,'de','Neu','Cart')&&!isset($GLOBALS['action']),'duplicate/stale no-op does not report save or fire hook');
$db->inserted=false;check(!$catalog->save_ai_translation_if_missing(1,'de','Neu','Cart'),'DB error does not report successful apply');
$before=$db->calls;check(!$catalog->save_ai_translation_if_missing(1,'xx','Neu','Cart')&&$before===$db->calls,'inactive target rejected before SQL');
check(!$catalog->save_ai_translation_if_missing(1,'de','','Cart')&&$before===$db->calls,'empty translation rejected before SQL');
