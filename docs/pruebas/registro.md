# Registro de pruebas

Estado real de las 22 pruebas obligatorias del plan (Fase 1, sección L). Una prueba solo figura como **superada** si se ha ejecutado y hay evidencia; las demás están **pendientes** con el hito en que se harán.

Última actualización: 2026-10-26, rama `feature/H2-esqueleto`.

## Entorno de las ejecuciones de hoy

- Backend: PHP 8.3.6, Laravel 13.38.0, PHPUnit 12.5.40, contra **MySQL 8.0.46** (en el entorno de desarrollo de la IA no había imagen de MySQL 8.4; la CI de GitHub repite las pruebas con MySQL 8.4).
- Frontend: Node 22, Vitest 5.0.5 con jsdom.
- Resultado: backend **29 pruebas, todas superadas**; frontend **16 pruebas, todas superadas**.

## Pruebas obligatorias

| ID | Prueba | Estado | Evidencia o plan |
| --- | --- | --- | --- |
| T01 | Consulta de una charla existente | **Superada** | `TalkCatalogTest::t01_…`: 200 con todos los campos; hora guardada en UTC y devuelta con el desfase de Madrid |
| T02 | Consulta de una charla inexistente | **Superada** | `TalkCatalogTest::t02_…`: 404 con `{"error":{"code":"NOT_FOUND",…}}`. En el frontend, `TalkDetailPage.test.tsx` muestra «Esta charla no existe o no está publicada» |
| T03 | Login válido e inválido | Pendiente | H3 |
| T04 | Acceso autorizado y prohibido | Pendiente | H3 |
| T05 | Reserva correcta | Pendiente | H4 |
| T06 | Reserva duplicada | Pendiente (parcial) | H4. La base de datos ya rechaza una segunda reserva confirmada: `DatabaseIntegrityTest::no_puede_haber_dos_reservas_confirmadas…` |
| T07 | Reserva con aforo completo | Pendiente | H4. Los datos de laboratorio ya incluyen una charla completa |
| T08 | Dos solicitudes concurrentes a la última plaza | Pendiente | H9, con procesos reales contra MySQL |
| T09 | Cancelación autorizada | Pendiente | H6 |
| T10 | Cancelar una reserva ajena | Pendiente | H6 |
| T11 | Persistencia tras cerrar sesión | Pendiente | H4 |
| T12 | Correo enviado | Pendiente | H7 |
| T13 | Fallo de correo y reintento | Pendiente | H7 |
| T14 | QR vigente | Pendiente | H8 |
| T15 | QR caducado | Pendiente | H10 |
| T16 | QR manipulado | Pendiente | H10 |
| T17 | QR de reserva inválida | Pendiente | H10 |
| T18 | Asistencia duplicada | Pendiente (parcial) | H10. La base de datos ya rechaza una segunda asistencia de la misma persona y charla: `DatabaseIntegrityTest::no_puede_haber_dos_asistencias…` |
| T19 | Validación QR sin permisos | Pendiente | H8 |
| T20 | Responsive y accesible | Pendiente | H11. Hoy: estados con texto e icono, foco al cambiar de página, enlace para saltar al contenido, contrastes medidos |
| T21 | Recuperación desde copia | Pendiente | H12. Scripts `scripts/backup.sh` y `scripts/restore.sh` escritos, sin ejecutar |
| T22 | Construcción y pruebas automatizadas | Pendiente (parcial) | Flujo `.github/workflows/ci.yml` escrito; superada cuando haya una ejecución en verde en GitHub |

## Otras pruebas ya automatizadas

| Ámbito | Pruebas |
| --- | --- |
| Programa (A01) | Solo charlas publicadas; plazas descuentan solo reservas confirmadas; charla llena con 0 plazas; orden por hora y paginación; filtro por día en hora de Madrid; búsqueda por título o ponente con comodines escapados; 422 con campos para filtros mal formados; lista vacía |
| Integridad en MySQL | Varias canceladas y una confirmada por persona y charla; `active_key` calculada por MySQL; cancelada exige fecha; aforo positivo; fin posterior al inicio; asistencia manual exige motivo; no se borra una charla con reservas; el rol no se asigna en masa |
| API | Formato de error único en 404 y 405; id no numérico; salud de base de datos y cola, incluida una cola atascada (503) |
| Frontend | Cliente HTTP (errores de la API, fallo de red, cabecera CSRF solo al modificar datos); fechas en Madrid con horario de verano; catálogo con carga, vacío, error y reintento, y filtros en la URL; detalle y 404 |

## Incidencias

| ID | Fecha | Incidencia | Corrección | Comprobación |
| --- | --- | --- | --- | --- |
| I-01 | 2026-10-26 | Al ejecutar las pruebas con `DB_DATABASE=sabitech` en el entorno (como dentro del contenedor), PHPUnit vació la base de la demo: de 6 charlas a 0. PHPUnit forzaba `$_ENV`, pero Laravel lee antes `$_SERVER` | Forzar también `<server>` en `phpunit.xml`; el contenedor db crea `sabitech_test`; nueva prueba `TestEnvironmentTest` que falla si la base no es la de pruebas | Repetido con `DB_DATABASE=sabitech`: la demo conserva sus 6 charlas tras `php artisan test` y tras `phpunit` |
