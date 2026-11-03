<?php

declare(strict_types=1);
namespace App\Http\Requests\BusinessAnalytics;
use Illuminate\Foundation\Http\FormRequest;
final class AdvisorResearchRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array { return ['review_id'=>['required','integer','min:1']]; }
}
