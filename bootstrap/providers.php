<?php

use App\Providers\AppServiceProvider;

return [
    AppServiceProvider::class,
    \App\Modules\Payments\Providers\PaymentsServiceProvider::class,
    \App\Modules\Admin\Providers\AdminServiceProvider::class,
    \App\Modules\Wallet\WalletServiceProvider::class,
    \App\Modules\Escrow\EscrowServiceProvider::class,
];
