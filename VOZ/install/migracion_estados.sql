-- ============================================================
--  ESTELAR · Migración: estados personalizables
--  Ejecuta esto UNA vez en phpMyAdmin si ya tenías el sistema instalado.
--  No borra contactos: solo amplía la columna estado y crea la tabla estados.
-- ============================================================

SET NAMES utf8mb4;

ALTER TABLE contactos MODIFY estado VARCHAR(40) NOT NULL;

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
