<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /*
         * Estado real de cada correo (R14). El índice único (reservation_id, type)
         * hace que reintentar no pueda crear una segunda notificación para la misma reserva.
         */
        Schema::create('email_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reservation_id')->constrained()->restrictOnDelete();
            $table->enum('type', ['reservation_confirmed']);
            $table->string('recipient', 190);
            $table->enum('status', ['pending', 'sending', 'sent', 'failed'])->default('pending')->index();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->string('last_error', 500)->nullable();
            $table->dateTime('last_attempt_at')->nullable();
            $table->dateTime('sent_at')->nullable();
            $table->datetimes();

            $table->unique(['reservation_id', 'type'], 'email_notifications_one_per_type');
        });

        /*
         * Intentos de lectura de QR (R30, SHOULD). Sirve al panel para detectar
         * códigos caducados o manipulados. No guarda el token.
         */
        Schema::create('scan_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('staff_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('talk_id')->constrained()->restrictOnDelete();
            $table->foreignId('reservation_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('result', ['registered', 'already', 'expired', 'invalid', 'wrong_talk', 'not_active']);
            $table->dateTime('created_at');

            $table->index(['talk_id', 'result']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scan_attempts');
        Schema::dropIfExists('email_notifications');
    }
};
