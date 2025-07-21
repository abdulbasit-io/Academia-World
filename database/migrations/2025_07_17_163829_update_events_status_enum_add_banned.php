<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            // Drop indexes that depend on status column
            $table->dropIndex(['start_date', 'status']);
            $table->dropIndex(['status', 'visibility']);
            $table->dropIndex(['host_id', 'status']);
            
            // Drop the status column
            $table->dropColumn('status');
            
            // Add the new status column with 'banned' option
            $table->enum('status', ['draft', 'pending_approval', 'published', 'cancelled', 'completed', 'banned'])
                  ->default('draft')
                  ->after('registration_deadline');
            
            // Recreate indexes
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
        // First update any banned events to cancelled
        DB::table('events')->where('status', 'banned')->update(['status' => 'cancelled']);
        
        Schema::table('events', function (Blueprint $table) {
            // Drop indexes that depend on status column
            $table->dropIndex(['start_date', 'status']);
            $table->dropIndex(['status', 'visibility']);
            $table->dropIndex(['host_id', 'status']);
            
            // Drop the status column
            $table->dropColumn('status');
            
            // Add the status column without 'banned'
            $table->enum('status', ['draft', 'pending_approval', 'published', 'cancelled', 'completed'])
                  ->default('draft')
                  ->after('registration_deadline');
            
            // Recreate indexes
            $table->index(['start_date', 'status']);
            $table->index(['status', 'visibility']);
            $table->index(['host_id', 'status']);
        });
    }
};
