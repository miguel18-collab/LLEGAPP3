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

    cont.innerHTML = `
        <div class="flex-between" style="margin-bottom:8px">
            <span class="live-indicator"><span class="live-dot"></span> EN VIVO</span>
            <button class="icon-btn" id="cerrarBusCard" aria-label="Cerrar">✕</button>
        </div>
        <div class="flex-between">
            <div>
                <strong style="font-size:18px">${bus.code}</strong>
                <span class="chip" style="--route-color:${bus.color};margin-left:8px">${bus.route_id}</span>
            </div>
            <button class="btn btn-secondary btn-sm" id="btnSeguir">${APP.busSeguido === bus.id ? 'Siguiendo ✓' : 'Seguir'}</button>
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
        </div>`;

    $('#cerrarBusCard').onclick = () => { cont.remove(); APP.busSeguido = null; };
    $('#btnSeguir').onclick = () => {
        APP.busSeguido = APP.busSeguido === bus.id ? null : bus.id;
        seleccionarBus(bus);
    };

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
                onDatos?.(data.data);
            }
        } catch (err) {
            console.warn('[flota] sin conexión con el servidor', err);
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
});
