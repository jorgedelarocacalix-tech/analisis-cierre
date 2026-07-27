<?php
// Credenciales de la base de datos MySQL de cPanel.
// Reemplaza estos valores con los que te dé tu programador al crear la base de datos.
define('DB_HOST', 'localhost');
define('DB_NAME', 'CAMBIAR_nombre_basedatos');
define('DB_USER', 'CAMBIAR_usuario');
define('DB_PASS', 'CAMBIAR_clave');

function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $pdo = new PDO(
            'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
            DB_USER, DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
        );
    }
    return $pdo;
}
