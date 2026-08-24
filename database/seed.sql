-- ============================================================================
-- LLEGAPP 3 · Datos de demostración (Medellín, Colombia)
-- ============================================================================

-- ----------------------------------------------------------------------------
-- Rutas
-- ----------------------------------------------------------------------------
INSERT IGNORE INTO `routes` (`route_id`, `name`, `short_name`, `color`, `emoji`, `description`, `frequency_min`) VALUES
('M101', 'Terminal Norte ↔ San Javier', 'M101', '#00A651', '🚌', 'Corredor troncal norte-occidente. Conecta el Terminal de Transporte con la comuna 13.', 8),
('M234', 'Centro ↔ Universidades',       'M234', '#EF4444', '🚍', 'Servicio universitario que une el centro con los campus de UdeA, UPB y el Estadio.', 10),
('M145', 'Circular Zona Comercial',      'M145', '#F59E0B', '🚌', 'Circuito comercial: El Hueco, Mayorca, Oviedo y Santa Fe.', 12),
('M301', 'El Poblado ↔ Envigado',        'M301', '#3B82F6', '🚌', 'Corredor sur por la Avenida Las Vegas hasta Envigado.', 9),
('M420', 'Laureles ↔ Belén',             'M420', '#8B5CF6', '🚌', 'Conecta Laureles con Belén por la Circular 74 y la Avenida 80.', 11),
('M505', 'Centro ↔ Manrique',            'M505', '#14B8A6', '🚌', 'Ruta oriental hacia Manrique, Berlín y La Salle.', 13);

-- ----------------------------------------------------------------------------
-- Paradas · M101 Terminal Norte ↔ San Javier
-- ----------------------------------------------------------------------------
INSERT INTO `stops` (`route_id`,`name`,`lat`,`lng`,`stop_order`,`is_terminal`,`zone`) VALUES
('M101','Terminal del Norte',6.257600,-75.589300,1,1,'Centro'),
('M101','Parque de Bolívar',6.251800,-75.567800,2,0,'Centro'),
('M101','Alpujarra',6.244200,-75.573500,3,0,'Centro'),
('M101','San Antonio',6.247400,-75.568100,4,0,'Centro'),
('M101','Suramericana',6.250700,-75.581900,5,0,'La Candelaria'),
('M101','Estadio',6.255100,-75.582600,6,0,'Estadio'),
('M101','Golfito',6.256900,-75.592400,7,0,'Robledo'),
('M101','Santa Lucía',6.262100,-75.601000,8,0,'San Javier'),
('M101','San Javier (Parque)',6.266800,-75.608700,9,1,'San Javier');

-- ----------------------------------------------------------------------------
-- Paradas · M234 Centro ↔ Universidades
-- ----------------------------------------------------------------------------
INSERT INTO `stops` (`route_id`,`name`,`lat`,`lng`,`stop_order`,`is_terminal`,`zone`) VALUES
('M234','Parque Berrío',6.252200,-75.566700,1,1,'Centro'),
('M234','Junín',6.250100,-75.564800,2,0,'Centro'),
('M234','La Playa',6.249300,-75.563100,3,0,'Centro'),
('M234','Universidad de Antioquia',6.266700,-75.567800,4,0,'Universidades'),
('M234','UPB (Laureles)',6.260100,-75.577700,5,0,'Laureles'),
('M234','Estadio',6.255100,-75.582600,6,0,'Estadio');

-- ----------------------------------------------------------------------------
-- Paradas · M145 Circular Zona Comercial
-- ----------------------------------------------------------------------------
INSERT INTO `stops` (`route_id`,`name`,`lat`,`lng`,`stop_order`,`is_terminal`,`zone`) VALUES
('M145','El Hueco',6.217600,-75.566600,1,1,'Comercial'),
('M145','Mayorca',6.224700,-75.572300,2,0,'Comercial'),
('M145','Oviedo',6.208700,-75.569500,3,0,'El Poblado'),
('M145','Santa Fe',6.205100,-75.565800,4,0,'El Poblado'),
('M145','Las Palmas (Acceso)',6.209800,-75.558700,5,0,'Las Palmas'),
('M145','San Diego',6.216900,-75.565100,6,0,'Comercial');

-- ----------------------------------------------------------------------------
-- Paradas · M301 El Poblado ↔ Envigado
-- ----------------------------------------------------------------------------
INSERT INTO `stops` (`route_id`,`name`,`lat`,`lng`,`stop_order`,`is_terminal`,`zone`) VALUES
('M301','Parque del Poblado',6.206700,-75.570000,1,1,'El Poblado'),
('M301','Los Molinos',6.201500,-75.573800,2,0,'El Poblado'),
('M301','Las Vegas (Km 1)',6.196200,-75.579000,3,0,'Sur'),
('M301','Dirección de Tránsito',6.190100,-75.582400,4,0,'Envigado'),
('M301','Envigado (Parque)',6.169300,-75.582400,5,0,'Envigado');

-- ----------------------------------------------------------------------------
-- Paradas · M420 Laureles ↔ Belén
-- ----------------------------------------------------------------------------
INSERT INTO `stops` (`route_id`,`name`,`lat`,`lng`,`stop_order`,`is_terminal`,`zone`) VALUES
('M420','Carlos E. Restrepo',6.258900,-75.590600,1,1,'Laureles'),
('M420','Circular 74 (74-65)',6.253300,-75.594400,2,0,'Laureles'),
('M420','Avenida 80 - Rosales',6.245500,-75.596700,3,0,'Belén'),
('M420','Belén (Parque)',6.241200,-75.598800,4,0,'Belén'),
('M420','Los Alpes',6.235800,-75.599500,5,0,'Belén'),
('M420','Miravalle',6.230100,-75.595200,6,0,'Belén');

-- ----------------------------------------------------------------------------
-- Paradas · M505 Centro ↔ Manrique
-- ----------------------------------------------------------------------------
INSERT INTO `stops` (`route_id`,`name`,`lat`,`lng`,`stop_order`,`is_terminal`,`zone`) VALUES
('M505','Boston (Parque)',6.257000,-75.563300,1,1,'Centro'),
('M505','Junín',6.250100,-75.564800,2,0,'Centro'),
('M505','Villanueva',6.260800,-75.560100,3,0,'Manrique'),
('M505','Berlín',6.267200,-75.554800,4,0,'Manrique'),
('M505','La Salle',6.271500,-75.549900,5,0,'Manrique'),
('M505','Manrique Central',6.275800,-75.545200,6,1,'Manrique');

SET @admin_uuid = UUID();
SET @demo_uuid  = UUID();

-- ----------------------------------------------------------------------------
-- Usuarios demo: admin@llegapp.com / Admin123!  ·  demo@llegapp.com / Demo123!
-- ----------------------------------------------------------------------------
INSERT IGNORE INTO `users` (`id`,`name`,`email`,`password_hash`,`role`,`avatar_color`,`email_verified`) VALUES
(@admin_uuid,'Administrador LlegAPP','admin@llegapp.com','$2y$10$VfUThwQf09aLe2lXb6oLleB00ZOZWVZYFev7MxJqRbVdUm9pZkDbi','admin','#00b25a',1),
(@demo_uuid,'Usuario Demostración','demo@llegapp.com','$2y$10$pzFIO9DCTd7wBq8TeYLUC.vH7dVez/TTlmHISDWss3.jh5bFNY5um','usuario','#3B82F6',1);

INSERT IGNORE INTO `user_preferences` (`user_id`) VALUES (@admin_uuid), (@demo_uuid);

-- Bienvenida para el usuario demo
INSERT INTO `notifications` (`user_id`,`type`,`title`,`message`,`icon`,`link_url`) VALUES
(@demo_uuid,'sistema','¡Bienvenido a LlegAPP!','Explora el mapa en tiempo real, guarda tus rutas favoritas y crea alertas de llegada.','🎉', NULL);

-- Incidencia de ejemplo activa
INSERT INTO `system_alerts` (`route_id`,`severity`,`title`,`message`,`active`,`created_by`)
VALUES ('M301','media','Demoras en corredor sur','Tránsito lento por obras en la Avenida Las Vegas. Frecuencia ampliada a 12 minutos.',1,@admin_uuid);
