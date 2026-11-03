<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Data\SalesKpi\SalesKpiFilter;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SalesKpiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string,mixed> */
    public function rules(): array
    {
        return [
            'end_date' => ['required', 'date_format:Y-m-d'],
            'line' => ['nullable', 'string', 'max:150'],
            'discontinue' => ['required', Rule::in(['all', 'yes', 'no'])],
            'clearance' => ['required', Rule::in(['all', 'yes', 'no'])],
            'grade' => ['required', Rule::in(['ALL', 'A', 'B', 'C', 'D', 'E', 'N', 'BLANK'])],
            'search' => ['nullable', 'string', 'max:150'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'end_date' => (string) $this->input('end_date', now()->format('Y-m-d')),
            'line' => trim((string) $this->input('line', '')),
            'discontinue' => strtolower((string) $this->input('discontinue', 'all')),
            'clearance' => strtolower((string) $this->input('clearance', 'all')),
            'grade' => strtoupper((string) $this->input('grade', 'all')),
            'search' => trim((string) $this->input('search', '')),
        ]);
    }

    public function filter(): SalesKpiFilter
    {
        $validated = $this->validated();

        return SalesKpiFilter::from(
            endDate: (string) $validated['end_date'],
            line: isset($validated['line']) ? (string) $validated['line'] : null,
            discontinue: (string) $validated['discontinue'],
            clearance: (string) $validated['clearance'],
            grade: (string) $validated['grade'],
            search: (string) ($validated['search'] ?? ''),
        );
    }
}
