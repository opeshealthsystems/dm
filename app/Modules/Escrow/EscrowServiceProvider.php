<?php

namespace App\Modules\Escrow;

use App\Modules\Escrow\Models\Dispute;
use App\Modules\Escrow\Policies\DisputePolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/** Wires the Escrow module (disputes + vendor fees): policies and routes. */
class EscrowServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Gate::policy(Dispute::class, DisputePolicy::class);

        Route::middleware('api')->prefix('api')->group(__DIR__ . '/routes.php');
    }
}
