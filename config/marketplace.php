<?php

return [
    /*
    | Legacy `platform_fee_percent` defaulted to 5% (0.05); stored as basis points.
    | Used when no vendor_fee_tiers row matches.
    */
    'default_commission_bps' => (int) env('MARKETPLACE_COMMISSION_BPS', 500),

    /* Legacy VENDOR_ENTRY_FEE default: 500 (in the marketplace currency), non-refundable bond. */
    'vendor_bond_cents' => (int) env('MARKETPLACE_VENDOR_BOND_CENTS', 50000),
    'vendor_bond_currency' => env('MARKETPLACE_VENDOR_BOND_CURRENCY', 'EUR'),
];
