# Media Library Enhancement Guide

This guide covers advanced media library enhancements for the Chinook frontend application, including file management, image optimization, and multimedia handling with Livewire/Volt integration.

## Table of Contents

- [Overview](#overview)
- [Advanced File Upload](#advanced-file-upload)
- [Image Processing](#image-processing)
- [Audio File Handling](#audio-file-handling)
- [Audio File Handling](#audio-file-handling)
- [Performance Optimization](#performance-optimization)
- [Security Considerations](#security-considerations)
- [Best Practices](#best-practices)

## Overview

The media library enhancement provides comprehensive multimedia handling capabilities for the Chinook application, supporting various file types with optimized processing and delivery.

### Enhancement Features

- **Multi-format Support**: Handle images, audio, video, and documents
- **Real-time Processing**: Live image editing and audio preview
- **Responsive Delivery**: Optimized media serving for different devices
- **Batch Operations**: Bulk upload and processing capabilities

## Advanced File Upload

### Drag-and-Drop Upload Component

```php
<?php
// resources/views/livewire/media-upload.blade.php

use function Livewire\Volt\{state, on, mount};
use Livewire\WithFileUploads;

new class extends \Livewire\Volt\Component {
    use WithFileUploads;
    
    public $files = [];
    public $uploadProgress = [];
    public $allowedTypes = ['image/*', 'audio/*', '.pdf'];
    public $maxFileSize = 10; // MB
    public $multiple = true;
    
    public function mount($config = [])
    {
        $this->allowedTypes = $config['types'] ?? $this->allowedTypes;
        $this->maxFileSize = $config['maxSize'] ?? $this->maxFileSize;
        $this->multiple = $config['multiple'] ?? $this->multiple;
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
        
        // Simulate upload progress
        for ($i = 0; $i <= 100; $i += 10) {
            $this->uploadProgress[$index] = $i;
            usleep(100000); // 0.1 second delay
        }
        
        // Store file
        $path = $file->store('media', 'public');
        
        // Create media record
        $media = \App\Models\Media::create([
            'name' => $file->getClientOriginalName(),
            'file_name' => basename($path),
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
            'path' => $path,
        ]);
        
        $this->dispatch('file-uploaded', [
            'media' => $media,
            'index' => $index
        ]);
    }
    
    private function getAllowedMimeTypes()
    {
        $mimeMap = [
            'image/*' => ['image/jpeg', 'image/png', 'image/gif', 'image/webp'],
            'audio/*' => ['audio/mpeg', 'audio/wav', 'audio/ogg', 'audio/mp4'],
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
}; ?>

<div class="media-upload-container">
    <div class="upload-zone" 
         x-data="{ 
             dragover: false,
             handleDrop(e) {
                 this.dragover = false;
                 const files = Array.from(e.dataTransfer.files);
                 @this.set('files', files);
             }
         }"
         @dragover.prevent="dragover = true"
         @dragleave.prevent="dragover = false"
         @drop.prevent="handleDrop($event)"
         :class="{ 'drag-active': dragover }">
        
        <div class="upload-content">
            <svg class="upload-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                      d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
            </svg>
            
            <h3>Drop files here or click to upload</h3>
            <p>Supports: {{ implode(', ', $allowedTypes) }}</p>
            <p>Max size: {{ $maxFileSize }}MB per file</p>
            
            <input type="file" 
                   wire:model="files"
                   {{ $multiple ? 'multiple' : '' }}
                   accept="{{ implode(',', $allowedTypes) }}"
                   class="file-input">
        </div>
    </div>
    
    @if($files)
        <div class="upload-progress">
            @foreach($files as $index => $file)
                <div class="file-progress">
                    <div class="file-info">
                        <span class="file-name">{{ $file->getClientOriginalName() }}</span>
                        <span class="file-size">{{ number_format($file->getSize() / 1024, 1) }}KB</span>
                    </div>
                    
                    <div class="progress-bar">
                        <div class="progress-fill" 
                             style="width: {{ $uploadProgress[$index] ?? 0 }}%"></div>
                    </div>
                    
                    <span class="progress-text">{{ $uploadProgress[$index] ?? 0 }}%</span>
                </div>
            @endforeach
        </div>
    @endif
</div>

<style>
.upload-zone {
    border: 2px dashed #cbd5e0;
    border-radius: 8px;
    padding: 2rem;
    text-align: center;
    transition: all 0.3s ease;
    cursor: pointer;
}

.upload-zone.drag-active {
    border-color: #4299e1;
    background-color: #ebf8ff;
}

.upload-icon {
    width: 3rem;
    height: 3rem;
    margin: 0 auto 1rem;
    color: #a0aec0;
}

.file-input {
    position: absolute;
    opacity: 0;
    width: 100%;
    height: 100%;
    cursor: pointer;
}

.progress-bar {
    width: 100%;
    height: 8px;
    background-color: #e2e8f0;
    border-radius: 4px;
    overflow: hidden;
}

.progress-fill {
    height: 100%;
    background-color: #4299e1;
    transition: width 0.3s ease;
}
</style>
```

## Image Processing

### Real-time Image Editor

```php
<?php
// resources/views/livewire/image-editor.blade.php

use function Livewire\Volt\{state, computed, on};

state([
    'mediaId' => null,
    'brightness' => 0,
    'contrast' => 0,
    'saturation' => 0,
    'rotation' => 0,
    'cropX' => 0,
    'cropY' => 0,
    'cropWidth' => 100,
    'cropHeight' => 100,
]);

computed('processedImageUrl', function () {
    if (!$this->mediaId) return null;
    
    $params = http_build_query([
        'brightness' => $this->brightness,
        'contrast' => $this->contrast,
        'saturation' => $this->saturation,
        'rotation' => $this->rotation,
        'crop' => "{$this->cropX},{$this->cropY},{$this->cropWidth},{$this->cropHeight}",
    ]);
    
    return "/media/{$this->mediaId}/process?{$params}";
});

function resetAdjustments()
{
    $this->brightness = 0;
    $this->contrast = 0;
    $this->saturation = 0;
    $this->rotation = 0;
}

function saveChanges()
{
    $media = \App\Models\Media::find($this->mediaId);
    
    if ($media) {
        // Apply transformations and save new version
        $processedPath = \App\Services\ImageProcessor::process($media->path, [
            'brightness' => $this->brightness,
            'contrast' => $this->contrast,
            'saturation' => $this->saturation,
            'rotation' => $this->rotation,
            'crop' => [
                'x' => $this->cropX,
                'y' => $this->cropY,
                'width' => $this->cropWidth,
                'height' => $this->cropHeight,
            ],
        ]);
        
        $media->update(['path' => $processedPath]);
        
        $this->dispatch('image-saved', ['media' => $media]);
    }
}

?>

<div class="image-editor">
    <div class="editor-workspace">
        <div class="image-preview">
            @if($this->processedImageUrl)
                <img src="{{ $this->processedImageUrl }}" 
                     alt="Preview" 
                     class="preview-image"
                     x-data="imageCropper()"
                     x-init="initCropper()">
            @endif
        </div>
        
        <div class="editor-controls">
            <div class="control-group">
                <label>Brightness</label>
                <input type="range" 
                       wire:model.live="brightness"
                       min="-100" 
                       max="100" 
                       step="1">
                <span>{{ $brightness }}</span>
            </div>
            
            <div class="control-group">
                <label>Contrast</label>
                <input type="range" 
                       wire:model.live="contrast"
                       min="-100" 
                       max="100" 
                       step="1">
                <span>{{ $contrast }}</span>
            </div>
            
            <div class="control-group">
                <label>Saturation</label>
                <input type="range" 
                       wire:model.live="saturation"
                       min="-100" 
                       max="100" 
                       step="1">
                <span>{{ $saturation }}</span>
            </div>
            
            <div class="control-group">
                <label>Rotation</label>
                <input type="range" 
                       wire:model.live="rotation"
                       min="0" 
                       max="360" 
                       step="1">
                <span>{{ $rotation }}°</span>
            </div>
        </div>
    </div>
    
    <div class="editor-actions">
        <button wire:click="resetAdjustments" class="btn btn-secondary">
            Reset
        </button>
        <button wire:click="saveChanges" class="btn btn-primary">
            Save Changes
        </button>
    </div>
</div>

<script>
function imageCropper() {
    return {
        cropper: null,
        
        initCropper() {
            // Initialize cropping functionality
            this.cropper = new Cropper(this.$el, {
                aspectRatio: NaN,
                viewMode: 1,
                autoCropArea: 1,
                crop: (event) => {
                    @this.set('cropX', Math.round(event.detail.x));
                    @this.set('cropY', Math.round(event.detail.y));
                    @this.set('cropWidth', Math.round(event.detail.width));
                    @this.set('cropHeight', Math.round(event.detail.height));
                }
            });
        }
    }
}
</script>
```

## Audio File Handling

### Audio Player Component

```php
<?php
// resources/views/livewire/audio-player.blade.php

use function Livewire\Volt\{state, on, mount};

state([
    'trackId' => null,
    'isPlaying' => false,
    'currentTime' => 0,
    'duration' => 0,
    'volume' => 50,
    'playlist' => [],
    'currentIndex' => 0,
]);

mount(function ($trackId = null, $playlist = []) {
    $this->trackId = $trackId;
    $this->playlist = $playlist;
    
    if ($trackId && empty($playlist)) {
        $track = \App\Models\Track::find($trackId);
        $this->playlist = [$track];
    }
});

on([
    'play-track' => function ($trackId) {
        $this->trackId = $trackId;
        $this->isPlaying = true;
        $this->dispatch('audio-play', ['trackId' => $trackId]);
    },
    'pause-track' => function () {
        $this->isPlaying = false;
        $this->dispatch('audio-pause');
    },
    'time-update' => function ($currentTime, $duration) {
        $this->currentTime = $currentTime;
        $this->duration = $duration;
    }
]);

function togglePlayPause()
{
    $this->isPlaying = !$this->isPlaying;
    
    if ($this->isPlaying) {
        $this->dispatch('audio-play', ['trackId' => $this->trackId]);
    } else {
        $this->dispatch('audio-pause');
    }
}

function nextTrack()
{
    if ($this->currentIndex < count($this->playlist) - 1) {
        $this->currentIndex++;
        $this->trackId = $this->playlist[$this->currentIndex]['id'];
        $this->dispatch('audio-play', ['trackId' => $this->trackId]);
    }
}

function previousTrack()
{
    if ($this->currentIndex > 0) {
        $this->currentIndex--;
        $this->trackId = $this->playlist[$this->currentIndex]['id'];
        $this->dispatch('audio-play', ['trackId' => $this->trackId]);
    }
}

?>

<div class="audio-player" 
     x-data="audioPlayer()"
     x-init="initPlayer()">
    
    <audio x-ref="audio" 
           @timeupdate="updateTime()"
           @loadedmetadata="updateDuration()"
           @ended="$wire.nextTrack()">
        @if($trackId)
            <source src="/tracks/{{ $trackId }}/stream" type="audio/mpeg">
        @endif
    </audio>
    
    <div class="player-controls">
        <button wire:click="previousTrack" class="control-btn">
            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                <path d="M8.445 14.832A1 1 0 0010 14v-2.798l5.445 3.63A1 1 0 0017 14V6a1 1 0 00-1.555-.832L10 8.798V6a1 1 0 00-1.555-.832l-6 4a1 1 0 000 1.664l6 4z"/>
            </svg>
        </button>
        
        <button wire:click="togglePlayPause" class="play-pause-btn">
            @if($isPlaying)
                <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 20 20">
                    <path d="M5 4a1 1 0 011 1v10a1 1 0 01-2 0V5a1 1 0 011-1zM14 4a1 1 0 011 1v10a1 1 0 01-2 0V5a1 1 0 011-1z"/>
                </svg>
            @else
                <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 20 20">
                    <path d="M8 5v10l8-5-8-5z"/>
                </svg>
            @endif
        </button>
        
        <button wire:click="nextTrack" class="control-btn">
            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                <path d="M4.555 5.168A1 1 0 003 6v8a1 1 0 001.555.832L10 11.202V14a1 1 0 001.555.832l6-4a1 1 0 000-1.664l-6-4A1 1 0 0010 6v2.798l-5.445-3.63z"/>
            </svg>
        </button>
    </div>
    
    <div class="progress-section">
        <span class="time-display">{{ formatTime($currentTime) }}</span>
        
        <div class="progress-bar" @click="seekTo($event)">
            <div class="progress-fill" 
                 :style="`width: ${duration ? (currentTime / duration) * 100 : 0}%`"></div>
        </div>
        
        <span class="time-display">{{ formatTime($duration) }}</span>
    </div>
    
    <div class="volume-control">
        <input type="range" 
               wire:model.live="volume"
               min="0" 
               max="100" 
               @input="updateVolume($event.target.value)">
    </div>
</div>

<script>
function audioPlayer() {
    return {
        currentTime: @entangle('currentTime'),
        duration: @entangle('duration'),
        
        initPlayer() {
            this.$wire.on('audio-play', (data) => {
                this.$refs.audio.play();
            });
            
            this.$wire.on('audio-pause', () => {
                this.$refs.audio.pause();
            });
        },
        
        updateTime() {
            this.currentTime = this.$refs.audio.currentTime;
        },
        
        updateDuration() {
            this.duration = this.$refs.audio.duration;
        },
        
        seekTo(event) {
            const rect = event.target.getBoundingClientRect();
            const percent = (event.clientX - rect.left) / rect.width;
            this.$refs.audio.currentTime = percent * this.duration;
        },
        
        updateVolume(volume) {
            this.$refs.audio.volume = volume / 100;
        }
    }
}
</script>
```

## Best Practices

### Performance Optimization

1. **Lazy Loading**: Load media content on demand
2. **Image Optimization**: Use WebP format and responsive images
3. **CDN Integration**: Serve media files from CDN
4. **Caching Strategy**: Implement proper caching for processed media

### Security Considerations

1. **File Validation**: Strict file type and size validation
2. **Virus Scanning**: Scan uploaded files for malware
3. **Access Control**: Implement proper media access permissions
4. **Secure Storage**: Use secure storage solutions for sensitive media

---

## Related Documentation

- **[CI/CD Integration](190-cicd-integration-guide.md)** - Deployment and integration
- **[Performance Monitoring](170-performance-monitoring-guide.md)** - Media performance tracking
- **[Accessibility Guide](140-accessibility-wcag-guide.md)** - Media accessibility compliance

---

## Navigation

**← Previous:** [CI/CD Integration](190-cicd-integration-guide.md)

**Next →** [Frontend Index](000-frontend-index.md)
