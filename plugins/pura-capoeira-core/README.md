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
| `PURA_GOOGLE_CLIENT_SECRET` | OAuth client secret for Sign in with Google |
| `PURA_APPLE_PRIVATE_KEY` | Contents of the Sign in with Apple `.p8` key (PEM with `\n` escapes, or base64) |

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
  accountUrl: "https://example.com/cuenta/",
  privacyUrl: "https://example.com/aviso-de-privacidad/",
  ajaxUrl: "https://example.com/wp-admin/admin-ajax.php",
  providers: [{ id: "google", label: "Continuar con Google", startUrl: "…/auth/google/start" }],
  whatsapp: "18056385603"
};
```

Nothing user-specific is ever printed into the page: HTML is cached for everyone. The plugin also
registers the classic script handle `pura-auth` (`assets/js/pura-auth.js`, `window.puraAuth`):
it asks `admin-ajax.php?action=rest-nonce` (cookie-authenticated, never cached) for a REST nonce —
a nonce means the visitor is signed in — and signs `account/*` calls with `X-WP-Nonce`. Blocks
that need the session list `pura-auth` in their `viewScript`.

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
| `events/register` | POST | public, 5/10 min | `{event, event_name, days:[…], days_offered:[…], first_name, last_name, email, phone, dob, parent_name, parent_phone (required under 18), city, academy, teacher, graduation, started_year, years_training, shirt_size, emergency_name, emergency_phone, notes}` → `{ok, registration_id, message}`; `409 {ok:false, duplicate:true}` when the email already registered for that event |
| `stripe/webhook` | POST | Stripe signature | `{received:true}` / `{received:true, duplicate:true}` / 400 / 500 |
| `auth/{google\|apple}/start` | POST (HTML form) | public, 20/10 min | `redirect_to` (same-host only) → 302 to the provider. POST-only so a page cache can never replay a `state`. |
| `auth/{google\|apple}/callback` | GET (Google) / POST (Apple `form_post`) | public, 30/10 min | 303 to `redirect_to`, or to `{accountUrl}?auth_error=denied\|state\|code\|exchange\|token\|admin_account\|user_create` |
| `account/me` | GET | signed-in student (`X-WP-Nonce`) | `{ok, user:{displayName,email,privateEmail}, student\|null, inscriptions:[…], monthly:{amount,currency,group,label,currentStudent}\|null, logoutUrl, whatsappUrl}` |
| `account/profile` | POST | signed-in + linked, 20/10 min | `{phone, emergency_phone, parent_name, parent_phone, address}` → same payload as `me`. Empty values do not blank stored ones. |
| `account/pay-monthly` | POST | signed-in + linked, 10/10 min | `{}` → `{ok, url, inscription_id}` (Stripe Checkout for plan `month`; alumno-actual price once the student has a `paid`/`free` inscription). Return URLs `{accountUrl}?pago=success\|cancel`. |
| `admin/notify-status` | GET | `manage_options` | masked diagnostics |
| `admin/test-notification` | POST | `manage_options` | sends a test mail |

Errors are always `{ok:false, error:"…"}` with a meaningful HTTP status (400, 404, 415, 429, 502, 503).

### `POST inscriptions`

Body: `plan`, `group`, `add_inscription` (0/1), `promocode`, `payment_mode` (`now`/`later`),
`trial_date` (YYYY-MM-DD, required for `plan=trial`), `privacy_accepted` (1, required — the
acceptance time is stored as `_pura_privacy_accepted_at`), and either `member:true` (the request
must be signed in as a student with a linked record; the email is taken from that record) or the
full profile `first_name last_name parent_name address email phone parent_phone emergency_phone dob`.

Responses:

- `200 {ok:true, url}` → redirect the browser to Stripe Checkout.
- `200 {ok:true, free:true, message, status}` → registered without charging now
  (`status` is `free` for a beca or `pending_payment_offline` for pay-in-person). `free:true` is
  returned for both cases on purpose: the form keys off it.
- `400 {ok:false, error}` → `privacy_accepted` missing, or an invalid field.
- `401 {ok:false, login_required:true, login_url, error}` → `member:true` without a signed-in,
  linked student. (The old unverified lookup by typed email was removed: it let anyone register —
  and enumerate — using someone else's address.)

Stripe return URLs are `{inscriptionsUrl}?inscription=success|cancel` and
`{storeUrl}?checkout=success|cancel`; the view scripts strip those params after showing a banner.

## Data

- `pura_student` — one per email; profile fields. Payments never overwrite profile data.
  `_pura_user_id` links the record to its WordPress user (see *Cuentas de alumnos*).
- `pura_inscription` — one per registration/payment attempt; status `free | pending_payment |
  pending_payment_offline | paid | expired`, linked to the student.
- `pura_event_reg` — one per person registered to an event through the theme's
  `pura/event-registration` block (**Pura Capoeira → Eventos**, with CSV export). Status
  `registered | confirmed | cancelled`. No payment: the organisers follow up by mail or WhatsApp.
- `gallery_video` + `gallery_category` — gallery items rendered by the theme's `pura/gallery` block.
- `{prefix}pura_stripe_events` — idempotency ledger for webhook events.
- WordPress users with role `pura_student` (capability `read` only). User meta: `_pura_student_id`,
  `_pura_identity_google` / `_pura_identity_apple` (the provider `sub`), `_pura_apple_private_email`.
  Students never reach wp-admin (redirected to *Mi cuenta*) and see no admin bar.

## Cuentas de alumnos (Sign in with Google / Apple)

Server-side OpenID Connect authorization-code flow, implemented in `includes/auth/` with PHP
`openssl` (no Composer runtime dependencies — the deploy excludes `vendor/`). No provider
JavaScript is loaded on the site, so sign-in adds no third-party cookies.

Flow: `POST auth/{provider}/start` stores `{provider, nonce, code_verifier, redirect_to}` in a
single-use transient keyed by `sha256(state)` (10 min) and redirects to the provider. The callback
consumes the state, exchanges the code (Google with PKCE S256; Apple with a per-request ES256
client-secret JWT signed by the `.p8` key), verifies the `id_token` signature against the
provider's JWKS (cached 6 h, one refetch on an unknown `kid`) plus `iss`/`aud`/`exp`/`nonce`, then:

1. a user with that provider identity → sign in;
2. else a verified email matching an existing user → attach the identity **only if that user's
   sole role is `pura_student`** (a provider-asserted email must never open a staff account →
   `auth_error=admin_account`);
3. else create `alumno-<random>` with role `pura_student`.

Then, if the user has no linked record, the `pura_student` with the same email is linked
automatically. Apple "Hide my email" relays never match: link those by hand on the Alumno screen
(**Pura Capoeira → Alumnos → Cuenta vinculada**), where the account email or user ID can be typed.
Apple sends the name only on the first authorization; it is stored then and never blanked.

### Setup

Settings live in **Pura Capoeira → Ajustes → Cuentas de alumnos** (secrets preferably as
`wp-config.php` constants). The callback URLs are printed there and by `wp pura doctor`:

```
https://<host>/wp-json/pura/v1/auth/google/callback
https://<host>/wp-json/pura/v1/auth/apple/callback
```

**Google** — Google Cloud Console → APIs & Services → Credentials → *OAuth client ID* (type *Web
application*). Authorised redirect URIs: the Google callback above for `http://localhost:8888`,
staging and production. OAuth consent screen: external, scopes `openid`, `email`, `profile`. Put
the Client ID in Ajustes and the secret in `PURA_GOOGLE_CLIENT_SECRET`.

**Apple** — requires an Apple Developer Program membership. Certificates, Identifiers & Profiles:

1. *Identifiers → App IDs*: create an App ID with the *Sign in with Apple* capability.
2. *Identifiers → Services IDs*: create one (this is `apple_services_id`), enable Sign in with
   Apple, *Configure*: primary App ID = the one above; domains `capoeiracuernavaca.com`,
   `staging.capoeiracuernavaca.com`; return URLs = the Apple callback above for each host.
   Apple requires HTTPS and a verified domain, so Apple cannot be tested on wp-env.
3. *Keys*: create a key with Sign in with Apple enabled, download the `.p8` once. Key ID →
   `apple_key_id`; the Team ID (top right of the developer account) → `apple_team_id`; the file
   contents → `PURA_APPLE_PRIVATE_KEY` (either the PEM with real newlines in a heredoc, `\n`
   escapes, or the whole file base64-encoded).

`wp pura doctor` reports `auth_google` / `auth_apple`, `account_page`, `student_role`,
`privacy_page` and `privacy_email`.

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
composer run test:unit  # PHPUnit: pricing, Stripe signature, JWT/JWK/ES256, OAuth state, Apple client secret
```
