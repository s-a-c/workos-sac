<?php

declare(strict_types=1);

namespace App\Enums;

enum CategoryType: string
{
    case GENRE = 'genre';
    case MOOD = 'mood';
    case THEME = 'theme';
    case ERA = 'era';
    case INSTRUMENT = 'instrument';
    case LANGUAGE = 'language';
    case OCCASION = 'occasion';

    /**
     * Get the display label for the category type.
     */
    public function label(): string
    {
        return match ($this) {
            self::GENRE => 'Music Genre',
            self::MOOD => 'Mood & Emotion',
            self::THEME => 'Theme & Style',
            self::ERA => 'Time Period',
            self::INSTRUMENT => 'Instrument Focus',
            self::LANGUAGE => 'Language',
            self::OCCASION => 'Occasion & Event',
        };
    }

    /**
     * Get the color for UI representation.
     */
    public function color(): string
    {
        return match ($this) {
            self::GENRE => '#FF6B6B',      // Coral Red
            self::MOOD => '#4ECDC4',       // Turquoise
            self::THEME => '#45B7D1',      // Sky Blue
            self::ERA => '#96CEB4',        // Mint Green
            self::INSTRUMENT => '#FFEAA7', // Warm Yellow
            self::LANGUAGE => '#DDA0DD',   // Plum
            self::OCCASION => '#F8C471',   // Peach
        };
    }

    /**
     * Get the Font Awesome icon for visual identification.
     */
    public function icon(): string
    {
        return match ($this) {
            self::GENRE => 'fas fa-music',
            self::MOOD => 'fas fa-heart',
            self::THEME => 'fas fa-palette',
            self::ERA => 'fas fa-clock',
            self::INSTRUMENT => 'fas fa-guitar',
            self::LANGUAGE => 'fas fa-globe',
            self::OCCASION => 'fas fa-calendar-alt',
        };
    }

    /**
     * Get validation rules for this category type.
     */
    public function validationRules(): array
    {
        return match ($this) {
            self::GENRE => [
                'max_depth' => 3,
                'allowed_parents' => [self::GENRE],
                'required_fields' => ['name', 'description'],
            ],
            self::MOOD => [
                'max_depth' => 2,
                'allowed_parents' => [self::MOOD],
                'required_fields' => ['name'],
            ],
            self::THEME => [
                'max_depth' => 2,
                'allowed_parents' => [self::THEME],
                'required_fields' => ['name'],
            ],
            self::ERA => [
                'max_depth' => 2,
                'allowed_parents' => [self::ERA],
                'required_fields' => ['name', 'start_year'],
            ],
            self::INSTRUMENT => [
                'max_depth' => 3,
                'allowed_parents' => [self::INSTRUMENT],
                'required_fields' => ['name'],
            ],
            self::LANGUAGE => [
                'max_depth' => 1,
                'allowed_parents' => [],
                'required_fields' => ['name', 'iso_code'],
            ],
            self::OCCASION => [
                'max_depth' => 2,
                'allowed_parents' => [self::OCCASION],
                'required_fields' => ['name'],
            ],
        };
    }

    /**
     * Get default category suggestions for seeding.
     */
    public function defaultCategories(): array
    {
        return match ($this) {
            self::GENRE => [
                'Rock' => ['Hard Rock', 'Soft Rock', 'Progressive Rock'],
                'Jazz' => ['Smooth Jazz', 'Bebop', 'Fusion'],
                'Electronic' => ['House', 'Techno', 'Ambient'],
                'Classical' => ['Baroque', 'Romantic', 'Modern'],
                'Hip-Hop' => ['East Coast', 'West Coast', 'Trap'],
            ],
            self::MOOD => [
                'Energetic' => ['High Energy', 'Motivational'],
                'Relaxing' => ['Calm', 'Peaceful'],
                'Melancholic' => ['Sad', 'Nostalgic'],
                'Upbeat' => ['Happy', 'Cheerful'],
            ],
            self::THEME => [
                'Workout' => ['Cardio', 'Strength Training'],
                'Study' => ['Focus', 'Background'],
                'Party' => ['Dance', 'Celebration'],
                'Romance' => ['Love Songs', 'Intimate'],
            ],
            self::ERA => [
                '1960s' => [],
                '1970s' => [],
                '1980s' => [],
                '1990s' => [],
                '2000s' => [],
                '2010s' => [],
                '2020s' => [],
            ],
            self::INSTRUMENT => [
                'Piano' => ['Solo Piano', 'Piano Ensemble'],
                'Guitar' => ['Acoustic Guitar', 'Electric Guitar'],
                'Orchestral' => ['Symphony', 'Chamber'],
                'Electronic' => ['Synthesizer', 'Digital'],
            ],
            self::LANGUAGE => [
                'English' => [],
                'Spanish' => [],
                'French' => [],
                'German' => [],
                'Italian' => [],
                'Japanese' => [],
                'Instrumental' => [],
            ],
            self::OCCASION => [
                'Wedding' => ['Ceremony', 'Reception'],
                'Birthday' => ['Children', 'Adult'],
                'Holiday' => ['Christmas', 'Halloween'],
                'Corporate' => ['Presentation', 'Networking'],
            ],
        };
    }

    /**
     * Get all category types as array.
     */
    public static function toArray(): array
    {
        return array_map(fn($case) => $case->value, self::cases());
    }

    /**
     * Get category types suitable for a specific model.
     */
    public static function forModel(string $modelClass): array
    {
        return match ($modelClass) {
            'App\Models\Artist' => [self::GENRE, self::ERA, self::INSTRUMENT],
            'App\Models\Album' => [self::GENRE, self::MOOD, self::THEME, self::ERA, self::LANGUAGE],
            'App\Models\Track' => [self::GENRE, self::MOOD, self::THEME, self::INSTRUMENT, self::LANGUAGE, self::OCCASION],
            'App\Models\Playlist' => [self::MOOD, self::THEME, self::OCCASION],
            'App\Models\Customer' => [self::GENRE, self::MOOD], // Preferences only
            default => self::cases(),
        };
    }
}
