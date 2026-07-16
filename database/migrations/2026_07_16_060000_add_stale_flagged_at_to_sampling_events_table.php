<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds a flag timestamp used by `sampling:prune-drafts` to mark draft events that
 * were never finalized within 24h. We flag rather than delete so the abandoned
 * draft (and any collected parts) remain auditable.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sampling_events', function (Blueprint $table) {
            $table->timestamp('stale_flagged_at')->nullable()->after('finalized_at');
        });
    }

    public function down(): void
    {
        Schema::table('sampling_events', function (Blueprint $table) {
            $table->dropColumn('stale_flagged_at');
        });
    }
};
