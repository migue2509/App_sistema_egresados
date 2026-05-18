-- ============================================================
-- SISTEMA DE EGRESADOS - I.E. DINAMARCA
-- Schema de Base de Datos
-- ============================================================

CREATE DATABASE IF NOT EXISTS dinamarca_egresados 
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE dinamarca_egresados;

-- ------------------------------------------------------------
-- 1. Base oficial de egresados (cargada por el colegio)
-- ------------------------------------------------------------
CREATE TABLE egresados_base (
  id              INT AUTO_INCREMENT PRIMARY KEY,
  documento       VARCHAR(20)  NOT NULL UNIQUE,
  nombres         VARCHAR(120) NOT NULL,
  apellidos       VARCHAR(120) NOT NULL,
  fecha_nacimiento DATE         NOT NULL,
  anno_graduacion  YEAR         NOT NULL,
  registrado      TINYINT(1)   NOT NULL DEFAULT 0,
  fecha_carga     TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- 2. Usuarios del sistema
-- ------------------------------------------------------------
CREATE TABLE usuarios (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  documento   VARCHAR(20)  NOT NULL UNIQUE,
  email       VARCHAR(150) NOT NULL UNIQUE,
  password    VARCHAR(255) NOT NULL,
  rol         ENUM('egresado','comite','rectoria') NOT NULL DEFAULT 'egresado',
  estado      ENUM('pendiente','verificado','destacado','inactivo') NOT NULL DEFAULT 'pendiente',
  created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  last_login  TIMESTAMP NULL,
  token_reset VARCHAR(100) NULL,
  token_exp   TIMESTAMP NULL
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- 3. Perfiles personales
-- ------------------------------------------------------------
CREATE TABLE perfiles (
  id               INT AUTO_INCREMENT PRIMARY KEY,
  usuario_id       INT NOT NULL UNIQUE,
  foto             VARCHAR(255) DEFAULT NULL,
  tipo_documento   ENUM('CC','TI','CE','PA','NIT') DEFAULT 'CC',
  genero           ENUM('Masculino','Femenino','Otro','Prefiero no decir') DEFAULT NULL,
  telefono         VARCHAR(20)  DEFAULT NULL,
  ciudad           VARCHAR(100) DEFAULT NULL,
  pais_nacimiento  VARCHAR(100) DEFAULT 'Colombia',
  direccion        VARCHAR(250) DEFAULT NULL,
  logros           TEXT         DEFAULT NULL,
  updated_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- 4. Estudios posteriores al colegio
-- ------------------------------------------------------------
CREATE TABLE estudios (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  usuario_id   INT          NOT NULL,
  institucion  VARCHAR(200) NOT NULL,
  carrera      VARCHAR(200) NOT NULL,
  anno_inicio  YEAR         NOT NULL,
  anno_fin     YEAR         NULL,
  en_curso     TINYINT(1)   NOT NULL DEFAULT 0,
  titulo       VARCHAR(200) DEFAULT NULL,
  created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- 5. Información laboral
-- ------------------------------------------------------------
CREATE TABLE trabajos (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  usuario_id  INT          NOT NULL,
  empresa     VARCHAR(200) DEFAULT NULL,
  cargo       VARCHAR(200) DEFAULT NULL,
  area        VARCHAR(200) DEFAULT NULL,
  actualmente TINYINT(1)   NOT NULL DEFAULT 0,
  anno_inicio YEAR         DEFAULT NULL,
  anno_fin    YEAR         DEFAULT NULL,
  descripcion TEXT         DEFAULT NULL,
  created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- 6. Redes sociales
-- ------------------------------------------------------------
CREATE TABLE redes_sociales (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  usuario_id INT          NOT NULL,
  red        VARCHAR(50)  NOT NULL,
  url        VARCHAR(300) NOT NULL,
  FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- 7. Log de acciones administrativas
-- ------------------------------------------------------------
CREATE TABLE log_acciones (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  usuario_id  INT          NULL,
  accion      VARCHAR(200) NOT NULL,
  detalle     TEXT         DEFAULT NULL,
  ip          VARCHAR(45)  DEFAULT NULL,
  fecha       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Datos iniciales: usuarios administrativos
-- ------------------------------------------------------------
-- Usuarios administrativos de prueba.
-- Antes de usar en producción, reemplace estos hashes por contraseñas generadas localmente.
INSERT INTO usuarios (documento, email, password, rol, estado) VALUES
('1000000001', 'rectoria@dinamarca.edu.co', '$2y$12$u4ZQboCNZIxGPJIQZ6nxiuaT3KsTOHzVGrN7p.UgE1bqEwK4NHPJO', 'rectoria', 'verificado'),
('1000000002', 'comite@dinamarca.edu.co',   '$2y$12$u4ZQboCNZIxGPJIQZ6nxiuaT3KsTOHzVGrN7p.UgE1bqEwK4NHPJO', 'comite',   'verificado');

INSERT INTO perfiles (usuario_id, tipo_documento) VALUES (1, 'CC'), (2, 'CC');

-- ------------------------------------------------------------
-- Datos de prueba: base oficial de egresados
-- ------------------------------------------------------------
INSERT INTO egresados_base (documento, nombres, apellidos, fecha_nacimiento, anno_graduacion) VALUES
('1001234567', 'Juan Camilo',   'García Ríos',     '2000-03-15', 2018),
('1009876543', 'María Alejandra','López Herrera',   '2001-07-22', 2019),
('1005551234', 'Carlos Andrés', 'Martínez Gómez',  '1999-11-08', 2017),
('1007894561', 'Laura Valentina','Pérez Sánchez',  '2002-01-30', 2020),
('1003216549', 'Santiago',      'Rodríguez Castro','2003-05-12', 2021);
