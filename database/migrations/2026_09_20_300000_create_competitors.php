<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('competitors', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->string('name', 100);
            // Нормализованное имя связывает одного и того же игрока в разных турнирах аккаунта.
            $table->string('normalized_name', 100);
            $table->string('type', 16);
            $table->timestampTz('created_at');
            $table->unique(['owner_id', 'type', 'normalized_name']);
        });

        Schema::table('participants', function (Blueprint $table): void {
            $table->foreignUuid('competitor_id')->nullable()->constrained('competitors')->nullOnDelete();
            $table->index('competitor_id');
        });
    }

    public function down(): void
    {
        Schema::table('participants', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('competitor_id');
        });
        Schema::dropIfExists('competitors');
    }
};
