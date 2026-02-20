#!/usr/bin/env bash
# =============================================================================
# hetzner_deploy_all.sh — Configura los 3 VPS desde tu máquina local
#
# Prerequisito: haber ejecutado hetzner_provision.sh (crea los VPS)
#
# Uso:
#   bash scripts/hetzner_deploy_all.sh <portal-domain> <gps-domain>
#
# Ejemplo:
#   bash scripts/hetzner_deploy_all.sh portal.mxotrack.com gps.mxotrack.com
# =============================================================================
set -euo pipefail

PORTAL_DOMAIN="${1:?Uso: $0 <portal-domain> <gps-domain>}"
GPS_DOMAIN="${2:?Uso: $0 <portal-domain> <gps-domain>}"
REPO_URL="${3:-$(git remote get-url origin 2>/dev/null || echo 'https://github.com/arazvan-ec/mxo-track.git')}"

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
PROJECT_DIR="$(dirname "$SCRIPT_DIR")"

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
CYAN='\033[0;36m'
BOLD='\033[1m'
NC='\033[0m'

log()  { echo -e "${GREEN}[✓]${NC} $1"; }
warn() { echo -e "${YELLOW}[!]${NC} $1"; }
info() { echo -e "${CYAN}[→]${NC} $1"; }
fail() { echo -e "${RED}[✗]${NC} $1"; exit 1; }
header() { echo -e "\n${BOLD}══════════════════════════════════════${NC}\n  $1\n${BOLD}══════════════════════════════════════${NC}\n"; }

# ─── Cargar IPs del archivo generado por hetzner_provision.sh ────────────────
INFRA_FILE="$PROJECT_DIR/infra/.hetzner_ips"
if [ ! -f "$INFRA_FILE" ]; then
    fail "No se encontró $INFRA_FILE. Ejecuta primero: bash scripts/hetzner_provision.sh"
fi
source "$INFRA_FILE"

SSH_OPTS="-o StrictHostKeyChecking=no -o ConnectTimeout=10 -o BatchMode=yes"

echo ""
echo "=============================================="
echo "  Deploy completo: mxo-track"
echo "=============================================="
echo "  Portal:  $PORTAL_DOMAIN → $VPS1_PUBLIC_IP"
echo "  GPS:     $GPS_DOMAIN → $VPS3_PUBLIC_IP"
echo "  DB:      $VPS2_PRIVATE_IP (solo red privada)"
echo "  Repo:    $REPO_URL"
echo "=============================================="
echo ""

# ─── Generar contraseñas seguras ─────────────────────────────────────────────
DB_PASSWORD="$(openssl rand -base64 24 | tr -d '/+=' | head -c 32)"
MYSQL_PASSWORD="$(openssl rand -base64 24 | tr -d '/+=' | head -c 32)"
MYSQL_ROOT_PASSWORD="$(openssl rand -base64 24 | tr -d '/+=' | head -c 32)"
APP_SECRET="$(openssl rand -hex 32)"
MERCURE_JWT_KEY="$(openssl rand -hex 32)"

# Guardar credenciales localmente
CREDS_FILE="$PROJECT_DIR/infra/.credentials"
cat > "$CREDS_FILE" <<EOF
# Credenciales generadas — $(date -Iseconds)
# ¡GUARDA ESTE ARCHIVO EN UN LUGAR SEGURO Y NO LO SUBAS A GIT!
DB_PASSWORD=$DB_PASSWORD
MYSQL_PASSWORD=$MYSQL_PASSWORD
MYSQL_ROOT_PASSWORD=$MYSQL_ROOT_PASSWORD
APP_SECRET=$APP_SECRET
MERCURE_JWT_KEY=$MERCURE_JWT_KEY
PORTAL_DOMAIN=$PORTAL_DOMAIN
GPS_DOMAIN=$GPS_DOMAIN
EOF
chmod 600 "$CREDS_FILE"
log "Credenciales guardadas en $CREDS_FILE"
warn "¡Guarda este archivo en un lugar seguro!"

# ─── Verificar conectividad SSH ──────────────────────────────────────────────
header "Verificando conectividad SSH"
for SERVER_NAME in "VPS1 Web:$VPS1_PUBLIC_IP" "VPS2 DB:$VPS2_PUBLIC_IP" "VPS3 Traccar:$VPS3_PUBLIC_IP"; do
    NAME="${SERVER_NAME%%:*}"
    IP="${SERVER_NAME##*:}"
    info "Probando SSH a $NAME ($IP)..."
    if ssh $SSH_OPTS "root@$IP" "echo ok" >/dev/null 2>&1; then
        log "$NAME accesible"
    else
        fail "No se puede conectar a $NAME ($IP). Verifica que la SSH key es correcta."
    fi
done

# ─── Función para ejecutar script remoto ─────────────────────────────────────
run_remote() {
    local IP=$1
    local NAME=$2
    shift 2
    info "Ejecutando en $NAME ($IP)..."
    ssh $SSH_OPTS "root@$IP" "$@"
}

# =============================================================================
# VPS2 — Base de datos
# =============================================================================
header "Configurando VPS2 — Base de datos"

run_remote "$VPS2_PUBLIC_IP" "VPS2" bash -s <<REMOTE_SCRIPT
set -euo pipefail

echo ">>> Instalando Docker..."
apt-get update -qq
apt-get install -y -qq docker.io docker-compose-plugin > /dev/null 2>&1

echo ">>> Configurando firewall..."
apt-get install -y -qq ufw > /dev/null 2>&1
ufw --force reset > /dev/null 2>&1
ufw default deny incoming > /dev/null
ufw default allow outgoing > /dev/null
ufw allow 22/tcp > /dev/null
ufw allow from 10.0.0.0/16 to any port 5432 proto tcp > /dev/null
echo "y" | ufw enable > /dev/null

echo ">>> Configurando PostgreSQL..."
mkdir -p /opt/db/backup/dumps

cat > /opt/db/docker-compose.yml <<'COMPOSE'
services:
  postgres:
    image: postgres:16-alpine
    restart: unless-stopped
    environment:
      POSTGRES_DB: transporte
      POSTGRES_USER: app
      POSTGRES_PASSWORD: ${DB_PASSWORD}
    ports:
      - '${VPS2_PRIVATE_IP}:5432:5432'
    volumes:
      - pg_data:/var/lib/postgresql/data
      - ./backup:/backup
    command:
      - "postgres"
      - "-c"
      - "max_connections=100"
      - "-c"
      - "shared_buffers=256MB"

volumes:
  pg_data:
COMPOSE

# Reemplazar variables en compose
sed -i "s/\\\${DB_PASSWORD}/$DB_PASSWORD/g" /opt/db/docker-compose.yml
sed -i "s/\\\${VPS2_PRIVATE_IP}/$VPS2_PRIVATE_IP/g" /opt/db/docker-compose.yml

cat > /opt/db/backup/backup_postgres.sh <<'BACKUP'
#!/usr/bin/env bash
set -euo pipefail
STAMP=\$(date +%F_%H%M)
TARGET_DIR=/opt/db/backup/dumps
mkdir -p "\$TARGET_DIR"
docker exec \$(docker ps -qf name=postgres) pg_dump -U app transporte | gzip > "\$TARGET_DIR/transporte_\${STAMP}.sql.gz"
find "\$TARGET_DIR" -type f -name '*.sql.gz' -mtime +14 -delete
BACKUP
chmod +x /opt/db/backup/backup_postgres.sh

echo ">>> Arrancando PostgreSQL..."
cd /opt/db && docker compose up -d

# Cron backup diario
(crontab -l 2>/dev/null | grep -v backup_postgres; echo "0 3 * * * /opt/db/backup/backup_postgres.sh >> /var/log/pg_backup.log 2>&1") | crontab -

echo ">>> VPS2 listo ✓"
REMOTE_SCRIPT

log "VPS2 (DB) configurado"

# =============================================================================
# VPS3 — Traccar
# =============================================================================
header "Configurando VPS3 — Traccar"

run_remote "$VPS3_PUBLIC_IP" "VPS3" bash -s <<REMOTE_SCRIPT
set -euo pipefail

echo ">>> Instalando paquetes..."
apt-get update -qq
apt-get install -y -qq docker.io docker-compose-plugin nginx certbot python3-certbot-nginx ufw > /dev/null 2>&1

echo ">>> Configurando firewall..."
ufw --force reset > /dev/null 2>&1
ufw default deny incoming > /dev/null
ufw default allow outgoing > /dev/null
ufw allow 22/tcp > /dev/null
ufw allow 80/tcp > /dev/null
ufw allow 443/tcp > /dev/null
ufw allow from 10.0.0.0/16 to any port 8082 proto tcp > /dev/null
echo "y" | ufw enable > /dev/null

echo ">>> Configurando Traccar..."
mkdir -p /opt/traccar

cat > /opt/traccar/traccar.xml <<'XML'
<?xml version='1.0' encoding='UTF-8'?>
<!DOCTYPE properties SYSTEM 'http://java.sun.com/dtd/properties.dtd'>
<properties>
    <entry key='database.driver'>com.mysql.cj.jdbc.Driver</entry>
    <entry key='database.url'>jdbc:mysql://mariadb:3306/traccar?zeroDateTimeBehavior=round&amp;serverTimezone=UTC&amp;allowPublicKeyRetrieval=true&amp;useSSL=false</entry>
    <entry key='database.user'>traccar</entry>
    <entry key='database.password'>MYSQL_PASS_PLACEHOLDER</entry>
    <entry key='web.port'>8082</entry>
    <entry key='osmand.port'>5055</entry>
    <entry key='logger.enable'>true</entry>
    <entry key='logger.level'>info</entry>
    <entry key='logger.file'>/opt/traccar/logs/tracker-server.log</entry>
</properties>
XML
sed -i "s/MYSQL_PASS_PLACEHOLDER/$MYSQL_PASSWORD/g" /opt/traccar/traccar.xml

cat > /opt/traccar/docker-compose.yml <<COMPOSE
services:
  mariadb:
    image: mariadb:11
    restart: unless-stopped
    environment:
      MYSQL_DATABASE: traccar
      MYSQL_USER: traccar
      MYSQL_PASSWORD: $MYSQL_PASSWORD
      MYSQL_ROOT_PASSWORD: $MYSQL_ROOT_PASSWORD
    volumes:
      - mariadb_data:/var/lib/mysql

  traccar:
    image: traccar/traccar:latest
    restart: unless-stopped
    depends_on:
      - mariadb
    ports:
      - '127.0.0.1:8082:8082'
      - '127.0.0.1:5055:5055'
    volumes:
      - ./traccar.xml:/opt/traccar/conf/traccar.xml
      - traccar_logs:/opt/traccar/logs

volumes:
  mariadb_data:
  traccar_logs:
COMPOSE

echo ">>> Configurando Nginx..."
cat > /etc/nginx/sites-available/gps.conf <<NGINX
server {
    listen 80;
    server_name $GPS_DOMAIN;

    location / {
        proxy_pass http://127.0.0.1:5055;
        proxy_set_header Host \\\$host;
        proxy_set_header X-Forwarded-Proto \\\$scheme;
    }
}
NGINX
ln -sf /etc/nginx/sites-available/gps.conf /etc/nginx/sites-enabled/
rm -f /etc/nginx/sites-enabled/default
nginx -t && systemctl reload nginx

echo ">>> Arrancando Traccar..."
cd /opt/traccar && docker compose up -d

echo ">>> Esperando que Traccar arranque (30s)..."
sleep 30

echo ">>> Creando usuario admin en Traccar..."
curl -sf -X POST 'http://127.0.0.1:8082/api/users' \
    -H 'Content-Type: application/json' \
    -d '{"name":"admin","email":"admin","password":"admin"}' && echo " OK" || echo " (puede que ya exista)"

echo ">>> Obteniendo certificado SSL..."
certbot --nginx -d $GPS_DOMAIN --non-interactive --agree-tos --register-unsafely-without-email --redirect 2>/dev/null || {
    echo "AVISO: certbot falló. Asegúrate de que el DNS apunta a este servidor."
    echo "Ejecuta después: certbot --nginx -d $GPS_DOMAIN"
}

echo ">>> VPS3 listo ✓"
REMOTE_SCRIPT

log "VPS3 (Traccar) configurado"

# =============================================================================
# VPS1 — Web (Symfony)
# =============================================================================
header "Configurando VPS1 — Web"

run_remote "$VPS1_PUBLIC_IP" "VPS1" bash -s <<REMOTE_SCRIPT
set -euo pipefail

echo ">>> Instalando paquetes base..."
apt-get update -qq
apt-get install -y -qq software-properties-common curl git unzip ufw nginx certbot python3-certbot-nginx docker.io docker-compose-plugin > /dev/null 2>&1

echo ">>> Instalando PHP 8.4..."
add-apt-repository -y ppa:ondrej/php > /dev/null 2>&1
apt-get update -qq
apt-get install -y -qq php8.4-fpm php8.4-cli php8.4-pgsql php8.4-xml php8.4-mbstring php8.4-curl php8.4-zip php8.4-intl php8.4-redis php8.4-opcache > /dev/null 2>&1

echo ">>> Instalando Composer..."
curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer > /dev/null 2>&1

echo ">>> Configurando firewall..."
ufw --force reset > /dev/null 2>&1
ufw default deny incoming > /dev/null
ufw default allow outgoing > /dev/null
ufw allow 22/tcp > /dev/null
ufw allow 80/tcp > /dev/null
ufw allow 443/tcp > /dev/null
echo "y" | ufw enable > /dev/null

echo ">>> Clonando repositorio..."
if [ ! -d /var/www/transporte-tracking ]; then
    git clone $REPO_URL /var/www/transporte-tracking
fi
chown -R www-data:www-data /var/www/transporte-tracking

echo ">>> Configurando PHP-FPM..."
cat > /etc/php/8.4/fpm/pool.d/www.conf <<'POOL'
[www]
user = www-data
group = www-data
listen = /run/php/php8.4-fpm.sock
listen.owner = www-data
listen.group = www-data
pm = dynamic
pm.max_children = 20
pm.start_servers = 4
pm.min_spare_servers = 2
pm.max_spare_servers = 8
pm.max_requests = 500
POOL

cat > /etc/php/8.4/fpm/conf.d/99-production.ini <<'INI'
opcache.enable=1
opcache.memory_consumption=256
opcache.max_accelerated_files=20000
opcache.validate_timestamps=0
realpath_cache_size=4096K
realpath_cache_ttl=600
INI
systemctl restart php8.4-fpm

echo ">>> Configurando Nginx..."
cp /var/www/transporte-tracking/infra/vps1-web/nginx/portal.conf /etc/nginx/sites-available/portal.conf
sed -i "s/portal.midominio.com/$PORTAL_DOMAIN/g" /etc/nginx/sites-available/portal.conf
ln -sf /etc/nginx/sites-available/portal.conf /etc/nginx/sites-enabled/
rm -f /etc/nginx/sites-enabled/default

# Primero sin SSL (para que certbot funcione)
cat > /etc/nginx/sites-available/portal.conf <<NGINX
server {
    listen 80;
    server_name $PORTAL_DOMAIN;

    root /var/www/transporte-tracking/backend/public;
    index index.php;

    location /.well-known/mercure {
        proxy_pass http://127.0.0.1:3000/.well-known/mercure;
        proxy_set_header Host \\\$host;
        proxy_set_header X-Forwarded-For \\\$proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto \\\$scheme;
        proxy_set_header Connection '';
        proxy_http_version 1.1;
        proxy_buffering off;
        proxy_cache off;
        proxy_read_timeout 24h;
    }

    location / {
        try_files \\\$uri /index.php\\\$is_args\\\$args;
    }

    location ~ ^/index\\.php(/|\\\$) {
        fastcgi_pass unix:/run/php/php8.4-fpm.sock;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME \\\$realpath_root\\\$fastcgi_script_name;
        fastcgi_param DOCUMENT_ROOT \\\$realpath_root;
        internal;
    }

    location ~ \\.php\\\$ {
        return 404;
    }
}
NGINX
nginx -t && systemctl reload nginx

echo ">>> Configurando Docker services (Mercure + Redis)..."
mkdir -p /var/www/transporte-tracking/infra/vps1-web

cat > /var/www/transporte-tracking/infra/vps1-web/.env <<ENV
MERCURE_PUBLISHER_JWT_KEY=$MERCURE_JWT_KEY
MERCURE_SUBSCRIBER_JWT_KEY=$MERCURE_JWT_KEY
PORTAL_DOMAIN=$PORTAL_DOMAIN
ENV

cd /var/www/transporte-tracking
docker compose -f infra/vps1-web/docker-compose.yml up -d

echo ">>> Creando .env.local..."
cat > /var/www/transporte-tracking/.env.local <<ENV
APP_ENV=prod
APP_SECRET=$APP_SECRET
APP_URL=https://$PORTAL_DOMAIN

DATABASE_URL=postgresql://app:$DB_PASSWORD@$VPS2_PRIVATE_IP:5432/transporte?serverVersion=16&charset=utf8

REDIS_URL=redis://127.0.0.1:6379
REDIS_SESSION_PREFIX=sess:transporte:

MERCURE_URL=http://127.0.0.1:3000/.well-known/mercure
MERCURE_PUBLIC_URL=https://$PORTAL_DOMAIN/.well-known/mercure
MERCURE_PUBLISHER_JWT_KEY=$MERCURE_JWT_KEY
MERCURE_SUBSCRIBER_JWT_KEY=$MERCURE_JWT_KEY
MERCURE_SUBSCRIBER_TOKEN_TTL=3600

TRACCAR_BASE_URL=http://$VPS3_PRIVATE_IP:8082
TRACCAR_WS_URL=ws://$VPS3_PRIVATE_IP:8082/api/socket
TRACCAR_USERNAME=admin
TRACCAR_PASSWORD=admin

POD_STORAGE=database
TRUSTED_PROXIES=127.0.0.1,REMOTE_ADDR
TRUSTED_HEADERS=x-forwarded-for,x-forwarded-host,x-forwarded-proto,x-forwarded-port,x-forwarded-prefix
ENV

echo ">>> Instalando dependencias Symfony..."
cd /var/www/transporte-tracking/backend
sudo -u www-data composer install --no-dev --optimize-autoloader --no-interaction 2>&1

echo ">>> Cache warmup..."
sudo -u www-data php bin/console cache:clear --env=prod --no-interaction
sudo -u www-data php bin/console cache:warmup --env=prod --no-interaction

echo ">>> Ejecutando migraciones..."
sudo -u www-data php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration 2>&1

echo ">>> Cargando fixtures..."
sudo -u www-data php bin/console doctrine:fixtures:load -n --env=prod 2>&1 || echo "(fixtures pueden fallar si ya existen datos)"

echo ">>> Instalando systemd worker..."
cp /var/www/transporte-tracking/infra/vps1-web/systemd/traccar-stream.service /etc/systemd/system/
systemctl daemon-reload
systemctl enable traccar-stream
systemctl start traccar-stream

echo ">>> Obteniendo certificado SSL..."
certbot --nginx -d $PORTAL_DOMAIN --non-interactive --agree-tos --register-unsafely-without-email --redirect 2>/dev/null || {
    echo "AVISO: certbot falló. Asegúrate de que el DNS apunta a este servidor."
    echo "Ejecuta después: certbot --nginx -d $PORTAL_DOMAIN"
}
systemctl reload nginx

echo ">>> VPS1 listo ✓"
REMOTE_SCRIPT

log "VPS1 (Web) configurado"

# =============================================================================
# Resumen final
# =============================================================================
header "Deploy completado"

echo "URLs:"
echo "  Portal:  https://$PORTAL_DOMAIN"
echo "  GPS:     https://$GPS_DOMAIN"
echo ""
echo "Credenciales del portal (desde fixtures):"
echo "  Admin:    admin@example.com / admin123"
echo "  Operator: operator@example.com / operator123"
echo "  (Verifica las credenciales en tus DataFixtures)"
echo ""
echo "Credenciales guardadas en: $CREDS_FILE"
echo ""
echo "Verificación rápida:"
echo "  curl -sI https://$PORTAL_DOMAIN | head -5"
echo "  ssh root@$VPS1_PUBLIC_IP systemctl status php8.4-fpm"
echo "  ssh root@$VPS1_PUBLIC_IP systemctl status traccar-stream"
echo ""
echo "IMPORTANTE: Cambia la contraseña de Traccar admin (actualmente 'admin'):"
echo "  ssh root@$VPS3_PUBLIC_IP"
echo "  curl -X PUT 'http://127.0.0.1:8082/api/users/1' \\"
echo "    -u admin:admin -H 'Content-Type: application/json' \\"
echo "    -d '{\"id\":1,\"name\":\"admin\",\"email\":\"admin\",\"password\":\"NUEVA_PASSWORD\"}'"
echo ""
