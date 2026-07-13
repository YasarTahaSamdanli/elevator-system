<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inspection_imports', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->enum('source', ['email', 'upload'])->default('email');
            $table->enum('status', ['pending', 'imported', 'needs_review', 'ignored', 'failed'])->default('pending');
            // Machine-readable code for why the import needs a human:
            // parse_failed | no_text_layer | elevator_not_found | multiple_matches | duplicate_report
            $table->string('review_reason', 50)->nullable();
            $table->text('error_message')->nullable();
            // Auto work order guard failure (no active contract, ...). The
            // import itself still counts as imported; this surfaces in the UI.
            $table->string('work_order_error', 500)->nullable();
            $table->string('message_id', 998)->nullable();
            $table->string('mail_from')->nullable();
            $table->string('mail_subject', 500)->nullable();
            $table->timestamp('mail_received_at')->nullable();
            $table->string('pdf_disk', 50)->default('local');
            $table->string('pdf_path', 500);
            $table->char('pdf_sha256', 64);
            $table->string('original_filename', 500)->nullable();
            $table->string('report_number', 100)->nullable();
            $table->json('parsed_payload')->nullable();
            $table->foreignId('elevator_id')->nullable()->constrained('elevators')->nullOnDelete();
            $table->foreignId('elevator_inspection_id')->nullable()->constrained('elevator_inspections')->nullOnDelete();
            // How the elevator was resolved: mapping | building_name | registration_number | serial_number | manual
            $table->string('matched_via', 50)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            // Checksum is the hard dedupe guard; message_id is only indexed
            // because one mail may legitimately carry several report PDFs.
            $table->unique(['company_id', 'pdf_sha256']);
            $table->index('message_id');
            $table->index('status');
            $table->index('mail_received_at');
            $table->index('deleted_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inspection_imports');
    }
};
