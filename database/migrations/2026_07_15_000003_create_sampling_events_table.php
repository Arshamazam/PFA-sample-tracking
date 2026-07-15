<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A single legal sampling event at a premises. Produces exactly three sample parts
 * (the "Rule of Three"); finalized_at is set only once all three parts exist.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sampling_events', function (Blueprint $table) {
            $table->ulid('id')->primary();
            // Format: PFA-{DISTRICT}-{YYYY}-{6-digit sequence}, e.g. PFA-LHR-2026-000123
            $table->string('event_code')->unique()->index();
            $table->foreignUlid('premises_id')->constrained('premises');
            $table->foreignUlid('fso_id')->constrained('users');
            $table->string('food_item');
            $table->string('brand_name')->nullable();
            $table->boolean('is_perishable')->default(false);
            $table->string('witness_name');
            $table->string('witness_cnic')->nullable();
            $table->string('witness_signature_path')->nullable();
            $table->dateTime('collected_at');
            // Set only when the Rule of Three is satisfied (all 3 parts created).
            $table->dateTime('finalized_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sampling_events');
    }
};
