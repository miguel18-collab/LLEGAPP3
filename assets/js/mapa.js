/* ============================================================================
   LLEGAPP 3 · JavaScript exclusivo de la página Mapa
   ============================================================================ */
'use strict';

document.addEventListener('DOMContentLoaded', () => {
    const filtroInicial = document.body.dataset.filtroRuta || '';
    const centroLat = parseFloat(document.body.dataset.centerLat) || 6.2518;
    const centroLng = parseFloat(document.body.dataset.centerLng) || -75.5636;
    const zoom = parseInt(document.body.dataset.defaultZoom) || 13;
    const autenticado = document.body.dataset.auth === '1';

    APP.mapa = crearMapa('mapaLeaflet', [centroLat, centroLng], zoom);
    if (!APP.mapa) return;

    document.body.dataset.auth = autenticado ? '1' : '0';

    // Cargar polilínea si hay ruta filtrada
    if (filtroInicial) {
        fetch(`${APP.base}/api/rutas.php?ruta=${encodeURIComponent(filtroInicial)}&con_paradas=1`, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
        })
            .then(r => r.json())
            .then(d => { if (d.success) dibujarRuta(APP.mapa, d.data.paradas, d.data.ruta.color); });
    }

    iniciarPollingFlota({
        rutaFiltro: filtroInicial,
        intervaloMs: 3000,
        onDatos: datos => {
            const activos = datos.buses.filter(b => b.status === 'operando').length;
            const el = document.getElementById('txtFlota');
            if (el) el.textContent = `${activos} buses en línea`;
        },
    });

    // Filtro por ruta desde sidebar
    let capaRutaActual = null;
    document.querySelectorAll('.ruta-item').forEach(btn => {
        btn.addEventListener('click', () => {
            const ruta = btn.dataset.ruta;
            detenerPolling();
            Object.values(APP.marcadoresBuses).forEach(m => APP.mapa.removeLayer(m));
            APP.marcadoresBuses = {};
            APP.busSeguido = null;

            if (capaRutaActual) {
                capaRutaActual.forEach(l => APP.mapa.removeLayer(l));
                capaRutaActual = null;
            }

            if (ruta) {
                fetch(`${APP.base}/api/rutas.php?ruta=${encodeURIComponent(ruta)}&con_paradas=1`, {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                })
                    .then(r => r.json())
                    .then(d => { if (d.success) capaRutaActual = dibujarRutaConCapas(APP.mapa, d.data.paradas, d.data.ruta.color); });
                history.replaceState(null, '', `?ruta=${ruta}`);
                iniciarPollingFlota({ rutaFiltro: ruta, intervaloMs: 3000 });
            } else {
                history.replaceState(null, '', location.pathname);
                APP.mapa.setView([centroLat, centroLng], zoom);
                iniciarPollingFlota({ intervaloMs: 3000 });
            }
        });
    });

    // Búsqueda en sidebar
    const buscador = document.getElementById('buscarRutaSidebar');
    if (buscador) {
        buscador.addEventListener('input', e => {
            const q = e.target.value.toLowerCase().trim();
            document.querySelectorAll('.ruta-item').forEach(item => {
                const coincide = !q || (item.dataset.buscar || 'todas').includes(q);
                item.style.display = coincide ? '' : 'none';
            });
        });
    }

    // Mi ubicación
    const btnMiUbicacion = document.getElementById('btnMiUbicacion');
    if (btnMiUbicacion) {
        btnMiUbicacion.addEventListener('click', () => {
            if (!navigator.geolocation) return toast('Geolocalización no disponible', '', 'error');
            navigator.geolocation.getCurrentPosition(
                pos => marcarUsuarioEnMapa(pos.coords.latitude, pos.coords.longitude, pos.coords.accuracy),
                () => toast('No se pudo obtener tu ubicación', 'Verifica los permisos del navegador.', 'error')
            );
        });
    }

    // Reportar problema desde el mapa
    const btnReportar = document.getElementById('btnReportar');
    if (btnReportar) {
        btnReportar.addEventListener('click', () => {
            const tipo = prompt('¿Qué tipo de problema reportas?\n1. bus_fantasma\n2. ruta_incorrecta\n3. tiempo_inaccurato\n4. bus_detenido\n5. otro\n\nEscribe el número o nombre:');
            if (!tipo) return;
            const tiposMap = {
                '1': 'bus_fantasma',
                '2': 'ruta_incorrecta',
                '3': 'tiempo_inaccurato',
                '4': 'bus_detenido',
                '5': 'otro',
            };
            const tipoValido = tiposMap[tipo]
                || (['bus_fantasma', 'ruta_incorrecta', 'tiempo_inaccurato', 'bus_detenido', 'otro'].includes(tipo) ? tipo : 'otro');
            const desc = prompt('Describe el problema (opcional):') || '';
            reportarProblema(null, filtroInicial || null, null, tipoValido, desc);
        });
    }

    // Pausar / reanudar polling
    let pausado = false;
    const btnSeguirActivo = document.getElementById('btnSeguirActivo');
    if (btnSeguirActivo) {
        btnSeguirActivo.addEventListener('click', () => {
            pausado = !pausado;
            if (pausado) detenerPolling();
            else iniciarPollingFlota({ rutaFiltro: filtroInicial, intervaloMs: 3000 });
            toast(pausado ? 'Actualización pausada' : 'Actualización reanudada', '', 'info', 2200);
        });
    }

    window.seleccionarParadaMapa = async p => {
        const data = await api(`${APP.base}/api/eta.php?parada=${p.id}`);
        if (!data?.success) return;
        const filas = data.data.buses.map(b =>
            `<tr><td><strong>${b.bus_code}</strong></td><td>${b.passengers}/${b.capacity} 👥</td><td><strong style="color:${b.eta_min <= 3 ? 'var(--primary)' : 'inherit'}">${b.eta_min} min</strong></td></tr>`
        ).join('');
        L.popup().setLatLng([p.lat, p.lng]).setContent(`
            <strong>🚏 ${p.name}</strong><br>
            <span style="color:var(--text-muted);font-size:12px">Próximos buses:</span>
            ${filas || '<em style="font-size:12px">Sin buses próximos</em>'}`).openOn(APP.mapa);
    };
});

function dibujarRutaConCapas(mapa, paradas, color) {
    const capas = [];
    const puntos = paradas.map(p => [parseFloat(p.lat), parseFloat(p.lng)]);
    if (puntos.length > 1) {
        capas.push(L.polyline(puntos, { color, weight: 5, opacity: 0.85 }).addTo(mapa));
    }
    paradas.forEach((p, i) => {
        const capa = L.circleMarker([parseFloat(p.lat), parseFloat(p.lng)], {
            radius: parseInt(p.is_terminal) === 1 ? 9 : 6,
            color,
            fillColor: '#fff',
            fillOpacity: 1,
            weight: 3.5,
        }).bindTooltip(`${i + 1}. ${p.name}`, { direction: 'top' }).addTo(mapa);
        capa.on('click', () => window.seleccionarParadaMapa?.(p));
        capas.push(capa);
    });
    mapa.fitBounds(L.latLngBounds(puntos).pad(0.15));
    return capas;
}
