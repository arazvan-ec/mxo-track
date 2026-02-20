# Plan: mxo-track Phase 4 — CRUDs, UI Premium, Realtime Full-Stack

## Estado actual del repo

**Base sólida (70% cubierto):**
- 13 entidades (User, Customer, Vehicle, Route, RouteStop, Shipment, Pod, etc.)
- APIs funcionales (Driver, Vehicle, Shipment, Fleet Map)
- Multi-tenant con Doctrine SQL filter
- Traccar integrado (API client, ingestion, streaming)
- Mercure SSE para GPS en vivo
- Seguridad con 4 roles (ADMIN, OPERATOR, CUSTOMER, DRIVER)
- Docker: PHP 8.4, PostgreSQL 16, Redis 7, Mercure, Traccar

**Lo que FALTA (este plan):**
- CRUDs completos de admin (Vehicles, Drivers, Routes, Customers)
- Campos lat/lng en RouteStop y enriquecimiento del CSV import
- Design system (actualmente HTML sin CSS)
- Dashboard del almacén (CUSTOMER) con seguimiento en vivo
- Mapa con puntos de entrega + vehículo
- Nginx como reverse proxy
- Turbo Frames/Streams para UX reactiva

---

## Decisiones de diseño

### 1. Design System: Tailwind CSS via CDN + Alpine.js
- **Tailwind CSS 3.x via CDN (Play CDN)** — sin build step, compatible con AssetMapper
- **Alpine.js** — interactividad ligera (modals, dropdowns, toggles) sin compilar
- **Palette**: Slate/gray base + Blue-600 primary + Emerald success + Amber warning + Rose danger
- **Layout**: Sidebar colapsable + topbar con breadcrumbs + content area
- Responsive mobile-first (drivers usan móvil)

### 2. CRUDs: Symfony Forms + Turbo Frames
- Cada CRUD tiene: **listado** (tabla paginada) + **crear/editar** (modal Turbo Frame) + **eliminar** (confirm dialog)
- Listados con búsqueda inline, ordenación por columnas, y paginación
- Formularios se abren en **Turbo Frames** (sin recarga de página)
- Flash messages como **toast notifications** (Turbo Stream append)

### 3. CSV Import enriquecido
- Columnas: `reference, recipient_name, address, latitude, longitude, phone, notes`
- Crea Shipment + RouteStop prellenado con coords
- Preview del CSV antes de confirmar import
- Geocoding opcional futuro (fuera de scope)

### 4. Mapa mejorado
- Leaflet con vehicle marker animado (movimiento suave)
- Delivery points como markers coloreados por estado (pending=blue, delivered=green, exception=red)
- Cluster de markers cuando hay muchos puntos
- Panel lateral con lista de paradas y estado en vivo
- Turbo Stream para actualizar estado de paradas sin recargar

### 5. Dashboard del almacén (CUSTOMER)
- KPIs: entregas pendientes, completadas, excepciones, % completado
- Mapa con sus vehículos y puntos de entrega
- Lista de rutas activas con progreso
- Actualizaciones en vivo vía Mercure (estado de entregas)

### 6. Nginx
- Contenedor separado como reverse proxy
- PHP-FPM en lugar de built-in server
- Configuración para servir assets estáticos directamente

---

## Plan de implementación por fases

### FASE 4A: Infraestructura UI + Nginx (base para todo lo demás)

#### 4A.1 — Nginx container + PHP-FPM
- **Archivo**: `docker-compose.local.yml` — añadir servicio `nginx`
- **Archivo**: `infra/docker/nginx/default.conf` — reverse proxy a PHP-FPM
- **Archivo**: `infra/docker/Dockerfile` — cambiar de `php:8.4-cli` a `php:8.4-fpm-bookworm`
- **Impacto**: El servicio `app` pasa a ser PHP-FPM, nginx escucha en puerto 8000
- **Test**: `curl http://localhost:8000` devuelve la página de login

#### 4A.2 — Design system base (Tailwind + Alpine + Layout)
- **Archivo**: `templates/base.html.twig` — reescribir con Tailwind CDN, Alpine.js, layout sidebar
- **Archivo**: `templates/_partials/sidebar.html.twig` — navegación lateral por rol
- **Archivo**: `templates/_partials/topbar.html.twig` — breadcrumbs + user menu
- **Archivo**: `templates/_partials/flash_toasts.html.twig` — toast notifications con Turbo Stream
- **Archivo**: `templates/_partials/modal_frame.html.twig` — Turbo Frame container para modals
- **Archivo**: `templates/_partials/pagination.html.twig` — componente de paginación reutilizable
- **Archivo**: `templates/_partials/confirm_delete.html.twig` — dialog de confirmación Alpine.js
- **Archivo**: `templates/_partials/empty_state.html.twig` — ilustraciones para tablas vacías
- **Impacto**: Todas las páginas heredan el nuevo layout automáticamente
- **Test**: Login se ve con el nuevo diseño, sidebar muestra opciones según rol

#### 4A.3 — Turbo + Stimulus setup real
- **Archivo**: `assets/controllers/modal_controller.js` — Stimulus controller para abrir/cerrar modals
- **Archivo**: `assets/controllers/toast_controller.js` — auto-dismiss de toasts
- **Archivo**: `assets/controllers/search_controller.js` — búsqueda con debounce en listados
- **Archivo**: `assets/controllers/confirm_controller.js` — confirmación de acciones destructivas
- **Archivo**: `assets/controllers.json` — registrar los controllers
- **Test**: Modal se abre/cierra, toasts aparecen y desaparecen

---

### FASE 4B: Schema + Migraciones (prerequisito para CRUDs)

#### 4B.1 — Enriquecer RouteStop con coordenadas
- **Archivo**: `src/Entity/RouteStop.php` — añadir `latitude` (float, nullable), `longitude` (float, nullable), `recipientName` (string, nullable), `recipientPhone` (string, nullable), `notes` (text, nullable)
- **Archivo**: `migrations/Version20260219_RouteStopCoords.php` — ALTER TABLE route_stop ADD COLUMN
- **Impacto**: RouteStop ahora tiene datos de geolocalización para mostrar en mapa

#### 4B.2 — Enriquecer Shipment con datos de entrega
- **Archivo**: `src/Entity/Shipment.php` — añadir `recipientName`, `recipientPhone`, `address`, `latitude`, `longitude`, `notes`
- **Archivo**: `migrations/Version20260219_ShipmentEnrich.php` — ALTER TABLE shipment
- **Impacto**: Shipment tiene toda la info que llega en el CSV

#### 4B.3 — Enriquecer Customer con datos de almacén
- **Archivo**: `src/Entity/Customer.php` — añadir `address`, `contactEmail`, `contactPhone`, `isActive`
- **Archivo**: `migrations/Version20260219_CustomerEnrich.php` — ALTER TABLE customer
- **Impacto**: Customer funciona como "almacén" completo

---

### FASE 4C: CRUDs Admin (EN PARALELO)

> Todos estos CRUDs siguen el mismo patrón: Controller + FormType + Templates (list, _form, _row)
> Se implementan en paralelo porque son independientes entre sí.

#### 4C.1 — CRUD Vehículos (`/admin/vehicles`)
- **Controller**: `src/Controller/Admin/VehicleAdminController.php`
  - `GET /admin/vehicles` — listado paginado con búsqueda
  - `GET /admin/vehicles/new` — form en Turbo Frame (modal)
  - `POST /admin/vehicles/new` — crear vehículo
  - `GET /admin/vehicles/{publicId}/edit` — form en Turbo Frame
  - `POST /admin/vehicles/{publicId}/edit` — actualizar
  - `DELETE /admin/vehicles/{publicId}` — soft-delete (isActive=false)
- **Form**: `src/Form/VehicleType.php` — name, traccarDeviceId, isActive
- **Templates**:
  - `templates/admin/vehicle/index.html.twig` — tabla con nombre, deviceId, estado, última posición, acciones
  - `templates/admin/vehicle/_form.html.twig` — formulario Turbo Frame
  - `templates/admin/vehicle/_row.html.twig` — fila de tabla (Turbo Frame para actualización inline)
- **Funcionalidad extra**: Botón "Crear dispositivo en Traccar" que llama a TraccarApiClient::createDevice()

#### 4C.2 — CRUD Transportistas (`/admin/drivers`)
- **Controller**: `src/Controller/Admin/DriverAdminController.php`
  - `GET /admin/drivers` — listado
  - `GET/POST /admin/drivers/new` — crear driver (User con ROLE_DRIVER)
  - `GET/POST /admin/drivers/{publicId}/edit` — editar
  - `DELETE /admin/drivers/{publicId}` — desactivar (isActive=false)
- **Form**: `src/Form/DriverType.php` — email, plainPassword (solo al crear), customer, isActive
- **Templates**:
  - `templates/admin/driver/index.html.twig` — tabla con email, almacén asignado, estado, rutas activas
  - `templates/admin/driver/_form.html.twig`
  - `templates/admin/driver/_row.html.twig`
- **Extra**: Al crear, hash password automáticamente via UserPasswordHasherInterface

#### 4C.3 — CRUD Rutas (`/admin/routes`)
- **Controller**: `src/Controller/Admin/RouteAdminController.php`
  - `GET /admin/routes` — listado con filtro por estado
  - `GET/POST /admin/routes/new` — crear ruta
  - `GET/POST /admin/routes/{publicId}/edit` — editar ruta + paradas
  - `DELETE /admin/routes/{publicId}` — cancelar ruta (status=CANCELLED)
  - `POST /admin/routes/{publicId}/stops/add` — añadir parada
  - `DELETE /admin/routes/{publicId}/stops/{stopPublicId}` — quitar parada
- **Form**: `src/Form/RouteType.php` — name, vehicle (select), driver (select), startAt, endAt
- **Form**: `src/Form/RouteStopType.php` — address, latitude, longitude, recipientName, recipientPhone, sequence, shipment (select opcional)
- **Templates**:
  - `templates/admin/route/index.html.twig` — tabla con nombre, vehículo, driver, estado, progreso (delivered/total)
  - `templates/admin/route/edit.html.twig` — formulario de ruta + lista de paradas con drag-to-reorder
  - `templates/admin/route/_stop_row.html.twig` — fila de parada con estado
- **Extra**: Vista de mapa inline con preview de la ruta (Leaflet minimap mostrando los puntos)

#### 4C.4 — CRUD Almacenes/Clientes (`/admin/customers`)
- **Controller**: `src/Controller/Admin/CustomerAdminController.php`
  - `GET /admin/customers` — listado
  - `GET/POST /admin/customers/new` — crear almacén
  - `GET/POST /admin/customers/{publicId}/edit` — editar
  - `POST /admin/customers/{publicId}/vehicles` — asignar vehículos (CustomerVehicle)
  - `DELETE /admin/customers/{publicId}` — desactivar
- **Form**: `src/Form/CustomerType.php` — name, address, contactEmail, contactPhone, isActive
- **Templates**:
  - `templates/admin/customer/index.html.twig` — tabla con nombre, contacto, vehículos asignados, usuarios
  - `templates/admin/customer/_form.html.twig`
  - `templates/admin/customer/vehicles.html.twig` — gestión de vehículos asignados (checkboxes)

#### 4C.5 — CRUD Usuarios del almacén (`/admin/users`)
- **Controller**: `src/Controller/Admin/UserAdminController.php`
  - `GET /admin/users` — listado de usuarios CUSTOMER
  - `GET/POST /admin/users/new` — crear usuario de almacén
  - `GET/POST /admin/users/{publicId}/edit` — editar
  - `DELETE /admin/users/{publicId}` — desactivar
- **Form**: `src/Form/CustomerUserType.php` — email, plainPassword, customer (select), isActive
- **Templates**: Mismo patrón que drivers

---

### FASE 4D: CSV Import enriquecido

#### 4D.1 — Refactor CSV importer
- **Archivo**: `src/Service/ShipmentCsvImporter.php` — refactorizar para aceptar columnas enriquecidas
  - Columnas: `reference, recipient_name, address, latitude, longitude, phone, notes`
  - Crear Shipment con todos los campos
  - Opcionalmente crear RouteStop prellenado si se pasa una Route
- **Archivo**: `src/Dto/CsvShipmentRow.php` — DTO para validar cada fila del CSV
- **Test**: CSV con 7 columnas se importa correctamente

#### 4D.2 — UI de import mejorada
- **Archivo**: `templates/admin/shipments_import.html.twig` — rediseñar
  - Step 1: Subir CSV + seleccionar cliente
  - Step 2: Preview en tabla con validación visual (filas rojas si hay errores)
  - Step 3: Confirmar import
  - Step 4: Resultado con contadores
- **Extra**: Drag & drop para el CSV, progress bar durante import

---

### FASE 4E: Dashboard del almacén (CUSTOMER)

#### 4E.1 — Dashboard principal del almacén
- **Controller**: `src/Controller/Customer/CustomerDashboardController.php`
  - `GET /dashboard` — KPIs + rutas activas + últimas entregas
- **Templates**:
  - `templates/customer/dashboard.html.twig` — Grid de KPIs, lista de rutas activas con progreso
  - Turbo Frames para actualización parcial
- **KPIs**: Entregas pendientes, completadas hoy, excepciones, % completado, vehículos en ruta

#### 4E.2 — Vista de seguimiento de rutas (almacén)
- **Controller**: `src/Controller/Customer/CustomerRouteController.php`
  - `GET /routes` — rutas del almacén
  - `GET /routes/{publicId}` — detalle de ruta con mapa + lista de paradas
- **Templates**:
  - `templates/customer/route/index.html.twig` — lista de rutas con estado
  - `templates/customer/route/show.html.twig` — mapa con vehículo + puntos de entrega + panel de paradas
- **Realtime**: Mercure SSE para actualización de posición del vehículo Y estado de paradas

#### 4E.3 — Vista de envíos del almacén
- **Controller**: `src/Controller/Customer/CustomerShipmentController.php`
  - `GET /shipments` — listado filtrable por estado
  - `GET /shipments/{publicId}` — detalle con timeline de eventos
- **Templates**:
  - `templates/customer/shipment/index.html.twig` — tabla con referencia, destinatario, estado, última actualización
  - `templates/customer/shipment/show.html.twig` — timeline visual de eventos

---

### FASE 4F: Mapa mejorado

#### 4F.1 — Mapa fleet con puntos de entrega
- **Refactor**: `templates/tracking/map.html.twig` — reescribir
  - Markers de vehículos con icono personalizado (truck icon)
  - Markers de delivery points coloreados por estado
  - Popups con info de la parada (destinatario, dirección, estado)
  - Panel lateral colapsable con lista de paradas
  - Filtro por ruta
- **API**: `src/Controller/Api/RouteStopApiController.php`
  - `GET /api/routes/{publicId}/stops` — stops con coords para el mapa (público con scope)

#### 4F.2 — Animación de vehículo
- **Archivo**: `assets/controllers/vehicle_marker_controller.js` — Stimulus controller
  - Movimiento suave del marker (interpolación entre posiciones)
  - Trail line (últimas N posiciones)
  - Rotación del icono según heading/course

#### 4F.3 — Realtime estado de paradas
- **Mercure topic nuevo**: `/routes/{publicId}/stops` — publica cuando un driver marca delivered/exception
- **Refactor**: `src/Controller/DriverApiController.php` — publicar a Mercure al marcar entrega
- **Frontend**: Turbo Stream actualiza el color del marker + el panel lateral

---

### FASE 4G: Realtime full-stack

#### 4G.1 — Mercure topics para entregas
- **Publicar** a `/customers/{id}/deliveries` cuando se marca delivered/exception
- **Publicar** a `/routes/{publicId}/progress` con % completado
- Actualizar `TraccarIngestionService` para publicar a `/operator/fleet` (ya existe)

#### 4G.2 — Turbo Streams en dashboards
- Admin dashboard: KPIs se actualizan en vivo
- Customer dashboard: entregas y progreso de rutas en vivo
- Toasts de notificación cuando se completa una entrega

---

## Orden de ejecución recomendado

```
FASE 4A (Infra UI + Nginx)     ──────────────────> Base para todo
    │
    ├── FASE 4B (Schema)        ──────────────────> Prerequisito para CRUDs
    │       │
    │       ├── FASE 4C.1 (CRUD Vehículos)    ┐
    │       ├── FASE 4C.2 (CRUD Drivers)       │
    │       ├── FASE 4C.3 (CRUD Rutas)         ├── EN PARALELO
    │       ├── FASE 4C.4 (CRUD Almacenes)     │
    │       └── FASE 4C.5 (CRUD Usuarios)      ┘
    │
    ├── FASE 4D (CSV Import)    ──────────────────> Tras schema
    │
    ├── FASE 4E (Dashboard CUSTOMER) ─────────────> Tras CRUDs
    │
    ├── FASE 4F (Mapa mejorado) ──────────────────> Tras schema + dashboard
    │
    └── FASE 4G (Realtime full) ──────────────────> Tras todo lo anterior
```

## Archivos nuevos estimados: ~45
## Archivos modificados estimados: ~15
## Migraciones nuevas: 3

---

## Riesgos y mitigaciones

| Riesgo | Mitigación |
|--------|------------|
| Tailwind CDN pesa ~300KB | Play CDN solo para dev; en prod se compila con CLI |
| PHP built-in server → FPM cambia el flujo | Mantener ambas opciones en docker-compose |
| RouteStop sin coords legacy | Campos nullable, backwards compatible |
| Muchos archivos en paralelo | Cada CRUD es independiente, no hay conflictos |

---

## Criterios de éxito

1. Admin ve sidebar con: Dashboard, Vehículos, Transportistas, Rutas, Almacenes, Usuarios, Import CSV
2. Cada CRUD funciona: listar, crear (modal), editar, eliminar/desactivar
3. CSV import acepta 7 columnas y muestra preview
4. Almacén ve dashboard con KPIs y mapa con sus vehículos + puntos de entrega
5. Mapa muestra vehículo moviéndose + delivery points coloreados por estado
6. Estado de entregas se actualiza en vivo (Mercure) en todos los perfiles
7. Nginx sirve la aplicación en puerto 8000
8. Diseño profesional con Tailwind + transiciones suaves
