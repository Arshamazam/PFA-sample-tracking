<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Food category (e.g. MILK, OIL_GHEE, WATER, SPICES) chosen by the FSO at
 * collection. Drives lab-section auto-suggestion via test_catalog and is part of
 * the blind, analyst-facing view of a sample.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sampling_events', function (Blueprint $table) {
            $table->string('food_category')->nullable()->index()->after('food_item');
        });
    }

    public function down(): void
    {
        Schema::table('sampling_events', function (Blueprint $table) {
            $table->dropColumn('food_category');
        });
    }
};
