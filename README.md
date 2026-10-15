# Pura Capoeira Cuernavaca

Sitio de [capoeiracuernavaca.com](https://capoeiracuernavaca.com) en WordPress: un **block theme**
(`themes/pura-capoeira`) y un **plugin** (`plugins/pura-capoeira-core`) con los ajustes, las
inscripciones, la tienda (Printful + Stripe), la galería, las cuentas de alumnos (Google / Apple),
el Aviso de Privacidad y la API REST.

> El sitio estático anterior (`public/`) se retiró tras el cambio a WordPress. En el servidor queda
> una copia en `public_html_static_backup` durante 30 días. Ver
> [`docs/cutover-runbook.md`](docs/cutover-runbook.md).

## Estructura

```
themes/pura-capoeira/       theme.json, plantillas, partes, patrones, bloques (src/ → build/), importador WP-CLI
plugins/pura-capoeira-core/ ajustes, precios, CPTs, REST (pura/v1), Stripe, Printful, correo, WP-CLI, tests
docs/                       runbook de cambio, plantilla de .htaccess de producción
bin/wp-env-seed.sh          semilla del entorno local
.wp-env.json                entorno local (Docker) con @wordpress/env
```

## Desarrollo local

Requisitos: Node 20+, Docker Desktop, Composer (solo para lint/tests de PHP).

```bash
npm install                 # instala wp-env y wp-scripts (el tema es el único workspace con JS)
npm run build               # compila los bloques del tema
npx wp-env start            # http://localhost:8888  (admin / password)
```

`bin/wp-env-seed.sh` corre solo tras `wp-env start`: idioma, permalinks, tema, plugins y, si el
importador existe, `wp pura-theme import all` (páginas desde los patrones del tema, menú, logos y
ajustes). Los videos de la galería solo se importan con `--source=/ruta/al/sitio/estatico`
(la carpeta que contiene `data/gallery.json`); sin ella el importador los omite con un aviso.

Llaves locales: crea `.wp-env.override.json` (ignorado por git) con las constantes `PURA_*` del
plugin (ver su [README](plugins/pura-capoeira-core/README.md)). Stripe local:

```bash
stripe listen --forward-to http://localhost:8888/wp-json/pura/v1/stripe/webhook
```

Printful no tiene sandbox: con `PURA_PRINTFUL_MOCK` el plugin sirve `tests/fixtures/printful/*.json`.
Captura fixtures reales con `wp pura printful-fixtures`.

Comandos útiles:

```bash
npm run start                                  # wp-scripts en modo watch
npm run lint                                   # ESLint + Stylelint
cd plugins/pura-capoeira-core && composer install && composer run lint && composer run test:unit
npx wp-env run cli wp pura doctor
```

## Contenido editable

Todo se edita en el admin: copia de las páginas (bloques y patrones), horarios, precios, códigos,
datos de contacto y redes (**Pura Capoeira → Ajustes**), videos de la galería (**Pura Capoeira →
Videos**), inscripciones y alumnos (**Pura Capoeira → Inscripciones / Alumnos**).

## Privacidad, cookies y cuentas de alumnos

- **Aviso de Privacidad** (`/aviso-de-privacidad/`): patrón `page-aviso-de-privacidad` importado como
  página y registrado como página de privacidad de WordPress. El responsable, su domicilio, el correo para derechos
  ARCO y la fecha de actualización se editan en **Ajustes → Privacidad**; el resto del texto, en el
  editor. El formulario de inscripción exige aceptar el Aviso (se guarda la fecha de aceptación).
- **Banner de consentimiento** (`inc/consent.php`, `assets/js/consent.js`): el sitio no usa cookies de
  analítica ni publicidad; el banner solo controla el contenido de terceros (el calendario de Calendly se
  carga al aceptar o al pulsar "Cargar calendario"). La preferencia vive en `localStorage` porque la
  caché de página sirve el mismo HTML a todos. "Preferencias de cookies" en el pie la reabre.
- **Mi cuenta** (`/cuenta/`): inicio de sesión con Google o Apple y portal del alumno (datos,
  historial, pago de mensualidad). Configuración y detalles en el
  [README del plugin](plugins/pura-capoeira-core/README.md#cuentas-de-alumnos-sign-in-with-google--apple).
  Apple solo funciona con HTTPS (staging/producción).

## Despliegue

`.github/workflows/deploy-wordpress.yml` compila, pasa PHPCS y PHPUnit y sube `themes/` y
`plugins/` al `wp-content` del sitio en producción por rsync, y limpia la caché NGINX de cPanel.
Se ejecuta en cada push a `master` (o a mano con *Run workflow*). Usa los mismos secretos de
siempre: `SSH_HOST`, `SSH_PORT`, `SSH_USER`, `SSH_PRIVATE_KEY`, `SSH_KNOWN_HOSTS` y
`DEPLOY_PATH` (la raíz del sitio, donde vive `wp-config.php`).

Los secretos de la app (`PURA_STRIPE_SECRET_KEY`, `PURA_STRIPE_WEBHOOK_SECRET`,
`PURA_PRINTFUL_API_KEY`, `PURA_GOOGLE_CLIENT_SECRET`, `PURA_APPLE_PRIVATE_KEY`,
`FLUENTMAIL_SMTP_PASSWORD`) viven como constantes en `wp-config.php` del servidor; nunca en el repo.

Tras desplegar una versión que añade páginas (p. ej. `cuenta`, `aviso-de-privacidad`), corre en el
servidor `wp pura-theme import pages && wp pura-theme import menu && wp pura-theme import settings`:
el importador no forma parte del despliegue.
