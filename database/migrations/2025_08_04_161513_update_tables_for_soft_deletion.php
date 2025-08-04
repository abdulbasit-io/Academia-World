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
        // Update event_registrations table to add cancelled status
        Schema::table('event_registrations', function (Blueprint $table) {
            // Check if status column exists, if not create it, if exists modify it
            if (!Schema::hasColumn('event_registrations', 'status')) {
                $table->enum('status', ['registered', 'cancelled', 'attended', 'no_show'])
                      ->default('registered')
                      ->after('notes');
            } else {
                // Modify existing status column to include cancelled
                DB::statement("ALTER TABLE event_registrations MODIFY COLUMN status ENUM('registered', 'cancelled', 'attended', 'no_show') DEFAULT 'registered'");
            }
        });

        // Update events table to add deleted_at for soft deletes
        Schema::table('events', function (Blueprint $table) {
            if (!Schema::hasColumn('events', 'deleted_at')) {
                $table->timestamp('deleted_at')->nullable()->after('updated_at');
            }
        });

        // Update forum_posts table to add deleted_at for soft deletes
        Schema::table('forum_posts', function (Blueprint $table) {
            if (!Schema::hasColumn('forum_posts', 'deleted_at')) {
                $table->timestamp('deleted_at')->nullable()->after('updated_at');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('event_registrations', function (Blueprint $table) {
            // Revert status to original values if needed
            if (Schema::hasColumn('event_registrations', 'status')) {
                DB::statement("ALTER TABLE event_registrations MODIFY COLUMN status ENUM('registered', 'attended', 'no_show') DEFAULT 'registered'");
            }
        });

        Schema::table('events', function (Blueprint $table) {
            if (Schema::hasColumn('events', 'deleted_at')) {
                $table->dropColumn('deleted_at');
            }
        });

        Schema::table('forum_posts', function (Blueprint $table) {
            if (Schema::hasColumn('forum_posts', 'deleted_at')) {
                $table->dropColumn('deleted_at');
            }
        });
    }
};
