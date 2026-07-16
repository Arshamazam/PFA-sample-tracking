<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SOP deviations detected during the chain of custody (e.g. a late/next-day sample
 * transfer or a cold-chain temperature breach). Violations are recorded for audit
 * but do not by themselves block the workflow; an admin can later resolve them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sop_violations', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('sample_part_id')->constrained('sample_parts');
            $table->string('type')->index(); // SAME_DAY_TRANSFER | COLD_CHAIN_BREACH | OTHER
            $table->json('details')->nullable();
            $table->dateTime('detected_at');
            $table->dateTime('resolved_at')->nullable();
            $table->foreignUlid('resolved_by_id')->nullable()->constrained('users');
            $table->text('resolution_notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sop_violations');
    }
};
