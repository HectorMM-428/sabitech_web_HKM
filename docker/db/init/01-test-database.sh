#!/bin/sh
# Lo ejecuta la imagen oficial de MySQL solo al crear el volumen por primera vez.
# Crea una base de datos separada para las pruebas, para que `php artisan test`
# (que vacía las tablas en cada prueba) nunca toque los datos de la demo.
set -e
mysql -uroot -p"$MYSQL_ROOT_PASSWORD" <<SQL
CREATE DATABASE IF NOT EXISTS sabitech_test CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;
GRANT ALL PRIVILEGES ON sabitech_test.* TO '${MYSQL_USER}'@'%';
FLUSH PRIVILEGES;
SQL
