# Plan Maestro de Mejoras: mxo-track

**Fecha:** 2026-03-11
**Estado:** En ejecución — Fase 0 ✅, Fase 2 ✅, Fases 1/3/4/5/6 pendientes
**Objetivo:** Plan exhaustivo de mejoras organizadas por fases, priorizadas por impacto de negocio vs esfuerzo
**Contexto:** Pre-producción, tests obligatorios, tres objetivos (cerrar ventas + retener + escalar)

---

## Diagnóstico del Estado Actual

### Lo que funciona bien
- Arquitectura Ports & Adapters sólida con multi-tenancy
- Route optimization completo (VROOM + OSRM + capacity validation + skills)
- GPS tracking en tiempo real (Traccar → Mercure SSE)
- Portal driver funcional (POD, exceptions, briefing)
- API v1 para integraciones externas
- CSV import con validación de calidad
- Provider framework configurable por tenant

### Lo que está scaffoldeado pero inactivo
- **AI/ML completo:** Claude exception classifier, post-route analyzer, delivery risk, demand forecasting, embeddings — todo usa Null implementations
- **SMS/WhatsApp:** Twilio integration lista con NullSmsProvider/NullWhatsAppProvider
- **ML Microservice:** Python Flask con 6 modelos (anomaly, risk, demand, affinity, time estimation, clustering)

### Gaps críticos identificados
1. **Sin test coverage suficiente** — 44 test files para 383 PHP files (~11% cobertura de archivos)
2. **Sin demo convincente end-to-end** — falta flujo completo demostrable
3. **AI/ML inactivo** — el diferenciador competitivo está apagado
4. **Notificaciones al receptor incompletas** — SMS/WhatsApp no envían realmente
5. **Sin circuit breakers** — si VROOM/OSRM cae, no hay fallback automático
6. **Credenciales sin encriptar** — CustomerIntegration guarda API keys en plaintext
7. **Deuda técnica en GPS interface** — métodos Traccar-específicos en interfaz genérica
8. **Mercure listeners no usan proxy** — bloquea HttpPolling para clientes sin WebSocket
9. **APP_BASE_URL faltante** — URLs de tracking/rating vacías en notificaciones
10. **Controller legacy duplicado** — `/api/shipments` vs `/api/v1/shipments`

---

## Fases del Plan

### Fase 0: Fundamentos (Prerequisito)
**Objetivo:** Base sólida para construir encima
**Esfuerzo:** 1-2 semanas | **Impacto:** Enabler — sin esto las demás fases tienen riesgo

### Fase 1: Demo-Ready
**Objetivo:** Producto demostrable end-to-end que cierre ventas
**Esfuerzo:** 2-3 semanas | **Impacto:** Alto — directamente genera ingresos

### Fase 2: Inteligencia Artificial Activa
**Objetivo:** Diferenciador competitivo — funcionalidades de IA que no tiene la competencia
**Esfuerzo:** 2-3 semanas | **Impacto:** Alto — "wow factor" en demos + valor real

### Fase 3: Experiencia del Receptor
**Objetivo:** El receptor (persona que recibe el paquete) tiene una experiencia excelente
**Esfuerzo:** 1-2 semanas | **Impacto:** Alto — satisfacción del cliente final

### Fase 4: Eficiencia Operativa
**Objetivo:** Operadores hacen más con menos esfuerzo
**Esfuerzo:** 2-3 semanas | **Impacto:** Medio-Alto — retención de clientes

### Fase 5: Robustez y Escala
**Objetivo:** Sistema fiable y preparado para crecimiento
**Esfuerzo:** 2-3 semanas | **Impacto:** Medio — previene problemas futuros

### Fase 6: Expansión de Negocio
**Objetivo:** Nuevas funcionalidades que abren segmentos de mercado
**Esfuerzo:** 3-4 semanas | **Impacto:** Alto a largo plazo — nuevos clientes

---

## Fase 0: Fundamentos

> Sin esta fase, las demás fases se construyen sobre terreno inestable.

### 0.1 — Arreglar test suite existente ✅
**Qué:** Asegurar que todos los tests existentes pasan sin errores
**Por qué:** No podemos añadir tests nuevos si los existentes fallan
**Tareas:**
- [x] Ejecutar `php vendor/bin/phpunit` y documentar estado actual
- [x] Arreglar tests rotos (sin cambiar comportamiento)
- [ ] Configurar CI para que falle si tests no pasan
- [x] Verificar: `phpunit` → 0 failures, 0 errors
**Resultado:** 249 tests, 701 assertions, 0 failures, 0 deprecations.
**Fix aplicado:** `fgetcsv`/`fputcsv` — añadido `$escape` parameter explícito (PHP 8.4 deprecation).

### 0.2 — Configurar APP_BASE_URL ✅
**Qué:** Variable de entorno para URLs públicas (tracking, rating, rescheduling)
**Por qué:** Sin esto, todas las URLs de notificación al receptor están vacías
**Tareas:**
- [x] Añadir `APP_BASE_URL` a `.env` (default: `http://localhost:8000`) — ya existía
- [ ] Añadir a `.env.railway` con valor de Railway (no existe el archivo; se configura en Railway dashboard)
- [x] Verificar: RecipientNotificationService usa `$appBaseUrl` para buildTrackingUrl/buildRatingUrl
**Resultado:** Ya configurado correctamente. `APP_BASE_URL=http://localhost:8000` en `.env`, inyectado via `services.yaml`.

### 0.3 — Limpiar controller duplicado ✅
**Qué:** Eliminar `/api/shipments` legacy (ShipmentApiController viejo)
**Por qué:** Dos endpoints para lo mismo confunde en demos y genera bugs
**Tareas:**
- [x] Verificar que `/api/v1/shipments` cubre toda la funcionalidad
- [x] Eliminar el controller legacy `src/Controller/ShipmentApiController.php`
- [x] Verificar: tests siguen pasando (249/249)
- [x] Actualizar docs: BACKEND_FUNCIONALIDAD.md, phase4-audit-results.md
**Resultado:** Controller legacy eliminado. V1 API cubre: POST create (batch), GET list (paginado), GET detail, GET tracking.

### 0.4 — Test coverage baseline ⏳
**Qué:** Medir coverage actual y establecer mínimos
**Por qué:** "Tests obligatorios" requiere saber dónde estamos
**Tareas:**
- [x] Verificar `phpunit.xml` con `<source>` configurado para coverage
- [ ] Instalar PCOV/Xdebug para coverage reports (no disponible en este entorno)
- [x] Documentar baseline numérico: 382 PHP source files, 42 test files, 249 tests, 701 assertions
**Baseline numérico:** ~11% cobertura de archivos (42/382). Coverage % real requiere PCOV.
**Nota:** Añadir `pecl install pcov && echo "extension=pcov.so" > /usr/local/etc/php/conf.d/pcov.ini` al Dockerfile.

---

## Fase 1: Demo-Ready

> Objetivo: un demo flow completo que un vendedor pueda ejecutar en 15 minutos.

### 1.1 — Flujo demo end-to-end automatizado
**Qué:** Comando Symfony que crea un escenario demo completo con datos realistas
**Por qué:** En cada demo se necesita datos frescos y un escenario convincente
**Impacto:** El vendedor ejecuta un comando y tiene todo listo
**Tareas:**
- [ ] Test: `DemoScenarioCommandTest` — verifica que el comando crea datos correctos
- [ ] Crear `app:demo:setup` que:
  - Crea Customer "Logística Express Madrid"
  - Crea 3 Vehicles (furgoneta, camión refrigerado, moto)
  - Crea 2 Drivers con usuarios
  - Crea 15-20 Shipments con direcciones reales de Madrid
  - Construye 2-3 Routes optimizadas automáticamente
  - Activa 1 ruta con GPS simulado
- [ ] Crear `app:demo:reset` que limpia y re-crea
- [ ] Test: verificar que las rutas tienen stops ordenados y capacity válida
- [ ] Documentar flujo demo en `docs/demo-guide.md`

### 1.2 — CSV demo para importación
**Qué:** CSV con 50+ envíos realistas de Madrid para demostrar el import
**Por qué:** El cliente en CLAUDE.md pide "CSV para importar" como requisito demo
**Impacto:** Demuestra bulk import + calidad de datos + route planning
**Tareas:**
- [ ] Test: `CsvDemoImportTest` — importar CSV, verificar shipments creados
- [ ] Crear `docs/demo/envios-madrid.csv` con:
  - 50 envíos con coordenadas reales de Madrid
  - Mix de prioridades (Critical, High, Normal, Low)
  - Mix de skills requeridos (refrigerado, frágil, pesado)
  - Mix de pesos/volúmenes variados
  - Ventanas de entrega variadas
- [ ] Verificar que `ShipmentCsvImporter` procesa sin errores
- [ ] Documentar formato en `docs/demo/csv-format.md`

### 1.3 — Configuración de rutas pre-aceptación
**Qué:** UI para revisar y ajustar rutas antes de confirmarlas
**Por qué:** El cliente pide "poder configurar antes de aceptar la ruta"
**Impacto:** Control sobre el proceso de planificación
**Tareas:**
- [ ] Test: endpoint de preview devuelve rutas sin persistir
- [ ] Crear endpoint `POST /api/routes/preview` que:
  - Acepta shipments + vehicles
  - Ejecuta RouteBuilder pero NO persiste
  - Devuelve rutas propuestas con métricas (distancia, duración, utilización)
- [ ] UI (Twig): pantalla de preview con:
  - Mapa con rutas propuestas
  - Tabla de métricas por ruta (peso/volumen/paradas)
  - Botón "Aceptar todas" / "Modificar" / "Cancelar"
- [ ] Poder mover shipments entre rutas antes de confirmar
- [ ] Test: mover shipment de ruta A a B actualiza capacity correctamente

### 1.4 — Dashboard de operador mejorado
**Qué:** Dashboard real-time con KPIs clave visibles de un vistazo
**Por qué:** Primera pantalla que ve el cliente en la demo
**Impacto:** Impresión inmediata de profesionalidad y valor
**Tareas:**
- [ ] Test: `AdminMetricsServiceTest` — verificar cálculo de cada KPI
- [ ] KPIs en dashboard:
  - Entregas hoy (completadas / pendientes / excepciones)
  - Vehículos activos con posición en mapa
  - Rutas en progreso (con % completado)
  - Tasa de entrega exitosa (últimos 7/30 días)
  - Top 3 drivers de la semana
- [ ] Auto-refresh via Mercure SSE (sin polling)
- [ ] Test: verificar que Mercure publica updates cuando cambia un KPI

### 1.5 — Mapa de flota interactivo mejorado
**Qué:** Mapa con vehículos en tiempo real, rutas dibujadas, y estado visual
**Por qué:** "Wow factor" en demos — ver vehículos moverse en el mapa
**Impacto:** Demuestra tracking GPS + Mercure + route optimization visualmente
**Tareas:**
- [ ] Test: `FleetMapControllerTest` — endpoint devuelve posiciones + rutas
- [ ] Mejorar mapa existente:
  - Vehículos con iconos según tipo (furgoneta/camión/moto)
  - Color por estado (verde=en ruta, amarillo=idle, rojo=sin GPS)
  - Click en vehículo → popup con info (driver, ruta actual, próxima parada, ETA)
  - Línea de ruta dibujada con stops numerados
  - Trail de posiciones recientes (últimos 15 min)
- [ ] Simulación GPS integrada: botón "Demo mode" que activa `SimulateGpsCommand`
- [ ] Test: verificar que posiciones simuladas se publican via Mercure

---

## Fase 2: Inteligencia Artificial Activa ✅

> El AI/ML ya está scaffoldeado. Activarlo es el mayor ROI posible.
>
> **Estado:** Completada. 55 tests nuevos (304 total, 886 assertions). 2 bugs de producción corregidos.

### 2.1 — Activar clasificación de excepciones con Claude ✅
**Qué:** Cuando un driver reporta excepción, Claude analiza y clasifica automáticamente
**Por qué:** Insights automáticos sin intervención humana
**Impacto:** Operadores entienden patrones de fallo sin revisar cada excepción
**Tareas:**
- [x] Test: `ExceptionClassifierServiceTest` — 8 tests (clasificación válida, fallbacks, 9 subcategorías)
- [x] Test: `NlpClassificationHandlerTest` — 3 tests (clasificar+persistir, skip, merge payload)
- [x] `ClaudeLlmClient` ya es implementación por defecto (wired en services.yaml)
- [x] `NlpClassificationMessage` ya se dispatcha desde `DeliveryService:147`
- [x] Clasificación se almacena en `ShipmentEvent.payload['ai_classification']`
- [x] UI: clasificación AI en detalle de excepción (badge de subcategoría, confianza, insight, acción sugerida)

### 2.2 — Activar análisis post-ruta ✅
**Qué:** Al completar ruta, Claude analiza eficiencia y sugiere mejoras
**Por qué:** Aprendizaje continuo — cada ruta genera insights
**Impacto:** "Tu sistema aprende de cada entrega" — argumento de venta potente
**Tareas:**
- [x] Test: `PostRouteAnalyzerTest` — 6 tests (AI analysis, fallback stats, JSON inválido, markdown, low rate warning, origin exclusion)
- [x] Test: `PostRouteAnalysisHandlerTest` — 2 tests (analyze+persist, skip not found)
- [x] `PostRouteAnalysisListener` ya conectado al evento `RouteCompleted`
- [x] Análisis incluye: summary, planned_vs_actual, insights, recommendations
- [x] Almacenado en `Route.aiAnalysis` (JSON)
- [x] UI: sección "Análisis IA" ya existía en `analysis.html.twig` (summary, comparativa, insights, recomendaciones)
- [x] Fallback estadístico automático cuando Claude API no disponible

### 2.3 — Activar predicción de riesgo de entrega ✅
**Qué:** Antes de asignar shipment a ruta, predecir probabilidad de fallo
**Por qué:** Rutas más inteligentes — evitar enviar drivers a direcciones problemáticas
**Impacto:** Reduce tasa de excepciones, ahorra tiempo y combustible
**Tareas:**
- [x] Test: `DeliveryRiskServiceTest` — 6 tests (LOW/MEDIUM/HIGH, address boost +0.15, cap 1.0, ML fallback)
- [x] Test: `AddressRiskServiceTest` — 6 tests (few samples, low rate, high rate, threshold, no history, DB error)
- [x] **Bug fix:** `DeliveryRiskService` llamaba `isHighRisk()` en array → corregido a `$result['is_risky'] ?? false`
- [x] Score integrado en planificador de rutas (`RoutePlannerController`)
- [x] UI: badge de riesgo (Alto=rojo, Bajo=verde) con tooltip en planificador
- [x] Address risk boost de +0.15 cuando dirección tiene ≥30% excepciones en ≥3 entregas

### 2.4 — Activar embeddings para búsqueda semántica ✅
**Qué:** Buscar envíos por descripción natural ("el paquete frágil de la calle Serrano")
**Por qué:** Operadores buscan envíos rápido sin recordar referencias exactas
**Impacto:** Eficiencia operativa + demo impressionante
**Tareas:**
- [x] Test: `EmbeddingServiceTest` — 5 tests (embed+store, null/empty skip, search results, search failure)
- [x] Test: `SearchServiceTest` — 5 tests (empty/short/whitespace query, semantic fallback, exception handling)
- [x] `OpenAiEmbeddingClient` ya wired como default en services.yaml
- [x] `ShipmentEmbeddingListener` ya conectado al evento `ShipmentsImported`
- [x] Búsqueda semántica via pgvector (cosine distance) en endpoint `/api/search`
- [x] Fallback automático: si keyword devuelve <3 resultados, intenta semántica

### 2.5 — AI Assistant para operadores ✅
**Qué:** Chat con IA que responde preguntas sobre el estado operativo
**Por qué:** "Pregúntale a tu sistema" — interfaz natural para datos complejos
**Impacto:** Demo killer feature
**Tareas:**
- [x] Test: `AiAssistantServiceTest` — 4 tests (chat response, error handling, rate limit 20/min, empty response)
- [x] Test: `AiAssistantControllerTest` — 5 tests (valid input, empty/long/missing message, unavailable response)
- [x] **Bug fix:** `AiAssistantService.chat()` parámetro `$customerId` cambiado de `?int` a `?string` (Doctrine BIGINT)
- [x] 5 herramientas: search_shipments, get_delivery_report, get_route_details, get_active_alerts, get_exception_patterns
- [x] UI: `/admin/ai-assistant` con chat widget (ya existía)
- [x] Rate limiting: 20 req/min por usuario (in-memory timestamp buckets)

---

## Fase 3: Experiencia del Receptor

> La persona que recibe el paquete es el cliente de tu cliente. Su experiencia importa.

### 3.1 — Activar notificaciones SMS
**Qué:** SMS reales al receptor en momentos clave del delivery
**Por qué:** El receptor sabe cuándo llega su paquete sin abrir ninguna app
**Impacto:** Reduce "no estaba en casa" — el mayor coste de last-mile
**Tareas:**
- [ ] Test: `SmsChannelTest` — mock Twilio, verificar envío
- [ ] Configurar Twilio como `SmsProviderInterface` (requiere `TWILIO_*` env vars)
- [ ] Templates SMS ya existentes:
  - PreDelivery: "Tu paquete llega en ~15 min. Sigue aquí: {url}"
  - DeliveryCompleted: "Entregado a las {hora}. Valora: {url}"
  - RescheduleConfirmation: "Reprogramado para {fecha}. Confirma: {url}"
- [ ] Activar `ApproachingNotificationSubscriber` (trigger: vehículo a <500m)
- [ ] Test: vehículo entra en radio 500m → SMS enviado con URL válida
- [ ] Fallback: si Twilio falla, log warning pero no romper flujo

### 3.2 — Mejorar página de tracking público
**Qué:** Página rica con mapa, timeline, ETA en tiempo real
**Por qué:** El receptor ve el estado sin llamar al operador
**Impacto:** Profesionalismo + reduce llamadas de soporte
**Tareas:**
- [ ] Test: `PublicTrackingControllerTest` — token válido muestra info correcta
- [ ] Mejorar `/track/{token}`:
  - Mapa con posición del vehículo en tiempo real (Mercure SSE)
  - ETA actualizado automáticamente
  - Timeline visual: Created → In Transit → Out for Delivery → Delivered
  - Botón "Reprogramar" si la entrega aún no ha salido
  - Botón "Valorar" post-entrega (1-5 estrellas + comentario)
- [ ] Mobile-first: diseño responsive que funcione en móvil (es el canal principal)
- [ ] Test: tracking de shipment DELIVERED muestra POD info + botón valorar

### 3.3 — Sistema de delivery slots
**Qué:** El receptor elige ventana de entrega preferida
**Por qué:** "Elige cuándo recibirlo" — valor percibido alto
**Impacto:** Reduce intentos fallidos + mejora satisfacción
**Tareas:**
- [ ] Test: `DeliverySlotServiceTest` — verificar slots disponibles
- [ ] Activar `DeliverySlotService` ya existente
- [ ] UI en tracking público: selector de franjas horarias
- [ ] SMS de confirmación al seleccionar slot
- [ ] Integrar slot como time_window en VROOM optimization
- [ ] Test: slot seleccionado se refleja en RouteStop.deliveryWindow

---

## Fase 4: Eficiencia Operativa

> Operadores más eficientes = más entregas por día = más ingresos por cliente.

### 4.1 — Alertas automáticas en tiempo real
**Qué:** Alertas push cuando algo requiere atención inmediata
**Por qué:** Operador no puede vigilar cada vehículo — el sistema debe alertar
**Impacto:** Tiempo de respuesta a problemas: horas → segundos
**Tareas:**
- [ ] Test: `AlertServiceTest` — verificar creación de alertas por trigger
- [ ] Alertas implementadas:
  - Vehículo sin GPS hace >30 min (posible avería)
  - Ruta con >3 excepciones consecutivas (posible problema de zona)
  - Driver parado >20 min fuera de stop (posible incidencia)
  - Shipment con priority CRITICAL sin asignar a ruta
  - Ruta que excede duración estimada en >50%
- [ ] Notificación via Mercure SSE al dashboard
- [ ] Panel de alertas con filtros (activas/resueltas/tipo)
- [ ] Test: simular vehículo sin GPS 31 min → alerta creada

### 4.2 — Re-optimización automática ante excepciones
**Qué:** Cuando un stop falla, re-optimizar los stops pendientes automáticamente
**Por qué:** Si el driver no puede entregar en dirección A, la ruta cambia
**Impacto:** Sin intervención manual del operador para ajustar rutas
**Tareas:**
- [ ] Test: `ExceptionReoptimizationSubscriberTest` — excepción trigger re-opt
- [ ] Verificar que `ExceptionReoptimizationSubscriber` funciona correctamente
- [ ] Cuando `Route.autoReoptimize == true`:
  - Excepción → VROOM re-optimiza stops PENDING desde posición actual del driver
  - Notificar driver del nuevo orden
  - Registrar cambio en audit log
- [ ] UI: toggle "Auto-reoptimizar" en configuración de ruta
- [ ] Test: ruta con 5 stops, excepción en stop 2 → stops 3-5 reordenados

### 4.3 — Circuit breakers en providers
**Qué:** Si VROOM/OSRM/Google falla, fallback automático sin intervención
**Por qué:** Services externos caen — el sistema debe seguir funcionando
**Impacto:** Disponibilidad 99.9% en lugar de depender de uptime de terceros
**Tareas:**
- [ ] Test: `CircuitBreakerTest` — 3 fallos → circuito abierto → fallback
- [ ] Implementar circuit breaker pattern en `ProviderResolver`:
  - Closed: funciona normal
  - Open: 3+ fallos consecutivos → usar fallback chain
  - Half-open: probar primary cada 60s
- [ ] Fallback chain por servicio:
  - RouteOptimizer: VROOM → GreedyOptimizer
  - RoutingEngine: OSRM → Google → HaversineEngine
  - GpsProvider: Traccar → WebhookGpsProvider
- [ ] Métricas: contar fallos y switches por provider
- [ ] Test: VROOM timeout 3 veces → Greedy usado automáticamente

### 4.4 — Planificador de rutas inteligente mejorado
**Qué:** Sugerencias automáticas basadas en datos históricos
**Por qué:** Operador no sabe qué driver asignar a qué zona
**Impacto:** Mejores asignaciones = menos excepciones = más entregas exitosas
**Tareas:**
- [ ] Test: `DriverAffinityServiceTest` — verificar scoring de afinidad
- [ ] Activar `DriverAffinityService` + `DriverScoringService`
- [ ] En planificador de rutas:
  - Sugerir mejor driver por zona (basado en historial de éxito)
  - Mostrar score de cada driver (entregas exitosas, rating, excepciones)
  - Warning si driver no tiene skills necesarios
  - Predicción de duración basada en historial del driver en esa zona
- [ ] Test: driver con 95% éxito en zona norte → sugerido primero para zona norte

### 4.5 — Reportes avanzados exportables
**Qué:** Reportes detallados que el cliente puede descargar y presentar a su management
**Por qué:** El cliente necesita demostrar ROI a sus jefes
**Impacto:** Retención — si los reportes son buenos, no cambian de proveedor
**Tareas:**
- [ ] Test: `ReportingServiceTest` — verificar generación de reportes
- [ ] Reportes implementados:
  - Resumen semanal/mensual (entregas, excepciones, tiempo medio, coste estimado)
  - Rendimiento por driver (ranking con métricas detalladas)
  - Rendimiento por zona (tasas de éxito, tiempos medios)
  - Comparativa periodos (este mes vs anterior)
  - Análisis de excepciones (causas más frecuentes, zonas problemáticas)
- [ ] Formato: PDF + CSV export
- [ ] Schedulable: enviar por email cada lunes automáticamente
- [ ] Test: reporte semanal con 100 entregas genera PDF válido

---

## Fase 5: Robustez y Escala

> Preparar el sistema para crecer sin romperse.

### 5.1 — Test coverage al 70%+
**Qué:** Aumentar cobertura de tests de ~11% a 70%+ en áreas críticas
**Por qué:** "Tests obligatorios" — no se puede construir sin confianza en el código
**Impacto:** Velocidad de desarrollo futura + confianza en deploys
**Tareas:**
- [ ] Prioridad 1 — Services core (RouteBuilder, DeliveryService, TraccarIngestion):
  - Tests unitarios de cada método público
  - Tests de integración de flujos completos
- [ ] Prioridad 2 — Controllers (API v1, Driver API, Admin):
  - Tests funcionales con requests HTTP reales
  - Verificar respuestas, status codes, validación
- [ ] Prioridad 3 — Provider framework:
  - Tests de TenantAwareProxy, ProviderResolver, FallbackChain
  - Tests de cada Factory
- [ ] Prioridad 4 — Event subscribers:
  - Tests de cada subscriber con eventos simulados
  - Verificar side effects (notifications, re-optimization)
- [ ] Meta: `phpunit --coverage-text` muestra 70%+ en `src/`

### 5.2 — Encriptación de credenciales
**Qué:** Encriptar API keys en CustomerIntegration
**Por qué:** Deuda técnica crítica — antes de que clientes guarden sus keys
**Impacto:** Seguridad fundamental para producción
**Tareas:**
- [ ] Test: credencial encriptada en DB, desencriptada al leer
- [ ] Usar Symfony Secrets o sodium_crypto_secretbox
- [ ] Migración: encriptar credenciales existentes
- [ ] Test: dump de DB no revela API keys en plaintext
- [ ] Rotación de keys: soporte para re-encriptar con nueva master key

### 5.3 — Refactorizar GpsDeviceProviderInterface
**Qué:** Eliminar métodos Traccar-específicos de la interfaz genérica
**Por qué:** Deuda técnica que impide implementar nuevos GPS providers limpiamente
**Impacto:** Arquitectura limpia para crecimiento
**Tareas:**
- [ ] Test: WebhookGpsProvider funciona sin stubs de login/getSessionCookie
- [ ] Extraer `TraccarAuthInterface` con `login()` y `getSessionCookie()`
- [ ] `GpsDeviceProviderInterface` solo tiene: `getDevices()`, `createDevice()`, `getPositions()`, `isAvailable()`
- [ ] `TraccarGpsProvider` implements ambas interfaces
- [ ] Test: todos los providers existentes siguen funcionando

### 5.4 — Refactorizar Mercure listeners → RealtimePublisherInterface
**Qué:** Que todos los listeners usen el proxy TenantAwareRealtimePublisher
**Por qué:** Sin esto, HttpPollingPublisher no funciona para clientes sin Mercure
**Impacto:** Flexibilidad de deployment — clientes sin WebSocket
**Tareas:**
- [ ] Test: listener publica via TenantAwareRealtimePublisher, no HubInterface
- [ ] Refactorizar:
  - `TraccarIngestionService` → `RealtimePublisherInterface`
  - `NotificationService` → `RealtimePublisherInterface`
  - Cualquier otro listener que use `HubInterface` directamente
- [ ] Test: con HttpPollingPublisher configurado, eventos llegan via polling

### 5.5 — Monitoring y health checks
**Qué:** Endpoints de salud y métricas para monitorear en producción
**Por qué:** Sin monitoring, problemas se detectan cuando el cliente llama
**Impacto:** SLA compliance + respuesta proactiva a incidencias
**Tareas:**
- [ ] Test: `/health` devuelve status de cada componente
- [ ] Health check endpoint:
  - PostgreSQL: query simple
  - Redis: PING
  - Mercure: publish test event
  - Traccar: API availability
  - VROOM/OSRM: connectivity check
- [ ] Métricas expuestas (Prometheus format):
  - Entregas/hora, excepciones/hora
  - Tiempo de respuesta de APIs
  - Tamaño de colas Messenger
  - Errores por provider
- [ ] Test: componente caído → health check lo reporta

---

## Fase 6: Expansión de Negocio

> Nuevas funcionalidades que abren segmentos de mercado adicionales.

### 6.1 — Multi-warehouse support
**Qué:** Soportar múltiples almacenes/hubs por cliente
**Por qué:** Clientes grandes tienen múltiples centros de distribución
**Impacto:** Acceder a clientes enterprise (ticket más alto)
**Tareas:**
- [ ] Test: shipments asignados a diferentes warehouses, rutas salen de warehouse correcto
- [ ] Entidad `Warehouse` (name, address, coordinates, customer, capacity)
- [ ] Shipment → warehouse (FK)
- [ ] Route planning: respetar warehouse de origen de cada shipment
- [ ] UI: selector de warehouse en planificador
- [ ] Test: VROOM recibe vehicle start_location = warehouse correcto

### 6.2 — Billing y facturación automática
**Qué:** Generar facturas automáticas basadas en entregas realizadas
**Por qué:** Automatizar la cadena de valor completa (delivery → factura)
**Impacto:** Revenue directo + profesionalismo
**Tareas:**
- [ ] Test: `BillingServiceTest` — calcular factura desde entregas del mes
- [ ] Activar `BillingService` + `AccountingExportService`
- [ ] Modelos de pricing:
  - Por entrega (precio fijo por delivery exitosa)
  - Por km (distancia recorrida × tarifa)
  - Suscripción (cuota mensual + excesos)
- [ ] Generación PDF de factura
- [ ] Test: 100 entregas × 2.50€/entrega → factura de 250€

### 6.3 — API pública para integraciones de clientes
**Qué:** API v2 documentada con OpenAPI/Swagger para que clientes integren
**Por qué:** Clientes quieren conectar su ERP/WMS con mxo-track
**Impacto:** Stickiness — una vez integrado, difícil de cambiar
**Tareas:**
- [ ] Test: todos los endpoints v2 tienen documentación OpenAPI válida
- [ ] Swagger UI disponible en `/api/docs`
- [ ] Endpoints v2 cubren:
  - CRUD completo de shipments
  - Consulta de rutas y estado
  - Webhooks management
  - Tracking público
  - Reportes
- [ ] Versionado estricto (v2 no rompe v1)
- [ ] Rate limiting por API key
- [ ] Test: request malformado → error con link a docs

### 6.4 — Soporte para returns/devoluciones
**Qué:** Flujo de devolución: receptor → hub → cliente
**Por qué:** E-commerce tiene 20-30% de devoluciones
**Impacto:** Capturar el segmento de e-commerce logistics
**Tareas:**
- [ ] Test: shipment tipo RETURN sigue flujo inverso
- [ ] ServiceType ya tiene RETURN; implementar flujo completo:
  - Receptor solicita devolución (via tracking page)
  - Driver recoge en próxima ruta a la zona
  - Return shipment tracking hasta hub
- [ ] UI: panel de devoluciones pendientes
- [ ] Test: devolución completada → shipment status RETURNED

### 6.5 — App móvil nativa para drivers (o PWA)
**Qué:** Progressive Web App que funciona offline para drivers
**Por qué:** Drivers necesitan app fiable con GPS, cámara, firma en terreno
**Impacto:** Experiencia de driver → velocidad de delivery → satisfacción
**Tareas:**
- [ ] Evaluar PWA vs nativa (PWA recomendado: mismo codebase, installable)
- [ ] Service Worker para offline:
  - Cache de ruta asignada y stops
  - Queue de deliveries/exceptions cuando offline
  - Sync cuando recupera conexión
- [ ] Funcionalidades:
  - GPS background tracking
  - Cámara para fotos de entrega/excepción
  - Canvas para firma digital
  - Push notifications
- [ ] Test: entregar stop offline → sync cuando online → server refleja delivery

---

## Matriz de Priorización

| Fase | Impacto Negocio | Esfuerzo | ROI | Dependencias |
|------|-----------------|----------|-----|-------------|
| **0: Fundamentos** | Enabler | Bajo (1-2 sem) | ∞ | Ninguna |
| **1: Demo-Ready** | Alto | Medio (2-3 sem) | **Muy Alto** | Fase 0 |
| **2: AI Activa** | Alto | Medio (2-3 sem) | **Muy Alto** | Fase 0 + API keys |
| **3: Receptor** | Alto | Bajo (1-2 sem) | **Alto** | Fase 0 + Twilio |
| **4: Eficiencia** | Medio-Alto | Medio (2-3 sem) | **Alto** | Fase 0 |
| **5: Robustez** | Medio | Medio (2-3 sem) | **Medio** | Ninguna (parallelizable) |
| **6: Expansión** | Alto largo plazo | Alto (3-4 sem) | **Medio** | Fases 1-4 |

---

## Orden de Ejecución Recomendado

```
Semana 1-2:   Fase 0 (Fundamentos)
              └→ En paralelo: Fase 5.1 (Test coverage)

Semana 3-4:   Fase 1 (Demo-Ready)
              └→ Prioridad: 1.1 (demo flow) + 1.2 (CSV) + 1.3 (config pre-accept)

Semana 5-6:   Fase 2 (AI Activa)
              └→ Prioridad: 2.1 (exceptions) + 2.2 (post-route) + 2.5 (assistant)

Semana 7-8:   Fase 3 (Receptor) + Fase 4.1-4.2 (Alertas + Re-optimization)

Semana 9-10:  Fase 4.3-4.5 (Circuit breakers + Planificador + Reportes)
              └→ En paralelo: Fase 5.2-5.4 (Seguridad + Refactors)

Semana 11-14: Fase 6 (Expansión) — según feedback de primeros clientes
```

---

## Métricas de Éxito

| Métrica | Baseline | Target Post-Plan |
|---------|----------|-----------------|
| Test coverage | ~11% archivos | 70%+ en src/ |
| Demo setup time | Manual (30+ min) | 1 comando (2 min) |
| AI features activas | 0/6 | 6/6 |
| Notification channels activos | 2 (in-app, log) | 5 (+ SMS, WhatsApp, webhook) |
| Tiempo respuesta a incidencia | Detección manual | Alerta automática <30s |
| Provider fallback | Manual | Automático (circuit breaker) |
| Credenciales encriptadas | No | Sí |
| API documentada | No | Swagger UI + OpenAPI spec |

---

## Cómo Ejecutar Este Plan

Cada fase se descompone en sub-planes detallados siguiendo el **Skill 3: Writing Plans**:
1. Para cada fase, crear `docs/superpowers/plans/2026-MM-DD-phase-N-<nombre>.md`
2. Cada sub-plan tiene tareas TDD (test first → implement → verify)
3. Ejecutar con **Skill 4: Executing Plans** o **Skill 5: Subagent-Driven Development**
4. Review con **Skill 11** al completar cada fase

**Siguiente paso:** El usuario aprueba este plan maestro, y luego selecciona qué fase implementar primero.
