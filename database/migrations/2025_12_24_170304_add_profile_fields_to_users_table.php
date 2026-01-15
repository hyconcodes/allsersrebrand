<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('username')->unique()->nullable()->after('role');
            $table->string('profile_picture')->nullable()->after('username');
            $table->string('gender')->nullable()->after('profile_picture');
            $table->string('work')->nullable()->after('gender');
            $table->text('bio')->nullable()->after('work');
            $table->string('experience_year')->nullable()->after('bio');
            $table->string('work_status')->nullable()->comment('employed, unemployed, student, etc.')->after('experience_year');
            $table->string('phone_number')->nullable()->after('work_status');
            $table->string('address')->nullable()->after('phone_number');
            $table->decimal('latitude', 10, 8)->nullable()->after('address');
            $table->decimal('longitude', 11, 8)->nullable()->after('latitude');
            $table->string('status')->default('offline')->comment('online, offline')->after('longitude');
            $table->timestamp('last_activity')->nullable()->after('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'username',
                'profile_picture',
                'gender',
                'work',
                'bio',
                'experience_year',
                'work_status',
                'phone_number',
                'address',
                'latitude',
                'longitude',
                'status',
                'last_activity',
            ]);
        });
    }
};
