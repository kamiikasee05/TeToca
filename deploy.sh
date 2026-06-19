#!/bin/bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$SCRIPT_DIR"

COMPOSE_ARGS="-f docker-compose.yml -f docker-compose.prod.yml"

echo "============================================"
echo "  TeToca — Production Deploy"
echo "============================================"
echo ""

# --- Check prerequisites ---
if ! command -v docker &>/dev/null; then
    echo "ERROR: docker is not installed or not in PATH"
    exit 1
fi

if [ ! -f .env ]; then
    echo "ERROR: .env file not found."
    echo "  => Run ./setup.sh first to generate it."
    exit 1
fi

echo "[1/4] Validating .env ..."
# shellcheck disable=SC1091
source .env
required_vars=(SCHEDULER_API_KEY API_MASTER_KEY OPENWA_SESSION_ID N8N_WEBHOOK_TOKEN ADMIN_PASSWORD_HASH REDIS_PASSWORD)
missing=0
for var in "${required_vars[@]}"; do
    if [ -z "${!var:-}" ] || [[ "${!var}" == CAMBIAR* ]]; then
        echo "  MISSING or unset placeholder: $var"
        missing=1
    fi
done
if [ "$missing" -eq 1 ]; then
    echo "ERROR: Please set all required variables in .env before deploying."
    echo "  => Run ./setup.sh first to generate them."
    exit 1
fi
echo "  All required variables look valid."

# --- Pull public images ---
echo ""
echo "[2/4] Pulling public images ..."
docker compose $COMPOSE_ARGS pull landing n8n redis 2>&1 || echo "  WARNING: Some images may already be present locally (non-fatal)."

# --- Start stack ---
echo ""
echo "[3/4] Starting services ..."
docker compose $COMPOSE_ARGS up -d

# --- Status ---
echo ""
echo "[4/4] Deployment complete."
echo ""
echo "=== Running containers ==="
docker compose $COMPOSE_ARGS ps
echo ""

# --- Logs tail ---
echo "=== Logs (last 30 lines) ==="
docker compose $COMPOSE_ARGS logs --tail=30
echo ""
echo "============================================"
echo "  Deploy finished successfully."
echo "  Public  → http://0.0.0.0:80"
echo "  Admin   → http://0.0.0.0/admin"
echo "============================================"
