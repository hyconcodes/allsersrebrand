<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->text('banned_reason')->nullable()->after('banned_until');
            $table->foreignId('banned_by')->nullable()->after('banned_reason')->constrained('users')->nullOnDelete();
            $table->timestamp('banned_at')->nullable()->after('banned_by');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['banned_by']);
            $table->dropColumn(['banned_reason', 'banned_by', 'banned_at']);
        });
    }
};
