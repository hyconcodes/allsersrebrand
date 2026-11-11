<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('voice_call_language', 10)->default('en')->after('country_code');
            $table->string('voice_call_consent', 10)->nullable()->after('voice_call_language');
            $table->timestamp('voice_call_consent_at')->nullable()->after('voice_call_consent');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['voice_call_language', 'voice_call_consent', 'voice_call_consent_at']);
        });
    }
};
