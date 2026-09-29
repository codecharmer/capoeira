# Cutover runbook: static site → WordPress on the same cPanel VPS

Same server, same domain. The switch is a document-root swap; DNS does not change.
Rollback is a directory rename.

## 0. Before anything else (do now)

- [ ] **Rotate the Printful token.** The old one lived inside the web root at `public_html/api/.printful-token`.
      Check exposure first: `curl -sI https://capoeiracuernavaca.com/api/.printful-token` (a 200 means it was public).
- [ ] Create the mailbox `notificaciones@capoeiracuernavaca.com` in cPanel → Email Accounts.
- [ ] cPanel → Email Deliverability: enable DKIM and SPF for the domain.
- [ ] Confirm the VPS PHP version for the vhost: `ssh … '/usr/local/cpanel/bin/rebuild_phpconf --current'`.
      Match it in `.wp-env.json` (`phpVersion`).
- [x] Deploys: `deploy-wordpress.yml` runs on every push to `master` and targets `secrets.DEPLOY_PATH`
      (the live docroot) with the existing SSH secrets. No GitHub environments are needed.

## 1. Staging (`staging.capoeiracuernavaca.com`)

1. cPanel → Domains → create subdomain `staging` (docroot `/home/capoeiracuernava/staging.capoeiracuernavaca.com`); wait for AutoSSL.
2. cPanel → WP Toolkit → Install WordPress into that docroot (Spanish, admin user, strong password).
   Fallback without Toolkit: `wp core download --locale=es_MX && wp config create … && wp core install …`.
3. Add to `wp-config.php` (above "That's all, stop editing"):
   ```php
   define( 'PURA_STRIPE_SECRET_KEY', 'sk_test_…' );
   define( 'PURA_STRIPE_WEBHOOK_SECRET', 'whsec_…' );   // from step 7
   define( 'PURA_PRINTFUL_API_KEY', '…new token…' );
   define( 'PURA_PRINTFUL_CONFIRM_DISABLED', true );    // staging never confirms Printful orders
   define( 'FLUENTMAIL_SMTP_PASSWORD', '…' );
   define( 'DISALLOW_FILE_EDIT', true );
   define( 'WP_AUTO_UPDATE_CORE', 'minor' );
   ```
4. Set the `staging` environment variable `WP_PATH` to the docroot, then run
   **Actions → Deploy WordPress theme and plugin → Run workflow → staging**.
5. Over SSH as the cPanel user:
   ```bash
   wp option update blog_public 0
   wp rewrite structure '/%postname%/' --hard
   wp plugin install fluent-smtp --activate
   wp pura-theme import all --source=/home/capoeiracuernava/public_html   # legacy static site
   wp pura migrate-inscriptions /home/capoeiracuernava/inscriptions.jsonl --dry-run
   wp pura migrate-inscriptions /home/capoeiracuernava/inscriptions.jsonl
   wp pura doctor
   ```
6. WP admin → Pura Capoeira → Ajustes: recipients, from address, contact details. Settings → FluentSMTP:
   host `mail.capoeiracuernavaca.com`, port 465 SSL, user `notificaciones@…`. Send the test mail.
7. Stripe dashboard (**test mode**) → Developers → Webhooks → Add endpoint
   `https://staging.capoeiracuernavaca.com/wp-json/pura/v1/stripe/webhook`, events
   `checkout.session.completed`, `checkout.session.async_payment_succeeded`, `checkout.session.expired`.
   Put the signing secret into `PURA_STRIPE_WEBHOOK_SECRET`.

### Staging checklist
- [ ] `/`, `/clases/`, `/trayectoria/`, `/inscripciones/`, `/galeria/`, `/tienda/`, `/contacto/` render; `#adultos`, `#kids`, `#agendar-clase` anchors work.
- [ ] Inscriptions: beca code → registered free, one email; current-student code + pay later → registered, one email; member email hit; unknown member email → switches to full form; trial plan requires a date.
- [ ] Inscriptions: paid flow with card `4242 4242 4242 4242` → status **Pagado** in admin, exactly one email with the student in CC.
- [ ] `stripe events resend <evt_id>` → webhook answers `{"received":true,"duplicate":true}`, no second email.
- [ ] Store: catalog loads, shipping quotes, Stripe test checkout completes, Printful shows a **draft** order, plugin log shows "confirm skipped".
- [ ] `wp pura doctor` is green.

## 2. Cutover

1. Content freeze on the static site; export the final `inscriptions.jsonl` and re-run the migration on staging (idempotent).
2. Stripe (**live mode**): create the endpoint `https://capoeiracuernavaca.com/wp-json/pura/v1/stripe/webhook`
   (it 404s until the swap; harmless). Keep its `whsec_` ready. Disable the old `/api/checkout.php?action=webhook` endpoint.
3. `mv /home/capoeiracuernava/public_html /home/capoeiracuernava/public_html_static_backup && chmod 700 …_static_backup`
   (keep 30 days; it contains the old `.htaccess` and secret dotfiles).
4. WP Toolkit → staging site → **Clone** → target domain `capoeiracuernavaca.com`, docroot `public_html`
   (Toolkit copies files + DB and search-replaces the URL).
5. On the production copy: `wp-config.php` → live Stripe keys, live `whsec_`, remove `PURA_PRINTFUL_CONFIRM_DISABLED`;
   `wp option update blog_public 1`; install `docs/htaccess.production` as `public_html/.htaccess`; `wp rewrite flush --hard`.
6. Set the `production` environment `WP_PATH` and run the deploy workflow with `production` so files equal git.
7. `/usr/local/cpanel/scripts/ea-nginx clear_cache capoeiracuernava`.

### Production checklist
- [ ] `curl -sI` each new slug → 200; each old `.html` URL → 301 to the right slug; `/api/checkout.php` → 410; `/api/.printful-token` and `/.git/` → 404.
- [ ] `GET /wp-json/pura/v1/inscriptions/config` and `/store/config` return `{"ok":true,…}`.
- [ ] A pay-later inscription with the current-student code → email arrives.
- [ ] The owner places one small real store order → Printful shows it **confirmed**, Stripe shows the webhook delivered with 200.
- [ ] `wp pura doctor` green; FluentSMTP log shows sends.

### After cutover
- [ ] Rotate the Stripe secret key and the SMTP password (they may have lived under `public_html/api/`).
- [ ] Delete the old Stripe webhook endpoint.
- [x] Commit: delete `public/`, `.github/workflows/deploy.yml`, update README (done on the WordPress branch; merging it to `master` retires the static deploy).
- [ ] Turn off the `staging` deploy-on-push if no longer wanted.

## 3. Rollback (≤ 5 minutes)

```bash
wp pura export --format=csv > /home/capoeiracuernava/inscriptions-during-wp.csv   # save anything captured
mv /home/capoeiracuernava/public_html /home/capoeiracuernava/public_html_wp_failed
mv /home/capoeiracuernava/public_html_static_backup /home/capoeiracuernava/public_html
/usr/local/cpanel/scripts/ea-nginx clear_cache capoeiracuernava
```
Re-enable the old Stripe endpoint, disable the new one. Staging stays intact for a second attempt.
