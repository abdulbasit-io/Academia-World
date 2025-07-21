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
        // Only drop column if it exists
        if (Schema::hasColumn('users', 'account_status')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropIndex(['account_status']); // Drop index first
                $table->dropColumn('account_status');
            });
        }
        
        // Add the column with new enum values
        Schema::table('users', function (Blueprint $table) {
            $table->enum('account_status', ['pending', 'active', 'suspended', 'banned', 'admin'])->default('pending')->after('social_links');
            $table->index('account_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('account_status');
        });
        
        Schema::table('users', function (Blueprint $table) {
            $table->enum('account_status', ['pending', 'active', 'suspended', 'banned'])->default('pending')->after('social_links');
        });
    }
};
