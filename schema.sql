-- Ejecuta esto en phpMyAdmin (pestaña "SQL") con la base de datos
-- `athena_app` ya seleccionada, o crea la base de datos primero:
CREATE DATABASE IF NOT EXISTS athena_app CHARACTER SET utf8mb4;
USE athena_app;

CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  email VARCHAR(150) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
