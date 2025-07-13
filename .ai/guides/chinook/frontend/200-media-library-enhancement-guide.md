# 1. Media Library Enhancement Guide

## Table of Contents

- [1. Overview](#1-overview)
- [2. Advanced File Upload](#2-advanced-file-upload)
- [3. Image Processing](#3-image-processing)
- [4. Audio File Handling](#4-audio-file-handling)
- [5. Performance Optimization](#5-performance-optimization)
- [6. Security Considerations](#6-security-considerations)
- [7. Best Practices](#7-best-practices)
- [8. Navigation](#8-navigation)

## 1. Overview

This guide covers advanced media library enhancements for the Chinook frontend application, including file management, image optimization, and multimedia handling with Livewire/Volt integration. The enhanced media library provides comprehensive multimedia handling capabilities supporting various file types with optimized processing and delivery.

### 1.1 Enhancement Features

- **Multi-format Support**: Handle images, audio, video, and documents
- **Real-time Processing**: Live image editing and audio preview
- **Responsive Delivery**: Optimized media serving for different devices
- **Batch Operations**: Bulk upload and processing capabilities
- **Cloud Integration**: Seamless integration with cloud storage providers
- **Progressive Enhancement**: Graceful degradation for different browser capabilities

## 2. Advanced File Upload

### 2.1 Drag-and-Drop Upload Component

```php
<?php
// resources/views/livewire/media-upload.blade.php

use function Livewire\Volt\{state, on, mount};
use Livewire\WithFileUploads;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

new class extends \Livewire\Volt\Component {
    use WithFileUploads;
    
    public $files = [];
    public $uploadProgress = [];
    public $allowedTypes = ['image/*', 'audio/*', '.pdf'];
    public $maxFileSize = 10; // MB
    public $multiple = true;
    public $collection = 'default';
    
    public function mount($config = [])
    {
        $this->allowedTypes = $config['types'] ?? $this->allowedTypes;
        $this->maxFileSize = $config['maxSize'] ?? $this->maxFileSize;
        $this->multiple = $config['multiple'] ?? $this->multiple;
        $this->collection = $config['collection'] ?? $this->collection;
    }
    
    public function updatedFiles()
    {
        $this->validate([
            'files.*' => [
                'required',
                'file',
                'max:' . ($this->maxFileSize * 1024),
                function ($attribute, $value, $fail) {
                    $allowedMimes = $this->getAllowedMimeTypes();
                    if (!in_array($value->getMimeType(), $allowedMimes)) {
                        $fail('File type not allowed.');
                    }
                }
            ]
        ]);
        
        foreach ($this->files as $index => $file) {
            $this->processFile($file, $index);
        }
    }
    
    public function processFile($file, $index)
    {
        $this->uploadProgress[$index] = 0;
        
        try {
            // Create media record with Spatie Media Library
            $model = auth()->user(); // or any model that uses HasMedia trait
            
            $media = $model
                ->addMediaFromRequest('files.' . $index)
                ->toMediaCollection($this->collection);
            
            // Generate conversions for images
            if (str_starts_with($file->getMimeType(), 'image/')) {
                $this->generateImageConversions($media);
            }
            
            // Extract metadata for audio files
            if (str_starts_with($file->getMimeType(), 'audio/')) {
                $this->extractAudioMetadata($media);
            }
            
            $this->uploadProgress[$index] = 100;
            
            $this->dispatch('file-uploaded', [
                'media' => $media->toArray(),
                'index' => $index
            ]);
            
        } catch (\Exception $e) {
            $this->addError('files.' . $index, 'Upload failed: ' . $e->getMessage());
        }
    }
    
    private function getAllowedMimeTypes(): array
    {
        $mimeMap = [
            'image/*' => ['image/jpeg', 'image/png', 'image/gif', 'image/webp'],
            'audio/*' => ['audio/mpeg', 'audio/wav', 'audio/ogg', 'audio/flac'],
            'video/*' => ['video/mp4', 'video/webm', 'video/ogg'],
            '.pdf' => ['application/pdf'],
        ];
        
        $allowed = [];
        foreach ($this->allowedTypes as $type) {
            if (isset($mimeMap[$type])) {
                $allowed = array_merge($allowed, $mimeMap[$type]);
            }
        }
        
        return $allowed;
    }
    
    private function generateImageConversions(Media $media): void
    {
        // Generate responsive image sizes
        $media->performConversions();
    }
    
    private function extractAudioMetadata(Media $media): void
    {
        // Extract audio metadata using getID3 or similar
        $getID3 = new \getID3();
        $fileInfo = $getID3->analyze($media->getPath());
        
        $metadata = [
            'duration' => $fileInfo['playtime_seconds'] ?? null,
            'bitrate' => $fileInfo['audio']['bitrate'] ?? null,
            'sample_rate' => $fileInfo['audio']['sample_rate'] ?? null,
            'channels' => $fileInfo['audio']['channels'] ?? null,
        ];
        
        $media->setCustomProperty('audio_metadata', $metadata);
        $media->save();
    }
};
?>

<div 
    x-data="fileUpload()"
    x-on:dragover.prevent="dragover = true"
    x-on:dragleave.prevent="dragover = false"
    x-on:drop.prevent="handleDrop($event)"
    class="border-2 border-dashed border-gray-300 rounded-lg p-8 text-center transition-colors"
    :class="{ 'border-blue-500 bg-blue-50': dragover }"
>
    <div class="space-y-4">
        <flux:icon name="cloud-arrow-up" class="mx-auto h-12 w-12 text-gray-400" />
        
        <div>
            <flux:heading size="lg">Upload Files</flux:heading>
            <flux:text variant="muted">
                Drag and drop files here, or 
                <label class="cursor-pointer text-blue-600 hover:text-blue-800">
                    browse
                    <input 
                        type="file" 
                        wire:model="files" 
                        :multiple="$wire.multiple"
                        :accept="$wire.allowedTypes.join(',')"
                        class="hidden"
                    />
                </label>
            </flux:text>
        </div>
        
        <flux:text variant="muted" size="sm">
            Maximum file size: {{ $maxFileSize }}MB
        </flux:text>
    </div>
    
    <!-- Upload Progress -->
    @if(count($uploadProgress) > 0)
        <div class="mt-6 space-y-2">
            @foreach($uploadProgress as $index => $progress)
                <div class="flex items-center space-x-3">
                    <flux:text size="sm" class="flex-1 text-left">
                        {{ $files[$index]->getClientOriginalName() ?? 'File ' . ($index + 1) }}
                    </flux:text>
                    <div class="flex-1">
                        <flux:progress value="{{ $progress }}" />
                    </div>
                    <flux:text size="sm" variant="muted">{{ $progress }}%</flux:text>
                </div>
            @endforeach
        </div>
    @endif
    
    <!-- Error Messages -->
    @if($errors->any())
        <div class="mt-4 space-y-1">
            @foreach($errors->all() as $error)
                <flux:text variant="danger" size="sm">{{ $error }}</flux:text>
            @endforeach
        </div>
    @endif
</div>

<script>
function fileUpload() {
    return {
        dragover: false,
        
        handleDrop(event) {
            this.dragover = false;
            const files = Array.from(event.dataTransfer.files);
            
            // Set files to Livewire component
            this.$wire.set('files', files);
        }
    }
}
</script>
```

### 2.2 Chunked Upload for Large Files

```php
<?php
// app/Http/Controllers/ChunkedUploadController.php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ChunkedUploadController extends Controller
{
    public function upload(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|file',
            'chunk' => 'required|integer|min:0',
            'chunks' => 'required|integer|min:1',
            'name' => 'required|string',
            'uuid' => 'required|string',
        ]);

        $chunk = $request->integer('chunk');
        $chunks = $request->integer('chunks');
        $uuid = $request->string('uuid');
        $originalName = $request->string('name');
        
        // Create temporary directory for chunks
        $tempDir = storage_path("app/temp/uploads/{$uuid}");
        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }
        
        // Save chunk
        $chunkPath = "{$tempDir}/chunk_{$chunk}";
        $request->file('file')->move($tempDir, "chunk_{$chunk}");
        
        // Check if all chunks are uploaded
        $uploadedChunks = glob("{$tempDir}/chunk_*");
        
        if (count($uploadedChunks) === $chunks) {
            // Merge chunks
            $finalPath = $this->mergeChunks($tempDir, $chunks, $originalName);
            
            // Clean up chunks
            $this->cleanupChunks($tempDir);
            
            // Create media record
            $media = auth()->user()
                ->addMedia($finalPath)
                ->usingName($originalName)
                ->toMediaCollection('uploads');
            
            return response()->json([
                'success' => true,
                'media' => $media,
                'message' => 'Upload completed successfully'
            ]);
        }
        
        return response()->json([
            'success' => true,
            'chunk' => $chunk,
            'message' => 'Chunk uploaded successfully'
        ]);
    }
    
    private function mergeChunks(string $tempDir, int $chunks, string $originalName): string
    {
        $finalPath = storage_path("app/temp/{$originalName}");
        $finalFile = fopen($finalPath, 'wb');
        
        for ($i = 0; $i < $chunks; $i++) {
            $chunkPath = "{$tempDir}/chunk_{$i}";
            $chunkFile = fopen($chunkPath, 'rb');
            stream_copy_to_stream($chunkFile, $finalFile);
            fclose($chunkFile);
        }
        
        fclose($finalFile);
        return $finalPath;
    }
    
    private function cleanupChunks(string $tempDir): void
    {
        $files = glob("{$tempDir}/*");
        foreach ($files as $file) {
            unlink($file);
        }
        rmdir($tempDir);
    }
}
```

## 3. Image Processing

### 3.1 Real-time Image Editor Component

```php
<?php
// resources/views/livewire/image-editor.blade.php

use function Livewire\Volt\{state, mount, computed};
use Spatie\MediaLibrary\MediaCollections\Models\Media;

state([
    'media' => null,
    'filters' => [
        'brightness' => 0,
        'contrast' => 0,
        'saturation' => 0,
        'blur' => 0,
        'rotation' => 0,
    ],
    'crop' => [
        'x' => 0,
        'y' => 0,
        'width' => 100,
        'height' => 100,
    ],
    'previewUrl' => null
]);

mount(function (Media $media) {
    $this->media = $media;
    $this->previewUrl = $media->getUrl();
});

$applyFilters = function () {
    // Generate preview with filters applied
    $this->previewUrl = route('media.preview', [
        'media' => $this->media->id,
        'filters' => base64_encode(json_encode($this->filters)),
        'crop' => base64_encode(json_encode($this->crop)),
    ]);
};

$saveChanges = function () {
    // Apply changes permanently
    $conversion = $this->media
        ->addMediaConversion('edited')
        ->performOnCollections('default')
        ->nonQueued();

    // Apply filters
    if ($this->filters['brightness'] !== 0) {
        $conversion->brightness($this->filters['brightness']);
    }

    if ($this->filters['contrast'] !== 0) {
        $conversion->contrast($this->filters['contrast']);
    }

    if ($this->filters['saturation'] !== 0) {
        $conversion->colorize(0, 0, 0, $this->filters['saturation']);
    }

    if ($this->filters['blur'] > 0) {
        $conversion->blur($this->filters['blur']);
    }

    if ($this->filters['rotation'] !== 0) {
        $conversion->rotate($this->filters['rotation']);
    }

    // Apply crop
    if ($this->crop['width'] < 100 || $this->crop['height'] < 100) {
        $conversion->crop(
            $this->crop['width'],
            $this->crop['height'],
            $this->crop['x'],
            $this->crop['y']
        );
    }

    $this->media->performConversions();

    $this->dispatch('image-saved', mediaId: $this->media->id);
};

$resetFilters = function () {
    $this->filters = [
        'brightness' => 0,
        'contrast' => 0,
        'saturation' => 0,
        'blur' => 0,
        'rotation' => 0,
    ];
    $this->previewUrl = $this->media->getUrl();
};
?>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <!-- Image Preview -->
    <div class="lg:col-span-2">
        <flux:card class="p-4">
            <div class="relative">
                <img
                    src="{{ $previewUrl }}"
                    alt="Image preview"
                    class="max-w-full h-auto rounded-lg"
                    style="filter:
                        brightness({{ 100 + $filters['brightness'] }}%)
                        contrast({{ 100 + $filters['contrast'] }}%)
                        saturate({{ 100 + $filters['saturation'] }}%)
                        blur({{ $filters['blur'] }}px);
                        transform: rotate({{ $filters['rotation'] }}deg);"
                />

                <!-- Crop overlay -->
                <div
                    class="absolute border-2 border-blue-500 bg-blue-500 bg-opacity-20"
                    style="
                        left: {{ $crop['x'] }}%;
                        top: {{ $crop['y'] }}%;
                        width: {{ $crop['width'] }}%;
                        height: {{ $crop['height'] }}%;
                    "
                ></div>
            </div>
        </flux:card>
    </div>

    <!-- Controls -->
    <div class="space-y-4">
        <flux:card class="p-4">
            <flux:heading size="lg" class="mb-4">Filters</flux:heading>

            <div class="space-y-4">
                <!-- Brightness -->
                <div>
                    <flux:label>Brightness: {{ $filters['brightness'] }}</flux:label>
                    <input
                        type="range"
                        wire:model.live="filters.brightness"
                        wire:change="applyFilters"
                        min="-100"
                        max="100"
                        class="w-full"
                    />
                </div>

                <!-- Contrast -->
                <div>
                    <flux:label>Contrast: {{ $filters['contrast'] }}</flux:label>
                    <input
                        type="range"
                        wire:model.live="filters.contrast"
                        wire:change="applyFilters"
                        min="-100"
                        max="100"
                        class="w-full"
                    />
                </div>

                <!-- Saturation -->
                <div>
                    <flux:label>Saturation: {{ $filters['saturation'] }}</flux:label>
                    <input
                        type="range"
                        wire:model.live="filters.saturation"
                        wire:change="applyFilters"
                        min="-100"
                        max="100"
                        class="w-full"
                    />
                </div>

                <!-- Blur -->
                <div>
                    <flux:label>Blur: {{ $filters['blur'] }}px</flux:label>
                    <input
                        type="range"
                        wire:model.live="filters.blur"
                        wire:change="applyFilters"
                        min="0"
                        max="10"
                        class="w-full"
                    />
                </div>

                <!-- Rotation -->
                <div>
                    <flux:label>Rotation: {{ $filters['rotation'] }}°</flux:label>
                    <input
                        type="range"
                        wire:model.live="filters.rotation"
                        wire:change="applyFilters"
                        min="-180"
                        max="180"
                        class="w-full"
                    />
                </div>
            </div>
        </flux:card>

        <flux:card class="p-4">
            <flux:heading size="lg" class="mb-4">Crop</flux:heading>

            <div class="space-y-4">
                <div class="grid grid-cols-2 gap-2">
                    <div>
                        <flux:label>X Position</flux:label>
                        <flux:input
                            type="number"
                            wire:model.live="crop.x"
                            wire:change="applyFilters"
                            min="0"
                            max="100"
                        />
                    </div>
                    <div>
                        <flux:label>Y Position</flux:label>
                        <flux:input
                            type="number"
                            wire:model.live="crop.y"
                            wire:change="applyFilters"
                            min="0"
                            max="100"
                        />
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-2">
                    <div>
                        <flux:label>Width</flux:label>
                        <flux:input
                            type="number"
                            wire:model.live="crop.width"
                            wire:change="applyFilters"
                            min="10"
                            max="100"
                        />
                    </div>
                    <div>
                        <flux:label>Height</flux:label>
                        <flux:input
                            type="number"
                            wire:model.live="crop.height"
                            wire:change="applyFilters"
                            min="10"
                            max="100"
                        />
                    </div>
                </div>
            </div>
        </flux:card>

        <!-- Actions -->
        <div class="flex space-x-2">
            <flux:button wire:click="saveChanges" variant="primary" class="flex-1">
                Save Changes
            </flux:button>
            <flux:button wire:click="resetFilters" variant="ghost" class="flex-1">
                Reset
            </flux:button>
        </div>
    </div>
</div>
```

## 4. Audio File Handling

### 4.1 Audio Player with Waveform Visualization

```php
<?php
// resources/views/livewire/audio-player.blade.php

use function Livewire\Volt\{state, mount, computed};
use Spatie\MediaLibrary\MediaCollections\Models\Media;

state([
    'media' => null,
    'isPlaying' => false,
    'currentTime' => 0,
    'duration' => 0,
    'volume' => 75,
    'playbackRate' => 1.0,
    'waveformData' => null
]);

mount(function (Media $media) {
    $this->media = $media;
    $this->duration = $media->getCustomProperty('audio_metadata.duration', 0);
    $this->generateWaveform();
});

$generateWaveform = function () {
    // Generate waveform data for visualization
    $audioPath = $this->media->getPath();
    $this->waveformData = $this->extractWaveformData($audioPath);
};

$play = function () {
    $this->isPlaying = true;
    $this->dispatch('audio-play', mediaId: $this->media->id);
};

$pause = function () {
    $this->isPlaying = false;
    $this->dispatch('audio-pause', mediaId: $this->media->id);
};

$seek = function ($time) {
    $this->currentTime = $time;
    $this->dispatch('audio-seek', time: $time);
};

$setVolume = function ($volume) {
    $this->volume = $volume;
    $this->dispatch('audio-volume', volume: $volume / 100);
};

$setPlaybackRate = function ($rate) {
    $this->playbackRate = $rate;
    $this->dispatch('audio-rate', rate: $rate);
};

$extractWaveformData = function ($audioPath) {
    // Simplified waveform generation
    // In production, use FFmpeg or similar for accurate waveform data
    $samples = 200;
    $data = [];

    for ($i = 0; $i < $samples; $i++) {
        $data[] = rand(10, 100);
    }

    return $data;
};
?>

<div class="bg-white rounded-lg shadow-lg p-6" x-data="audioPlayer()">
    <!-- Track Info -->
    <div class="flex items-center space-x-4 mb-6">
        <div class="w-16 h-16 bg-gray-200 rounded-lg flex items-center justify-center">
            <flux:icon name="musical-note" class="w-8 h-8 text-gray-500" />
        </div>

        <div class="flex-1">
            <flux:heading size="lg">{{ $media->name }}</flux:heading>
            <flux:text variant="muted">
                Duration: {{ gmdate('i:s', $duration) }}
            </flux:text>
        </div>
    </div>

    <!-- Waveform Visualization -->
    <div class="mb-6">
        <div class="relative h-20 bg-gray-100 rounded-lg overflow-hidden">
            <canvas
                x-ref="waveform"
                class="w-full h-full cursor-pointer"
                @click="seekToPosition($event)"
            ></canvas>

            <!-- Progress indicator -->
            <div
                class="absolute top-0 bottom-0 bg-blue-500 opacity-30 pointer-events-none"
                :style="{ width: (currentTime / duration * 100) + '%' }"
            ></div>
        </div>
    </div>

    <!-- Controls -->
    <div class="flex items-center justify-between mb-4">
        <div class="flex items-center space-x-4">
            <!-- Play/Pause -->
            <flux:button
                @click="togglePlay()"
                variant="primary"
                size="lg"
                class="w-12 h-12 rounded-full"
            >
                <flux:icon :name="isPlaying ? 'pause' : 'play'" />
            </flux:button>

            <!-- Time Display -->
            <div class="text-sm text-gray-600">
                <span x-text="formatTime(currentTime)">00:00</span>
                <span>/</span>
                <span x-text="formatTime(duration)">{{ gmdate('i:s', $duration) }}</span>
            </div>
        </div>

        <div class="flex items-center space-x-4">
            <!-- Playback Rate -->
            <div class="flex items-center space-x-2">
                <flux:text size="sm">Speed:</flux:text>
                <select
                    wire:model.live="playbackRate"
                    wire:change="setPlaybackRate($event.target.value)"
                    class="text-sm border rounded px-2 py-1"
                >
                    <option value="0.5">0.5x</option>
                    <option value="0.75">0.75x</option>
                    <option value="1.0">1x</option>
                    <option value="1.25">1.25x</option>
                    <option value="1.5">1.5x</option>
                    <option value="2.0">2x</option>
                </select>
            </div>

            <!-- Volume -->
            <div class="flex items-center space-x-2">
                <flux:icon name="speaker-wave" class="w-4 h-4" />
                <input
                    type="range"
                    wire:model.live="volume"
                    wire:change="setVolume($event.target.value)"
                    min="0"
                    max="100"
                    class="w-20"
                />
                <span class="text-sm w-8">{{ $volume }}%</span>
            </div>
        </div>
    </div>

    <!-- Progress Bar -->
    <div class="relative">
        <input
            type="range"
            :value="currentTime"
            :max="duration"
            @input="seek($event.target.value)"
            class="w-full h-2 bg-gray-200 rounded-lg appearance-none cursor-pointer"
        />
    </div>

    <!-- Hidden Audio Element -->
    <audio
        x-ref="audio"
        :src="'{{ $media->getUrl() }}'"
        @timeupdate="updateTime()"
        @loadedmetadata="updateDuration()"
        @ended="onEnded()"
        preload="metadata"
    ></audio>
</div>

<script>
function audioPlayer() {
    return {
        isPlaying: @entangle('isPlaying'),
        currentTime: @entangle('currentTime'),
        duration: @entangle('duration'),
        volume: @entangle('volume'),
        playbackRate: @entangle('playbackRate'),
        waveformData: @js($waveformData),

        init() {
            this.drawWaveform();
            this.$refs.audio.volume = this.volume / 100;
            this.$refs.audio.playbackRate = this.playbackRate;
        },

        togglePlay() {
            if (this.isPlaying) {
                this.$refs.audio.pause();
                this.isPlaying = false;
            } else {
                this.$refs.audio.play();
                this.isPlaying = true;
            }
        },

        seek(time) {
            this.$refs.audio.currentTime = time;
            this.currentTime = time;
        },

        seekToPosition(event) {
            const rect = event.target.getBoundingClientRect();
            const x = event.clientX - rect.left;
            const percentage = x / rect.width;
            const time = percentage * this.duration;
            this.seek(time);
        },

        updateTime() {
            this.currentTime = this.$refs.audio.currentTime;
        },

        updateDuration() {
            this.duration = this.$refs.audio.duration;
        },

        onEnded() {
            this.isPlaying = false;
            this.currentTime = 0;
        },

        formatTime(seconds) {
            const mins = Math.floor(seconds / 60);
            const secs = Math.floor(seconds % 60);
            return `${mins.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`;
        },

        drawWaveform() {
            const canvas = this.$refs.waveform;
            const ctx = canvas.getContext('2d');
            const width = canvas.width = canvas.offsetWidth;
            const height = canvas.height = canvas.offsetHeight;

            ctx.clearRect(0, 0, width, height);
            ctx.fillStyle = '#3b82f6';

            const barWidth = width / this.waveformData.length;

            this.waveformData.forEach((value, index) => {
                const barHeight = (value / 100) * height;
                const x = index * barWidth;
                const y = (height - barHeight) / 2;

                ctx.fillRect(x, y, barWidth - 1, barHeight);
            });
        }
    }
}
</script>
```

## 5. Performance Optimization

### 5.1 Lazy Loading and Progressive Enhancement

```php
<?php
// resources/views/livewire/media-gallery.blade.php

use function Livewire\Volt\{state, computed, mount};
use Spatie\MediaLibrary\MediaCollections\Models\Media;

state([
    'page' => 1,
    'perPage' => 20,
    'loadedItems' => [],
    'hasMore' => true,
    'loading' => false
]);

$media = computed(function () {
    return Media::query()
        ->latest()
        ->paginate($this->perPage, ['*'], 'page', $this->page);
});

$loadMore = function () {
    if ($this->loading || !$this->hasMore) return;

    $this->loading = true;
    $this->page++;

    $newItems = $this->media->items();
    $this->loadedItems = array_merge($this->loadedItems, $newItems);
    $this->hasMore = $this->media->hasMorePages();
    $this->loading = false;
};

mount(function () {
    $this->loadedItems = $this->media->items();
    $this->hasMore = $this->media->hasMorePages();
});
?>

<div x-data="mediaGallery()" x-init="init()">
    <!-- Gallery Grid -->
    <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-4">
        @foreach($loadedItems as $item)
            <div
                class="aspect-square bg-gray-200 rounded-lg overflow-hidden"
                x-intersect="loadImage($el, '{{ $item->getUrl('thumb') }}')"
            >
                <img
                    class="w-full h-full object-cover opacity-0 transition-opacity duration-300"
                    alt="{{ $item->name }}"
                    loading="lazy"
                />
            </div>
        @endforeach
    </div>

    <!-- Load More -->
    @if($hasMore)
        <div class="text-center mt-8">
            <flux:button
                wire:click="loadMore"
                :disabled="$loading"
                variant="outline"
            >
                <span x-show="!$wire.loading">Load More</span>
                <span x-show="$wire.loading">Loading...</span>
            </flux:button>
        </div>
    @endif

    <!-- Infinite Scroll Trigger -->
    <div x-intersect="$wire.loadMore()" class="h-10"></div>
</div>

<script>
function mediaGallery() {
    return {
        init() {
            // Initialize intersection observer for lazy loading
        },

        loadImage(element, src) {
            const img = element.querySelector('img');
            if (img && !img.src) {
                img.src = src;
                img.onload = () => {
                    img.classList.remove('opacity-0');
                };
            }
        }
    }
}
</script>
```

## 6. Security Considerations

### 6.1 File Validation and Sanitization

```php
<?php
// app/Rules/SecureFileUpload.php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;
use Illuminate\Http\UploadedFile;

class SecureFileUpload implements Rule
{
    private array $allowedMimes;
    private int $maxSize;
    private array $errors = [];

    public function __construct(array $allowedMimes, int $maxSize = 10485760) // 10MB default
    {
        $this->allowedMimes = $allowedMimes;
        $this->maxSize = $maxSize;
    }

    public function passes($attribute, $value): bool
    {
        if (!$value instanceof UploadedFile) {
            $this->errors[] = 'Invalid file upload';
            return false;
        }

        // Check file size
        if ($value->getSize() > $this->maxSize) {
            $this->errors[] = 'File size exceeds maximum allowed';
            return false;
        }

        // Check MIME type
        if (!in_array($value->getMimeType(), $this->allowedMimes)) {
            $this->errors[] = 'File type not allowed';
            return false;
        }

        // Check file extension matches MIME type
        if (!$this->validateExtension($value)) {
            $this->errors[] = 'File extension does not match content';
            return false;
        }

        // Scan for malicious content
        if (!$this->scanForMalware($value)) {
            $this->errors[] = 'File contains suspicious content';
            return false;
        }

        return true;
    }

    public function message(): string
    {
        return implode(', ', $this->errors);
    }

    private function validateExtension(UploadedFile $file): bool
    {
        $extension = strtolower($file->getClientOriginalExtension());
        $mimeType = $file->getMimeType();

        $mimeToExtension = [
            'image/jpeg' => ['jpg', 'jpeg'],
            'image/png' => ['png'],
            'image/gif' => ['gif'],
            'image/webp' => ['webp'],
            'audio/mpeg' => ['mp3'],
            'audio/wav' => ['wav'],
            'audio/ogg' => ['ogg'],
            'video/mp4' => ['mp4'],
            'application/pdf' => ['pdf'],
        ];

        return isset($mimeToExtension[$mimeType]) &&
               in_array($extension, $mimeToExtension[$mimeType]);
    }

    private function scanForMalware(UploadedFile $file): bool
    {
        // Basic content scanning
        $content = file_get_contents($file->getPathname());

        // Check for suspicious patterns
        $suspiciousPatterns = [
            '/<\?php/',
            '/<script/',
            '/eval\s*\(/',
            '/exec\s*\(/',
            '/system\s*\(/',
            '/shell_exec\s*\(/',
        ];

        foreach ($suspiciousPatterns as $pattern) {
            if (preg_match($pattern, $content)) {
                return false;
            }
        }

        return true;
    }
}
```

## 7. Best Practices

### 7.1 Media Library Guidelines

1. **File Organization**: Use collections to organize different types of media
2. **Naming Conventions**: Use descriptive names and maintain consistency
3. **Size Optimization**: Generate multiple sizes for responsive delivery
4. **Metadata Extraction**: Store relevant metadata for searchability
5. **Security**: Validate all uploads and scan for malicious content

### 7.2 Performance Optimization

1. **Lazy Loading**: Load images only when needed
2. **Progressive Enhancement**: Provide fallbacks for older browsers
3. **Caching**: Implement proper caching strategies
4. **CDN Integration**: Use CDN for global content delivery
5. **Compression**: Optimize file sizes without quality loss

### 7.3 User Experience

1. **Progress Indicators**: Show upload progress for large files
2. **Error Handling**: Provide clear error messages
3. **Accessibility**: Ensure all components are accessible
4. **Mobile Optimization**: Optimize for mobile devices
5. **Offline Support**: Provide offline capabilities where possible

## 8. Navigation

**← Previous** [CI/CD Integration Guide](190-cicd-integration-guide.md)
**Next →** [Frontend Index](000-frontend-index.md)

---

**Source Attribution:** Refactored from: .ai/guides/chinook/frontend/200-media-library-enhancement-guide.md on 2025-07-11

*This guide provides comprehensive media library enhancements for the Chinook music platform. Return to the frontend index for complete navigation of all frontend guides.*

[⬆️ Back to Top](#1-media-library-enhancement-guide)
