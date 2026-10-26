<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Charlas. Horas en UTC. Las reglas que la base de datos puede garantizar por sí misma
 * se declaran como CHECK, para que ningún error de código las salte.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('talks', function (Blueprint $table) {
            $table->id();
            $table->string('title', 160);
            $table->text('description');
            $table->foreignId('speaker_id')->constrained()->restrictOnDelete();
            $table->foreignId('room_id')->constrained()->restrictOnDelete();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->unsignedSmallInteger('capacity');
            $table->enum('status', ['draft', 'published', 'cancelled'])->default('draft');
            $table->datetimes();

            // Consulta del programa: charlas publicadas ordenadas por hora.
            $table->index(['status', 'starts_at']);
        });

        DB::statement('ALTER TABLE talks ADD CONSTRAINT talks_ends_after_starts CHECK (ends_at > starts_at)');
        DB::statement('ALTER TABLE talks ADD CONSTRAINT talks_capacity_positive CHECK (capacity > 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('talks');
    }
};
