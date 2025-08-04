<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\AnalyticsService;
use App\Models\User;
use App\Models\Event;
use App\Models\ForumPost;
use App\Models\AnalyticsEvent;

class TestAnalytics extends Command
{
    protected $signature = 'test:analytics';
    protected $description = 'Test the analytics system';

    public function handle()
    {
        $this->info('Testing Analytics System...');
        $this->info('===========================');

        // Check data counts
        $this->info('Current data counts:');
        $this->line('- Users: ' . User::count());
        $this->line('- Events: ' . Event::count());
        $this->line('- Forum Posts: ' . ForumPost::count());
        $this->line('- Analytics Events: ' . AnalyticsEvent::count());
        $this->newLine();

        // Test analytics service
        $this->info('Testing AnalyticsService...');
        $analytics = new AnalyticsService();
        
        try {
            $metrics = $analytics->generatePlatformMetrics();
            $this->info('✅ Platform metrics generated successfully!');
            
            $this->line('User metrics:');
            $this->line('- Total users: ' . $metrics['users']['total_users']);
            $this->line('- Active users (30d): ' . $metrics['users']['active_users_30d']);
            $this->line('- Verified users: ' . $metrics['users']['verified_users']);
            
            $this->newLine();
            $this->line('Event metrics:');
            $this->line('- Total events: ' . $metrics['events']['total_events']);
            $this->line('- Published events: ' . $metrics['events']['published_events']);
            $this->line('- Event views (30d): ' . $metrics['events']['event_views_30d']);
            
            $this->newLine();
            $this->line('Forum metrics:');
            $this->line('- Total posts: ' . $metrics['forum']['total_posts']);
            $this->line('- Posts (30d): ' . $metrics['forum']['new_posts_30d']);
            $this->line('- Total discussions: ' . $metrics['forum']['total_discussions']);
            
            $this->newLine();
            $this->info('✅ Analytics system is working correctly!');
            
        } catch (\Exception $e) {
            $this->error('❌ Analytics test failed: ' . $e->getMessage());
            $this->line('Stack trace: ' . $e->getTraceAsString());
        }
    }
}
