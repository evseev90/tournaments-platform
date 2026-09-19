<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stages', function (Blueprint $table): void {
            // Имя есть у группы («Группа A») и у плей-офф; у единственной стадии турнира его нет.
            $table->string('name', 60)->nullable()->after('tournament_id');
            $table->unsignedSmallInteger('phase')->default(1)->after('name');
            $table->index(['tournament_id', 'phase', 'name']);
        });
    }

    public function down(): void
    {
        Schema::table('stages', function (Blueprint $table): void {
            $table->dropIndex(['tournament_id', 'phase', 'name']);
            $table->dropColumn(['name', 'phase']);
        });
    }
};
