<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;

class FileStorageService
{
    protected array $providers = [];
    protected array $providerOrder = ['s3', 'cloudinary', 'local'];

    public function __construct()
    {
        $this->detectAvailableProviders();
    }

    /**
     * Detect which storage providers are available based on environment
     */
    protected function detectAvailableProviders(): void
    {
        $driver = env('FILE_STORAGE_DRIVER', 'auto');

        if ($driver !== 'auto') {
            // Use explicitly specified driver
            $this->providers = [$driver];
            return;
        }

        // Auto-detect based on environment variables
        $this->providers = [];

        // Check S3
        if ($this->isS3Available()) {
            $this->providers[] = 's3';
        }

        // Check Cloudinary
        if ($this->isCloudinaryAvailable()) {
            $this->providers[] = 'cloudinary';
        }

        // Always have local as fallback
        $this->providers[] = 'local';

        Log::info('Storage providers detected', [
            'providers' => $this->providers,
            'driver_setting' => $driver
        ]);
    }

    /**
     * Check if S3 is configured
     */
    protected function isS3Available(): bool
    {
        return !empty(env('AWS_ACCESS_KEY_ID')) &&
               !empty(env('AWS_SECRET_ACCESS_KEY')) &&
               !empty(env('AWS_BUCKET'));
    }

    /**
     * Check if Cloudinary is configured
     */
    protected function isCloudinaryAvailable(): bool
    {
        return !empty(env('CLOUDINARY_CLOUD_NAME')) &&
               !empty(env('CLOUDINARY_API_KEY')) &&
               !empty(env('CLOUDINARY_API_SECRET'));
    }

    /**
     * Upload file with automatic provider selection and fallback
     * Returns the complete URL for database storage
     */
    public function store(UploadedFile $file, string $path, array $options = []): string
    {
        // If path doesn't contain a filename (no extension), generate one
        if (!pathinfo($path, PATHINFO_EXTENSION)) {
            $filename = $this->generateUniqueFilename($file, $options);
            $path = trim($path, '/') . '/' . $filename;
        }

        foreach ($this->providers as $provider) {
            try {
                $url = $this->storeToProvider($file, $path, $provider);
                
                Log::info('File uploaded successfully', [
                    'provider' => $provider,
                    'url' => $url,
                    'size' => $file->getSize(),
                    'original_name' => $file->getClientOriginalName()
                ]);

                return $url;

            } catch (\Exception $e) {
                Log::warning('Storage provider failed, trying next', [
                    'provider' => $provider,
                    'error' => $e->getMessage(),
                    'file' => $file->getClientOriginalName()
                ]);
                
                // Continue to next provider
                continue;
            }
        }

        // If we get here, all providers failed
        throw new \Exception('File upload failed on all available storage providers');
    }

    /**
     * Upload and process image with automatic provider selection
     * Returns the complete URL for database storage
     */
    public function storeImage(UploadedFile $file, string $path, array $options = []): string
    {
        // Default image processing options
        $width = $options['width'] ?? null;
        $height = $options['height'] ?? null;
        $quality = $options['quality'] ?? 90;
        
        try {
            // Process image if dimensions specified
            if ($width || $height) {
                $processedFile = $this->processImage($file, $width, $height, $quality);
                $url = $this->store($processedFile, $path, $options);
                
                // Clean up temporary file
                if (file_exists($processedFile->getPathname())) {
                    unlink($processedFile->getPathname());
                }
                
                return $url;
            }

            return $this->store($file, $path, $options);

        } catch (\Exception $e) {
            Log::error('Image upload failed', [
                'error' => $e->getMessage(),
                'file' => $file->getClientOriginalName()
            ]);
            
            throw $e;
        }
    }

    /**
     * Store file to specific provider and return complete URL
     */
    protected function storeToProvider(UploadedFile $file, string $path, string $provider): string
    {
        switch ($provider) {
            case 's3':
                return $this->storeToS3($file, $path);
            case 'cloudinary':
                return $this->storeToCloudinary($file, $path);
            case 'local':
                return $this->storeToLocal($file, $path);
            default:
                throw new \Exception("Unknown storage provider: {$provider}");
        }
    }

    /**
     * Store file to AWS S3 and return complete URL
     */
    protected function storeToS3(UploadedFile $file, string $path): string
    {
        $filePath = Storage::disk('s3')->putFileAs(
            dirname($path),
            $file,
            basename($path),
            'public'
        );

        return Storage::disk('s3')->url($filePath);
    }

    /**
     * Store file to Cloudinary and return complete URL
     */
    protected function storeToCloudinary(UploadedFile $file, string $path): string
    {
        // Check if Cloudinary package is available
        if (!class_exists('\Cloudinary\Cloudinary')) {
            throw new \Exception('Cloudinary package not installed. Run: composer require cloudinary/cloudinary_php');
        }

        $cloudinary = new \Cloudinary\Cloudinary([
            'cloud' => [
                'cloud_name' => env('CLOUDINARY_CLOUD_NAME'),
                'api_key' => env('CLOUDINARY_API_KEY'),
                'api_secret' => env('CLOUDINARY_API_SECRET'),
            ]
        ]);

        $folder = dirname($path);
        $publicId = pathinfo($path, PATHINFO_FILENAME);

        $result = $cloudinary->uploadApi()->upload($file->getPathname(), [
            'public_id' => $publicId,
            'folder' => $folder,
            'resource_type' => 'auto'
        ]);

        return $result['secure_url'];
    }

    /**
     * Store file to local storage and return complete URL
     */
    protected function storeToLocal(UploadedFile $file, string $path): string
    {
        $filePath = Storage::disk('public')->putFileAs(
            dirname($path),
            $file,
            basename($path)
        );

        $url = Storage::disk('public')->url($filePath);
        
        // Ensure we return a complete URL (not relative)
        if (!str_starts_with($url, 'http')) {
            $url = config('app.url') . $url;
        }

        return $url;
    }

    /**
     * Delete file using its URL or legacy path (extracts provider and path)
     */
    public function delete(string $urlOrPath): bool
    {
        try {
            // Handle empty or null URLs
            if (empty($urlOrPath)) {
                Log::info('Skipping deletion of empty URL');
                return true;
            }

            // Handle legacy path formats (for backward compatibility)
            if (!str_contains($urlOrPath, 'http') && !str_contains($urlOrPath, '/storage/')) {
                // This looks like a legacy path (e.g., "avatars/old-avatar.jpg")
                return $this->deleteFromLocal($urlOrPath);
            }
            
            $provider = $this->detectProviderFromUrl($urlOrPath);
            $path = $this->extractPathFromUrl($urlOrPath, $provider);

            Log::info('Attempting file deletion', [
                'url' => $urlOrPath,
                'provider' => $provider,
                'path' => $path
            ]);

            switch ($provider) {
                case 's3':
                    return $this->deleteFromS3($path);
                case 'cloudinary':
                    return $this->deleteFromCloudinary($urlOrPath);
                case 'local':
                    return $this->deleteFromLocal($path);
                default:
                    Log::warning('Unknown provider for URL deletion', ['url' => $urlOrPath]);
                    return false;
            }

        } catch (\Exception $e) {
            Log::error('File deletion failed', [
                'url' => $urlOrPath,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return false;
        }
    }

    /**
     * Check if file exists using its URL
     */
    public function exists(string $url): bool
    {
        try {
            $provider = $this->detectProviderFromUrl($url);
            $path = $this->extractPathFromUrl($url, $provider);

            switch ($provider) {
                case 's3':
                    return Storage::disk('s3')->exists($path);
                case 'cloudinary':
                    return $this->checkCloudinaryExists($url);
                case 'local':
                    return Storage::disk('public')->exists($path);
                default:
                    return false;
            }

        } catch (\Exception $e) {
            Log::warning('File existence check failed', [
                'url' => $url,
                'error' => $e->getMessage()
            ]);
            
            return false;
        }
    }

    /**
     * Get the local file path from a URL (for testing purposes)
     * Only works for local storage URLs
     */
    public function getLocalPathFromUrl(string $url): ?string
    {
        try {
            if ($this->detectProviderFromUrl($url) === 'local') {
                return $this->extractPathFromUrl($url, 'local');
            }
            return null;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Detect storage provider from URL
     */
    protected function detectProviderFromUrl(string $url): string
    {
        if (str_contains($url, '.s3.') || str_contains($url, 's3.amazonaws.com')) {
            return 's3';
        }
        
        if (str_contains($url, 'cloudinary.com')) {
            return 'cloudinary';
        }
        
        if (str_contains($url, '/storage/') || str_contains($url, config('app.url'))) {
            return 'local';
        }
        
        throw new \Exception("Cannot detect provider from URL: {$url}");
    }

    /**
     * Extract file path from URL based on provider
     */
    protected function extractPathFromUrl(string $url, string $provider): string
    {
        switch ($provider) {
            case 's3':
                // Extract path from S3 URL
                $bucket = env('AWS_BUCKET');
                $pattern = "/https:\/\/[^\/]+\/" . preg_quote($bucket, '/') . "\/(.*)/";
                if (preg_match($pattern, $url, $matches)) {
                    return $matches[1];
                }
                throw new \Exception("Cannot extract S3 path from URL: {$url}");
                
            case 'local':
                // Extract path from local URL
                if (str_contains($url, '/storage/')) {
                    return str_replace(config('app.url') . '/storage/', '', $url);
                }
                throw new \Exception("Cannot extract local path from URL: {$url}");
                
            case 'cloudinary':
                // For Cloudinary, we'll use the full URL for deletion
                return $url;
                
            default:
                throw new \Exception("Unknown provider: {$provider}");
        }
    }

    /**
     * Delete file from S3
     */
    protected function deleteFromS3(string $path): bool
    {
        try {
            if (Storage::disk('s3')->exists($path)) {
                Storage::disk('s3')->delete($path);
                Log::info('File deleted from S3', ['path' => $path]);
                return true;
            }
            return false;
        } catch (\Exception $e) {
            Log::error('S3 deletion failed', ['path' => $path, 'error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Delete file from Cloudinary
     */
    protected function deleteFromCloudinary(string $url): bool
    {
        try {
            if (!class_exists('\Cloudinary\Cloudinary')) {
                Log::warning('Cloudinary class not available for deletion', ['url' => $url]);
                return false;
            }

            $cloudinary = new \Cloudinary\Cloudinary([
                'cloud' => [
                    'cloud_name' => env('CLOUDINARY_CLOUD_NAME'),
                    'api_key' => env('CLOUDINARY_API_KEY'),
                    'api_secret' => env('CLOUDINARY_API_SECRET'),
                ]
            ]);

            // Extract public_id from Cloudinary URL
            $publicId = $this->extractCloudinaryPublicId($url);
            
            // First check if the file exists
            try {
                $cloudinary->adminApi()->asset($publicId);
                Log::info('Cloudinary file found, proceeding with deletion', ['public_id' => $publicId]);
            } catch (\Exception $e) {
                Log::warning('Cloudinary file not found, marking as already deleted', [
                    'public_id' => $publicId,
                    'url' => $url,
                    'error' => $e->getMessage()
                ]);
                // Return true since the file is already "deleted" (doesn't exist)
                return true;
            }
            
            // Proceed with deletion
            $result = $cloudinary->uploadApi()->destroy($publicId);
            
            $success = isset($result['result']) && in_array($result['result'], ['ok', 'not found']);
            
            Log::info('Cloudinary deletion attempt completed', [
                'public_id' => $publicId,
                'result' => $result,
                'success' => $success
            ]);
            
            return $success;
            
        } catch (\Exception $e) {
            Log::error('Cloudinary deletion failed', [
                'url' => $url,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }

    /**
     * Delete file from local storage
     */
    protected function deleteFromLocal(string $path): bool
    {
        try {
            if (Storage::disk('public')->exists($path)) {
                Storage::disk('public')->delete($path);
                Log::info('File deleted from local storage', ['path' => $path]);
                return true;
            }
            return false;
        } catch (\Exception $e) {
            Log::error('Local deletion failed', ['path' => $path, 'error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Check if file exists on Cloudinary
     */
    protected function checkCloudinaryExists(string $url): bool
    {
        try {
            // Simple HTTP check for Cloudinary URLs
            $headers = @get_headers($url);
            return $headers && strpos($headers[0], '200') !== false;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Extract public_id from Cloudinary URL
     */
    protected function extractCloudinaryPublicId(string $url): string
    {
        // Cloudinary URL format: https://res.cloudinary.com/{cloud_name}/image/upload/{version}/{folder}/{public_id}.{format}
        // We need to extract everything after '/upload/' excluding version (v1234567890) and file extension
        
        // Remove the base URL part
        $pattern = '/\/upload\/(?:v\d+\/)?(.+?)(?:\.[^.]+)?$/';
        if (preg_match($pattern, $url, $matches)) {
            // This will capture: "avatars/avatar_10_1754402470" from the URL
            $publicId = $matches[1];
            
            Log::info('Cloudinary public ID extracted', [
                'url' => $url,
                'public_id' => $publicId,
                'pattern_used' => $pattern
            ]);
            
            return $publicId;
        }
        
        throw new \Exception("Cannot extract public_id from Cloudinary URL: {$url}");
    }

    /**
     * Process image (resize, optimize)
     */
    protected function processImage(UploadedFile $file, ?int $width, ?int $height, int $quality): UploadedFile
    {
        $manager = new ImageManager(new Driver());
        $image = $manager->read($file->getPathname());
        
        // Resize image if dimensions provided
        if ($width && $height) {
            $image->cover($width, $height);
        } elseif ($width) {
            $image->scaleDown($width);
        } elseif ($height) {
            $image->scaleDown(height: $height);
        }
        
        // Create temporary file for processed image
        $tempPath = tempnam(sys_get_temp_dir(), 'processed_');
        $image->save($tempPath, $quality);
        
        return new UploadedFile(
            $tempPath,
            $file->getClientOriginalName(),
            $file->getMimeType(),
            null,
            true
        );
    }

    /**
     * Generate unique filename
     */
    protected function generateUniqueFilename(UploadedFile $file, array $options = []): string
    {
        $prefix = $options['prefix'] ?? 'file';
        $timestamp = $options['timestamp'] ?? time();
        $extension = $file->getClientOriginalExtension();
        
        return $prefix . '_' . $timestamp . '_' . Str::uuid() . '.' . $extension;
    }

    /**
     * Get available storage providers for debugging
     */
    public function getAvailableProviders(): array
    {
        return $this->providers;
    }

    /**
     * Get storage statistics for configured providers
     */
    public function getStorageStats(): array
    {
        $stats = [];
        
        foreach ($this->providers as $provider) {
            try {
                switch ($provider) {
                    case 's3':
                        if (config('filesystems.disks.s3')) {
                            $files = Storage::disk('s3')->allFiles();
                            $totalSize = array_sum(array_map(fn($file) => Storage::disk('s3')->size($file), $files));
                            $stats['s3'] = [
                                'file_count' => count($files),
                                'total_size' => $totalSize,
                                'total_size_formatted' => $this->formatBytes($totalSize),
                            ];
                        }
                        break;
                        
                    case 'local':
                        $files = Storage::disk('public')->allFiles();
                        $totalSize = array_sum(array_map(fn($file) => Storage::disk('public')->size($file), $files));
                        $stats['local'] = [
                            'file_count' => count($files),
                            'total_size' => $totalSize,
                            'total_size_formatted' => $this->formatBytes($totalSize),
                        ];
                        break;
                        
                    case 'cloudinary':
                        $stats['cloudinary'] = [
                            'note' => 'Cloudinary stats require API calls - not implemented in this method'
                        ];
                        break;
                }
                
            } catch (\Exception $e) {
                $stats[$provider] = [
                    'error' => $e->getMessage()
                ];
            }
        }
        
        return $stats;
    }

    /**
     * Format bytes to human readable format
     */
    protected function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, 2) . ' ' . $units[$i];
    }
}
