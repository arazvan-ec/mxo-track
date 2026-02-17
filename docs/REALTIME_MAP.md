# REALTIME_MAP — Fase 2

## Objetivo
Mapa realtime en `/fleet/map` consumiendo telemetría por Mercure (SSE + JSON), sin Turbo en esa página.

## Topics
- Vehículo (estándar):
  - `/vehicles/{vehicle_public_id}/position`

## Payload JSON de posición
```json
{
  "vehicleId": "01JABCDEF1234567890ABCDE1",
  "lat": 40.4168,
  "lng": -3.7038,
  "speed": 12.3,
  "course": 180,
  "accuracy": 4.5,
  "deviceTime": "2026-02-17T17:10:00+00:00",
  "receivedAt": "2026-02-17T17:10:01+00:00"
}
```

## Flujo frontend
1. La vista `/fleet/map` solicita cookie de suscripción con `GET /api/mercure-token`.
2. Carga `GET /api/vehicles` para poblar el selector.
3. Al elegir vehículo:
   - cierra el `EventSource` actual si existe,
   - abre nuevo `EventSource` para `/vehicles/{public_id}/position`.
4. En `beforeunload`, cierra `EventSource` para evitar conexiones colgadas.

## Endpoint fake update (admin)
- `POST /admin/dev/push-position`
- Seguridad: solo `ROLE_ADMIN`.

### Ejemplo cURL
```bash
curl -X POST 'http://localhost:8000/admin/dev/push-position' \
  -H 'Content-Type: application/json' \
  -b 'PHPSESSID=TU_SESION' \
  --data-raw '{
    "vehicleId": "01JABCDEF1234567890ABCDE1",
    "lat": 40.4168,
    "lng": -3.7038,
    "speed": 35.5,
    "course": 270,
    "accuracy": 5,
    "deviceTime": "2026-02-17T17:10:00+00:00"
  }'
```

## Prueba manual recomendada
1. Login como admin en el portal.
2. Abrir `/fleet/map` y seleccionar un vehículo.
3. Lanzar el cURL anterior (ajustando `vehicleId` al `public_id` del selector).
4. Verificar movimiento del marcador y ausencia de reconexiones duplicadas al cambiar de vehículo.

## Notas
- Turbo se desactiva solo en esta vista (`data-turbo="false"`).
- La cookie `mercureAuthorization` se configura con `HttpOnly` y `SameSite=lax`.
