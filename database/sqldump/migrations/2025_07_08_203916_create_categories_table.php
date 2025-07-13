<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();

            // Adjacency list fields (for fast writes and simple queries)
            $table->foreignId('parent_id')->nullable()->constrained('categories')->onDelete('cascade');
            $table->integer('depth')->default(0)->index();    // Cached depth for performance
            $table->string('path', 500)->nullable()->index(); // Materialized path for optimization

            // Core fields
            $table->string('name', 120);
            $table->text('description')->nullable();
            $table->enum('type', [
                'genre', 'mood', 'theme', 'era',
                'instrument', 'language', 'occasion',
            ])->index();

            // Ordering and display
            $table->integer('sort_order')->default(0);

            // Enhanced metadata
            $table->string('color', 7)->nullable();           // Hex color for UI
            $table->string('icon', 50)->nullable();           // Font Awesome icon class
            $table->json('metadata')->nullable();             // Flexible metadata storage
            $table->boolean('is_active')->default(true);

            // Modern Laravel 12 features
            $table->char('public_id', 36)->unique()->index(); // UUID for categories
            $table->string('slug')->unique()->index();
            $table->timestamps();
            $table->softDeletes();
            $table->userstamps();

            // Hybrid hierarchy performance indexes
            $table->index(['parent_id', 'sort_order']);
            $table->index(['type', 'parent_id']);
            $table->index(['depth', 'type']);
            $table->index(['parent_id', 'is_active']);

            // Standard performance indexes
            $table->index('name');
            $table->index('is_active');
            $table->index('sort_order');
            $table->index(['type', 'is_active']);
            $table->index(['is_active', 'name']);
            $table->index(['type', 'is_active', 'name']);

            // Unique constraints
            $table->unique(['name', 'type'], 'categories_name_type_unique');

            // Comments for documentation
            $table->comment('Categories table with hybrid closure table + adjacency list hierarchical structure and polymorphic support');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
