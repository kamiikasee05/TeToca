#!/bin/bash

# ============================================================
# TeToca — Clonar y buildear OpenWA
# OpenWA es el motor de WhatsApp (WhatsApp Web via Puppeteer)
# Repo original: https://github.com/rmyndharis/OpenWA
# ============================================================

set -euo pipefail

GREEN='\033[0;32m'
CYAN='\033[0;36m'
NC='\033[0m'

echo ""
echo -e "${CYAN}Clonando OpenWA...${NC}"

if [[ -d openwa ]]; then
    echo "  openwa/ ya existe, usando directorio existente."
else
    git clone https://github.com/rmyndharis/OpenWA.git openwa
    echo -e "${GREEN}✓ Repo clonado${NC}"
fi

echo ""
echo -e "${CYAN}Buildenado imagen Docker (openwa-openwa:latest)...${NC}"
echo "  Esto instala Chromium + Puppeteer, puede tardar varios minutos."
echo ""

cd openwa
docker build -t openwa-openwa:latest .
cd ..

echo ""
echo -e "${GREEN}✓ OpenWA listo. Imagen: openwa-openwa:latest${NC}"
echo ""
echo -e "Siguiente paso:  ${CYAN}chmod +x deploy.sh && ./deploy.sh${NC}"
echo ""
