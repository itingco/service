<?php

declare(strict_types=1);
namespace App\Http\Requests\BusinessAnalytics;
use Illuminate\Foundation\Http\FormRequest;
final class ExperimentCreateRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array
    {
        return [
            'review_id'=>['nullable','integer','min:1'],'item_code'=>['required','string','max:255'],'source'=>['required','in:ALL,INGCO,SMI'],'channel'=>['required','in:ONLINE,OFFLINE,OMNICHANNEL'],
            'title'=>['required','string','max:255'],'hypothesis'=>['required','string','max:5000'],'target_audience'=>['required','string','max:5000'],'offer_description'=>['required','string','max:5000'],
            'start_date'=>['required','date_format:Y-m-d'],'end_date'=>['required','date_format:Y-m-d','after_or_equal:start_date'],'stop_rule'=>['required','string','max:5000'],
            'revenue_uplift_min'=>['nullable','numeric','min:-1','max:10'],'quantity_uplift_min'=>['nullable','numeric','min:-1','max:10'],
        ];
    }
    /** @return array<string,mixed> */
    public function experiment(): array
    {
        $v=$this->validated();
        $v['kpis']=['revenue/day','quantity/day'];
        $v['success_threshold']=['revenue_uplift_min'=>(float)($v['revenue_uplift_min']??0.10),'quantity_uplift_min'=>(float)($v['quantity_uplift_min']??0.05)];
        unset($v['revenue_uplift_min'],$v['quantity_uplift_min']);
        return $v;
    }
}
