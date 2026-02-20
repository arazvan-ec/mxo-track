#!/usr/bin/env bash
# Initial setup script for VPS2 (Database) — run ONCE on fresh Ubuntu 22.04/24.04 VPS
set -euo pipefail

PRIVATE_IP="${1:?Usage: $0 <private-ip> (e.g. 10.0.2.10)}"
DB_PASSWORD="${2:?Usage: $0 <private-ip> <db-password>}"

echo "=== VPS2 DATABASE Initial Setup ==="
echo "Private IP: $PRIVATE_IP"
echo ""

# -------------------------------------------------------
# 1. System packages
# -------------------------------------------------------
echo "[1/4] Installing system packages..."
sudo apt-get update
sudo apt-get install -y docker.io docker-compose-plugin ufw

# -------------------------------------------------------
# 2. Firewall — only SSH public, PostgreSQL only from private network
# -------------------------------------------------------
echo "[2/4] Configuring firewall..."
sudo ufw default deny incoming
sudo ufw default allow outgoing
sudo ufw allow 22/tcp
# Allow PostgreSQL only from private network (10.0.0.0/16)
sudo ufw allow from 10.0.0.0/16 to any port 5432 proto tcp
echo "y" | sudo ufw enable

# -------------------------------------------------------
# 3. Create directories and config
# -------------------------------------------------------
echo "[3/4] Setting up directories..."
sudo mkdir -p /opt/db/{backup/dumps}

# Create docker-compose.yml
sudo tee /opt/db/docker-compose.yml > /dev/null <<COMPOSE
services:
  postgres:
    image: postgres:16-alpine
    restart: unless-stopped
    environment:
      POSTGRES_DB: transporte
      POSTGRES_USER: app
      POSTGRES_PASSWORD: $DB_PASSWORD
    ports:
      - '$PRIVATE_IP:5432:5432'
    volumes:
      - pg_data:/var/lib/postgresql/data
      - ./backup:/backup
    command:
      - "postgres"
      - "-c"
      - "max_connections=100"
      - "-c"
      - "shared_buffers=256MB"
      - "-c"
      - "effective_cache_size=512MB"
      - "-c"
      - "log_min_duration_statement=1000"

volumes:
  pg_data:
COMPOSE

# Backup script
sudo tee /opt/db/backup/backup_postgres.sh > /dev/null <<'BACKUP'
#!/usr/bin/env bash
set -euo pipefail
STAMP=$(date +%F_%H%M)
TARGET_DIR=/opt/db/backup/dumps
mkdir -p "$TARGET_DIR"
docker exec $(docker ps -qf name=postgres) pg_dump -U app transporte | gzip > "$TARGET_DIR/transporte_${STAMP}.sql.gz"
find "$TARGET_DIR" -type f -name '*.sql.gz' -mtime +14 -delete
echo "Backup completed: transporte_${STAMP}.sql.gz"
BACKUP
sudo chmod +x /opt/db/backup/backup_postgres.sh

# -------------------------------------------------------
# 4. Start PostgreSQL and configure backup cron
# -------------------------------------------------------
echo "[4/4] Starting PostgreSQL..."
cd /opt/db
docker compose up -d

# Daily backup at 3:00 AM
(sudo crontab -l 2>/dev/null; echo "0 3 * * * /opt/db/backup/backup_postgres.sh >> /var/log/pg_backup.log 2>&1") | sudo crontab -

echo ""
echo "=== VPS2 Database setup complete ==="
echo "PostgreSQL listening on $PRIVATE_IP:5432"
echo "Daily backups at 03:00, 14-day retention"
echo ""
echo "CONNECTION STRING for .env.local on VPS1:"
echo "DATABASE_URL=postgresql://app:$DB_PASSWORD@$PRIVATE_IP:5432/transporte?serverVersion=16&charset=utf8"
