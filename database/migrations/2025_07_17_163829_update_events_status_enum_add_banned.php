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
        DB::statement("ALTER TABLE events MODIFY COLUMN status ENUM('draft', 'pending_approval', 'published', 'cancelled', 'completed', 'banned') DEFAULT 'draft'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // First update any banned events to cancelled
        DB::table('events')->where('status', 'banned')->update(['status' => 'cancelled']);
        
        // Then remove banned from enum
        DB::statement("ALTER TABLE events MODIFY COLUMN status ENUM('draft', 'pending_approval', 'published', 'cancelled', 'completed') DEFAULT 'draft'");
    }
};
