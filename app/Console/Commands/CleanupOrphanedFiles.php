<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Services\FileStorageService;
use App\Models\User;
use App\Models\Event;
use App\Models\EventResource;

class CleanupOrphanedFiles extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'files:cleanup-orphaned 
                           {--dry-run : Show what would be cleaned without actually doing it}
                           {--type= : Specify file type to check (avatars, posters, resources, all)}
                           {--fix-urls : Fix URLs that point to non-existent files}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean up orphaned file references in the database';

    protected FileStorageService $fileStorageService;

    public function __construct(FileStorageService $fileStorageService)
    {
        parent::__construct();
        $this->fileStorageService = $fileStorageService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $dryRun = $this->option('dry-run');
        $type = $this->option('type') ?? 'all';
        $fixUrls = $this->option('fix-urls');

        $this->info('🔍 File Storage Audit and Cleanup');
        $this->info('================================');

        if ($dryRun) {
            $this->warn('DRY RUN MODE - No changes will be made');
        }

        $stats = [
            'avatars_checked' => 0,
            'avatars_orphaned' => 0,
            'avatars_fixed' => 0,
            'posters_checked' => 0,
            'posters_orphaned' => 0,
            'posters_fixed' => 0,
            'resources_checked' => 0,
            'resources_orphaned' => 0,
            'resources_fixed' => 0,
        ];

        if (in_array($type, ['avatars', 'all'])) {
            $this->checkAvatars($stats, $dryRun, $fixUrls);
        }

        if (in_array($type, ['posters', 'all'])) {
            $this->checkPosters($stats, $dryRun, $fixUrls);
        }

        if (in_array($type, ['resources', 'all'])) {
            $this->checkResources($stats, $dryRun, $fixUrls);
        }

        $this->displaySummary($stats);

        return 0;
    }

    protected function checkAvatars(array &$stats, bool $dryRun, bool $fixUrls)
    {
        $this->info("\n👤 Checking User Avatars...");

        $users = User::whereNotNull('avatar')
            ->where('avatar', '!=', '')
            ->get(['id', 'first_name', 'last_name', 'avatar']);

        $stats['avatars_checked'] = $users->count();

        foreach ($users as $user) {
            $exists = $this->checkFileExists($user->avatar);
            
            if (!$exists) {
                $stats['avatars_orphaned']++;
                $this->warn("❌ User {$user->id} ({$user->first_name} {$user->last_name}): {$user->avatar}");
                
                if ($fixUrls && !$dryRun) {
                    $user->update(['avatar' => null]);
                    $stats['avatars_fixed']++;
                    $this->info("   ✅ Cleared orphaned avatar reference");
                }
            } else {
                $this->line("✅ User {$user->id}: Avatar exists");
            }
        }
    }

    protected function checkPosters(array &$stats, bool $dryRun, bool $fixUrls)
    {
        $this->info("\n🎪 Checking Event Posters...");

        $events = Event::whereNotNull('poster')
            ->where('poster', '!=', '')
            ->get(['id', 'title', 'poster']);

        $stats['posters_checked'] = $events->count();

        foreach ($events as $event) {
            $exists = $this->checkFileExists($event->poster);
            
            if (!$exists) {
                $stats['posters_orphaned']++;
                $this->warn("❌ Event {$event->id} ({$event->title}): {$event->poster}");
                
                if ($fixUrls && !$dryRun) {
                    $event->update(['poster' => null]);
                    $stats['posters_fixed']++;
                    $this->info("   ✅ Cleared orphaned poster reference");
                }
            } else {
                $this->line("✅ Event {$event->id}: Poster exists");
            }
        }
    }

    protected function checkResources(array &$stats, bool $dryRun, bool $fixUrls)
    {
        $this->info("\n📁 Checking Event Resources...");

        $resources = EventResource::whereNotNull('file_path')
            ->where('file_path', '!=', '')
            ->get(['id', 'title', 'file_path']);

        $stats['resources_checked'] = $resources->count();

        foreach ($resources as $resource) {
            $exists = $this->checkFileExists($resource->file_path);
            
            if (!$exists) {
                $stats['resources_orphaned']++;
                $this->warn("❌ Resource {$resource->id} ({$resource->title}): {$resource->file_path}");
                
                if ($fixUrls && !$dryRun) {
                    $resource->delete(); // Soft delete the resource if file doesn't exist
                    $stats['resources_fixed']++;
                    $this->info("   ✅ Deleted orphaned resource reference");
                }
            } else {
                $this->line("✅ Resource {$resource->id}: File exists");
            }
        }
    }

    protected function checkFileExists(string $url): bool
    {
        try {
            // For Cloudinary files, check if they exist
            if (str_contains($url, 'cloudinary.com')) {
                return $this->checkCloudinaryFile($url);
            }
            
            // For local files, use the exists method
            return $this->fileStorageService->exists($url);
            
        } catch (\Exception $e) {
            $this->error("Error checking file: {$e->getMessage()}");
            return false;
        }
    }

    protected function checkCloudinaryFile(string $url): bool
    {
        try {
            if (!class_exists('\Cloudinary\Cloudinary')) {
                return false;
            }

            $cloudinary = new \Cloudinary\Cloudinary([
                'cloud' => [
                    'cloud_name' => env('CLOUDINARY_CLOUD_NAME'),
                    'api_key' => env('CLOUDINARY_API_KEY'),
                    'api_secret' => env('CLOUDINARY_API_SECRET'),
                ]
            ]);

            // Extract public_id from URL
            $reflection = new \ReflectionClass($this->fileStorageService);
            $extractMethod = $reflection->getMethod('extractCloudinaryPublicId');
            $extractMethod->setAccessible(true);
            $publicId = $extractMethod->invoke($this->fileStorageService, $url);

            // Check if file exists
            $cloudinary->adminApi()->asset($publicId);
            return true;
            
        } catch (\Exception $e) {
            return false;
        }
    }

    protected function displaySummary(array $stats)
    {
        $this->info("\n📊 Summary:");
        $this->info("===========");
        
        $this->info("👤 Avatars:");
        $this->info("   Checked: {$stats['avatars_checked']}");
        $this->info("   Orphaned: {$stats['avatars_orphaned']}");
        $this->info("   Fixed: {$stats['avatars_fixed']}");
        
        $this->info("\n🎪 Posters:");
        $this->info("   Checked: {$stats['posters_checked']}");
        $this->info("   Orphaned: {$stats['posters_orphaned']}");
        $this->info("   Fixed: {$stats['posters_fixed']}");
        
        $this->info("\n📁 Resources:");
        $this->info("   Checked: {$stats['resources_checked']}");
        $this->info("   Orphaned: {$stats['resources_orphaned']}");
        $this->info("   Fixed: {$stats['resources_fixed']}");

        $totalOrphaned = $stats['avatars_orphaned'] + $stats['posters_orphaned'] + $stats['resources_orphaned'];
        $totalFixed = $stats['avatars_fixed'] + $stats['posters_fixed'] + $stats['resources_fixed'];

        if ($totalOrphaned > 0) {
            $this->warn("\n⚠️  Found {$totalOrphaned} orphaned file references");
            if ($totalFixed > 0) {
                $this->info("✅ Fixed {$totalFixed} orphaned references");
            } else {
                $this->info("💡 Run with --fix-urls to clean up orphaned references");
            }
        } else {
            $this->info("\n✅ No orphaned file references found!");
        }
    }
}
