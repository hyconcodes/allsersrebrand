<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->index('user_id');
            $table->index('created_at');
        });

        Schema::table('comments', function (Blueprint $table) {
            $table->index(['post_id', 'parent_id']);
            $table->index('user_id');
        });

        Schema::table('messages', function (Blueprint $table) {
            $table->index(['conversation_id', 'user_id']);
            $table->index('read_at');
        });

        Schema::table('reviews', function (Blueprint $table) {
            $table->index('artisan_id');
            $table->index('created_at');
        });

        Schema::table('likes', function (Blueprint $table) {
            $table->index('post_id');
            $table->index('user_id');
        });

        Schema::table('bookmarks', function (Blueprint $table) {
            $table->index('post_id');
            $table->index('user_id');
        });

        Schema::table('conversation_user', function (Blueprint $table) {
            $table->index('user_id');
            $table->index('conversation_id');
        });
    }

    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->dropIndex(['user_id']);
            $table->dropIndex(['created_at']);
        });

        Schema::table('comments', function (Blueprint $table) {
            $table->dropIndex(['post_id', 'parent_id']);
            $table->dropIndex(['user_id']);
        });

        Schema::table('messages', function (Blueprint $table) {
            $table->dropIndex(['conversation_id', 'user_id']);
            $table->dropIndex(['read_at']);
        });

        Schema::table('reviews', function (Blueprint $table) {
            $table->dropIndex(['artisan_id']);
            $table->dropIndex(['created_at']);
        });

        Schema::table('likes', function (Blueprint $table) {
            $table->dropIndex(['post_id']);
            $table->dropIndex(['user_id']);
        });

        Schema::table('bookmarks', function (Blueprint $table) {
            $table->dropIndex(['post_id']);
            $table->dropIndex(['user_id']);
        });

        Schema::table('conversation_user', function (Blueprint $table) {
            $table->dropIndex(['user_id']);
            $table->dropIndex(['conversation_id']);
        });
    }
};
