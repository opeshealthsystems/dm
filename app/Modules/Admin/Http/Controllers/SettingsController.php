<?php

namespace App\Modules\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Admin\Actions\PlatformSettings;
use App\Modules\Admin\Http\Requests\SettingsRequest;
use Illuminate\Http\JsonResponse;

/**
 * @tags Admin: Settings
 */
class SettingsController extends Controller
{
    public function __construct(private PlatformSettings $settings) {}

    /** All platform settings with typed values (defaults applied). Requires `admin`. */
    public function show(): JsonResponse
    {
        $this->authorize('admin');

        return response()->json(['data' => $this->settings->all()]);
    }

    /**
     * Update one or more whitelisted settings. Requires `admin`.
     *
     * Body: `{"settings": {"site_name": "...", "maintenance_mode": true}}`. Unknown keys are rejected with 422.
     * Keys: site_name, support_email, default_currency, fee_rate_buyer_bps, fee_rate_vendor_bps, maintenance_mode.
     */
    public function update(SettingsRequest $request): JsonResponse
    {
        $this->authorize('admin');

        return response()->json(['data' => $this->settings->update($request->validated('settings'))]);
    }
}
