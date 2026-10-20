<?php
/**
 * Innovatrónica S.A. - Sistema de Control de Stock
 * Configuración de Conexión a Base de Datos (PDO)
 * Zona horaria: America/Argentina/Buenos_Aires
 */

// Establecer zona horaria oficial en PHP
date_default_timezone_set('America/Argentina/Buenos_Aires');

// Parámetros de conexión WAMP local
define('DB_HOST', 'localhost');
define('DB_PORT', '3306');
define('DB_NAME', 'innovatronica');
define('DB_USER', 'root');
define('DB_PASS', '');

/**
 * Obtener la instancia singleton de conexión PDO.
 *
 * @throws PDOException si falla la conexión
 * @return PDO
 */
function db(): PDO {
    static $pdo = null;

    if ($pdo === null) {
        $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', DB_HOST, DB_PORT, DB_NAME);
        
        $opciones = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            // Sincronizar zona horaria de MySQL con la de Argentina (-03:00)
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4, time_zone = '-03:00'"
        ];

        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $opciones);
        } catch (PDOException $e) {
            // En desarrollo se puede loguear; en producción no exponer detalles sensibles
            error_log('Error de conexión a la base de datos: ' . $e->getMessage());
            die('Error crítico: No se pudo conectar a la base de datos de Innovatrónica S.A.');
        }
    }

    return $pdo;
}
