# SABITECH WEB

Aplicación web para consultar las charlas de las jornadas tecnológicas **SABITECH**, reservar plaza, recibir la confirmación por correo y registrar la asistencia con un código QR que cambia cada 30 segundos.

Proyecto intermodular de 2.º DAW (XTART). Desarrollado por **Sabitech**: Karim Gómez (backend y datos), Héctor Molina (frontend e interfaces) y Michael Leguizamón (entorno, CI y pruebas).

| Pieza | Tecnología |
| --- | --- |
| Frontend | React 19 + TypeScript + Vite |
| Backend | PHP 8.4 + Laravel 13 (API REST `/api/v1`) |
| Base de datos | MySQL 8.4 LTS |
| Ejecución | Docker Compose con 3 contenedores: `front`, `back`, `db` |
| Contrato de la API | [`docs/api/openapi.yaml`](docs/api/openapi.yaml) (OpenAPI 3.1) |

## Estado actual

Hito **H2 · esqueleto ejecutable** (Fase 4). Lo que funciona hoy, con pruebas ejecutadas:

| Funcionalidad | Estado |
| --- | --- |
| Programa de charlas con filtros y paginación (A01) | Hecho y probado |
| Detalle de charla (A02) | Hecho y probado |
| Estado del servicio `/api/v1/health` (A29) | Hecho y probado |
| Modelo de datos completo: 8 tablas con índices únicos y CHECK | Hecho y probado |
| Datos de laboratorio reproducibles | Hecho |
| Registro, login, reservas, correo, QR, panel | **Pendiente** (hitos H3 a H10) |

El detalle de cada prueba está en [`docs/pruebas/registro.md`](docs/pruebas/registro.md).

## Estructura

```text
.
├── compose.yaml            # demo: imágenes de producción
├── compose.override.yaml   # desarrollo: código montado y recarga en caliente
├── .env.example            # variables de entorno (sin secretos)
├── backend/                # API Laravel
├── frontend/               # SPA React (+ nginx/ para la demo)
├── docker/db/init/         # crea la base de datos de pruebas en el primer arranque
├── docs/                   # contrato de API, decisiones, pruebas, registro de IA
└── scripts/                # copia de seguridad y restauración
```

## Requisitos

- Docker Desktop (o Docker Engine) con Compose v2.24 o superior.
- Git.
- Para la demo en red local: [mkcert](https://github.com/FiloSottile/mkcert).

No hace falta instalar PHP, Composer, Node ni MySQL en el equipo: van dentro de los contenedores.

## 1. Configuración (una sola vez)

```bash
git clone https://github.com/HectorMM-428/sabitech_web_HKM.git
cd sabitech_web_HKM
cp .env.example .env
```

Rellena en `.env`:

| Variable | Cómo obtenerla |
| --- | --- |
| `DB_PASSWORD`, `DB_ROOT_PASSWORD` | Inventa dos contraseñas largas |
| `QR_SIGNING_KEY` | `openssl rand -base64 32` |
| `APP_KEY` | `echo "base64:$(openssl rand -base64 32)"` (pega el resultado completo, con `base64:`) |
| `MAIL_*` | Credenciales del buzón SMTP de pruebas (solo cuando se implemente el correo) |

El fichero `.env` nunca se sube al repositorio.

## 2. Desarrollo

```bash
docker compose up -d --build
docker compose ps          # espera a que back aparezca como healthy (instala dependencias la primera vez)
docker compose exec back php artisan migrate --seed
```

Abre <http://localhost:5173>. Los cambios en `frontend/src` y `backend/app` se ven sin reconstruir nada.

## 3. Demo en red local (HTTPS)

La cámara del móvil del personal de acceso solo funciona con HTTPS, así que la demo usa certificados locales.

1. Genera el certificado (una vez, en el portátil que hará de servidor):
   ```bash
   mkcert -install
   mkdir -p certs
   mkcert -cert-file certs/sabitech.pem -key-file certs/sabitech-key.pem sabitech.test localhost 127.0.0.1 <IP-del-portátil>
   ```
2. En cada dispositivo de prueba, instala la CA de mkcert (`mkcert -CAROOT` indica dónde está) y añade al fichero `hosts`:
   ```text
   <IP-del-portátil>  sabitech.test
   ```
3. Arranca **sin** el fichero de desarrollo:
   ```bash
   docker compose -f compose.yaml up -d --build
   docker compose -f compose.yaml exec back php artisan migrate --seed --force
   ```
4. Abre <https://sabitech.test>. Comprueba el estado en <https://sabitech.test/api/v1/health>.

## Cuentas de laboratorio

Creadas por el seeder. Son ficticias; la contraseña de todas es `Sabitech-Lab-2026` (se puede cambiar con `SEED_LAB_PASSWORD`).

| Perfil | Email |
| --- | --- |
| Administración | `admin@sabitech.test` |
| Personal de acceso | `staff@sabitech.test` |
| Asistentes | `lucia@sabitech.test`, `pablo@sabitech.test`, `irene@sabitech.test` |

Las charlas se crean dentro de 7 y 8 días a partir de la fecha en que se ejecuta el seeder. La charla «La última plaza: concurrencia en sistemas de reservas» queda **completa** a propósito, para probar el rechazo por aforo.

## Pruebas

```bash
# Backend (usa siempre la base de datos sabitech_test, nunca la de la demo)
docker compose exec back php artisan test

# Frontend
docker compose exec front npm test
docker compose exec front npm run lint
docker compose exec front npm run typecheck
```

GitHub Actions ejecuta todo lo anterior en cada subida, además de validar el contrato OpenAPI, construir las imágenes y buscar secretos ([`.github/workflows/ci.yml`](.github/workflows/ci.yml)).

## Contrato de la API

[`docs/api/openapi.yaml`](docs/api/openapi.yaml) es la fuente de verdad. Si cambia, regenera los tipos del frontend y súbelos junto al cambio:

```bash
cd frontend && npm run gen:api
```

La CI falla si los tipos no coinciden con el contrato.

## Copias de seguridad

```bash
./scripts/backup.sh                                   # crea backups/sabitech-AAAAMMDD-HHMMSS.sql.gz
./scripts/restore.sh backups/sabitech-<fecha>.sql.gz  # pide confirmación
```

## Flujo de trabajo con Git

- `main` solo recibe cambios por pull request, revisada por otro integrante y con la CI en verde.
- Ramas: `feature/<requisito>-<descripcion>`, por ejemplo `feature/R08-reserva`.
- Commits con prefijo: `feat:`, `fix:`, `test:`, `docs:`, `chore:`.

## Documentación

- [`docs/decisiones.md`](docs/decisiones.md): decisiones de arquitectura y sus cambios.
- [`docs/pruebas/registro.md`](docs/pruebas/registro.md): pruebas T01–T22 con su estado real.
- [`docs/ia/registro.md`](docs/ia/registro.md): uso real de inteligencia artificial.
