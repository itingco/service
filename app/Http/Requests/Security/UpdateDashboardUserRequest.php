<?php

declare(strict_types=1);

namespace App\Http\Requests\Security;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateDashboardUserRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => trim((string) $this->input('name')),
            'role' => strtolower(trim((string) $this->input('role'))),
        ]);
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $connection = (string) config('dashboard_security.connection', 'sqlite_targets');

        return [
            'name' => ['required', 'string', 'max:120'],
            'role' => ['required', Rule::exists($connection.'.dashboard_roles', 'role_key')],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
