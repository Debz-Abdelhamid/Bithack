<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Laravel's stock notifications table stores `data` as text, but Filament's
     * database notifications filter on `data->>'format'`, which PostgreSQL only
     * permits on json/jsonb columns (SQLite tolerates text, which is why tests
     * on sqlite never hit this). Static DDL — no user input involved.
     */
    public function up(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE notifications ALTER COLUMN data TYPE jsonb USING data::jsonb');
        }
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE notifications ALTER COLUMN data TYPE text USING data::text');
        }
    }
};
