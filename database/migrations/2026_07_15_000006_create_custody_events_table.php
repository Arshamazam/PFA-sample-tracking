<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * APPEND-ONLY chain-of-custody trail. Every movement / status change of a sample
 * part is an immutable row here. Rows must never be updated or deleted (enforced
 * at the model level in Phase 2). No soft deletes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('custody_events', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('sample_part_id')->constrained('sample_parts');
            $table->string('status'); // the PartStatus this event moved the part into
            // Nullable for system-generated events.
            $table->foreignUlid('actor_id')->nullable()->constrained('users');
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('location_note')->nullable();
            // Required by service layer when the event's part is perishable (Phase 2).
            $table->decimal('temperature_c', 5, 2)->nullable();
            $table->string('photo_path')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('custody_events');
    }
};
