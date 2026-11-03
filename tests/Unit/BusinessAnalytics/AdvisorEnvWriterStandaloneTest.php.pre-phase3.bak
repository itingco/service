<?php

declare(strict_types=1);

function envAssert(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

$file = __DIR__.'/../../../app/Services/BusinessAnalytics/AdvisorEnvWriter.php';
envAssert(is_file($file), 'AdvisorEnvWriter.php must exist.');
require_once $file;

use App\Services\BusinessAnalytics\AdvisorEnvWriter;

$writer = new AdvisorEnvWriter();
$source = "APP_NAME=Sales\nBUSINESS_ADVISOR_PROVIDER=disabled\nBUSINESS_ADVISOR_API_KEY=\nOTHER=x\n";
$out = $writer->withSettings($source, [
    'BUSINESS_ADVISOR_PROVIDER'=>'openai',
    'BUSINESS_ADVISOR_MODEL'=>'gpt-5.6-terra',
    'BUSINESS_ADVISOR_API_KEY'=>'sk-test_ABC-123',
    'BUSINESS_ADVISOR_WEB_RESEARCH'=>'true',
    'BUSINESS_ADVISOR_TIMEOUT_SECONDS'=>'45',
]);
envAssert(substr_count($out, 'BUSINESS_ADVISOR_PROVIDER=') === 1, 'Provider setting must not be duplicated.');
envAssert(str_contains($out, 'BUSINESS_ADVISOR_PROVIDER=openai'), 'Provider must be updated.');
envAssert(str_contains($out, 'BUSINESS_ADVISOR_MODEL=gpt-5.6-terra'), 'Missing settings must be appended.');
envAssert(str_contains($out, 'BUSINESS_ADVISOR_API_KEY="sk-test_ABC-123"'), 'API key must be quoted.');
envAssert(str_contains($out, 'OTHER=x'), 'Unrelated env values must be preserved.');

$out2 = $writer->withSettings($out, ['BUSINESS_ADVISOR_PROVIDER'=>'disabled']);
envAssert(substr_count($out2, 'BUSINESS_ADVISOR_PROVIDER=') === 1, 'Repeated writes must remain idempotent.');
envAssert(str_contains($out2, 'BUSINESS_ADVISOR_PROVIDER=disabled'), 'Repeated write must update existing value.');

echo "Advisor env writer tests passed.\n";
