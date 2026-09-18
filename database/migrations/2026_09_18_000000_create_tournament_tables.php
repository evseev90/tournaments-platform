<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tournaments', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name', 100);
            $table->string('sport', 32);
            $table->jsonb('rules');
            $table->string('status', 16);
            $table->char('admin_token', 40)->unique();
            $table->string('public_slug', 20)->unique();
            $table->timestampTz('created_at');
            $table->timestampTz('updated_at');
        });

        Schema::create('participants', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tournament_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('type', 16);
            $table->jsonb('members');
            $table->unsignedSmallInteger('seed');
            $table->index(['tournament_id', 'seed']);
        });

        Schema::create('stages', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tournament_id')->constrained()->cascadeOnDelete();
            $table->string('format', 32);
            $table->jsonb('settings');
            $table->string('status', 16);
            $table->timestampTz('created_at');
            $table->timestampTz('updated_at');
        });

        Schema::create('fixtures', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('stage_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('round');
            $table->unsignedSmallInteger('position');
            $table->foreignUuid('home_id')->nullable()->constrained('participants')->restrictOnDelete();
            $table->foreignUuid('away_id')->nullable()->constrained('participants')->restrictOnDelete();
            $table->jsonb('outcome')->nullable();
            $table->boolean('bye')->default(false);
            $table->uuid('next_win_fixture_id')->nullable();
            $table->string('next_win_side', 4)->nullable();
            $table->uuid('next_lose_fixture_id')->nullable();
            $table->string('next_lose_side', 4)->nullable();
            $table->unique(['stage_id', 'round', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fixtures');
        Schema::dropIfExists('stages');
        Schema::dropIfExists('participants');
        Schema::dropIfExists('tournaments');
    }
};
