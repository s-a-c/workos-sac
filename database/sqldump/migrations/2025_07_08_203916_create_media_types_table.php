<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('media_types', function (Blueprint $table) {
            $table->id();

            // Core fields
            $table->string('name', 120);
            $table->text('description')->nullable();
            $table->string('file_extension', 10)->nullable();
            $table->string('mime_type', 100)->nullable();
            $table->boolean('is_audio')->default(true);
            $table->boolean('is_video')->default(false);
            $table->boolean('is_active')->default(true);

            // Modern Laravel features
            $table->char('public_id', 36)->unique()->index(); // UUID
            $table->string('slug')->unique()->index();
            $table->timestamps();
            $table->softDeletes();
            $table->userstamps();

            // Indexes for performance
            $table->index('name');
            $table->index('file_extension');
            $table->index('mime_type');
            $table->index(['is_active', 'is_audio']);
            $table->index(['is_active', 'is_video']);

            // Comments for documentation
            $table->comment('Media types table with file format metadata and modern Laravel features');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_types');
    }
};
