<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('invoice_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained('invoices')->onDelete('cascade');
            $table->foreignId('track_id')->constrained('tracks')->onDelete('restrict');
            $table->decimal('unit_price', 10, 2);
            $table->integer('quantity');

            // Indexes
            $table->index('invoice_id');
            $table->index('track_id');
            $table->index(['invoice_id', 'track_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_lines');
    }
};
