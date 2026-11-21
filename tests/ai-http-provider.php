<?php
require __DIR__ . '/ai-translation-service.php';
require dirname(__DIR__) . '/includes/class-itkt-ai-http-provider.php';
function is_wp_error($value) { return $value instanceof WP_Error; }
function wp_json_encode($value,$flags=0) { return json_encode($value,$flags); }
function wp_remote_retrieve_response_code($response) { return $response['status']; }
function wp_remote_retrieve_body($response) { return json_encode($response['body']); }
$GLOBALS['http_calls']=array(); $GLOBALS['http_mode']='ok';
function wp_remote_post($url,$args) {
    $GLOBALS['http_calls'][]=array('url'=>$url,'args'=>$args);
    if ('limited'===$GLOBALS['http_mode']) { return array('status'=>429,'body'=>array('message'=>'Private provider diagnostic')); }
    if ('truncated'===$GLOBALS['http_mode']) { return array('status'=>200,'body'=>array('choices'=>array(array('finish_reason'=>'length','message'=>array('content'=>'partial'))))); }
    if ('badjson'===$GLOBALS['http_mode']) { return array('status'=>200,'body'=>array()); }
    return array('status'=>200,'body'=>str_contains($url,'openai') ? array('choices'=>array(array('finish_reason'=>'stop','message'=>array('content'=>'Warenkorb')))) : array('translations'=>array(array('text'=>'Warenkorb'))));
}
// Synthetic dummy values, not real API keys. No network transport exists in this test.
putenv('ITKT_OPENAI_API_KEY=local-test-dummy'); putenv('ITKT_DEEPL_API_KEY=local-test-dummy');
$openai=new ITKT_AI_HTTP_Provider('openai','configured-model');
check('Warenkorb'===$openai->translate('Cart','en','de'),'OpenAI response parser with mocked HTTP');
$call=end($GLOBALS['http_calls']); $body=json_decode($call['args']['body'],true);
check('https://api.openai.com/v1/chat/completions'===$call['url'] && false===$body['store'] && 4096===$body['max_completion_tokens'],'OpenAI fixed endpoint, no storage request and output bound');
check(0===$call['args']['redirection'] && 15===$call['args']['timeout'] && 100000===$call['args']['limit_response_size'],'HTTP redirect/timeout/response bounds');
check(!str_contains($call['args']['body'],'local-test-dummy'),'key excluded from request payload');
foreach(array('deepl_free'=>'https://api-free.deepl.com/v2/translate','deepl_pro'=>'https://api.deepl.com/v2/translate') as $provider=>$url) {
    check('Warenkorb'===(new ITKT_AI_HTTP_Provider($provider))->translate('Cart','de','en'),'DeepL mocked response '.$provider);
    $call=end($GLOBALS['http_calls']); $body=json_decode($call['args']['body'],true);
    check($url===$call['url'] && 'EN-US'===$body['target_lang'] && array('Cart')===$body['text'],'DeepL endpoint and explicit target variant '.$provider);
}
$before=count($GLOBALS['http_calls']);
check((new ITKT_AI_HTTP_Provider('untrusted','model'))->translate('Cart','en','de') instanceof WP_Error && $before===count($GLOBALS['http_calls']),'unknown providers cannot choose network destinations');
check((new ITKT_AI_HTTP_Provider('openai',''))->translate('Cart','en','de') instanceof WP_Error && $before===count($GLOBALS['http_calls']),'missing model makes no call');
$GLOBALS['http_mode']='limited'; $r=$openai->translate('Cart','en','de');
check($r instanceof WP_Error && count($GLOBALS['http_calls'])===$before+1,'rate limit returns error without retries');
$GLOBALS['http_mode']='truncated'; check($openai->translate('Cart','en','de') instanceof WP_Error,'truncated model output rejected');
$GLOBALS['http_mode']='badjson'; check($openai->translate('Cart','en','de') instanceof WP_Error,'malformed provider body rejected');
putenv('ITKT_OPENAI_API_KEY');
$before=count($GLOBALS['http_calls']);
check((new ITKT_AI_HTTP_Provider('openai','model'))->translate('Cart','en','de') instanceof WP_Error && $before===count($GLOBALS['http_calls']),'missing credential makes no call');
putenv('ITKT_DEEPL_API_KEY');
