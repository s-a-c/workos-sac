<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('playlists', function (Blueprint $table) {
            $table->id();

            // Core fields
            $table->string('name', 120);
            $table->text('description')->nullable();
            $table->boolean('is_public')->default(true);
            $table->boolean('is_collaborative')->default(false);
            $table->string('cover_image_url')->nullable();

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
            $table->index('name');
            $table->index('is_public');
            $table->index(['is_public', 'created_at']);
            $table->index('public_id');
            $table->index('slug');

            // Comments for documentation
            $table->comment('Playlists table with enhanced features and modern Laravel functionality');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('playlists');
    }
};
