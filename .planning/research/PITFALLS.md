# Domain Pitfalls: Sistema de Turnos Online (Salón de Uñas, Chamical)

**Domain:** Online appointment booking for small LatAm businesses via WhatsApp
**Researched:** 2026-06-13
**Confidence:** HIGH (verified via Context7 official docs + GitHub issues + project context)

---

## Critical Pitfalls

Mistakes that cause project failure — business owner loses their system or customers lose trust.

### Pitfall 1: WhatsApp Account Ban — Loss of Customer Channel (EXISTENTIAL)

**What goes wrong:** La cuenta de WhatsApp del salón es baneada permanentemente por usar Baileys (cliente no oficial). El negocio pierde acceso a TODOS sus chats de clientes. No hay apelación efectiva.

**Why it happens:** Baileys hace reverse-engineering del protocolo WhatsApp Web. WhatsApp NO lo soporta oficialmente. En los últimos 6 meses hubo olas de baneos masivos: el issue #1869 (octubre 2025) documenta 5 bots baneados en una semana, incluyendo bots de 3 años funcionando sin problemas previos. El issue #2309 (enero 2026) documenta ban permanente después de bans temporales al correr en servidor productivo. WhatsApp detecta patrones de servidor (IP de datacenter, comportamiento automatizado, sin latencia humana) y está intensificando la detección.

**Consequences:** 
- La dueña del salón pierde su número de WhatsApp (posiblemente también su número personal)
- Todos los clientes que solo se comunican por WhatsApp quedan sin contacto
- El sistema completo de turnos colapsa (WF-1, WF-2, WF-3, WF-4 dejan de funcionar)
- Recuperar un número baneado es casi imposible
- La reputación del agente local (desarrollador) queda destruida con el cliente

**Prevention:**
1. **Número de WhatsApp dedicado y separado** — NUNCA usar el número personal de la dueña. Registrar un chip nuevo exclusivo para el sistema. Si hay ban, solo se pierde el número del bot, no el negocio.
2. **Estrategia de warm-up** — Antes de automatizar, usar el número manualmente por 2-4 semanas (mensajes normales, unirse a grupos, comportamiento humano). Baileys anti-ban middleware (`kobie3717/baileys-antiban`) implementa patrones human-like, rate limiting y warm-up schedules.
3. **Rate limiting estricto** — Máximo 10-20 mensajes por minuto. Usar `createBufferedFunction` de Baileys para agrupar envíos. NUNCA enviar broadcast masivos sin límite.
4. **Rotación de QR/Session** — La sesión de Baileys se degrada con el tiempo. Guardar `auth_info_baileys` persistente (Redis o filesystem con volumen Docker) para no regenerar QR innecesariamente. La regeneración frecuente de QR es señal de actividad sospechosa.
5. **Plan de contingencia** — Tener documentado y practicado: (a) cómo volver a gestión manual de turnos si el bot cae, (b) cómo migrar a un nuevo número si hay ban, (c) mensaje pre-redactado para avisar a clientes del cambio.

**Detection:** 
- Warning signs: "code 429" (rate limited) frecuentes, desconexiones repetidas con `connectionReplaced`, `badSession`, o `forbidden`, QR code expirando más rápido de lo normal.
- Monitorear `connection.update` para códigos de desconexión. Si aparece `DisconnectReason.forbidden` o múltiples rate limits en corto período, detener toda actividad automatizada inmediatamente.

**Phase to address:** Etapa 3 (Automatización WhatsApp) — implementar rate limiting, warm-up, y número dedicado ANTES de activar workflows automáticos. Etapa 0 ya debe planificar persistencia de sesión.

**Sources:**
- Context7: Baileys docs — Disconnect reasons, `createBufferedFunction`, "no official API is available" limitation [HIGH]
- GitHub #1869: Mass ban wave October 2025 [HIGH]
- GitHub #2309: Permanent ban on production server [HIGH]
- GitHub #2340: Aggressive session rotation causing bans [HIGH]

---

### Pitfall 2: Polling Cada 2 Minutos Cuando Existen Webhooks (INNECESARIO)

**What goes wrong:** Easy!Appointments SÍ tiene webhooks nativos via `/api/v1/webhooks`. El diseño actual usa polling cada 2 minutos en WF-1, cuando podría ser tiempo real con webhooks. Esto introduce latencia innecesaria (hasta 2 min de delay en confirmación), carga al servidor, y punto de falla (si n8n falla un ciclo, se pierde confirmación).

**Why it happens:** El PROJECT.md afirma "Easy!Appointments no tiene webhooks nativos" — esto es INCORRECTO. La documentación oficial de Easy!Appointments muestra soporte completo de webhooks con 18 acciones disponibles incluyendo `appointment_save`, `appointment_delete`, y `customer_save`. El endpoint es `POST /api/v1/webhooks` y acepta `secretToken` para seguridad.

**Consequences:**
- Cliente espera hasta 2 minutos para recibir confirmación (mala experiencia)
- Si el ciclo de polling se solapa o falla, turnos pueden perderse sin confirmación
- Carga innecesaria: 720 consultas/día al API de EA incluso sin turnos nuevos
- Complejidad de estado adicional: mantener "último ID procesado" es frágil

**Prevention:**
- **Reemplazar WF-1 polling por webhook** — Configurar un webhook en Easy!Appointments con acción `appointment_save` que apunte a un endpoint de n8n (Webhook trigger node). La confirmación llega en tiempo real, sin estado que mantener, sin carga innecesaria.
- **Webhook + fallback polling como safety net** — Si se quiere redundancia, mantener polling cada 5-10 minutos como backup, no como primario.
- **Verificación de webhook** — Usar `secretToken` para autenticar que el POST viene de Easy!Appointments, no de un tercero.

**Detection:** Revisar si `POST /api/v1/webhooks` responde en la instancia de Easy!Appointments. El feature existe desde la versión que incluye API REST; verificar en `http://localhost:8080/index.php/api/v1/webhooks`.

**Phase to address:** Etapa 2 (Configuración EA) — configurar webhook como parte de CONF-03. Etapa 3 (Automatización WhatsApp) — cambiar WF-1 de polling a webhook trigger.

**Sources:**
- Context7: Easy!Appointments official API docs — `/api/v1/webhooks` with 18 action types [HIGH]
- PROJECT.md line 67: "Easy!Appointments no tiene webhooks nativos" [source of misconception]

---

### Pitfall 3: Non-Technical Owner Cannot Self-Recover (SUPPORT HELL)

**What goes wrong:** Cualquier falla — bot desconectado, turno no confirmado, QR expirado — requiere que el desarrollador intervenga manualmente. Si el developedor no está disponible (fin de semana, vacaciones, otro cliente), el sistema queda roto y el negocio no puede aceptar turnos automatizados.

**Why it happens:** El modelo de "agente local" asume que el desarrollador configura y mantiene todo. Pero no hay procedimientos de auto-recuperación ni documentación de troubleshooting para escenarios comunes. La dueña no tiene skills técnicos ni interés en aprenderlos. Sin un "modo manual" o procedimientos de degradación graceful, cada falla es una llamada de emergencia al desarrollador.

**Consequences:**
- Burnout del desarrollador por llamadas de soporte constantes
- Clientes sin confirmación durante horas o días si el dev no responde
- La dueña pierde confianza en el sistema y vuelve a gestión manual permanente
- Insostenible al escalar a más de 2-3 clientes

**Prevention:**
1. **Degradación graceful, no falla total** — Si Baileys está caído, Easy!Appointments sigue funcionando. Si n8n está caído, la landing y el booking page siguen funcionando. NUNCA un componente caído bloquea el booking de turnos.
2. **"Modo manual de emergencia" documentado** — Una guía de 1 página para la dueña con: (a) cómo verificar si el sistema está funcionando (checklist visual), (b) cómo gestionar turnos manualmente desde el panel de EA si la automatización falla, (c) cuándo y cómo contactar al desarrollador.
3. **Health dashboard simple** — Una página de status público o semi-público que muestra verde/rojo para cada componente. La dueña puede ver si "el bot está caído" sin entender qué es un bot.
4. **Auto-healing en Docker** — Configurar `restart: unless-stopped` en todos los servicios del docker-compose.yml. Healthchecks con intervalos cortos. Si un contenedor muere, Docker lo levanta.
5. **Alertas proactivas** — n8n error workflow que notifique al desarrollador POR ADELANTADO, antes de que la dueña se dé cuenta. Si algo falla, el dev ya está trabajando en ello cuando la dueña escribe.

**Detection:** Durante testing, simular fallas (detener Baileys, detener n8n, cortar red) y medir: ¿puede el negocio seguir tomando turnos? ¿cuánto tarda en detectarse? ¿la dueña sabría qué hacer?

**Phase to address:** Etapa 4 (Artefactos de Negocio) — Guía rápida para la dueña (NEGO-02) debe incluir troubleshooting. Etapa 3 (Automatización) — error workflows y auto-healing.

**Source:** Domain expertise — common failure pattern in B2SmallBiz SaaS [MEDIUM]

---

### Pitfall 4: Phone Number as Identifier Without Validation

**What goes wrong:** Easy!Appointments usa el teléfono como identificador informal del cliente. Si el cliente ingresa mal su número (sin código de país, o con formato inconsistente), los mensajes de WhatsApp nunca llegan. No hay rebote ni notificación del error. El cliente cree que reservó y confirmó; el negocio cree que el cliente recibió la confirmación. Nadie se entera del problema hasta que el cliente reclama.

**Why it happens:** Easy!Appointments tiene un campo de teléfono en el formulario de booking, pero no valida formato internacional. En Argentina, los números son 54 9 XXX XXX-XXXX (11 dígitos sin código). Un cliente puede ingresar: "3826421234", "+543826421234", "3826-421234", o incluso un número sin código de área. Baileys requiere el formato internacional `54XXXXXXXXX@s.whatsapp.net` para enviar mensajes.

**Consequences:**
- Silencio total: ni el cliente ni el negocio saben que el mensaje falló
- Turnos no confirmados que se pierden (no-show)
- Clientes frustrados que no vuelven
- Imposible diagnosticar sin logs explícitos de errores de envío

**Prevention:**
1. **Validación de teléfono en el booking form** — JavaScript en el landing/iframe que valide: exactamente 10 dígitos sin código de país (asumiendo Argentina) o exactamente 13 con prefijo +54. Formato consistente.
2. **Normalización server-side** — n8n workflow WF-0 (pre-procesamiento) que normalice cualquier formato a `54XXXXXXXXXX` antes de enviar a Baileys. Ej: "3826-421234" → "543826421234".
3. **Verificación de entrega** — Baileys devuelve estado de mensaje (sent, delivered, read). El WF-1 debe verificar que el mensaje fue "sent" exitosamente. Si no, reintentar o alertar.
4. **Fallback: email confirmación** — Easy!Appointments ya envía email de confirmación nativo. Asegurarse de que esté configurado como safety net.

**Detection:** Loggear TODOS los intentos de envío con número original y normalizado. Alertar si `sendMessage` devuelve error o no retorna success en 30 segundos.

**Phase to address:** Etapa 2 (CONF-03: reglas de reserva) — validación de teléfono en el form. Etapa 3 — normalización en WF-1 y verificación de entrega.

---

## Moderate Pitfalls

Mistakes que causan mala experiencia o deuda técnica, pero no matan el proyecto.

### Pitfall 5: Time Zone Confusion (Argentina Time)

**What goes wrong:** Easy!Appointments, n8n, Docker, y Baileys pueden operar en zonas horarias diferentes. Si EA guarda en UTC y n8n compara con hora local, los recordatorios 24h se envían a destiempo. Peor: Argentina no tiene DST, pero el servidor puede estar en UTC o en zona con DST, causando drift estacional.

**Why it happens:** Docker containers suelen usar UTC por defecto. Easy!Appointments tiene config de timezone independiente. n8n usa la timezone del sistema. Sin alineación explícita, cada componente interpreta "mañana a las 14:00" diferente.

**Prevention:**
- **Un solo source of truth** — TZ environment variable en docker-compose.yml: `TZ=America/Argentina/La_Rioja` para todos los servicios.
- **Easy!Appointments config** — Establecer timezone a `America/Argentina/La_Rioja` en `configuration.php`.
- **n8n** — Verificar que los Schedule Trigger y Date/Time nodes respeten la timezone configurada.
- **Test de zona horaria** — Crear un turno para "mañana a las 09:00" y verificar que WF-2 se dispara a las 09:00 del día anterior (24h antes), no a las 06:00 o 12:00.

**Detection:** Si los recordatorios llegan consistentemente corridos por N horas, es problema de timezone.

**Phase to address:** Etapa 0 (Stack base) — configurar TZ en docker-compose. Etapa 2 — verificar en EA config.

---

### Pitfall 6: Easy!Appointments Customization Breaking Future Upgrades

**What goes wrong:** Modificar código PHP de Easy!Appointments (colores, textos, lógica) directamente en los archivos fuente. Cuando sale una nueva versión de EA, el upgrade pisa las personalizaciones o genera conflictos de merge.

**Why it happens:** La tentación de "solo toco este archivo" es alta porque EA está en PHP y es fácil de modificar. Pero EA recibe actualizaciones de seguridad y features regularmente.

**Prevention:**
- **Customización via configuración, no código** — EA soporta customización de colores, logo, textos vía su panel admin y archivos de traducción (no core). Usar esos mecanismos.
- **Si es inevitable tocar código** — Documentar CADA cambio en [[EasyAppointments]] con archivo, línea, razón, y commit hash. Crear un diff mantenible.
- **Docker volume para custom assets** — Mantener overrides en un volumen montado, no en la imagen.
- **Preferir fork con cherry-pick** — Si los cambios son extensos, forkear EA y mantener un branch con cherry-picks de upstream.

**Phase to address:** Etapa 1 (LAND-03: personalizar colores/logo) — verificar que todo se hace vía admin panel.

---

### Pitfall 7: Session/Redis Data Loss on Docker Restart

**What goes wrong:** Si el volumen de Redis no está configurado o Docker hace cleanup, se pierde la sesión de Baileys (auth state). El bot requiere re-escanear QR code. Durante ese tiempo, cero automatización. Si la dueña no está para escanear el QR (o no sabe cómo), el sistema queda inoperable.

**Why it happens:** Baileys usa `useMultiFileAuthState` que guarda credenciales en archivos. Redis guarda estado de n8n y potencialmente estado de sesión. Sin volúmenes persistentes, `docker-compose down` borra todo.

**Prevention:**
- **Volúmenes nombrados para TODO estado** — `baileys_auth:/app/auth_info_baileys`, `redis_data:/data`, `mysql_data:/var/lib/mysql`, `n8n_data:/home/node/.n8n`. NUNCA bind mounts en path relativos que puedan perderse.
- **Backup de sesión de Baileys** — Script que copie `auth_info_baileys/` a un backup en S3 o similar una vez por semana. Si se pierde el volumen local, se restaura.
- **Health check de sesión** — Endpoint `/health` de Baileys que verifique si la sesión está autenticada (no solo si el proceso corre). Alertar si la sesión se perdió.

**Phase to address:** Etapa 0 — verificar que docker-compose.yml tiene volúmenes nombrados para todos los servicios stateful.

---

### Pitfall 8: n8n Workflow State Without Persistence Strategy

**What goes wrong:** WF-1 mantiene "último ID procesado" en memoria del workflow o en un nodo estático de n8n. Si n8n se reinicia, pierde ese estado y: (a) reenvía confirmaciones de turnos viejos (spam a clientes), o (b) no detecta turnos nuevos porque el ID "ya fue procesado".

**Why it happens:** n8n workflows son stateless por diseño. El estado debe persistirse externamente (Redis, database, file). Usar variables de workflow o nodos "Set" como memoria es frágil.

**Prevention:**
- **Con webhooks, este problema desaparece** — Sin polling, no hay estado que mantener. Razón adicional para webhooks (ver Pitfall 2).
- **Si se mantiene polling, usar Redis** — Guardar `last_processed_appointment_id` en Redis, que sobrevive reinicios de n8n.
- **Idempotencia** — Cada WF-1 debe verificar si YA envió confirmación para ese turno (guardar en Redis: `sent_confirmation:{appointment_id}` con TTL de 7 días).

**Phase to address:** Etapa 3 — diseño de workflows.

---

### Pitfall 9: Instagram In-App Browser Compatibility

**What goes wrong:** El funnel principal es Instagram → landing page → booking. El navegador embebido de Instagram (WebView) tiene limitaciones: no soporta algunas features de CSS/JS modernos, cookies bloqueadas, localStorage limitado, y a veces rompe iframes (el booking de EA va en iframe).

**Why it happens:** Instagram WebView en Android usa Chrome WebView (versión variable según device), en iOS usa WKWebView. Ambos son más restrictivos que un browser standalone. Los usuarios nunca salen de Instagram para abrir el link.

**Consequences:**
- El iframe de Easy!Appointments no carga o no envía formularios
- La landing se ve rota y el cliente abandona
- Funnel roto = cero reservas desde la fuente principal de tráfico

**Prevention:**
- **Test en Instagram WebView específicamente** — No solo Chrome DevTools mobile mode. Publicar un link de prueba en una cuenta de Instagram y abrirlo desde la app.
- **Fallback "Abrir en navegador"** — Botón prominente en la landing: "¿Problemas? Abrir en Chrome/Safari" con `target="_blank"` que fuerza salir del WebView.
- **Iframe con atributos correctos** — `allow="payment; camera; microphone"`, `sandbox` mínimo, dimensiones responsivas. Testear el POST del formulario de EA dentro del iframe en WebView.
- **Progressive enhancement** — La landing debe funcionar sin JS (HTML + CSS server-rendered). Si el iframe falla, link directo al booking page de EA como fallback.

**Phase to address:** Etapa 1 (LAND-04: responsive verificado) — incluir test en Instagram WebView. Error común: solo testear en Chrome desktop responsive mode.

---

### Pitfall 10: Keyword-Based Commands Conflicting With Natural Language (WF-3, WF-4)

**What goes wrong:** WF-3 y WF-4 usan keywords "CANCELAR" y "CAMBIAR" para detectar intención del cliente. Pero un cliente puede escribir "hola necesito cancelar el turno de las 3" o "no voy a poder ir, cancelar" — variaciones que no matchean. O peor: menciona "cancelar" en una conversación sin intención real de cancelar.

**Why it happens:** El keyword matching simple (exacto o contains) no entiende contexto, negaciones ("no quiero cancelar"), o intención parcial. La experiencia se vuelve rígida y frustrante.

**Prevention:**
- **Case-insensitive y fuzzy matching** — Aceptar "cancelar", "CANCELAR", "Cancelar", "kancelar" (typo común en mobile). Usar regex con word boundaries: `/\bcancelar\b/i`.
- **Confirmación de dos pasos** — Detectar keyword → responder "¿Querés cancelar tu turno del [fecha] a las [hora]? Responde SI para confirmar." Evita cancelaciones accidentales.
- **Manejo de ambigüedad** — Si el mensaje contiene keyword pero también otras palabras conflictivas, preguntar en lugar de actuar.
- **Considerar NLP simple en v2** — Si el volumen de mensajes crece, evaluar integración con un modelo simple de clasificación de intención (incluso reglas con regex más sofisticado).

**Detection:** Loggear TODOS los mensajes entrantes y registrar qué matcheó cada keyword. Revisar semanalmente falsos positivos (cancelaciones que no debieron dispararse) y falsos negativos (clientes que pidieron cancelar y no se detectó).

**Phase to address:** Etapa 3 — diseño de WF-3 y WF-4.

---

## Minor Pitfalls

### Pitfall 11: Landing Page Peso en Redes Móviles Lentas

**What goes wrong:** Chamical (La Rioja) puede tener conectividad móvil limitada (3G/4G inestable). LAND-05 exige <200KB sin imágenes. Si la landing excede ese peso por fuentes custom, íconos pesados, o JS innecesario, carga lento y el cliente abandona.

**Prevention:** Usar system fonts (sin Google Fonts), SVG inline para íconos (no icon fonts), HTML estático sin frameworks JS, lazy loading para galería de imágenes. Herramienta: medir con WebPageTest en conexión "Slow 3G" desde ubicación LatAm.

**Phase:** Etapa 1 — LAND-05.

---

### Pitfall 12: Infraestructura Productiva Postergada Hasta Que "Haya Cliente"

**What goes wrong:** PROJECT.md dice: "Infraestructura productiva (Cloudflare Tunnel, VPS, backups automaticos) — v2, solo si hay cliente que paga." Pero el cliente piloto YA está definido y YA necesita el sistema funcionando. Sin túnel, la dueña no puede acceder al panel de EA fuera de la red local del dev. Sin backups, cualquier falla de disco = pérdida total de datos.

**Prevention:** Priorizar Cloudflare Tunnel o ngrok en Etapa 0/1 para que el cliente piloto pueda usar el sistema incluso en fase de testing. Backups manuales (dump MySQL + copiar auth de Baileys) semanalmente desde el inicio.

**Phase:** V-01/V-02 deberían incluir túnel de desarrollo.

---

## Phase-Specific Warnings

| Phase | Likeliest Pitfalls | Mitigation |
|-------|-------------------|------------|
| **Etapa 0** (Stack) | #7: Volumes no persistentes, #12: Sin túnel ni backups | docker-compose con volúmenes nombrados. Túnel aunque sea ngrok gratis para dev |
| **Etapa 1** (Landing) | #9: Instagram WebView roto, #11: Peso >200KB | Test en WebView real. System fonts, sin frameworks |
| **Etapa 2** (Config EA) | #2: No usar webhooks, #5: Timezone inconsistente, #6: Tocar código core | Configurar webhook en CONF-03. TZ en docker-compose. Customización via admin panel |
| **Etapa 3** (WhatsApp) | #1: WhatsApp ban (EXISTENCIAL), #4: Teléfonos inválidos, #8: Estado sin persistencia, #10: Keywords frágiles | Número dedicado. Warm-up de 2 semanas. Rate limiting. Validación + normalización de teléfonos. Webhooks eliminan problema de estado. Confirmación en 2 pasos |
| **Etapa 4** (Negocio) | #3: Dueña no puede recover, #12: Sin backups | Guía de troubleshooting + modo manual + health dashboard |

---

## Sources

- **Context7: Baileys official docs** — Disconnect reasons, rate limiting, `createBufferedFunction`, ban risks [HIGH]
- **Context7: Easy!Appointments official API docs** — Webhooks via `/api/v1/webhooks`, 18 actions [HIGH]
- **Context7: n8n official docs** — Error workflows, polling vs webhook triggers [HIGH]
- **GitHub WhiskeySockets/Baileys #1869** — Mass ban wave October 2025 (5 bots, 3-year-old bots banned) [HIGH]
- **GitHub WhiskeySockets/Baileys #2309** — Permanent ban on production server after temp bans [HIGH]
- **GitHub WhiskeySockets/Baileys #2340** — Aggressive session rotation suspected as ban trigger [HIGH]
- **PROJECT.md** — Project context, architecture decisions, constraints [HIGH]
- **docs/WF1-Confirmacion.md** — Current polling-based design [HIGH]
- **docs/Baileys.md** — Current endpoints and architecture [HIGH]
