<?php

declare(strict_types=1);

namespace App\Http\Requests\Security;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreDashboardRoleRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => trim((string) $this->input('name')),
            'menus' => array_values(array_filter((array) $this->input('menus', []), 'is_string')),
        ]);
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'menus' => ['nullable', 'array'],
            'menus.*' => ['string', Rule::in(array_keys((array) config('dashboard_security.menus', [])))],
        ];
    }
}
