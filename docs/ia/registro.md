# Registro del uso real de inteligencia artificial

Base para el informe de IA de la memoria (§4.12 y §9 del enunciado). Cada entrada relaciona una herramienta y una petición con lo que se aceptó, lo que se modificó o descartó, y cómo se comprobó. **El equipo debe revisar y completar este registro con sus propias verificaciones**: una entrada sin comprobación no demuestra un uso crítico.

No se incluyen credenciales ni datos personales en las muestras.

## Herramienta

| Herramienta | Uso | Apartados afectados |
| --- | --- | --- |
| Claude (Anthropic), en claude.ai | Análisis de requisitos, diseño técnico, generación de código y configuración, revisión | Fases 1–4: requisitos, arquitectura, modelo de datos, contrato de API, guía de estilo, esqueleto del repositorio |

SABITECH WEB **no** incorpora ninguna funcionalidad de IA: la IA se usa solo como herramienta de apoyo al desarrollo.

## Entradas

| # | Fecha | Petición (resumen) | Resultado | Aceptado / modificado / descartado | Verificación |
| --- | --- | --- | --- | --- | --- |
| 1 | 2026-10-26 | «Prompt maestro» con el rol de equipo de ingeniería y el PDF del enunciado; pedir la Fase 1 (apartados A–L) | Documento de análisis: requisitos, casos de uso, reglas, riesgos, arquitectura, plan | Aceptado como propuesta. El equipo decidió React, demo en Docker local y reparto | Contraste con el PDF del enunciado (pendiente: revisión del equipo) |
| 2 | 2026-10-26 | Responder a las decisiones pendientes (K) | El equipo pidió un QR «cada 30 minutos»; la IA señaló que el enunciado exige 30 segundos | **Corregido por el equipo**: se mantienen 30 s | §1, §4.5 y criterio 7 de la rúbrica |
| 3 | 2026-10-26 | Fases 2–3: diseño técnico | ER, diccionario, estados, protocolo QR, contrato OpenAPI, guía de estilo, `compose.yaml` | Aceptado | OpenAPI validado con Redocly; `compose.yaml` con `docker compose config`; contrastes de color calculados |
| 4 | 2026-10-26 | Fase 4: crear el esqueleto en el repositorio | Laravel 13, React, migraciones, seeders, A01/A02/A29, Docker, CI | Aceptado tras pruebas | 29 pruebas de backend contra MySQL y 16 de frontend, ejecutadas. La IA detectó y corrigió un fallo propio que vaciaba la base de la demo (incidencia I-01) |
| 5 | 2026-10-26 | — | La IA dio un dominio de Docker Hub incorrecto para la lista de permitidos | Corregido | Al construir la imagen falló la descarga y se identificó el dominio correcto |

## Pendiente para el informe

- Explicar con palabras propias cada decisión aceptada y cómo se comprobó.
- Relacionar estos usos con las fuentes en vídeo en inglés analizadas para el checkpoint (§4.11).
