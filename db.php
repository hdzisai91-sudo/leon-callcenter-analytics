<?php
// Configuración por defecto de Laragon: usuario "root", sin contraseña.
// Si tu Laragon pide contraseña, cámbiala aquí.
$DB_HOST = "localhost";
$DB_NAME = "athena_app";
$DB_USER = "root";
$DB_PASS = "";

try {
    $pdo = new PDO(
        "mysql:host=$DB_HOST;dbname=$DB_NAME;charset=utf8mb4",
        $DB_USER,
        $DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (PDOException $e) {
    die("No se pudo conectar a la base de datos. Revisa que Laragon esté "
        . "corriendo (botón 'Start All') y que la base 'athena_app' exista. "
        . "Detalle técnico: " . $e->getMessage());
}
