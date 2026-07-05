<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Schema.md §2.2 — locals (salles, bureaux, labos, amphis…). Faculty is
     * derived through the building; patrimoine rows are restrict-on-delete
     * by convention (§0).
     */
    public function up(): void
    {
        Schema::create('locals', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('building_id')
                ->constrained()
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('type'); // App\Enums\LocalType
            $table->smallInteger('floor')->nullable();
            $table->smallInteger('capacity')->nullable();
            $table->decimal('surface_m2', 8, 2)->nullable();
            $table->foreignId('responsible_user_id')
                ->nullable()
                ->constrained('users')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->string('status')->default('available'); // App\Enums\LocalStatus
            $table->timestamps();

            $table->index(['building_id', 'type']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('locals');
    }
};
