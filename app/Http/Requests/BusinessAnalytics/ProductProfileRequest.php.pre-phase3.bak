<?php

declare(strict_types=1);

namespace App\Http\Requests\BusinessAnalytics;

use Illuminate\Foundation\Http\FormRequest;

final class ProductProfileRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array
    {
        return [
            'item_code'=>['required','string','max:255'],'item_name'=>['nullable','string','max:255'],'source'=>['required','in:ALL,INGCO,SMI'],
            'end_month'=>['nullable','date_format:Y-m'],'specifications'=>['required','string','max:10000'],'use_cases'=>['nullable','string','max:5000'],
            'advantages'=>['nullable','string','max:5000'],'reference_price'=>['nullable','numeric','min:0'],'current_stock'=>['nullable','numeric','min:0'],
            'target_margin_percent'=>['nullable','numeric','min:0','max:100'],'competitor_notes'=>['nullable','string','max:5000'],'constraints'=>['nullable','string','max:5000'],
        ];
    }
}
