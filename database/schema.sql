-- ============================================================================
-- LLEGAPP 3 · Esquema de base de datos (MySQL / MariaDB)
-- Sistema profesional de rastreo de transporte urbano en tiempo real
-- ============================================================================

CREATE DATABASE IF NOT EXISTS `llegapp3`
    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `llegapp3`;

SET FOREIGN_KEY_CHECKS = 0;

-- ----------------------------------------------------------------------------
-- settings · Configuración clave-valor del sistema
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `settings` (
    `clave`  VARCHAR(60)  NOT NULL PRIMARY KEY,
    `valor`  TEXT         NULL,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- users · Usuarios del sistema (admin | usuario | conductor)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `users` (
    `id`             CHAR(36)     NOT NULL PRIMARY KEY,
    `name`           VARCHAR(100) NOT NULL,
    `email`          VARCHAR(120) NOT NULL UNIQUE,
    `password_hash`  VARCHAR(255) NOT NULL,
    `role`           ENUM('admin','usuario','conductor') NOT NULL DEFAULT 'usuario',
    `phone`          VARCHAR(30)  NULL,
    `avatar_color`   VARCHAR(7)   NULL,
    `email_verified` TINYINT(1)   NOT NULL DEFAULT 0,
    `is_active`      TINYINT(1)   NOT NULL DEFAULT 1,
    `last_login`     DATETIME     NULL,
    `created_at`     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_users_role` (`role`),
    INDEX `idx_users_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- user_preferences · Preferencias de experiencia por usuario
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `user_preferences` (
    `user_id`                CHAR(36) NOT NULL PRIMARY KEY,
    `theme`                  ENUM('light','dark','auto') NOT NULL DEFAULT 'auto',
    `notifications_enabled`  TINYINT(1) NOT NULL DEFAULT 1,
    `notify_stops_ahead`     TINYINT UNSIGNED NOT NULL DEFAULT 2,
    `default_map_zoom`       TINYINT UNSIGNED NOT NULL DEFAULT 13,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- routes · Rutas de transporte
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `routes` (
    `route_id`   VARCHAR(10)  NOT NULL PRIMARY KEY,
    `name`       VARCHAR(120) NOT NULL,
    `short_name` VARCHAR(20)  NULL,
    `color`      VARCHAR(7)   NOT NULL DEFAULT '#00A651',
    `emoji`      VARCHAR(8)   NOT NULL DEFAULT '🚌',
    `description` VARCHAR(255) NULL,
    `frequency_min` SMALLINT UNSIGNED NOT NULL DEFAULT 10,
    `first_departure` TIME NOT NULL DEFAULT '05:00:00',
    `last_departure`  TIME NOT NULL DEFAULT '22:30:00',
    `active`     TINYINT(1)   NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- stops · Paradas de cada ruta (orden secuencial ida)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `stops` (
    `id`            INT AUTO_INCREMENT PRIMARY KEY,
    `route_id`      VARCHAR(10)  NOT NULL,
    `name`          VARCHAR(120) NOT NULL,
    `lat`           DECIMAL(10,6) NOT NULL,
    `lng`           DECIMAL(10,6) NOT NULL,
    `stop_order`    SMALLINT UNSIGNED NOT NULL,
    `is_terminal`   TINYINT(1) NOT NULL DEFAULT 0,
    `zone`          VARCHAR(50) NULL,
    UNIQUE KEY `uq_route_order` (`route_id`, `stop_order`),
    INDEX `idx_route` (`route_id`),
    CONSTRAINT `fk_stops_route` FOREIGN KEY (`route_id`) REFERENCES `routes`(`route_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- buses · Flota simulada en tiempo real
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `buses` (
    `id`          VARCHAR(50)  NOT NULL PRIMARY KEY,
    `code`        VARCHAR(20)  NOT NULL,
    `plate`       VARCHAR(12)  NULL,
    `route_id`    VARCHAR(10)  NOT NULL,
    `stop_index`  SMALLINT UNSIGNED NOT NULL DEFAULT 1 COMMENT 'Parada destino actual (1-based)',
    `progress`    DECIMAL(5,4) NOT NULL DEFAULT 0 COMMENT 'Avance 0..1 entre paradas',
    `speed_kmh`   SMALLINT UNSIGNED NOT NULL DEFAULT 28,
    `direction`   TINYINT NOT NULL DEFAULT 1 COMMENT '1=ida, -1=vuelta',
    `passengers`  SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    `capacity`    SMALLINT UNSIGNED NOT NULL DEFAULT 60,
    `status`      ENUM('operando','detenido','mantenimiento','fuera_servicio') NOT NULL DEFAULT 'operando',
    `last_update` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `created_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_bus_code` (`code`),
    INDEX `idx_buses_route` (`route_id`),
    INDEX `idx_buses_status` (`status`),
    CONSTRAINT `fk_buses_route` FOREIGN KEY (`route_id`) REFERENCES `routes`(`route_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- favorites · Favoritos del usuario (rutas o paradas)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `favorites` (
    `id`        BIGINT AUTO_INCREMENT PRIMARY KEY,
    `user_id`   CHAR(36) NOT NULL,
    `item_type` ENUM('ruta','parada') NOT NULL,
    `item_key`  VARCHAR(20) NOT NULL COMMENT 'route_id o stops.id',
    `label`     VARCHAR(150) NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_fav` (`user_id`, `item_type`, `item_key`),
    CONSTRAINT `fk_fav_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- trip_history · Registro de actividad de viajes
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `trip_history` (
    `id`         BIGINT AUTO_INCREMENT PRIMARY KEY,
    `user_id`    CHAR(36) NOT NULL,
    `action`     ENUM('viewed','tracked','planned','alert_created') NOT NULL DEFAULT 'viewed',
    `route_id`   VARCHAR(10) NULL,
    `origin_stop`  VARCHAR(120) NULL,
    `dest_stop`    VARCHAR(120) NULL,
    `duration_min` SMALLINT UNSIGNED NULL,
    `meta`       JSON NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_hist_user_date` (`user_id`, `created_at`),
    INDEX `idx_hist_route` (`route_id`),
    CONSTRAINT `fk_hist_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- user_alerts · Alertas de proximidad configuradas por el usuario
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `user_alerts` (
    `id`          BIGINT AUTO_INCREMENT PRIMARY KEY,
    `user_id`     CHAR(36) NOT NULL,
    `route_id`    VARCHAR(10) NOT NULL,
    `stop_id`     INT NOT NULL,
    `stops_ahead` TINYINT UNSIGNED NOT NULL DEFAULT 2 COMMENT 'Avisar cuando falten ~N paradas',
    `active`      TINYINT(1) NOT NULL DEFAULT 1,
    `created_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_user_stop` (`user_id`, `stop_id`),
    INDEX `idx_alert_active` (`active`),
    CONSTRAINT `fk_alert_user`  FOREIGN KEY (`user_id`)  REFERENCES `users`(`id`)  ON DELETE CASCADE,
    CONSTRAINT `fk_alert_stop`  FOREIGN KEY (`stop_id`)  REFERENCES `stops`(`id`)  ON DELETE CASCADE,
    CONSTRAINT `fk_alert_route` FOREIGN KEY (`route_id`) REFERENCES `routes`(`route_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- notifications · Bandeja de notificaciones in-app
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `notifications` (
    `id`         BIGINT AUTO_INCREMENT PRIMARY KEY,
    `user_id`    CHAR(36) NOT NULL,
    `type`       ENUM('bus_cerca','sistema','incidencia','plan') NOT NULL DEFAULT 'sistema',
    `title`      VARCHAR(150) NOT NULL,
    `message`    VARCHAR(300) NOT NULL,
    `icon`       VARCHAR(8) NOT NULL DEFAULT '🔔',
    `link_url`   VARCHAR(255) NULL,
    `meta`       JSON NULL,
    `is_read`    TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_notif_unread` (`user_id`, `is_read`, `created_at`),
    CONSTRAINT `fk_notif_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- system_alerts · Incidencias publicadas por administración
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `system_alerts` (
    `id`         INT AUTO_INCREMENT PRIMARY KEY,
    `route_id`   VARCHAR(10) NULL,
    `severity`   ENUM('info','media','alta','critica') NOT NULL DEFAULT 'info',
    `title`      VARCHAR(150) NOT NULL,
    `message`    VARCHAR(400) NOT NULL,
    `active`     TINYINT(1) NOT NULL DEFAULT 1,
    `starts_at`  DATETIME NULL,
    `expires_at` DATETIME NULL,
    `created_by` CHAR(36) NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_sysalert_active` (`active`, `severity`),
    CONSTRAINT `fk_sysalert_route` FOREIGN KEY (`route_id`) REFERENCES `routes`(`route_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- ----------------------------------------------------------------------------
-- Vistas útiles
-- ----------------------------------------------------------------------------
CREATE OR REPLACE VIEW `v_rutas_resumen` AS
SELECT r.route_id, r.name, r.color, r.emoji, r.active, r.frequency_min,
       COUNT(s.id) AS total_paradas
FROM routes r LEFT JOIN stops s ON s.route_id = r.route_id
GROUP BY r.route_id, r.name, r.color, r.emoji, r.active, r.frequency_min;

CREATE OR REPLACE VIEW `v_flota_estado` AS
SELECT b.id, b.code, b.plate, b.route_id, r.name AS route_name, r.color,
       b.stop_index, b.progress, b.speed_kmh, b.direction, b.passengers, b.capacity,
       b.status, b.last_update
FROM buses b JOIN routes r ON r.route_id = b.route_id;
