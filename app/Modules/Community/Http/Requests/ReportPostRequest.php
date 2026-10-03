<?php

namespace App\Modules\Community\Http\Requests;

use App\Modules\Community\Models\CommunityReport;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReportPostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'reason' => ['required', Rule::in(CommunityReport::REASONS)],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }
}
