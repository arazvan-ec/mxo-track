# REALTIME_MAP — Fase 2

## Objetivo
Mapa realtime en `/fleet/map` consumiendo telemetría por Mercure (SSE + JSON), sin Turbo en esa página. Muestra la ruta histórica como polyline y tracking en tiempo real.

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
   - limpia el mapa (cierra `EventSource`, elimina polyline y marker anteriores),
   - llama a `GET /api/vehicles/{publicId}/positions?order=ASC&limit=2000` para obtener el historial de posiciones,
   - dibuja una **polyline azul** con todas las coordenadas históricas,
   - coloca un **marker** en la última posición,
   - ajusta el zoom con `fitBounds` para que toda la ruta sea visible,
   - abre nuevo `EventSource` para `/vehicles/{public_id}/position`.
4. Cuando llega una nueva posición por Mercure:
   - extiende la polyline con `addLatLng`,
   - mueve el marker a la nueva posición,
   - hace `panTo` para seguir el vehículo.
5. Al cambiar de vehículo, limpia polyline + marker antes de cargar los nuevos datos.
6. En `beforeunload`, cierra `EventSource` para evitar conexiones colgadas.

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

## Prueba manual recomendada (tracking en vivo)
1. Login como admin en el portal.
2. Abrir `/fleet/map` y seleccionar un vehículo.
3. En otra terminal, lanzar la simulación GPS y el stream de Traccar:
```bash
# Arrancar el stream de posiciones (publica a Mercure)
docker compose -f docker-compose.local.yml exec -T -d app php bin/console app:traccar:stream --sleep=2

# Simular movimiento del vehículo (~2 minutos)
docker compose -f docker-compose.local.yml exec -T app php bin/console app:dev:simulate-gps --points=120 --interval=1
```
4. Verificar que la polyline azul se extiende y el marker se mueve en tiempo real.
5. Al cambiar de vehículo, verificar que polyline y marker anteriores se limpian.

## Notas
- Turbo se desactiva solo en esta vista (`data-turbo="false"`).
- La cookie `mercureAuthorization` se configura con `HttpOnly` y `SameSite=lax`.
- Para que el tracking en vivo funcione, `app:traccar:stream` debe estar corriendo — es el proceso que lee posiciones de Traccar y las publica a Mercure.
- La opción `--ingest` de `simulate-gps` ingesta posiciones al final (batch), no en tiempo real. Para tracking en vivo usar `app:traccar:stream` en paralelo.
