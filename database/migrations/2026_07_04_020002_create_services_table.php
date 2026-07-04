<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('services', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('faculty_id')
                ->nullable()
                ->constrained()
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->string('name');
            $table->string('type'); // App\Enums\ServiceType: service | labo | bureau
            $table->foreignId('responsible_user_id')
                ->nullable()
                ->constrained('users')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->timestamps();

            $table->unique(['faculty_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('services');
    }
};
