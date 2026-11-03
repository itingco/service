<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

final class StockAgingDetailRequest extends StockAgingRequest
{
    protected function prepareForValidation(): void
    {
        parent::prepareForValidation();

        $this->merge([
            'source' => strtoupper(trim((string) $this->input('source'))),
            'bucket' => strtolower(trim((string) $this->input('bucket'))),
            'warehouse_id' => $this->input('warehouse_id'),
            'page' => max(1, (int) $this->input('page', 1)),
        ]);
    }

    public function rules(): array
    {
        return [
            ...parent::rules(),
            'source' => ['required', Rule::in(['INGCO', 'WADFOW', 'SMI'])],
            'warehouse_id' => ['required', 'integer', 'min:1'],
            'bucket' => ['required', Rule::in(['0-30', '31-60', '61-90', '91-120', 'over-120'])],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
