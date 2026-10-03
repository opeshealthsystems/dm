<?php

namespace App\Modules\Admin\Http\Requests;

/** Force-archiving a product requires a reason. */
class ArchiveProductRequest extends AdminFormRequest
{
    public function rules(): array
    {
        return ['reason' => ['required', 'string', 'min:3', 'max:500']];
    }
}
