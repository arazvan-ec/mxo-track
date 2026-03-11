# GPS Tracking (Traccar)

**Última actualización:** 2026-03-11
**Estado:** Vigente

## Arquitectura

Dos providers de GPS disponibles:

| Provider | Cómo funciona | Infraestructura |
|----------|--------------|-----------------|
| **Traccar** | Polling de la API REST de Traccar | Servidor Traccar + BD dedicada |
| **Webhook** | Push-based, recibe posiciones via HTTP | Ninguna |

## Traccar Integration

### Componentes

| Componente | Responsabilidad |
|------------|----------------|
| `TraccarApiClient` | HTTP client para Traccar REST API (devices, positions, createDevice) |
| `TraccarStreamCommand` | `app:traccar:stream` — polling de posiciones con backfill (--once, --sleep=5) |
| `TraccarSyncDevicesCommand` | `app:traccar:sync-devices` — sincroniza devices Traccar → Vehicle entities |
| `TraccarIngestionService` | Procesa y almacena posiciones en VehiclePosition + VehicleLastPosition |
| `SimulateGpsCommand` | `app:dev:simulate-gps` — simula GPS para desarrollo |

### Inicialización (Primer Arranque)

Traccar 6.x arranca con DB vacía y **sin usuario admin**. `GET /api/server` → `"newServer": true`.

```bash
# Desde dentro del contenedor app:
curl -s -X POST 'http://traccar:8082/api/users' \
  -H 'Content-Type: application/json' \
  -d '{"name":"admin","email":"admin","password":"admin"}'
```

- El primer usuario creado recibe `administrator: true` automáticamente
- **NO incluir** campo `"administrator"` en el JSON (causa NullPointerException)
- Si se borra el volumen `traccar_data`, repetir este paso

### Flujo de Ingesta

```
Traccar (dispositivos GPS) → TraccarStreamCommand (polling) → TraccarIngestionService
  → VehiclePosition (histórico)
  → VehicleLastPosition (cache desnormalizado)
  → VehiclePositionReceived event → MercurePositionListener (SSE)
```

### Variables de Entorno

```env
TRACCAR_BASE_URL=http://traccar:8082    # local
# TRACCAR_BASE_URL=http://traccar-mxo.railway.internal:8082  # Railway
TRACCAR_USERNAME=admin
TRACCAR_PASSWORD=admin
```

## Simulación GPS (Desarrollo)

```bash
php bin/console app:dev:simulate-gps --points=10 --interval=1 --ingest
```

**Opciones:**
- `--points=N` — posiciones a enviar
- `--interval=N` — segundos entre cada una
- `--ingest` — ingestar en Symfony al terminar (batch, no realtime)

**Flujo:**
1. Busca Vehicle activo (preferencia por nombre con "Demo")
2. Crea device en Traccar via API REST si no existe (uniqueId: `sim-{nombre}`)
3. Actualiza `Vehicle.traccarDeviceId` en DB local
4. Envía posiciones al protocolo OsmAnd de Traccar (puerto 5055)
5. Si `--ingest`: espera 3s y llama a `TraccarIngestionService`

**Ruta simulada:** circuito por el centro de Madrid (Sol → Gran Vía → Plaza España → Palacio Real → Puerta de Toledo → Atocha → Retiro → Cibeles → Sol).

## Tracking en Vivo

Para ver vehículo moverse en `/fleet/map`, se necesitan **dos procesos**:

```bash
# 1. Stream que lee Traccar y publica a Mercure
docker compose -f docker-compose.local.yml exec -T -d app php bin/console app:traccar:stream --sleep=2

# 2. Simular movimiento GPS (~2 min)
docker compose -f docker-compose.local.yml exec -T app php bin/console app:dev:simulate-gps --points=120 --interval=1
```

`--ingest` hace ingesta batch al final, no en tiempo real. Para tracking en vivo, usar `app:traccar:stream` en paralelo.

## Entidades Relacionadas

- **VehiclePosition**: Posición GPS histórica (lat, lng, speed, timestamp, heading)
- **VehicleLastPosition**: Cache desnormalizada de última posición (OneToOne con Vehicle)
- **VehicleCheckpoint**: Último punto de ingesta procesado desde Traccar

## Deuda Técnica

`GpsDeviceProviderInterface` tiene `login()` y `getSessionCookie()` que son Traccar-específicos. WebhookGpsProvider usa stubs (no-op). Refactorizar cuando se añada un tercer provider GPS.

## Historial

- 2026-03-11: Creación inicial
