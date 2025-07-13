<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('albums', function (Blueprint $table) {
            $table->id();

            // Core fields
            $table->string('title', 200);
            $table->foreignId('artist_id')->constrained('artists')->onDelete('cascade');
            $table->date('release_date')->nullable();
            $table->string('label', 100)->nullable();
            $table->string('catalog_number', 50)->nullable();
            $table->text('description')->nullable();
            $table->string('cover_image_url')->nullable();
            $table->integer('total_tracks')->default(0);
            $table->integer('total_duration_ms')->default(0);
            $table->boolean('is_compilation')->default(false);
            $table->boolean('is_explicit')->default(false);

            // Comment system configuration
            $table->boolean('comments_enabled')->default(true);
            $table->boolean('auto_approve_comments')->default(true);
            $table->boolean('reactions_only')->default(false);

            // Modern Laravel features
            $table->string('public_id', 26)->unique()->index(); // ULID
            $table->string('slug')->unique()->index();
            $table->timestamps();
            $table->softDeletes();
            $table->userstamps();

            // Indexes for performance
            $table->index('artist_id');
            $table->index('title');
            $table->index('release_date');
            $table->index(['artist_id', 'release_date']);
            $table->index(['is_compilation', 'release_date']);

            // Comments for documentation
            $table->comment('Albums table with enhanced metadata and modern Laravel features');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('albums');
    }
};
