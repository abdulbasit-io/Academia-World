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
        Schema::table('users', function (Blueprint $table) {
            $table->string('first_name')->after('name');
            $table->string('last_name')->after('first_name');
            $table->string('avatar')->nullable()->after('email_verified_at');
            $table->text('bio')->nullable()->after('avatar');
            $table->string('institution')->nullable()->after('bio');
            $table->string('department')->nullable()->after('institution');
            $table->string('position')->nullable()->after('department');
            $table->string('website')->nullable()->after('position');
            $table->string('phone', 20)->nullable()->after('website');
            $table->json('social_links')->nullable()->after('phone');
            $table->enum('account_status', ['pending', 'active', 'suspended', 'banned'])->default('pending')->after('social_links');
            $table->timestamp('last_login_at')->nullable()->after('account_status');
            $table->json('preferences')->nullable()->after('last_login_at');
            
            $table->index('account_status');
            $table->index('institution');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'first_name', 'last_name', 'avatar', 'bio', 'institution', 
                'department', 'position', 'website', 'phone', 'social_links',
                'account_status', 'last_login_at', 'preferences'
            ]);
        });
    }
};
