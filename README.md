# 🚌 LlegAPP 3

Sistema **profesional de rastreo de transporte urbano en tiempo real** para Medellín, Colombia.
Sucesor de LlegAPP 2, construido con la calidad y completitud de un producto de producción.

![PHP](https://img.shields.io/badge/PHP-8.0%2B-777bb4) ![MySQL](https://img.shields.io/badge/MySQL-MariaDB-00618a) ![PWA](https://img.shields.io/badge/PWA-ready-5a0fc8)

---

## ✨ Características

### Para pasajeros
- 🗺️ **Mapa en vivo** — flota simulada sobre Leaflet/OpenStreetMap con actualización cada 3 s, transiciones suaves, seguimiento de bus individual y tarjetas de información (velocidad, ocupación, próxima parada).
- 🛣️ **Catálogo de rutas** — búsqueda, favoritos, horarios y estado de cada línea.
- 🚏 **Detalle de ruta** — recorrido completo con paradas numeradas, ETA por parada en tiempo real y mapa dedicado.
- 🧭 **Planificador de viajes** — calcula rutas directas o con transbordo, tiempos estimados según la posición real de la flota y tarifa aproximada (COP).
- 🔔 **Alertas inteligentes** — avisa cuando tu bus está a N paradas de tu parada; bandeja de notificaciones in-app con contador no leídas.
- ⭐ Favoritos · 🕘 Historial con métricas personales · 👤 Perfil con preferencias (tema claro/oscuro, antelación de avisos).

### Para administradores
- 📊 **Dashboard** — KPIs, gráficas Chart.js (actividad 14 días, estado de flota), control de simulación (pausar/reanudar/reset).
- 🛣️ **Gestión de rutas** — CRUD completo + editor de paradas con reordenamiento y coordenadas.
- 🚌 **Gestión de flota** — CRUD de buses, placas, capacidad, velocidad, estado operativo.
- 👥 **Gestión de usuarios** — roles (admin/usuario/conductor), activar/desactivar, reset de contraseña, eliminación segura.
- 📢 **Incidencias** — publicar avisos por severidad que se notifican a todos los usuarios.

### Técnico
- **Motor de simulación server-side**: la posición se calcula determinísticamente desde `last_update` + velocidad; cualquier cliente dispara el tick global (con throttle anti-carrera). No requiere cron.
- API REST JSON con respuestas estandarizadas (`{success, status, data|message}`).
- Seguridad: PDO con prepared statements, CSRF en todos los formularios/PUT/DELETE, rate-limiting de login, sesiones endurecidas, headers HTTP de seguridad, hashing bcrypt.
- PWA completa: manifest, service worker (network-first + tiles cacheados) y página offline.
- Diseño responsive propio (design system CSS con variables, tema oscuro automático).

---

## 🚀 Instalación

1. Copia la carpeta en `C:\xampp\htdocs\LLEGAPP3`.
2. Inicia **Apache** y **MySQL** desde XAMPP.
3. Abre el instalador: <http://localhost/LLEGAPP3/database/setup.php>
   (crea BD `llegapp3`, tablas, vistas, datos demo y flota).
4. Entra a <http://localhost/LLEGAPP3/>

### Cuentas demo
| Rol | Correo | Contraseña |
|---|---|---|
| Admin | `admin@llegapp.com` | `Admin123!` |
| Usuario | `demo@llegapp.com` | `Demo123!` |

> ⚠️ Cambia estas contraseñas antes de usar en producción.

---

## 📁 Estructura

```
LLEGAPP3/
├── index.php              Landing pública
├── manifest.json / sw.js  PWA
├── config/                BD (PDO), seguridad, configuración
├── includes/              auth, funciones, header/footer, motor de simulación, lógica rutas
├── pages/                 mapa, rutas, detalle, planificador, favoritos,
│                          historial, alertas, notificaciones, perfil, login, registro
├── admin/                 dashboard, rutas, buses, usuarios, incidencias, estadísticas
├── api/                   REST JSON (posiciones, eta, planificar, favoritos…)
│   └── admin/             dashboard KPIs, control de simulación
├── processes/             login, registro, logout
├── assets/                css design system · js núcleo · img
└── database/              schema.sql, seed.sql, setup.php (instalador)
```

## 🗄️ Base de datos (`llegapp3`)

`users` · `user_preferences` · `routes` · `stops` · `buses` · `favorites`
`trip_history` · `user_alerts` · `notifications` · `system_alerts` · `settings`

---

## 📄 Licencia

Proyecto educativo/demostrativo. Los datos de rutas y posiciones son simulados.
