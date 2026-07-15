<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One of the three physical parts of a sampling event. Each part carries its own
 * QR token and tamper seal. The LAB part (and an activated REFERENCE part) also
 * carries a blind_code so analysts never see the business identity.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sample_parts', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('sampling_event_id')->constrained('sampling_events');
            $table->string('role'); // PartRole: LAB | REFERENCE | FBO_COPY
            $table->string('qr_token')->unique()->index(); // random 32-char token in QR URL
            // Only for LAB role or an activated REFERENCE part (blind testing).
            $table->string('blind_code')->nullable()->unique()->index();
            $table->string('seal_number');
            $table->string('seal_photo_path');
            $table->string('status')->index(); // denormalized current PartStatus
            $table->timestamps();

            // Exactly one part per role per sampling event.
            $table->unique(['sampling_event_id', 'role']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sample_parts');
    }
};
