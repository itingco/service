<?php

declare(strict_types=1);

function healthAssert(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

$file = __DIR__.'/../../../app/Services/BusinessAnalytics/AdvisorHealthProbe.php';
healthAssert(is_file($file), 'AdvisorHealthProbe.php must exist.');
require_once $file;

use App\Services\BusinessAnalytics\AdvisorHealthProbe;

$probe = new AdvisorHealthProbe();
$disabled = $probe->preflight('disabled', 'gpt-5.6-terra', '');
healthAssert($disabled['ready'] === false && $disabled['code'] === 'DISABLED', 'Disabled provider must not be ready.');
$missing = $probe->preflight('openai', 'gpt-5.6-terra', '');
healthAssert($missing['ready'] === false && $missing['code'] === 'MISSING_KEY', 'OpenAI without key must report MISSING_KEY.');
$missingModel = $probe->preflight('openai', '', 'sk-test');
healthAssert($missingModel['ready'] === false && $missingModel['code'] === 'MISSING_MODEL', 'OpenAI without model must report MISSING_MODEL.');
$ready = $probe->preflight('openai', 'gpt-5.6-terra', 'sk-test');
healthAssert($ready['ready'] === true && $ready['code'] === 'READY', 'Configured OpenAI must pass preflight.');

$ok = $probe->interpretHttp(200, ['output'=>[['type'=>'message','content'=>[['type'=>'output_text','text'=>'OK']]]]]);
healthAssert($ok['ok'] === true && $ok['code'] === 'LIVE_OK', 'Successful Responses API result must report LIVE_OK.');
$auth = $probe->interpretHttp(401, ['error'=>['message'=>'Incorrect API key']]);
healthAssert($auth['ok'] === false && $auth['code'] === 'AUTH_FAILED', '401 must report AUTH_FAILED.');
$rate = $probe->interpretHttp(429, ['error'=>['message'=>'Rate limit reached']]);
healthAssert($rate['ok'] === false && $rate['code'] === 'RATE_LIMITED', '429 must report RATE_LIMITED.');
$other = $probe->interpretHttp(500, ['error'=>['message'=>'Server error']]);
healthAssert($other['ok'] === false && $other['code'] === 'HTTP_500', 'Unexpected HTTP status must retain status code.');

echo "Advisor health probe tests passed.\n";
