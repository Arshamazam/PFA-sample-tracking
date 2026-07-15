<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Food business premises. This table is a local cache / fallback. After integration
 * with PFA's ~400k registered business database, records may be sourced from there
 * (source = 'PFA_DB') instead of manual entry.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('premises', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('license_no')->unique()->index(); // public lookup key
            $table->string('name');
            $table->string('address');
            $table->string('city');
            $table->string('owner_name')->nullable();
            $table->string('owner_phone')->nullable();
            $table->string('source')->default('MANUAL'); // MANUAL | PFA_DB
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('premises');
    }
};
