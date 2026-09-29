-- Importar en la base combustibles_db desde phpMyAdmin.
-- Agrega el control RFID sin eliminar ni modificar las tablas existentes.

CREATE TABLE IF NOT EXISTS cisternas (
    id_cisterna INT NOT NULL AUTO_INCREMENT,
    codigo VARCHAR(30) NOT NULL,
    nombre VARCHAR(100) NOT NULL,
    placa VARCHAR(20) NOT NULL,
    capacidad_litros DECIMAL(12,2) DEFAULT NULL,
    activa TINYINT(1) NOT NULL DEFAULT 1,
    fecha_creacion TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id_cisterna),
    UNIQUE KEY uq_cisternas_codigo (codigo),
    UNIQUE KEY uq_cisternas_placa (placa)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS rfid_tarjetas (
    uid VARCHAR(64) NOT NULL,
    id_cisterna INT NOT NULL,
    activa TINYINT(1) NOT NULL DEFAULT 1,
    fecha_creacion TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (uid),
    KEY idx_rfid_tarjetas_cisterna (id_cisterna),
    CONSTRAINT fk_rfid_tarjetas_cisterna
        FOREIGN KEY (id_cisterna) REFERENCES cisternas (id_cisterna)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS ingresos_cisternas (
    id_ingreso BIGINT NOT NULL AUTO_INCREMENT,
    uid_tarjeta VARCHAR(64) NOT NULL,
    id_cisterna INT DEFAULT NULL,
    resultado ENUM('identificada', 'no_identificada') NOT NULL,
    registrada_por INT DEFAULT NULL,
    fecha_hora TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id_ingreso),
    KEY idx_ingresos_fecha (fecha_hora),
    KEY idx_ingresos_cisterna (id_cisterna),
    KEY idx_ingresos_uid (uid_tarjeta),
    CONSTRAINT fk_ingresos_cisterna
        FOREIGN KEY (id_cisterna) REFERENCES cisternas (id_cisterna)
        ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_ingresos_usuario
        FOREIGN KEY (registrada_por) REFERENCES usuarios (id_usuario)
        ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS rfid_configuracion (
    id_configuracion TINYINT NOT NULL,
    puerto_com VARCHAR(10) NOT NULL DEFAULT 'COM3',
    velocidad_baudios INT NOT NULL DEFAULT 9600,
    actualizado_por INT DEFAULT NULL,
    fecha_modificacion TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id_configuracion),
    CONSTRAINT fk_rfid_config_usuario
        FOREIGN KEY (actualizado_por) REFERENCES usuarios (id_usuario)
        ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT IGNORE INTO rfid_configuracion (id_configuracion, puerto_com, velocidad_baudios)
VALUES (1, 'COM3', 9600);