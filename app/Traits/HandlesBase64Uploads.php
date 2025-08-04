<?php

namespace App\Traits;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

trait HandlesBase64Uploads
{
    /**
     * Create UploadedFile instance from base64 data
     */
    protected function createUploadedFileFromBase64(string $base64Data, ?string $originalName = null): UploadedFile
    {
        // Extract file info from base64 data
        if (preg_match('/^data:([^;]+);base64,(.+)$/', $base64Data, $matches)) {
            $mimeType = $matches[1];
            $fileData = base64_decode($matches[2]);
            
            if ($fileData === false) {
                throw new \InvalidArgumentException('Invalid base64 data');
            }
            
            $extension = $this->getExtensionFromMimeType($mimeType);
            $filename = $originalName ?: 'upload_' . Str::uuid() . '.' . $extension;
            
            // Create temporary file
            $tempPath = tempnam(sys_get_temp_dir(), 'upload_');
            if ($tempPath === false) {
                throw new \RuntimeException('Failed to create temporary file');
            }
            
            if (file_put_contents($tempPath, $fileData) === false) {
                throw new \RuntimeException('Failed to write base64 data to temporary file');
            }
            
            return new UploadedFile($tempPath, $filename, $mimeType, null, true);
        }
        
        throw new \InvalidArgumentException('Invalid base64 file data format');
    }
    
    /**
     * Get file extension from MIME type
     */
    protected function getExtensionFromMimeType(string $mimeType): string
    {
        $mimeMap = [
            // Images
            'image/jpeg' => 'jpg',
            'image/jpg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            'image/svg+xml' => 'svg',
            
            // Documents
            'application/pdf' => 'pdf',
            'application/msword' => 'doc',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
            'application/vnd.ms-powerpoint' => 'ppt',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'pptx',
            'application/vnd.ms-excel' => 'xls',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
            
            // Text
            'text/plain' => 'txt',
            'text/csv' => 'csv',
            
            // Audio
            'audio/mpeg' => 'mp3',
            'audio/wav' => 'wav',
            'audio/ogg' => 'ogg',
            'audio/aac' => 'aac',
            
            // Video
            'video/mp4' => 'mp4',
            'video/avi' => 'avi',
            'video/quicktime' => 'mov',
            'video/x-msvideo' => 'avi',
            
            // Archives
            'application/zip' => 'zip',
            'application/x-rar-compressed' => 'rar',
            'application/x-tar' => 'tar',
            'application/gzip' => 'gz',
        ];
        
        return $mimeMap[$mimeType] ?? 'bin';
    }
    
    /**
     * Validate base64 string format
     */
    protected function isValidBase64(string $data): bool
    {
        return preg_match('/^data:([^;]+);base64,(.+)$/', $data) === 1;
    }
    
    /**
     * Extract MIME type from base64 data
     */
    protected function getMimeTypeFromBase64(string $base64Data): ?string
    {
        if (preg_match('/^data:([^;]+);base64,/', $base64Data, $matches)) {
            return $matches[1];
        }
        
        return null;
    }
    
    /**
     * Get file size from base64 data
     */
    protected function getBase64FileSize(string $base64Data): int
    {
        if (preg_match('/^data:([^;]+);base64,(.+)$/', $base64Data, $matches)) {
            $encodedData = $matches[2];
            // Calculate approximate file size (base64 adds ~33% overhead)
            return (int) (strlen($encodedData) * 0.75);
        }
        
        return 0;
    }
    
    /**
     * Convert UploadedFile to base64 string
     */
    protected function fileToBase64(UploadedFile $file): string
    {
        $fileContent = file_get_contents($file->getPathname());
        $mimeType = $file->getMimeType();
        
        return 'data:' . $mimeType . ';base64,' . base64_encode($fileContent);
    }
}
