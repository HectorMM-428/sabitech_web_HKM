<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // En desarrollo y pruebas, avisar de errores típicos (consultas N+1,
        // atributos que no existen o que no se pueden asignar en masa).
        Model::shouldBeStrict(! $this->app->isProduction());

        $this->configureRateLimiting();
    }

    /**
     * Límites contra fuerza bruta y abuso de endpoints (§9 del prompt, RNF-01).
     * Al superarlos, la API responde 429 TOO_MANY_REQUESTS con Retry-After.
     */
    private function configureRateLimiting(): void
    {
        // 5 intentos por minuto para cada combinación de email e IP.
        RateLimiter::for('login', fn (Request $request) => Limit::perMinute(5)
            ->by(mb_strtolower((string) $request->input('email')).'|'.$request->ip()));

        RateLimiter::for('register', fn (Request $request) => Limit::perMinute(5)->by($request->ip()));

        // Credencial QR: el cliente pide un token cada 30 s; 10/min deja margen sin permitir abuso.
        RateLimiter::for('credential', fn (Request $request) => Limit::perMinute(10)
            ->by((string) ($request->user()?->id ?? $request->ip())));

        // Resto de la API autenticada.
        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(60)
            ->by((string) ($request->user()?->id ?? $request->ip())));
    }
}
