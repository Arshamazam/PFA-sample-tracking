<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Audit log of every SMS send attempt (success and failure), for accountability and
 * troubleshooting the gateway.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sms_logs', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('to');
            $table->text('message');
            $table->string('driver');
            $table->string('status')->index();       // SENT | FAILED
            $table->string('provider_message_id')->nullable();
            $table->text('error')->nullable();
            $table->string('trigger')->nullable();    // which notification produced it
            $table->dateTime('sent_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sms_logs');
    }
};
