<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Track how a dispute was filed (an officer on behalf of a walk-in FBO, or the FBO
 * themselves via the public site) and give every dispute a human reference number.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('disputes', function (Blueprint $table) {
            $table->string('source')->default('INTERNAL')->after('status'); // INTERNAL | PUBLIC
            $table->string('reference_no')->nullable()->unique()->after('source'); // D-{YYYY}-{seq}
        });
    }

    public function down(): void
    {
        Schema::table('disputes', function (Blueprint $table) {
            $table->dropColumn(['source', 'reference_no']);
        });
    }
};
