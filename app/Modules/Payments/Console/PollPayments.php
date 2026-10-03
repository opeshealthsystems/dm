<?php

namespace App\Modules\Payments\Console;

use App\Modules\Payments\Actions\PaymentService;
use Illuminate\Console\Command;

class PollPayments extends Command
{
    protected $signature = 'payments:poll';

    protected $description = 'Check the chain for every open payment and mark paid orders (replaces the legacy payment cron).';

    public function handle(PaymentService $payments): int
    {
        $n = 0;
        $payments->pollable()->orderBy('id')->each(function ($payment) use ($payments, &$n) {
            $payments->poll($payment);
            $n++;
        });
        $this->info("Polled $n payment(s).");

        return self::SUCCESS;
    }
}
