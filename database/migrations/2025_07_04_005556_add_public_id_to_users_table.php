<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\Uid\Ulid;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Step 1: Create nullable public_id
        Schema::table('users', function (Blueprint $table) {
            $table->char('public_id', 26)->nullable(); // ULID - initially nullable
        });

        // Step 2: Backfill ULIDs and recalculate slugs
        User::whereNull('public_id')->chunk(100, function ($users) {
            // Step 2.1: Prepare slug for recalculation
            // Drop existing slug index and make slug nullable to avoid conflicts during backfill
            Schema::table('users', function (Blueprint $table) {
                $table->dropUnique(['slug']);                 // Drop the existing unique index
                $table->string('slug')->nullable()->change(); // Make slug nullable
            });

            // Step 2.2: Backfill public_id and recalculate slugs
            foreach ($users as $user) {
                // Generate public_id
                $user->public_id = (string)Ulid::generate();

                // Force slug regeneration based on new public_id
                // Clear the slug to force regeneration based on public_id
                $user->slug = null;

                // Save will trigger slug generation via HasSlug trait
                $user->save();
            }

            // Step 2.3: Make slug unique and non-nullable
            Schema::table('users', function (Blueprint $table) {
                $table->string('slug')->nullable(false)->unique()->change();
            });
        });

        // Step 3: Make public_id unique and non-nullable
        Schema::table('users', function (Blueprint $table) {
            $table->char('public_id', 26)->nullable(false)->unique()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Drop the public_id column
            $table->dropColumn('public_id');
        });
    }
};
