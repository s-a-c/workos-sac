<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\CategoryType;
use App\Models\Category;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Chinook Genre to Category Seeder
 *
 * Converts the original Chinook genres table data into the polymorphic Category system
 * using CategoryType::GENRE. Maintains original IDs for foreign key compatibility.
 */
class ChinookGenreCategorySeeder extends Seeder
{
    /**
     * The original Chinook genre data to be converted to categories.
     */
    private array $chinookGenres = [
        1 => 'Rock',
        2 => 'Jazz',
        3 => 'Metal',
        4 => 'Alternative & Punk',
        5 => 'Rock And Roll',
        6 => 'Blues',
        7 => 'Latin',
        8 => 'Reggae',
        9 => 'Pop',
        10 => 'Soundtrack',
        11 => 'Bossa Nova',
        12 => 'Easy Listening',
        13 => 'Heavy Metal',
        14 => 'R&B/Soul',
        15 => 'Electronica/Dance',
        16 => 'World',
        17 => 'Hip Hop/Rap',
        18 => 'Science Fiction',
        19 => 'TV Shows',
        20 => 'Sci Fi & Fantasy',
        21 => 'Drama',
        22 => 'Comedy',
        23 => 'Alternative',
        24 => 'Classical',
        25 => 'Opera',
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('Starting Chinook Genre to Category conversion...');

        DB::transaction(function () {
            $systemUser = $this->getSystemUser();
            $createdCount = 0;
            $skippedCount = 0;

            foreach ($this->chinookGenres as $originalId => $genreName) {
                try {
                    // Check if category already exists with this name and type
                    $existingCategory = Category::where('name', $genreName)
                        ->where('type', CategoryType::GENRE)
                        ->first();

                    if ($existingCategory) {
                        $this->command->warn("Category '{$genreName}' already exists, skipping...");
                        $skippedCount++;
                        continue;
                    }

                    // Create the category with original ID preserved for FK compatibility
                    $category = Category::create([
                        'id' => $originalId, // Preserve original ID for foreign key mapping
                        'name' => $genreName,
                        'description' => $this->getGenreDescription($genreName),
                        'type' => CategoryType::GENRE,
                        'color' => $this->getGenreColor($genreName),
                        'icon' => $this->getGenreIcon($genreName),
                        'sort_order' => $originalId,
                        'is_active' => true,
                        'created_by' => $systemUser?->id,
                        'updated_by' => $systemUser?->id,
                    ]);

                    // Add appropriate tags
                    $tags = $this->getGenreTags($genreName);
                    $category->syncTags($tags);

                    $createdCount++;
                    $this->command->info("Created genre category: {$genreName} (ID: {$originalId})");

                } catch (\Exception $e) {
                    $this->command->error("Failed to create category for genre '{$genreName}': " . $e->getMessage());
                    Log::error("ChinookGenreCategorySeeder failed for genre: {$genreName}", [
                        'error' => $e->getMessage(),
                        'original_id' => $originalId,
                    ]);
                    throw $e; // Re-throw to rollback transaction
                }
            }

            $this->command->info("Chinook Genre conversion completed: {$createdCount} created, {$skippedCount} skipped");
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
     * Get appropriate description for genre.
     */
    private function getGenreDescription(string $genreName): string
    {
        return match ($genreName) {
            'Rock' => 'A broad genre of popular music that originated as "rock and roll"',
            'Jazz' => 'A music genre that originated in the African-American communities',
            'Metal' => 'A genre of rock music that developed in the late 1960s and early 1970s',
            'Alternative & Punk' => 'Alternative rock and punk music genres',
            'Rock And Roll' => 'A genre of popular music that originated and evolved in the United States',
            'Blues' => 'A music genre and musical form which originated in the Deep South',
            'Latin' => 'Music from Latin America and the Iberian Peninsula',
            'Reggae' => 'A music genre that originated in Jamaica in the late 1960s',
            'Pop' => 'A genre of popular music that originated in its modern form',
            'Soundtrack' => 'Music recorded for use in a movie, television show, or video game',
            'Bossa Nova' => 'A style of Brazilian music derived from samba and jazz',
            'Easy Listening' => 'A popular music genre and radio format',
            'Heavy Metal' => 'A genre of rock music that developed in the late 1960s',
            'R&B/Soul' => 'Rhythm and blues and soul music genres',
            'Electronica/Dance' => 'Electronic music designed for dancing',
            'World' => 'Music from around the world, often traditional or folk',
            'Hip Hop/Rap' => 'A cultural movement and music genre developed in the Bronx',
            'Science Fiction' => 'Music related to science fiction themes and media',
            'TV Shows' => 'Music from television shows and series',
            'Sci Fi & Fantasy' => 'Music related to science fiction and fantasy themes',
            'Drama' => 'Music related to dramatic themes and media',
            'Comedy' => 'Music related to comedy and humorous content',
            'Alternative' => 'Alternative rock and alternative music',
            'Classical' => 'Art music produced or rooted in Western musical traditions',
            'Opera' => 'A form of theatre in which music has a leading role',
            default => "A {$genreName} music category from the Chinook database",
        };
    }

    /**
     * Get appropriate color for genre.
     */
    private function getGenreColor(string $genreName): string
    {
        return match ($genreName) {
            'Rock', 'Rock And Roll', 'Alternative', 'Alternative & Punk' => '#FF6B6B',
            'Jazz', 'Blues', 'Bossa Nova' => '#4ECDC4',
            'Metal', 'Heavy Metal' => '#2C3E50',
            'Pop', 'Easy Listening' => '#FF9FF3',
            'Classical', 'Opera' => '#98D8C8',
            'Electronica/Dance', 'Hip Hop/Rap' => '#BB8FCE',
            'Latin', 'Reggae', 'World' => '#F39C12',
            'R&B/Soul' => '#8E44AD',
            'Soundtrack', 'TV Shows', 'Science Fiction', 'Sci Fi & Fantasy', 'Drama', 'Comedy' => '#3498DB',
            default => '#95A5A6',
        };
    }

    /**
     * Get appropriate icon for genre.
     */
    private function getGenreIcon(string $genreName): string
    {
        return match ($genreName) {
            'Rock', 'Rock And Roll', 'Alternative', 'Alternative & Punk' => 'fas fa-guitar',
            'Jazz', 'Blues' => 'fas fa-music',
            'Metal', 'Heavy Metal' => 'fas fa-fire',
            'Classical', 'Opera' => 'fas fa-violin',
            'Electronica/Dance' => 'fas fa-microchip',
            'Hip Hop/Rap' => 'fas fa-microphone',
            'World', 'Latin' => 'fas fa-globe',
            'Soundtrack', 'TV Shows', 'Science Fiction', 'Sci Fi & Fantasy', 'Drama', 'Comedy' => 'fas fa-film',
            default => 'fas fa-compact-disc',
        };
    }

    /**
     * Get appropriate tags for genre.
     */
    private function getGenreTags(string $genreName): array
    {
        $baseTags = ['music', 'genre', 'chinook'];

        $specificTags = match ($genreName) {
            'Rock' => ['rock'],
            'Jazz' => ['jazz'],
            'Metal' => ['metal', 'rock'],
            'Alternative & Punk' => ['alternative', 'punk', 'rock'],
            'Rock And Roll' => ['rock-and-roll', 'rock'],
            'Blues' => ['blues'],
            'Latin' => ['latin', 'world'],
            'Reggae' => ['reggae', 'world'],
            'Pop' => ['pop'],
            'Soundtrack' => ['soundtrack', 'media'],
            'Bossa Nova' => ['bossa-nova', 'latin', 'jazz'],
            'Easy Listening' => ['easy-listening', 'pop'],
            'Heavy Metal' => ['heavy-metal', 'metal', 'rock'],
            'R&B/Soul' => ['rnb', 'soul'],
            'Electronica/Dance' => ['electronic', 'dance'],
            'World' => ['world'],
            'Hip Hop/Rap' => ['hip-hop', 'rap'],
            'Science Fiction' => ['sci-fi', 'soundtrack', 'media'],
            'TV Shows' => ['tv', 'soundtrack', 'media'],
            'Sci Fi & Fantasy' => ['sci-fi', 'fantasy', 'soundtrack', 'media'],
            'Drama' => ['drama', 'soundtrack', 'media'],
            'Comedy' => ['comedy', 'soundtrack', 'media'],
            'Alternative' => ['alternative', 'rock'],
            'Classical' => ['classical'],
            'Opera' => ['opera', 'classical'],
            default => [strtolower(str_replace([' ', '&', '/'], ['-', 'and', '-'], $genreName))],
        };

        return array_merge($baseTags, $specificTags);
    }
}
