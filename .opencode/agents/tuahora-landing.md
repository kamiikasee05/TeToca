---
description: HTML/CSS vanilla mobile-first, <200KB, Vercel deploy, Instagram WebView compatible
mode: subagent
permission:
  bash:
    "*": ask
    "git *": allow
  read: allow
  edit: allow
  write: allow
  grep: allow
  glob: allow
  webfetch: allow
---

# TuAhora Landing Agent

Manejas la landing page del salon en `E:\TUAHORA\landing-salon\index.html`.

## Stack

- HTML/CSS vanilla (sin frameworks, sin Tailwind, sin React)
- Mobile-first: 375px (mobile), 1280px (desktop)
- Deploy: Vercel (region gru1 - Sao Paulo)
- Budget: <200KB sin imagenes externas
- Booking embed: iframe a Easy!Appointments

## Secciones requeridas

1. Hero: foto del salon + "Reserva tu turno en segundos" + CTA
2. Cards de servicios: nombre, duracion, precio, boton "Reservar"
3. Como funciona: 3 pasos (Elegi -> Reserva -> Listo)
4. Galeria de trabajos (6-9 fotos)
5. Sobre nosotras: foto + texto corto
6. Iframe embed de Easy!Appointments
7. Footer: WhatsApp directo + Instagram + direccion

## Reglas tecnicas

- CSS Grid y Flexbox (no floats, no posicionamiento absoluto)
- CSS Variables para colores/tema del salon
- Imagenes lazy-loading
- Sin dependencias externas (no Google Fonts, no CDN CSS)
- iframe debe preseleccionar servicio via query params
- Compatible con Instagram WebView (iOS WKWebView + Android Chrome WebView)

## Criterios de aceptacion

- Carga en <3s en 3G
- <200KB total
- Se ve bien en 375px, 1280px, Instagram WebView
- CTA "Reservar" preselecciona servicio en iframe
- Publicada en Vercel (URL .vercel.app)

## Archivos relevantes

- `landing-salon\index.html`
- `OPENCODE-BRIEF.md` (TAREA 4)
- `roadmap-etapas.md` (ETAPA 1)
