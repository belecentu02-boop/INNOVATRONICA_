-- ============================================================
-- Base de Datos: Innovatrónica S.A. - Sistema de Control de Stock
-- Archivo: database.sql (Fase 1 - instalación inicial)
-- Zona horaria: America/Argentina/Buenos_Aires (-03:00)
--
-- IMPORTANTE:
-- Este script es solo para una instalación inicial en una base vacía.
-- No lo ejecutes como mecanismo de actualización/migración.
-- No elimina tablas existentes; si ya existen, MySQL detendrá el script
-- al intentar crearlas. Para cambios sobre una instalación existente,
-- utiliza migraciones revisadas y una copia de seguridad previa.
-- ============================================================

CREATE DATABASE IF NOT EXISTS innovatronica 
  CHARACTER SET utf8mb4 
  COLLATE utf8mb4_unicode_ci;

USE innovatronica;

-- ------------------------------------------------------------
-- 1. Tabla de Usuarios (Soporte para 3 Roles y Bloqueo de Intentos)
-- ------------------------------------------------------------
CREATE TABLE usuarios (
  id_empleado INT AUTO_INCREMENT PRIMARY KEY,
  codigo_empleado VARCHAR(20) NOT NULL UNIQUE,
  nombre VARCHAR(100) NOT NULL,
  usuario VARCHAR(50) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  rol ENUM('VENDEDOR_INTERNO', 'SUPERVISOR_COMERCIAL', 'ADMIN') NOT NULL,
  activo TINYINT(1) NOT NULL DEFAULT 1,
  debe_cambiar_password TINYINT(1) NOT NULL DEFAULT 0,
  intentos_fallidos INT NOT NULL DEFAULT 0,
  bloqueado_hasta DATETIME NULL,
  creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  actualizado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 2. Tabla de Catálogo de Productos
-- ------------------------------------------------------------
CREATE TABLE productos (
  sku VARCHAR(30) PRIMARY KEY,
  nombre VARCHAR(120) NOT NULL,
  categoria VARCHAR(60) NOT NULL,
  ficha_tecnica VARCHAR(255) NULL,
  ubicacion_deposito VARCHAR(30) NOT NULL,
  creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  actualizado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 3. Tabla de Inventario Físico y Umbral de Seguridad
-- ------------------------------------------------------------
CREATE TABLE inventario (
  sku VARCHAR(30) PRIMARY KEY,
  stock_fisico INT NOT NULL DEFAULT 0,
  umbral_minimo INT NOT NULL DEFAULT 5,
  actualizado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (sku) REFERENCES productos(sku) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 4. Tabla de Reservas (7 Estados definidos en decisiones.md)
-- ------------------------------------------------------------
CREATE TABLE reservas (
  id_reserva VARCHAR(30) PRIMARY KEY,
  sku VARCHAR(30) NOT NULL,
  cantidad INT NOT NULL,
  id_empleado INT NOT NULL,
  creada_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  fecha_vencimiento DATETIME NOT NULL,
  estado ENUM(
    'ACTIVA',
    'PENDIENTE_AUTORIZACION',
    'CONFIRMADA',
    'FACTURADA',
    'EXPIRADA',
    'RECHAZADA',
    'LIBERADA'
  ) NOT NULL DEFAULT 'ACTIVA',
  motivo_rechazo VARCHAR(255) NULL,
  autorizado_por INT NULL,
  actualizado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (sku) REFERENCES productos(sku) ON DELETE RESTRICT ON UPDATE CASCADE,
  FOREIGN KEY (id_empleado) REFERENCES usuarios(id_empleado) ON DELETE RESTRICT ON UPDATE CASCADE,
  FOREIGN KEY (autorizado_por) REFERENCES usuarios(id_empleado) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 5. Tabla de Movimientos de Stock (Auditoría de Cambios en S_F)
-- ------------------------------------------------------------
CREATE TABLE movimientos_stock (
  id INT AUTO_INCREMENT PRIMARY KEY,
  sku VARCHAR(30) NOT NULL,
  tipo ENUM('FACTURACION', 'AJUSTE', 'INGRESO') NOT NULL,
  cantidad INT NOT NULL,
  stock_anterior INT NOT NULL,
  stock_nuevo INT NOT NULL,
  id_empleado INT NOT NULL,
  motivo VARCHAR(255) NOT NULL,
  fecha DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (sku) REFERENCES productos(sku) ON DELETE RESTRICT ON UPDATE CASCADE,
  FOREIGN KEY (id_empleado) REFERENCES usuarios(id_empleado) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 6. Tabla de Log de Acciones (Trazabilidad Global del Sistema)
-- ------------------------------------------------------------
CREATE TABLE log_acciones (
  id INT AUTO_INCREMENT PRIMARY KEY,
  id_empleado INT NULL,
  accion VARCHAR(80) NOT NULL,
  detalles TEXT NULL,
  ip VARCHAR(45) NULL,
  fecha DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (id_empleado) REFERENCES usuarios(id_empleado) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 7. Expiración automática de reservas con auditoría
-- Requiere: SET GLOBAL event_scheduler = ON;
-- Las expiraciones se registran individualmente en log_acciones.
-- ------------------------------------------------------------
DROP EVENT IF EXISTS expirar_reservas_vencidas;
DROP PROCEDURE IF EXISTS expirar_reservas_vencidas_procedure;

DELIMITER $
CREATE PROCEDURE expirar_reservas_vencidas_procedure()
BEGIN
  DECLARE fin_cursor BOOLEAN DEFAULT FALSE;
  DECLARE v_id_reserva VARCHAR(30);
  DECLARE v_estado_anterior VARCHAR(30);

  DECLARE cur_reservas CURSOR FOR
    SELECT id_reserva, estado
    FROM reservas
    WHERE estado IN ('ACTIVA', 'PENDIENTE_AUTORIZACION')
      AND fecha_vencimiento <= NOW();

  DECLARE CONTINUE HANDLER FOR NOT FOUND SET fin_cursor = TRUE;
  DECLARE EXIT HANDLER FOR SQLEXCEPTION
  BEGIN
    ROLLBACK;
    RESIGNAL;
  END;

  START TRANSACTION;
  OPEN cur_reservas;

  ciclo_expiracion: LOOP
    FETCH cur_reservas INTO v_id_reserva, v_estado_anterior;
    IF fin_cursor THEN
      LEAVE ciclo_expiracion;
    END IF;

    UPDATE reservas
    SET estado = 'EXPIRADA'
    WHERE id_reserva = v_id_reserva
      AND estado = v_estado_anterior
      AND estado IN ('ACTIVA', 'PENDIENTE_AUTORIZACION')
      AND fecha_vencimiento <= NOW();

    IF ROW_COUNT() = 1 THEN
      INSERT INTO log_acciones (id_empleado, accion, detalles, ip)
      VALUES (
        NULL,
        'RESERVA_EXPIRADA',
        CONCAT('La reserva ', v_id_reserva,
               ' venció automáticamente. Estado anterior: ', v_estado_anterior, '.'),
        '127.0.0.1'
      );
    END IF;
  END LOOP;

  CLOSE cur_reservas;
  COMMIT;
END$

CREATE EVENT expirar_reservas_vencidas
ON SCHEDULE EVERY 1 MINUTE
DO
  CALL expirar_reservas_vencidas_procedure()$
DELIMITER ;

-- ============================================================
-- DATOS SEMILLA / PRUEBA (Fase 1)
-- ============================================================

-- Cuentas iniciales de desarrollo. Las contraseñas conocidas deben cambiarse
-- en el primer inicio de sesión. No usar estas cuentas en producción.
INSERT INTO usuarios (id_empleado, codigo_empleado, nombre, usuario, password_hash, rol, activo, debe_cambiar_password) VALUES
(1, 'EMP-001', 'Administrador General', 'admin', '$2y$10$OrEyXs8vqq1sMB2FmgAFKOC//thOsT/5mEsfqh3XADACsWfVO.WcK', 'ADMIN', 1, 1),
(2, 'EMP-002', 'Supervisor Comercial Demo', 'supervisor', '$2y$10$OyRADfIhHPg0ksVTqt.x3eq8uo1EikLiDlQrA8u5MFstoUYqydg1e', 'SUPERVISOR_COMERCIAL', 1, 1),
(3, 'EMP-003', 'Vendedor Interno Demo', 'vendedor', '$2y$10$EpLSEGLHIurm0BfQtoWo9.9imkGCLBJOQ9vGtc8ClbpGXd.glz85u', 'VENDEDOR_INTERNO', 1, 1);

-- 18 Componentes Electrónicos con SKU Realista, Ubicación y Ficha Técnica
INSERT INTO productos (sku, nombre, categoria, ficha_tecnica, ubicacion_deposito) VALUES
('ESP32-CAM-01', 'Módulo ESP32-CAM con Cámara OV2640', 'Microcontroladores', 'WiFi + Bluetooth BLE, cámara 2MP, 4MB PSRAM, slot MicroSD', 'A1-01'),
('ESP32-DEV-01', 'Placa de Desarrollo ESP32 30 Pines', 'Microcontroladores', 'Dual Core 240MHz, WiFi, Bluetooth, micro USB CP2102', 'A1-02'),
('ARD-UNO-R3', 'Placa Arduino Uno R3 Compatible', 'Microcontroladores', 'ATmega328P, 16MHz, 14 E/S digitales, 6 analógicas', 'A1-03'),
('STM32-BLUEPILL', 'Módulo STM32F103C8T6 Blue Pill', 'Microcontroladores', 'ARM Cortex-M3 72MHz, 64KB Flash, 20KB SRAM', 'A1-04'),
('RASP-PI4-4GB', 'Microordenador Raspberry Pi 4 Model B 4GB', 'Sistemas Embebidos', 'Broadcom BCM2711 quad-core Cortex-A72 1.5GHz, Gigabit Ethernet', 'A2-01'),

('DHT22-AM2302', 'Sensor Digital de Temp. y Humedad DHT22', 'Sensores', 'Rango temp: -40°C a 80°C (±0.5°C), humedad: 0-100% RH', 'B1-01'),
('DS18B20-SONDA', 'Sensor de Temperatura Sumergible DS18B20', 'Sensores', 'Protocolo 1-Wire, cable 1m acero inoxidable, -55°C a +125°C', 'B1-02'),
('MPU6050-GY521', 'Sensor Acelerómetro y Giroscopio MPU-6050', 'Sensores', '6 Grados de Libertad (3 ejes acelerómetro + 3 ejes giro), I2C', 'B1-03'),
('BMP280-I2C', 'Sensor de Presión Barométrica y Temp. BMP280', 'Sensores', 'Alta precisión, barómetro altímetro 300-1100 hPa, I2C/SPI', 'B1-04'),
('HC-SR04-ULTRA', 'Sensor Ultrasónico de Distancia HC-SR04', 'Sensores', 'Rango 2cm - 400cm, precisión 3mm, alimentación 5V', 'B2-01'),

('REL-4CH-5V', 'Módulo de Relés 4 Canales Optoacoplado', 'Actuadores', 'Control 5V, conmutación 250VAC 10A / 30VDC 10A', 'C1-01'),
('STEP-A4988', 'Driver para Motor Paso a Paso A4988', 'Actuadores', 'Hasta 2A por bobina, micropasos 1/16, disipador térmico', 'C1-02'),
('SERVO-SG90', 'Micro Servomotor SG90 9g', 'Actuadores', 'Torque 1.8 kg-cm (4.8V), engranajes nylon, rotación 180°', 'C1-03'),

('OLED-096-I2C', 'Display OLED 0.96 Pulgadas Azul/Amarillo', 'Displays', 'Resolución 128x64 píxeles, controlador SSD1306, interfaz I2C', 'D1-01'),
('TFT-24-SPI', 'Pantalla TFT LCD 2.4 Pulgadas Táctil SPI', 'Displays', 'Resolución 240x320 ILI9341, lector tarjeta SD integrado', 'D1-02'),
('LCD-1602-I2C', 'Display LCD 16x2 con Módulo Mochila I2C', 'Displays', 'Fondo azul letras blancas, contraste ajustable, dirección 0x27', 'D1-03'),

('LM2596-STEPDOWN', 'Módulo Regulador Reductor Buck LM2596', 'Fuentes de Alimentación', 'Entrada 4-40V, salida 1.25-35V ajustable, corriente máx 3A', 'E1-01'),
('TP4056-LIPO', 'Cargador de Baterías Litio TP4056 USB-C', 'Fuentes de Alimentación', 'Carga 1A, protección sobredescarga y sobrecorriente', 'E1-02');

-- Inventario correspondiente:
-- • Quiebre Total (Stock Físico = 0): 'ESP32-CAM-01'
-- • Stock Crítico (S_F <= Umbral): 'DHT22-AM2302', 'ARD-UNO-R3', 'HC-SR04-ULTRA'
-- • Stock Normal / Abundante: resto de los componentes
INSERT INTO inventario (sku, stock_fisico, umbral_minimo) VALUES
('ESP32-CAM-01', 0, 5),       -- Quiebre Total (S_F = 0)
('ESP32-DEV-01', 45, 8),      -- Stock Normal
('ARD-UNO-R3', 4, 5),         -- Stock Crítico (4 <= 5)
('STM32-BLUEPILL', 18, 5),    -- Stock Normal
('RASP-PI4-4GB', 9, 3),       -- Stock Normal

('DHT22-AM2302', 3, 5),       -- Stock Crítico (3 <= 5)
('DS18B20-SONDA', 32, 10),    -- Stock Normal
('MPU6050-GY521', 25, 6),     -- Stock Normal
('BMP280-I2C', 14, 5),        -- Stock Normal
('HC-SR04-ULTRA', 2, 4),      -- Stock Crítico (2 <= 4)

('REL-4CH-5V', 12, 4),        -- Stock Normal
('STEP-A4988', 20, 5),        -- Stock Normal
('SERVO-SG90', 35, 10),       -- Stock Normal

('OLED-096-I2C', 16, 5),      -- Stock Normal
('TFT-24-SPI', 7, 3),         -- Stock Normal
('LCD-1602-I2C', 22, 6),      -- Stock Normal

('LM2596-STEPDOWN', 50, 10),  -- Stock Normal
('TP4056-LIPO', 60, 12);      -- Stock Normal

-- Registrar en log_acciones la inicialización
INSERT INTO log_acciones (id_empleado, accion, detalles, ip) VALUES
(1, 'INICIALIZACION_SISTEMA', 'Esquema de base de datos y datos iniciales creados para Fase 1', '127.0.0.1');
