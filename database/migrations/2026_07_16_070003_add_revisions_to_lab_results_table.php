<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Prior versions of a lab result's parameters. When an analyst re-submits results
 * (allowed only while RESULT_ENTERED) the previous parameters snapshot is appended
 * here so the history is preserved.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lab_results', function (Blueprint $table) {
            $table->json('lab_result_revisions')->nullable()->after('parameters');
        });
    }

    public function down(): void
    {
        Schema::table('lab_results', function (Blueprint $table) {
            $table->dropColumn('lab_result_revisions');
        });
    }
};
