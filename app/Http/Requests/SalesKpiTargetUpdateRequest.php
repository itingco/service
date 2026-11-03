<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

final class SalesKpiTargetUpdateRequest extends FormRequest
{
    /** @var list<string> */
    private array $duplicateKeys = [];

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string,mixed> */
    public function rules(): array
    {
        return [
            'targets' => ['required', 'array', 'min:1'],
            'targets.*' => ['nullable', 'numeric', 'min:0', 'max:9999999999999999.99'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $rawTargets = (array) $this->input('targets', []);
        $normalized = [];
        $seen = [];

        foreach ($rawTargets as $line => $value) {
            $key = $this->normalizeLine((string) $line);
            if (isset($seen[$key])) {
                $this->duplicateKeys[] = $key;
            }
            $seen[$key] = true;
            $normalized[$key] = $this->normalizeMoney($value);
        }

        $this->merge(['targets' => $normalized]);
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($this->duplicateKeys !== []) {
                $validator->errors()->add('targets', 'Terdapat LiniPenjualan ganda setelah normalisasi.');
            }
        });
    }

    /** @return array<string,?float> */
    public function normalizedTargets(): array
    {
        /** @var array<string,?float|int|string> $targets */
        $targets = (array) $this->validated('targets', []);
        $result = [];

        foreach ($targets as $line => $value) {
            $result[$this->normalizeLine((string) $line)] = $value === null ? null : (float) $value;
        }

        return $result;
    }

    private function normalizeMoney(mixed $value): ?float
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }

        $digits = preg_replace('/[^0-9-]/', '', (string) $value) ?? '';
        if ($digits === '' || (float) $digits == 0.0) {
            return null;
        }

        return (float) $digits;
    }

    private function normalizeLine(string $line): string
    {
        $line = trim($line);

        return strtoupper(preg_replace('/\s+/', ' ', $line) ?? $line);
    }
}
