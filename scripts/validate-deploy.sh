#!/bin/bash
# ============================================================
# TeToca — Post-Deploy Validation Checklist
# Ejecutar después de cada deploy para verificar todo el flujo
# ============================================================
set -e

RED='\033[0;31m'; GREEN='\033[0;32m'; YELLOW='\033[1;33m'; NC='\033[0m'
pass() { echo -e "  ${GREEN}✓${NC} $1"; }
fail() { echo -e "  ${RED}✗${NC} $1 — $2"; FAILED=1; }
warn() { echo -e "  ${YELLOW}⚠${NC} $1"; }

FAILED=0
API="http://localhost:3000/api/v1"
N8N="http://localhost:5678"
API_KEY="${SCHEDULER_API_KEY:-}"

echo "============================================"
echo "  TeToca — Validación Post-Deploy"
echo "============================================"
echo ""

# ─── 1. Container Health ───────────────────────────────────
echo "─── 1. Servicios ───"
for svc in scheduler n8n tetoca_openwa landing landing-admin redis; do
  if docker ps --format '{{.Names}}' | grep -q "^${svc}$"; then
    pass "$svc corriendo"
  else
    fail "$svc" "no está corriendo"
  fi
done

# ─── 2. API Health ─────────────────────────────────────────
echo ""
echo "─── 2. Scheduler API ───"
HEALTH=$(curl -sf http://localhost:3000/health 2>/dev/null && echo "ok" || echo "fail")
if [ "$HEALTH" = "ok" ]; then pass "health OK"; else fail "health" "scheduler no responde"; fi

SERVICES=$(curl -sf "$API/services" 2>/dev/null | python3 -c "import sys,json; print(len(json.load(sys.stdin)))" 2>/dev/null || echo "0")
if [ "$SERVICES" -gt 0 ]; then pass "$SERVICES servicios cargados"; else fail "servicios" "no hay servicios"; fi

PROVIDER=$(curl -sf -H "x-api-key: $API_KEY" "$API/appointments" 2>/dev/null | python3 -c "import sys; print('ok')" 2>/dev/null || echo "fail")
if [ "$PROVIDER" = "ok" ]; then pass "GET /appointments (con auth) OK"; else fail "appointments" "falló con API key"; fi

# ─── 3. Booking Flow ───────────────────────────────────────
echo ""
echo "─── 3. Flujo de reserva ───"
CID=$(curl -sf -X POST "$API/customers" \
  -H 'Content-Type: application/json' \
  -d '{"firstName":"Test QA","phone":"54911111111"}' 2>/dev/null | python3 -c "import sys,json; d=json.load(sys.stdin); print(d.get('id',''))" 2>/dev/null || echo "")
if [ -n "$CID" ]; then pass "POST /customers (id=$CID)"; else fail "customers" "no se pudo crear cliente de prueba"; fi

if [ -n "$CID" ]; then
  APPT=$(curl -sf -X POST "$API/appointments" \
    -H 'Content-Type: application/json' \
    -d "{\"start\":\"2026-12-31 10:00:00\",\"end\":\"2026-12-31 11:00:00\",\"serviceId\":1,\"customerId\":$CID,\"providerId\":5}" 2>/dev/null)
  APPT_ID=$(echo "$APPT" | python3 -c "import sys,json; print(json.load(sys.stdin).get('id',''))" 2>/dev/null || echo "")
  if [ -n "$APPT_ID" ]; then
    pass "POST /appointments creó turno id=$APPT_ID"
  else
    fail "appointments" "no se pudo crear turno: $APPT"
  fi
fi

# ─── 4. WhatsApp Connectivity ──────────────────────────────
echo ""
echo "─── 4. WhatsApp ───"
SESSIONS=$(curl -sf -H "x-api-key: ${OPENWA_API_KEY:-}" http://localhost:2785/api/sessions 2>/dev/null | python3 -c "import sys,json; d=json.load(sys.stdin); print(len(d))" 2>/dev/null || echo "0")
if [ "$SESSIONS" -gt 0 ]; then pass "OpenWA: $SESSIONS sesiones"; else warn "OpenWA sin sesiones (QR no vinculado)"; fi

WA_TEST=$(curl -sf -X POST "$API/whatsapp/send" \
  -H "x-api-key: $API_KEY" \
  -H 'Content-Type: application/json' \
  -d '{"phone":"54911111111","message":"Test QA - ignorar"}' 2>/dev/null | python3 -c "import sys,json; d=json.load(sys.stdin); print(d.get('success',''))" 2>/dev/null || echo "")
if [ "$WA_TEST" = "True" ]; then pass "WhatsApp proxy funciona"; else warn "WhatsApp proxy no confirmó (puede ser sesión no vinculada)"; fi

# ─── 5. n8n Workflows ──────────────────────────────────────
echo ""
echo "─── 5. n8n Workflows ───"
WF_COUNT=$(curl -sf "$N8N/rest/workflows" \
  -H "X-N8N-API-KEY: ${N8N_API_KEY:-}" 2>/dev/null | python3 -c "import sys,json; d=json.load(sys.stdin); print(d.get('count',0) if isinstance(d,dict) else 0)" 2>/dev/null || echo "0")
if [ "$WF_COUNT" -gt 0 ]; then pass "$WF_COUNT workflows en n8n"; else warn "n8n workflows no verificados (falta N8N_API_KEY env)"; fi

# ─── 6. Landing ────────────────────────────────────────────
echo ""
echo "─── 6. Landing ───"
LANDING=$(curl -sf http://localhost:80/ 2>/dev/null | head -1 || echo "")
if echo "$LANDING" | grep -q "DOCTYPE"; then pass "landing carga"; else fail "landing" "no carga"; fi

ADMIN=$(curl -sf http://localhost:8081/admin/ 2>/dev/null | head -1 || echo "")
if echo "$ADMIN" | grep -q "DOCTYPE"; then pass "admin panel carga"; else fail "admin" "no carga"; fi

# ─── 7. Resumen ────────────────────────────────────────────
echo ""
echo "============================================"
if [ "$FAILED" = "1" ]; then
  echo -e "  ${RED}VALIDACIÓN FALLIDA — revisar items arriba${NC}"
  exit 1
else
  echo -e "  ${GREEN}VALIDACIÓN OK — sistema operativo${NC}"
  echo ""
  echo "  Próximo paso: vincular WhatsApp si no está conectado"
  echo "    http://$(hostname -I | awk '{print $1}'):2785/session/<SESSION_ID>"
  exit 0
fi
