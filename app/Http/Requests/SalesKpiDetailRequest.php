<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

final class SalesKpiDetailRequest extends SalesKpiRequest
{
    /** @return array<string,mixed> */
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'detail_type' => ['required', Rule::in(['all', 'risk', 'grade'])],
            'draw' => ['required', 'integer', 'min:0'],
            'start' => ['required', 'integer', 'min:0'],
            'length' => ['required', 'integer', 'min:10', 'max:100'],
            'order_column' => ['required', Rule::in([
                'database', 'invoice_date', 'invoice_number', 'line',
                'item_code', 'item_name', 'grade', 'quantity', 'line_total',
            ])],
            'order_dir' => ['required', Rule::in(['asc', 'desc'])],
        ]);
    }

    protected function prepareForValidation(): void
    {
        parent::prepareForValidation();

        $this->merge([
            'detail_type' => strtolower((string) $this->input('detail_type', 'all')),
            'draw' => (int) $this->input('draw', 0),
            'start' => (int) $this->input('start', 0),
            'length' => (int) $this->input('length', 10),
            'order_column' => strtolower((string) $this->input('order_column', 'invoice_date')),
            'order_dir' => strtolower((string) $this->input('order_dir', 'desc')),
        ]);
    }
}
