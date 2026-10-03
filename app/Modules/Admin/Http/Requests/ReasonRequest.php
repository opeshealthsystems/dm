<?php

namespace App\Modules\Admin\Http\Requests;

/** Optional moderation reason (suspend / restore). */
class ReasonRequest extends AdminFormRequest
{
    public function rules(): array
    {
        return ['reason' => ['nullable', 'string', 'min:3', 'max:500']];
    }
}
