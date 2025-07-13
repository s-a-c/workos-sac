<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // Apply SQLite optimizations via migration
        $pragmas = [
            'auto_vacuum = incremental',
            'busy_timeout = 5000',
            'cache_size = -64000',      // 64MB cache
            'foreign_keys = ON',
            'incremental_vacuum',
            'journal_mode = WAL',
            // 'mmap_size = 2147483648',    // 2GB memory mapping
            'mmap_size = 268435456',    // 256MB memory mapping
            'page_size = 32768',
            // 'synchronous = FULL',
            'synchronous = NORMAL',  // Reduces disk sync frequency (vs FULL)
            'temp_store = MEMORY',
            'wal_autocheckpoint = 1000',
        ];

        foreach ($pragmas as $pragma) {
            DB::statement("PRAGMA {$pragma}");
        }
    }

    public function down(): void
    {
        // Reset to default SQLite settings
        $pragmas = [
            'cache_size = -2000',
            'journal_mode = DELETE',
            'mmap_size = 0',
            'synchronous = FULL',
            'temp_store = DEFAULT',
        ];

        foreach ($pragmas as $pragma) {
            DB::statement("PRAGMA {$pragma}");
        }
    }
};
