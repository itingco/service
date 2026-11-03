<?php

declare(strict_types=1);

namespace App\Http\Requests\Security;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

final class ChangeOwnPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $minimum = max(1, (int) config('dashboard_security.password_min_length', 8));

        return [
            'current_password' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'max:255', 'confirmed', Password::min($minimum)],
        ];
    }

    public function messages(): array
    {
        $minimum = max(1, (int) config('dashboard_security.password_min_length', 8));

        return [
            'current_password.required' => 'Password lama wajib diisi.',
            'password.required' => 'Password baru wajib diisi.',
            'password.confirmed' => 'Konfirmasi password baru tidak sama.',
            'password.min' => "Password baru minimal {$minimum} karakter.",
        ];
    }
}
