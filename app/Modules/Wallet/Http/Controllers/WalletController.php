<?php

namespace App\Modules\Wallet\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Escrow\Actions\FeeSchedule;
use App\Modules\Wallet\Actions\WalletStats;
use App\Modules\Wallet\Http\Resources\WalletEntryResource;
use App\Modules\Wallet\Models\WalletEntry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class WalletController extends Controller
{
    /**
     * Your wallet.
     *
     * Per-currency available balance (integer cents), total sales, commission paid, funds still in
     * escrow and payouts requested. Requires `vendor:manage`. Port of the legacy wallet stats.
     */
    public function show(Request $request, WalletStats $stats, FeeSchedule $fees): JsonResponse
    {
        $user = $request->user();
        $wallets = $stats->for($user);
        foreach ($wallets as $currency => &$w) {
            $w['commission_bps'] = $fees->commissionBps($w['total_sales_cents']);
        }

        return response()->json(['data' => (object) $wallets]);
    }

    /**
     * Your ledger.
     *
     * Append-only list of credits (sales), commission deductions and payout debits, newest first.
     * Filters: `type`, `currency`, `date_from`, `date_to` (YYYY-MM-DD). Paginated.
     */
    public function entries(Request $request): AnonymousResourceCollection
    {
        $request->validate([
            'type' => ['nullable', 'string', 'in:' . implode(',', [WalletEntry::SALE_CREDIT, WalletEntry::PLATFORM_FEE, WalletEntry::PAYOUT_DEBIT, WalletEntry::PAYOUT_REVERSAL])],
            'currency' => ['nullable', 'string', 'size:3'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
        ]);

        $q = WalletEntry::where('user_id', $request->user()->id)
            ->when($request->query('type'), fn ($q, $v) => $q->where('type', $v))
            ->when($request->query('currency'), fn ($q, $v) => $q->where('currency', strtoupper($v)))
            ->when($request->query('date_from'), fn ($q, $v) => $q->where('created_at', '>=', $v . ' 00:00:00'))
            ->when($request->query('date_to'), fn ($q, $v) => $q->where('created_at', '<=', $v . ' 23:59:59'))
            ->orderByDesc('id');

        return WalletEntryResource::collection($q->paginate(min($request->integer('per_page', 20), 100)));
    }
}
