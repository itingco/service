<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

final class SalesDashboardDetailRequest extends SalesDashboardRequest
{
    /** @return array<string,mixed> */
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'draw' => ['required', 'integer', 'min:0'],
            'start' => ['required', 'integer', 'min:0'],
            'length' => ['required', 'integer', 'min:10', 'max:100'],
            'order_column' => ['required', Rule::in([
                'database', 'invoice_date', 'invoice_number', 'line', 'customer_code',
                'customer_name', 'sales_person_name', 'item_code', 'item_name', 'grade',
                'quantity', 'line_total', 'line_contribution',
            ])],
            'order_dir' => ['required', Rule::in(['asc', 'desc'])],
            'search' => ['nullable', 'string', 'max:200'],
        ]);
    }

    protected function prepareForValidation(): void
    {
        parent::prepareForValidation();

        $this->merge([
            'draw' => (int) $this->input('draw', 0),
            'start' => (int) $this->input('start', 0),
            'length' => (int) $this->input('length', 25),
            'order_column' => strtolower((string) $this->input('order_column', 'invoice_date')),
            'order_dir' => strtolower((string) $this->input('order_dir', 'desc')),
            'search' => trim((string) $this->input('search', '')),
        ]);
    }
}
