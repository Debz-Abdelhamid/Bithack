<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Schema.md §2.1 + faculty_id (documented divergence: required for N2
     * scoping per Security.md §3, Phase 5 approval routing by the room's
     * faculty, and the campus map's data shape from the legacy app).
     * NULL faculty = central/shared building.
     */
    public function up(): void
    {
        Schema::create('buildings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('faculty_id')
                ->nullable()
                ->constrained()
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('campus')->nullable();
            $table->string('address')->nullable();
            $table->smallInteger('floors_count')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('status')->default('active'); // App\Enums\BuildingStatus
            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('buildings');
    }
};
