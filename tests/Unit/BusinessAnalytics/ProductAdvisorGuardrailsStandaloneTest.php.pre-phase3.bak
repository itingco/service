<?php

declare(strict_types=1);

use App\Services\BusinessAnalytics\ExperimentEvaluationService;
use App\Services\BusinessAnalytics\ProductStrategyService;

require_once __DIR__.'/../../../app/Services/BusinessAnalytics/ExperimentEvaluationService.php';
require_once __DIR__.'/../../../app/Services/BusinessAnalytics/ProductStrategyService.php';

function guardAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$thrown = false;
try {
    (new ExperimentEvaluationService())->evaluate(
        baseline: ['revenue' => 0.0, 'quantity' => 0.0, 'days' => 14],
        result: ['revenue' => 10000000.0, 'quantity' => 10.0, 'days' => 14],
        threshold: ['revenue_uplift_min' => 0.10, 'quantity_uplift_min' => 0.05],
    );
} catch (InvalidArgumentException) {
    $thrown = true;
}
guardAssert($thrown, 'Zero-data baseline must be rejected instead of producing SCALE/MODIFY/STOP.');

$facts = [
    'diagnosis' => ['labels' => []],
    'online_share' => 0.40,
    'online_yoy_growth' => 0.0,
    'lost_revenue' => 0.0,
    'cross_sell_candidates' => [],
    'related_items' => [],
];
$service = new ProductStrategyService();
$oneScale = $service->build($facts, [], [
    ['decision' => 'SCALE', 'channel' => 'ONLINE', 'observed_revenue_uplift' => 0.20],
]);
$oneTitles = array_column($oneScale['strategies'], 'title');
guardAssert(!in_array('Reuse company-proven playbook', $oneTitles, true), 'One SCALE experiment is not enough to create a learned company playbook.');

$twoScale = $service->build($facts, [], [
    ['decision' => 'SCALE', 'channel' => 'ONLINE', 'observed_revenue_uplift' => 0.20],
    ['decision' => 'SCALE', 'channel' => 'ONLINE', 'observed_revenue_uplift' => 0.16],
]);
$twoTitles = array_column($twoScale['strategies'], 'title');
guardAssert(in_array('Reuse company-proven playbook', $twoTitles, true), 'Two SCALE experiments should be enough to surface a cautious learned playbook.');

echo "Product Advisor guardrail tests passed.\n";
