-- ============================================================
-- Innovatrónica S.A. - Sistema de Control de Stock
-- Migración Fase 3.1: Cambio de Contraseña Obligatorio
-- Fuente: docs/decisiones.md (Sección 16)
-- ============================================================

USE innovatronica;

-- 1. Agregar columna debe_cambiar_password con valor por defecto 0
ALTER TABLE usuarios 
ADD COLUMN debe_cambiar_password TINYINT(1) NOT NULL DEFAULT 0 
AFTER activo;

-- 2. Garantizar que los usuarios semilla de desarrollo queden en 0
UPDATE usuarios 
SET debe_cambiar_password = 0 
WHERE usuario IN ('admin', 'supervisor', 'vendedor');

-- 3. Registrar aplicación de migración en log de auditoría
INSERT INTO log_acciones (id_empleado, accion, detalles, ip) 
VALUES (1, 'MIGRACION_ESQUEMA', 'Aplicada migración Fase 3.1: columna debe_cambiar_password en usuarios', '127.0.0.1');
