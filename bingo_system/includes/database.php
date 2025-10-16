<?php
if (!defined('BINGO_SYSTEM')) die('Acceso denegado');

// Las líneas "use PDO;" y "use PDOException;" han sido eliminadas.

function get_db_connection(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ];
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            // En producción, es mejor loguear el error que mostrar un mensaje genérico.
            // error_log("DB Connection Error: " . $e->getMessage());
            die("Error de conexión a la base de datos. Por favor, intente más tarde.");
        }
    }
    return $pdo;
}