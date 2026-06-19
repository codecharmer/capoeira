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
- `POST /api/printful.php?action=order` crea orden borrador en Printful.

### Flujo de compra actual

1. Se cargan productos desde Printful.
2. El usuario agrega productos al carrito.
3. El usuario captura direccion de envio.
4. Se crea una orden borrador (`confirm=false`) en Printful.

### Siguiente mejora sugerida

Integrar checkout con Stripe o PayPal y confirmar orden en Printful solo despues del pago exitoso.
