<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('artists', function (Blueprint $table) {
            $table->id();

            // Core fields
            $table->string('name', 120);
            $table->text('biography')->nullable();
            $table->string('website')->nullable();
            $table->json('social_links')->nullable();

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
            $table->index(['created_at', 'name']);
            $table->index('public_id');
            $table->index('slug');

            // Comments for documentation
            $table->comment('Artists table with modern Laravel features: timestamps, soft deletes, user stamps, secondary keys, and slugs');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('artists');
    }
};
