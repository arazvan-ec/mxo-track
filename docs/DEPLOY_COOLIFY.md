# Deploy en Coolify (VPS)

Guía para desplegar mxo-track en [Coolify](https://coolify.io), un PaaS open-source auto-hospedado.

## Requisitos del VPS

- **RAM**: 4 GB mínimo (8 GB recomendado — OSRM y Traccar consumen bastante)
- **Disco**: 20 GB+ (mapa OSRM ~75 MB, PostgreSQL, logs)
- **OS**: Ubuntu 22.04+ o Debian 12+
- **Proveedores recomendados**: Hetzner (~5-10 EUR/mo), DigitalOcean, OVH

## 1. Instalar Coolify en el VPS

```bash
ssh root@tu-servidor
curl -fsSL https://cdn.coollabs.io/coolify/install.sh | bash
```

Acceder al panel de Coolify en `http://tu-servidor-ip:8000` y crear la cuenta admin.

> **Nota**: Coolify usa el puerto 8000 por defecto para su UI. Una vez configurado, la app usará su propio dominio vía reverse proxy.

## 2. Crear el proyecto en Coolify

1. **Servers** → Añadir el servidor (localhost o remoto)
2. **Projects** → New Project → Nombre: `mxo-track`
3. **New Resource** → **Docker Compose**
4. Conectar repositorio GitHub (o pegar URL del repo)
5. Seleccionar archivo: `docker-compose.coolify.yml`

## 3. Configurar variables de entorno

En la UI de Coolify → **Environment Variables**, configurar todas las variables del `.env.coolify.example`.

Variables críticas que **debes cambiar**:

| Variable | Ejemplo |
|----------|---------|
| `APP_SECRET` | `openssl rand -hex 32` |
| `APP_URL` | `https://tudominio.com` |
| `POSTGRES_PASSWORD` | contraseña segura |
| `MERCURE_PUBLISHER_JWT_KEY` | `openssl rand -base64 32` |
| `MERCURE_SUBSCRIBER_JWT_KEY` | (misma que publisher) |
| `MERCURE_PUBLIC_URL` | `https://mercure.tudominio.com/.well-known/mercure` |
| `MERCURE_CORS_ORIGINS` | `https://tudominio.com` |
| `TRACCAR_PASSWORD` | contraseña segura |
| `TRACCAR_DB_PASSWORD` | contraseña segura |
| `TRACCAR_POSTGRES_PASSWORD` | (misma que TRACCAR_DB_PASSWORD) |

## 4. Configurar dominios

En Coolify, cada servicio expuesto necesita un dominio:

| Servicio | Puerto | Dominio sugerido |
|----------|--------|-----------------|
| `app` | 8000 | `tudominio.com` |
| `mercure` | 3000 | `mercure.tudominio.com` |

Coolify configura SSL automáticamente via Let's Encrypt.

### Puerto GPS (Traccar)

El puerto `5055` (protocolo OsmAnd para GPS) se expone directamente. Configurar firewall del VPS:

```bash
ufw allow 5055/tcp
```

## 5. Deploy

Click **Deploy** en Coolify. El primer deploy tarda más porque:
- Construye las imágenes Docker (app, worker, traccar, osrm)
- OSRM descarga y procesa el mapa de Madrid (~75 MB)
- PostgreSQL inicializa las bases de datos
- El app espera a la DB, ejecuta migraciones y calienta cache

### Verificar servicios

Desde el VPS:

```bash
# Ver logs de todos los servicios
docker compose -f docker-compose.coolify.yml logs -f

# Verificar que la app responde
curl -sf http://localhost:8000 | head -5

# Verificar Mercure
curl -sf http://localhost:3000/.well-known/mercure

# Verificar Traccar
curl -sf http://localhost:8082/api/server
```

## 6. Post-deploy (primera vez)

### Cargar fixtures (datos de ejemplo)

```bash
docker compose -f docker-compose.coolify.yml exec app \
  php bin/console doctrine:fixtures:load -n --env=prod
```

### Preparar mapa OSRM (si no se descargó automáticamente)

El contenedor OSRM descarga el mapa en el primer arranque. Si falla, verificar:

```bash
docker compose -f docker-compose.coolify.yml logs osrm
```

## 7. Auto-deploy desde GitHub

En Coolify → Settings del recurso → **Webhooks**: copiar la URL del webhook y añadirla en GitHub:

**GitHub** → Settings → Webhooks → Add webhook → Payload URL: (pegar de Coolify)

Cada push a `main` triggerea un redeploy automático.

## Arquitectura

```
                    ┌─────────────────────┐
                    │   Coolify Proxy      │
                    │   (Caddy/Traefik)    │
                    │   SSL termination    │
                    └──────┬────┬──────────┘
                           │    │
              :443 (HTTPS) │    │ :443 (HTTPS)
                           ▼    ▼
                    ┌──────┐    ┌─────────┐
                    │ app  │    │ mercure  │
                    │ :8000│    │ :80      │
                    └──┬───┘    └──────────┘
                       │
          ┌────────────┼────────────┬──────────┐
          ▼            ▼            ▼          ▼
    ┌────────┐   ┌─────────┐  ┌─────────┐ ┌────────┐
    │   db   │   │  redis  │  │ traccar  │ │ worker │
    │ :5432  │   │  :6379  │  │ :8082    │ │        │
    └────────┘   └─────────┘  │ :5055    │ └────────┘
                              └────┬─────┘
                                   │
                            ┌──────┴──────┐
                            │ traccar_db  │
                            │   :5432     │
                            └─────────────┘
          ┌─────────┐
          │  osrm   │◄──── vroom (:3000)
          │  :5000  │
          └─────────┘
```

## Diferencias vs Railway

| Aspecto | Railway | Coolify |
|---------|---------|---------|
| Puerto app | Dinámico (`$PORT`) | Fijo `8000` |
| Networking | `*.railway.internal` | Docker service names |
| SSL | Automático | Let's Encrypt via Coolify |
| DB/Redis | Managed | Self-hosted (Docker volumes) |
| Coste | ~$20-40/mo | ~5-10 EUR/mo (VPS) |
| Backups | Dashboard | Manual o cron |

## Backups

Configurar backup de volúmenes PostgreSQL via cron:

```bash
# Añadir a crontab del VPS (diario a las 3 AM)
0 3 * * * docker compose -f /path/to/docker-compose.coolify.yml exec -T db \
  pg_dump -U mxo mxo_track | gzip > /backups/mxo_track_$(date +\%Y\%m\%d).sql.gz
```
