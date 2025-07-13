<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('first_name', 40);
            $table->string('last_name', 20);
            $table->string('company', 80)->nullable();
            $table->string('address', 70)->nullable();
            $table->string('city', 40)->nullable();
            $table->string('state', 40)->nullable();
            $table->string('country', 40)->nullable();
            $table->string('postal_code', 10)->nullable();
            $table->string('phone', 24)->nullable();
            $table->string('fax', 24)->nullable();
            $table->string('email', 60);
            $table->foreignId('support_rep_id')->nullable()->constrained('employees')->onDelete('set null');

            // Indexes
            $table->index('support_rep_id');
            $table->index(['last_name', 'first_name']);
            $table->index('email');
            $table->index('country');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
