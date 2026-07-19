<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The deciding officer's mandatory notes when accepting or rejecting a dispute
 * (distinct from `reason`, which is the FBO's filing reason).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('disputes', function (Blueprint $table) {
            $table->text('decision_notes')->nullable()->after('reason');
        });
    }

    public function down(): void
    {
        Schema::table('disputes', function (Blueprint $table) {
            $table->dropColumn('decision_notes');
        });
    }
};
