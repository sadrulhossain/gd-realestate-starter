<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Contrib extensions shipped with the postgres image: typo-tolerant search (pg_trgm) and radius search (cube + earthdistance).
return new class extends Migration
{
    public function up(): void
    {
        foreach (['pg_trgm', 'cube', 'earthdistance'] as $extension) {
            DB::statement("CREATE EXTENSION IF NOT EXISTS {$extension}");
        }
    }

    public function down(): void
    {
        foreach (['earthdistance', 'cube', 'pg_trgm'] as $extension) {
            DB::statement("DROP EXTENSION IF EXISTS {$extension}");
        }
    }
};
