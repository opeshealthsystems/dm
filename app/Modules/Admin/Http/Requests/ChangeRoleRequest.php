<?php

namespace App\Modules\Admin\Http\Requests;

class ChangeRoleRequest extends AdminFormRequest
{
    public function rules(): array
    {
        return ['role' => ['required', 'string', 'in:buyer,vendor']];
    }
}
