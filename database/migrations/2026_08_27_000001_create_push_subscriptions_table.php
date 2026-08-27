<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use NotificationChannels\WebPush\PushSubscription;

return new class extends Migration
{
    public function up(): void
    {
        $connection = config('webpush.database_connection');
        $tableName = config('webpush.table_name');
        Schema::connection($connection)->create($tableName, function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->morphs('subscribable', 'push_subscriptions_subscribable_morph_idx');
            $table->string('endpoint', PushSubscription::ENDPOINT_MAX_LENGTH)->charset('ascii')->unique();
            $table->string('public_key')->nullable();
            $table->string('auth_token')->nullable();
            $table->string('content_encoding')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        $connection = config('webpush.database_connection');
        $tableName = config('webpush.table_name');
        Schema::connection($connection)->dropIfExists($tableName);
    }
};
