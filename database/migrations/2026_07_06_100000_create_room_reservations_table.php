<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Schema.md §2.7 — two kinds in one table (decision 2026-07-06):
     * `timetable` (faculty-authored, confirmed directly by N2/A3) and
     * `request` (Enseignant-initiated ad-hoc bookings, starts pending).
     * `recurring_group_id` is a documented addition beyond the original
     * §2.7 listing — it lets a generated weekly series (one row per
     * occurrence) be identified/cancelled together without fragile string
     * matching on `recurring_rule`.
     */
    public function up(): void
    {
        Schema::create('room_reservations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('local_id')
                ->constrained()
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->string('source'); // App\Enums\ReservationSource
            $table->foreignId('requested_by_user_id')
                ->constrained('users')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->foreignId('teacher_user_id')
                ->nullable()
                ->constrained('users')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->string('module_name')->nullable();
            $table->string('level')->nullable(); // App\Enums\ReservationLevel
            $table->string('department')->nullable();
            $table->string('student_group')->nullable();
            $table->smallInteger('attendees_count')->nullable();
            $table->string('purpose')->nullable();
            $table->timestamp('start_at');
            $table->timestamp('end_at');
            $table->string('recurring_rule')->nullable();
            $table->uuid('recurring_group_id')->nullable();
            $table->string('status')->default('pending'); // App\Enums\ReservationStatus
            $table->foreignId('approved_by_user_id')
                ->nullable()
                ->constrained('users')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->string('external_calendar_ref')->nullable();
            $table->timestamps();

            // Schema.md §5 — composite index for overlap queries.
            $table->index(['local_id', 'start_at', 'end_at']);
            $table->index('status');
            $table->index('requested_by_user_id');
            $table->index('teacher_user_id');
            $table->index('recurring_group_id');
        });

        // Defense-in-depth on Postgres against concurrent double-booking:
        // no two CONFIRMED reservations on the same room may overlap in
        // time. Sqlite (tests) relies on the app-level guard in
        // RoomReservationObserver, which runs on every driver.
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('CREATE EXTENSION IF NOT EXISTS btree_gist');
            DB::statement(
                'ALTER TABLE room_reservations ADD CONSTRAINT room_reservations_no_overlap '
                .'EXCLUDE USING gist (local_id WITH =, tsrange(start_at, end_at) WITH &&) '
                ."WHERE (status = 'confirmed')"
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('room_reservations');
    }
};
