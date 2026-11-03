<?php

declare(strict_types=1);

namespace App\Http\Requests\Security;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateDashboardDeviceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['label' => ['required', 'string', 'max:120']];
    }
}
