<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ping availability chain: attempt 1 at ping time, +3-minute callbacks
     * up to 3 attempts, then the pinger is told the artisan is
     * unavailable. answered_at / pinger_notified drive exactly-once
     * notifications and cancel further attempts.
     */
    public function up(): void
    {
        Schema::table('voice_announcements', function (Blueprint $table) {
            // Calls dialed so far (0 = row created, dial pending).
            $table->unsignedTinyInteger('attempt')->default(1);
            $table->timestamp('next_attempt_at')->nullable()->index();
            $table->timestamp('answered_at')->nullable();
            $table->string('pinger_notified', 20)->nullable();
            $table->timestamp('pinger_notified_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('voice_announcements', function (Blueprint $table) {
            $table->dropColumn([
                'attempt',
                'next_attempt_at',
                'answered_at',
                'pinger_notified',
                'pinger_notified_at',
            ]);
        });
    }
};
