-- ============================================================
--  ESTELAR · Prospección por voz — esquema MySQL 8 / MariaDB 10.6+
--  Se puede ejecutar varias veces: no borra datos.
--  Importa este archivo en la base ya creada (cPanel → phpMyAdmin)
--  o ejecuta:  php install/install.php
-- ============================================================

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS usuarios (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(100) NOT NULL,
  email VARCHAR(150) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  rol ENUM('admin','asesor') NOT NULL DEFAULT 'asesor',
  activo TINYINT(1) NOT NULL DEFAULT 1,
  creado_en DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rubros (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(100) NOT NULL,
  nombre_normalizado VARCHAR(100) NOT NULL UNIQUE,
  creado_en DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS contactos (
  id INT AUTO_INCREMENT PRIMARY KEY,
  usuario_id INT NOT NULL,
  pais CHAR(2) NOT NULL,
  telefono_e164 VARCHAR(16) NOT NULL,
  telefono_local VARCHAR(12) NOT NULL,
  estado VARCHAR(40) NOT NULL,
  rubro_id INT NULL,
  nota VARCHAR(255) NULL,
  texto_dictado TEXT NULL,
  creado_en DATETIME DEFAULT CURRENT_TIMESTAMP,
  actualizado_en DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  -- Periodo AAAA-MM. Se usa YEAR/MONTH/LPAD (en vez de DATE_FORMAT) porque MySQL y MariaDB
  -- los aceptan por igual en columnas generadas.
  periodo CHAR(7) AS (CONCAT(YEAR(creado_en), '-', LPAD(MONTH(creado_en), 2, '0'))) STORED,
  UNIQUE KEY uq_telefono (telefono_e164),
  INDEX idx_periodo_estado (periodo, estado),
  INDEX idx_periodo_rubro (periodo, rubro_id),
  INDEX idx_pais_periodo (pais, periodo),
  INDEX idx_usuario_periodo (usuario_id, periodo),
  INDEX idx_creado (creado_en),
  CONSTRAINT chk_pais CHECK (pais IN ('PE','CO')),
  CONSTRAINT fk_contactos_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id),
  CONSTRAINT fk_contactos_rubro FOREIGN KEY (rubro_id) REFERENCES rubros(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS contactos_historial (
  id INT AUTO_INCREMENT PRIMARY KEY,
  contacto_id INT NOT NULL,
  estado_anterior VARCHAR(20) NULL,
  estado_nuevo VARCHAR(20) NOT NULL,
  rubro_anterior_id INT NULL,
  rubro_nuevo_id INT NULL,
  usuario_id INT NOT NULL,
  creado_en DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_historial_contacto (contacto_id, creado_en),
  CONSTRAINT fk_historial_contacto FOREIGN KEY (contacto_id) REFERENCES contactos(id),
  CONSTRAINT fk_historial_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Control de intentos de login (bloqueo temporal tras varios fallos).
CREATE TABLE IF NOT EXISTS login_intentos (
  clave CHAR(64) NOT NULL PRIMARY KEY,
  fallos INT NOT NULL DEFAULT 0,
  bloqueado_hasta DATETIME NULL,
  actualizado_en DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Estados de contacto
CREATE TABLE IF NOT EXISTS estados (
  id INT AUTO_INCREMENT PRIMARY KEY,
  clave VARCHAR(40) NOT NULL UNIQUE,
  nombre VARCHAR(60) NOT NULL,
  palabras VARCHAR(255) NULL,
  color CHAR(7) NOT NULL DEFAULT '#64748b',
  cuenta_conversion TINYINT(1) NOT NULL DEFAULT 0,
  orden INT NOT NULL DEFAULT 100,
  activo TINYINT(1) NOT NULL DEFAULT 1,
  creado_en DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO estados (clave, nombre, palabras, color, cuenta_conversion, orden) VALUES
  ('interesado',           'Interesado',           NULL,                                   '#16a34a', 1, 1),
  ('reunion',              'Reunión',              NULL,                                   '#0284c7', 1, 2),
  ('medio_interesado',     'Medio interesado',     NULL,                                   '#d97706', 0, 3),
  ('no_interesado',        'No interesado',        NULL,                                   '#e11d48', 0, 4),
  ('sin_respuesta',        'Sin respuesta',        NULL,                                   '#64748b', 0, 10),
  ('no_quiere',            'No quiere',            NULL,                                   '#be123c', 0, 11),
  ('no_contesta',          'No contesta',          'no contesto, no responde',             '#94a3b8', 0, 12),
  ('en_duda',              'En duda',              'duda, dudoso',                         '#ca8a04', 0, 13),
  ('apagado',              'Apagado',              NULL,                                   '#475569', 0, 14),
  ('otra_fecha',           'Otra fecha',           'otro dia',                             '#7c3aed', 0, 15),
  ('volver_a_llamar',      'Volver a llamar',      'llamar despues, llamar luego',         '#0891b2', 0, 16),
  ('pago_pendiente',       'Pago pendiente',       'falta pagar',                          '#ea580c', 0, 17),
  ('por_confirmar',        'Por confirmar',        NULL,                                   '#4f46e5', 0, 18),
  ('potencial',            'Potencial',            'posible cliente',                      '#65a30d', 0, 19),
  ('reunion_pendiente',    'Reunión pendiente',    NULL,                                   '#0369a1', 1, 20),
  ('escribir_al_whatsapp', 'Escribir al WhatsApp', 'escribir por whatsapp, mandar whatsapp', '#15803d', 0, 21),
  ('seguimiento',          'Seguimiento',          'hacer seguimiento',                    '#0d9488', 0, 22);

-- Rubros iniciales
INSERT IGNORE INTO rubros (nombre, nombre_normalizado) VALUES
  ('Educación', 'educacion'),
  ('Salud', 'salud'),
  ('Construcción', 'construccion'),
  ('Comercio', 'comercio'),
  ('Restaurantes', 'restaurantes'),
  ('Transporte', 'transporte'),
  ('Inmobiliaria', 'inmobiliaria'),
  ('Tecnología', 'tecnologia'),
  ('Servicios', 'servicios'),
  ('Otros', 'otros');

-- Tokens de ingreso desde Estelar ya usados (cada token sirve una sola vez).
CREATE TABLE IF NOT EXISTS sso_usados (
  jti CHAR(32) NOT NULL PRIMARY KEY,
  expira DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
