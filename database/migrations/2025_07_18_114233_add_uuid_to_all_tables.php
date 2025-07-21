<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add UUID columns to existing tables
        $tables = ['users', 'events', 'event_registrations'];
        
        foreach ($tables as $table) {
            if (Schema::hasTable($table)) {
                Schema::table($table, function (Blueprint $table) {
                    $table->uuid('uuid')->unique()->after('id');
                    $table->index('uuid');
                });
            }
        }
        
        // Populate existing records with UUIDs
        foreach ($tables as $tableName) {
            if (Schema::hasTable($tableName)) {
                DB::table($tableName)->whereNull('uuid')->chunkById(100, function ($records) use ($tableName) {
                    foreach ($records as $record) {
                        DB::table($tableName)
                            ->where('id', $record->id)
                            ->update(['uuid' => Str::uuid()->toString()]);
                    }
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tables = ['users', 'events', 'event_registrations'];
        
        foreach ($tables as $table) {
            if (Schema::hasTable($table)) {
                Schema::table($table, function (Blueprint $table) {
                    $table->dropIndex(['uuid']);
                    $table->dropColumn('uuid');
                });
            }
        }
    }
};
