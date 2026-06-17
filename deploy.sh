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
    echo "  => Copy .env.example to .env and fill in all values before deploying."
    exit 1
fi

echo "[1/5] Validating .env ..."
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
    exit 1
fi
echo "  All required variables look valid."

# --- Pull public images ---
echo ""
echo "[2/5] Pulling public images ..."
docker compose $COMPOSE_ARGS pull landing n8n redis mailpit 2>&1 || echo "  WARNING: Some images may already be present locally (non-fatal)."

# --- Build local images ---
echo ""
echo "[3/5] Building local images (scheduler, landing-admin) ..."
docker compose $COMPOSE_ARGS build scheduler landing-admin

# --- Start stack ---
echo ""
echo "[4/5] Starting services ..."
docker compose $COMPOSE_ARGS up -d

# --- Status ---
echo ""
echo "[5/5] Deployment complete."
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
echo "  Public  → http://0.0.0.0:8080"
echo "  Admin   → http://127.0.0.1:8081  (localhost only)"
echo "  n8n     → http://127.0.0.1:5678  (localhost only)"
echo "  Scheduler → http://127.0.0.1:3000 (localhost only)"
echo "============================================"
