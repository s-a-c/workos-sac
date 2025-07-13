<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->onDelete('cascade');
            $table->dateTime('invoice_date');
            $table->string('billing_address', 70)->nullable();
            $table->string('billing_city', 40)->nullable();
            $table->string('billing_state', 40)->nullable();
            $table->string('billing_country', 40)->nullable();
            $table->string('billing_postal_code', 10)->nullable();
            $table->decimal('total', 10, 2);

            // Indexes
            $table->index('customer_id');
            $table->index('invoice_date');
            $table->index('billing_country');
            $table->index('total');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
