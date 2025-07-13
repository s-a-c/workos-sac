<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('playlist_track', function (Blueprint $table) {
            $table->foreignId('playlist_id')->constrained('playlists')->onDelete('cascade');
            $table->foreignId('track_id')->constrained('tracks')->onDelete('cascade');

            // Composite primary key
            $table->primary(['playlist_id', 'track_id']);

            // Indexes
            $table->index('playlist_id');
            $table->index('track_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('playlist_track');
    }
};
