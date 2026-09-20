<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tournaments', function (Blueprint $table): void {
            // Турниры остаются анонимными: владелец появляется, только если организатор завёл аккаунт.
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->index(['owner_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('tournaments', function (Blueprint $table): void {
            $table->dropIndex(['owner_id', 'created_at']);
            $table->dropConstrainedForeignId('owner_id');
        });
    }
};
