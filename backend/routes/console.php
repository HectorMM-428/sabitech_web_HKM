<?php

use Illuminate\Support\Facades\Schedule;

/*
 * Tareas programadas. Las ejecuta el proceso `scheduler` del contenedor back
 * (php artisan schedule:work, ver backend/docker/supervisord.conf).
 */

// Correos que quedaron en «enviando» porque el proceso se cayó → «fallido», para poder reintentarlos.
Schedule::command('sabitech:release-stuck-notifications')->everyFiveMinutes()->withoutOverlapping();
