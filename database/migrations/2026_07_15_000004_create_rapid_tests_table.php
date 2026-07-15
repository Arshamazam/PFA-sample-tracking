<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * On-site rapid screening tests. A passing rapid test may exist standalone (tied
 * only to a premises) without a formal sampling event; a failing test typically
 * triggers formal sampling and is then linked to the sampling event.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rapid_tests', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('sampling_event_id')->nullable()->constrained('sampling_events');
            $table->foreignUlid('premises_id')->constrained('premises');
            $table->foreignUlid('fso_id')->constrained('users');
            $table->string('device'); // RapidTestDevice
            $table->string('reading');
            $table->boolean('passed');
            $table->string('photo_path')->nullable();
            $table->dateTime('tested_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rapid_tests');
    }
};
