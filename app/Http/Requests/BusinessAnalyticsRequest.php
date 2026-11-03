<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Data\BusinessAnalytics\BusinessAnalyticsFilter;
use Illuminate\Foundation\Http\FormRequest;

final class BusinessAnalyticsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'database' => ['nullable', 'in:all,ingco,smi'],
            'end_month' => ['nullable', 'date_format:Y-m'],
            'group_by' => ['nullable', 'in:company,line,customer,salesperson,item'],
            'source' => ['nullable', 'in:INGCO,SMI'],
            'line' => ['nullable', 'string', 'max:160'],
            'customer_code' => ['nullable', 'string', 'max:240'],
            'salesperson' => ['nullable', 'string', 'max:180'],
        ];
    }

    public function filter(?string $defaultMonth = null): BusinessAnalyticsFilter
    {
        $input = $this->validated();
        if (($input['end_month'] ?? null) === null && $defaultMonth !== null) {
            $input['end_month'] = $defaultMonth;
        }
        return BusinessAnalyticsFilter::from($input);
    }
}
