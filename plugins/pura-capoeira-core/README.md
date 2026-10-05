# Pura Capoeira Core

Settings, inscriptions, Stripe/Printful integration, gallery videos, and the REST API that the
`pura-capoeira` theme's blocks talk to.

## Configuration

Secrets are read from constants in `wp-config.php` first, then from the (non-autoloaded) option:

| Constant | Purpose |
|---|---|
| `PURA_STRIPE_SECRET_KEY` | Stripe secret key |
| `PURA_STRIPE_WEBHOOK_SECRET` | Signing secret of the `/wp-json/pura/v1/stripe/webhook` endpoint |
| `PURA_PRINTFUL_API_KEY` | Printful store token |
| `PURA_PRINTFUL_MOCK` | `true` → serve `tests/fixtures/printful/*.json` instead of calling Printful |
| `PURA_PRINTFUL_CONFIRM_DISABLED` | `true` → the webhook never confirms Printful orders (staging) |

Everything else (prices, promo codes, schedule, contact details, notification recipients) is edited in
**Pura Capoeira → Ajustes**. Prices are entered in pesos and stored in cents.

## Frontend contract

The plugin prints `window.puraConfig` in `<head>` on every front-end page:

```js
window.puraConfig = {
  restUrl: "https://example.com/wp-json/pura/v1/",
  currency: "MXN",
  storeUrl: "https://example.com/tienda/",
  inscriptionsUrl: "https://example.com/inscripciones/",
  whatsapp: "18056385603",
  calendly: { trialAdult: "https://calendly.com/…", trialKids: "https://calendly.com/…" }
};
```

Theme view scripts call the routes below with plain `fetch`. POST bodies must be JSON with
`Content-Type: application/json`. There is no nonce on public routes (nonces go stale under page
caching); rate limiting and a honeypot field (`website`) protect them instead.

### Routes (`/wp-json/pura/v1/…`)

| Route | Method | Access | Response |
|---|---|---|---|
| `store/config` | GET | public | `{ok, currency, price_multiplier}` |
| `store/products` | GET | public (cached 5 min; `?refresh=1`) | `{ok, products:[…]}` |
| `store/products/{id}` | GET | public | `{ok, product:{sync_product, sync_variants:[…]}}` |
| `store/shipping` | POST | public, 20/10 min | `{recipient, items:[{variant_id, quantity}]}` → `{ok, currency, rates:[{id,name,rate}]}` |
| `store/checkout` | POST | public, 10/10 min | `{recipient, items:[{sync_variant_id, quantity}], shipping_id}` → `{ok, url, printful_order_id}`. Prices are looked up server-side; client prices are ignored. |
| `inscriptions/config?group=adult\|kids` | GET | public | `{ok, currency, group, addon_amount, monthly, plans:[{id,label,note,amount,allow_addon}]}` (pesos) |
| `inscriptions/validate-promo` | POST | public, 30/10 min | `{promocode, group}` → `{ok, valid:false}` or `{ok, valid:true, type:'beca'\|'current', free, payment_optional, monthly, group, addon_amount, plans}` |
| `inscriptions` | POST | public, 5/10 min | see below |
| `events/register` | POST | public, 5/10 min | `{event, event_name, days:[…], days_offered:[…], first_name, last_name, email, phone, city, academy, teacher, graduation, shirt_size, emergency_name, emergency_phone, notes}` → `{ok, registration_id, message}`; `409 {ok:false, duplicate:true}` when the email already registered for that event |
| `stripe/webhook` | POST | Stripe signature | `{received:true}` / `{received:true, duplicate:true}` / 400 / 500 |
| `admin/notify-status` | GET | `manage_options` | masked diagnostics |
| `admin/test-notification` | POST | `manage_options` | sends a test mail |

Errors are always `{ok:false, error:"…"}` with a meaningful HTTP status (400, 404, 415, 429, 502, 503).

### `POST inscriptions`

Body: `plan`, `group`, `add_inscription` (0/1), `promocode`, `payment_mode` (`now`/`later`),
`trial_date` (YYYY-MM-DD, required for `plan=trial`), and either `member:true` + `email`, or the
full profile `first_name last_name parent_name address email phone parent_phone emergency_phone dob`.

Responses:

- `200 {ok:true, url}` → redirect the browser to Stripe Checkout.
- `200 {ok:true, free:true, message, status}` → registered without charging now
  (`status` is `free` for a beca or `pending_payment_offline` for pay-in-person). `free:true` is
  returned for both cases on purpose: the form keys off it.
- `404 {ok:false, not_registered:true, error}` → `member` email unknown; the form switches to the full profile.

Stripe return URLs are `{inscriptionsUrl}?inscription=success|cancel` and
`{storeUrl}?checkout=success|cancel`; the view scripts strip those params after showing a banner.

## Data

- `pura_student` — one per email; profile fields. Payments never overwrite profile data.
- `pura_inscription` — one per registration/payment attempt; status `free | pending_payment |
  pending_payment_offline | paid | expired`, linked to the student.
- `pura_event_reg` — one per person registered to an event through the theme's
  `pura/event-registration` block (**Pura Capoeira → Eventos**, with CSV export). Status
  `registered | confirmed | cancelled`. No payment: the organisers follow up by mail or WhatsApp.
- `gallery_video` + `gallery_category` — gallery items rendered by the theme's `pura/gallery` block.
- `{prefix}pura_stripe_events` — idempotency ledger for webhook events.

## WP-CLI

```
wp pura migrate-inscriptions <inscriptions.jsonl> [--dry-run] [--since=YYYY-MM-DD]
wp pura export [--status=…] [--from=…] [--to=…]   # CSV to stdout
wp pura doctor
wp pura printful-fixtures                          # capture real catalog into tests/fixtures
wp pura stripe-replay <evt_id>
```

## Development

```
composer install
composer run lint       # PHPCS (WordPress-Extra + PHPCompatibilityWP)
composer run test:unit  # PHPUnit: pricing, Stripe signature
```
