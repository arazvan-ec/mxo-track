#!/usr/bin/env bash
# Initial setup script for VPS3 (Traccar) — run ONCE on fresh Ubuntu 22.04/24.04 VPS
set -euo pipefail

DOMAIN="${1:?Usage: $0 <gps-domain> <mysql-password> (e.g. gps.example.com secretpass)}"
MYSQL_PASSWORD="${2:?Usage: $0 <gps-domain> <mysql-password>}"
MYSQL_ROOT_PASSWORD="${3:-$(openssl rand -hex 16)}"

echo "=== VPS3 TRACCAR Initial Setup ==="
echo "Domain: $DOMAIN"
echo ""

# -------------------------------------------------------
# 1. System packages
# -------------------------------------------------------
echo "[1/6] Installing system packages..."
sudo apt-get update
sudo apt-get install -y \
    docker.io docker-compose-plugin \
    nginx certbot python3-certbot-nginx ufw

# -------------------------------------------------------
# 2. Firewall
# -------------------------------------------------------
echo "[2/6] Configuring firewall..."
sudo ufw default deny incoming
sudo ufw default allow outgoing
sudo ufw allow 22/tcp
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp
# Allow Traccar API from private network only
sudo ufw allow from 10.0.0.0/16 to any port 8082 proto tcp
echo "y" | sudo ufw enable

# -------------------------------------------------------
# 3. Traccar config
# -------------------------------------------------------
echo "[3/6] Setting up Traccar..."
sudo mkdir -p /opt/traccar

# traccar.xml with MariaDB backend
sudo tee /opt/traccar/traccar.xml > /dev/null <<XML
<?xml version='1.0' encoding='UTF-8'?>
<!DOCTYPE properties SYSTEM 'http://java.sun.com/dtd/properties.dtd'>
<properties>
    <entry key='database.driver'>com.mysql.cj.jdbc.Driver</entry>
    <entry key='database.url'>jdbc:mysql://mariadb:3306/traccar?zeroDateTimeBehavior=round&amp;serverTimezone=UTC&amp;allowPublicKeyRetrieval=true&amp;useSSL=false</entry>
    <entry key='database.user'>traccar</entry>
    <entry key='database.password'>$MYSQL_PASSWORD</entry>
    <entry key='web.port'>8082</entry>
    <entry key='osmand.port'>5055</entry>
    <entry key='logger.enable'>true</entry>
    <entry key='logger.level'>info</entry>
    <entry key='logger.file'>/opt/traccar/logs/tracker-server.log</entry>
</properties>
XML

# docker-compose.yml
sudo tee /opt/traccar/docker-compose.yml > /dev/null <<COMPOSE
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

# -------------------------------------------------------
# 4. Nginx config
# -------------------------------------------------------
echo "[4/6] Configuring Nginx..."
sudo tee /etc/nginx/sites-available/gps.conf > /dev/null <<NGINX
server {
    listen 80;
    server_name $DOMAIN;
    return 301 https://\$host\$request_uri;
}

server {
    listen 443 ssl;
    server_name $DOMAIN;

    ssl_certificate /etc/letsencrypt/live/$DOMAIN/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/$DOMAIN/privkey.pem;
    ssl_protocols TLSv1.2 TLSv1.3;

    # OsmAnd GPS protocol endpoint
    location / {
        proxy_pass http://127.0.0.1:5055;
        proxy_set_header Host \$host;
        proxy_set_header X-Forwarded-Proto https;
    }
}
NGINX

sudo ln -sf /etc/nginx/sites-available/gps.conf /etc/nginx/sites-enabled/
sudo rm -f /etc/nginx/sites-enabled/default

# -------------------------------------------------------
# 5. SSL Certificate
# -------------------------------------------------------
echo "[5/6] Obtaining SSL certificate..."
sudo certbot --nginx -d "$DOMAIN" --non-interactive --agree-tos --redirect --email admin@"$DOMAIN" || {
    echo "WARNING: Certbot failed. Make sure DNS for $DOMAIN points to this server."
    echo "Run manually: sudo certbot --nginx -d $DOMAIN"
}

sudo systemctl reload nginx

# -------------------------------------------------------
# 6. Start services
# -------------------------------------------------------
echo "[6/6] Starting Traccar..."
cd /opt/traccar
docker compose up -d

# Wait for Traccar to start
echo "Waiting for Traccar to start..."
sleep 15

# Create initial admin user
echo "Creating Traccar admin user..."
curl -sf -X POST 'http://127.0.0.1:8082/api/users' \
    -H 'Content-Type: application/json' \
    -d '{"name":"admin","email":"admin","password":"admin"}' && echo " OK" || echo " (may already exist)"

echo ""
echo "=== VPS3 Traccar setup complete ==="
echo "GPS endpoint: https://$DOMAIN"
echo "Traccar API (private): http://127.0.0.1:8082"
echo ""
echo "CHANGE the Traccar admin password after first login!"
echo "MySQL root password: $MYSQL_ROOT_PASSWORD"
echo ""
echo "For .env.local on VPS1, use the PRIVATE IP of this VPS:"
echo "TRACCAR_BASE_URL=http://<VPS3_PRIVATE_IP>:8082"
echo "TRACCAR_WS_URL=ws://<VPS3_PRIVATE_IP>:8082/api/socket"
