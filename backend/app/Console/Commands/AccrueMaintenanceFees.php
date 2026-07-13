<?php

namespace App\Console\Commands;

use App\Services\LedgerService;
use Carbon\Exceptions\InvalidFormatException;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class AccrueMaintenanceFees extends Command
{
    /**
     * @var string
     */
    protected $signature = 'ledger:accrue-maintenance {--month= : Target month as YYYY-MM (defaults to the current month)}';

    /**
     * @var string
     */
    protected $description = 'Post the monthly maintenance fee to every active service contract\'s customer ledger (idempotent per contract per month)';

    public function handle(LedgerService $ledger): int
    {
        $option = $this->option('month');

        try {
            $month = $option === null
                ? Carbon::now()->startOfMonth()
                : Carbon::createFromFormat('Y-m', $option)?->startOfMonth();
        } catch (InvalidFormatException) {
            $month = null;
        }

        if ($month === false || $month === null) {
            $this->error('Invalid --month value; expected YYYY-MM (e.g. 2026-07).');

            return self::FAILURE;
        }

        $created = $ledger->accrueMonthlyFees($month);

        $this->info("Created {$created} maintenance fee accrual(s) for {$month->format('Y-m')}.");

        return self::SUCCESS;
    }
}
