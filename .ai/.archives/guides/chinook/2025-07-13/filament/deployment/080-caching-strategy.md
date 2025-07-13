# Caching Strategy Guide

## Table of Contents

- [Overview](#overview)
- [Redis Configuration](#redis-configuration)
- [Application Caching](#application-caching)
- [Database Query Caching](#database-query-caching)
- [Session & User Caching](#session--user-caching)
- [API Response Caching](#api-response-caching)
- [File & Asset Caching](#file--asset-caching)
- [Cache Invalidation](#cache-invalidation)
- [Monitoring & Optimization](#monitoring--optimization)
- [Navigation](#navigation)

## Overview

This comprehensive caching strategy guide provides optimized caching solutions for the Chinook music database system
using Filament. The strategy focuses on multi-layer caching to improve performance, reduce database load, and enhance
user experience.

**Caching Goals:**

- **Cache Hit Ratio**: > 95% for frequently accessed data
- **Response Time**: < 50ms for cached responses
- **Memory Usage**: Efficient Redis memory utilization
- **Database Load**: 80% reduction in database queries

## Redis Configuration

### Production Redis Setup

```bash
# /etc/redis/redis.conf

# Basic configuration
bind 127.0.0.1
port 6379
timeout 300
tcp-keepalive 60

# Memory management
maxmemory 2gb
maxmemory-policy allkeys-lru
maxmemory-samples 5

# Persistence
save 900 1
save 300 10
save 60 10000
stop-writes-on-bgsave-error yes
rdbcompression yes
rdbchecksum yes
dbfilename chinook-dump.rdb
dir /var/lib/redis

# AOF persistence
appendonly yes
appendfilename "chinook-appendonly.aof"
appendfsync everysec
no-appendfsync-on-rewrite no
auto-aof-rewrite-percentage 100
auto-aof-rewrite-min-size 64mb

# Logging
loglevel notice
logfile /var/log/redis/redis-server.log

# Security
requirepass your_secure_password

# Performance tuning
tcp-backlog 511
databases 16
hz 10
```

### Laravel Redis Configuration

```php
// config/database.php
'redis' => [
    'client' => env('REDIS_CLIENT', 'phpredis'),
    
    'options' => [
        'cluster' => env('REDIS_CLUSTER', 'redis'),
        'prefix' => env('REDIS_PREFIX', Str::slug(env('APP_NAME', 'laravel'), '_').'_database_'),
        'serializer' => Redis::SERIALIZER_IGBINARY,
        'compression' => Redis::COMPRESSION_LZ4,
    ],
    
    'default' => [
        'url' => env('REDIS_URL'),
        'host' => env('REDIS_HOST', '127.0.0.1'),
        'password' => env('REDIS_PASSWORD'),
        'port' => env('REDIS_PORT', '6379'),
        'database' => env('REDIS_DB', '0'),
        'read_write_timeout' => 60,
        'context' => [
            'auth' => [env('REDIS_PASSWORD'), env('REDIS_USERNAME', 'default')],
        ],
    ],
    
    'cache' => [
        'url' => env('REDIS_URL'),
        'host' => env('REDIS_HOST', '127.0.0.1'),
        'password' => env('REDIS_PASSWORD'),
        'port' => env('REDIS_PORT', '6379'),
        'database' => env('REDIS_CACHE_DB', '1'),
    ],
    
    'session' => [
        'url' => env('REDIS_URL'),
        'host' => env('REDIS_HOST', '127.0.0.1'),
        'password' => env('REDIS_PASSWORD'),
        'port' => env('REDIS_PORT', '6379'),
        'database' => env('REDIS_SESSION_DB', '2'),
    ],
    
    'queue' => [
        'url' => env('REDIS_URL'),
        'host' => env('REDIS_HOST', '127.0.0.1'),
        'password' => env('REDIS_PASSWORD'),
        'port' => env('REDIS_PORT', '6379'),
        'database' => env('REDIS_QUEUE_DB', '3'),
    ],
],
```

### Cache Store Configuration

```php
// config/cache.php
'stores' => [
    'redis' => [
        'driver' => 'redis',
        'connection' => 'cache',
        'lock_connection' => 'default',
    ],
    
    'redis_tags' => [
        'driver' => 'redis',
        'connection' => 'cache',
        'lock_connection' => 'default',
    ],
    
    'file' => [
        'driver' => 'file',
        'path' => storage_path('framework/cache/data'),
    ],
    
    'array' => [
        'driver' => 'array',
        'serialize' => false,
    ],
],

'prefix' => env('CACHE_PREFIX', Str::slug(env('APP_NAME', 'laravel'), '_').'_cache'),
```

## Application Caching

### Model Caching Strategy

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Track extends Model
{
    protected static function boot()
    {
        parent::boot();

        // Clear cache when model is updated
        static::saved(function ($track) {
            Cache::tags(['tracks', "track:{$track->id}", 'music'])->flush();
        });

        static::deleted(function ($track) {
            Cache::tags(['tracks', "track:{$track->id}", 'music'])->flush();
        });
    }

    // Cache popular tracks
    public static function getPopularTracks(int $limit = 10): Collection
    {
        return Cache::tags(['tracks', 'popular'])
            ->remember("popular_tracks:{$limit}", 3600, function () use ($limit) {
                return static::withCount('invoiceLines')
                    ->orderBy('invoice_lines_count', 'desc')
                    ->limit($limit)
                    ->get();
            });
    }

    // Cache track with relationships
    public function getCachedWithRelations(): self
    {
        return Cache::tags(['tracks', "track:{$this->id}"])
            ->remember("track_full:{$this->id}", 1800, function () {
                return $this->load(['album.artist', 'mediaType', 'categories']);
            });
    }

    // Cache track statistics
    public function getStatistics(): array
    {
        return Cache::tags(['tracks', "track:{$this->id}", 'stats'])
            ->remember("track_stats:{$this->id}", 7200, function () {
                return [
                    'total_sales' => $this->invoiceLines()->count(),
                    'total_revenue' => $this->invoiceLines()->sum('total'),
                    'playlist_count' => $this->playlists()->count(),
                    'average_rating' => $this->reviews()->avg('rating'),
                ];
            });
    }
}
```

### Service Layer Caching

```php
<?php

namespace App\Services;

use App\Models\Track;
use App\Models\Album;
use App\Models\Artist;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Collection;

class MusicCacheService
{
    private int $defaultTtl = 3600; // 1 hour
    private int $longTtl = 86400;   // 24 hours

    public function getTopCharts(string $period = 'week'): array
    {
        return Cache::tags(['charts', $period])
            ->remember("top_charts:{$period}", $this->defaultTtl, function () use ($period) {
                $days = match($period) {
                    'day' => 1,
                    'week' => 7,
                    'month' => 30,
                    'year' => 365,
                    default => 7,
                };

                return [
                    'tracks' => $this->getTopTracks($days),
                    'albums' => $this->getTopAlbums($days),
                    'artists' => $this->getTopArtists($days),
                ];
            });
    }

    public function getGenreStatistics(): array
    {
        return Cache::tags(['genres', 'stats'])
            ->remember('genre_statistics', $this->longTtl, function () {
                return DB::select("
                    SELECT 
                        c.name as genre,
                        COUNT(DISTINCT t.id) as track_count,
                        COUNT(DISTINCT a.id) as album_count,
                        COUNT(DISTINCT ar.id) as artist_count,
                        SUM(il.total) as total_revenue
                    FROM categories c
                    INNER JOIN categorizables cat ON c.id = cat.category_id
                    INNER JOIN tracks t ON cat.categorizable_id = t.id AND cat.categorizable_type = 'App\\Models\\Track'
                    INNER JOIN albums a ON t.album_id = a.id
                    INNER JOIN artists ar ON a.artist_id = ar.id
                    LEFT JOIN invoice_lines il ON t.id = il.track_id
                    WHERE c.type = 'GENRE'
                    GROUP BY c.id, c.name
                    ORDER BY total_revenue DESC
                ");
            });
    }

    public function getArtistDiscography(int $artistId): array
    {
        return Cache::tags(['artists', "artist:{$artistId}", 'discography'])
            ->remember("artist_discography:{$artistId}", $this->defaultTtl, function () use ($artistId) {
                $artist = Artist::with([
                    'albums.tracks.categories',
                    'albums.tracks.mediaType'
                ])->find($artistId);

                return [
                    'artist' => $artist,
                    'albums' => $artist->albums->map(function ($album) {
                        return [
                            'id' => $album->id,
                            'title' => $album->title,
                            'release_date' => $album->release_date,
                            'track_count' => $album->tracks->count(),
                            'total_duration' => $album->tracks->sum('milliseconds'),
                            'genres' => $album->tracks->flatMap->categories
                                ->where('type', 'GENRE')
                                ->unique('id')
                                ->pluck('name'),
                        ];
                    }),
                    'statistics' => [
                        'total_albums' => $artist->albums->count(),
                        'total_tracks' => $artist->albums->sum(fn($album) => $album->tracks->count()),
                        'career_span' => $this->calculateCareerSpan($artist->albums),
                    ],
                ];
            });
    }

    private function getTopTracks(int $days): Collection
    {
        return Track::select([
                'tracks.id',
                'tracks.name',
                'albums.title as album_title',
                'artists.name as artist_name',
                'COUNT(il.id) as sales_count'
            ])
            ->join('albums', 'tracks.album_id', '=', 'albums.id')
            ->join('artists', 'albums.artist_id', '=', 'artists.id')
            ->join('invoice_lines', 'tracks.id', '=', 'invoice_lines.track_id')
            ->join('invoices', 'invoice_lines.invoice_id', '=', 'invoices.id')
            ->where('invoices.invoice_date', '>=', now()->subDays($days))
            ->groupBy('tracks.id', 'tracks.name', 'albums.title', 'artists.name')
            ->orderBy('sales_count', 'desc')
            ->limit(50)
            ->get();
    }

    private function calculateCareerSpan(Collection $albums): array
    {
        $dates = $albums->pluck('release_date')->filter();
        
        if ($dates->isEmpty()) {
            return ['start' => null, 'end' => null, 'years' => 0];
        }

        $start = $dates->min();
        $end = $dates->max();
        $years = $start->diffInYears($end);

        return [
            'start' => $start->year,
            'end' => $end->year,
            'years' => $years,
        ];
    }
}
```

## Database Query Caching

### Query Result Caching

```php
<?php

namespace App\Http\Controllers\Api;

use App\Models\Track;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Spatie\QueryBuilder\QueryBuilder;

class TrackController extends Controller
{
    public function index(Request $request)
    {
        // Create cache key from request parameters
        $cacheKey = 'tracks_index:' . md5(serialize($request->all()));
        
        $tracks = Cache::tags(['tracks', 'api'])
            ->remember($cacheKey, 900, function () use ($request) { // 15 minutes
                return QueryBuilder::for(Track::class)
                    ->allowedFilters(['name', 'album.title', 'album.artist.name'])
                    ->allowedSorts(['name', 'unit_price', 'created_at'])
                    ->allowedIncludes(['album.artist', 'mediaType', 'categories'])
                    ->with(['album.artist', 'mediaType'])
                    ->paginate($request->get('per_page', 15));
            });

        return response()->json($tracks);
    }

    public function show(Track $track)
    {
        $trackData = Cache::tags(['tracks', "track:{$track->id}"])
            ->remember("track_detail:{$track->id}", 1800, function () use ($track) {
                return $track->load([
                    'album.artist',
                    'mediaType',
                    'categories',
                    'playlists' => function ($query) {
                        $query->where('is_public', true)->limit(5);
                    }
                ]);
            });

        return response()->json($trackData);
    }
}
```

### Filament Resource Caching

```php
<?php

namespace App\Filament\Resources;

use App\Models\Track;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;

class TrackResource extends Resource
{
    protected static ?string $model = Track::class;

    public static function getEloquentQuery(): Builder
    {
        // Cache the base query for resource listings
        return Cache::tags(['tracks', 'filament'])
            ->remember('tracks_filament_query', 300, function () {
                return parent::getEloquentQuery()
                    ->with(['album.artist', 'mediaType'])
                    ->select([
                        'tracks.id',
                        'tracks.name',
                        'tracks.unit_price',
                        'tracks.milliseconds',
                        'tracks.album_id',
                        'tracks.media_type_id',
                        'tracks.created_at',
                    ]);
            });
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('album.title')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('album.artist.name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('unit_price')
                    ->money('USD')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->paginate([25, 50, 100]);
    }
}
```

## Session & User Caching

### User Session Optimization

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class CacheUserData
{
    public function handle(Request $request, Closure $next)
    {
        if ($request->user()) {
            $userId = $request->user()->id;
            
            // Cache user permissions
            $permissions = Cache::tags(['users', "user:{$userId}", 'permissions'])
                ->remember("user_permissions:{$userId}", 3600, function () use ($request) {
                    return $request->user()->getAllPermissions()->pluck('name');
                });
            
            // Cache user preferences
            $preferences = Cache::tags(['users', "user:{$userId}", 'preferences'])
                ->remember("user_preferences:{$userId}", 7200, function () use ($request) {
                    return $request->user()->preferences ?? [];
                });
            
            // Share cached data with views
            view()->share('userPermissions', $permissions);
            view()->share('userPreferences', $preferences);
        }

        return $next($request);
    }
}
```

### Authentication Caching

```php
<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasRoles;

    protected static function boot()
    {
        parent::boot();

        static::saved(function ($user) {
            Cache::tags(['users', "user:{$user->id}"])->flush();
        });

        static::deleted(function ($user) {
            Cache::tags(['users', "user:{$user->id}"])->flush();
        });
    }

    public function getCachedRoles(): Collection
    {
        return Cache::tags(['users', "user:{$this->id}", 'roles'])
            ->remember("user_roles:{$this->id}", 3600, function () {
                return $this->roles;
            });
    }

    public function getCachedPermissions(): Collection
    {
        return Cache::tags(['users', "user:{$this->id}", 'permissions'])
            ->remember("user_permissions:{$this->id}", 3600, function () {
                return $this->getAllPermissions();
            });
    }

    public function hasPermissionCached(string $permission): bool
    {
        $permissions = $this->getCachedPermissions();
        return $permissions->contains('name', $permission);
    }
}
```

## API Response Caching

### HTTP Cache Headers

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class SetCacheHeaders
{
    private array $cacheRules = [
        'api/tracks' => ['public', 'max-age=900'], // 15 minutes
        'api/albums' => ['public', 'max-age=1800'], // 30 minutes
        'api/artists' => ['public', 'max-age=3600'], // 1 hour
        'api/charts' => ['public', 'max-age=300'], // 5 minutes
    ];

    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        if ($response instanceof Response) {
            $path = $request->path();
            
            foreach ($this->cacheRules as $pattern => $rules) {
                if (str_starts_with($path, $pattern)) {
                    $response->headers->set('Cache-Control', implode(', ', $rules));
                    $response->headers->set('Vary', 'Accept, Accept-Encoding');
                    
                    // Add ETag for conditional requests
                    $etag = md5($response->getContent());
                    $response->headers->set('ETag', $etag);
                    
                    // Check if client has cached version
                    if ($request->header('If-None-Match') === $etag) {
                        return response('', 304);
                    }
                    
                    break;
                }
            }
        }

        return $response;
    }
}
```

### API Response Caching Service

```php
<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class ApiCacheService
{
    private array $cacheTtl = [
        'tracks' => 900,    // 15 minutes
        'albums' => 1800,   // 30 minutes
        'artists' => 3600,  // 1 hour
        'charts' => 300,    // 5 minutes
        'search' => 600,    // 10 minutes
    ];

    public function getCachedResponse(Request $request, string $type, callable $callback)
    {
        $cacheKey = $this->generateCacheKey($request, $type);
        $ttl = $this->cacheTtl[$type] ?? 900;

        return Cache::tags(['api', $type])
            ->remember($cacheKey, $ttl, $callback);
    }

    public function invalidateApiCache(string $type, ?string $id = null): void
    {
        if ($id) {
            Cache::tags(['api', $type, "{$type}:{$id}"])->flush();
        } else {
            Cache::tags(['api', $type])->flush();
        }
    }

    private function generateCacheKey(Request $request, string $type): string
    {
        $params = $request->all();
        ksort($params); // Ensure consistent key generation
        
        return sprintf(
            'api:%s:%s:%s',
            $type,
            $request->path(),
            md5(serialize($params))
        );
    }
}
```

## File & Asset Caching

### Static Asset Caching

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

class StaticAssetCache
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        // Cache static assets for 1 year
        if ($this->isStaticAsset($request)) {
            $response->headers->set('Cache-Control', 'public, max-age=31536000, immutable');
            $response->headers->set('Expires', now()->addYear()->toRfc7231String());
        }

        return $response;
    }

    private function isStaticAsset(Request $request): bool
    {
        $path = $request->path();
        $extensions = ['css', 'js', 'png', 'jpg', 'jpeg', 'gif', 'svg', 'woff', 'woff2', 'ttf', 'eot'];

        return collect($extensions)->contains(function ($ext) use ($path) {
            return str_ends_with($path, '.' . $ext);
        });
    }
}
```

### File Upload Caching

```php
<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Facades\Image;

class FileCache
{
    public function cacheUploadedFile(UploadedFile $file, string $disk = 'public'): string
    {
        $hash = hash_file('sha256', $file->getRealPath());
        $extension = $file->getClientOriginalExtension();
        $filename = $hash . '.' . $extension;

        // Check if file already exists
        if (Storage::disk($disk)->exists($filename)) {
            return $filename;
        }

        // Store file
        $path = $file->storeAs('', $filename, $disk);

        // Cache file metadata
        Cache::put("file_meta:{$hash}", [
            'original_name' => $file->getClientOriginalName(),
            'size' => $file->getSize(),
            'mime_type' => $file->getMimeType(),
            'path' => $path,
            'created_at' => now(),
        ], now()->addDays(30));

        return $path;
    }

    public function generateThumbnail(string $imagePath, int $width = 300, int $height = 300): string
    {
        $cacheKey = "thumbnail:{$imagePath}:{$width}x{$height}";

        return Cache::remember($cacheKey, now()->addDays(7), function () use ($imagePath, $width, $height) {
            $thumbnailPath = 'thumbnails/' . pathinfo($imagePath, PATHINFO_FILENAME) . "_{$width}x{$height}." . pathinfo($imagePath, PATHINFO_EXTENSION);

            if (!Storage::exists($thumbnailPath)) {
                $image = Image::make(Storage::path($imagePath))
                    ->fit($width, $height)
                    ->encode();

                Storage::put($thumbnailPath, $image);
            }

            return $thumbnailPath;
        });
    }

    public function getCachedFileUrl(string $path): string
    {
        $cacheKey = "file_url:{$path}";

        return Cache::remember($cacheKey, now()->addHours(24), function () use ($path) {
            return Storage::url($path);
        });
    }
}
```

### CDN Integration

```php
<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class CdnCache
{
    private string $cdnUrl;
    private string $apiKey;

    public function __construct()
    {
        $this->cdnUrl = config('cdn.url');
        $this->apiKey = config('cdn.api_key');
    }

    public function purgeCache(array $urls): bool
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->apiKey,
            'Content-Type' => 'application/json',
        ])->post($this->cdnUrl . '/purge', [
            'files' => $urls,
        ]);

        return $response->successful();
    }

    public function preloadAssets(array $assets): bool
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->apiKey,
            'Content-Type' => 'application/json',
        ])->post($this->cdnUrl . '/preload', [
            'assets' => $assets,
        ]);

        return $response->successful();
    }

    public function getCdnUrl(string $asset): string
    {
        $cacheKey = "cdn_url:{$asset}";

        return Cache::remember($cacheKey, now()->addHours(12), function () use ($asset) {
            return $this->cdnUrl . '/' . ltrim($asset, '/');
        });
    }
}
```

### Asset Versioning

```php
<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;

class AssetVersioning
{
    public function getVersionedAsset(string $asset): string
    {
        $cacheKey = "asset_version:{$asset}";

        return Cache::remember($cacheKey, now()->addDays(1), function () use ($asset) {
            $path = public_path($asset);

            if (!File::exists($path)) {
                return $asset;
            }

            $version = hash_file('md5', $path);
            $extension = pathinfo($asset, PATHINFO_EXTENSION);
            $filename = pathinfo($asset, PATHINFO_FILENAME);
            $directory = dirname($asset);

            return ($directory !== '.' ? $directory . '/' : '') .
                   $filename . '.' . substr($version, 0, 8) . '.' . $extension;
        });
    }

    public function generateManifest(): array
    {
        $assets = [
            'css/app.css',
            'js/app.js',
            'css/filament.css',
            'js/filament.js',
        ];

        $manifest = [];

        foreach ($assets as $asset) {
            $manifest[$asset] = $this->getVersionedAsset($asset);
        }

        Cache::put('asset_manifest', $manifest, now()->addDays(1));

        return $manifest;
    }
}
```

## Cache Invalidation

### Event-Based Cache Invalidation

```php
<?php

namespace App\Observers;

use App\Models\Track;
use Illuminate\Support\Facades\Cache;

class TrackObserver
{
    public function created(Track $track): void
    {
        $this->invalidateRelatedCaches($track);
    }

    public function updated(Track $track): void
    {
        $this->invalidateRelatedCaches($track);
    }

    public function deleted(Track $track): void
    {
        $this->invalidateRelatedCaches($track);
    }

    private function invalidateRelatedCaches(Track $track): void
    {
        // Invalidate track-specific caches
        Cache::tags(['tracks', "track:{$track->id}"])->flush();
        
        // Invalidate album caches
        if ($track->album_id) {
            Cache::tags(['albums', "album:{$track->album_id}"])->flush();
        }
        
        // Invalidate artist caches
        if ($track->album && $track->album->artist_id) {
            Cache::tags(['artists', "artist:{$track->album->artist_id}"])->flush();
        }
        
        // Invalidate general music caches
        Cache::tags(['music', 'charts', 'popular'])->flush();
        
        // Invalidate API caches
        Cache::tags(['api', 'tracks'])->flush();
    }
}
```

### Scheduled Cache Cleanup

```php
<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;

class CacheCleanup extends Command
{
    protected $signature = 'cache:cleanup {--dry-run : Show what would be cleaned without actually cleaning}';
    protected $description = 'Clean up expired and unused cache entries';

    public function handle()
    {
        $dryRun = $this->option('dry-run');
        
        $this->info('Starting cache cleanup...');
        
        // Clean expired keys
        $expiredKeys = $this->findExpiredKeys();
        $this->info("Found {$expiredKeys->count()} expired keys");
        
        if (!$dryRun) {
            $expiredKeys->each(function ($key) {
                Cache::forget($key);
            });
            $this->info('Expired keys cleaned');
        }
        
        // Clean unused API cache entries
        $unusedApiKeys = $this->findUnusedApiKeys();
        $this->info("Found {$unusedApiKeys->count()} unused API cache keys");
        
        if (!$dryRun) {
            Cache::tags(['api'])->flush();
            $this->info('Unused API cache cleaned');
        }
        
        // Optimize Redis memory
        if (!$dryRun) {
            Redis::command('MEMORY', ['PURGE']);
            $this->info('Redis memory optimized');
        }
        
        $this->info('Cache cleanup completed');
    }

    private function findExpiredKeys(): Collection
    {
        $keys = Redis::keys('*');
        $expiredKeys = collect();
        
        foreach ($keys as $key) {
            $ttl = Redis::ttl($key);
            if ($ttl === -2) { // Key doesn't exist or expired
                $expiredKeys->push($key);
            }
        }
        
        return $expiredKeys;
    }

    private function findUnusedApiKeys(): Collection
    {
        // Find API cache keys older than 1 hour
        $cutoff = now()->subHour()->timestamp;
        $apiKeys = Redis::keys('*api:*');
        
        return collect($apiKeys)->filter(function ($key) use ($cutoff) {
            $lastAccess = Redis::object('idletime', $key);
            return $lastAccess && $lastAccess > 3600; // 1 hour in seconds
        });
    }
}
```

## Monitoring & Optimization

### Cache Performance Monitoring

```php
<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;

class CacheMonitor extends Command
{
    protected $signature = 'cache:monitor';
    protected $description = 'Monitor cache performance and statistics';

    public function handle()
    {
        $info = Redis::info();
        $stats = $this->calculateCacheStats($info);
        
        $this->displayCacheStats($stats);
        $this->displayMemoryUsage($info);
        $this->displayHitRatio($info);
        $this->displayTopKeys();
    }

    private function calculateCacheStats(array $info): array
    {
        return [
            'connected_clients' => $info['connected_clients'] ?? 0,
            'used_memory_human' => $info['used_memory_human'] ?? '0B',
            'used_memory_peak_human' => $info['used_memory_peak_human'] ?? '0B',
            'keyspace_hits' => $info['keyspace_hits'] ?? 0,
            'keyspace_misses' => $info['keyspace_misses'] ?? 0,
            'total_commands_processed' => $info['total_commands_processed'] ?? 0,
            'instantaneous_ops_per_sec' => $info['instantaneous_ops_per_sec'] ?? 0,
        ];
    }

    private function displayCacheStats(array $stats): void
    {
        $this->table(
            ['Metric', 'Value'],
            [
                ['Connected Clients', $stats['connected_clients']],
                ['Memory Used', $stats['used_memory_human']],
                ['Peak Memory', $stats['used_memory_peak_human']],
                ['Commands/sec', $stats['instantaneous_ops_per_sec']],
                ['Total Commands', number_format($stats['total_commands_processed'])],
            ]
        );
    }

    private function displayHitRatio(array $info): void
    {
        $hits = $info['keyspace_hits'] ?? 0;
        $misses = $info['keyspace_misses'] ?? 0;
        $total = $hits + $misses;
        
        if ($total > 0) {
            $hitRatio = ($hits / $total) * 100;
            $this->info("Cache Hit Ratio: " . number_format($hitRatio, 2) . "%");
            
            if ($hitRatio < 90) {
                $this->warn("Low cache hit ratio detected!");
            }
        }
    }

    private function displayTopKeys(): void
    {
        $this->info("\nTop Cache Keys by Memory Usage:");
        
        // This would require Redis modules or custom tracking
        // For now, show a sample of key patterns
        $keyPatterns = [
            'tracks:*' => Redis::eval("return #redis.call('keys', 'tracks:*')", 0),
            'albums:*' => Redis::eval("return #redis.call('keys', 'albums:*')", 0),
            'artists:*' => Redis::eval("return #redis.call('keys', 'artists:*')", 0),
            'api:*' => Redis::eval("return #redis.call('keys', 'api:*')", 0),
        ];
        
        $this->table(
            ['Pattern', 'Count'],
            collect($keyPatterns)->map(function ($count, $pattern) {
                return [$pattern, $count];
            })->toArray()
        );
    }
}
```

---

## Navigation

**← Previous:** [Asset Optimization Guide](070-asset-optimization.md)

**Next →** [Monitoring Setup Guide](090-monitoring-setup.md)

**↑ Back to:** [Deployment Index](000-deployment-index.md)
