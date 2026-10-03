<?php

namespace App\Modules\Admin\Http\Requests;

use App\Modules\Admin\Support\SettingsRegistry;

class SettingsRequest extends AdminFormRequest
{
    public function rules(): array
    {
        $rules = ['settings' => ['required', 'array', 'min:1']];
        foreach (SettingsRegistry::definitions() as $key => $def) {
            $rules["settings.$key"] = array_merge(['sometimes'], $def['rules']);
        }

        return $rules;
    }

    /** Reject keys outside the whitelist. */
    public function after(): array
    {
        return [function ($validator) {
            $unknown = array_diff(array_keys((array) $this->input('settings', [])), SettingsRegistry::keys());
            foreach ($unknown as $key) {
                $validator->errors()->add("settings.$key", "Unknown setting [$key].");
            }
        }];
    }
}
