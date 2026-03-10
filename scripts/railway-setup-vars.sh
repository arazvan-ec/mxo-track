#!/bin/bash
# =============================================================================
# Railway Environment Variables Setup Script
# =============================================================================
# Sets all environment variables for mxo-track services via Railway CLI.
#
# Prerequisites:
#   - Railway CLI installed: npm install -g @railway/cli
#   - Logged in: railway login
#   - Project linked: railway link (select your project)
#   - All services already created in Railway dashboard:
#     App, Worker, Mercure, Traccar, PostgreSQL (managed), Redis (managed)
#
# Usage:
#   bash scripts/railway-setup-vars.sh
# =============================================================================

set -euo pipefail

# --- Colors ---
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
BOLD='\033[1m'
NC='\033[0m'

# --- Helpers ---
info()    { echo -e "${BLUE}[INFO]${NC} $*"; }
success() { echo -e "${GREEN}[OK]${NC}   $*"; }
warn()    { echo -e "${YELLOW}[WARN]${NC} $*"; }
error()   { echo -e "${RED}[ERR]${NC}  $*"; }

generate_secret() {
    openssl rand -hex 32
}

# Set variables on a Railway service.
# Usage: set_vars "service-name" "KEY1=val1" "KEY2=val2" ...
set_vars() {
    local service="$1"
    shift

    echo ""
    echo -e "${CYAN}━━━ ${BOLD}${service}${NC}${CYAN} ━━━${NC}"

    for var in "$@"; do
        local key="${var%%=*}"
        echo -n "  $key ... "
        if railway variables set "$var" --service "$service" > /dev/null 2>&1; then
            echo -e "${GREEN}OK${NC}"
        else
            # Fallback: try linking to service first, then set
            if railway service "$service" > /dev/null 2>&1 && \
               railway variables set "$var" > /dev/null 2>&1; then
                echo -e "${GREEN}OK${NC} (via service switch)"
            else
                echo -e "${RED}FAILED${NC}"
                FAILURES=$((FAILURES + 1))
            fi
        fi
    done
}

# =============================================================================
# Preflight checks
# =============================================================================
echo -e "${BOLD}${BLUE}"
echo "╔══════════════════════════════════════════════╗"
echo "║   mxo-track — Railway Variables Setup        ║"
echo "╚══════════════════════════════════════════════╝"
echo -e "${NC}"

# Check railway CLI
if ! command -v railway &> /dev/null; then
    error "Railway CLI not found."
    echo "  Install: npm install -g @railway/cli"
    echo "  Then:    railway login && railway link"
    exit 1
fi

# Check logged in
if ! railway whoami > /dev/null 2>&1; then
    error "Not logged in. Run: railway login"
    exit 1
fi
success "Railway CLI authenticated as: $(railway whoami 2>/dev/null || echo 'unknown')"

# Check project linked
if ! railway status > /dev/null 2>&1; then
    warn "No project linked. Run: railway link"
    exit 1
fi
success "Project linked."

# =============================================================================
# Gather service names
# =============================================================================
echo ""
echo -e "${BOLD}Service names${NC} (as shown in Railway dashboard):"
echo -e "${YELLOW}Press Enter to accept defaults in brackets.${NC}"
echo ""

read -rp "  App service name [app]: " SVC_APP
SVC_APP=${SVC_APP:-app}

read -rp "  Worker service name [worker]: " SVC_WORKER
SVC_WORKER=${SVC_WORKER:-worker}

read -rp "  Mercure service name [mercure]: " SVC_MERCURE
SVC_MERCURE=${SVC_MERCURE:-mercure}

read -rp "  Traccar service name [traccar]: " SVC_TRACCAR
SVC_TRACCAR=${SVC_TRACCAR:-traccar}

read -rp "  PostgreSQL service name [Postgres]: " SVC_PG
SVC_PG=${SVC_PG:-Postgres}

read -rp "  Redis service name [Redis]: " SVC_REDIS
SVC_REDIS=${SVC_REDIS:-Redis}

read -rp "  OSRM service name [osrm-mxo]: " SVC_OSRM
SVC_OSRM=${SVC_OSRM:-osrm-mxo}

read -rp "  VROOM service name [vroom-mxo]: " SVC_VROOM
SVC_VROOM=${SVC_VROOM:-vroom-mxo}

# =============================================================================
# Gather URLs and connection strings
# =============================================================================
echo ""
echo -e "${BOLD}Connection strings${NC}"
echo -e "${YELLOW}Copy these from Railway dashboard → service → Variables tab.${NC}"
echo ""

read -rp "  DATABASE_URL (from $SVC_PG service): " DATABASE_URL
if [ -z "$DATABASE_URL" ]; then
    error "DATABASE_URL is required."
    exit 1
fi

read -rp "  REDIS_URL (from $SVC_REDIS service): " REDIS_URL
if [ -z "$REDIS_URL" ]; then
    error "REDIS_URL is required."
    exit 1
fi

echo ""
echo -e "${BOLD}Public URLs${NC}"
echo -e "${YELLOW}The domains Railway assigned (or custom domains you configured).${NC}"
echo ""

read -rp "  App public URL (e.g. https://mxo-track.up.railway.app): " APP_URL
if [ -z "$APP_URL" ]; then
    error "App URL is required."
    exit 1
fi
# Strip trailing slash
APP_URL="${APP_URL%/}"

read -rp "  Mercure public URL (e.g. https://mxo-mercure.up.railway.app): " MERCURE_PUBLIC_DOMAIN
if [ -z "$MERCURE_PUBLIC_DOMAIN" ]; then
    error "Mercure public URL is required."
    exit 1
fi
MERCURE_PUBLIC_DOMAIN="${MERCURE_PUBLIC_DOMAIN%/}"

# =============================================================================
# Generate secrets
# =============================================================================
echo ""
info "Generating secrets..."
APP_SECRET=$(generate_secret)
MERCURE_JWT_KEY=$(generate_secret)
TRACCAR_PASSWORD=$(openssl rand -base64 16 | tr -d '=/+' | head -c 20)

echo -e "  APP_SECRET:       ${CYAN}${APP_SECRET:0:12}...${NC}"
echo -e "  MERCURE_JWT_KEY:  ${CYAN}${MERCURE_JWT_KEY:0:12}...${NC}"
echo -e "  TRACCAR_PASSWORD: ${CYAN}${TRACCAR_PASSWORD}${NC}"

# Internal service URLs (Railway private networking)
TRACCAR_INTERNAL="http://${SVC_TRACCAR}.railway.internal:8082"
MERCURE_INTERNAL="http://${SVC_MERCURE}.railway.internal/.well-known/mercure"
OSRM_INTERNAL="http://${SVC_OSRM}.railway.internal:5000"
VROOM_INTERNAL="http://${SVC_VROOM}.railway.internal:3000"

# =============================================================================
# Confirm before applying
# =============================================================================
echo ""
echo -e "${BOLD}Summary — variables will be set on:${NC}"
echo "  App ($SVC_APP):     18 variables"
echo "  Worker ($SVC_WORKER):  13 variables"
echo "  Mercure ($SVC_MERCURE): 7 variables"
echo "  Traccar ($SVC_TRACCAR): 1 variable"
echo "  OSRM ($SVC_OSRM):     1 variable"
echo "  VROOM ($SVC_VROOM):   1 variable"
echo ""
read -rp "Proceed? [Y/n]: " CONFIRM
CONFIRM=${CONFIRM:-Y}
if [[ ! "$CONFIRM" =~ ^[Yy]$ ]]; then
    echo "Aborted."
    exit 0
fi

FAILURES=0

# =============================================================================
# APP service
# =============================================================================
set_vars "$SVC_APP" \
    "APP_ENV=prod" \
    "APP_SECRET=${APP_SECRET}" \
    "APP_URL=${APP_URL}" \
    "DATABASE_URL=${DATABASE_URL}" \
    "REDIS_URL=${REDIS_URL}" \
    "REDIS_SESSION_PREFIX=sess:transporte:" \
    "MERCURE_URL=${MERCURE_INTERNAL}" \
    "MERCURE_PUBLIC_URL=${MERCURE_PUBLIC_DOMAIN}/.well-known/mercure" \
    "MERCURE_PUBLISHER_JWT_KEY=${MERCURE_JWT_KEY}" \
    "MERCURE_SUBSCRIBER_JWT_KEY=${MERCURE_JWT_KEY}" \
    "MERCURE_SUBSCRIBER_TOKEN_TTL=3600" \
    "TRACCAR_BASE_URL=${TRACCAR_INTERNAL}" \
    "TRACCAR_WS_URL=ws://${SVC_TRACCAR}.railway.internal:8082/api/socket" \
    "TRACCAR_USERNAME=admin" \
    "TRACCAR_PASSWORD=${TRACCAR_PASSWORD}" \
    "POD_STORAGE=database" \
    "TRUSTED_PROXIES=REMOTE_ADDR" \
    "OSRM_URL=${OSRM_INTERNAL}" \
    "VROOM_URL=${VROOM_INTERNAL}"

# =============================================================================
# WORKER service
# =============================================================================
set_vars "$SVC_WORKER" \
    "APP_ENV=prod" \
    "APP_SECRET=${APP_SECRET}" \
    "DATABASE_URL=${DATABASE_URL}" \
    "REDIS_URL=${REDIS_URL}" \
    "MERCURE_URL=${MERCURE_INTERNAL}" \
    "MERCURE_PUBLISHER_JWT_KEY=${MERCURE_JWT_KEY}" \
    "MERCURE_SUBSCRIBER_JWT_KEY=${MERCURE_JWT_KEY}" \
    "TRACCAR_BASE_URL=${TRACCAR_INTERNAL}" \
    "TRACCAR_WS_URL=ws://${SVC_TRACCAR}.railway.internal:8082/api/socket" \
    "TRACCAR_USERNAME=admin" \
    "TRACCAR_PASSWORD=${TRACCAR_PASSWORD}" \
    "OSRM_URL=${OSRM_INTERNAL}" \
    "VROOM_URL=${VROOM_INTERNAL}"

# =============================================================================
# MERCURE service
# =============================================================================
set_vars "$SVC_MERCURE" \
    "SERVER_NAME=:80" \
    "PORT=80" \
    "MERCURE_PUBLISHER_JWT_KEY=${MERCURE_JWT_KEY}" \
    "MERCURE_PUBLISHER_JWT_ALG=HS256" \
    "MERCURE_SUBSCRIBER_JWT_KEY=${MERCURE_JWT_KEY}" \
    "MERCURE_SUBSCRIBER_JWT_ALG=HS256" \
    "MERCURE_CORS_ORIGINS=${APP_URL}"

# =============================================================================
# TRACCAR service — no custom vars needed (config baked in Dockerfile)
# but set PORT so Railway knows which port to route to
# =============================================================================
set_vars "$SVC_TRACCAR" \
    "PORT=8082"

# =============================================================================
# OSRM service — internal routing engine, no public access needed
# =============================================================================
set_vars "$SVC_OSRM" \
    "PORT=5000"

# =============================================================================
# VROOM service — internal VRP optimizer, no public access needed
# =============================================================================
set_vars "$SVC_VROOM" \
    "PORT=3000"

# =============================================================================
# Done
# =============================================================================
echo ""
if [ "$FAILURES" -gt 0 ]; then
    warn "$FAILURES variable(s) failed to set. Check the errors above."
    echo "  You can set them manually in the Railway dashboard."
else
    success "All variables set successfully!"
fi

echo ""
echo -e "${BOLD}Next steps:${NC}"
echo "  1. Deploy all services from Railway dashboard (or git push)"
echo "  2. After first deploy, load fixtures:"
echo "     railway run -s ${SVC_APP} -- php bin/console doctrine:fixtures:load -n"
echo ""
echo -e "${BOLD}Generated secrets (save these!):${NC}"
echo "  APP_SECRET=${APP_SECRET}"
echo "  MERCURE_JWT_KEY=${MERCURE_JWT_KEY}"
echo "  TRACCAR_PASSWORD=${TRACCAR_PASSWORD}"
echo ""
