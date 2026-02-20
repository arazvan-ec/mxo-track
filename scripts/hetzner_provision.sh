#!/usr/bin/env bash
# =============================================================================
# hetzner_provision.sh — Crea toda la infraestructura en Hetzner Cloud
#
# Prerequisitos:
#   1. Instalar hcloud CLI: https://github.com/hetznercloud/cli
#      brew install hcloud  (macOS)
#      snap install hcloud  (Ubuntu)
#
#   2. Crear API Token en Hetzner Cloud Console:
#      → Security → API Tokens → Generate API Token (Read & Write)
#
#   3. Configurar el CLI:
#      hcloud context create mxo-track
#      (pega el token cuando lo pida)
#
# Uso:
#   bash scripts/hetzner_provision.sh
#
# El script es idempotente: si un recurso ya existe, lo reutiliza.
# =============================================================================
set -euo pipefail

# ─── CONFIGURACIÓN (edita estos valores) ────────────────────────────────────
PROJECT_NAME="mxo-track"
LOCATION="fsn1"                    # fsn1=Falkenstein, nbg1=Nuremberg, hel1=Helsinki
SSH_KEY_NAME="mxo-deploy"         # Nombre de tu SSH key en Hetzner
NETWORK_NAME="transporte-net"
NETWORK_RANGE="10.0.0.0/16"
SUBNET_RANGE="10.0.0.0/24"

# IPs privadas asignadas
VPS1_PRIVATE_IP="10.0.2.5"
VPS2_PRIVATE_IP="10.0.2.10"
VPS3_PRIVATE_IP="10.0.3.20"

# Tipos de servidor
VPS1_TYPE="cx22"    # 2 vCPU, 4GB RAM — Web
VPS2_TYPE="cx22"    # 2 vCPU, 4GB RAM — Database
VPS3_TYPE="cx11"    # 1 vCPU, 2GB RAM — Traccar
# ─────────────────────────────────────────────────────────────────────────────

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
CYAN='\033[0;36m'
NC='\033[0m'

log()  { echo -e "${GREEN}[✓]${NC} $1"; }
warn() { echo -e "${YELLOW}[!]${NC} $1"; }
info() { echo -e "${CYAN}[→]${NC} $1"; }
fail() { echo -e "${RED}[✗]${NC} $1"; exit 1; }

# ─── Verificar hcloud CLI ───────────────────────────────────────────────────
command -v hcloud >/dev/null 2>&1 || fail "hcloud CLI no encontrado. Instálalo: https://github.com/hetznercloud/cli"

# Verificar que hay un contexto activo
hcloud context active >/dev/null 2>&1 || fail "No hay contexto hcloud activo. Ejecuta: hcloud context create $PROJECT_NAME"

echo ""
echo "=============================================="
echo "  Provisionando infraestructura Hetzner"
echo "  Proyecto: $PROJECT_NAME"
echo "  Ubicación: $LOCATION"
echo "=============================================="
echo ""

# ─── 1. SSH Key ─────────────────────────────────────────────────────────────
info "Verificando SSH key '$SSH_KEY_NAME'..."
if hcloud ssh-key describe "$SSH_KEY_NAME" >/dev/null 2>&1; then
    log "SSH key '$SSH_KEY_NAME' ya existe"
else
    warn "SSH key '$SSH_KEY_NAME' no encontrada en Hetzner."
    echo ""
    echo "  Opción A — Subir tu clave existente:"
    echo "    hcloud ssh-key create --name $SSH_KEY_NAME --public-key-from-file ~/.ssh/id_ed25519.pub"
    echo ""
    echo "  Opción B — Generar una nueva:"
    echo "    ssh-keygen -t ed25519 -f ~/.ssh/${SSH_KEY_NAME} -C '${SSH_KEY_NAME}' -N ''"
    echo "    hcloud ssh-key create --name $SSH_KEY_NAME --public-key-from-file ~/.ssh/${SSH_KEY_NAME}.pub"
    echo ""
    fail "Crea la SSH key y vuelve a ejecutar este script."
fi

# ─── 2. Red privada ─────────────────────────────────────────────────────────
info "Creando red privada '$NETWORK_NAME'..."
if hcloud network describe "$NETWORK_NAME" >/dev/null 2>&1; then
    log "Red '$NETWORK_NAME' ya existe"
else
    hcloud network create --name "$NETWORK_NAME" --ip-range "$NETWORK_RANGE"
    hcloud network add-subnet "$NETWORK_NAME" --type server --network-zone eu-central --ip-range "$SUBNET_RANGE"
    log "Red '$NETWORK_NAME' creada ($NETWORK_RANGE)"
fi

# ─── 3. Firewalls ───────────────────────────────────────────────────────────
info "Creando firewalls..."

# Firewall VPS1 (Web)
if hcloud firewall describe "fw-web" >/dev/null 2>&1; then
    log "Firewall 'fw-web' ya existe"
else
    hcloud firewall create --name "fw-web"
    hcloud firewall add-rule "fw-web" --direction in --protocol tcp --port 22 --source-ips 0.0.0.0/0 --source-ips ::/0 --description "SSH"
    hcloud firewall add-rule "fw-web" --direction in --protocol tcp --port 80 --source-ips 0.0.0.0/0 --source-ips ::/0 --description "HTTP"
    hcloud firewall add-rule "fw-web" --direction in --protocol tcp --port 443 --source-ips 0.0.0.0/0 --source-ips ::/0 --description "HTTPS"
    log "Firewall 'fw-web' creado"
fi

# Firewall VPS2 (DB)
if hcloud firewall describe "fw-db" >/dev/null 2>&1; then
    log "Firewall 'fw-db' ya existe"
else
    hcloud firewall create --name "fw-db"
    hcloud firewall add-rule "fw-db" --direction in --protocol tcp --port 22 --source-ips 0.0.0.0/0 --source-ips ::/0 --description "SSH"
    hcloud firewall add-rule "fw-db" --direction in --protocol tcp --port 5432 --source-ips 10.0.0.0/16 --description "PostgreSQL (private network only)"
    log "Firewall 'fw-db' creado"
fi

# Firewall VPS3 (Traccar)
if hcloud firewall describe "fw-traccar" >/dev/null 2>&1; then
    log "Firewall 'fw-traccar' ya existe"
else
    hcloud firewall create --name "fw-traccar"
    hcloud firewall add-rule "fw-traccar" --direction in --protocol tcp --port 22 --source-ips 0.0.0.0/0 --source-ips ::/0 --description "SSH"
    hcloud firewall add-rule "fw-traccar" --direction in --protocol tcp --port 80 --source-ips 0.0.0.0/0 --source-ips ::/0 --description "HTTP"
    hcloud firewall add-rule "fw-traccar" --direction in --protocol tcp --port 443 --source-ips 0.0.0.0/0 --source-ips ::/0 --description "HTTPS"
    hcloud firewall add-rule "fw-traccar" --direction in --protocol tcp --port 8082 --source-ips 10.0.0.0/16 --description "Traccar API (private network only)"
    log "Firewall 'fw-traccar' creado"
fi

# ─── 4. Crear VPS ───────────────────────────────────────────────────────────
create_server() {
    local NAME=$1 TYPE=$2 FIREWALL=$3 PRIVATE_IP=$4

    if hcloud server describe "$NAME" >/dev/null 2>&1; then
        log "Servidor '$NAME' ya existe"
        return
    fi

    info "Creando servidor '$NAME' ($TYPE)..."
    hcloud server create \
        --name "$NAME" \
        --type "$TYPE" \
        --image ubuntu-24.04 \
        --location "$LOCATION" \
        --ssh-key "$SSH_KEY_NAME" \
        --firewall "$FIREWALL" \
        --network "$NETWORK_NAME"

    # Esperar a que el servidor esté corriendo
    info "Esperando a que '$NAME' arranque..."
    sleep 5

    # Asignar IP privada específica si es diferente a la auto-asignada
    # hcloud asigna IPs automáticamente, pero podemos verificar
    local ACTUAL_PRIVATE
    ACTUAL_PRIVATE=$(hcloud server describe "$NAME" -o json | python3 -c "
import sys, json
data = json.load(sys.stdin)
for net in data.get('private_net', []):
    print(net['ip'])
    break
" 2>/dev/null || echo "unknown")

    log "Servidor '$NAME' creado (IP privada: $ACTUAL_PRIVATE)"
}

create_server "${PROJECT_NAME}-web"     "$VPS1_TYPE" "fw-web"     "$VPS1_PRIVATE_IP"
create_server "${PROJECT_NAME}-db"      "$VPS2_TYPE" "fw-db"      "$VPS2_PRIVATE_IP"
create_server "${PROJECT_NAME}-traccar" "$VPS3_TYPE" "fw-traccar" "$VPS3_PRIVATE_IP"

# ─── 5. Obtener IPs ─────────────────────────────────────────────────────────
echo ""
info "Obteniendo IPs de los servidores..."

get_ips() {
    local NAME=$1
    local PUBLIC_IP PRIVATE_IP
    PUBLIC_IP=$(hcloud server ip "$NAME" 2>/dev/null || echo "N/A")
    PRIVATE_IP=$(hcloud server describe "$NAME" -o json | python3 -c "
import sys, json
data = json.load(sys.stdin)
for net in data.get('private_net', []):
    print(net['ip'])
    break
" 2>/dev/null || echo "N/A")
    echo "$PUBLIC_IP $PRIVATE_IP"
}

read VPS1_PUBLIC VPS1_ACTUAL_PRIVATE <<< "$(get_ips "${PROJECT_NAME}-web")"
read VPS2_PUBLIC VPS2_ACTUAL_PRIVATE <<< "$(get_ips "${PROJECT_NAME}-db")"
read VPS3_PUBLIC VPS3_ACTUAL_PRIVATE <<< "$(get_ips "${PROJECT_NAME}-traccar")"

# ─── 6. Resumen ─────────────────────────────────────────────────────────────
echo ""
echo "=============================================="
echo "  Infraestructura creada exitosamente"
echo "=============================================="
echo ""
echo "┌─────────────┬──────────────────┬──────────────────┐"
echo "│ Servidor     │ IP Pública       │ IP Privada       │"
echo "├─────────────┼──────────────────┼──────────────────┤"
printf "│ %-11s │ %-16s │ %-16s │\n" "WEB" "$VPS1_PUBLIC" "$VPS1_ACTUAL_PRIVATE"
printf "│ %-11s │ %-16s │ %-16s │\n" "DB" "$VPS2_PUBLIC" "$VPS2_ACTUAL_PRIVATE"
printf "│ %-11s │ %-16s │ %-16s │\n" "TRACCAR" "$VPS3_PUBLIC" "$VPS3_ACTUAL_PRIVATE"
echo "└─────────────┴──────────────────┴──────────────────┘"
echo ""

# Guardar IPs en archivo para los scripts siguientes
INFRA_FILE="infra/.hetzner_ips"
cat > "$INFRA_FILE" <<EOF
# Generado por hetzner_provision.sh — $(date -Iseconds)
VPS1_PUBLIC_IP=$VPS1_PUBLIC
VPS1_PRIVATE_IP=$VPS1_ACTUAL_PRIVATE
VPS2_PUBLIC_IP=$VPS2_PUBLIC
VPS2_PRIVATE_IP=$VPS2_ACTUAL_PRIVATE
VPS3_PUBLIC_IP=$VPS3_PUBLIC
VPS3_PRIVATE_IP=$VPS3_ACTUAL_PRIVATE
EOF
log "IPs guardadas en $INFRA_FILE"

echo ""
echo "PRÓXIMOS PASOS:"
echo ""
echo "  1. Configura DNS (registros A):"
echo "     portal.tudominio.com  →  $VPS1_PUBLIC"
echo "     gps.tudominio.com     →  $VPS3_PUBLIC"
echo ""
echo "  2. Ejecuta el script de configuración completa:"
echo "     bash scripts/hetzner_deploy_all.sh portal.tudominio.com gps.tudominio.com"
echo ""
