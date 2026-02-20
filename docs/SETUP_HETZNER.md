# Setup Hetzner (3 VPS) para transporte-tracking

## Requisitos previos

- Cuenta en [Hetzner Cloud](https://www.hetzner.com/cloud)
- Un dominio con acceso a la configuración DNS
- Clave SSH configurada en Hetzner

## Arquitectura

```
                    ┌──────────────────────────────────────────┐
                    │          Red Privada Hetzner             │
                    │         (10.0.0.0/16)                    │
                    │                                          │
  Internet ──►  VPS1 WEB (10.0.2.5)                           │
  (HTTPS)       ├── Nginx (reverse proxy + SSL)               │
                ├── PHP 8.4-FPM (Symfony app)                 │
                ├── Docker: Mercure (SSE, :3000 loopback)     │
                ├── Docker: Redis (sesiones, :6379 loopback)  │
                └── systemd: traccar-stream worker            │
                    │         │                                │
                    │         ├──► VPS2 DB (10.0.2.10)        │
                    │         │    └── Docker: PostgreSQL 16   │
                    │         │        (:5432 red privada)     │
                    │         │                                │
  Internet ──►  VPS3 TRACCAR (10.0.3.20)                      │
  (HTTPS/GPS)   ├── Nginx (reverse proxy + SSL)    ◄─────────┘
                ├── Docker: Traccar (:8082 loopback)
                ├── Docker: MariaDB 11 (interno)
                └── Puerto GPS OsmAnd (:5055 via nginx)
```

## Coste estimado

| VPS | Tipo sugerido | Coste/mes |
|-----|---------------|-----------|
| VPS1 Web | CX22 (2 vCPU, 4GB RAM) | ~€4.50 |
| VPS2 DB | CX22 (2 vCPU, 4GB RAM) | ~€4.50 |
| VPS3 Traccar | CX11 (1 vCPU, 2GB RAM) | ~€3.50 |
| **Total** | | **~€12.50/mes** |

---

## 1) Crear infraestructura en Hetzner

### 1.1 Crear red privada

1. Hetzner Cloud Console → Networks → Create Network
2. Nombre: `transporte-net`
3. Rango IP: `10.0.0.0/16`
4. Crear subred: `10.0.0.0/24` (zona: la misma de los VPS)

### 1.2 Crear los 3 VPS

Crear cada VPS con Ubuntu 24.04 y tu clave SSH:

| VPS | Nombre | Tipo | Imagen |
|-----|--------|------|--------|
| VPS1 | `web` | CX22 | Ubuntu 24.04 |
| VPS2 | `db` | CX22 | Ubuntu 24.04 |
| VPS3 | `traccar` | CX11 | Ubuntu 24.04 |

Adjuntar los 3 a la red `transporte-net`. Asignar IPs privadas:
- VPS1: `10.0.2.5`
- VPS2: `10.0.2.10`
- VPS3: `10.0.3.20`

### 1.3 Configurar DNS

En tu proveedor de dominios, crear registros A:

```
portal.tudominio.com  →  <IP pública VPS1>
gps.tudominio.com     →  <IP pública VPS3>
```

---

## 2) Despliegue automatizado (scripts)

Los scripts de setup hacen toda la configuración automáticamente. Ejecútalos en orden:

### 2.1 VPS2 — Base de datos (primero)

```bash
ssh root@<IP_VPS2>
# Descargar y ejecutar el script:
curl -sL https://raw.githubusercontent.com/<tu-repo>/main/scripts/setup_vps2_db.sh -o setup.sh
bash setup.sh 10.0.2.10 "TU_PASSWORD_DB_SEGURA"
```

Anota la `DATABASE_URL` que imprime al final.

### 2.2 VPS3 — Traccar (segundo)

```bash
ssh root@<IP_VPS3>
curl -sL https://raw.githubusercontent.com/<tu-repo>/main/scripts/setup_vps3_traccar.sh -o setup.sh
bash setup.sh gps.tudominio.com "TU_PASSWORD_MYSQL"
```

Anota las URLs de Traccar que imprime al final. **Cambia la contraseña del admin de Traccar.**

### 2.3 VPS1 — Web (último)

```bash
ssh root@<IP_VPS1>
curl -sL https://raw.githubusercontent.com/<tu-repo>/main/scripts/setup_vps1_web.sh -o setup.sh
bash setup.sh portal.tudominio.com
```

### 2.4 Configurar .env.local en VPS1

```bash
ssh root@<IP_VPS1>
cp /var/www/transporte-tracking/.env.example /var/www/transporte-tracking/.env.local
nano /var/www/transporte-tracking/.env.local
```

Completar con los valores reales:

```env
APP_ENV=prod
APP_SECRET=<genera con: openssl rand -hex 32>
APP_URL=https://portal.tudominio.com

DATABASE_URL=postgresql://app:<PASSWORD_DB>@10.0.2.10:5432/transporte?serverVersion=16&charset=utf8

REDIS_URL=redis://127.0.0.1:6379
REDIS_SESSION_PREFIX=sess:transporte:

MERCURE_URL=http://127.0.0.1:3000/.well-known/mercure
MERCURE_PUBLIC_URL=https://portal.tudominio.com/.well-known/mercure
MERCURE_PUBLISHER_JWT_KEY=<copiar de infra/vps1-web/.env>
MERCURE_SUBSCRIBER_JWT_KEY=<copiar de infra/vps1-web/.env>
MERCURE_SUBSCRIBER_TOKEN_TTL=3600

TRACCAR_BASE_URL=http://10.0.3.20:8082
TRACCAR_WS_URL=ws://10.0.3.20:8082/api/socket
TRACCAR_USERNAME=admin
TRACCAR_PASSWORD=<la contraseña que configuraste en Traccar>

POD_STORAGE=database
TRUSTED_PROXIES=127.0.0.1,REMOTE_ADDR
TRUSTED_HEADERS=x-forwarded-for,x-forwarded-host,x-forwarded-proto,x-forwarded-port,x-forwarded-prefix
```

### 2.5 Primer deploy

```bash
ssh root@<IP_VPS1>
cd /var/www/transporte-tracking
bash scripts/deploy_vps1_web.sh main
```

### 2.6 Cargar datos iniciales

```bash
ssh root@<IP_VPS1>
cd /var/www/transporte-tracking/backend
php bin/console doctrine:fixtures:load -n --env=prod
```

---

## 3) Verificación post-deploy

### Checklist

```bash
# VPS1 — Verificar servicios
systemctl status php8.4-fpm          # ✓ active
systemctl status traccar-stream      # ✓ active
docker compose -f /var/www/transporte-tracking/infra/vps1-web/docker-compose.yml ps  # mercure + redis running

# VPS2 — Verificar PostgreSQL
docker ps                             # postgres running
docker exec $(docker ps -qf name=postgres) pg_isready  # accepting connections

# VPS3 — Verificar Traccar
docker ps                             # traccar + mariadb running
curl -s http://127.0.0.1:8082/api/server | head  # Traccar server info
```

### URLs finales

| URL | Qué es |
|-----|--------|
| `https://portal.tudominio.com` | Portal web (login) |
| `https://portal.tudominio.com/.well-known/mercure` | Hub Mercure (SSE) |
| `https://gps.tudominio.com` | Endpoint GPS para dispositivos |

---

## 4) Deploy automático (CI/CD con GitHub Actions)

Cada push a `main` ejecuta automáticamente:
1. Lint PHP + validación Symfony 7.4 lock
2. Verificación de que Symfony arranca
3. Deploy a VPS1 vía SSH
4. Health check post-deploy

### 4.1 Configurar GitHub Secrets

En tu repositorio: **Settings → Secrets and variables → Actions** → añadir:

| Secret | Valor | Ejemplo |
|--------|-------|---------|
| `VPS1_HOST` | IP pública de VPS1 | `65.21.xx.xx` |
| `VPS1_USER` | Usuario SSH | `root` |
| `VPS1_SSH_KEY` | Clave SSH privada (contenido completo) | `-----BEGIN OPENSSH...` |
| `VPS1_PORT` | Puerto SSH (si no es 22) | `22` |
| `PORTAL_DOMAIN` | Tu dominio del portal | `portal.tudominio.com` |

### 4.2 Crear environment "production"

En **Settings → Environments** → Create environment `production`.

Opcional pero recomendado: activar **Required reviewers** para que alguien apruebe cada deploy antes de ejecutarse.

### 4.3 Generar clave SSH para GitHub Actions

```bash
# En tu máquina local:
ssh-keygen -t ed25519 -f ~/.ssh/github_deploy -C "github-actions-deploy" -N ""

# Copiar la clave PÚBLICA al VPS1:
ssh-copy-id -i ~/.ssh/github_deploy.pub root@<IP_VPS1>

# Copiar la clave PRIVADA como Secret en GitHub (VPS1_SSH_KEY):
cat ~/.ssh/github_deploy
```

### 4.4 Flujo de trabajo

```
git push origin main
    ↓
GitHub Actions: lint + validate
    ↓ (si pasa)
SSH a VPS1 → bash scripts/deploy_vps1_web.sh main
    ↓
Health check → curl https://portal.tudominio.com
    ↓
✅ Deploy exitoso / ❌ Fallo (ver logs en GitHub Actions)
```

### 4.5 Deploy manual (sin CI/CD)

También puedes hacer deploy manual por SSH:

```bash
ssh root@<IP_VPS1>
cd /var/www/transporte-tracking
bash scripts/deploy_vps1_web.sh main
```

### 4.6 Deploy manual desde GitHub (workflow_dispatch)

En GitHub → Actions → "Deploy to Production" → Run workflow. Puedes marcar "Skip tests" si necesitas deploy urgente.

---

## 5) Traccar Client (dispositivos GPS)

### Android — Traccar Client

1. Instalar [Traccar Client](https://play.google.com/store/apps/details?id=org.traccar.client) desde Play Store
2. Configurar:
   - **URL servidor**: `https://gps.tudominio.com`
   - **Device identifier**: IMEI o un ID único
   - **Intervalo**: 5-10 segundos
   - **Protocolo**: OsmAnd HTTP

### iOS — Traccar Client

Misma configuración en la app de iOS.

---

## 6) Runbooks (operaciones comunes)

### Reiniciar Mercure/Redis

```bash
ssh root@<IP_VPS1>
docker compose -f /var/www/transporte-tracking/infra/vps1-web/docker-compose.yml restart
```

### Ver logs del worker Traccar

```bash
ssh root@<IP_VPS1>
journalctl -u traccar-stream -f
```

### Backup manual de la base de datos

```bash
ssh root@<IP_VPS2>
bash /opt/db/backup/backup_postgres.sh
ls -la /opt/db/backup/dumps/
```

### Restaurar backup

```bash
ssh root@<IP_VPS2>
gunzip -c /opt/db/backup/dumps/transporte_FECHA.sql.gz | \
  docker exec -i $(docker ps -qf name=postgres) psql -U app transporte
```

### Renovar certificados SSL (automático, pero manual si falla)

```bash
# VPS1
sudo certbot renew
# VPS3
sudo certbot renew
```

### Ver logs de Symfony

```bash
ssh root@<IP_VPS1>
tail -f /var/www/transporte-tracking/backend/var/log/prod.log
```
