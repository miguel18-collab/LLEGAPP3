-- ============================================================================
-- LLEGAPP 3 · Mejoras para superar apps similares
-- Honestidad con el dato, reportes de problemas, viajes activos
-- ============================================================================

-- Tabla: fuentes de datos por ruta (honestidad: en vivo vs horario)
CREATE TABLE IF NOT EXISTS `data_sources` (
    `route_id`   VARCHAR(10)  NOT NULL PRIMARY KEY,
    `source_type` ENUM('en_vivo','horario_programado','simulado') NOT NULL DEFAULT 'simulado',
    `source_name` VARCHAR(120) NULL COMMENT 'Nombre de la fuente oficial (TransMilenio, Metro, etc.)',
    `last_sync`  DATETIME NULL COMMENT 'Última sincronización con fuente real',
    `notes`      VARCHAR(255) NULL,
    FOREIGN KEY (`route_id`) REFERENCES `routes`(`route_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla: reportes de problemas de usuarios (bus fantasma, ruta incorrecta, etc.)
CREATE TABLE IF NOT EXISTS `reportes_problema` (
    `id`          BIGINT AUTO_INCREMENT PRIMARY KEY,
    `user_id`     CHAR(36) NULL,
    `bus_id`      VARCHAR(50) NULL,
    `route_id`    VARCHAR(10) NULL,
    `stop_id`     INT NULL,
    `tipo`        ENUM('bus_fantasma','ruta_incorrecta','tiempo_inaccurato','bus_detenido','otro') NOT NULL DEFAULT 'otro',
    `descripcion` VARCHAR(300) NULL,
    `lat`         DECIMAL(10,6) NULL,
    `lng`         DECIMAL(10,6) NULL,
    `resuelto`    TINYINT(1) NOT NULL DEFAULT 0,
    `created_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_reportes_fecha` (`created_at`),
    INDEX `idx_reportes_resuelto` (`resuelto`),
    CONSTRAINT `fk_reporte_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_reporte_route` FOREIGN KEY (`route_id`) REFERENCES `routes`(`route_id`) ON DELETE SET NULL,
    CONSTRAINT `fk_reporte_stop` FOREIGN KEY (`stop_id`) REFERENCES `stops`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla: viajes activos de usuarios (modo viaje en segundo plano + alerta de desembarque)
CREATE TABLE IF NOT EXISTS `viajes_activos` (
    `id`          BIGINT AUTO_INCREMENT PRIMARY KEY,
    `user_id`     CHAR(36) NOT NULL,
    `route_id`    VARCHAR(10) NOT NULL,
    `stop_origen_id` INT NULL,
    `stop_destino_id` INT NULL,
    `bus_id`      VARCHAR(50) NULL COMMENT 'Bus que el usuario está siguiendo',
    `estado`      ENUM('esperando','en_camino','cerca_destino','completado','cancelado') NOT NULL DEFAULT 'esperando',
    `alerta_enviada` TINYINT(1) NOT NULL DEFAULT 0,
    `iniciado_en` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `finalizado_en` DATETIME NULL,
    INDEX `idx_viajes_usuario` (`user_id`, `estado`),
    INDEX `idx_viajes_bus` (`bus_id`),
    CONSTRAINT `fk_viaje_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_viaje_route` FOREIGN KEY (`route_id`) REFERENCES `routes`(`route_id`) ON DELETE CASCADE,
    CONSTRAINT `fk_viaje_stop_origen` FOREIGN KEY (`stop_origen_id`) REFERENCES `stops`(`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_viaje_stop_destino` FOREIGN KEY (`stop_destino_id`) REFERENCES `stops`(`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_viaje_bus` FOREIGN KEY (`bus_id`) REFERENCES `buses`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insertar fuentes de datos iniciales (simulado por defecto, honesto)
INSERT IGNORE INTO `data_sources` (`route_id`, `source_type`, `source_name`, `notes`) VALUES
('M101', 'simulado', 'LLEGAPP 3 (simulación)', 'Datos de demostración sin fuente oficial'),
('M234', 'simulado', 'LLEGAPP 3 (simulación)', 'Datos de demostración sin fuente oficial'),
('M145', 'simulado', 'LLEGAPP 3 (simulación)', 'Datos de demostración sin fuente oficial'),
('M301', 'simulado', 'LLEGAPP 3 (simulación)', 'Datos de demostración sin fuente oficial'),
('M420', 'simulado', 'LLEGAPP 3 (simulación)', 'Datos de demostración sin fuente oficial'),
('M505', 'simulado', 'LLEGAPP 3 (simulación)', 'Datos de demostración sin fuente oficial');
