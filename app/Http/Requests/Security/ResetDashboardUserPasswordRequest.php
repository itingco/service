<?php

declare(strict_types=1);

namespace App\Http\Requests\Security;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

final class ResetDashboardUserPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'password' => ['required', 'string', 'max:255', 'confirmed', Password::min(max(1, (int) config('dashboard_security.password_min_length', 8)))],
        ];
    }
}
