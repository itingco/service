<?php

declare(strict_types=1);

namespace App\Http\Requests\Security;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

final class StoreDashboardUserRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'username' => strtolower(trim((string) $this->input('username'))),
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
            'username' => ['required', 'string', 'max:100', 'alpha_dash', Rule::unique($connection.'.dashboard_users', 'username')],
            'password' => ['required', 'string', 'max:255', 'confirmed', Password::min(max(1, (int) config('dashboard_security.password_min_length', 8)))],
            'role' => ['required', Rule::exists($connection.'.dashboard_roles', 'role_key')],
        ];
    }
}
