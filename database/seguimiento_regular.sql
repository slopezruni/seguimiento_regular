-- Base de datos: seguimiento_regular
-- Importar este archivo desde phpMyAdmin
CREATE DATABASE IF NOT EXISTS seguimiento_regular
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE seguimiento_regular;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS seguimientos;
DROP TABLE IF EXISTS beneficiarios;
DROP TABLE IF EXISTS convocatorias;
SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE convocatorias (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(150) NOT NULL,
  periodo VARCHAR(30) NOT NULL,
  cupos INT UNSIGNED NOT NULL DEFAULT 0,
  estado ENUM('Activa','Cerrada') NOT NULL DEFAULT 'Activa',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE beneficiarios (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tipo_documento VARCHAR(10) NOT NULL,
  documento VARCHAR(30) NOT NULL UNIQUE,
  nombre VARCHAR(180) NOT NULL,
  ficha VARCHAR(40) DEFAULT NULL,
  programa VARCHAR(180) DEFAULT NULL,
  convocatoria_id INT UNSIGNED DEFAULT NULL,
  estado ENUM('Activo','Suspendido','Retirado','Finalizado') NOT NULL DEFAULT 'Activo',
  fecha_ingreso DATE DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_beneficiario_convocatoria FOREIGN KEY (convocatoria_id)
    REFERENCES convocatorias(id) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE seguimientos (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  beneficiario_id INT UNSIGNED NOT NULL,
  mes CHAR(7) NOT NULL,
  asistencia TINYINT(1) NOT NULL DEFAULT 0,
  entrega_documentos TINYINT(1) NOT NULL DEFAULT 0,
  fic ENUM('No encontrado','Vigente','No vigente','Revisar') NOT NULL DEFAULT 'No encontrado',
  novedad VARCHAR(255) DEFAULT NULL,
  resultado ENUM('Pendiente','Cumple','Alerta') NOT NULL DEFAULT 'Pendiente',
  observaciones TEXT DEFAULT NULL,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uk_beneficiario_mes (beneficiario_id, mes),
  CONSTRAINT fk_seguimiento_beneficiario FOREIGN KEY (beneficiario_id)
    REFERENCES beneficiarios(id) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB;

INSERT INTO convocatorias (nombre, periodo, cupos, estado)
VALUES ('Convocatoria 2 Regular', '2026', 100, 'Activa');

INSERT INTO beneficiarios
(tipo_documento, documento, nombre, ficha, programa, convocatoria_id, estado, fecha_ingreso)
VALUES
('CC','1001001001','Laura Martinez','2678901','Analisis y Desarrollo de Software',1,'Activo','2026-10-01'),
('TI','1001001002','Carlos Ramirez','2678902','Gestion Administrativa',1,'Activo','2026-10-01');
