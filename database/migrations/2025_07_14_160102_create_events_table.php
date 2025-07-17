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
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('host_id')->constrained('users')->onDelete('cascade');
            $table->string('title');
            $table->text('description');
            $table->datetime('start_date');
            $table->datetime('end_date');
            $table->string('timezone')->default('UTC');
            $table->enum('location_type', ['physical', 'virtual', 'hybrid'])->default('physical');
            $table->string('location')->nullable();
            $table->string('virtual_link')->nullable();
            $table->integer('capacity')->nullable();
            $table->string('poster')->nullable();
            $table->json('agenda')->nullable();
            $table->json('tags')->nullable();
            $table->enum('status', ['draft', 'pending_approval', 'published', 'cancelled', 'completed'])->default('draft');
            $table->enum('visibility', ['public', 'private'])->default('public');
            $table->text('requirements')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->text('rejection_reason')->nullable();
            $table->timestamps();

            $table->index(['start_date', 'status']);
            $table->index(['status', 'visibility']);
            $table->index(['host_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};
