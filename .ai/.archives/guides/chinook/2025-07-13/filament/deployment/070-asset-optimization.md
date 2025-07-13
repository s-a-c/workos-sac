# Asset Optimization Guide

## Table of Contents

- [Overview](#overview)
- [Vite Configuration](#vite-configuration)
- [CSS Optimization](#css-optimization)
- [JavaScript Optimization](#javascript-optimization)
- [Image Optimization](#image-optimization)
- [Font Optimization](#font-optimization)
- [CDN Integration](#cdn-integration)
- [Performance Monitoring](#performance-monitoring)
- [Navigation](#navigation)

## Overview

This guide provides comprehensive asset optimization strategies for Filament applications in the Chinook music database
system. These optimizations focus on reducing bundle sizes, improving load times, and enhancing the overall user
experience through efficient asset delivery.

**Optimization Goals:**

- **Bundle Size**: < 500KB for main JavaScript bundle
- **CSS Size**: < 200KB for main stylesheet
- **Image Optimization**: 80%+ size reduction with WebP format
- **Load Time**: < 2 seconds for first contentful paint

## Vite Configuration

### Production Build Configuration

Optimize Vite for production builds:

```javascript
// vite.config.js
import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import { resolve } from 'path';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
                'resources/css/filament/admin/theme.css',
            ],
            refresh: true,
        }),
    ],
    
    build: {
        // Output directory
        outDir: 'public/build',
        
        // Asset file names
        assetsDir: 'assets',
        
        // Rollup options
        rollupOptions: {
            output: {
                // Manual chunks for better caching
                manualChunks: {
                    // Vendor libraries
                    vendor: [
                        'alpinejs',
                        '@alpinejs/focus',
                        '@alpinejs/collapse',
                    ],
                    
                    // Filament core
                    filament: [
                        '@filament/forms',
                        '@filament/tables',
                        '@filament/notifications',
                    ],
                    
                    // Chart libraries
                    charts: [
                        'chart.js',
                        'chartjs-adapter-date-fns',
                    ],
                },
                
                // Asset file naming
                chunkFileNames: 'assets/js/[name]-[hash].js',
                entryFileNames: 'assets/js/[name]-[hash].js',
                assetFileNames: (assetInfo) => {
                    const info = assetInfo.name.split('.');
                    const ext = info[info.length - 1];
                    
                    if (/\.(png|jpe?g|svg|gif|tiff|bmp|ico)$/i.test(assetInfo.name)) {
                        return `assets/images/[name]-[hash].${ext}`;
                    }
                    
                    if (/\.(woff2?|eot|ttf|otf)$/i.test(assetInfo.name)) {
                        return `assets/fonts/[name]-[hash].${ext}`;
                    }
                    
                    return `assets/${ext}/[name]-[hash].${ext}`;
                },
            },
        },
        
        // Minification
        minify: 'terser',
        terserOptions: {
            compress: {
                drop_console: true,
                drop_debugger: true,
                pure_funcs: ['console.log', 'console.info'],
            },
            mangle: {
                safari10: true,
            },
        },
        
        // Source maps (disable in production)
        sourcemap: false,
        
        // Chunk size warning limit
        chunkSizeWarningLimit: 1000,
        
        // Asset inlining threshold
        assetsInlineLimit: 4096,
    },
    
    // CSS preprocessing
    css: {
        postcss: {
            plugins: [
                require('tailwindcss'),
                require('autoprefixer'),
                require('cssnano')({
                    preset: ['default', {
                        discardComments: { removeAll: true },
                        normalizeWhitespace: true,
                    }],
                }),
            ],
        },
    },
    
    // Development server
    server: {
        hmr: {
            host: 'localhost',
        },
        watch: {
            usePolling: true,
        },
    },
    
    // Resolve aliases
    resolve: {
        alias: {
            '@': resolve(__dirname, 'resources'),
            '@images': resolve(__dirname, 'resources/images'),
            '@components': resolve(__dirname, 'resources/js/components'),
        },
    },
});
```

### Environment-Specific Configuration

```javascript
// vite.config.production.js
import { defineConfig } from 'vite';
import baseConfig from './vite.config.js';

export default defineConfig({
    ...baseConfig,
    
    build: {
        ...baseConfig.build,
        
        // Production-specific optimizations
        reportCompressedSize: false,
        
        // Advanced minification
        minify: 'terser',
        terserOptions: {
            compress: {
                arguments: true,
                dead_code: true,
                drop_console: true,
                drop_debugger: true,
                keep_fargs: false,
                passes: 2,
            },
            mangle: {
                toplevel: true,
                safari10: true,
            },
            format: {
                comments: false,
            },
        },
    },
    
    // Experimental features
    experimental: {
        renderBuiltUrl(filename, { hostType }) {
            if (hostType === 'js') {
                return { js: `https://cdn.example.com/${filename}` };
            } else {
                return { relative: true };
            }
        },
    },
});
```

## CSS Optimization

### Tailwind CSS Configuration

Optimize Tailwind for production:

```javascript
// tailwind.config.js
const defaultTheme = require('tailwindcss/defaultTheme');

module.exports = {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './vendor/laravel/jetstream/**/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './vendor/filament/**/*.blade.php',
        './app/Filament/**/*.php',
        './resources/js/**/*.js',
    ],
    
    darkMode: 'class',
    
    theme: {
        extend: {
            fontFamily: {
                sans: ['Inter var', ...defaultTheme.fontFamily.sans],
            },
            
            colors: {
                // Custom Chinook brand colors
                primary: {
                    50: '#eff6ff',
                    500: '#3b82f6',
                    600: '#2563eb',
                    700: '#1d4ed8',
                    800: '#1e40af',
                    900: '#1e3a8a',
                },
            },
        },
    },
    
    plugins: [
        require('@tailwindcss/forms'),
        require('@tailwindcss/typography'),
        
        // Custom plugin for Filament optimizations
        function({ addUtilities, addComponents, theme }) {
            addUtilities({
                '.text-balance': {
                    'text-wrap': 'balance',
                },
            });
            
            addComponents({
                '.btn-primary': {
                    backgroundColor: theme('colors.primary.600'),
                    color: theme('colors.white'),
                    padding: theme('spacing.2') + ' ' + theme('spacing.4'),
                    borderRadius: theme('borderRadius.md'),
                    '&:hover': {
                        backgroundColor: theme('colors.primary.700'),
                    },
                },
            });
        },
    ],
    
    // Optimize for production
    corePlugins: {
        preflight: true,
        container: false, // Disable if not used
        accessibility: true,
    },
    
    // Purge unused styles
    safelist: [
        // Filament dynamic classes
        'fi-btn',
        'fi-ta-table',
        'fi-fo-field-wrp',
        {
            pattern: /fi-(primary|secondary|success|warning|danger|info)/,
            variants: ['hover', 'focus'],
        },
    ],
};
```

### Critical CSS Extraction

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class InlineCriticalCSS
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);
        
        if ($response->headers->get('content-type') === 'text/html; charset=UTF-8') {
            $content = $response->getContent();
            
            // Extract critical CSS for above-the-fold content
            $criticalCSS = $this->getCriticalCSS($request->path());
            
            if ($criticalCSS) {
                $content = str_replace(
                    '</head>',
                    "<style>{$criticalCSS}</style></head>",
                    $content
                );
                
                $response->setContent($content);
            }
        }
        
        return $response;
    }
    
    private function getCriticalCSS(string $path): ?string
    {
        $criticalCSSMap = [
            '/' => 'critical-home.css',
            'admin' => 'critical-admin.css',
            'admin/tracks' => 'critical-tracks.css',
            'admin/albums' => 'critical-albums.css',
        ];
        
        $filename = $criticalCSSMap[$path] ?? null;
        
        if ($filename && file_exists(public_path("css/critical/{$filename}"))) {
            return file_get_contents(public_path("css/critical/{$filename}"));
        }
        
        return null;
    }
}
```

### CSS Purging Strategy

```javascript
// postcss.config.js
module.exports = {
    plugins: [
        require('tailwindcss'),
        require('autoprefixer'),
        
        // PurgeCSS for production
        process.env.NODE_ENV === 'production' && require('@fullhuman/postcss-purgecss')({
            content: [
                './storage/framework/views/*.php',
                './resources/views/**/*.blade.php',
                './vendor/filament/**/*.blade.php',
                './app/Filament/**/*.php',
                './resources/js/**/*.js',
            ],
            
            defaultExtractor: content => content.match(/[\w-/:]+(?<!:)/g) || [],
            
            safelist: {
                standard: [
                    /^fi-/,
                    /^livewire/,
                    /^alpine/,
                    'dark',
                ],
                deep: [
                    /^fi-btn/,
                    /^fi-ta/,
                    /^fi-fo/,
                ],
                greedy: [
                    /^fi-.*-primary$/,
                    /^fi-.*-secondary$/,
                ],
            },
            
            blocklist: [
                'container',
                'prose',
            ],
        }),
        
        // CSS optimization
        process.env.NODE_ENV === 'production' && require('cssnano')({
            preset: ['default', {
                discardComments: { removeAll: true },
                normalizeWhitespace: true,
                colormin: true,
                convertValues: true,
                discardDuplicates: true,
                discardEmpty: true,
                mergeRules: true,
                minifyFontValues: true,
                minifySelectors: true,
            }],
        }),
    ].filter(Boolean),
};
```

## JavaScript Optimization

### Code Splitting Strategy

```javascript
// resources/js/app.js
import Alpine from 'alpinejs';
import Focus from '@alpinejs/focus';
import Collapse from '@alpinejs/collapse';

// Register Alpine plugins
Alpine.plugin(Focus);
Alpine.plugin(Collapse);

// Dynamic imports for heavy components
const loadChartComponents = () => import('./components/charts');
const loadMediaComponents = () => import('./components/media');

// Conditional loading based on page
if (document.querySelector('[data-chart]')) {
    loadChartComponents().then(module => {
        module.initCharts();
    });
}

if (document.querySelector('[data-media-player]')) {
    loadMediaComponents().then(module => {
        module.initMediaPlayer();
    });
}

// Initialize Alpine
window.Alpine = Alpine;
Alpine.start();
```

### Tree Shaking Configuration

```javascript
// resources/js/components/charts.js
import {
    Chart,
    CategoryScale,
    LinearScale,
    BarElement,
    LineElement,
    PointElement,
    Title,
    Tooltip,
    Legend,
} from 'chart.js';

// Register only needed components
Chart.register(
    CategoryScale,
    LinearScale,
    BarElement,
    LineElement,
    PointElement,
    Title,
    Tooltip,
    Legend
);

export function initCharts() {
    // Chart initialization logic
    const chartElements = document.querySelectorAll('[data-chart]');
    
    chartElements.forEach(element => {
        const config = JSON.parse(element.dataset.chart);
        new Chart(element, config);
    });
}
```

### Service Worker for Caching

```javascript
// public/sw.js
const CACHE_NAME = 'chinook-v1.0.0';
const urlsToCache = [
    '/',
    '/css/app.css',
    '/js/app.js',
    '/images/logo.svg',
    '/fonts/inter-var.woff2',
];

self.addEventListener('install', event => {
    event.waitUntil(
        caches.open(CACHE_NAME)
            .then(cache => cache.addAll(urlsToCache))
    );
});

self.addEventListener('fetch', event => {
    event.respondWith(
        caches.match(event.request)
            .then(response => {
                // Return cached version or fetch from network
                return response || fetch(event.request);
            })
    );
});

// Update cache when new version is available
self.addEventListener('activate', event => {
    event.waitUntil(
        caches.keys().then(cacheNames => {
            return Promise.all(
                cacheNames.map(cacheName => {
                    if (cacheName !== CACHE_NAME) {
                        return caches.delete(cacheName);
                    }
                })
            );
        })
    );
});
```

## Image Optimization

### Responsive Image Component

```php
<?php

namespace App\View\Components;

use Illuminate\View\Component;

class ResponsiveImage extends Component
{
    public function __construct(
        public string $src,
        public string $alt,
        public ?string $sizes = null,
        public ?array $breakpoints = null,
        public bool $lazy = true,
        public string $format = 'webp'
    ) {
        $this->breakpoints = $breakpoints ?? [640, 768, 1024, 1280, 1536];
    }

    public function render()
    {
        return view('components.responsive-image');
    }

    public function getSrcSet(): string
    {
        $srcSet = [];
        $basePath = pathinfo($this->src, PATHINFO_DIRNAME);
        $filename = pathinfo($this->src, PATHINFO_FILENAME);
        $extension = $this->format;

        foreach ($this->breakpoints as $width) {
            $srcSet[] = "{$basePath}/{$filename}-{$width}w.{$extension} {$width}w";
        }

        return implode(', ', $srcSet);
    }

    public function getDefaultSizes(): string
    {
        return $this->sizes ?? '(max-width: 640px) 100vw, (max-width: 1024px) 50vw, 33vw';
    }
}
```

```blade
{{-- resources/views/components/responsive-image.blade.php --}}
<picture>
    @if($format === 'webp')
        <source 
            srcset="{{ $getSrcSet() }}" 
            sizes="{{ $getDefaultSizes() }}" 
            type="image/webp"
        >
    @endif
    
    <img 
        src="{{ $src }}" 
        srcset="{{ $getSrcSet() }}" 
        sizes="{{ $getDefaultSizes() }}" 
        alt="{{ $alt }}"
        @if($lazy)
            loading="lazy"
            decoding="async"
        @endif
        class="w-full h-auto"
    >
</picture>
```

### Image Processing Pipeline

```php
<?php

namespace App\Services;

use Intervention\Image\Facades\Image;
use Illuminate\Support\Facades\Storage;

class ImageOptimizationService
{
    private array $breakpoints = [640, 768, 1024, 1280, 1536];
    private array $formats = ['webp', 'jpg'];
    private int $quality = 85;

    public function processImage(string $imagePath): array
    {
        $processedImages = [];
        $originalImage = Image::make(Storage::path($imagePath));
        
        foreach ($this->breakpoints as $width) {
            foreach ($this->formats as $format) {
                $processedPath = $this->generateResponsiveImage(
                    $originalImage, 
                    $imagePath, 
                    $width, 
                    $format
                );
                
                $processedImages[$format][$width] = $processedPath;
            }
        }
        
        return $processedImages;
    }

    private function generateResponsiveImage($image, string $originalPath, int $width, string $format): string
    {
        $pathInfo = pathinfo($originalPath);
        $filename = $pathInfo['filename'] . "-{$width}w.{$format}";
        $directory = $pathInfo['dirname'];
        $newPath = "{$directory}/{$filename}";
        
        $resizedImage = clone $image;
        $resizedImage->resize($width, null, function ($constraint) {
            $constraint->aspectRatio();
            $constraint->upsize();
        });
        
        // Apply format-specific optimizations
        switch ($format) {
            case 'webp':
                $resizedImage->encode('webp', $this->quality);
                break;
            case 'jpg':
                $resizedImage->encode('jpg', $this->quality);
                break;
        }
        
        Storage::put($newPath, $resizedImage->stream());
        
        return $newPath;
    }

    public function optimizeExistingImages(): void
    {
        $images = Storage::files('public/images');
        
        foreach ($images as $image) {
            if (in_array(pathinfo($image, PATHINFO_EXTENSION), ['jpg', 'jpeg', 'png'])) {
                $this->processImage($image);
            }
        }
    }
}
```

## Font Optimization

### Font Loading Strategy

```css
/* resources/css/fonts.css */
@font-face {
    font-family: 'Inter var';
    font-style: normal;
    font-weight: 100 900;
    font-display: swap;
    src: url('/fonts/inter-var.woff2') format('woff2');
    unicode-range: U+0000-00FF, U+0131, U+0152-0153, U+02BB-02BC, U+02C6, U+02DA, U+02DC, U+2000-206F, U+2074, U+20AC, U+2122, U+2191, U+2193, U+2212, U+2215, U+FEFF, U+FFFD;
}

/* Preload critical fonts */
.font-preload {
    font-family: 'Inter var', system-ui, -apple-system, sans-serif;
}

/* Font optimization utilities */
.text-optimize {
    text-rendering: optimizeLegibility;
    -webkit-font-smoothing: antialiased;
    -moz-osx-font-smoothing: grayscale;
}
```

### Font Subsetting

```javascript
// build/font-subset.js
const fontkit = require('fontkit');
const fs = require('fs');

function subsetFont(inputPath, outputPath, text) {
    const font = fontkit.openSync(inputPath);
    const subset = font.createSubset();
    
    // Add characters used in the application
    for (const char of text) {
        subset.includeGlyph(font.glyphForCodePoint(char.codePointAt(0)));
    }
    
    const buffer = subset.encode();
    fs.writeFileSync(outputPath, buffer);
}

// Subset fonts for different languages
const englishText = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789.,!?;:()[]{}"\'-–—…';
const extendedText = englishText + 'ÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÐÑÒÓÔÕÖØÙÚÛÜÝÞßàáâãäåæçèéêëìíîïðñòóôõöøùúûüýþÿ';

subsetFont('fonts/inter-var.woff2', 'public/fonts/inter-var-latin.woff2', englishText);
subsetFont('fonts/inter-var.woff2', 'public/fonts/inter-var-extended.woff2', extendedText);
```

## CDN Integration

### CloudFlare Configuration

```php
// config/cdn.php
return [
    'default' => env('CDN_DRIVER', 'local'),
    
    'drivers' => [
        'cloudflare' => [
            'zone_id' => env('CLOUDFLARE_ZONE_ID'),
            'api_token' => env('CLOUDFLARE_API_TOKEN'),
            'domain' => env('CDN_DOMAIN', 'cdn.example.com'),
        ],
        
        'aws' => [
            'distribution_id' => env('AWS_CLOUDFRONT_DISTRIBUTION_ID'),
            'domain' => env('CDN_DOMAIN', 'd123456789.cloudfront.net'),
        ],
    ],
    
    'cache_control' => [
        'images' => 'public, max-age=31536000, immutable',
        'fonts' => 'public, max-age=31536000, immutable',
        'css' => 'public, max-age=31536000, immutable',
        'js' => 'public, max-age=31536000, immutable',
    ],
];
```

### Asset URL Helper

```php
<?php

namespace App\Helpers;

class AssetHelper
{
    public static function cdn(string $path): string
    {
        $cdnDomain = config('cdn.drivers.' . config('cdn.default') . '.domain');
        
        if (!$cdnDomain || app()->environment('local')) {
            return asset($path);
        }
        
        return "https://{$cdnDomain}/{$path}";
    }
    
    public static function image(string $path, ?int $width = null, string $format = 'webp'): string
    {
        if ($width) {
            $pathInfo = pathinfo($path);
            $path = $pathInfo['dirname'] . '/' . $pathInfo['filename'] . "-{$width}w.{$format}";
        }
        
        return self::cdn("images/{$path}");
    }
}
```

## Performance Monitoring

### Asset Performance Tracking

```javascript
// resources/js/performance.js
class AssetPerformanceMonitor {
    constructor() {
        this.metrics = {};
        this.init();
    }
    
    init() {
        // Monitor resource loading
        window.addEventListener('load', () => {
            this.collectResourceMetrics();
            this.sendMetrics();
        });
        
        // Monitor Core Web Vitals
        this.observeWebVitals();
    }
    
    collectResourceMetrics() {
        const resources = performance.getEntriesByType('resource');
        
        resources.forEach(resource => {
            const type = this.getResourceType(resource.name);
            
            if (!this.metrics[type]) {
                this.metrics[type] = {
                    count: 0,
                    totalSize: 0,
                    totalTime: 0,
                };
            }
            
            this.metrics[type].count++;
            this.metrics[type].totalSize += resource.transferSize || 0;
            this.metrics[type].totalTime += resource.duration;
        });
    }
    
    getResourceType(url) {
        if (url.includes('.css')) return 'css';
        if (url.includes('.js')) return 'js';
        if (url.match(/\.(png|jpg|jpeg|gif|webp|svg)$/)) return 'images';
        if (url.match(/\.(woff|woff2|ttf|eot)$/)) return 'fonts';
        return 'other';
    }
    
    observeWebVitals() {
        // Largest Contentful Paint
        new PerformanceObserver((list) => {
            const entries = list.getEntries();
            const lastEntry = entries[entries.length - 1];
            this.metrics.lcp = lastEntry.startTime;
        }).observe({ entryTypes: ['largest-contentful-paint'] });
        
        // First Input Delay
        new PerformanceObserver((list) => {
            const entries = list.getEntries();
            entries.forEach(entry => {
                this.metrics.fid = entry.processingStart - entry.startTime;
            });
        }).observe({ entryTypes: ['first-input'] });
        
        // Cumulative Layout Shift
        let clsValue = 0;
        new PerformanceObserver((list) => {
            for (const entry of list.getEntries()) {
                if (!entry.hadRecentInput) {
                    clsValue += entry.value;
                }
            }
            this.metrics.cls = clsValue;
        }).observe({ entryTypes: ['layout-shift'] });
    }
    
    sendMetrics() {
        fetch('/api/performance-metrics', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            },
            body: JSON.stringify(this.metrics),
        });
    }
}

// Initialize monitoring
new AssetPerformanceMonitor();
```

---

## Navigation

**← Previous:** [Database Optimization Guide](060-database-optimization.md)

**Next →** [Caching Strategy Guide](080-caching-strategy.md)

**↑ Back to:** [Deployment Index](000-deployment-index.md)
