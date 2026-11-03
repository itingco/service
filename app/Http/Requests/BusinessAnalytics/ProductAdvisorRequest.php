<?php

declare(strict_types=1);

namespace App\Http\Requests\BusinessAnalytics;

use Illuminate\Foundation\Http\FormRequest;

final class ProductAdvisorRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array
    {
        return [
            'item_code'=>['nullable','string','max:255'],
            'source'=>['nullable','in:ALL,INGCO,SMI'],
            'end_month'=>['nullable','date_format:Y-m'],
            'review_id'=>['nullable','integer','min:1'],
        ];
    }
}
