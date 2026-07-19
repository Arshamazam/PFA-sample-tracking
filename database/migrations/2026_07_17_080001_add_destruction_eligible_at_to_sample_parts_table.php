<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * When set (and past), the REFERENCE part is eligible for manual destruction.
 * Populated by the `retention:process` command once the dispute window has closed
 * (or the verdict was FIT). Destruction itself is always a manual custody event.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sample_parts', function (Blueprint $table) {
            $table->dateTime('destruction_eligible_at')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('sample_parts', function (Blueprint $table) {
            $table->dropColumn('destruction_eligible_at');
        });
    }
};
