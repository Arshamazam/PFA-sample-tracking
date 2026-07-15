<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Analytical result for a sample part. One result per part (unique). Analyst enters
 * parameters/verdict; a verifying officer confirms before the report is issued.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lab_results', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('sample_part_id')->unique()->constrained('sample_parts');
            $table->string('lab_section'); // LabSection
            $table->foreignUlid('analyst_id')->nullable()->constrained('users');
            $table->foreignUlid('verified_by_id')->nullable()->constrained('users');
            // Array of {name, value, unit, permissible_limit, within_limit}.
            $table->json('parameters')->nullable();
            $table->string('verdict')->nullable(); // Verdict: FIT | UNFIT
            $table->dateTime('verdict_at')->nullable();
            $table->string('report_pdf_path')->nullable();
            $table->string('report_photo_path')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lab_results');
    }
};
