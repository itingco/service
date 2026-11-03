<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;

final class BusinessAnalyticsTargetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'scope_level' => ['required', 'in:TOTAL,COMPANY,LINE'],
            'source' => ['required', 'in:ALL,INGCO,SMI'],
            'dimension_key' => ['required', 'string', 'max:200'],
            'targets' => ['required', 'array', 'min:1', 'max:3'],
            'targets.*' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    /** @return array<string,?float> */
    public function normalizedTargets(): array
    {
        $targets = [];
        foreach ((array) $this->validated('targets', []) as $period => $value) {
            if (!preg_match('/^\d{4}-\d{2}$/', (string) $period)) {
                throw ValidationException::withMessages(['targets' => 'Periode target harus YYYY-MM.']);
            }
            $targets[(string) $period] = $value === null || $value === '' ? null : (float) $value;
        }
        return $targets;
    }
}
