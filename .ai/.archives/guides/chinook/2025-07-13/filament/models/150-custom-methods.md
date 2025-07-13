# Custom Methods Guide

## Table of Contents

- [Overview](#overview)
- [Business Logic Methods](#business-logic-methods)
- [Helper Methods](#helper-methods)
- [Utility Methods](#utility-methods)
- [Calculation Methods](#calculation-methods)
- [Validation Methods](#validation-methods)
- [Integration Methods](#integration-methods)
- [Testing Custom Methods](#testing-custom-methods)
- [Best Practices](#best-practices)
- [Navigation](#navigation)

## Overview

This guide covers comprehensive custom method implementation for Laravel 12 models in the Chinook application. The focus is on business logic encapsulation, helper methods, utility functions, and domain-specific operations with performance optimization.

**🚀 Key Features:**
- **Business Logic Encapsulation**: Domain-specific operations in models
- **Helper Methods**: Utility functions for common operations
- **Calculation Methods**: Complex computations and aggregations
- **Validation Methods**: Custom validation logic
- **WCAG 2.1 AA Compliance**: Accessible method outputs

## Business Logic Methods

### Artist Business Logic

```php
<?php
// app/Models/Artist.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Carbon\Carbon;

class Artist extends Model
{
    /**
     * Check if artist can release new album
     */
    public function canReleaseAlbum(): bool
    {
        // Business rule: Must be active and have less than 10 unreleased albums
        if (!$this->is_active) {
            return false;
        }

        $unreleasedCount = $this->albums()
            ->where('status', 'unreleased')
            ->count();

        return $unreleasedCount < 10;
    }

    /**
     * Get artist's career span
     */
    public function getCareerSpan(): array
    {
        $firstAlbum = $this->albums()
            ->whereNotNull('release_date')
            ->orderBy('release_date')
            ->first();

        $latestAlbum = $this->albums()
            ->whereNotNull('release_date')
            ->orderByDesc('release_date')
            ->first();

        if (!$firstAlbum) {
            return [
                'start_year' => null,
                'end_year' => null,
                'years_active' => 0,
                'status' => 'no_releases'
            ];
        }

        $startYear = $firstAlbum->release_date->year;
        $endYear = $latestAlbum ? $latestAlbum->release_date->year : $startYear;
        $yearsActive = $endYear - $startYear + 1;

        return [
            'start_year' => $startYear,
            'end_year' => $endYear,
            'years_active' => $yearsActive,
            'status' => $endYear === now()->year ? 'active' : 'inactive'
        ];
    }

    /**
     * Calculate artist productivity score
     */
    public function getProductivityScore(): float
    {
        $careerSpan = $this->getCareerSpan();
        
        if ($careerSpan['years_active'] === 0) {
            return 0.0;
        }

        $albumCount = $this->albums()->count();
        $trackCount = $this->tracks()->count();
        
        // Score based on output per year
        $albumsPerYear = $albumCount / $careerSpan['years_active'];
        $tracksPerYear = $trackCount / $careerSpan['years_active'];
        
        return ($albumsPerYear * 10) + ($tracksPerYear * 0.5);
    }

    /**
     * Get artist's genre evolution
     */
    public function getGenreEvolution(): array
    {
        $albums = $this->albums()
            ->with(['categories' => function ($q) {
                $q->where('type', 'genre');
            }])
            ->orderBy('release_date')
            ->get();

        $evolution = [];
        
        foreach ($albums as $album) {
            $year = $album->release_date?->year;
            if (!$year) continue;

            $genres = $album->categories->pluck('name')->toArray();
            
            $evolution[] = [
                'year' => $year,
                'album' => $album->title,
                'genres' => $genres,
                'primary_genre' => $genres[0] ?? 'Unknown'
            ];
        }

        return $evolution;
    }

    /**
     * Check if artist is trending
     */
    public function isTrending(): bool
    {
        // Check recent activity and engagement
        $recentPlays = $this->getRecentPlayCount(30); // Last 30 days
        $recentViews = $this->getRecentViewCount(30);
        $followerGrowth = $this->getFollowerGrowth(30);

        // Trending criteria
        return $recentPlays > 1000 || 
               $recentViews > 5000 || 
               $followerGrowth > 0.1; // 10% growth
    }

    /**
     * Get collaboration opportunities
     */
    public function getCollaborationOpportunities(): Collection
    {
        $myGenres = $this->getGenres();
        
        return static::where('id', '!=', $this->id)
            ->where('is_active', true)
            ->whereHas('categories', function ($q) use ($myGenres) {
                $q->whereIn('name', $myGenres);
            })
            ->withCount('albums')
            ->having('albums_count', '>', 0)
            ->orderByDesc('albums_count')
            ->limit(10)
            ->get();
    }

    /**
     * Calculate compatibility with another artist
     */
    public function getCompatibilityWith(Artist $otherArtist): array
    {
        $myGenres = collect($this->getGenres());
        $theirGenres = collect($otherArtist->getGenres());
        
        $commonGenres = $myGenres->intersect($theirGenres);
        $genreCompatibility = $commonGenres->count() / max($myGenres->count(), $theirGenres->count());
        
        $careerOverlap = $this->calculateCareerOverlap($otherArtist);
        $popularityDifference = abs($this->getPopularityScore() - $otherArtist->getPopularityScore());
        
        return [
            'genre_compatibility' => round($genreCompatibility, 2),
            'career_overlap' => $careerOverlap,
            'popularity_difference' => $popularityDifference,
            'common_genres' => $commonGenres->values()->toArray(),
            'overall_score' => $this->calculateOverallCompatibility(
                $genreCompatibility, 
                $careerOverlap, 
                $popularityDifference
            )
        ];
    }

    /**
     * Get artist's influence network
     */
    public function getInfluenceNetwork(): array
    {
        // This would typically involve complex analysis of:
        // - Similar artists
        // - Collaboration history
        // - Genre relationships
        // - Fan overlap
        
        return [
            'influenced_by' => $this->getInfluencedBy(),
            'influences' => $this->getInfluences(),
            'collaborators' => $this->getCollaborators(),
            'similar_artists' => $this->getSimilarArtists(),
        ];
    }

    /**
     * Helper method to get artist genres
     */
    protected function getGenres(): array
    {
        return $this->categories()
            ->where('type', 'genre')
            ->pluck('name')
            ->toArray();
    }

    /**
     * Helper method to calculate career overlap
     */
    protected function calculateCareerOverlap(Artist $otherArtist): float
    {
        $mySpan = $this->getCareerSpan();
        $theirSpan = $otherArtist->getCareerSpan();
        
        if (!$mySpan['start_year'] || !$theirSpan['start_year']) {
            return 0.0;
        }
        
        $overlapStart = max($mySpan['start_year'], $theirSpan['start_year']);
        $overlapEnd = min($mySpan['end_year'], $theirSpan['end_year']);
        
        if ($overlapStart > $overlapEnd) {
            return 0.0;
        }
        
        $overlapYears = $overlapEnd - $overlapStart + 1;
        $totalYears = max($mySpan['years_active'], $theirSpan['years_active']);
        
        return $overlapYears / $totalYears;
    }

    /**
     * Calculate overall compatibility score
     */
    protected function calculateOverallCompatibility(
        float $genreCompatibility, 
        float $careerOverlap, 
        float $popularityDifference
    ): float {
        // Weighted scoring
        $genreWeight = 0.4;
        $careerWeight = 0.3;
        $popularityWeight = 0.3;
        
        // Normalize popularity difference (lower is better)
        $normalizedPopularity = max(0, 1 - ($popularityDifference / 100));
        
        return ($genreCompatibility * $genreWeight) + 
               ($careerOverlap * $careerWeight) + 
               ($normalizedPopularity * $popularityWeight);
    }

    /**
     * Get recent play count
     */
    protected function getRecentPlayCount(int $days): int
    {
        // This would query a plays/analytics table
        return 0; // Placeholder
    }

    /**
     * Get recent view count
     */
    protected function getRecentViewCount(int $days): int
    {
        // This would query a views/analytics table
        return 0; // Placeholder
    }

    /**
     * Get follower growth rate
     */
    protected function getFollowerGrowth(int $days): float
    {
        // This would calculate follower growth rate
        return 0.0; // Placeholder
    }

    /**
     * Get popularity score
     */
    protected function getPopularityScore(): float
    {
        // This would calculate based on various metrics
        return 0.0; // Placeholder
    }

    /**
     * Get artists that influenced this artist
     */
    protected function getInfluencedBy(): array
    {
        // This would involve complex relationship analysis
        return []; // Placeholder
    }

    /**
     * Get artists influenced by this artist
     */
    protected function getInfluences(): array
    {
        // This would involve complex relationship analysis
        return []; // Placeholder
    }

    /**
     * Get collaborators
     */
    protected function getCollaborators(): array
    {
        // This would analyze collaboration history
        return []; // Placeholder
    }

    /**
     * Get similar artists
     */
    protected function getSimilarArtists(): array
    {
        // This would use recommendation algorithms
        return []; // Placeholder
    }
}
```

## Helper Methods

### Album Helper Methods

```php
<?php
// app/Models/Album.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class Album extends Model
{
    /**
     * Get formatted track listing
     */
    public function getTrackListing(): array
    {
        return $this->tracks()
            ->orderBy('disc_number')
            ->orderBy('track_number')
            ->get()
            ->map(function ($track) {
                return [
                    'position' => $track->position_display,
                    'title' => $track->name,
                    'duration' => $track->duration_formatted,
                    'explicit' => $track->is_explicit,
                ];
            })
            ->toArray();
    }

    /**
     * Get album credits
     */
    public function getCredits(): array
    {
        $metadata = $this->metadata ?? [];
        
        return [
            'producer' => $metadata['producer'] ?? null,
            'engineer' => $metadata['engineer'] ?? null,
            'studio' => $metadata['studio'] ?? null,
            'label' => $metadata['label'] ?? null,
            'additional_credits' => $metadata['credits'] ?? [],
        ];
    }

    /**
     * Check if album is complete
     */
    public function isComplete(): bool
    {
        return $this->tracks()->count() > 0 &&
               !empty($this->title) &&
               !empty($this->release_date);
    }

    /**
     * Get album format information
     */
    public function getFormatInfo(): array
    {
        $trackCount = $this->tracks()->count();
        $totalDuration = $this->tracks()->sum('duration_ms');
        
        // Determine format based on duration and track count
        $format = 'album'; // Default
        
        if ($trackCount <= 3 && $totalDuration < 900000) { // 15 minutes
            $format = 'single';
        } elseif ($trackCount <= 7 && $totalDuration < 1800000) { // 30 minutes
            $format = 'ep';
        } elseif ($totalDuration > 4800000) { // 80 minutes
            $format = 'double_album';
        }
        
        return [
            'format' => $format,
            'track_count' => $trackCount,
            'total_duration' => $totalDuration,
            'duration_formatted' => $this->formatDuration($totalDuration),
            'disc_count' => $this->tracks()->max('disc_number') ?? 1,
        ];
    }

    /**
     * Get album statistics
     */
    public function getStatistics(): array
    {
        $tracks = $this->tracks;
        
        if ($tracks->isEmpty()) {
            return [
                'track_count' => 0,
                'total_duration' => 0,
                'average_track_length' => 0,
                'shortest_track' => null,
                'longest_track' => null,
                'explicit_content' => false,
            ];
        }
        
        $durations = $tracks->pluck('duration_ms');
        
        return [
            'track_count' => $tracks->count(),
            'total_duration' => $durations->sum(),
            'average_track_length' => $durations->avg(),
            'shortest_track' => [
                'title' => $tracks->sortBy('duration_ms')->first()->name,
                'duration' => $durations->min(),
            ],
            'longest_track' => [
                'title' => $tracks->sortByDesc('duration_ms')->first()->name,
                'duration' => $durations->max(),
            ],
            'explicit_content' => $tracks->where('is_explicit', true)->count() > 0,
        ];
    }

    /**
     * Generate album summary
     */
    public function getSummary(): string
    {
        $stats = $this->getStatistics();
        $format = $this->getFormatInfo();
        
        $summary = "{$this->title} by {$this->artist->name} is a {$format['format']} ";
        $summary .= "featuring {$stats['track_count']} tracks ";
        $summary .= "with a total duration of {$format['duration_formatted']}. ";
        
        if ($this->release_date) {
            $summary .= "Released on {$this->release_date->format('F j, Y')}";
        }
        
        if ($stats['explicit_content']) {
            $summary .= " (Contains explicit content)";
        }
        
        return $summary . ".";
    }

    /**
     * Format duration helper
     */
    protected function formatDuration(int $milliseconds): string
    {
        $seconds = floor($milliseconds / 1000);
        $minutes = floor($seconds / 60);
        $hours = floor($minutes / 60);
        
        $seconds = $seconds % 60;
        $minutes = $minutes % 60;
        
        if ($hours > 0) {
            return sprintf('%d:%02d:%02d', $hours, $minutes, $seconds);
        }
        
        return sprintf('%d:%02d', $minutes, $seconds);
    }
}
```

## Utility Methods

### Track Utility Methods

```php
<?php
// app/Models/Track.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Track extends Model
{
    /**
     * Convert track to different audio formats
     */
    public function getAudioFormats(): array
    {
        $basePath = $this->file_path;

        return [
            'mp3' => $this->getFormatPath($basePath, 'mp3'),
            'flac' => $this->getFormatPath($basePath, 'flac'),
            'wav' => $this->getFormatPath($basePath, 'wav'),
            'ogg' => $this->getFormatPath($basePath, 'ogg'),
        ];
    }

    /**
     * Get track waveform data
     */
    public function getWaveformData(): array
    {
        // This would typically generate or retrieve waveform data
        $cacheKey = "waveform_{$this->id}";

        return cache()->remember($cacheKey, 3600, function () {
            return $this->generateWaveform();
        });
    }

    /**
     * Calculate track tempo (BPM)
     */
    public function calculateTempo(): ?float
    {
        $metadata = $this->metadata ?? [];

        if (isset($metadata['bpm'])) {
            return (float) $metadata['bpm'];
        }

        // This would use audio analysis to detect BPM
        return $this->analyzeAudioTempo();
    }

    /**
     * Get track key signature
     */
    public function getKeySignature(): ?string
    {
        $metadata = $this->metadata ?? [];

        return $metadata['key'] ?? $this->analyzeAudioKey();
    }

    /**
     * Check if track is instrumental
     */
    public function isInstrumental(): bool
    {
        // Check if lyrics exist or if marked as instrumental
        return empty($this->lyrics) ||
               ($this->metadata['instrumental'] ?? false);
    }

    /**
     * Get track mood analysis
     */
    public function getMoodAnalysis(): array
    {
        return [
            'energy' => $this->getEnergyLevel(),
            'valence' => $this->getValence(),
            'danceability' => $this->getDanceability(),
            'acousticness' => $this->getAcousticness(),
            'mood_tags' => $this->getMoodTags(),
        ];
    }

    /**
     * Generate track fingerprint for matching
     */
    public function generateFingerprint(): string
    {
        // This would use audio fingerprinting algorithms
        $cacheKey = "fingerprint_{$this->id}";

        return cache()->remember($cacheKey, 86400, function () {
            return $this->analyzeAudioFingerprint();
        });
    }

    /**
     * Find similar tracks
     */
    public function findSimilarTracks(int $limit = 10): Collection
    {
        $fingerprint = $this->generateFingerprint();
        $tempo = $this->calculateTempo();
        $key = $this->getKeySignature();

        return static::where('id', '!=', $this->id)
            ->when($tempo, function ($q) use ($tempo) {
                $q->whereRaw('ABS(JSON_EXTRACT(metadata, "$.bpm") - ?) < 10', [$tempo]);
            })
            ->when($key, function ($q) use ($key) {
                $q->whereRaw('JSON_EXTRACT(metadata, "$.key") = ?', [$key]);
            })
            ->limit($limit)
            ->get();
    }

    /**
     * Helper methods for audio analysis
     */
    protected function getFormatPath(string $basePath, string $format): string
    {
        return str_replace(pathinfo($basePath, PATHINFO_EXTENSION), $format, $basePath);
    }

    protected function generateWaveform(): array
    {
        // Placeholder for waveform generation
        return array_fill(0, 100, rand(0, 100));
    }

    protected function analyzeAudioTempo(): ?float
    {
        // Placeholder for tempo analysis
        return null;
    }

    protected function analyzeAudioKey(): ?string
    {
        // Placeholder for key analysis
        return null;
    }

    protected function getEnergyLevel(): float
    {
        // Placeholder for energy analysis
        return rand(0, 100) / 100;
    }

    protected function getValence(): float
    {
        // Placeholder for valence analysis
        return rand(0, 100) / 100;
    }

    protected function getDanceability(): float
    {
        // Placeholder for danceability analysis
        return rand(0, 100) / 100;
    }

    protected function getAcousticness(): float
    {
        // Placeholder for acousticness analysis
        return rand(0, 100) / 100;
    }

    protected function getMoodTags(): array
    {
        // Placeholder for mood tag analysis
        return ['energetic', 'happy', 'danceable'];
    }

    protected function analyzeAudioFingerprint(): string
    {
        // Placeholder for fingerprint generation
        return hash('sha256', $this->id . $this->name . $this->duration_ms);
    }
}
```

## Calculation Methods

### Advanced Calculation Methods

```php
<?php
// app/Models/Category.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Category extends Model
{
    /**
     * Calculate category popularity score
     */
    public function calculatePopularityScore(): float
    {
        $usageCount = $this->getUsageCount();
        $recentUsage = $this->getRecentUsageCount(30);
        $childrenPopularity = $this->getChildrenPopularitySum();

        // Weighted scoring
        return ($usageCount * 0.5) +
               ($recentUsage * 0.3) +
               ($childrenPopularity * 0.2);
    }

    /**
     * Get category usage statistics
     */
    public function getUsageStatistics(): array
    {
        $totalUsage = $this->getUsageCount();
        $modelUsage = $this->getUsageByModel();
        $trendData = $this->getUsageTrend();

        return [
            'total_usage' => $totalUsage,
            'usage_by_model' => $modelUsage,
            'trend_data' => $trendData,
            'popularity_rank' => $this->getPopularityRank(),
            'growth_rate' => $this->calculateGrowthRate(),
        ];
    }

    /**
     * Calculate category influence score
     */
    public function calculateInfluenceScore(): float
    {
        $directUsage = $this->getUsageCount();
        $childrenUsage = $this->getDescendantsUsageCount();
        $parentInfluence = $this->parent ? $this->parent->calculateInfluenceScore() * 0.1 : 0;

        return $directUsage + ($childrenUsage * 0.5) + $parentInfluence;
    }

    /**
     * Get category network metrics
     */
    public function getNetworkMetrics(): array
    {
        return [
            'centrality' => $this->calculateCentrality(),
            'clustering_coefficient' => $this->calculateClusteringCoefficient(),
            'betweenness' => $this->calculateBetweenness(),
            'closeness' => $this->calculateCloseness(),
        ];
    }

    /**
     * Helper methods for calculations
     */
    protected function getUsageCount(): int
    {
        return DB::table('categorizables')
            ->where('category_id', $this->id)
            ->count();
    }

    protected function getRecentUsageCount(int $days): int
    {
        return DB::table('categorizables')
            ->where('category_id', $this->id)
            ->where('created_at', '>=', now()->subDays($days))
            ->count();
    }

    protected function getChildrenPopularitySum(): float
    {
        return $this->children->sum(function ($child) {
            return $child->calculatePopularityScore();
        });
    }

    protected function getUsageByModel(): array
    {
        return DB::table('categorizables')
            ->select('categorizable_type', DB::raw('COUNT(*) as count'))
            ->where('category_id', $this->id)
            ->groupBy('categorizable_type')
            ->pluck('count', 'categorizable_type')
            ->toArray();
    }

    protected function getUsageTrend(): array
    {
        return DB::table('categorizables')
            ->select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('COUNT(*) as count')
            )
            ->where('category_id', $this->id)
            ->where('created_at', '>=', now()->subDays(30))
            ->groupBy('date')
            ->orderBy('date')
            ->pluck('count', 'date')
            ->toArray();
    }

    protected function getPopularityRank(): int
    {
        $rank = DB::table('categories')
            ->selectRaw('COUNT(*) + 1 as rank')
            ->whereRaw('
                (SELECT COUNT(*) FROM categorizables WHERE category_id = categories.id) >
                (SELECT COUNT(*) FROM categorizables WHERE category_id = ?)
            ', [$this->id])
            ->value('rank');

        return $rank ?? 1;
    }

    protected function calculateGrowthRate(): float
    {
        $currentMonth = $this->getRecentUsageCount(30);
        $previousMonth = $this->getUsageCountBetween(60, 30);

        if ($previousMonth === 0) {
            return $currentMonth > 0 ? 100.0 : 0.0;
        }

        return (($currentMonth - $previousMonth) / $previousMonth) * 100;
    }

    protected function getUsageCountBetween(int $startDays, int $endDays): int
    {
        return DB::table('categorizables')
            ->where('category_id', $this->id)
            ->whereBetween('created_at', [
                now()->subDays($startDays),
                now()->subDays($endDays)
            ])
            ->count();
    }

    protected function getDescendantsUsageCount(): int
    {
        $descendantIds = $this->descendants()->pluck('id');

        return DB::table('categorizables')
            ->whereIn('category_id', $descendantIds)
            ->count();
    }

    protected function calculateCentrality(): float
    {
        // Placeholder for network centrality calculation
        return 0.0;
    }

    protected function calculateClusteringCoefficient(): float
    {
        // Placeholder for clustering coefficient calculation
        return 0.0;
    }

    protected function calculateBetweenness(): float
    {
        // Placeholder for betweenness centrality calculation
        return 0.0;
    }

    protected function calculateCloseness(): float
    {
        // Placeholder for closeness centrality calculation
        return 0.0;
    }
}
```

## Validation Methods

### Custom Validation Methods

```php
<?php
// app/Models/Artist.php (continued)

class Artist extends Model
{
    /**
     * Validate artist data integrity
     */
    public function validateDataIntegrity(): array
    {
        $errors = [];

        // Check required fields
        if (empty($this->name)) {
            $errors[] = 'Artist name is required';
        }

        if (empty($this->public_id)) {
            $errors[] = 'Public ID is missing';
        }

        // Check data consistency
        if ($this->albums()->exists() && !$this->is_active) {
            $errors[] = 'Artist with albums should be active';
        }

        // Check metadata integrity
        $metadataErrors = $this->validateMetadataIntegrity();
        $errors = array_merge($errors, $metadataErrors);

        return $errors;
    }

    /**
     * Validate business rules
     */
    public function validateBusinessRules(): array
    {
        $errors = [];

        // Check album limits
        $albumCount = $this->albums()->count();
        if ($albumCount > 50) {
            $errors[] = 'Artist has too many albums (limit: 50)';
        }

        // Check genre consistency
        $genreErrors = $this->validateGenreConsistency();
        $errors = array_merge($errors, $genreErrors);

        return $errors;
    }

    /**
     * Validate metadata integrity
     */
    protected function validateMetadataIntegrity(): array
    {
        $errors = [];
        $metadata = $this->metadata ?? [];

        // Validate social links
        if (isset($metadata['social'])) {
            foreach ($metadata['social'] as $platform => $url) {
                if (!filter_var($url, FILTER_VALIDATE_URL)) {
                    $errors[] = "Invalid {$platform} URL";
                }
            }
        }

        // Validate stats
        if (isset($metadata['stats'])) {
            foreach ($metadata['stats'] as $stat => $value) {
                if (!is_numeric($value) || $value < 0) {
                    $errors[] = "Invalid {$stat} value";
                }
            }
        }

        return $errors;
    }

    /**
     * Validate genre consistency
     */
    protected function validateGenreConsistency(): array
    {
        $errors = [];
        $artistGenres = $this->getGenres();

        foreach ($this->albums as $album) {
            $albumGenres = $album->getGenres();
            $commonGenres = array_intersect($artistGenres, $albumGenres);

            if (empty($commonGenres) && !empty($artistGenres) && !empty($albumGenres)) {
                $errors[] = "Album '{$album->title}' has no common genres with artist";
            }
        }

        return $errors;
    }
}
```

## Integration Methods

### API Integration Methods

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

class Artist extends Model
{
    /**
     * Sync artist data with external music services
     */
    public function syncWithMusicBrainz(): array
    {
        $cacheKey = "musicbrainz_artist_{$this->id}";

        return Cache::remember($cacheKey, now()->addHours(24), function () {
            $response = Http::get("https://musicbrainz.org/ws/2/artist/{$this->musicbrainz_id}", [
                'fmt' => 'json',
                'inc' => 'aliases+tags+ratings',
            ]);

            if ($response->successful()) {
                $data = $response->json();

                // Update artist information
                $this->update([
                    'biography' => $data['disambiguation'] ?? null,
                    'country' => $data['country'] ?? null,
                    'tags' => collect($data['tags'] ?? [])->pluck('name')->toArray(),
                ]);

                return $data;
            }

            return [];
        });
    }

    /**
     * Export artist data for external systems
     */
    public function exportToFormat(string $format = 'json'): string
    {
        $data = [
            'id' => $this->public_id,
            'name' => $this->name,
            'albums' => $this->albums->map(function ($album) {
                return [
                    'id' => $album->public_id,
                    'title' => $album->title,
                    'year' => $album->release_year,
                    'tracks' => $album->tracks->count(),
                ];
            }),
            'total_tracks' => $this->tracks()->count(),
            'genres' => $this->categories()->where('type', 'genre')->pluck('name'),
        ];

        return match ($format) {
            'xml' => $this->arrayToXml($data),
            'csv' => $this->arrayToCsv($data),
            default => json_encode($data, JSON_PRETTY_PRINT),
        };
    }
}
```

### Third-Party Service Integration

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Services\SpotifyService;
use App\Services\LastFmService;

class Track extends Model
{
    /**
     * Get streaming analytics from multiple platforms
     */
    public function getStreamingAnalytics(): array
    {
        $spotify = app(SpotifyService::class);
        $lastfm = app(LastFmService::class);

        return [
            'spotify' => $spotify->getTrackAnalytics($this->spotify_id),
            'lastfm' => $lastfm->getTrackScrobbles($this->artist->name, $this->name),
            'total_plays' => $this->play_count,
            'popularity_score' => $this->calculatePopularityScore(),
        ];
    }

    /**
     * Sync track metadata with external services
     */
    public function syncMetadata(): bool
    {
        try {
            $spotify = app(SpotifyService::class);
            $metadata = $spotify->getTrackMetadata($this->spotify_id);

            if ($metadata) {
                $this->update([
                    'duration_ms' => $metadata['duration_ms'],
                    'explicit' => $metadata['explicit'],
                    'preview_url' => $metadata['preview_url'],
                    'external_urls' => $metadata['external_urls'],
                ]);

                return true;
            }
        } catch (\Exception $e) {
            logger()->error('Failed to sync track metadata', [
                'track_id' => $this->id,
                'error' => $e->getMessage(),
            ]);
        }

        return false;
    }
}
```

## Testing Custom Methods

### Custom Method Test Suite

```php
<?php
// tests/Feature/CustomMethodsTest.php

use App\Models\Artist;
use App\Models\Album;
use App\Models\Track;
use Tests\TestCase;

class CustomMethodsTest extends TestCase
{
    public function test_artist_can_release_album(): void
    {
        $artist = Artist::factory()->create(['is_active' => true]);

        $this->assertTrue($artist->canReleaseAlbum());

        // Create 10 unreleased albums
        Album::factory()->count(10)->create([
            'artist_id' => $artist->id,
            'status' => 'unreleased'
        ]);

        $this->assertFalse($artist->fresh()->canReleaseAlbum());
    }

    public function test_artist_career_span_calculation(): void
    {
        $artist = Artist::factory()->create();

        Album::factory()->create([
            'artist_id' => $artist->id,
            'release_date' => '2010-01-01'
        ]);

        Album::factory()->create([
            'artist_id' => $artist->id,
            'release_date' => '2020-01-01'
        ]);

        $careerSpan = $artist->getCareerSpan();

        $this->assertEquals(2010, $careerSpan['start_year']);
        $this->assertEquals(2020, $careerSpan['end_year']);
        $this->assertEquals(11, $careerSpan['years_active']);
    }

    public function test_album_format_detection(): void
    {
        $album = Album::factory()->create();

        // Create single (1 track, short duration)
        Track::factory()->create([
            'album_id' => $album->id,
            'duration_ms' => 180000 // 3 minutes
        ]);

        $formatInfo = $album->getFormatInfo();
        $this->assertEquals('single', $formatInfo['format']);

        // Add more tracks to make it an EP
        Track::factory()->count(4)->create([
            'album_id' => $album->id,
            'duration_ms' => 240000 // 4 minutes each
        ]);

        $formatInfo = $album->fresh()->getFormatInfo();
        $this->assertEquals('ep', $formatInfo['format']);
    }

    public function test_track_mood_analysis(): void
    {
        $track = Track::factory()->create();

        $moodAnalysis = $track->getMoodAnalysis();

        $this->assertArrayHasKey('energy', $moodAnalysis);
        $this->assertArrayHasKey('valence', $moodAnalysis);
        $this->assertArrayHasKey('danceability', $moodAnalysis);
        $this->assertArrayHasKey('mood_tags', $moodAnalysis);

        $this->assertIsFloat($moodAnalysis['energy']);
        $this->assertIsArray($moodAnalysis['mood_tags']);
    }

    public function test_category_popularity_calculation(): void
    {
        $category = Category::factory()->create();

        // Create some usage
        Artist::factory()->count(5)->create()->each(function ($artist) use ($category) {
            $artist->categories()->attach($category->id);
        });

        $popularityScore = $category->calculatePopularityScore();

        $this->assertIsFloat($popularityScore);
        $this->assertGreaterThan(0, $popularityScore);
    }

    public function test_artist_data_integrity_validation(): void
    {
        $artist = Artist::factory()->create(['name' => '']);

        $errors = $artist->validateDataIntegrity();

        $this->assertContains('Artist name is required', $errors);
    }
}
```

## Best Practices

### Custom Method Guidelines

1. **Single Responsibility**: Each method should have one clear purpose
2. **Performance**: Cache expensive calculations and use efficient queries
3. **Validation**: Always validate inputs and handle edge cases
4. **Documentation**: Document complex business logic clearly
5. **Testing**: Write comprehensive tests for all custom methods
6. **Error Handling**: Implement proper error handling and logging

### Implementation Checklist

```php
<?php
// Custom methods implementation checklist

/*
✓ Implement business logic methods for domain operations
✓ Create helper methods for common calculations
✓ Add utility methods for data processing
✓ Implement validation methods for data integrity
✓ Create calculation methods for complex metrics
✓ Add integration methods for external services
✓ Write comprehensive test coverage
✓ Document complex method logic
✓ Optimize performance with caching
✓ Implement proper error handling
✓ Use consistent naming conventions
✓ Follow single responsibility principle
*/
```

## Navigation

**← Previous:** [Model Events Guide](140-model-events.md)

**Related Guides:**
- [Model Architecture Guide](010-model-architecture.md) - Foundation model patterns
- [Advanced Features Guide](../../050-chinook-advanced-features-guide.md) - Domain logic patterns
- [Performance Optimization](../deployment/050-performance-optimization.md) - Utility function patterns

---

*This guide provides comprehensive custom method implementation for Laravel 12 models in the Chinook application. The system includes business logic encapsulation, helper methods, utility functions, and domain-specific operations with performance optimization.*
