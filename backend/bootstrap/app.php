<?php

use App\Http\ApiErrorRenderer;
use App\Http\Middleware\EnsureUserHasRole;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        // Rutas versionadas: /api/v1/... (contrato docs/api/openapi.yaml)
        apiPrefix: 'api/v1',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Detrás de Nginx (contenedor front): confiar en sus cabeceras X-Forwarded-*
        // para que Laravel sepa que la petición original llegó por HTTPS.
        $middleware->trustProxies(at: '*');

        // Sanctum en modo SPA: las peticiones de nuestro frontend usan la cookie de
        // sesión y la protección CSRF (AD-02).
        $middleware->statefulApi();

        $middleware->alias([
            'role' => EnsureUserHasRole::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->is('sanctum/*') || $request->expectsJson(),
        );

        // Formato de error único para toda la API.
        $exceptions->render(fn (Throwable $e, Request $request) => ApiErrorRenderer::render($e, $request));
    })->create();
