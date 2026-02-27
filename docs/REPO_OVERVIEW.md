# mxo-track — Explicación completa del repositorio y negocio

## El Negocio

**mxo-track** es una plataforma SaaS de logística de última milla. Resuelve el problema de empresas que necesitan **gestionar flotas de vehículos de reparto, planificar rutas de entrega, y dar visibilidad en tiempo real** tanto a sus operadores internos como a sus clientes finales.

**Problema que resuelve:** Una empresa de transporte/distribución tiene múltiples clientes, cada uno con envíos que deben entregarse en distintas direcciones. Necesitan:
1. Saber dónde están sus vehículos en tiempo real
2. Planificar rutas óptimas para minimizar kilometraje
3. Que los conductores confirmen entregas con prueba de entrega (POD)
4. Que los clientes puedan ver el estado de sus envíos
5. Reportes de rendimiento por conductor y por cliente

**Modelo de negocio multi-tenant:** Cada "Customer" (empresa cliente) ve solo sus datos. Los operadores/admins de la plataforma ven todo.

## Roles y Usuarios

| Rol | Qué hace | Qué ve |
|-----|----------|--------|
| **ADMIN** | Gestiona toda la plataforma: clientes, vehículos, conductores, rutas, importación CSV, reportes, facturación, mapa de flota | Todo |
| **OPERATOR** | Similar al admin pero sin acceso total | Panel de operaciones |
| **CUSTOMER** | Es el cliente de la empresa de transporte. Ve sus envíos, rutas activas, posición de vehículos asignados | Solo sus datos (filtro por `customer_id`) |
| **DRIVER** | Conductor. Ve sus rutas asignadas, marca entregas, reporta excepciones | Solo sus rutas asignadas |
| **Público (sin login)** | Destinatario final del paquete. Puede rastrear su envío con un token `TRK-XXXX-XXXX` | Solo el estado del envío + posición aproximada del vehículo |

## Flujo principal de negocio

```
1. IMPORTACIÓN   → Admin sube CSV con envíos (Shipments) de un cliente
2. PLANIFICACIÓN → Admin crea una Ruta, le asigna un conductor, un vehículo, y agrega paradas (RouteStop)
3. OPTIMIZACIÓN  → El sistema optimiza el orden de paradas (nearest-neighbor) para minimizar distancia
4. EJECUCIÓN     → El conductor arranca la ruta (PLANNED → ACTIVE), ve el mapa con sus paradas y ETAs
5. ENTREGA       → En cada parada, el conductor confirma la entrega con POD (nombre del receptor + ID codificado)
6. EXCEPCIÓN     → Si no puede entregar: marca excepción (AUSENTE, DIRECCIÓN INCORRECTA, RECHAZADO, DAÑADO, OTRO)
7. SEGUIMIENTO   → El cliente ve en tiempo real el progreso. El destinatario final puede rastrear con token público
8. CIERRE        → El conductor finaliza la ruta (ACTIVE → DONE)
9. REPORTING     → Reportes de rendimiento: entregas/excepciones por conductor, por cliente, tendencias
```

## Modelo de datos (Entidades)

```
Customer ──────── (Empresa cliente: nombre, dirección, teléfono, webhook URL)
  ├── User (ROLE_CUSTOMER) ── ve solo datos de su Customer
  ├── CustomerLocation ────── (Almacenes/sedes del cliente: origen de rutas)
  ├── CustomerVehicle ─────── (Relación N:M entre Customer y Vehicle)
  └── Shipment ────────────── (Envío: referencia, destinatario, dirección, coordenadas, tracking token)
       └── ShipmentEvent ──── (Eventos del ciclo de vida: CREATED → PICKED_UP → IN_TRANSIT → OUT_FOR_DELIVERY → DELIVERED/EXCEPTION)

Vehicle ───────── (Vehículo: nombre, traccarDeviceId para GPS)
  ├── VehiclePosition ─────── (Historial de posiciones GPS)
  ├── VehicleLastPosition ──── (Última posición conocida, para consulta rápida)
  └── VehicleCheckpoint ────── (Checkpoints de control)

Route ─────────── (Ruta: nombre, status [PLANNED/ACTIVE/DONE/CANCELLED], driver, vehicle, customer, origin)
  └── RouteStop ───────────── (Parada: secuencia, dirección, coordenadas, destinatario, status [PENDING/DELIVERED/EXCEPTION/SKIPPED], ventana de entrega)
       └── Pod ────────────── (Prueba de Entrega: nombre firmante, ID receptor codificado, confirmación del conductor)

User ──────────── (email, roles [ADMIN/CUSTOMER/DRIVER], customer opcional)
DriverAction ──── (Idempotencia: evita que un conductor ejecute la misma acción dos veces)
AuditLog ──────── (Auditoría: quién hizo qué, cuándo, con qué datos)
Notification ──── (Notificaciones in-app: tipo, título, mensaje, leída/no leída)
CsvImportRun ──── (Registro de importaciones CSV: cuántos creados, omitidos)
```

## Funcionalidades técnicas clave

### 1. Tracking GPS en tiempo real (Traccar + Mercure)
- Los vehículos tienen dispositivos GPS que reportan a **Traccar** (servidor open-source de tracking)
- `TraccarStreamCommand` hace polling a Traccar y guarda posiciones en PostgreSQL
- `TraccarIngestionService` procesa las posiciones y publica a **Mercure** (SSE) para actualizaciones en tiempo real
- El mapa de flota (`/fleet/map`) muestra vehículos moviéndose en vivo vía Server-Sent Events

### 2. Optimización de rutas
- `RouteOptimizationService` implementa el algoritmo nearest-neighbor (vecino más cercano)
- Calcula distancias con la fórmula de Haversine
- Puede hacer preview (ver el resultado) o aplicar directamente el nuevo orden
- Compara distancia total antes/después de optimizar

### 3. ETAs (Tiempo estimado de llegada)
- `EtaService` calcula ETAs para cada parada pendiente basándose en:
  - Posición actual del vehículo (o última conocida)
  - Velocidad promedio de 30 km/h
  - 2 minutos de parada por entrega
- Se muestra a conductores y en la API

### 4. Importación masiva (CSV)
- `ShipmentCsvImporter` procesa archivos CSV con columnas: reference, recipient_name, address, latitude, longitude, phone, notes
- Detecta duplicados por referencia, valida coordenadas
- `ImportRunTracker` registra cada importación

### 5. Multi-tenancy
- `CustomerTenantFilter` (filtro SQL de Doctrine) agrega automáticamente `WHERE customer_id = X` a todas las queries
- Solo se activa para ROLE_CUSTOMER y ROLE_DRIVER con customer asociado
- Admins/Operators lo bypasean

### 6. Webhooks
- `WebhookNotificationService` envía eventos a URLs configuradas por cliente
- Firma HMAC-SHA256 para verificación
- Los clientes pueden integrar sus sistemas con los eventos de mxo-track

### 7. Reporting y Billing
- `ReportingService`: entregas/excepciones por conductor, por cliente, tendencias semanales/mensuales, ranking de conductores
- `BillingService`: resumen facturable por cliente (envíos totales, entregados, excepciones)

### 8. Sistema de notificaciones y alertas
- `NotificationService` + `EmailNotificationService` para alertas
- `AlertService` detecta vehículos offline (sin señal GPS > 30 min) y rutas con demasiadas excepciones (> 3)

### 9. Tracking público
- Cada shipment genera un token `TRK-XXXX-XXXX`
- URL pública `/track/{token}` muestra timeline de eventos + posición aproximada del vehículo (anonimizada a ~500m)

### 10. Prueba de Entrega (POD)
- El conductor confirma cada entrega con:
  - Nombre del firmante (`signedByName`)
  - ID del receptor codificado (`recipientIdEncoded`)
  - Confirmación explícita del conductor (`confirmedByDriver`)
- `DeliveryEvidenceFactory` construye un registro forense con IP, User-Agent, timestamps
- `DriverAction` garantiza idempotencia (evita entregas duplicadas por reintentos de red)

### 11. Seguridad
- Sesiones en Redis con rate limiting (5 intentos de login)
- CSRF en todos los formularios
- Headers de seguridad (X-Frame-Options, CSP)
- `UserChecker` valida usuario activo antes de autenticar
- `AuditLogger` registra operaciones sensibles
- IDs públicos (ULID) en URLs, nunca IDs internos (BIGINT)

## Stack técnico

```
Backend:    PHP 8.4 + Symfony 7.4 LTS + Doctrine ORM 3.x
Base datos: PostgreSQL 16
Sesiones:   Redis 7
Realtime:   Mercure (SSE/Server-Sent Events)
GPS:        Traccar (servidor open-source)
Frontend:   Twig + Turbo (Hotwire)
Deploy:     Railway (Docker: php:8.4-cli-bookworm)
```

## Endpoints principales

| Área | Ruta | Descripción |
|------|------|-------------|
| Dashboard | `/` | Redirige según rol |
| Admin | `/admin/*` | CRUD completo: clientes, vehículos, conductores, rutas, envíos, reportes |
| Admin Importación | `/admin/shipments/import` | Subir CSV de envíos |
| Admin Facturación | `/admin/billing` | Resumen facturable por cliente |
| Mapa de flota | `/fleet/map` | Mapa en tiempo real con vehículos y rutas |
| Customer | `/customer/dashboard` | KPIs, rutas activas, posición de vehículos |
| Customer Rutas | `/customer/routes/*` | Ver rutas y paradas del cliente |
| Customer Envíos | `/customer/shipments/*` | Ver envíos del cliente |
| Driver Web | `/driver/routes` | Lista de rutas asignadas al conductor |
| Driver Web | `/driver/routes/{id}` | Detalle de ruta con mapa y ETAs |
| Driver API | `/api/driver/*` | API JSON para app móvil: rutas, iniciar/finalizar, entregar/excepción, POD, ETAs |
| Tracking público | `/track/{TRK-XXXX-XXXX}` | Página pública de seguimiento de envío |
| Fleet API | `/api/fleet/summary` | Resumen de flota activa |
| Mercure | `/api/mercure/token` | Token JWT para suscripción SSE |

## Estructura del proyecto

```
mxo-track/
├── backend/
│   ├── src/
│   │   ├── Command/          # Comandos CLI (Traccar stream, sync, simulación GPS)
│   │   ├── Controller/       # Controladores web y API
│   │   │   ├── Admin/        # Panel admin (rutas, clientes, vehículos, conductores, reportes)
│   │   │   ├── Customer/     # Portal del cliente
│   │   │   └── Driver/       # Vista web del conductor
│   │   ├── Dto/              # Data Transfer Objects con validación
│   │   ├── Entity/           # Entidades Doctrine (modelo de datos)
│   │   ├── Enum/             # Enums (RouteStatus, RouteStopStatus, ShipmentEventType, etc.)
│   │   ├── EventSubscriber/  # Listeners (filtro tenant, headers seguridad, etc.)
│   │   ├── Form/             # Formularios Symfony
│   │   ├── Repository/       # Repositorios Doctrine
│   │   ├── Security/         # UserChecker, TopicResolver, MercureTokenController
│   │   ├── Service/          # Lógica de negocio (tracking, optimización, ETAs, billing, etc.)
│   │   └── Validator/        # Validadores custom
│   ├── templates/            # Plantillas Twig
│   ├── migrations/           # Migraciones Doctrine
│   └── config/               # Configuración Symfony
├── docs/                     # Documentación
└── docker-compose.local.yml  # Entorno local (app, db, redis, mercure, traccar)
```
