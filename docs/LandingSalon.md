# Landing Page — Salón de Uñas

Página completa en `E:\TUAHORA\landing-salon\index.html`. Colores y configuración en `config.json`.

## Secciones

- Hero con foto + CTA "Reservá tu turno"
- Cards de servicios con precio/duración (cargados desde EA API)
- Cómo funciona (3 pasos)
- Galería de trabajos
- Sobre nosotras
- **Formulario de reserva custom 3 pasos:** servicio → fecha/horario → datos cliente
  - Integración directa con EA API (`POST customers` → `POST appointments`)
  - Mobile-first responsive
  - Aviso 24hs de anticipación
- Footer con WhatsApp + Instagram + dirección

## Requisitos

- Responsive mobile (375px) y desktop (1280px)
- Peso < 200KB sin imágenes externas
- Formulario funcional end-to-end (crea customer + appointment en EA)

## Relacionado

- [[EasyAppointments]] — Motor de reservas
- [[WF1-Confirmacion]] — Confirmación post-reserva
- [[README|Volver al inicio]]
