# 📊 Comparativa: LLEGAPP 3 vs Apps similares de transporte público

Este documento analiza las apps de rastreo de transporte más relevantes de Colombia y Latinoamérica,
identifica qué hacen mejor que LLEGAPP 3 y establece el plan de mejoras para superarlas.

---

## 1. Apps analizadas

| App | Origen | Alcance | Modelo de datos | PWA | Cuenta requerida | Anuncios |
|-----|--------|---------|-----------------|-----|------------------|----------|
| **Muuf** | Medellín, CO | Medellín, Rionegro | Tiempo real (fuentes opcionales de empresas) | No | No | No |
| **Chiva** | Colombia Move | Bogotá, Medellín | Datos abiertos oficiales (TransMilenio, Metro) | ✅ Sí | ✅ No requerida | ✅ No |
| **HiWay** | Colombia (iOS) | Colombia | Tiempo real | No | No | No |
| **Paradero** | Croma Labs, Bogotá | Bogotá | Datos abiertos TransMilenio, MIT open source | Sí | No | No |
| **MiTrasto** | Bogotá | Bogotá | TransMilenio/SITP en vivo + saldo tarjeta | Web | No | No |
| **Q'ruta** | Colombia | 13 ciudades | Rutas, horarios, paradas | ✅ Sí | No | No |
| **Treep** | Ualabee, LatAm | 8 países | Tiempo real + crowdsourcing | No | Sí | No |
| **Moovit** | Global | 112 países | Múltiples modos + datos oficiales | No | No | Sí (gratis) |
| **OneBusAway** | EE. UU. / Canadá | Ciudades seleccionadas | Tiempo real + GTFS, open source | No | No | No |

---

## 2. Matriz de funcionalidades

| Funcionalidad | Muuf | Chiva | HiWay | Paradero | MiTrasto | Q'ruta | Treep | Moovit | OneBusAway | **LLEGAPP 3** |
|---------------|------|-------|-------|----------|----------|--------|-------|--------|------------|---------------|
| Mapa en tiempo real | ✅ | ✅ | ✅ | ✅ | ✅ | ⚠️ Parciales | ✅ | ✅ | ✅ | ✅ |
| Buses en movimiento (vivo) | ✅ | ✅ | ✅ | ✅ | ✅ | ❌ | ✅ | ✅ | ✅ | ✅ (simulado) |
| Planificador A → B | ✅ | ✅ | ❌ | ❌ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| Alertas de llegada a parada | ❌ | ❌ | ❌ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| **Honestidad: en vivo vs horario** | ⚠️ Parcial | ✅ Excelente | ❌ | ✅ Excelente | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ **FALTA** |
| **Alerta al acercarse a parada (desembarque)** | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ✅ | ✅ | ✅ | ❌ **FALTA** |
| **Reportar bus fantasma / problema** | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ✅ (chat ciudad) | ❌ | ✅ (report ghost bus) | ❌ **FALTA** |
| Alertas de servicio / incidencias | ❌ | ⚠️ Básico | ❌ | ❌ | ❌ | ✅ Noticias | ✅ | ✅ Alertas de servicio | ❌ | ⚠️ Básico (admin) |
| Ocupación del vehículo en tiempo real | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ✅ | ❌ | ❌ | ⚠️ Simulado |
| Multimodal (metro, tranvía, cable) | ❌ | ✅ Medellín | ❌ | ❌ | ❌ | ❌ | ✅ | ✅ | ❌ | ❌ **FALTA** |
| Tarifa / saldo de tarjeta integrada | ❌ | ❌ | ❌ | ❌ | ✅ | ❌ | ✅ | ✅ Reserva billetes | ❌ | ❌ **FALTA** |
| Sin cuenta requerida | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ❌ | ✅ | ✅ | ❌ Requiere login |
| PWA instalable | ❌ | ✅ | ❌ | ✅ | Web | ✅ | ❌ | ❌ | ❌ | ✅ |
| Accesibilidad (lector de pantalla) | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ✅ VoiceOver | ⚠️ Básico |
| Geolocalización del usuario en mapa | ❌ | ✅ | ❌ | ✅ | ❌ | ❌ | ✅ | ✅ | ✅ | ❌ **FALTA** |
| Datos abiertos oficiales | ⚠️ Opcional | ✅ | ❌ | ✅ | ✅ | ❌ | ⚠️ Parcial | ✅ | ✅ | ❌ Simulado |

---

## 3. Qué tienen las demás que LLEGAPP 3 NO tiene (y debería implementar)

### Crítico (alto impacto, medio esfuerzo)

| # | Funcionalidad | Apps que la tienen | Por qué es necesaria para LLEGAPP 3 |
|---|---------------|-------------------|-------------------------------------|
| 1 | **Honestidad con el dato** (indicador visual: "en vivo" vs "horario programado" vs "simulado") | Chiva, Paradero | Evita frustrar al usuario mostrando hora programada como si fuera en vivo. Genera confianza. |
| 2 | **Alerta de desembarque** (avisa cuando te acercas a tu parada para bajarte) | Treep, Moovit, OneBusAway | Funcionalidad estrella del modo viaje. Muy valorada por usuarios. |
| 3 | **Reportar problema / bus fantasma** | OneBusAway, Treep | Crowdsourcing: los usuarios ayudan a detectar datos incorrectos. |
| 4 | **Geolocalización del usuario en el mapa** ("estás aquí") | Chiva, Paradero, Treep, Moovit | Contexto esencial para saber qué tan lejos está la parada más cercana. |
| 5 | **Multimodal** (metro, tranvía, metrocable, bus) | Chiva (Medellín), Moovit, Treep | Medellín tiene Metro, Tranvía y Metrocables. LlegAPP solo tiene buses. |

### Alto impacto (mayor esfuerzo)

| # | Funcionalidad | Apps que la tienen |
|---|---------------|-------------------|
| 6 | **Datos abiertos oficiales** (conectar con fuentes reales del transporte) | Chiva, Paradero, MiTrasto, Moovit |
| 7 | **Tarifa / saldo de tarjeta integrada** | MiTrasto, Moovit, Treep |
| 8 | **Alertas de servicio en tiempo real** (cortes, desvíos, paros) | Treep, Moovit, Q'ruta |
| 9 | **Ocupación real del vehículo** (sensor o estimación avanzada) | Treep |

### Diferenciador (para ganar a la competencia)

| # | Funcionalidad | Justificación |
|---|---------------|---------------|
| 10 | **Panel de administración completo** (ya lo tiene LLEGAPP 3) | Ninguna app competidora ofrece panel admin tan completo para operadores. Es una ventaja competitiva única. |
| 11 | **PWA + sin anuncios + sin cuenta obligatoria** | Chiva y Paradero lo hacen. LLEGAPP 3 ya tiene PWA; solo falta eliminar la obligatoriedad de cuenta para uso básico. |

---

## 4. Plan de acción para LLEGAPP 3 (proyecto de 4 personas)

### Semana 1 — Honestidad con el dato y corregir mapa (crítico)
- [ ] Agregar columna `data_sources.source_type` (en_vivo / horario_programado / simulado)
- [ ] Mostrar etiqueta visual en el mapa y tarjeta del bus: "🔴 Simulado" / "🟡 Horario" / "🟢 En vivo"
- [ ] Corregir API de posiciones (`api/posiciones.php`) para que nunca rompa el frontend
- [ ] Agregar geolocalización del usuario ("estás aquí") en el mapa

### Semana 2 — Reportes y modo viaje
- [ ] Endpoint `/api/reporte_problema.php` (bus fantasma, ruta incorrecta)
- [ ] Botón "Reportar problema" en tarjeta del bus
- [ ] Endpoint `/api/viaje_activo.php` y alerta de desembarque cuando el bus esté cerca de la parada destino

### Semana 3 — Multimodal y mejoras admin
- [ ] Agregar rutas de Metro, Tranvía y Metrocables de Medellín (tipo de ruta distinto al bus)
- [ ] Panel admin: ver reportes de problemas, estado de fuentes de datos, estadísticas de viajeros
- [ ] Notificaciones push para alertas de servicio

### Semana 4 — Pulido y pruebas
- [ ] Accesibilidad básica (aria-labels, contraste, navegación por teclado)
- [ ] Pruebas en dispositivos móviles reales
- [ ] Documentación de API y manual de usuario

---

## 5. Conclusión

LLEGAPP 3 ya tiene una base sólida: mapa en vivo, planificador, alertas, favoritos, historial, panel admin completo y PWA. 
Para ser **superior a la competencia**, debe priorizar:

1. **Honestidad con el dato** (lo que falla en Muuf, Treep y Moovit según sus reseñas)
2. **Alerta de desembarque** (funcionalidad más valorada por usuarios según OneBusAway y Treep)
3. **Reportar problema** (crowdsourcing que mantiene los datos limpios)
4. **Geolocalización del usuario** en el mapa (contexto esencial)
5. **Multimodal** (integrar Metro/Medellín para ser realmente útil en la ciudad)

Con estas 5 mejoras, LLEGAPP 3 superaría a Muuf, Chiva, HiWay, Paradero, MiTrasto, Q'ruta y Treep en el contexto local de Medellín.
