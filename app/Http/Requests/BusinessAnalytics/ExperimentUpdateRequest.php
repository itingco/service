<?php

declare(strict_types=1);
namespace App\Http\Requests\BusinessAnalytics;
use Illuminate\Foundation\Http\FormRequest;
final class ExperimentUpdateRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array { return ['status'=>['required','in:DRAFT,RUNNING,COMPLETED,SCALED,MODIFIED,STOPPED']]; }
}
