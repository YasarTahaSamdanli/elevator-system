<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\InspectionImport;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<InspectionImport>
 */
class InspectionImportFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $uuid = (string) Str::uuid();

        return [
            'company_id' => Company::factory(),
            'source' => 'email',
            'status' => 'pending',
            'message_id' => '<'.Str::random(24).'@royalcert.com>',
            'mail_from' => 'rapor@royalcert.com',
            'mail_subject' => fake()->streetName().' Asansör Denetim Raporu',
            'mail_received_at' => fake()->dateTimeBetween('-1 month', 'now'),
            'pdf_disk' => 'local',
            'pdf_path' => "inspection-imports/1/2026/07/{$uuid}.pdf",
            'pdf_sha256' => hash('sha256', $uuid),
            'original_filename' => fake()->slug(3).'.pdf',
        ];
    }

    public function upload(): static
    {
        return $this->state(fn () => [
            'source' => 'upload',
            'message_id' => null,
            'mail_from' => null,
            'mail_subject' => null,
            'mail_received_at' => null,
        ]);
    }

    public function needsReview(string $reason = InspectionImport::REVIEW_ELEVATOR_NOT_FOUND): static
    {
        return $this->state(fn () => [
            'status' => 'needs_review',
            'review_reason' => $reason,
        ]);
    }
}
