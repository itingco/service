<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\BusinessAnalytics\AdvisorAskRequest;
use App\Http\Requests\BusinessAnalytics\AdvisorResearchRequest;
use App\Http\Requests\BusinessAnalytics\ExperimentCreateRequest;
use App\Http\Requests\BusinessAnalytics\ExperimentUpdateRequest;
use App\Http\Requests\BusinessAnalytics\ProductAdvisorRequest;
use App\Http\Requests\BusinessAnalytics\ProductProfileRequest;
use App\Services\BusinessAnalytics\AnalyticsRepository;
use App\Services\BusinessAnalytics\AdvisorProviderStatus;
use App\Services\BusinessAnalytics\AdvisorPlainLanguageService;
use App\Services\BusinessAnalytics\ExperimentService;
use App\Services\BusinessAnalytics\InteractiveStrategyAdvisorService;
use App\Services\BusinessAnalytics\MarketResearchService;
use App\Services\BusinessAnalytics\ProductAdvisorRepository;
use App\Services\BusinessAnalytics\ProductFactService;
use App\Services\BusinessAnalytics\ProductOpportunityScorer;
use App\Services\BusinessAnalytics\ProductStrategyService;
use App\Services\BusinessAnalytics\BusinessAnalyticsPdfPresenter;
use App\Services\ProductStrategy\ProductStrategyCatalogRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Barryvdh\DomPDF\Facade\Pdf;
use Symfony\Component\HttpFoundation\Response;
use InvalidArgumentException;

final class ProductStrategyAdvisorController extends Controller
{
    public function index(
        ProductAdvisorRequest $request,
        AnalyticsRepository $analytics,
        ProductAdvisorRepository $repository,
        ProductStrategyCatalogRepository $catalog,
        ProductFactService $factsService,
        ProductOpportunityScorer $scorer,
        ProductStrategyService $strategyService,
        AdvisorPlainLanguageService $plainLanguage,
    ): View|RedirectResponse {
        $v=$request->validated();
        $source=strtoupper((string)($v['source']??'ALL'));
        $endMonth=(string)($v['end_month']??$analytics->latestPeriod()??now()->format('Y-m'));
        $itemCode=trim((string)($v['item_code']??''));
        $reviewId=(int)($v['review_id']??0);
        $review=null;$facts=null;$research=null;$market=[];$strategy=null;$messages=[];$experiments=[];$memory=[];$manualSimilarItems=[];
        $items=$repository->searchItems($itemCode, $source, 30);
        $exactItem=null;
        if($itemCode!==''){
            foreach($items as $candidate){
                if(strtoupper((string)$candidate['item_code'])===strtoupper($itemCode)){$exactItem=$candidate;break;}
            }
        }

        if($reviewId>0){
            $review=$repository->review($reviewId);
            if($review){$itemCode=(string)$review['item_code'];$source=(string)$review['source'];$endMonth=(string)$review['review_period'];$facts=(array)$review['internal_facts'];$research=is_array($review['external_research']??null)?$review['external_research']:null;}
        } elseif($itemCode!=='' && $exactItem!==null) {
            $facts=$factsService->review($itemCode,$source,$endMonth);
            $profile=(array)($facts['profile']??[]);
            $heuristic=$scorer->rank($profile,['online_share'=>$facts['online_share']??null,'online_yoy_growth'=>$facts['online_yoy_growth']??null,'offline_share'=>$facts['offline_share']??null],$scorer->heuristicSegments($profile));
            $memory=$repository->strategyMemory($itemCode);
            $strategy=$strategyService->build($facts,$heuristic,$memory);
            $reviewId=$repository->saveReview(['source'=>$source,'item_code'=>$itemCode,'review_period'=>$endMonth,'internal_facts'=>$facts,'profile'=>$profile,'diagnosis'=>$facts['diagnosis'],'strategy'=>$strategy,'research_status'=>'NOT_REQUESTED','user_id'=>$this->userId($request)]);
            return redirect()->route('business-analytics.product-advisor.index',['item_code'=>$itemCode,'source'=>$source,'end_month'=>$endMonth,'review_id'=>$reviewId]);
        }

        if($facts!==null){
            $memory=$memory?:$repository->strategyMemory($itemCode);
            $profile=(array)($facts['profile']??[]);
            $marketRaw=is_array($research)&&($research['segments']??[])!==[]?(array)$research['segments']:$scorer->rank($profile,['online_share'=>$facts['online_share']??null,'online_yoy_growth'=>$facts['online_yoy_growth']??null,'offline_share'=>$facts['offline_share']??null],$scorer->heuristicSegments($profile));
            $strategy=$plainLanguage->strategy($strategyService->build($facts,$marketRaw,$memory));
            $market=$plainLanguage->market($marketRaw);
            $messages=$reviewId>0?$repository->advisorMessages($reviewId):[];
            $experiments=$repository->experiments($itemCode,$source);
            $manualSimilarItems=$catalog->similarFor($itemCode);

            if($manualSimilarItems!==[]){
                $manualCodes=array_map(static fn(array $row): string => strtoupper((string)$row['similar_item_code']),$manualSimilarItems);
                $manualRows=array_map(static fn(array $row): array => [
                    'item_code'=>(string)$row['similar_item_code'],
                    'item_name'=>'SIMILAR MASTER · '.((string)($row['similar_item_name']?:$row['similar_item_code'])),
                    'buyer_overlap'=>'MASTER',
                    'sales'=>0.0,
                ],$manualSimilarItems);
                $historical=array_values(array_filter((array)($facts['related_items']??[]),static fn(array $row): bool => !in_array(strtoupper((string)($row['item_code']??'')),$manualCodes,true)));
                $facts['related_items']=array_merge($manualRows,$historical);
            }
        }

        $provider=(new AdvisorProviderStatus(
            provider:(string)config('business_advisor.provider','disabled'),
            model:(string)config('business_advisor.model',''),
            apiKey:(string)config('business_advisor.api_key',''),
            webResearch:(bool)config('business_advisor.web_research',true),
        ))->toArray();
        return view('business-analytics.product-advisor',compact('items','itemCode','source','endMonth','reviewId','review','facts','research','market','strategy','messages','experiments','memory','provider','manualSimilarItems'));
    }

    public function exportPdf(
        Request $request,
        ProductAdvisorRepository $repository,
        ProductOpportunityScorer $scorer,
        ProductStrategyService $strategyService,
        BusinessAnalyticsPdfPresenter $presenter,
        AdvisorPlainLanguageService $plainLanguage,
    ): Response {
        $reviewId = max(1, (int) $request->query('review_id', 0));
        $review = $repository->review($reviewId);
        if (!$review) abort(404, 'Review tidak ditemukan.');

        $facts = (array) ($review['internal_facts'] ?? []);
        $profile = (array) ($facts['profile'] ?? $review['profile'] ?? []);
        $research = is_array($review['external_research'] ?? null) ? $review['external_research'] : null;
        $market = is_array($research) && ($research['segments'] ?? []) !== []
            ? (array) $research['segments']
            : $scorer->rank($profile, [
                'online_share' => $facts['online_share'] ?? null,
                'online_yoy_growth' => $facts['online_yoy_growth'] ?? null,
                'offline_share' => $facts['offline_share'] ?? null,
            ], $scorer->heuristicSegments($profile));
        $memory = $repository->strategyMemory((string) ($review['item_code'] ?? ''));
        $strategy = $plainLanguage->strategy($strategyService->build($facts, $market, $memory));
        $market = $plainLanguage->market($market);
        $messages = array_map(function (array $message) use ($plainLanguage): array {
            if (is_array($message['response'] ?? null)) {
                $message['response'] = $plainLanguage->response((array) $message['response']);
            }
            return $message;
        }, $repository->advisorMessages($reviewId, 10));
        $experiments = $repository->experiments((string) ($review['item_code'] ?? ''), (string) ($review['source'] ?? 'ALL'));
        $chartSvg = $presenter->trendSvg((array) ($facts['monthly_trend'] ?? []), (array) ($facts['forecast'] ?? []));
        $filename = 'Product_Advisor_'.preg_replace('/[^A-Za-z0-9_-]+/', '_', (string) ($review['item_code'] ?? 'item')).'_'.($review['review_period'] ?? now()->format('Y-m')).'.pdf';

        return Pdf::loadView('business-analytics.pdf.product-advisor', compact('review', 'facts', 'profile', 'research', 'market', 'strategy', 'messages', 'experiments', 'memory', 'presenter', 'chartSvg'))
            ->setPaper('a4', 'landscape')
            ->download($filename);
    }

    public function items(Request $request, ProductAdvisorRepository $repository): JsonResponse
    {
        $search=trim((string)$request->query('q',''));$source=strtoupper((string)$request->query('source','ALL'));
        if(!in_array($source,['ALL','INGCO','SMI'],true))$source='ALL';
        return response()->json(['data'=>$repository->searchItems($search,$source,30)]);
    }

    public function saveProfile(ProductProfileRequest $request, ProductAdvisorRepository $repository): RedirectResponse
    {
        $v=$request->validated();$repository->saveProfile((string)$v['item_code'],(string)$v['source'],$v,$this->userId($request));
        return redirect()->route('business-analytics.product-advisor.index',['item_code'=>$v['item_code'],'source'=>$v['source'],'end_month'=>$v['end_month']??null])->with('status','Profil produk disimpan. Review baru dibuat dari profil terbaru.');
    }

    public function research(AdvisorResearchRequest $request, ProductAdvisorRepository $repository, MarketResearchService $researchService, ProductStrategyService $strategyService): RedirectResponse
    {
        $id=(int)$request->validated('review_id');$review=$repository->review($id);if(!$review)abort(404,'Review tidak ditemukan.');
        $facts=(array)$review['internal_facts'];$research=$researchService->research($facts);$repository->updateReviewResearch($id,$research,(string)($research['status']??'FAILED'));
        $strategy=$strategyService->build($facts,(array)($research['segments']??[]),$repository->strategyMemory((string)$review['item_code']));$repository->updateReviewStrategy($id,$strategy);
        return redirect()->route('business-analytics.product-advisor.index',['review_id'=>$id])->with('status',$research['status']==='SUCCESS'?'External market research selesai.':'Research tidak tersedia; heuristic internal tetap digunakan.');
    }

    public function ask(AdvisorAskRequest $request, ProductAdvisorRepository $repository, InteractiveStrategyAdvisorService $advisor): JsonResponse
    {
        $v=$request->validated();$id=(int)$v['review_id'];$review=$repository->review($id);if(!$review)abort(404,'Review tidak ditemukan.');
        $question=(string)$v['question'];$repository->saveAdvisorMessage($id,'USER',$question,null,null,null,$this->userId($request));
        $response=$advisor->ask((array)$review['internal_facts'],is_array($review['external_research']??null)?$review['external_research']:null,$repository->strategyMemory((string)$review['item_code']),$question);
        $repository->saveAdvisorMessage($id,'ADVISOR',(string)($response['summary']??''),$response,(string)($response['provider']??''),(string)($response['model']??''),$this->userId($request));
        return response()->json(['data'=>$response]);
    }

    public function createExperiment(ExperimentCreateRequest $request, ExperimentService $service): RedirectResponse
    {
        $id=$service->create($request->experiment(),$this->userId($request));
        return back()->with('status','Experiment #'.$id.' dibuat. Baseline window dihitung otomatis dari durasi test.');
    }

    public function updateExperiment(ExperimentUpdateRequest $request, int $experiment, ProductAdvisorRepository $repository): RedirectResponse
    {
        if(!$repository->experiment($experiment))abort(404,'Experiment tidak ditemukan.');$repository->updateExperiment($experiment,['status'=>$request->validated('status')]);return back()->with('status','Status experiment diperbarui.');
    }

    public function evaluateExperiment(int $experiment, ExperimentService $service): RedirectResponse
    {
        try {
            $result = $service->evaluate($experiment);
            return back()->with('status', 'Experiment dievaluasi sampai '.$result['evaluated_through'].': '.$result['evaluation']['decision'].'.');
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['experiment' => $e->getMessage()]);
        }
    }

    private function userId(Request $request): ?int
    {
        $user=$request->attributes->get('dashboard_user');return is_object($user)&&isset($user->id)?(int)$user->id:null;
    }
}
