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
        Schema::create('platform_metrics', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('metric_type'); // 'daily_active_users', 'event_engagement', 'forum_activity', etc.
            $table->string('metric_key'); // Specific metric identifier
            $table->json('value'); // Metric value (can be number, array, object)
            $table->date('metric_date'); // Date this metric represents
            $table->string('period')->default('daily'); // 'hourly', 'daily', 'weekly', 'monthly'
            $table->json('breakdown')->nullable(); // Detailed breakdown of the metric
            $table->timestamps();

            $table->unique(['metric_type', 'metric_key', 'metric_date', 'period'], 'platform_metrics_unique_combo');
            $table->index(['metric_type', 'metric_date']);
            $table->index(['metric_date', 'period']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('platform_metrics');
    }
};
