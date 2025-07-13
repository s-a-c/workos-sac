<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->id();

            // Core fields
            $table->string('last_name', 50);
            $table->string('first_name', 50);
            $table->string('title', 100)->nullable();
            $table->string('department', 50)->nullable();
            $table->foreignId('reports_to')->nullable()->constrained('employees')->onDelete('set null');
            $table->date('birth_date')->nullable();
            $table->date('hire_date')->nullable();
            $table->date('termination_date')->nullable();
            $table->string('employee_number', 20)->unique()->nullable();
            $table->decimal('salary', 10, 2)->nullable();
            $table->boolean('is_active')->default(true);

            // Contact information
            $table->string('address', 100)->nullable();
            $table->string('city', 50)->nullable();
            $table->string('state', 50)->nullable();
            $table->string('country', 50)->nullable();
            $table->string('postal_code', 20)->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('fax', 30)->nullable();
            $table->string('email', 100)->nullable();
            $table->string('emergency_contact', 100)->nullable();
            $table->string('emergency_phone', 30)->nullable();

            // Modern Laravel features
            $table->string('public_id', 26)->unique()->index(); // ULID
            $table->string('slug')->unique()->index();
            $table->timestamps();
            $table->softDeletes();
            $table->userstamps();

            // Indexes for performance
            $table->index('reports_to');
            $table->index(['last_name', 'first_name']);
            $table->index('email');
            $table->index('employee_number');
            $table->index('department');
            $table->index(['is_active', 'department']);
            $table->index(['hire_date', 'is_active']);

            // Comments for documentation
            $table->comment('Employees table with HR management features and modern Laravel capabilities');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};
