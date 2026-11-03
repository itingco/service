<?php

declare(strict_types=1);

function requestAssert(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

$file = __DIR__.'/../../../app/Services/BusinessAnalytics/OpenAIRequestBuilder.php';
requestAssert(is_file($file), 'OpenAIRequestBuilder.php must exist.');
require_once $file;

use App\Services\BusinessAnalytics\OpenAIRequestBuilder;

$schema = ['type'=>'object','properties'=>['summary'=>['type'=>'string']],'required'=>['summary'],'additionalProperties'=>false];
$builder = new OpenAIRequestBuilder('gpt-5.6-terra', true);
$research = $builder->research('research this', $schema);
requestAssert($research['model'] === 'gpt-5.6-terra', 'Research payload must use configured model.');
requestAssert(($research['tools'][0]['type'] ?? null) === 'web_search', 'Research must use current web_search tool, not preview.');
requestAssert(($research['tool_choice'] ?? null) === 'required', 'Research must require web search when web research is enabled.');
requestAssert(in_array('web_search_call.action.sources', $research['include'] ?? [], true), 'Research must request web search source metadata.');
requestAssert(($research['text']['format']['type'] ?? null) === 'json_schema', 'Research must use structured output json_schema.');
requestAssert(($research['text']['format']['strict'] ?? null) === true, 'Research structured output must be strict.');
requestAssert(($research['store'] ?? null) === false, 'Responses must not be stored by default.');

$noWeb = (new OpenAIRequestBuilder('gpt-5.6-terra', false))->research('research this', $schema);
requestAssert(!isset($noWeb['tools']), 'Research payload must omit web tool when web research is disabled.');
requestAssert(!isset($noWeb['tool_choice']), 'Research payload must omit tool_choice when web research is disabled.');

$advisor = $builder->advisor('answer this', $schema);
requestAssert(!isset($advisor['tools']), 'Advisor synthesis must not browse unless research flow explicitly requests web.');
requestAssert(($advisor['text']['format']['type'] ?? null) === 'json_schema', 'Advisor must use structured output.');

echo "OpenAI request builder tests passed.\n";
