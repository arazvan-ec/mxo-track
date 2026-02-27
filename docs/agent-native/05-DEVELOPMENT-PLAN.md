# Plan de Desarrollo — MXO-Track Agent-Native

> Fecha: 2026-02-27
> Estado: Borrador v1 — Pendiente de aprobación

---

## Visión General de Fases

```
FASE 0: Fundamentos del Dominio (modelo de datos extendido)
   ↓
FASE 1: Tools Atómicos Core (CRUD + lógica de negocio)
   ↓
FASE 2: Optimización + Geo (isócronas, rutas, capacidad)
   ↓
FASE 3: Agent Layer (orquestador, contexto, prompts)
   ↓
FASE 4: Notificaciones + Cliente B2B Portal
   ↓
FASE 5: Dashboard + Analytics + Costes
   ↓
FASE 6: SGA + Documentos (albaranes, etiquetas)
```

---

## FASE 0: Fundamentos del Dominio
**Objetivo:** Extender el modelo de datos para soportar bultos, tipos de servicio, capacidad de vehículos y categorización de clientes.

### Tareas

| ID | Tarea | Estado | Prioridad |
|----|-------|--------|-----------|
| F0-01 | Crear entidad `Parcel` (bulto) con peso, volumen, EAN, descripción, estado | ⬜ Pendiente | Alta |
| F0-02 | Crear enum `ServiceType` (DELIVERY, DELIVERY_PICKUP, RETURN) | ⬜ Pendiente | Alta |
| F0-03 | Crear enum `ParcelStatus` (PENDING, LOADED, IN_ROUTE, DELIVERED, RETURNED, ABSENT, EXCEPTION) | ⬜ Pendiente | Alta |
| F0-04 | Extender `Vehicle` con max_weight_kg, max_volume_m3, license_plate, vehicle_type | ⬜ Pendiente | Alta |
| F0-05 | Extender `Shipment` con service_type, total_parcels, estimated_delivery_date, preferred_time_window | ⬜ Pendiente | Alta |
| F0-06 | Extender `Customer` con email, frequency_category, preferred_delivery_time, notification_prefs | ⬜ Pendiente | Alta |
| F0-07 | Crear migraciones Doctrine para todos los cambios | ⬜ Pendiente | Alta |
| F0-08 | Actualizar fixtures con datos de ejemplo | ⬜ Pendiente | Media |
| F0-09 | Actualizar CSV importer para nuevas columnas (peso, volumen, tipo servicio) | ⬜ Pendiente | Alta |

**Entregable:** Modelo de datos completo, migraciones, fixtures.
**Dependencia:** Respuestas a Q1, Q2.

---

## FASE 1: Tools Atómicos Core
**Objetivo:** Crear la capa de servicios atómicos que los agentes (y la UI) consumirán.

### Tareas

| ID | Tarea | Estado | Prioridad |
|----|-------|--------|-----------|
| F1-01 | Crear `ShipmentToolService` (create, add_parcel, update_status, get_status, search) | ⬜ Pendiente | Alta |
| F1-02 | Crear `RouteToolService` (create, add_stop, start, finish, validate_load) | ⬜ Pendiente | Alta |
| F1-03 | Crear `VehicleToolService` (list, check_capacity, assign, get_position) | ⬜ Pendiente | Alta |
| F1-04 | Crear `CustomerToolService` (get_profile, get_frequency, set_preferences) | ⬜ Pendiente | Media |
| F1-05 | Crear `CapacityValidationService` — validar peso/volumen vs vehículo | ⬜ Pendiente | Alta |
| F1-06 | Crear endpoints REST para cada tool (JSON in/out) | ⬜ Pendiente | Alta |
| F1-07 | Tests unitarios para cada tool service | ⬜ Pendiente | Alta |
| F1-08 | Documentación OpenAPI (Swagger) de cada endpoint | ⬜ Pendiente | Media |

**Entregable:** API de tools atómicos funcionando con tests.
**Dependencia:** Fase 0 completa.

---

## FASE 2: Optimización + Geo
**Objetivo:** Optimizador de rutas avanzado con isócronas, validación de capacidad y generación automática de rutas.

### Tareas

| ID | Tarea | Estado | Prioridad |
|----|-------|--------|-----------|
| F2-01 | Integrar OpenRouteService isochrones API | ⬜ Pendiente | Alta |
| F2-02 | Implementar estrategia "farthest-first" en RouteOptimizationService | ⬜ Pendiente | Alta |
| F2-03 | Implementar agrupación por isócronas (RGU) | ⬜ Pendiente | Alta |
| F2-04 | Crear `AutoRouteCreationService` — de N shipments a M rutas automáticas | ⬜ Pendiente | Alta |
| F2-05 | Integrar validación de capacidad en la creación de rutas | ⬜ Pendiente | Alta |
| F2-06 | Integrar ventanas de tiempo en la optimización | ⬜ Pendiente | Media |
| F2-07 | Crear tool `calculate_isochrone` | ⬜ Pendiente | Media |
| F2-08 | Crear tool `auto_create_routes` (composición de tools) | ⬜ Pendiente | Alta |
| F2-09 | Tests con datos reales (800 pedidos de "Raúl") | ⬜ Pendiente | Media |

**Entregable:** Optimizador que crea rutas automáticas con validación de capacidad.
**Dependencia:** Fase 1 completa, Respuesta a Q6, Q12.

---

## FASE 3: Agent Layer
**Objetivo:** Capa de agente IA que orquesta tools atómicos para resolver peticiones en lenguaje natural.

### Tareas

| ID | Tarea | Estado | Prioridad |
|----|-------|--------|-----------|
| F3-01 | Diseñar Agent Context System (Context.md pattern) | ⬜ Pendiente | Alta |
| F3-02 | Crear `AgentOrchestrator` — recibe petición, descompone en tools, ejecuta | ⬜ Pendiente | Alta |
| F3-03 | Definir tool schemas (JSON Schema para input/output de cada tool) | ⬜ Pendiente | Alta |
| F3-04 | Integrar con LLM (Claude API) para razonamiento | ⬜ Pendiente | Alta |
| F3-05 | Crear sistema de prompts por dominio (rutas, envíos, vehículos) | ⬜ Pendiente | Alta |
| F3-06 | Implementar completion signals (éxito, fallo, continuación) | ⬜ Pendiente | Media |
| F3-07 | Crear contexto acumulado por cliente/transportista/zona | ⬜ Pendiente | Media |
| F3-08 | Tests de integración: flujo 800 pedidos end-to-end | ⬜ Pendiente | Alta |
| F3-09 | Tests de emergencia: "camión averiado, reorganizar" | ⬜ Pendiente | Media |

**Entregable:** Agente funcional que procesa peticiones complejas.
**Dependencia:** Fase 2 completa, Respuesta a Q4.

---

## FASE 4: Notificaciones + Portal B2B
**Objetivo:** Sistema de notificaciones multi-canal y portal para clientes B2B.

### Tareas

| ID | Tarea | Estado | Prioridad |
|----|-------|--------|-----------|
| F4-01 | Extender NotificationService para estados de bulto/entrega | ⬜ Pendiente | Alta |
| F4-02 | Implementar notificaciones por email (ya existe base) | ⬜ Pendiente | Alta |
| F4-03 | Implementar notificaciones webhook (ya existe base) | ⬜ Pendiente | Alta |
| F4-04 | Implementar notificaciones SMS (requiere proveedor) | ⬜ Pendiente | Media |
| F4-05 | Portal B2B: vista de estados de entregas para el cliente | ⬜ Pendiente | Alta |
| F4-06 | Portal B2B: propuesta de fecha y franja horaria | ⬜ Pendiente | Media |
| F4-07 | Crear tools de notificación para el agente | ⬜ Pendiente | Alta |

**Entregable:** Notificaciones automáticas + portal cliente.
**Dependencia:** Fase 1, Respuesta a Q7.

---

## FASE 5: Dashboard + Analytics + Costes
**Objetivo:** Dashboard con métricas de costes, productividad y optimización.

### Tareas

| ID | Tarea | Estado | Prioridad |
|----|-------|--------|-----------|
| F5-01 | Dashboard principal con KPIs (€/ruta, €/bulto, entregas/día) | ⬜ Pendiente | Alta |
| F5-02 | Métricas de productividad por transportista | ⬜ Pendiente | Alta |
| F5-03 | Métricas por zona (RGU compliance) | ⬜ Pendiente | Media |
| F5-04 | Categorización automática de clientes por frecuencia | ⬜ Pendiente | Media |
| F5-05 | Crear tools de analytics para el agente | ⬜ Pendiente | Alta |
| F5-06 | Exportar reportes (PDF, CSV) | ⬜ Pendiente | Media |

**Entregable:** Dashboard operativo con métricas de negocio.
**Dependencia:** Fase 1, Respuesta a Q3, Q10.

---

## FASE 6: SGA + Documentos
**Objetivo:** Módulo de almacén simplificado y generación de documentos (albaranes, etiquetas).

### Tareas

| ID | Tarea | Estado | Prioridad |
|----|-------|--------|-----------|
| F6-01 | Módulo de entrada de mercancía en almacén | ⬜ Pendiente | Alta |
| F6-02 | Generador de albaranes (PDF) | ⬜ Pendiente | Alta |
| F6-03 | Generador de etiquetas para bultos | ⬜ Pendiente | Media |
| F6-04 | Numeración secuencial de albaranes | ⬜ Pendiente | Media |
| F6-05 | Crear tools de documentos para el agente | ⬜ Pendiente | Alta |

**Entregable:** Documentos generados automáticamente, módulo SGA básico.
**Dependencia:** Fase 1, Respuesta a Q5, Q9.

---

## Cronograma Estimado (por fases)

```
Fase 0 ████████                    (Fundamentos)
Fase 1          ████████████       (Tools Core)
Fase 2                    ████████████  (Optimización)
Fase 3                              ████████████ (Agent Layer)
Fase 4              ████████████                 (Notificaciones — paralelo con F2)
Fase 5                        ████████████       (Dashboard — paralelo con F3)
Fase 6                                    ████████ (SGA/Docs — al final)
```

**Nota:** Las fases 4 y 5 pueden avanzar en paralelo con 2 y 3 respectivamente, ya que son módulos independientes que comparten la base de Fase 1.

---

## Criterios de Éxito por Fase

| Fase | Criterio |
|------|----------|
| 0 | Modelo de datos pasa `doctrine:schema:validate`, fixtures cargadas |
| 1 | Todos los tools devuelven JSON válido, tests pasan |
| 2 | 800 pedidos → X rutas en < 30s, cada ruta valida capacidad |
| 3 | "Crea rutas para los pedidos de Raúl" → funciona end-to-end |
| 4 | Cliente recibe notificación en cada cambio de estado |
| 5 | Dashboard muestra €/ruta y €/bulto en tiempo real |
| 6 | Albarán PDF generado con toda la información legal |
