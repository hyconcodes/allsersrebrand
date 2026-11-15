<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Connected seconds per attempt (from call.completed / no_answer
     * payloads) — shown as "answered · 14s" in the admin ping detail.
     */
    public function up(): void
    {
        Schema::table('voice_announcements', function (Blueprint $table) {
            $table->unsignedSmallInteger('duration_seconds')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('voice_announcements', function (Blueprint $table) {
            $table->dropColumn('duration_seconds');
        });
    }
};
