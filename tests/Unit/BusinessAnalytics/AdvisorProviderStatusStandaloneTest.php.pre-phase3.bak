<?php

declare(strict_types=1);

function statusAssert(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

$file = __DIR__.'/../../../app/Services/BusinessAnalytics/AdvisorProviderStatus.php';
statusAssert(is_file($file), 'AdvisorProviderStatus.php must exist.');
require_once $file;

use App\Services\BusinessAnalytics\AdvisorProviderStatus;

$disabled = (new AdvisorProviderStatus('disabled', 'gpt-5.6-terra', '', true))->toArray();
statusAssert($disabled['state'] === 'DISABLED', 'Disabled provider must expose DISABLED state.');
statusAssert($disabled['configured'] === false, 'Disabled provider must not be configured.');
statusAssert($disabled['display_model'] === null, 'Disabled state must hide model to avoid misleading badge.');
statusAssert($disabled['badge'] === 'AI OFF · INTERNAL ONLY', 'Disabled badge must clearly explain internal-only mode.');

$missing = (new AdvisorProviderStatus('openai', 'gpt-5.6-terra', '', true))->toArray();
statusAssert($missing['state'] === 'NEEDS_KEY', 'OpenAI without API key must expose NEEDS_KEY state.');
statusAssert($missing['configured'] === false, 'OpenAI without API key must not be configured.');
statusAssert(str_contains($missing['badge'], 'SETUP REQUIRED'), 'Missing-key badge must request setup.');

$ready = (new AdvisorProviderStatus('openai', 'gpt-5.6-terra', 'sk-test-secret', true))->toArray();
statusAssert($ready['state'] === 'READY', 'OpenAI with key must expose READY state.');
statusAssert($ready['configured'] === true, 'OpenAI with key must be configured.');
statusAssert($ready['display_model'] === 'gpt-5.6-terra', 'Ready state must show configured model.');
statusAssert($ready['badge'] === 'AI ON · GPT-5.6-TERRA · WEB ON', 'Ready badge must include model and web state.');

echo "Advisor provider status tests passed.\n";
