<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Reservas.
 *
 * Una persona puede tener varias reservas canceladas de la misma charla (historial),
 * pero solo una confirmada. Lo garantiza la base de datos con un índice único sobre
 * (user_id, talk_id, active_key): active_key vale 1 si la reserva está confirmada y
 * NULL si está cancelada, y MySQL permite varios NULL en un índice único (RN-03).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reservations', function (Blueprint $table) {
            $table->id();
            $table->char('reference', 10)->unique();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('talk_id')->constrained()->restrictOnDelete();
            $table->enum('status', ['confirmed', 'cancelled']);
            $table->dateTime('confirmed_at');
            $table->dateTime('cancelled_at')->nullable();
            $table->unsignedTinyInteger('active_key')
                ->nullable()
                ->storedAs("IF(`status` = 'confirmed', 1, NULL)");
            $table->datetimes();

            $table->unique(['user_id', 'talk_id', 'active_key'], 'reservations_one_active_per_user_talk');
            // Contar plazas ocupadas de una charla.
            $table->index(['talk_id', 'status']);
        });

        DB::statement(
            "ALTER TABLE reservations ADD CONSTRAINT reservations_cancelled_at_matches_status CHECK (
                (`status` = 'cancelled' AND `cancelled_at` IS NOT NULL)
                OR (`status` = 'confirmed' AND `cancelled_at` IS NULL)
            )"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('reservations');
    }
};
