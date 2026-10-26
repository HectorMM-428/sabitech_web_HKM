<?php

/*
 * Configuración propia de SABITECH WEB. Todo valor sensible llega por variables de
 * entorno; aquí solo hay nombres y valores por defecto no secretos.
 */
return [

    /*
     * QR temporal (diseño, sección 5). El token caduca a los `ttl_seconds` de su emisión
     * según el reloj del servidor; `leeway_seconds` cubre la latencia entre el escaneo
     * y la llegada de la petición. Validez máxima: ttl + leeway.
     */
    'qr' => [
        'signing_key' => env('QR_SIGNING_KEY'),
        'ttl_seconds' => (int) env('QR_TTL_SECONDS', 30),
        'leeway_seconds' => (int) env('QR_LEEWAY_SECONDS', 5),
    ],

    /* Resultados por página en listados paginados (contrato de la API). */
    'per_page' => 20,

    /*
     * Contraseña de las cuentas de laboratorio que crea el seeder. Son cuentas ficticias
     * para la demo y las pruebas; en la demo puede cambiarse con SEED_LAB_PASSWORD.
     */
    'lab_password' => env('SEED_LAB_PASSWORD', 'Sabitech-Lab-2026'),

];
