<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

final class SalesKpiDetailExportRequest extends SalesKpiRequest
{
    /** @return array<string,mixed> */
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'detail_type' => ['required', Rule::in(['all', 'risk', 'grade'])],
            'detail_search' => ['nullable', 'string', 'max:200'],
        ]);
    }

    protected function prepareForValidation(): void
    {
        parent::prepareForValidation();
        $this->merge([
            'detail_type' => strtolower((string) $this->input('detail_type', 'all')),
            'detail_search' => trim((string) $this->input('detail_search', '')),
        ]);
    }
}
