<div align="center">

# NAATIA AI

### Conversational AI for hotels — 24/7 on WhatsApp

Production-grade AI receptionist that answers guests, qualifies leads, quotes rooms,
and closes reservations — natively on WhatsApp.

[![PHP](https://img.shields.io/badge/PHP-8.2+-777BB4?style=flat-square&logo=php&logoColor=white)](https://php.net)
[![Laravel](https://img.shields.io/badge/Laravel-11-FF2D20?style=flat-square&logo=laravel&logoColor=white)](https://laravel.com)
[![OpenAI](https://img.shields.io/badge/OpenAI-GPT-412991?style=flat-square&logo=openai&logoColor=white)](https://openai.com)
[![WhatsApp](https://img.shields.io/badge/WhatsApp-Cloud%20API-25D366?style=flat-square&logo=whatsapp&logoColor=white)](https://developers.facebook.com/docs/whatsapp/cloud-api)
[![Stripe](https://img.shields.io/badge/Stripe-Payments-635BFF?style=flat-square&logo=stripe&logoColor=white)](https://stripe.com)
[![MercadoPago](https://img.shields.io/badge/MercadoPago-Payments-00B1EA?style=flat-square&logo=mercadopago&logoColor=white)](https://mercadopago.com)
[![License](https://img.shields.io/badge/License-Proprietary-lightgrey?style=flat-square)](#license)

</div>

---

## What it does

NAATIA AI turns a hotel's WhatsApp number into a **fully autonomous sales & reception channel**:

- 🤖 **Answers guests naturally** in Spanish or English, 24/7
- 📅 **Checks real availability** against inventory (calendar of rooms + rates + capacity)
- 💰 **Quotes prices dynamically** based on nights, weekday/weekend rules, room type, and packages
- 💳 **Sends payment links** through Stripe or MercadoPago
- 🏨 **Books reservations** end-to-end without human intervention
- 📊 **Feeds a real-time dashboard** with conversations, leads, and analytics
- 🚨 **Escalates to a human receptionist** when the intent falls outside the bot's confidence

Currently **in production with paying hotel clients in Mexico** (boutique & mid-market segment).

---

## Architecture

```
                            ┌────────────────────────┐
                            │  WhatsApp Cloud API    │
                            │  (Meta / Facebook)     │
                            └────────────┬───────────┘
                                         │  webhook
                                         ▼
┌───────────────────────────┐   ┌────────────────────────┐   ┌────────────────────┐
│  Admin Dashboard (Blade)  │◀──│  Laravel 11 Backend    │──▶│  OpenAI GPT API    │
│  · Reservations           │   │  · Webhook controllers │   │  · Reply generation│
│  · Conversations          │   │  · Async Jobs / Queue  │   │  · Structured JSON │
│  · Inventory & Calendar   │   │  · Services layer      │   └────────────────────┘
│  · Analytics              │   │  · RBAC middleware     │
│  · Media assets           │   └────────┬───────────────┘
└───────────────────────────┘            │
                                         │
                    ┌────────────────────┼─────────────────────┐
                    ▼                    ▼                     ▼
             ┌────────────┐       ┌────────────┐        ┌────────────┐
             │  MySQL     │       │  Stripe    │        │ MercadoPago│
             │  · Rooms   │       │  · Checkout│        │ · Checkout │
             │  · Rates   │       │  · Webhook │        │ · Webhook  │
             │  · Convos  │       └────────────┘        └────────────┘
             │  · Memory  │
             └────────────┘
```

**Design principles:**
- **API-first** — every action the bot takes is also available through the admin panel
- **Async processing** — every WhatsApp message is dispatched to a queue Job so the webhook never blocks
- **Multi-tenant** — one Laravel deployment serves multiple hotels, each with their own inventory and branding
- **Structured LLM outputs** — the GPT layer returns JSON that the app validates before acting (never blind execution)
- **Human-in-the-loop escalation** — receptionist alerts fire when confidence is low or the guest asks for a human

---

## Tech stack

| Layer | Choice |
|---|---|
| **Backend** | PHP 8.2, Laravel 11 |
| **Database** | MySQL 8 |
| **Queue** | Laravel Queue (database / Redis-ready) |
| **AI** | OpenAI Chat Completions API |
| **Messaging** | WhatsApp Cloud API (Meta) |
| **Payments** | Stripe Checkout, MercadoPago Checkout Pro |
| **Frontend** | Blade + Tailwind CSS + Vite |
| **PDF generation** | Custom quote PDFs (server-side) |
| **Deployment** | Linux (Nginx), Cloudflare, Docker-ready |

---

## Domain model — the interesting parts

The system models a hotel as a set of **rooms** with **types**, **rates**, and **daily inventory**.
Every guest interaction lives inside a **conversation** with its own long-term **memory**.

- `Hotel` — multi-tenant root, owns everything below
- `RoomType` / `Room` / `Rate` — inventory & pricing structure
- `RoomInventoryDay` / `RoomDayStatus` — daily availability grid
- `Contact` / `Conversation` / `Message` / `ConversationMemory` — chat history + long-term memory for the LLM
- `Reservation` / `PaymentAttempt` — booking state machine + payment reconciliation
- `HotelPackage` / `HotelService` / `Faq` / `MediaAsset` — content the LLM can quote from
- `ReceptionistAlert` — human-in-the-loop escalation queue
- `BotFollowUp` — automated re-engagement of inactive leads

---

## Highlighted engineering decisions

### 1. Structured outputs, not free-form text
Instead of letting GPT reply directly to guests, the LLM produces a **structured JSON action**
(quote, reservation intent, FAQ answer, escalate, etc.). The application layer validates the
action, executes the side effect (query inventory, generate payment link…), and only then
sends the natural-language reply.

Result: **no hallucinated prices, no fake availability, deterministic bookings**.

### 2. Async webhook processing
The WhatsApp webhook returns `200 OK` in <50 ms — actual reply generation happens inside
`ProcessWhatsAppReplyJob`. This keeps Meta happy (no retry storms) and lets us scale
horizontally by adding queue workers.

### 3. Long-term conversation memory
Instead of stuffing entire chat histories into every prompt (expensive, slow, noisy), the
`ConversationMemory` layer summarizes and distills key facts about the guest and reuses
them across conversations. Cheaper tokens, better recall.

### 4. Dual payment providers with unified reconciliation
Stripe & MercadoPago both write into a single `PaymentAttempt` model with a common
state machine. Reservation status stays consistent regardless of which processor closed
the payment.

---

## Screenshots

<div align="center">

### WhatsApp — end-to-end reservation flow

<img src="docs/screenshots/04-whatsapp-stripe.jpg" width="720" alt="NAATIA AI answering a guest on WhatsApp and sending a Stripe checkout link" />

*Guest asks for pricing, gets a real quote for a 2-night stay, and receives a live Stripe checkout link — all autonomously.*

---

### Admin dashboard

<table>
  <tr>
    <td width="50%"><img src="docs/screenshots/01-inventory.jpg" alt="Inventory & media management" /></td>
    <td width="50%"><img src="docs/screenshots/02-calendar.jpg" alt="Room availability calendar" /></td>
  </tr>
  <tr>
    <td colspan="2"><img src="docs/screenshots/03-rooms-services.jpg" alt="Rooms, services and packages configuration" /></td>
  </tr>
</table>

*Inventory, per-day room calendar, and dynamic pricing rules — all managed from a single admin panel.*

</div>

---

## Local development

Requires PHP 8.2+, Composer, Node 18+, MySQL 8, and a Meta developer account for WhatsApp Cloud API.

```bash
# 1. Install dependencies
composer install
npm install

# 2. Set up env
cp .env.example .env
php artisan key:generate

# 3. Configure required env vars in .env
#    OPENAI_API_KEY=...
#    WHATSAPP_ACCESS_TOKEN=...
#    WHATSAPP_PHONE_NUMBER_ID=...
#    STRIPE_SECRET=...             (optional)
#    MERCADOPAGO_ACCESS_TOKEN=...  (optional)

# 4. Run migrations
php artisan migrate

# 5. Serve
php artisan serve
npm run dev

# 6. Expose your local webhook to Meta (ngrok / cloudflared)
#    Register the URL in Meta's WhatsApp Cloud API dashboard
```

---

## Project status

This repository is a **public showcase** of the NAATIA AI codebase.
The product is actively maintained and deployed for paying customers.
Some proprietary modules (client-specific prompts, private integrations) are kept
outside this public tree.

---

## Author

**Aldahir Cortés** — Founder & Principal Engineer

- Portfolio: coming soon
- LinkedIn: [aldahir-cortes](https://www.linkedin.com/in/aldahir-cortes-987137204/)
- GitHub: [@eaCortes91](https://github.com/eaCortes91)
- Email: ea.cortes91@gmail.com

---

## License

Proprietary — © Aldahir Cortés, 2026.
This repository is published for portfolio and evaluation purposes.
No permission is granted for commercial use, redistribution, or derivative works.
