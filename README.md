# transporte-tracking

Monorepo para plataforma de tracking y operación logística con Symfony 6.5 + Turbo, Mercure y Traccar (backend puente).

## Decisiones cuestionadas (y decisión tomada)
- **Sesiones vs JWT de portal**: se mantiene sesión tradicional (cookies HttpOnly) por menor superficie de ataque en backoffice y UX Twig/Turbo.
- **Mapa en Turbo**: no se usa Turbo Streams para telemetría densa; el mapa usa JS SSE + Mercure.
- **Confirmación en BBDD vs firma en archivo**: por tu confirmación, se almacena firma en BBDD (`pod.signature_png_base64`) con interfaz `PodStorageInterface` para migrar a S3 sin romper API.
- **UUIDs**: se usan UUID para entidades de negocio expuestas externamente.
- **Customers sin wildcard Mercure**: prohibido por diseño y validado por test.

## Estructura
- `backend/`: aplicación Symfony (código, config, tests, migraciones).
- `infra/`: Docker Compose/Nginx/scripts para 3 VPS Hetzner.
- `docs/`: guía de despliegue, hardening, runbooks.


## Probar en local con Docker (rápido)

Si quieres validar el backend sin montar VPS, usa `docker-compose.local.yml` (PHP 8.2 CLI + PostgreSQL + Redis + Mercure).

1. Levantar contenedores:
   ```bash
   docker compose -f docker-compose.local.yml up -d --build
   ```
2. Instalar dependencias de PHP dentro del contenedor:
   ```bash
   docker compose -f docker-compose.local.yml run --rm app composer install
   ```
3. Ejecutar migraciones:
   ```bash
   docker compose -f docker-compose.local.yml run --rm app php bin/console doctrine:migrations:migrate -n
   ```
4. Ejecutar tests/lint:
   ```bash
   docker compose -f docker-compose.local.yml run --rm app php bin/phpunit
   docker compose -f docker-compose.local.yml run --rm app find src tests -name '*.php' -print0 | xargs -0 -n1 php -l
   ```

Notas:
- El contenedor `app` queda preparado para comandos Symfony/Composer y usa `backend/` como working dir.
- Si sólo quieres entrar al shell del contenedor: `docker compose -f docker-compose.local.yml run --rm app bash`.
- Este setup es para desarrollo local (no reemplaza los compose de `infra/` para producción).

## Arquitectura resumida
- VPS1 WEB: Nginx + PHP-FPM (host), Docker (`mercure`, `redis`).
- VPS2 DB_APP: Docker PostgreSQL privado + backups diarios.
- VPS3 TRACCAR: Docker Traccar + MariaDB + Nginx TLS para `gps.midominio.com`.

## Flujo de tracking
1. Traccar Client envía posiciones a `gps.midominio.com:443` (proxy TLS -> 5055).
2. Worker Symfony (`app:traccar:stream`) consume WS Traccar, persiste histórico/última posición.
3. Backend publica en Mercure topics autorizados.
4. Frontend SSE muestra sólo vehículos permitidos por token subscriber.

## Seguridad clave
- `MERCURE_PUBLISHER_JWT_KEY` sólo en servidor backend.
- Cookie sesión: `HttpOnly`, `Secure(auto/prod)`, `SameSite=lax`.
- Cookie `mercureAuthorization` HttpOnly emitida server-side.
- Customers reciben topics explícitos; jamás `/*`.

## Runbooks rápidos
- Reiniciar worker: `systemctl restart transporte-traccar-stream.service`
- Ver logs app: `tail -f /var/log/transporte/app.log`
- Backup DB manual: `infra/vps2-db/backup/backup_postgres.sh`

## Estado del repositorio
Este commit entrega base productiva extensa (dominio, seguridad, endpoints, infra y docs) lista para completar wiring de vendor/instalación en entorno con acceso a Packagist.

## Siguientes pasos propuestos (iteración 2)
1. Conectar repositorios Doctrine reales para filtros por CUSTOMER/DRIVER en endpoints de vehículos, rutas y envíos.
2. Implementar `app:traccar:stream` con cliente WS real (re-login + backfill por checkpoint).
3. Añadir CSV import para envíos y monitor de progreso de rutas en backoffice.
4. Ejecutar smoke tests end-to-end sobre entorno staging en Hetzner.


## Roadmap de mejoras (decisiones pospuestas)
1. Restringir `POST /api/driver/routes/{routeId}/start|finish` al driver asignado (ownership estricto), cuando prioricemos hardening de permisos finos.
2. Mantener `shipment_id` flexible en `deliver/exception` por ahora; evaluar modo estricto obligatorio en una fase posterior.


## Contrato de errores API (iteración actual)
- Se estandariza respuesta de error JSON: `error.code`, `error.message`, y `error.details` para validaciones (422).
- Endpoints DRIVER (`deliver/exception`) validan payload con DTO + Symfony Validator.
- `invalid_json` responde 400 con formato homogéneo.


## Confirmación de entrega por DNI codificado (iteración actual)
- Se audita evidencia mínima reforzada: hash SHA-256 del DNI codificado, timestamp, IP y User-Agent del driver en `audit_log` para trazabilidad sin firma digital.
- Se añade `action_fingerprint` (hash SHA-256 por parada/acción/driver/bucket temporal) para trazabilidad antifraude ligera sin dependencias extra.
- `GET /api/driver/stops/{stopId}/pod` devuelve metadatos de confirmación (`pod_id`, `download_url`, `confirmation_mode`).
- `GET /api/driver/stops/{stopId}/pod/download` devuelve el detalle de confirmación (`recipient_id_encoded`, `confirmed_by_driver`).
- Se elimina la dependencia de firma en canvas/PNG para reducir infraestructura operativa.


## Iteración autónoma (paralelo)
- API de vehículos ahora aplica visibilidad por rol (staff ve todo activo, customer por `customer_vehicle`, driver por vehículos de sus rutas).
- Se añade endpoint API de envíos con filtrado por customer y timeline de eventos.

- Polling/backfill Traccar implementado en comando `app:traccar:stream` con checkpoint por vehículo.
- Backoffice de import CSV de envíos habilitado en `/admin/shipments/import`.

- Ingesta Traccar ahora publica posición a Mercure por topic `/vehicles/{id}/position` durante el procesamiento.
- Import CSV registra histórico de ejecuciones y se visualiza en backoffice de importaciones.

- Dashboard admin incorpora métricas básicas (`import_runs_today`, `positions_ingested_last_hour`, `active_routes`, `pending_stops`).
- Smoke test CLI disponible: `php bin/console app:smoke:traccar-once`.

- Dashboard muestra salud básica de integraciones (`traccar_ok`, `mercure_ok`).
- Smoke de permisos disponible: `php bin/console app:smoke:permissions`.
- API histórico: `GET /api/vehicles/{id}/positions?from=&to=` con control de visibilidad.

- Endpoint JSON de salud admin: `GET /admin/health` (health + metrics + timestamp).
- Replay de posiciones soporta `limit`, `offset`, `order` además de `from`/`to`.
- Smoke import CSV: `php bin/console app:smoke:csv-import --customer-id=... --file=...`.
