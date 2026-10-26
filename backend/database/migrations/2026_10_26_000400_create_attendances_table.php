<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Asistencias registradas (por QR o manualmente).
 *
 * user_id y talk_id se copian de la reserva a propósito: el índice único
 * (user_id, talk_id) impide dos asistencias de la misma persona a la misma charla
 * aunque existan varias reservas históricas (RN-10).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reservation_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('talk_id')->constrained()->restrictOnDelete();
            $table->foreignId('validated_by')->constrained('users')->restrictOnDelete();
            $table->enum('method', ['qr', 'manual']);
            $table->string('manual_reason')->nullable();
            $table->dateTime('validated_at');
            $table->dateTime('created_at')->nullable();

            $table->unique(['user_id', 'talk_id'], 'attendances_one_per_user_talk');
        });

        DB::statement(
            "ALTER TABLE attendances ADD CONSTRAINT attendances_manual_needs_reason
             CHECK (`method` = 'qr' OR `manual_reason` IS NOT NULL)"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('attendances');
    }
};
