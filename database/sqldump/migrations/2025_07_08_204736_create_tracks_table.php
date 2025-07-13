<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('tracks', function (Blueprint $table) {
            $table->id();

            // Core fields
            $table->string('name', 200);
            $table->foreignId('album_id')->constrained('albums')->onDelete('cascade');
            $table->foreignId('media_type_id')->constrained('media_types')->onDelete('restrict');
            // REMOVED: genre_id - now using polymorphic categories
            $table->string('composer', 220)->nullable();
            $table->integer('milliseconds');
            $table->integer('bytes')->nullable();
            $table->decimal('unit_price', 10, 2);

            // Enhanced fields
            $table->integer('track_number')->nullable();
            $table->integer('disc_number')->default(1);
            $table->boolean('is_explicit')->default(false);
            $table->boolean('is_active')->default(true);
            $table->string('preview_url')->nullable();
            $table->text('lyrics')->nullable();

            // Comment system configuration
            $table->boolean('comments_enabled')->default(true);
            $table->boolean('auto_approve_comments')->default(true);
            $table->boolean('reactions_only')->default(false);

            // Modern Laravel features
            $table->string('public_id', 19)->unique()->index(); // Snowflake for high performance
            $table->string('slug')->unique()->index();
            $table->timestamps();
            $table->softDeletes();
            $table->userstamps();

            // Indexes for performance
            $table->index('album_id');
            $table->index('media_type_id');
            $table->index('name');
            $table->index('unit_price');
            $table->index('track_number');
            $table->index('disc_number');
            $table->index('is_explicit');
            $table->index('is_active');
            $table->index(['album_id', 'track_number']);
            $table->index(['album_id', 'disc_number', 'track_number']);
            $table->index(['is_active', 'unit_price']);
            $table->index(['milliseconds']); // For duration-based queries

            // Comments for documentation
            $table->comment('Tracks table with enhanced metadata, removed genre_id (now using polymorphic categories)');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tracks');
    }
};
