# Pura Capoeira Cuernavaca

Sitio estatico con despliegue por GitHub Actions al VPS.

## Tienda con Printful

Se agrego una tienda conectada con la API de Printful:

- Frontend: `public/tienda.html`
- JS de tienda: `public/assets/js/main.js`
- API proxy (servidor): `public/api/printful.php`

### Por que hay un API proxy

La llave de Printful nunca debe exponerse en el navegador. Por eso, el frontend llama a `public/api/printful.php` y este archivo PHP hace las llamadas a Printful desde el servidor.

### Configuracion requerida en el servidor

Configura una de estas opciones:

1. Variable de entorno `PRINTFUL_API_KEY` en Apache/PHP-FPM.
2. Archivo local `public/api/.printful-token` con solo el token (sin espacios).

Opcional:

- `PRINTFUL_API_BASE_URL` (por defecto usa `https://api.printful.com`).

### Endpoints internos

- `GET /api/printful.php?action=products` lista productos del store.
- `GET /api/printful.php?action=product&id=123` obtiene detalle de producto.
- `POST /api/printful.php?action=order` crea orden borrador en Printful (legado).
- `POST /api/checkout.php?action=shipping` calcula tarifas reales de envío (MXN).
- `POST /api/checkout.php?action=create-checkout` crea la orden borrador + sesión de Stripe Checkout.
- `POST /api/checkout.php?action=webhook` Stripe llama aquí tras el pago y se confirma la orden en Printful.

## Pagos en el sitio (Stripe + Printful)

El checkout completo se realiza en el sitio con Stripe (pago) y Printful (fulfillment).

### Flujo de compra

1. Se cargan productos desde Printful.
2. El usuario agrega productos al carrito y captura su dirección.
3. "Calcular envío" obtiene tarifas reales de Printful en MXN.
4. El usuario elige método de envío y pulsa "Pagar con tarjeta".
5. El backend crea una orden **borrador** en Printful y una **sesión de Stripe Checkout** (MXN), y redirige al pago seguro de Stripe.
6. Al pagar, Stripe llama al **webhook**, que **confirma** la orden en Printful para producción automáticamente.

### Configuración requerida (Stripe)

Configura los secretos igual que el token de Printful (variable de entorno o archivo fuera del web root):

| Secreto | Variable de entorno | Archivo (alternativa) |
| --- | --- | --- |
| Llave secreta de Stripe | `STRIPE_SECRET_KEY` | `.stripe-secret` |
| Firma del webhook | `STRIPE_WEBHOOK_SECRET` | `.stripe-webhook-secret` |

Opcionales:

- `STORE_CURRENCY` (por defecto `MXN`).
- `PRICE_MULTIPLIER` (por defecto `1`; úsalo si tus precios en Printful están en USD y quieres convertir a MXN).
- `SITE_BASE_URL` (por defecto se detecta del host; ej. `https://capoeiracuernavaca.com`).

Los archivos `.stripe-secret` y `.stripe-webhook-secret` deben guardarse **fuera** del web root (un nivel arriba de `public_html`, junto a `.printful-token`) o como variables de entorno. Nunca se suben al repo ni al servidor por rsync (ya están excluidos).

### Registrar el webhook en Stripe

1. En el panel de Stripe: Developers → Webhooks → Add endpoint.
2. URL: `https://capoeiracuernavaca.com/api/checkout.php?action=webhook`
3. Evento: `checkout.session.completed` (y opcional `checkout.session.async_payment_succeeded`).
4. Copia el **Signing secret** (`whsec_...`) y guárdalo en `STRIPE_WEBHOOK_SECRET` / `.stripe-webhook-secret`.
