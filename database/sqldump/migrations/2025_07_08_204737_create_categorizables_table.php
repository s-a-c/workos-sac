<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('categorizables', function (Blueprint $table) {
            $table->id();

            // Polymorphic relationship columns
            $table->foreignId('category_id')->constrained('categories')->onDelete('cascade');
            $table->morphs('categorizable'); // Creates categorizable_type and categorizable_id

            // Additional metadata
            $table->integer('sort_order')->default(0);
            $table->json('metadata')->nullable(); // Flexible metadata for the relationship

            // Audit trail
            $table->timestamps();
            $table->userstamps();

            // Indexes for performance
            $table->index('category_id');
            $table->index(['category_id', 'categorizable_type']);
            $table->index(['categorizable_type', 'categorizable_id', 'category_id'], 'categorizables_full_index');
            $table->index('sort_order');

            // Unique constraint to prevent duplicate assignments
            $table->unique([
                'category_id',
                'categorizable_type',
                'categorizable_id',
            ], 'categorizables_unique');

            // Comments for documentation
            $table->comment('Polymorphic pivot table for category assignments to any model');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('categorizables');
    }
};
