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
        Schema::create('discussion_forums', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('event_id')->constrained()->onDelete('cascade');
            $table->string('title');
            $table->text('description')->nullable();
            $table->enum('type', ['general', 'q_and_a', 'networking', 'feedback', 'technical']);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_moderated')->default(false);
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            $table->integer('post_count')->default(0);
            $table->integer('participant_count')->default(0);
            $table->timestamp('last_activity_at')->nullable();
            $table->timestamps();

            $table->index(['event_id', 'type']);
            $table->index(['event_id', 'is_active']);
            $table->index('last_activity_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('discussion_forums');
    }
};
