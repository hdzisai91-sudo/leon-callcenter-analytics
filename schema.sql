-- Creación de la base de datos
CREATE DATABASE IF NOT EXISTS `athena_db` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `athena_db`;

-- 1. TABLA DE USUARIOS (ANALISTAS)
CREATE TABLE IF NOT EXISTS `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `email` VARCHAR(150) NOT NULL UNIQUE,
  `password_hash` VARCHAR(255) NOT NULL,
  `role` VARCHAR(50) DEFAULT 'Analista',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- 2. TABLA DE LLAMADAS DEL CALL CENTER
CREATE TABLE IF NOT EXISTS `call_records` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `call_date` DATE NOT NULL,
  `call_time` TIME NOT NULL,
  `duration_seconds` INT DEFAULT 180,
  `state_name` VARCHAR(100) NOT NULL,
  `contact_reason` VARCHAR(100) NOT NULL,
  `day_slot` ENUM('Madrugada', 'Mañana', 'Tarde', 'Noche') NOT NULL,
  `is_fraud_report` TINYINT(1) DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- 3. TABLA DE REPORTES DE FRAUDE
CREATE TABLE IF NOT EXISTS `fraud_reports` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `report_code` VARCHAR(50) NOT NULL UNIQUE,
  `incident_date` DATETIME NOT NULL,
  `fraud_type` VARCHAR(100) NOT NULL,
  `attack_channel` VARCHAR(100) NOT NULL,
  `amount_affected` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `amount_recovered` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `state_name` VARCHAR(100) NOT NULL,
  `victim_age` INT NOT NULL,
  `victim_gender` ENUM('Masculino', 'Femenino', 'Otro') NOT NULL,
  `status` ENUM('En Investigación', 'Crítico', 'Resuelto') NOT NULL DEFAULT 'En Investigación',
  `description` TEXT,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ========================================================
-- DATOS DE EJEMPLO REALISTAS PARA EL SISTEMA
-- ========================================================

-- Insertar reportes de fraude de muestra
INSERT INTO `fraud_reports` 
(`report_code`, `incident_date`, `fraud_type`, `attack_channel`, `amount_affected`, `amount_recovered`, `state_name`, `victim_age`, `victim_gender`, `status`, `description`) 
VALUES
('#FR-2026-089', '2026-09-02 18:42:00', 'Phishing Bancario', 'WhatsApp / SMS', 45000.00, 15000.00, 'Ciudad de México', 48, 'Masculino', 'Crítico', 'Suplantación de falso ejecutivo bancario solicitando OTP.'),
('#FR-2026-088', '2026-09-02 17:15:00', 'Clonación de Tarjeta', 'Cajero Automático', 12800.00, 12800.00, 'Estado de México', 34, 'Femenino', 'En Investigación', 'Cargos no reconocidos en gasolinera y tienda de conveniencia.'),
('#FR-2026-087', '2026-09-02 15:30:00', 'Suplantación de Identidad', 'Llamada Celular', 89500.00, 0.00, 'Jalisco', 59, 'Masculino', 'Crítico', 'Apertura de crédito no solicitado con documentos falsos.'),
('#FR-2026-086', '2026-09-02 13:10:00', 'Transferencia No Reconocida', 'Sitio Web Falso', 6400.00, 6400.00, 'Nuevo León', 27, 'Femenino', 'Resuelto', 'SPEI no autorizado, fondos congelados a tiempo.'),
('#FR-2026-085', '2026-09-02 11:22:00', 'Extorsión Telefónica', 'Llamada Celular', 22000.00, 0.00, 'Puebla', 67, 'Femenino', 'En Investigación', 'Falso premio de sorteo con depósito en tienda.'),
('#FR-2026-084', '2026-09-01 16:40:00', 'Phishing Bancario', 'Correo Falso', 31500.00, 10000.00, 'Ciudad de México', 42, 'Masculino', 'Resuelto', 'Correo falso con link simulando portal de banco.');

-- Insertar llamadas de muestra
INSERT INTO `call_records` 
(`call_date`, `call_time`, `duration_seconds`, `state_name`, `contact_reason`, `day_slot`, `is_fraud_report`) 
VALUES
('2026-09-02', '11:15:00', 240, 'Ciudad de México', 'Reporte de fraude', 'Mañana', 1),
('2026-09-02', '11:45:00', 180, 'Estado de México', 'Consulta de saldo', 'Mañana', 0),
('2026-09-02', '12:30:00', 320, 'Jalisco', 'Aclaración de movimientos', 'Tarde', 0),
('2026-09-02', '13:10:00', 410, 'Nuevo León', 'Reporte de fraude', 'Tarde', 1),
('2026-09-02', '17:15:00', 190, 'Puebla', 'Reporte de fraude', 'Tarde', 1),
('2026-09-02', '19:20:00', 150, 'Ciudad de México', 'Bloqueo de tarjeta', 'Noche', 0),
('2026-09-02', '23:10:00', 120, 'Guanajuato', 'Consulta de saldo', 'Noche', 0),
('2026-09-02', '03:40:00', 95, 'Querétaro', 'Consulta de saldo', 'Madrugada', 0);