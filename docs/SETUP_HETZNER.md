# Setup Hetzner (3 VPS) para transporte-tracking

## 1) Crear infraestructura
1. Crear VPS: `web`, `db_app`, `traccar`.
2. Crear red privada Hetzner y adjuntar los 3 VPS.
3. DNS:
   - `portal.midominio.com` -> IP pública VPS1
   - `gps.midominio.com` -> IP pública VPS3

## 2) Firewall sugerido (UFW)

### VPS1 WEB
- Allow: 22/tcp, 80/tcp, 443/tcp
- Deny: 6379 público, 3000 público
- Egress privado a VPS2:5432 y VPS3:8082

### VPS2 DB_APP
- Allow: 22/tcp
- Allow private: 5432/tcp sólo desde VPS1 private IP
- Deny all public incoming

### VPS3 TRACCAR
- Allow: 22/tcp, 80/tcp, 443/tcp
- Deny public: 8082/tcp y 5055/tcp (quedan loopback)

## 3) Servicios Docker
- VPS1: `infra/vps1-web/docker-compose.yml`
- VPS2: `infra/vps2-db/docker-compose.yml`
- VPS3: `infra/vps3-traccar/docker-compose.yml`

## 4) TLS
1. Instalar Nginx + certbot en VPS1 y VPS3.
2. Emitir certificados para `portal.midominio.com` y `gps.midominio.com`.
3. Activar configs Nginx del repo y recargar.

## 5) Symfony en host (no docker)
1. Clonar repo en `/var/www/transporte-tracking`.
2. Copiar `.env.example` a `.env.local` y completar secretos.
3. Instalar dependencias PHP (`composer install`) en entorno con salida a packagist.
4. Ejecutar migraciones.
5. Configurar systemd para worker `app:traccar:stream`.

## 6) Sesiones Redis y Mercure
- `REDIS_URL=redis://127.0.0.1:6379`
- Handler de sesión Redis con prefijo `sess:transporte:`.
- Hub Mercure detrás de `/.well-known/mercure`.
- Token subscriber por sesión en cookie HttpOnly `mercureAuthorization`.

## 7) Traccar Client Android
- URL servidor: `https://gps.midominio.com`
- Protocolo: OsmAnd HTTP
- Device identifier: IMEI o ID único estable
- Intervalo sugerido: 5-10s (ajustar consumo batería)

## 8) Runbooks
- Reinicio Mercure/Redis: `docker compose -f infra/vps1-web/docker-compose.yml restart`
- Backup db: cron diario `infra/vps2-db/backup/backup_postgres.sh`
- Revisar WS Traccar: logs de servicio `app:traccar:stream`
