-- ============================================================
-- Innovatrónica S.A. - Sistema de Control de Stock
-- Migración Fase 3.1: Cambio de Contraseña Obligatorio
-- Idempotente: puede ejecutarse si la columna ya existe.
-- IMPORTANTE: realizar backup antes de aplicar en producción.
-- ============================================================

USE innovatronica;

SET @columna_existe := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'usuarios'
      AND COLUMN_NAME = 'debe_cambiar_password'
);

SET @sql_migracion := IF(
    @columna_existe = 0,
    'ALTER TABLE usuarios ADD COLUMN debe_cambiar_password TINYINT(1) NOT NULL DEFAULT 0 AFTER activo',
    'SELECT ''La columna debe_cambiar_password ya existe; no se modifica el esquema.'' AS resultado'
);

PREPARE stmt_migracion FROM @sql_migracion;
EXECUTE stmt_migracion;
DEALLOCATE PREPARE stmt_migracion;

-- Exigir cambio a cuentas que aún conservan los hashes de las credenciales
-- conocidas incluidas originalmente en los datos semilla. Tras cambiar la clave,
-- el hash cambia y una ejecución posterior no vuelve a marcar la cuenta.
UPDATE usuarios
SET debe_cambiar_password = 1
WHERE (usuario = 'admin' AND password_hash = '$2y$10$OrEyXs8vqq1sMB2FmgAFKOC//thOsT/5mEsfqh3XADACsWfVO.WcK')
   OR (usuario = 'supervisor' AND password_hash = '$2y$10$OyRADfIhHPg0ksVTqt.x3eq8uo1EikLiDlQrA8u5MFstoUYqydg1e')
   OR (usuario = 'vendedor' AND password_hash = '$2y$10$EpLSEGLHIurm0B2FtoWo9.9imkGCLBJOQ9vGtc8ClbpGXd.glz85u');

-- Registrar la migración una sola vez, cuando realmente se agregó la columna.
INSERT INTO log_acciones (id_empleado, accion, detalles, ip)
SELECT 1, 'MIGRACION_ESQUEMA',
       'Aplicada migración Fase 3.1: columna debe_cambiar_password en usuarios',
       '127.0.0.1'
WHERE @columna_existe = 0;
