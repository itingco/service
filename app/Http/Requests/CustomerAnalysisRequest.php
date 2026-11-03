<?php

declare(strict_types=1);

namespace App\Http\Requests;

use DateTimeImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class CustomerAnalysisRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'database' => strtoupper(trim((string) $this->input('database', 'BOTH'))),
            'status' => strtolower(trim((string) $this->input('status', 'all'))),
            'search' => trim((string) $this->input('search', '')),
            'sort' => strtolower(trim((string) $this->input('sort', 'sales_desc'))),
            'end_date' => trim((string) $this->input('end_date', now()->format('Y-m-d'))),
        ]);
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'database' => ['required', Rule::in(['BOTH', 'INGCO', 'SMI'])],
            'status' => ['required', Rule::in(['all', 'active', 'inactive'])],
            'search' => ['nullable', 'string', 'max:120'],
            'sort' => ['required', Rule::in(['sales_desc', 'sales_asc', 'name_asc', 'name_desc', 'recent_desc'])],
            'end_date' => ['required', 'date_format:Y-m-d'],
            'sales_person_id' => ['nullable', 'integer', 'min:1'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    /** @return array{database:string,status:string,search:string,sort:string,end_date:DateTimeImmutable,sales_person_id:?int,page:int} */
    public function filters(): array
    {
        $validated = $this->validated();

        return [
            'database' => (string) $validated['database'],
            'status' => (string) $validated['status'],
            'search' => trim((string) ($validated['search'] ?? '')),
            'sort' => (string) $validated['sort'],
            'end_date' => new DateTimeImmutable((string) $validated['end_date']),
            'sales_person_id' => isset($validated['sales_person_id']) ? (int) $validated['sales_person_id'] : null,
            'page' => max(1, (int) ($validated['page'] ?? 1)),
        ];
    }
}
