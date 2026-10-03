<?php

namespace App\Modules\Admin\Support;

/** Whitelist of platform settings: key => type, default and validation rules. */
class SettingsRegistry
{
    /** @return array<string, array{type:string, default:mixed, rules:list<string>}> */
    public static function definitions(): array
    {
        return [
            'site_name' => ['type' => 'string', 'default' => config('app.name'), 'rules' => ['string', 'min:1', 'max:100']],
            'support_email' => ['type' => 'string', 'default' => null, 'rules' => ['nullable', 'email', 'max:190']],
            'default_currency' => ['type' => 'string', 'default' => 'EUR', 'rules' => ['string', 'regex:/^[A-Z]{3}$/']],
            // Placeholders: basis points (100 = 1%). Not applied to orders yet.
            'fee_rate_buyer_bps' => ['type' => 'int', 'default' => 0, 'rules' => ['integer', 'min:0', 'max:10000']],
            'fee_rate_vendor_bps' => ['type' => 'int', 'default' => 0, 'rules' => ['integer', 'min:0', 'max:10000']],
            'maintenance_mode' => ['type' => 'bool', 'default' => false, 'rules' => ['boolean']],
        ];
    }

    /** @return list<string> */
    public static function keys(): array
    {
        return array_keys(self::definitions());
    }
}
