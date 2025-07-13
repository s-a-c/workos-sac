<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('slug')
                ->unique()
                ->nullable()
                ->after('email');
        });

        // Generate slugs for existing users using the User model
        // This leverages spatie/laravel-sluggable's built-in uniqueness handling
        User::whereNull('slug')->chunk(100, function ($users) {
            foreach ($users as $user) {
                // The save() method will trigger slug generation via the HasSlug trait
                $user->save();
            }
        });

        // Make slug required after populating existing records
        Schema::table('users', function (Blueprint $table) {
            $table->string('slug')->nullable(false)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['slug']);
            $table->dropColumn('slug');
        });
    }
};
