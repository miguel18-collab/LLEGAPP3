/* ============================================================================
   LLEGAPP 3 · Núcleo JavaScript
   Tema · Menús · Toasts · Mapa Leaflet en tiempo real · Polling de flota
   ============================================================================ */
'use strict';

const APP = {
    base: document.body.dataset.base || '',
    page: document.body.dataset.page || '',
    csrf: window.CSRF_TOKEN || '',
    mapa: null,
    marcadoresBuses: {},
    capasRutas: {},
    timerFlota: null,
    busSeguido: null,
};

/* ============================ Utilidades ================================ */
const $  = (sel, ctx = document) => ctx.querySelector(sel);
const $$ = (sel, ctx = document) => [...ctx.querySelectorAll(sel)];

function toast(titulo, mensaje = '', tipo = 'info', duracion = 4200) {
    const cont = $('#toastContainer');
    if (!cont) return;
    const el = document.createElement('div');
    el.className = `toast ${tipo}`;
    el.innerHTML = `<div><strong></strong><p></p></div>`;
    $('strong', el).textContent = titulo;
    $('p', el).textContent = mensaje;
    cont.appendChild(el);
    setTimeout(() => { el.classList.add('out'); setTimeout(() => el.remove(), 260); }, duracion);
}

async function api(url, opciones = {}) {
    const resp = await fetch(url, {
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-Token': APP.csrf,
        },
        ...opciones,
    });
    if (resp.status === 401) { location.href = `${APP.base}/pages/login.php`; return null; }
    return resp.json();
}

/* ============================ Tema claro/oscuro ========================= */
function initTema() {
    const btn = $('#themeToggle');
    const aplicar = tema => {
        document.body.classList.toggle('dark-theme', tema === 'dark');
        document.body.classList.toggle('light-theme', tema !== 'dark');
        document.cookie = `theme=${tema};path=/;max-age=31536000;samesite=Lax`;
        if (APP.mapa && window.L) refrescarTiles();
    };
    let tema = document.cookie.match(/(?:^|;\s*)theme=(dark|light)/)?.[1]
        ?? (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
    aplicar(tema);
    btn?.addEventListener('click', () => {
        tema = tema === 'dark' ? 'light' : 'dark';
        aplicar(tema);
    });
}

/* ============================ Menús ===================================== */
function initMenus() {
    const menuToggle = $('#menuToggle');
    const mobileNav = $('#mobileNav');
    menuToggle?.addEventListener('click', () => {
        const abierto = mobileNav.hidden;
        mobileNav.hidden = !abierto;
        mobileNav.dataset.open = abierto;
        menuToggle.setAttribute('aria-expanded', abierto);
    });

    const avatarBtn = $('#avatarBtn');
    const dropdown = $('.dropdown-menu');
    avatarBtn?.addEventListener('click', e => {
        e.stopPropagation();
        const oculto = dropdown.hidden;
        dropdown.hidden = !oculto;
        avatarBtn.setAttribute('aria-expanded', oculto);
    });
    document.addEventListener('click', e => {
        if (dropdown && !dropdown.hidden && !e.target.closest('#userMenu')) {
            dropdown.hidden = true;
            avatarBtn.setAttribute('aria-expanded', 'false');
        }
    });
}

/* ============================ Mapa en tiempo real ======================= */
function crearMapa(idContenedor, centro = [6.2518, -75.5636], zoom = 13) {
    if (!window.L) return null;
    const map = L.map(idContenedor, { zoomControl: false, attributionControl: true }).setView(centro, zoom);
    L.control.zoom({ position: 'bottomright' }).addTo(map);

    map._capaTiles = L.tileLayer('https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png', {
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> &copy; <a href="https://carto.com/">CARTO</a>',
        maxZoom: 19,
    }).addTo(map);
    return map;
}

function refrescarTiles() {
    // Re-render para adaptar el estilo al cambio de tema
    Object.values(APP.mapa?._layers || {}).forEach(l => l.redraw?.());
}

function iconoBus(bus, seguido = false) {
    return L.divIcon({
        className: '',
        html: `<div class="bus-marker ${seguido ? 'seguido' : ''}" style="--bus-color:${bus.color}">${(bus.code || '').replace(/[^0-9]/g, '').slice(-2)}</div>`,
        iconSize: [34, 34],
        iconAnchor: [17, 17],
    });
}

/**
 * Dibuja/actualiza buses en el mapa con transición suave
 */
function pintarBuses(buses, opciones = {}) {
    if (!APP.mapa) return;
    const vistos = new Set();

    buses.forEach(bus => {
        vistos.add(bus.id);
        const pos = [parseFloat(bus.lat), parseFloat(bus.lng)];
        const esSeguido = APP.busSeguido === bus.id;

        let marker = APP.marcadoresBuses[bus.id];
        if (!marker) {
            marker = L.marker(pos, { icon: iconoBus(bus), rotationAngle: 0 }).addTo(APP.mapa);
            marker.on('click', () => seleccionarBus(bus));
            marker._codigo = bus.code;
            marker._estado = bus.status;
            APP.marcadoresBuses[bus.id] = marker;
        } else {
            // Transición CSS suave entre actualizaciones de posición
            const old = marker.getLatLng();
            if (old.lat !== pos[0] || old.lng !== pos[1]) {
                animarMarcador(marker, old, pos, 1800);
            }
            const cambiaSeguido = esSeguido !== marker._seguido;
            if (cambiaSeguido) marker.setIcon(iconoBus(bus, esSeguido));
            if (marker._estado !== bus.status) {
                marker._estado = bus.status;
                marker.getElement()?.querySelector('.bus-marker')?.classList.toggle('detenido', bus.status !== 'operando');
            }
        }
        marker._seguido = esSeguido;

        if (esSeguido && opciones.centrarSeguido) {
            APP.mapa.panTo(pos, { animate: true, duration: .6 });
        }
    });

    // Eliminar buses fuera de servicio o filtrados
    Object.keys(APP.marcadoresBuses).forEach(id => {
        if (!vistos.has(id)) {
            APP.mapa.removeLayer(APP.marcadoresBuses[id]);
            delete APP.marcadoresBuses[id];
        }
    });
}

function animarMarcador(marker, from, to, ms) {
    const inicio = performance.now();
    function paso(t) {
        const k = Math.min(1, (t - inicio) / ms);
        const suave = k * (2 - k); // easeOutQuad
        marker.setLatLng([
            from.lat + (to[0] - from.lat) * suave,
            from.lng + (to[1] - from.lng) * suave,
        ]);
        if (k < 1) requestAnimationFrame(paso);
    }
    requestAnimationFrame(paso);
}

let popupBusActual = null;
function seleccionarBus(bus) {
    APP.busSeguido = bus.id;
    Object.entries(APP.marcadoresBuses).forEach(([id, m]) => {
        m.getElement()?.querySelector('.bus-marker')?.classList.toggle('seguido', id === bus.id);
    });
    mostrarTarjetaBus(bus);
    registrarHistorial('tracked', bus.route_id);
}

async function mostrarTarjetaBus(bus) {
    let cont = $('#busInfoCard');
    if (!cont) {
        cont = document.createElement('div');
        cont.id = 'busInfoCard';
        cont.className = 'card bus-info-card';
        $('.map-canvas')?.appendChild(cont);
    }

    const fuenteClase = bus.es_dato_real ? 'badge-success' : 'badge-danger';
    const fuenteTexto = bus.es_dato_real ? '🟢 Dato en vivo' : '🔴 Dato simulado';

    cont.innerHTML = `
        <div class="flex-between" style="margin-bottom:8px">
            <span class="live-indicator"><span class="live-dot"></span> EN VIVO</span>
            <button class="icon-btn" id="cerrarBusCard" aria-label="Cerrar">✕</button>
        </div>
        <div class="flex-between">
            <div style="display:flex;gap:12px;align-items:center">
                <strong style="font-size:18px">${bus.code}</strong>
                <span class="chip" style="--route-color:${bus.color};margin-left:8px">${bus.route_id}</span>
            </div>
            <button class="btn btn-secondary btn-sm" id="btnSeguir">${APP.busSeguido === bus.id ? 'Siguiendo ✓' : 'Seguir'}</button>
        </div>
        <div style="display:flex;gap:6px;align-items:center;margin:6px 0 4px">
            <span class="badge ${fuenteClase}" style="font-size:11px">${fuenteTexto}</span>
        </div>
        <p style="font-size:13px;color:var(--text-muted);margin:4px 0 10px">${bus.route_name}</p>
        <div style="display:flex;gap:16px;flex-wrap:wrap;font-size:13px">
            <div>🚏 Próx. parada<br><strong>${bus.next_stop}</strong></div>
            <div>⏱️ Llega en<br><strong>~${bus.eta_next_min} min</strong></div>
            <div>⚡ Velocidad<br><strong>${bus.speed_kmh} km/h</strong></div>
            <div>👥 Pasajeros<br><strong>${bus.passengers}/${bus.capacity}</strong></div>
        </div>
        <div class="skeleton" style="height:7px;margin-top:12px;border-radius:99px">
            <div style="height:100%;width:${Math.round(bus.progress * 100)}%;background:${bus.color};border-radius:99px;transition:width 1s linear"></div>
        </div>
        <div style="margin-top:10px;display:flex;gap:6px">
            <button type="button" class="btn btn-ghost btn-sm btnReportarBus" data-bus-id="${bus.id}" data-route-id="${bus.route_id}" title="Reportar problema con este bus">🚨 Reportar</button>
            <button type="button" class="btn btn-ghost btn-sm btnViajeActivo" data-bus-id="${bus.id}" data-route-id="${bus.route_id}" title="Seguir este viaje">🧭 Viajar</button>
        </div>`;

    $('#cerrarBusCard').onclick = () => { cont.remove(); APP.busSeguido = null; };
    $('#btnSeguir').onclick = () => {
        APP.busSeguido = APP.busSeguido === bus.id ? null : bus.id;
        seleccionarBus(bus);
    };

    cont.querySelector('.btnReportarBus')?.addEventListener('click', () => {
        const tipo = prompt('¿Qué tipo de problema observas?\n1. bus_fantasma\n2. ruta_incorrecta\n3. tiempo_inaccurato\n4. bus_detenido\n5. otro\n\nEscribe el número o nombre:');
        if (!tipo) return;
        const tiposMap = { '1': 'bus_fantasma', '2': 'ruta_incorrecta', '3': 'tiempo_inaccurato', '4': 'bus_detenido', '5': 'otro' };
        const tipoValido = tiposMap[tipo] || (['bus_fantasma','ruta_incorrecta','tiempo_inaccurato','bus_detenido','otro'].includes(tipo) ? tipo : 'otro');
        const desc = prompt('Describe el problema (opcional):') || '';
        reportarProblema(bus.id, bus.route_id, null, tipoValido, desc, bus.lat, bus.lng);
    });

    cont.querySelector('.btnViajeActivo')?.addEventListener('click', async () => {
        const data = await api(`${APP.base}/api/viaje_activo.php`, {
            method: 'POST',
            body: JSON.stringify({ route_id: bus.route_id, bus_id: bus.id }),
        });
        if (data?.success) {
            toast('Viaje iniciado', 'Te avisaremos al acercarte a tu parada destino.', 'success', 3000);
        } else {
            toast('Error', data?.message || 'No se pudo iniciar el viaje.', 'error');
        }
    });

    if (APP.busSeguido === bus.id) {
        APP.mapa.panTo([bus.lat, bus.lng], { animate: true, duration: .6 });
    }
}

/**
 * Bucle principal: obtiene posiciones del servidor y repinta.
 */
function iniciarPollingFlota(opts = {}) {
    const { rutaFiltro = null, intervaloMs = 3000, onDatos = null } = opts;

    async function tick() {
        try {
            const url = `${APP.base}/api/posiciones.php${rutaFiltro ? `?ruta=${encodeURIComponent(rutaFiltro)}` : ''}`;
            const data = await api(url);
            if (data?.success) {
                pintarBuses(data.data.buses, { centrarSeguido: false });
                mostrarIndicadorFuenteDatos(data.data.buses);
                onDatos?.(data.data);
                // Mostrar indicador de fuente si hay contenedor
                const cont = document.getElementById('indicadorFuente');
                if (cont && data.data.buses.length > 0) {
                    const esReal = data.data.buses.some(b => b.es_dato_real);
                    cont.innerHTML = esReal
                        ? '<span class="badge badge-success">🟢 Datos en vivo (fuente oficial)</span>'
                        : '<span class="badge badge-danger">🔴 Datos simulados (demostración)</span>';
                }
            } else {
                const cont = document.getElementById('indicadorFuente');
                if (cont) cont.innerHTML = '<span class="badge badge-danger">⚠️ Servicio no disponible</span>';
            }
        } catch (err) {
            console.warn('[flota] sin conexión con el servidor', err);
            const cont = document.getElementById('indicadorFuente');
            if (cont) cont.innerHTML = '<span class="badge badge-danger">⚠️ Sin conexión al servidor</span>';
        }
    }

    tick();
    clearInterval(APP.timerFlota);
    APP.timerFlota = setInterval(tick, intervaloMs);
    document.addEventListener('visibilitychange', () => {
        if (!document.hidden) tick();
    });
    return tick;
}

function detenerPolling() {
    clearInterval(APP.timerFlota);
}

/**
 * Dibuja la polilínea de una ruta con paradas numeradas
 */
function dibujarRuta(mapa, paradas, color, { interactivas = true } = {}) {
    const puntos = paradas.map(p => [parseFloat(p.lat), parseFloat(p.lng)]);
    if (puntos.length > 1) {
        L.polyline(puntos, { color, weight: 5, opacity: .85, lineCap: 'round' }).addTo(mapa);
        L.polyline(puntos, { color: '#fff', weight: 1.6, opacity: .5, dashArray: '6 9' }).addTo(mapa);
    }
    paradas.forEach((p, i) => {
        const esTerminal = parseInt(p.is_terminal) === 1;
        L.circleMarker([parseFloat(p.lat), parseFloat(p.lng)], {
            radius: esTerminal ? 9 : 6,
            color,
            fillColor: '#fff',
            fillOpacity: 1,
            weight: 3.5,
        })
        .bindTooltip(`${i + 1}. ${p.name}`, { direction: 'top', offset: [0, -6] })
        .on('click', () => window.seleccionarParadaMapa?.(p))
        .addTo(mapa);
    });
    mapa.fitBounds(L.latLngBounds(puntos).pad(.12));
}

/* ============================ Historial de actividad ==================== */
function registrarHistorial(accion, routeId, extra = {}) {
    if (!document.body.dataset.auth) return;
    api(`${APP.base}/api/historial.php`, {
        method: 'POST',
        body: JSON.stringify({ action: accion, route_id: routeId, ...extra }),
    }).catch(() => {});
}

/* ============================ Favoritos ================================= */
async function alternarFavorito(tipo, clave, boton = null) {
    const data = await api(`${APP.base}/api/favoritos.php`, {
        method: 'POST',
        body: JSON.stringify({ item_type: tipo, item_key: String(clave) }),
    });
    if (!data) return;
    const activo = data.data?.favorito;
    boton?.classList.toggle('activo', activo);
    if (boton) boton.textContent = activo ? '★' : '☆';
    toast(
        activo ? 'Añadido a favoritos' : 'Eliminado de favoritos',
        tipo === 'ruta' ? `Ruta ${clave}` : 'Elemento actualizado',
        'success', 2500
    );
}

/* ============================ Usuario en el mapa ========================== */
let marcadorUsuario = null;
let precisionUsuario = null;

function marcarUsuarioEnMapa(lat, lng, precision = null) {
    if (!APP.mapa || !window.L) return;
    if (marcadorUsuario) APP.mapa.removeLayer(marcadorUsuario);
    if (precisionUsuario) APP.mapa.removeLayer(precisionUsuario);

    const color = '#0ea5e9';
    marcadorUsuario = L.circleMarker([lat, lng], {
        radius: 9, color, fillColor: '#fff', fillOpacity: 0.9, weight: 3
    }).bindTooltip('Estás aquí').openTooltip().addTo(APP.mapa);

    if (precision) {
        precisionUsuario = L.circle([lat, lng], {
            radius: precision, color, fillColor: color, fillOpacity: 0.15, weight: 1, dashArray: '6 9'
        }).addTo(APP.mapa);
    }

    APP.mapa.flyTo([lat, lng], 15, { animate: true, duration: 1 });
}

/* ============================ Reportar problema =========================== */
async function reportarProblema(busId = null, routeId = null, stopId = null, tipo = 'otro', descripcion = '', lat = null, lng = null) {
    const data = await api(`${APP.base}/api/reporte_problema.php`, {
        method: 'POST',
        body: JSON.stringify({ bus_id: busId, route_id: routeId, stop_id: stopId, tipo, descripcion, lat, lng }),
    });
    if (data?.success) {
        toast('Reporte enviado', 'Gracias por ayudarnos a mejorar el servicio.', 'success', 3000);
        return true;
    }
    toast('Error al enviar reporte', data?.message || 'Inténtalo nuevamente.', 'error');
    return false;
}

/* ============================ Indicador fuente de datos =================== */
function mostrarIndicadorFuenteDatos(buses) {
    const contenedor = $('#indicadorFuente');
    if (!contenedor) return;

    const tipos = new Set(buses.map(b => b.fuente_tipo));
    let texto = 'Datos: ';
    let clase = '';
    if (tipos.has('en_vivo')) { texto += '🟢 En vivo (fuente oficial)'; clase = 'badge-success'; }
    else if (tipos.has('horario_programado')) { texto += '🟡 Horario programado'; clase = 'badge-warning'; }
    else { texto += '🔴 Simulado (demostración)'; clase = 'badge-danger'; }

    contenedor.innerHTML = `<span class="badge ${clase}">${texto}</span>`;
}

/* ============================ Arranque ================================== */
document.addEventListener('DOMContentLoaded', () => {
    initTema();
    initMenus();

    // Marcar ítems activos del bottom-nav según la página actual
    const paginaActual = location.pathname.split('/').pop()?.split('.')[0] || '';
    $$('.bottom-item').forEach(a => {
        const destino = (a.getAttribute('href') || '').split('/').pop()?.split('.')[0];
        a.classList.toggle('activo',
            destino === paginaActual ||
            (paginaActual.startsWith('detalle_ruta') && destino === 'rutas'));
    });

    // Geolocalización del usuario si está en una página con mapa
    if (APP.mapa && navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(
            pos => marcarUsuarioEnMapa(pos.coords.latitude, pos.coords.longitude, pos.coords.accuracy),
            err => console.warn('[geolocalización] denegada o no disponible', err.code)
        );
    }
});
