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
        Schema::create('event_resources', function (Blueprint $table) {
            $table->id();
            $table->string('uuid')->unique();
            $table->foreignId('event_id')->constrained()->onDelete('cascade');
            $table->foreignId('uploaded_by')->constrained('users')->onDelete('cascade');
            
            // File information
            $table->string('title')->nullable();
            $table->text('description')->nullable();
            $table->string('filename'); // Internal filename
            $table->string('original_filename'); // User's original filename
            $table->string('file_path'); // Storage path
            $table->string('file_type', 10); // pdf, doc, ppt, etc.
            $table->string('mime_type');
            $table->bigInteger('file_size'); // Size in bytes
            
            // Resource categorization
            $table->enum('resource_type', ['presentation', 'paper', 'recording', 'agenda', 'other'])->default('other');
            
            // Access control
            $table->boolean('is_public')->default(false); // Public or registered-only
            $table->boolean('is_downloadable')->default(true);
            $table->boolean('requires_registration')->default(true); // Must be registered for event
            
            // Analytics
            $table->integer('download_count')->default(0);
            $table->integer('view_count')->default(0);
            
            // Status
            $table->enum('status', ['active', 'hidden', 'archived'])->default('active');
            
            // Timestamps
            $table->timestamps();
            
            // Indexes
            $table->index(['event_id', 'status']);
            $table->index(['uploaded_by']);
            $table->index(['resource_type']);
            $table->index(['is_public', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('event_resources');
    }
};
