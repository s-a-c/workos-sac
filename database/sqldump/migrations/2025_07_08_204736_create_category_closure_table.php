<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('category_closure', function (Blueprint $table) {
            $table->id();

            // Closure table relationships
            $table->foreignId('ancestor_id')->constrained('categories')->onDelete('cascade');
            $table->foreignId('descendant_id')->constrained('categories')->onDelete('cascade');
            $table->integer('depth')->default(0); // 0 = self, 1 = direct child, 2+ = deeper levels

            // Audit trail
            $table->timestamps();
            $table->userstamps();

            // Primary composite key for efficient queries
            $table->unique(['ancestor_id', 'descendant_id'], 'category_closure_unique');

            // Indexes for performance
            $table->index('ancestor_id');
            $table->index('descendant_id');
            $table->index('depth');
            $table->index(['ancestor_id', 'depth']);
            $table->index(['descendant_id', 'depth']);
            $table->index(['depth', 'ancestor_id']);

            // Comments for documentation
            $table->comment('Closure table for efficient hierarchical category queries and management');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('category_closure');
    }
};
