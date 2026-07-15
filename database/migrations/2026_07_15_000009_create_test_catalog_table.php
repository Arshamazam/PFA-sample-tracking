<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Catalog of tests per food category, with default lab-section routing and a
 * parameter template (names, units, permissible limits) plus expected turnaround.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('test_catalog', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('food_category')->index(); // MILK | OIL_GHEE | WATER | SPICES | MEAT ...
            $table->string('lab_section'); // LabSection — default routing target
            $table->string('test_name');
            // Template of parameter names, units, permissible limits.
            $table->json('parameters');
            $table->integer('tat_hours'); // expected turnaround time
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('test_catalog');
    }
};
