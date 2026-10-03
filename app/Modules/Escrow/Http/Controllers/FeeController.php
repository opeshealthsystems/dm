<?php

namespace App\Modules\Escrow\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Escrow\Exceptions\EscrowException;
use App\Modules\Escrow\Http\Requests\StoreFeeTierRequest;
use App\Modules\Escrow\Http\Requests\StoreVendorFeeRequest;
use App\Modules\Escrow\Models\VendorFee;
use App\Modules\Escrow\Models\VendorFeeTier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Vendor fees (entry bond) and the commission schedule. Port of legacy FeeController / VendorFee. */
class FeeController extends Controller
{
    private function present(VendorFee $f): array
    {
        return [
            'id' => $f->id, 'fee_type' => $f->fee_type, 'amount_cents' => $f->amount_cents,
            'currency' => $f->currency, 'status' => $f->status, 'description' => $f->description,
            'paid_at' => $f->paid_at?->toIso8601String(),
        ];
    }

    /** Your fee history and whether your vendor bond is active. Requires `vendor:manage`. */
    public function index(Request $request): JsonResponse
    {
        $fees = VendorFee::where('vendor_id', $request->user()->id)->latest('id')->get();

        return response()->json([
            'bond_paid' => $fees->contains(fn ($f) => $f->status === VendorFee::PAID),
            'data' => $fees->map(fn ($f) => $this->present($f))->values(),
        ]);
    }

    /**
     * Create the vendor bond fee (unpaid).
     *
     * Legacy: a single non-refundable bond (`entry_fee`, 500 by default). Fails when the bond is already
     * paid or an unpaid one is already open. An admin marks it paid once the payment is received.
     * Requires `vendor:manage`.
     */
    public function store(StoreVendorFeeRequest $request): JsonResponse
    {
        $user = $request->user();

        $fee = DB::transaction(function () use ($user, $request) {
            // Serialise per vendor so two requests cannot both create an open bond.
            \App\Models\User::whereKey($user->id)->lockForUpdate()->first();
            $existing = VendorFee::where('vendor_id', $user->id)->whereIn('fee_type', VendorFee::TYPES)->get();
            if ($existing->contains(fn ($f) => $f->status === VendorFee::PAID)) {
                throw new EscrowException('Your vendor bond is already active.');
            }
            if ($existing->contains(fn ($f) => $f->status === VendorFee::UNPAID)) {
                throw new EscrowException('You already have an unpaid vendor bond.');
            }
            $fee = new VendorFee();
            $fee->forceFill([
                'vendor_id' => $user->id,
                'fee_type' => $request->validated('fee_type', VendorFee::ENTRY_FEE),
                'amount_cents' => config('marketplace.vendor_bond_cents'),
                'currency' => config('marketplace.vendor_bond_currency'),
                'status' => VendorFee::UNPAID,
                'description' => 'Pending payment for Marketplace Vendor Bond (non-refundable)',
            ])->save();

            return $fee->refresh();
        });

        return response()->json(['data' => $this->present($fee)], 201);
    }

    /** Public-to-vendors view of the commission schedule: tiers plus the default rate. Requires `vendor:manage`. */
    public function schedule(): JsonResponse
    {
        return response()->json([
            'default_commission_bps' => (int) config('marketplace.default_commission_bps'),
            'tiers' => VendorFeeTier::orderBy('min_sales_cents')->get(['id', 'name', 'min_sales_cents', 'commission_bps']),
        ]);
    }

    /** Admin: create a commission tier. Requires `admin`. */
    public function storeTier(StoreFeeTierRequest $request): JsonResponse
    {
        $tier = VendorFeeTier::create($request->validated());

        return response()->json(['data' => $tier], 201);
    }

    /** Admin: delete a commission tier. Past ledger entries are unaffected. Requires `admin`. */
    public function destroyTier(Request $request, VendorFeeTier $tier): JsonResponse
    {
        abort_unless($request->user()->isAdmin(), 403);
        $tier->delete();

        return response()->json(null, 204);
    }

    /** Admin: list vendor fees, optionally `status=unpaid|paid`. Requires `admin`. */
    public function adminIndex(Request $request): JsonResponse
    {
        abort_unless($request->user()->isAdmin(), 403);
        $fees = VendorFee::when($request->query('status'), fn ($q, $s) => $q->where('status', $s))->latest('id')->limit(200)->get();

        return response()->json(['data' => $fees->map(fn ($f) => $this->present($f) + ['vendor_id' => $f->vendor_id])]);
    }

    /** Admin: confirm a vendor fee was paid (idempotent; keeps the first paid_at). Requires `admin`. */
    public function markPaid(Request $request, VendorFee $fee): JsonResponse
    {
        abort_unless($request->user()->isAdmin(), 403);

        $fee = DB::transaction(function () use ($fee) {
            $locked = VendorFee::whereKey($fee->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== VendorFee::PAID) {
                $locked->forceFill(['status' => VendorFee::PAID, 'paid_at' => now()])->save();
            }

            return $locked->refresh();
        });

        return response()->json(['data' => $this->present($fee)]);
    }
}
