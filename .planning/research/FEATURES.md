# Feature Landscape

**Domain:** Online appointment booking system for small businesses in Argentina  
**Researched:** 2026-06-13  
**Context:** Greenfield project — nail salon pilot client (1 professional, multiple services), local agent model, WhatsApp-first communication  

---

## Research Methodology

| Source | Contribution | Confidence |
|--------|-------------|------------|
| Easy!Appointments official site & features page | Core booking engine capabilities (out-of-box EA features) | HIGH |
| Easy!Appointments REST API docs (Context7) | API surface: appointments, services, providers, customers, availabilities, unavailabilities | HIGH |
| Easy!Appointments v1.6.0 release notes | New features: Jitsi/Google Meet video, ALTCHA CAPTCHA, GDPR, CalDAV improvements | HIGH |
| Baileys library docs (Context7) | WhatsApp messaging capabilities: text send/receive, message listeners, QR auth | HIGH |
| PROJECT.md & existing docs | Project-specific context, validated requirements, out-of-scope decisions | HIGH |
| Domain knowledge (Argentina small business) | WhatsApp penetration (95%+), Instagram funnel, ARS currency norms, cash/transfer payment preferences | MEDIUM |

**Overall confidence: HIGH.** Core booking features verified via official sources. Argentina-specific insights from project context and domain knowledge.

---

## Table Stakes

Features users expect. Missing any of these = product feels broken or incomplete. These are what competitors (Calendly, Cal.com, turnos.com.ar, any salon booking tool) all have.

| Feature | Why Expected | Complexity | Notes |
|---------|-------------|------------|-------|
| **Service catalog** — list services with name, description, duration, price | Customer must know what they're booking. Every booking system starts here. | Low | EA provides this natively. Services: manicura, pedicura, kapping, nail art, combos. |
| **Availability discovery** — show available dates and time slots for a service+provider | The core value proposition. Customer picks a service and sees when it's available. | Low | EA `/api/v1/availabilities` endpoint returns slots. Used to drive booking form. |
| **Booking flow** — step-by-step: select service → pick date/time → fill info → confirm | Must be frictionless on mobile. This is the conversion funnel. | Low-Med | EA booking page is a 4-step wizard (service → date/time → info → confirm). Works as iframe embed. |
| **Booking confirmation** — notification that appointment was successfully created | User needs immediate feedback that booking worked. | Low | EA sends email natively. TuAhora adds WhatsApp confirmation (WF-1). |
| **Customer data collection** — name, phone, email at minimum | Phone is critical for WA reminders. Email optional but stored for future. | Low | EA collects: first name, last name, email, phone, address, city, zip, notes. |
| **Provider configuration** — working hours, breaks, days off per professional | Without this, booking slots have no boundaries. | Low | EA supports `workingPlan` per provider (day-of-week hours, breaks). API: POST/PUT `/api/v1/providers`. |
| **Booking rules** — how far in advance clients can book, minimum notice, cancellation window | Prevents last-minute chaos and overbooking. | Low | EA supports booking rules: future booking limit, cancellation timeout, phone mandatory flag. |
| **Calendar backend view** — admin sees all appointments in calendar format | Owner needs to see their day/week at a glance. | Low | EA provides backend calendar view with day/week/month layouts. |
| **Mobile-responsive booking page** | Instagram → landing → booking is the primary funnel. 90%+ traffic from mobile. | Low | EA booking page is responsive. Landing page (LAND-01) must be verified at 375px. |
| **Cancel appointment capability** | Customers expect to cancel. Without it, no-shows increase. | Low-Med | EA API: DELETE `/api/v1/appointments/:id`. WF-3 adds WhatsApp keyword cancel. |
| **Reschedule appointment capability** | Customers change plans. Must flow smoothly. | Med | EA API: PUT `/api/v1/appointments/:id`. WF-4 adds WhatsApp-based rescheduling. |
| **Spanish interface** | Argentina. Non-negotiable. | Low | EA supports 30+ languages including Spanish (es). Must verify translation completeness. |
| **ARS currency display** | Customers see prices in pesos. Builds trust and avoids confusion. | Low | EA service entity has `price` and `currency` fields. Set currency = ARS. |
| **Basic spam protection** | Prevents bot bookings that pollute the calendar. | Low | EA v1.6 added ALTCHA CAPTCHA integration. Enable during CONF-03. |

**All 14 table stakes features are supported or directly mapped to Easy!Appointments capabilities.** None require custom development — they need proper configuration.

---

## Differentiators

Features that set TuAhora apart from generic booking tools (Calendly, Cal.com, Google Calendar booking) and create value specific to the Argentine small business + local agent model.

### WhatsApp Automation (Core Differentiator)

Every Argentinian has WhatsApp. Small business owners already manage their entire client communication there. TuAhora brings the booking system TO WhatsApp instead of forcing clients to use email or a separate app.

| Feature | Value Proposition | Complexity | Status |
|---------|-------------------|------------|--------|
| **WF-1: WhatsApp instant confirmation** — when client books via landing, they get a WA message within 2 minutes confirming date, time, service | No other booking tool in the Argentine market does this for the price point. Reduces "me confirmaste?" anxiety calls. | Med | Planned (WHAT-01). n8n polls EA appointments every 2 min → Baileys sends WA. |
| **WF-2: WhatsApp 24h reminder** — day before appointment, client gets WA: "Mañana tenes turno de [servicio] a las [hora]" | Dramatically reduces no-shows. In Argentina, WA messages are read within minutes. Email reminders are often ignored. | Med | Planned (WHAT-02). n8n daily schedule → query tomorrow's appointments → Baileys sends. |
| **WF-3: WhatsApp cancellation** — client sends "CANCELAR" and the system cancels their appointment, confirms via WA | Zero friction. Client doesn't need to find the booking link, log in, or call. They just text. | Med | Planned (WHAT-03). n8n webhook receives incoming WA → matches phone to appointment → cancels via EA API → confirms via WA. |
| **WF-4: WhatsApp rescheduling** — client sends "CAMBIAR" and system cancels current + sends booking link | Solves the #1 support request: "puedo cambiar el turno?" Without needing the owner to intervene. | Med-High | Planned (WHAT-04). Multi-step workflow: find appointment → cancel → send new booking link. Complexity comes from edge cases (no current booking, multiple bookings). |

### Local Agent Model (Business Differentiator)

The "agente local" model is what makes TuAhora sustainable. The business owner (dueña) pays a monthly fee and never touches configuration.

| Feature | Value Proposition | Complexity | Status |
|---------|-------------------|------------|--------|
| **Zero-config onboarding per client** — developer sets up: EA instance, services, provider, WA number, landing page | Turns a complex tech stack into a managed service. Owner just receives appointments. | Low (repetitive, not complex) | Planned. Developer configures everything once per client. |
| **Branded landing page** — mobile-first landing with salon photos, services, Instagram link, and embedded booking | Professional online presence without the owner needing a website. Instagram → landing → booking funnel. | Low-Med | Planned (LAND-01 to LAND-06). Static HTML on Vercel, <200KB. |
| **Service combos and packages** — "Manicura + Pedicura" as a single bookable service with combined duration/price | Upsells without the owner doing anything. Relevant for salons. | Low | EA services configured with combined durations (e.g., 90 min combo). |
| **Customer phone as primary identifier** — phone number is the key for WA communication, repeat customer recognition | Replaces email as the customer ID. Repeat clients recognized by phone. | Low | EA stores phone, but needs phone-as-lookup in n8n workflows. |
| **Simple business artifacts** — 1-page guide for owner, service contract, commercial proposal PDF | Professionalism. Sets expectations. Makes the business replicable for future clients. | Low | Planned (NEGO-01 to NEGO-04). Non-technical deliverables. |

### Argentina-Specific Adaptations

| Feature | Value Proposition | Complexity | Notes |
|---------|-------------------|------------|-------|
| **Instagram-first funnel** — optimized for Instagram bio link → landing → booking | In Argentina, small salons get 80%+ of new clients via Instagram. | Low | Landing page must render perfectly in Instagram in-app browser. |
| **WhatsApp-only communication** — no reliance on email for client notifications | Email penetration is lower, and small business clients don't check email. WA is the channel. | Low | Already planned in WHAT-01 through WHAT-04. |
| **Flexible payment mentions** — booking confirmation includes "Formas de pago: efectivo o transferencia" | No online payment integration. Argentina runs on cash and bank transfers (CBU/alias). | Low | Add payment info text block to WF-1 confirmation message template. |

---

## Anti-Features

Features to explicitly NOT build for MVP. Each has a clear reason for exclusion and a trigger condition for when it might make sense.

| Anti-Feature | Why Avoid | What to Do Instead | Revisit When |
|-------------|-----------|-------------------|-------------|
| **Online payments (Stripe, MercadoPago, etc.)** | Adds enormous complexity: payment gateway integration, refunds, failure handling, PCI compliance. In Argentina, small salon clients pay cash or transfer post-service. Not expected. | Include payment method info text in confirmation messages: "Pago en efectivo o transferencia al finalizar el servicio." | When 3+ clients request it or competition forces it. |
| **Multiple professionals per business** | Explicitly out of scope. Adds scheduling complexity (conflict resolution, provider assignment logic). Pilot has 1 professional. | Single provider = Laura. EA handles this natively with one provider. | V2. When first multi-provider client signs. |
| **Custom domain per client** | Infrastructure complexity (DNS, SSL certs, Cloudflare config). Not needed for MVP — the link goes in Instagram bio anyway. | Use Vercel subdomain or TuAhora domain path. Clients don't care about the URL. | V2 (when infrastructure production ready). |
| **Native mobile app (iOS/Android)** | Massive development investment. Small business clients won't download an app. They use WhatsApp. | Mobile web + WhatsApp. The entire product lives in the browser and WA. | Not planned. |
| **Complex analytics dashboard** — revenue reports, occupancy rates, client retention metrics | The pilot salon owner doesn't want analytics. She wants to stop managing appointments manually. | Calendar view is sufficient. Owner can see their day at a glance. | When a client specifically requests it. |
| **Email-only notifications** | Argentina is WhatsApp country. Email is the fallback, not the primary channel. | WhatsApp for client notifications. Email only for system alerts to the developer/agent. | If a client's clients don't use WhatsApp (unlikely). |
| **Google Calendar sync for the owner** | Requires owner to have and configure a Google account. Breaks the "zero-config" promise. EA supports it natively but skip setup. | Developer can optionally enable it if owner already uses Google Calendar. Not part of standard setup. | When an owner explicitly requests it and has a Google account. |
| **Jitsi/Google Meet video links** | New in EA v1.6. Completely irrelevant for a nail salon — clients come to the physical location. | Skip video integration entirely. | If the model expands to virtual services (consulting, therapy, tutoring). |
| **Social media auto-posting** — auto-post available slots to Instagram/Facebook | Adds maintenance burden, platform API changes, auth token management. | Manual Instagram posts by the owner (which they already do). | If a client specifically requests it and has business social media accounts. |
| **Multi-language interface** | Pilot is in Argentina. Spanish only. Adding English adds translation maintenance cost. | EA supports 30+ languages out of the box. Leave Spanish as default. Add other languages only if needed. | When expanding to non-Spanish markets. |
| **Loyalty/rewards program** — points, discounts, "5th visit free" | Premature optimization. First validate that the core booking flow works and clients adopt it. | Manual tracking by the owner (which they already do). | V2 or when a client asks for it. |
| **Waitlist** — notify client when a slot opens up | Complex logic: queue management, time-bound offers, notification timing. Pilot has 1 provider — if it's full, it's full. | Offer alternative times, or the client texts "CAMBIAR" later. | When utilization > 80% consistently. |

---

## Feature Dependencies

```
Service catalog → Availability discovery → Booking flow → Booking confirmation
                                                              ↓
                                              WF-1 (WA Confirmation) depends on:
                                                - EA booking complete (polling detects new appointment)
                                                - Baileys connected + authenticated
                                                - n8n workflow running

WF-2 (WA Reminder) depends on:
  - Appointments exist in EA
  - Baileys connected + authenticated
  - n8n scheduled workflow

WF-3 (WA Cancellation) depends on:
  - Incoming WA webhook from Baileys → n8n working
  - EA API DELETE endpoint accessible
  - Phone number → appointment matching logic

WF-4 (WA Rescheduling) depends on:
  - WF-3 working (cancellation is a sub-step)
  - Booking link available to send

Landing page → EA booking iframe embed
Landing page → Instagram bio link
```

### Phase Dependencies

```
Phase 1 (Visual): Landing + Booking Page
  ↓ (provides the booking surface)
Phase 2 (Configuration): EA Services/Provider/Rules
  ↓ (provides the data layer)
Phase 3 (Automation): WhatsApp Workflows
  ↓ (provides the differentiator)
Phase 4 (Business): Contracts, Guides, Proposals
  (parallel, not dependent)
```

---

## MVP Recommendation

### Must Have (MVP = All Table Stakes + 2 Differentiators)

**Prioritize building in this order:**

1. **Service catalog** (EA configuration: services with name, duration, price in ARS)
2. **Provider setup** (EA configuration: Laura's working hours, breaks)
3. **Booking flow working end-to-end** (landing page → iframe → service → slot → confirm → confirmed in EA)
4. **WhatsApp confirmation (WF-1)** — the single most impactful differentiator. Turns a silent booking into an immediate, reassuring interaction.
5. **Booking rules configured** (minimum notice, future limit, phone required)
6. **WhatsApp 24h reminder (WF-2)** — second most impactful. Directly reduces no-shows.
7. **Landing page deployed** (Vercel, mobile-first, <200KB, Instagram-optimized)
8. **Spanish interface verified** (all EA labels, WA messages in Spanish)

### Defer to Post-MVP

- WF-3 (Cancelación por WhatsApp): **Defer** — useful but less critical than confirmation and reminder. Clients who need to cancel can call (as they do today).
- WF-4 (Reagendado por WhatsApp): **Defer** — most complex workflow. High edge case count. Post-MVP.
- Service combos: **Defer** — configure basic services first, add combos once core flow works.
- Google Calendar sync for owner: **Defer** — not needed if owner checks EA calendar or receives WA notifications.

### Why This Order

1. **WF-1 and WF-2 are the killer features.** They immediately eliminate the owner's #1 pain point: "tengo que estar pendiente del celular para confirmar y recordar turnos."
2. **WF-3 and WF-4 are nice-to-haves.** The owner already handles cancellations manually. Automating them is valuable but not urgent for pilot.
3. **Landing page can be built in parallel** to EA configuration — different skills, different stack.
4. **Business artifacts (Phase 4) are completely independent** and can be built anytime.

---

## Competitive Context

### Who TuAhora Competes Against

| Competitor | Type | Strengths | Weaknesses vs TuAhora |
|-----------|------|-----------|----------------------|
| **Manual WhatsApp/agenda** | Status quo | Free, familiar | Owner spends hours managing schedule. No-shows common. No online booking. |
| **Calendly** (free tier) | SaaS | Brand recognition, easy setup | English-only UX. No WhatsApp integration. Requires owner to self-configure. No local support. |
| **Cal.com** (self-hosted) | Open source SaaS | Feature-rich, video calls, payments | Overkill for 1-provider salon. No WhatsApp. Self-hosting complex for non-technical. |
| **turnos.com.ar / local alternatives** | Local SaaS | Spanish, ARS pricing, local payment methods | Monthly fees per booking or subscription. No WhatsApp integration. Less customization. |
| **Google Calendar booking** | Free tool | Zero cost, Google ecosystem | Not designed for businesses. No WhatsApp. No landing page. No service catalog. |
| **Easy!Appointments standalone** | Open source | Full booking engine, Spanish, free | No WhatsApp. No landing page. Requires self-hosting and configuration. |

### TuAhora's Moat

1. **WhatsApp-first automation** — nobody in the Argentine market offers this at the $8,500 ARS/month price point
2. **Managed service model** — the owner doesn't configure anything. This is huge for non-technical small business owners.
3. **Instagram → Landing → Booking funnel** — optimized for how Argentine salons actually acquire clients
4. **Local presence** — the agente local is physically present in Chamical/Chilecito/La Rioja, can visit clients, provide in-person support
5. **Low total cost** — ~$8,500 ARS/mo infrastructure + developer fee is far below any SaaS alternative that includes WhatsApp

---

## Sources

| Source | URL | Confidence |
|--------|-----|------------|
| Easy!Appointments Features Page | https://easyappointments.org/features/ | HIGH |
| Easy!Appointments v1.6.0 Release Blog | https://easyappointments.org/blog/easyappointments-v1-6-0-release/ | HIGH |
| Easy!Appointments REST API Docs (Context7) | https://context7.com/alextselegidis/easyappointments/llms.txt | HIGH |
| Easy!Appointments GitHub | https://github.com/alextselegidis/easyappointments | HIGH |
| Easy!Appointments Demo | https://demo.easyappointments.org/ | HIGH |
| Baileys Library Docs (Context7) | https://context7.com/whiskeysockets/baileys/llms.txt | HIGH |
| PROJECT.md (TuAhora) | .planning/PROJECT.md | HIGH |
| Cal.com Features Page | https://cal.com | MEDIUM |
| Domain knowledge: Argentina small business, WhatsApp adoption | — | MEDIUM |
