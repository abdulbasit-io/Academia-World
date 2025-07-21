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
        Schema::create('forum_posts', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('forum_id')->constrained('discussion_forums')->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('parent_id')->nullable()->constrained('forum_posts')->onDelete('cascade');
            $table->text('content');
            $table->boolean('is_pinned')->default(false);
            $table->boolean('is_solution')->default(false);
            $table->boolean('is_moderated')->default(false);
            $table->integer('likes_count')->default(0);
            $table->integer('replies_count')->default(0);
            $table->timestamp('edited_at')->nullable();
            $table->foreignId('edited_by')->nullable()->constrained('users');
            $table->softDeletes();
            $table->timestamps();

            $table->index(['forum_id', 'created_at']);
            $table->index(['forum_id', 'is_pinned', 'created_at']);
            $table->index(['parent_id', 'created_at']);
            $table->index(['user_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('forum_posts');
    }
};
