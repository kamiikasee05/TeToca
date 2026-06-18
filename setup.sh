#!/bin/bash
set -euo pipefail

# ============================================================
# TeToca — Setup interactivo
# Pregunta datos del negocio y genera .env + configs automáticamente
# ============================================================

RED='\033[0;31m'
GREEN='\033[0;32m'
CYAN='\033[0;36m'
YELLOW='\033[1;33m'
NC='\033[0m'

echo ""
echo -e "${CYAN}============================================${NC}"
echo -e "${CYAN}  TeToca — Configuración inicial${NC}"
echo -e "${CYAN}============================================${NC}"
echo ""
echo -e "Este script va a generarte el archivo ${YELLOW}.env${NC} y los ${YELLOW}config.json${NC}"
echo -e "automáticamente. Solo necesito algunos datos de tu negocio."
echo ""

# ─── 1. Datos del negocio ────────────────────────────────────
echo -e "${GREEN}─── Datos del negocio ───${NC}"
echo ""

read -p "Nombre del negocio: " BRAND_NAME
BRAND_NAME=${BRAND_NAME:-Mi Negocio}

read -p "Frase corta (ej: Manicura profesional en Chamical): " BRAND_TAGLINE
BRAND_TAGLINE=${BRAND_TAGLINE:-Manicura profesional}

read -p "Dirección (ej: Mitre 456, Chamical): " BRAND_ADDRESS
BRAND_ADDRESS=${BRAND_ADDRESS:-Mitre 456, Chamical}

read -p "Nombre del profesional: " BRAND_PROFESIONAL
BRAND_PROFESIONAL=${BRAND_PROFESIONAL:-Profesional}

read -p "Instagram (ej: @cuchi_mua): " BRAND_INSTAGRAM
BRAND_INSTAGRAM=${BRAND_INSTAGRAM:-@mi_negocio}

read -p "Número de WhatsApp con código de país (ej: 5493826403110): " WHATSAPP_PHONE
while [[ ! "$WHATSAPP_PHONE" =~ ^[0-9]{10,15}$ ]]; do
    echo -e "${RED}El número debe tener solo dígitos, entre 10 y 15. Ej: 5491122334455${NC}"
    read -p "Número de WhatsApp: " WHATSAPP_PHONE
done

echo ""
echo -e "${GREEN}─── Configuración visual ───${NC}"
echo ""

read -p "Color principal (hex, ej: #e8a0a0): " COLOR_PRIMARY
COLOR_PRIMARY=${COLOR_PRIMARY:-#e8a0a0}

read -p "Color secundario (hex, ej: #f5f0f0): " COLOR_SECONDARY
COLOR_SECONDARY=${COLOR_SECONDARY:-#f5f0f0}

read -p "Color de acento (hex, ej: #b56576): " COLOR_ACCENT
COLOR_ACCENT=${COLOR_ACCENT:-#b56576}

read -p "Color de texto (hex, ej: #2d2d2d): " COLOR_TEXT
COLOR_TEXT=${COLOR_TEXT:-#2d2d2d}

echo ""
echo -e "${GREEN}─── Panel de administración ───${NC}"
echo ""

read -sp "Contraseña para el panel admin (no se muestra al tipear): " ADMIN_PASSWORD
echo ""
while [[ -z "$ADMIN_PASSWORD" || ${#ADMIN_PASSWORD} -lt 6 ]]; do
    echo -e "${RED}La contraseña debe tener al menos 6 caracteres.${NC}"
    read -sp "Contraseña para el panel admin: " ADMIN_PASSWORD
    echo ""
done

read -p "IP o dominio del servidor (ej: 192.168.18.11): " SERVER_HOST
SERVER_HOST=${SERVER_HOST:-localhost}

echo ""
echo -e "${CYAN}────────────────────────────────────────────${NC}"
echo -e "${YELLOW}Generando claves y archivos de configuración...${NC}"
echo ""

# ─── 2. Generar claves automáticamente ────────────────────────
SCHEDULER_API_KEY=$(openssl rand -hex 16)
OPENWA_API_KEY=$(openssl rand -hex 16)
API_MASTER_KEY="$OPENWA_API_KEY"
OPENWA_SESSION_ID=$(uuidgen)
N8N_WEBHOOK_TOKEN=$(openssl rand -hex 16)
REDIS_PASSWORD=$(openssl rand -hex 16)
ADMIN_PASSWORD_HASH=$(php -r "echo password_hash('$ADMIN_PASSWORD', PASSWORD_BCRYPT);")

CORS_ORIGIN="http://${SERVER_HOST}"
if [[ "$SERVER_HOST" == "localhost" ]]; then
    CORS_ORIGIN="http://localhost:8080"
fi

# ─── 3. Escribir .env ─────────────────────────────────────────
cat > .env <<EOF
# ============================================================
# TeToca — Variables de entorno
# Generado automáticamente por setup.sh
# ============================================================

# --- Scheduler API ---
SCHEDULER_API_KEY=${SCHEDULER_API_KEY}

# --- OpenWA (WhatsApp) ---
OPENWA_API_KEY=${OPENWA_API_KEY}
API_MASTER_KEY=${API_MASTER_KEY}
OPENWA_SESSION_ID=${OPENWA_SESSION_ID}

# --- n8n ---
N8N_WEBHOOK_TOKEN=${N8N_WEBHOOK_TOKEN}
N8N_OWNER_PHONE=${WHATSAPP_PHONE}

# --- Admin Panel ---
ADMIN_PASSWORD_HASH=${ADMIN_PASSWORD_HASH}

# --- Redis ---
REDIS_PASSWORD=${REDIS_PASSWORD}

# --- CORS ---
CORS_ORIGIN=${CORS_ORIGIN}
EOF

echo -e "  ${GREEN}✓${NC} .env generado"

# ─── 4. Escribir landing/config.json ──────────────────────────
mkdir -p landing
cat > landing/config.json <<EOF
{
    "brand": {
        "name": "${BRAND_NAME}",
        "tagline": "${BRAND_TAGLINE}",
        "address": "${BRAND_ADDRESS}",
        "whatsapp": "${WHATSAPP_PHONE}",
        "instagram": "${BRAND_INSTAGRAM}",
        "profesional": "${BRAND_PROFESIONAL}"
    },
    "colors": {
        "primary": "${COLOR_PRIMARY}",
        "secondary": "${COLOR_SECONDARY}",
        "accent": "${COLOR_ACCENT}",
        "text": "${COLOR_TEXT}",
        "background": "#ffffff"
    },
    "logo": "uploads/logo.png",
    "gallery": []
}
EOF

echo -e "  ${GREEN}✓${NC} landing/config.json generado"

# ─── 5. Escribir landing-salon/config.json ────────────────────
mkdir -p landing-salon
cat > landing-salon/config.json <<EOF
{
    "brand": {
        "name": "${BRAND_NAME}",
        "tagline": "${BRAND_TAGLINE}",
        "address": "${BRAND_ADDRESS}",
        "whatsapp": "${WHATSAPP_PHONE}",
        "instagram": "${BRAND_INSTAGRAM}",
        "profesional": "${BRAND_PROFESIONAL}"
    },
    "colors": {
        "primary": "${COLOR_PRIMARY}",
        "secondary": "${COLOR_SECONDARY}",
        "accent": "${COLOR_ACCENT}",
        "text": "${COLOR_TEXT}",
        "background": "#ffffff"
    },
    "logo": "uploads/logo.png",
    "gallery": []
}
EOF

echo -e "  ${GREEN}✓${NC} landing-salon/config.json generado"

# ─── 6. Mostrar resumen ───────────────────────────────────────
echo ""
echo -e "${CYAN}============================================${NC}"
echo -e "${CYAN}  ¡Configuración completada!${NC}"
echo -e "${CYAN}============================================${NC}"
echo ""
echo -e "${GREEN}Archivos generados:${NC}"
echo -e "  ${YELLOW}.env${NC}                  — variables de entorno"
echo -e "  ${YELLOW}landing/config.json${NC}     — datos públicos de la landing"
echo -e "  ${YELLOW}landing-salon/config.json${NC} — datos del panel admin"
echo ""
echo -e "${GREEN}Datos importantes para después:${NC}"
echo ""
echo -e "  ${CYAN}Dashboard admin:${NC}"
echo -e "    URL:      http://${SERVER_HOST}/admin"
echo -e "    Usuario:  admin"
echo -e "    Password: ${YELLOW}la que ingresaste${NC}"
echo ""
echo -e "  ${CYAN}OpenWA (WhatsApp):${NC}"
echo -e "    URL:          http://${SERVER_HOST}:2785"
echo -e "    Session ID:   ${YELLOW}${OPENWA_SESSION_ID}${NC}"
echo -e "    API Key:      ${YELLOW}${OPENWA_API_KEY}${NC}"
echo ""
echo -e "${CYAN}  ⚠  Guardá el Session ID y la API Key —${NC}"
echo -e "${CYAN}     los vas a necesitar en el paso 7 para vincular WhatsApp.${NC}"
echo ""
echo -e "${GREEN}Siguiente paso:${NC}"
echo -e "  cd openwa && docker build -t openwa-openwa:latest . && cd .."
echo -e "  chmod +x deploy.sh && ./deploy.sh"
echo ""
