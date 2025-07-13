<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\MediaType;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Chinook Media Types Seeder
 * 
 * Seeds the media_types table with data from the original Chinook database.
 * Maintains original IDs for foreign key compatibility with tracks.
 */
class ChinookMediaTypesSeeder extends Seeder
{
    /**
     * The original Chinook media types data.
     */
    private array $chinookMediaTypes = [
        1 => 'MPEG audio file',
        2 => 'Protected AAC audio file',
        3 => 'Protected MPEG-4 video file',
        4 => 'Purchased AAC audio file',
        5 => 'AAC audio file',
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('Starting Chinook Media Types seeding...');
        
        DB::transaction(function () {
            $systemUser = $this->getSystemUser();
            $createdCount = 0;
            $skippedCount = 0;

            foreach ($this->chinookMediaTypes as $originalId => $mediaTypeName) {
                try {
                    // Check if media type already exists
                    $existingMediaType = MediaType::where('name', $mediaTypeName)->first();
                    
                    if ($existingMediaType) {
                        $this->command->warn("Media Type '{$mediaTypeName}' already exists, skipping...");
                        $skippedCount++;
                        continue;
                    }

                    // Create the media type with original ID preserved for FK compatibility
                    $mediaType = MediaType::create([
                        'id' => $originalId,
                        'name' => $mediaTypeName,
                        'description' => $this->getMediaTypeDescription($mediaTypeName),
                        'file_extension' => $this->getFileExtension($mediaTypeName),
                        'mime_type' => $this->getMimeType($mediaTypeName),
                        'is_audio' => $this->isAudioType($mediaTypeName),
                        'is_video' => $this->isVideoType($mediaTypeName),
                        'quality_level' => $this->getQualityLevel($mediaTypeName),
                        'created_by' => $systemUser?->id,
                        'updated_by' => $systemUser?->id,
                    ]);

                    // Add appropriate tags
                    $tags = $this->getMediaTypeTags($mediaTypeName);
                    $mediaType->syncTags($tags);

                    $createdCount++;
                    $this->command->info("Created media type: {$mediaTypeName} (ID: {$originalId})");

                } catch (\Exception $e) {
                    $this->command->error("Failed to create media type '{$mediaTypeName}': " . $e->getMessage());
                    Log::error("ChinookMediaTypesSeeder failed for media type: {$mediaTypeName}", [
                        'error' => $e->getMessage(),
                        'original_id' => $originalId,
                    ]);
                    throw $e;
                }
            }

            $this->command->info("Chinook Media Types seeding completed: {$createdCount} created, {$skippedCount} skipped");
        });
    }

    /**
     * Get the system user for user stamps.
     */
    private function getSystemUser(): ?User
    {
        return User::where('email', 'system@chinook.com')->first();
    }

    /**
     * Get appropriate description for media type.
     */
    private function getMediaTypeDescription(string $mediaTypeName): string
    {
        return match ($mediaTypeName) {
            'MPEG audio file' => 'Standard MPEG audio format, commonly used for music files',
            'Protected AAC audio file' => 'Advanced Audio Coding format with digital rights management protection',
            'Protected MPEG-4 video file' => 'MPEG-4 video format with digital rights management protection',
            'Purchased AAC audio file' => 'Advanced Audio Coding format for purchased digital music',
            'AAC audio file' => 'Advanced Audio Coding format, high-quality audio compression',
            default => "Media type: {$mediaTypeName}",
        };
    }

    /**
     * Get file extension for media type.
     */
    private function getFileExtension(string $mediaTypeName): string
    {
        return match ($mediaTypeName) {
            'MPEG audio file' => 'mp3',
            'Protected AAC audio file' => 'm4p',
            'Protected MPEG-4 video file' => 'm4v',
            'Purchased AAC audio file' => 'm4a',
            'AAC audio file' => 'aac',
            default => 'unknown',
        };
    }

    /**
     * Get MIME type for media type.
     */
    private function getMimeType(string $mediaTypeName): string
    {
        return match ($mediaTypeName) {
            'MPEG audio file' => 'audio/mpeg',
            'Protected AAC audio file' => 'audio/mp4',
            'Protected MPEG-4 video file' => 'video/mp4',
            'Purchased AAC audio file' => 'audio/mp4',
            'AAC audio file' => 'audio/aac',
            default => 'application/octet-stream',
        };
    }

    /**
     * Check if media type is audio.
     */
    private function isAudioType(string $mediaTypeName): bool
    {
        return !str_contains(strtolower($mediaTypeName), 'video');
    }

    /**
     * Check if media type is video.
     */
    private function isVideoType(string $mediaTypeName): bool
    {
        return str_contains(strtolower($mediaTypeName), 'video');
    }

    /**
     * Get quality level for media type.
     */
    private function getQualityLevel(string $mediaTypeName): string
    {
        return match ($mediaTypeName) {
            'MPEG audio file' => 'standard',
            'Protected AAC audio file' => 'high',
            'Protected MPEG-4 video file' => 'high',
            'Purchased AAC audio file' => 'high',
            'AAC audio file' => 'high',
            default => 'standard',
        };
    }

    /**
     * Get appropriate tags for media type.
     */
    private function getMediaTypeTags(string $mediaTypeName): array
    {
        $baseTags = ['media', 'chinook'];
        
        $specificTags = match ($mediaTypeName) {
            'MPEG audio file' => ['audio', 'mp3', 'mpeg'],
            'Protected AAC audio file' => ['audio', 'aac', 'protected', 'drm'],
            'Protected MPEG-4 video file' => ['video', 'mp4', 'mpeg4', 'protected', 'drm'],
            'Purchased AAC audio file' => ['audio', 'aac', 'purchased', 'digital'],
            'AAC audio file' => ['audio', 'aac'],
            default => ['unknown'],
        };

        return array_merge($baseTags, $specificTags);
    }
}
