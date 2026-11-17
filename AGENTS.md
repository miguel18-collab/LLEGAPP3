# 🤖 Instrucciones para IA — LLEGAPP 3

Este archivo ayuda a cualquier asistente de IA a entender, mantener y mejorar el proyecto **LLEGAPP 3**.

## 1. Qué es LLEGAPP 3

Sistema web de rastreo de transporte urbano en tiempo real para Medellín, Colombia. Stack: PHP 8 + PDO + MySQL/MariaDB, JavaScript vanilla, Leaflet (mapas), CSS propio (design system), PWA (service worker + manifest).

Arquitectura basada en capas:
- `index.php` — Landing pública
- `pages/` — Vistas de usuario (mapa, rutas, planificador, perfil, etc.)
- `admin/` — Panel de administración (dashboard, rutas, buses, usuarios, incidencias, estadísticas)
- `api/` — API REST JSON (`posiciones`, `rutas`, `eta`, `planificar`, `favoritos`, `alertas`, `notificaciones`, `perfil`, `reporte_problema`, `viaje_activo`, `admin/*`)
- `processes/` — Acciones de sesión (login, registro, logout)
- `includes/` — Lógica de negocio (autenticación, simulación de flota, rutas, header/footer)
- `config/` — Conexión BD, seguridad, configuración global
- `assets/` — CSS (design system), JS (app.js, mapa.js), imágenes SVG/PNG
- `database/` — `schema.sql`, `seed.sql`, `setup.php` (instalador), `mejoras_schema.sql` (tablas nuevas), `llegapp3_dump.sql` (dump completo)

## 2. Base de datos

BD: `llegapp3`
Usuario por defecto (XAMPP): `root` / sin contraseña

Tablas principales:
- `users` — Usuarios (admin, usuario, conductor)
- `routes` — Rutas de transporte
- `stops` — Paradas (con coordenadas y orden)
- `buses` — Flota simulada (posición, velocidad, pasajeros, estado)
- `favorites`, `trip_history`, `user_alerts`, `notifications`, `system_alerts`
- `data_sources` — Honestidad del dato (en_vivo / horario_programado / simulado)
- `reportes_problema` — Reportes de usuarios (bus fantasma, etc.)
- `viajes_activos` — Modo viaje y alerta de desembarque

Instalación: `http://localhost/LLEGAPP3/database/setup.php`
Mejoras: ejecutar `database/mejoras_schema.sql`

## 3. Cuentas demo

| Rol | Correo | Contraseña |
|-----|--------|------------|
| Admin | admin@llegapp.com | Admin123! |
| Usuario | demo@llegapp.com | Demo123! |

## 4. Reglas de estilo y convenciones

- PHP: PDO con prepared statements (nunca consultas sin preparar). `requireMethod()`, `requerirCSRF()`, `requerirAuth()`, `requerirAdmin()`.
- JSON API: respuesta estandarizada `{success, status, data|message}`.
- JS: `APP.base`, `APP.csrf`, `api()` helper, `toast()` para notificaciones.
- CSS: variables CSS (`--primary`, `--bg`, etc.), tema claro/oscuro, diseño responsive (móvil primero).
- Nombres de archivos en minúsculas con guiones bajos.

## 5. Áreas de mejora conocidas (pendientes)

- Conectar datos reales GTFS de transporte de Medellín (ahora es simulación honesta).
- Rutas multimodal (Metro, Tranvía, Metrocables).
- Notificaciones push reales (VAPID).
- Panel admin de reportes de problemas.
- Recuperación de contraseña en la app.

## 6. Comandos útiles

```bash
# Instalar BD base
http://localhost/LLEGAPP3/database/setup.php

# Aplicar mejoras de BD
mysql -u root llegapp3 < database/mejoras_schema.sql

# Verificar sintaxis PHP
php -l archivo.php

# Git (repositorio: https://github.com/miguel18-collab/LLEGAPP3.git)
git add -A && git commit -m "mensaje" && git push origin main
```

## 7. Contacto del autor

Repositorio: https://github.com/miguel18-collab/LLEGAPP3
