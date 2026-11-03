<?php

declare(strict_types=1);

namespace App\Http\Requests\Security;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateDashboardUserRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $salesPersonId = $this->input('sales_person_id');

        $this->merge([
            'name' => trim((string) $this->input('name')),
            'role' => strtolower(trim((string) $this->input('role'))),
            'sales_person_id' => $salesPersonId === null || $salesPersonId === '' ? null : (int) $salesPersonId,
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
            'sales_person_id' => [
                Rule::requiredIf(fn (): bool => strtolower((string) $this->input('role')) === 'sales'),
                'nullable',
                'integer',
                'min:1',
            ],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'sales_person_id.required' => 'User dengan role Sales wajib dipetakan ke Sales Person ERP.',
        ];
    }
}
