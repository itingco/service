<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Data\StockAgingFilter;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StockAgingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    // MULTISTATUS-PATCH
    protected function prepareForValidation(): void
    {
        $this->merge([
            'date' => (string) $this->input('date', now()->format('Y-m-d')),
            'item_status' => implode(',', array_values(array_unique(array_map('strtolower', array_filter(array_map('trim', (array) $this->input('item_status', ['all']))))))),
            'database' => strtolower((string) $this->input('database', 'all')),
        ]);
    }

    public function rules(): array
    {
        return [
            'date' => ['required', 'date_format:Y-m-d'],
            'item_status' => ['required', 'regex:/^(all|normal|discontinued|discontinued_sale|clearance|reject)(,(normal|discontinued|discontinued_sale|clearance|reject))*$/D'],
            'database' => ['required', Rule::in(['all', 'ingco', 'wadfow', 'smi'])],
        ];
    }

    public function messages(): array
    {
        return [
            'date.required' => 'Tanggal laporan wajib diisi.',
            'date.date_format' => 'Format tanggal laporan harus YYYY-MM-DD.',
            'item_status.required' => 'Status item wajib dipilih.',
            'item_status.in' => 'Status item tidak valid.',
            'database.required' => 'Database wajib dipilih.',
            'database.in' => 'Pilihan database tidak valid.',
        ];
    }

    protected function getRedirectUrl(): string
    {
        return route('stock-aging.index');
    }

    public function filter(): StockAgingFilter
    {
        $validated = $this->validated();

        return StockAgingFilter::from(
            (string) $validated['date'],
            (string) $validated['item_status'],
            (string) $validated['database'],
        );
    }
}
