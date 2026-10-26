# Registro de decisiones

Decisiones que condicionan el desarrollo, con su motivo y sus consecuencias (§4.12 del enunciado). El análisis completo está en los documentos de la Fase 1 (requisitos y arquitectura) y de las Fases 2–3 (diseño técnico). Cada cambio de una decisión se añade como fila nueva; no se borran las anteriores.

## Decisiones vigentes

| ID | Fecha | Decisión | Motivo | Consecuencia |
| --- | --- | --- | --- | --- |
| K-01 | 2026-10-26 | Frontend con React + TypeScript (Vite) | Curva más corta; ecosistema amplio para el QR | Tipos generados desde el contrato OpenAPI |
| AD-02 | 2026-10-26 | Laravel 13 con Sanctum en modo SPA (cookie de sesión) | Cookie HttpOnly no legible por JavaScript; CSRF incluido | Front y API deben compartir dominio (AD-01) |
| AD-01 | 2026-10-26 | Un solo dominio: Nginx sirve la SPA y pasa `/api` y `/sanctum` a PHP-FPM | Evita CORS y problemas de cookies | Nginx vive en el contenedor front |
| AD-04 | 2026-10-26 | Aforo con transacción + `SELECT … FOR UPDATE` sobre la charla; duplicados con índice único sobre `active_key` | Comprobar antes de insertar no basta con peticiones simultáneas | La prueba de concurrencia debe usar MySQL y procesos reales |
| K-05 | 2026-10-26 | QR del asistente, renovado cada **30 s**, validado por el staff; token HMAC-SHA256 con `exp = iat + 30 s` y 5 s de tolerancia | Exigido por §4.5 y criterio 7 de la rúbrica | Validez máxima de un token: 35 s |
| K-09 / K-11 | 2026-10-26 | Demo en local con Docker Compose y 3 contenedores: front, back, db | Decisión del equipo; reproducible en cualquier portátil | HTTPS con mkcert y nombre `sabitech.test` en la red local (K-18) |
| K-10 | 2026-10-26 | Correo de laboratorio con un buzón SMTP de pruebas externo | No añade un cuarto contenedor | La demo necesita internet para enviar correo |
| K-14 / K-15 | 2026-10-26 | Equipo de tres (Héctor Molina, Karim Gómez, Michael Leguizamón), empresa «Sabitech»; trío admitido por la coordinación | — | Reparto: Karim backend, Héctor frontend, Michael entorno y pruebas |
| V-01 | 2026-10-26 | Versiones: PHP 8.4, Laravel 13, MySQL 8.4 LTS, Node 26 LTS | Versiones con soporte durante todo el curso; MySQL 8.0 sin soporte desde abril de 2026 | Sustituye «MySQL 8» de la Fase 1 |

## Cambios e incidencias

| Fecha | Qué cambió | Por qué |
| --- | --- | --- |
| 2026-10-26 | Nuevo código de error `METHOD_NOT_ALLOWED` (405) en el contrato | El formato de error único debía cubrir también métodos HTTP no admitidos |
| 2026-10-26 | La credencial QR (A12) responde 409 `ATTENDED` si la asistencia ya está registrada | El recorrido de la acreditación lo necesitaba y el contrato no lo contemplaba |
| 2026-10-26 | PHPUnit fuerza `DB_DATABASE=sabitech_test` también en `$_SERVER`, y el contenedor db crea esa base | Incidencia: dentro de Docker las pruebas podían vaciar la base de la demo (ver `docs/pruebas/registro.md`, I-01) |
