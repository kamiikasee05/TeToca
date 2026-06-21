#!/bin/bash

# ============================================================
# TeToca — Setup interactivo
# Pregunta datos del negocio y genera .env + configs automáticamente
# ============================================================

RED='\033[0;31m'
GREEN='\033[0;32m'
CYAN='\033[0;36m'
YELLOW='\033[1;33m'
NC='\033[0m'

abort() { echo -e "${RED}ERROR: $1${NC}" >&2; exit 1; }

echo ""
echo -e "${CYAN}============================================${NC}"
echo -e "${CYAN}  TeToca — Configuración inicial${NC}"
echo -e "${CYAN}============================================${NC}"
echo ""
echo -e "Este script genera ${YELLOW}.env${NC}, ${YELLOW}landing/config.json${NC} y ${YELLOW}landing-salon/config.json${NC}"
echo -e "automáticamente. Solo necesito algunos datos de tu negocio."
echo ""

# ─── Verificar dependencias ──────────────────────────────────
echo -e "${CYAN}Verificando dependencias...${NC}"

missing_deps=()

if ! command -v openssl &>/dev/null; then
    missing_deps+=("openssl")
fi

if ! command -v uuidgen &>/dev/null; then
    missing_deps+=("uuidgen (paquete uuid-runtime)")
fi

if ! command -v php &>/dev/null; then
    missing_deps+=("php-cli")
fi

if ! command -v git &>/dev/null; then
    missing_deps+=("git")
fi

if ! command -v docker &>/dev/null; then
    missing_deps+=("docker")
fi

if [[ ${#missing_deps[@]} -gt 0 ]]; then
    echo -e "${YELLOW}Faltan dependencias: ${missing_deps[*]}${NC}"
    echo ""
    read -p "¿Las instalo automáticamente? (s/n): " INSTALL_DEPS
    if [[ "$INSTALL_DEPS" =~ ^[sSyY]$ ]]; then
        sudo apt update && sudo apt install -y openssl uuid-runtime php-cli git || abort "No se pudieron instalar las dependencias."
        if ! command -v docker &>/dev/null; then
            echo -e "${RED}Docker no se instala automáticamente. Instalalo primero: https://docs.docker.com/engine/install/${NC}"
            abort "Docker es obligatorio."
        fi
        echo -e "${GREEN}✓ Dependencias instaladas${NC}"
    else
        abort "Se necesitan: sudo apt install -y openssl uuid-runtime php-cli git  + Docker"
    fi
fi

echo -e "${GREEN}✓ Todo listo${NC}"
echo ""

# ─── 1. Datos del negocio ────────────────────────────────────
echo -e "${GREEN}─── Datos del negocio ───${NC}"
echo -e "  (presioná Enter para usar el valor por defecto entre paréntesis)"
echo ""

read -p "Nombre del negocio [Mi Negocio]: " BRAND_NAME
BRAND_NAME=${BRAND_NAME:-Mi Negocio}

read -p "Frase corta [Manicura profesional]: " BRAND_TAGLINE
BRAND_TAGLINE=${BRAND_TAGLINE:-Manicura profesional}

read -p "Dirección [Mitre 456, Chamical]: " BRAND_ADDRESS
BRAND_ADDRESS=${BRAND_ADDRESS:-Mitre 456, Chamical}

read -p "Nombre del profesional [Profesional]: " BRAND_PROFESIONAL
BRAND_PROFESIONAL=${BRAND_PROFESIONAL:-Profesional}

read -p "Instagram [@mi_negocio]: " BRAND_INSTAGRAM
BRAND_INSTAGRAM=${BRAND_INSTAGRAM:-@mi_negocio}

while true; do
    read -p "WhatsApp con código de país, solo números (ej: 5493826403110): " WHATSAPP_PHONE
    if [[ "$WHATSAPP_PHONE" =~ ^[0-9]{10,15}$ ]]; then
        break
    fi
    echo -e "${RED}Formato incorrecto. Solo dígitos, entre 10 y 15 caracteres.${NC}"
done

echo ""
echo -e "${GREEN}─── Configuración visual ───${NC}"
echo ""

read -p "Color principal [#e8a0a0]: " COLOR_PRIMARY
COLOR_PRIMARY=${COLOR_PRIMARY:-#e8a0a0}

read -p "Color secundario [#f5f0f0]: " COLOR_SECONDARY
COLOR_SECONDARY=${COLOR_SECONDARY:-#f5f0f0}

read -p "Color de acento [#b56576]: " COLOR_ACCENT
COLOR_ACCENT=${COLOR_ACCENT:-#b56576}

read -p "Color de texto [#2d2d2d]: " COLOR_TEXT
COLOR_TEXT=${COLOR_TEXT:-#2d2d2d}

echo ""
echo -e "${GREEN}─── Panel de administración ───${NC}"
echo ""

while true; do
    read -sp "Contraseña para el panel admin (mín 6 caracteres): " ADMIN_PASSWORD
    echo ""
    if [[ -n "$ADMIN_PASSWORD" && ${#ADMIN_PASSWORD} -ge 6 ]]; then
        break
    fi
    echo -e "${RED}La contraseña debe tener al menos 6 caracteres.${NC}"
done

read -p "IP o dominio del servidor [localhost]: " SERVER_HOST
SERVER_HOST=${SERVER_HOST:-localhost}

echo ""
echo -e "${CYAN}────────────────────────────────────────────${NC}"
echo -e "${YELLOW}Generando claves y archivos...${NC}"
echo ""

# ─── 2. Generar claves ────────────────────────────────────────
SCHEDULER_API_KEY=$(openssl rand -hex 16)       || abort "Error generando SCHEDULER_API_KEY"
OPENWA_API_KEY=$(openssl rand -hex 16)           || abort "Error generando OPENWA_API_KEY"
API_MASTER_KEY="$OPENWA_API_KEY"
OPENWA_SESSION_ID=$(uuidgen)                     || abort "Error generando OPENWA_SESSION_ID (¿uuid-runtime instalado?)"
REDIS_PASSWORD=$(openssl rand -hex 16)           || abort "Error generando REDIS_PASSWORD"

# El hash puede fallar si la password tiene caracteres especiales
ADMIN_PASSWORD_HASH=$(php -r "echo password_hash('$ADMIN_PASSWORD', PASSWORD_BCRYPT);" 2>/dev/null)
if [[ -z "$ADMIN_PASSWORD_HASH" ]]; then
    abort "Error generando el hash de la contraseña. ¿Tenés php-cli instalado? Ejecutá: sudo apt install -y php-cli"
fi

CORS_ORIGIN="http://${SERVER_HOST}"
if [[ "$SERVER_HOST" == "localhost" ]]; then
    CORS_ORIGIN="http://localhost:8080"
fi

echo -e "  ${GREEN}✓${NC} Claves generadas"

# ─── 3. Escribir .env ─────────────────────────────────────────
cat > .env <<ENVEOF
# ============================================================
# TeToca — Variables de entorno
# Generado automáticamente por setup.sh
# ============================================================

# --- Scheduler API ---
SCHEDULER_API_KEY='${SCHEDULER_API_KEY}'

# --- OpenWA (WhatsApp) ---
OPENWA_API_KEY='${OPENWA_API_KEY}'
API_MASTER_KEY='${API_MASTER_KEY}'
OPENWA_SESSION_ID='${OPENWA_SESSION_ID}'

# --- Admin Panel ---
ADMIN_PASSWORD_HASH='${ADMIN_PASSWORD_HASH}'

# --- Redis ---
REDIS_PASSWORD='${REDIS_PASSWORD}'

# --- CORS ---
CORS_ORIGIN='${CORS_ORIGIN}'
ENVEOF

# Strip Windows CRLF if present (git can introduce \r)
sed -i 's/\r$//' .env 2>/dev/null || true

if [[ -f .env ]]; then
    echo -e "  ${GREEN}✓${NC} .env generado"
else
    abort "No se pudo crear .env"
fi

# ─── 4. Escribir landing/config.json ──────────────────────────
mkdir -p landing
cat > landing/config.json <<JSONEOF
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
JSONEOF

echo -e "  ${GREEN}✓${NC} landing/config.json generado"

# ─── 5. Escribir landing-salon/config.json ────────────────────
mkdir -p landing-salon
cat > landing-salon/config.json <<JSONEOF
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
JSONEOF

echo -e "  ${GREEN}✓${NC} landing-salon/config.json generado"

# ─── 6. Clonar OpenWA si no existe ────────────────────────────
echo ""
echo -e "${CYAN}─── OpenWA (motor de WhatsApp) ───${NC}"

if [[ -d openwa ]]; then
    echo -e "  openwa/ ya existe, omitiendo clonación."
else
    echo "  Clonando desde GitHub..."
    git clone https://github.com/rmyndharis/OpenWA.git openwa || abort "Error clonando OpenWA"
    echo -e "  ${GREEN}✓${NC} Repositorio clonado"
fi

# ─── 7. Buildear imágenes Docker ──────────────────────────────
echo ""
echo -e "${CYAN}─── Buildenado imágenes Docker ───${NC}"
echo -e "  Esto puede tardar varios minutos (especialmente OpenWA)."
echo ""

COMPOSE_ARGS="-f docker-compose.yml -f docker-compose.prod.yml"

echo "  [1/3] OpenWA (WhatsApp + Chromium)..."
(cd openwa && docker build -t openwa-openwa:latest .) || abort "Error buildenado OpenWA"
echo -e "  ${GREEN}✓${NC} openwa-openwa:latest"

echo "  [2/3] Scheduler API..."
docker compose $COMPOSE_ARGS build scheduler || abort "Error buildenado scheduler"
echo -e "  ${GREEN}✓${NC} scheduler"

echo "  [3/3] Admin Panel..."
docker compose $COMPOSE_ARGS build landing-admin || abort "Error buildenado landing-admin"
echo -e "  ${GREEN}✓${NC} landing-admin"

echo ""
echo -e "${GREEN}✓ Todas las imágenes buildendas${NC}"

# ─── 8. Mostrar resumen ───────────────────────────────────────
echo ""
echo -e "${CYAN}============================================${NC}"
echo -e "${CYAN}  ¡Setup completado!${NC}"
echo -e "${CYAN}============================================${NC}"
echo ""
echo -e "${GREEN}Archivos generados:${NC}"
echo -e "  ${YELLOW}.env${NC}"
echo -e "  ${YELLOW}landing/config.json${NC}"
echo -e "  ${YELLOW}landing-salon/config.json${NC}"
echo ""
echo -e "${GREEN}Imágenes buildendas:${NC}"
echo -e "  openwa-openwa, scheduler, landing-admin"
echo ""
echo -e "${GREEN}────────────────────────────────────────${NC}"
echo ""
echo -e "  ${CYAN}Dashboard admin:${NC}"
echo -e "    URL:      http://${SERVER_HOST}/admin"
echo -e "    Usuario:  admin"
echo ""
echo -e "  ${CYAN}OpenWA — WhatsApp:${NC}"
echo -e "    URL:        http://${SERVER_HOST}:2785"
echo -e "    Session ID: ${YELLOW}${OPENWA_SESSION_ID}${NC}"
echo -e "    API Key:    ${YELLOW}${OPENWA_API_KEY}${NC}"
echo ""
echo -e "  ${CYAN}⚠  Copiá el Session ID y la API Key —${NC}"
echo -e "  ${CYAN}   los necesitás para vincular WhatsApp (ver GUIA-DEPLOY.md paso 7).${NC}"
echo ""
echo -e "${GREEN}Siguiente paso:${NC}"
echo "  chmod +x deploy.sh && ./deploy.sh"
echo ""
