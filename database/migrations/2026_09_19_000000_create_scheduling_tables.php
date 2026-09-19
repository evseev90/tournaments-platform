<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('schedules', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            // Ссылка на турнир из другого контекста: без внешнего ключа, связь поддерживают события.
            $table->uuid('tournament_id')->unique();
            $table->jsonb('settings');
            $table->timestampTz('created_at');
            $table->timestampTz('updated_at');
        });

        Schema::create('venues', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('schedule_id')->constrained()->cascadeOnDelete();
            $table->string('name', 60);
            $table->unsignedSmallInteger('position');
            $table->index(['schedule_id', 'position']);
        });

        Schema::create('schedule_assignments', function (Blueprint $table): void {
            $table->foreignUuid('schedule_id')->constrained()->cascadeOnDelete();
            $table->uuid('match_id');
            $table->foreignUuid('venue_id')->constrained()->cascadeOnDelete();
            $table->timestampTz('starts_at');
            $table->unsignedSmallInteger('minutes');
            $table->uuid('home_id')->nullable();
            $table->uuid('away_id')->nullable();
            $table->primary(['schedule_id', 'match_id']);
            $table->index(['venue_id', 'starts_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schedule_assignments');
        Schema::dropIfExists('venues');
        Schema::dropIfExists('schedules');
    }
};
