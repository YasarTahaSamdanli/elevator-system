<?php

namespace Database\Factories;

use App\Models\InspectionImport;
use App\Models\PrintJob;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PrintJob>
 */
class PrintJobFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'inspection_import_id' => InspectionImport::factory(),
            'purpose' => 'inspection_report',
            'status' => 'pending',
            'attempts' => 0,
        ];
    }

    /**
     * @return $this
     */
    public function configure(): static
    {
        return $this->afterMaking(function (PrintJob $job): void {
            $job->company_id ??= InspectionImport::withoutGlobalScopes()->find($job->inspection_import_id)?->company_id;
        });
    }
}
