<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Data\SalesDashboardFilter;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SalesDashboardRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    // MULTISTATUS-PATCH
    protected function prepareForValidation(): void
    {
        $this->merge([
            'database' => strtolower((string) $this->input('database', 'all')),
            'end_date' => (string) $this->input('end_date', now()->format('Y-m-d')),
            'item_status' => implode(',', array_values(array_unique(array_map('strtolower', array_filter(array_map('trim', (array) $this->input('item_status', ['all']))))))),
        ]);
    }

    public function rules(): array
    {
        return [
            'database' => ['required', Rule::in(['all', 'ingco', 'wadfow', 'smi'])],
            'end_date' => ['required', 'date_format:Y-m-d'],
            'item_status' => ['required', 'regex:/^(all|normal|discontinued|discontinued_sale|clearance|reject)(,(normal|discontinued|discontinued_sale|clearance|reject))*$/D'],
        ];
    }

    public function messages(): array
    {
        return [
            'database.required' => 'Database wajib dipilih.',
            'database.in' => 'Pilihan database tidak valid.',
            'end_date.required' => 'Tanggal laporan wajib diisi.',
            'end_date.date_format' => 'Format tanggal laporan harus YYYY-MM-DD.',
            'item_status.required' => 'Status item wajib dipilih.',
            'item_status.in' => 'Status item tidak valid.',
        ];
    }

    protected function getRedirectUrl(): string
    {
        return route('sales-dashboard.index');
    }

    public function filter(): SalesDashboardFilter
    {
        $validated = $this->validated();

        return SalesDashboardFilter::from(
            (string) $validated['database'],
            (string) $validated['end_date'],
            (string) $validated['item_status'],
        );
    }
}
