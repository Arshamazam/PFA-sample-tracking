<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * FBO dispute against an UNFIT verdict, filed within the configurable dispute window.
 * When accepted, the REFERENCE part is activated for retest and its result is linked
 * back here via retest_lab_result_id.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('disputes', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('sampling_event_id')->constrained('sampling_events');
            $table->string('filed_by_name');
            $table->string('filed_by_phone');
            $table->string('filed_by_cnic')->nullable();
            $table->text('reason')->nullable();
            $table->string('status')->index(); // DisputeStatus
            $table->dateTime('filed_at');
            $table->foreignUlid('decided_by_id')->nullable()->constrained('users');
            $table->dateTime('decided_at')->nullable();
            $table->foreignUlid('retest_lab_result_id')->nullable()->constrained('lab_results');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('disputes');
    }
};
