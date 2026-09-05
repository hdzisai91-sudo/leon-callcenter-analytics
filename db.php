<?php
// Configuración de conexión a la Base de Datos en Laragon
$DB_HOST = "localhost";
$DB_NAME = "athena_db";
$DB_USER = "root";
$DB_PASS = "";

try {
    $pdo = new PDO(
        "mysql:host=$DB_HOST;dbname=$DB_NAME;charset=utf8mb4",
        $DB_USER,
        $DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );
} catch (PDOException $e) {
    die("No se pudo conectar a la base de datos. Revisa que MySQL esté corriendo en Laragon. Detalle: " . $e->getMessage());
}