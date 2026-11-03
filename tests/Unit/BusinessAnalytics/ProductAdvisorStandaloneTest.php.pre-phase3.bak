<?php

declare(strict_types=1);

use App\Services\BusinessAnalytics\AdvisorContextSanitizer;
use App\Services\BusinessAnalytics\AdvisorResponseValidator;
use App\Services\BusinessAnalytics\ExperimentEvaluationService;
use App\Services\BusinessAnalytics\ProductDiagnosisService;
use App\Services\BusinessAnalytics\ProductOpportunityScorer;

require_once __DIR__.'/../../../app/Services/BusinessAnalytics/ProductDiagnosisService.php';
require_once __DIR__.'/../../../app/Services/BusinessAnalytics/ProductOpportunityScorer.php';
require_once __DIR__.'/../../../app/Services/BusinessAnalytics/AdvisorContextSanitizer.php';
require_once __DIR__.'/../../../app/Services/BusinessAnalytics/ExperimentEvaluationService.php';
require_once __DIR__.'/../../../app/Services/BusinessAnalytics/AdvisorResponseValidator.php';

function assertTrue(bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$diagnosis = (new ProductDiagnosisService())->diagnose([
    'sales_3m' => 72000000.0,
    'previous_3m_sales' => 100000000.0,
    'yoy_growth' => -0.22,
    'momentum_3m' => -0.28,
    'online_share' => 0.11,
    'online_yoy_growth' => 0.18,
    'active_buyers' => 37,
    'previous_active_buyers' => 54,
    'top5_share' => 0.61,
    'volatility_cv' => 0.18,
]);
assertTrue(in_array('DECLINING', $diagnosis['labels'], true), 'Declining product must be classified DECLINING.');
assertTrue(in_array('CHANNEL GAP', $diagnosis['labels'], true), 'Low online share with positive online growth should be CHANNEL GAP.');
assertTrue(count($diagnosis['facts']) >= 2, 'Diagnosis must expose factual evidence.');

$scorer = new ProductOpportunityScorer();
$opportunities = $scorer->rank(
    ['specifications' => '20V brushless cordless impact drill, 2 batteries, heavy duty', 'use_cases' => 'drilling and fastening'],
    ['online_share' => 0.11, 'online_yoy_growth' => 0.18, 'offline_share' => 0.89],
    [
        ['segment' => 'Contractor Interior', 'fit_hint' => 0.9, 'why' => 'Repeated fastening and installation use', 'buyer_problem' => 'Butuh alat portable untuk pekerjaan instalasi berulang', 'selling_angle' => 'Brushless + 2 battery untuk mengurangi downtime', 'first_test' => 'Trial ke 20 contractor selama 14 hari', 'preferred_channel' => 'OFFLINE', 'source_count' => 2],
        ['segment' => 'Industrial Maintenance', 'fit_hint' => 0.8, 'why' => 'Portable maintenance work', 'source_count' => 1],
    ],
);
assertTrue($opportunities !== [], 'Opportunity scorer must return ranked segments.');
assertTrue($opportunities[0]['fit_score'] >= 0 && $opportunities[0]['fit_score'] <= 100, 'Fit score must be bounded 0..100.');
assertTrue($opportunities[0]['evidence'] !== [], 'High fit opportunity requires evidence.');
assertTrue(trim((string)($opportunities[0]['buyer_problem'] ?? '')) !== '', 'Market opportunity must explain the buyer problem.');
assertTrue(trim((string)($opportunities[0]['selling_angle'] ?? '')) !== '', 'Market opportunity must provide a selling angle.');
assertTrue(trim((string)($opportunities[0]['first_test'] ?? '')) !== '', 'Market opportunity must provide a first measurable test.');

$sanitized = (new AdvisorContextSanitizer())->sanitize([
    'item_code' => 'ABC123',
    'item_name' => 'Impact Drill',
    'customer_name' => 'PT Rahasia',
    'customer_code' => 'CUS001',
    'salesperson_name' => 'Budi',
    'invoice_number' => 'INV-1',
    'top_customers' => [['customer_name' => 'PT Rahasia', 'sales' => 10000000]],
    'sales_3m' => 72000000,
    'online_share' => 0.11,
]);
$json = json_encode($sanitized, JSON_THROW_ON_ERROR);
assertTrue(!str_contains($json, 'PT Rahasia'), 'Sanitizer must remove customer names.');
assertTrue(!str_contains($json, 'CUS001'), 'Sanitizer must remove customer codes.');
assertTrue(!str_contains($json, 'Budi'), 'Sanitizer must remove salesperson names.');
assertTrue(!str_contains($json, 'INV-1'), 'Sanitizer must remove invoice numbers.');
assertTrue(($sanitized['sales_3m'] ?? null) === 72000000, 'Sanitizer must retain aggregate business facts.');

$evaluation = (new ExperimentEvaluationService())->evaluate(
    baseline: ['revenue' => 70000000.0, 'quantity' => 140.0, 'days' => 14],
    result: ['revenue' => 92000000.0, 'quantity' => 172.0, 'days' => 14],
    threshold: ['revenue_uplift_min' => 0.15, 'quantity_uplift_min' => 0.10],
);
assertTrue($evaluation['decision'] === 'SCALE', 'Strong uplift above thresholds must recommend SCALE.');
assertTrue($evaluation['observed_revenue_uplift'] > 0.30, 'Observed uplift should be calculated from daily baseline.');

$validator = new AdvisorResponseValidator();
$valid = $validator->validate([
    'summary' => 'Prioritaskan contractor trial dan online bundle test.',
    'facts' => [['text' => 'Sales 3M turun 28%', 'source' => 'INTERNAL']],
    'inferences' => [['text' => 'Coverage customer kemungkinan melemah', 'confidence' => 'MEDIUM']],
    'market_opportunities' => [],
    'recommendations' => [],
    'experiments' => [],
    'risks' => [],
    'missing_information' => [],
    'external_sources' => [],
]);
assertTrue($valid['summary'] !== '', 'Valid advisor response must preserve summary.');

$failed = false;
try {
    $validator->validate(['summary' => 'missing arrays']);
} catch (InvalidArgumentException) {
    $failed = true;
}
assertTrue($failed, 'Validator must reject incomplete response schema.');

echo "Product Advisor standalone tests passed.\n";
