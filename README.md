# Pura Capoeira Cuernavaca

Sitio de [capoeiracuernavaca.com](https://capoeiracuernavaca.com) en WordPress: un **block theme**
(`themes/pura-capoeira`) y un **plugin** (`plugins/pura-capoeira-core`) con los ajustes, las
inscripciones, la tienda (Printful + Stripe), la galería y la API REST.

> `public/` es el sitio estático anterior. Sigue desplegándose con `deploy.yml` hasta el cambio a
> WordPress y sirve como fuente para el importador (`wp pura-theme import`). Ver
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
npm install                 # instala wp-env y wp-scripts para ambos workspaces
npm run build               # compila los bloques del tema
npx wp-env start            # http://localhost:8888  (admin / password)
```

`bin/wp-env-seed.sh` corre solo tras `wp-env start`: idioma, permalinks, tema, plugins y, si el
importador existe, `wp pura-theme import all` (páginas, menú, logos, galería y ajustes desde `public/`).

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

## Despliegue

`.github/workflows/deploy-wordpress.yml` compila, pasa PHPCS y PHPUnit y sube `themes/` y
`plugins/` al `wp-content` del VPS por rsync, activa tema y plugin y limpia cachés. Un push a
`master` despliega a **staging**; producción se lanza a mano (*Run workflow → production*). Cada
entorno de GitHub define la variable `WP_PATH`; los secretos SSH son los mismos de siempre.

Los secretos de la app (`PURA_STRIPE_SECRET_KEY`, `PURA_STRIPE_WEBHOOK_SECRET`,
`PURA_PRINTFUL_API_KEY`, `FLUENTMAIL_SMTP_PASSWORD`) viven como constantes en `wp-config.php`
del servidor; nunca en el repo.
