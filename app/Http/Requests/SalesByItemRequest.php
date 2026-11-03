<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Data\SalesByItemFilter;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SalesByItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'database' => strtolower((string) $this->input('database', 'all')),
            'end_date' => (string) $this->input('end_date', now()->format('Y-m-d')),
        ]);
    }

    public function rules(): array
    {
        return [
            'database' => ['required', Rule::in(['all', 'ingco', 'smi'])],
            'end_date' => ['required', 'date_format:Y-m-d'],
        ];
    }

    protected function getRedirectUrl(): string
    {
        return route('sales-by-item.index');
    }

    public function messages(): array
    {
        return [
            'database.required' => 'Database wajib dipilih.',
            'database.in' => 'Pilihan database tidak valid.',
            'end_date.required' => 'Tanggal akhir wajib diisi.',
            'end_date.date_format' => 'Format tanggal akhir harus YYYY-MM-DD.',
        ];
    }

    public function filter(): SalesByItemFilter
    {
        $validated = $this->validated();

        return SalesByItemFilter::from(
            (string) $validated['database'],
            (string) $validated['end_date'],
        );
    }
}
