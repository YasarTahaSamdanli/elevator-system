<?php

namespace App\Console\Commands;

use App\Services\MaintenanceWorkOrderService;
use Carbon\Exceptions\InvalidFormatException;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class GenerateMaintenanceWorkOrders extends Command
{
    /**
     * @var string
     */
    protected $signature = 'work-orders:generate-maintenance {--month= : Target month as YYYY-MM (defaults to the current month)}';

    /**
     * @var string
     */
    protected $description = 'Open the monthly maintenance work order for every active service contract (idempotent per contract per month)';

    public function handle(MaintenanceWorkOrderService $service): int
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

        $created = $service->generateForMonth($month);

        $this->info("Created {$created} maintenance work order(s) for {$month->format('Y-m')}.");

        return self::SUCCESS;
    }
}
