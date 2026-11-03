<?php

declare(strict_types=1);

namespace App\Services\BusinessAnalytics;

use DateTimeImmutable;
use InvalidArgumentException;

final class ExperimentService
{
    public function __construct(
        private readonly ProductAdvisorRepository $repository,
        private readonly ExperimentEvaluationService $evaluator,
    ) {}

    /** @param array<string,mixed> $input */
    public function create(array $input, ?int $userId = null): int
    {
        $start = new DateTimeImmutable((string)$input['start_date']);
        $end = new DateTimeImmutable((string)$input['end_date']);
        if ($end < $start) throw new InvalidArgumentException('Experiment end_date tidak boleh sebelum start_date.');
        $duration = max(1, $start->diff($end)->days + 1);
        $baselineEnd = $start->modify('-1 day');
        $baselineStart = $baselineEnd->modify('-'.($duration-1).' days');
        $threshold = (array)($input['success_threshold'] ?? ['revenue_uplift_min'=>0.10,'quantity_uplift_min'=>0.05]);
        return $this->repository->createExperiment([
            'review_id'=>$input['review_id']??null,'item_code'=>(string)$input['item_code'],'source'=>strtoupper((string)($input['source']??'ALL')),
            'channel'=>strtoupper((string)($input['channel']??'OMNICHANNEL')),'title'=>(string)$input['title'],'hypothesis'=>(string)$input['hypothesis'],
            'target_audience'=>(string)$input['target_audience'],'offer_description'=>(string)$input['offer_description'],'start_date'=>$start->format('Y-m-d'),'end_date'=>$end->format('Y-m-d'),
            'baseline_start_date'=>$baselineStart->format('Y-m-d'),'baseline_end_date'=>$baselineEnd->format('Y-m-d'),
            'kpi_json'=>json_encode($input['kpis']??['revenue/day','quantity/day'],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),
            'success_threshold_json'=>json_encode($threshold,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),'stop_rule'=>(string)$input['stop_rule'],
            'control_definition_json'=>isset($input['control_definition'])?json_encode($input['control_definition'],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES):null,
            'status'=>(string)($input['status']??'DRAFT'),'result_json'=>null,'decision'=>null,'created_by_user_id'=>$userId,
        ]);
    }

    /** @return array<string,mixed> */
    public function evaluate(int $id): array
    {
        $experiment=$this->repository->experiment($id);
        if(!$experiment)throw new InvalidArgumentException('Experiment tidak ditemukan.');
        $baseline=$this->repository->dailyWindow((string)$experiment['item_code'],(string)$experiment['source'],(string)$experiment['baseline_start_date'],(string)$experiment['baseline_end_date'],(string)$experiment['channel']);
        $experimentStart = new DateTimeImmutable((string) $experiment['start_date']);
        $experimentEnd = new DateTimeImmutable((string) $experiment['end_date']);
        $today = new DateTimeImmutable('today');
        if ($today < $experimentStart) {
            throw new InvalidArgumentException('Experiment belum dimulai; belum ada result window yang dapat dievaluasi.');
        }
        $resultEnd = $today < $experimentEnd ? $today : $experimentEnd;
        $result=$this->repository->dailyWindow((string)$experiment['item_code'],(string)$experiment['source'],(string)$experiment['start_date'],$resultEnd->format('Y-m-d'),(string)$experiment['channel']);
        $threshold=json_decode((string)$experiment['success_threshold_json'],true)?:[];
        $evaluation=$this->evaluator->evaluate($baseline,$result,$threshold);
        $status=match($evaluation['decision']){'SCALE'=>'SCALED','MODIFY'=>'MODIFIED','STOP'=>'STOPPED',default=>'COMPLETED'};
        $this->repository->updateExperiment($id,['result_json'=>json_encode($evaluation,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),'decision'=>$evaluation['decision'],'status'=>$status]);
        return ['experiment'=>$experiment,'baseline'=>$baseline,'result'=>$result,'evaluated_through'=>$resultEnd->format('Y-m-d'),'evaluation'=>$evaluation];
    }
}
