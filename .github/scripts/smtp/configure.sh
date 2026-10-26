#!/bin/bash
# Runs on the VPS as root (piped through `ssh … bash -s`). Inspects, and with APPLY=true
# configures, outgoing mail for WordPress:
#
#   1. a dedicated mailbox <MAILBOX>@<DOMAIN> in cPanel (created, or its password reset);
#   2. its credentials in wp-config.php as the FLUENTMAIL_SMTP_USERNAME/PASSWORD constants;
#   3. a FluentSMTP connection (mail.<DOMAIN>, port 465, SSL) that reads those constants;
#   4. the plugin's own sender settings, then a real test mail to the notification address.
#
# The password is generated here and only ever written to cPanel and wp-config.php; every
# line of output is passed through a mask so it cannot reach the Actions log.
#
# Expects: W (WordPress path), MAILBOX, APPLY ("true" to change anything), and the two PHP
# helpers next to this file uploaded to /tmp/pura-smtp-setup.php and /tmp/pura-smtp-test.php.
set -u

U=capoeiracuernava
D=capoeiracuernavaca.com
M="${MAILBOX:-notificaciones}"
SENDER="$M@$D"
HOST="mail.$D"
P=''

run() { su -s /bin/bash "$U" -c "wp --path='$W' $*" 2>&1; }
mask() { if [ -n "$P" ]; then sed "s/$P/***/g"; else cat; fi; }
mask_json() { sed -E 's/"(password|access_key|secret_key|api_key|client_secret|auth_token|access_token|refresh_token)":"[^"]*"/"\1":"***"/g'; }

echo "== FluentSMTP connections now (secrets masked)"
run option get fluentmail-settings --format=json | mask_json | head -c 3000; echo
echo "== FluentSMTP log table"
run db query "'SHOW TABLES LIKE \"%fsmpt_email_logs\"'" | tail -n +2
echo "== mailboxes on $D"
uapi --output=json --user="$U" Email list_pops | grep -o '"email":"[^"]*"' | cut -d'"' -f4
echo "== TLS certificate on $HOST:465"
timeout 15 openssl s_client -connect "$HOST:465" -servername "$HOST" </dev/null 2>/dev/null \
  | openssl x509 -noout -subject -issuer -enddate 2>&1 | sed 's/^/  /'

if [ "${APPLY:-false}" != "true" ]; then
  echo "== inspect only (run again with apply=true to configure $SENDER)"
  exit 0
fi

echo "== 1. mailbox $SENDER"
P=$(openssl rand -base64 48 | tr -dc 'A-Za-z0-9' | head -c 28)
if uapi --output=json --user="$U" Email list_pops | grep -q "\"email\":\"$SENDER\""; then
  out=$(uapi --output=json --user="$U" Email passwd_pop email="$M" domain="$D" password="$P"); what=password-reset
else
  out=$(uapi --output=json --user="$U" Email add_pop email="$M" domain="$D" password="$P" quota=1024); what=created
fi
status=$(echo "$out" | grep -o '"status":[01]' | head -1)
echo "  $what: ${status:-no status}"
echo "$out" | grep -o '"errors":\[[^]]*\]' | mask | sed 's/^/  /'
case "$status" in *1) ;; *) echo "  cPanel refused the mailbox change; stopping before touching WordPress."; exit 1;; esac

echo "== 2. credentials in wp-config.php"
run config set FLUENTMAIL_SMTP_USERNAME "$SENDER" --type=constant | mask
run config set FLUENTMAIL_SMTP_PASSWORD "$P" --type=constant | mask

echo "== 3. FluentSMTP tables (re-run the plugin's activation)"
run plugin deactivate fluent-smtp | mask
run plugin activate fluent-smtp | mask

echo "== 4. FluentSMTP connection + plugin sender (verifies the SMTP login first)"
chmod 644 /tmp/pura-smtp-setup.php /tmp/pura-smtp-test.php
run eval-file /tmp/pura-smtp-setup.php "$SENDER" "$HOST" | mask
rc=${PIPESTATUS[0]}
if [ "$rc" -ne 0 ]; then
  echo "  setup failed (exit $rc); FluentSMTP settings were not written."
  rm -f /tmp/pura-smtp-setup.php /tmp/pura-smtp-test.php
  exit 1
fi

echo "== 5. test mail through the new connection"
run eval-file /tmp/pura-smtp-test.php | mask
rm -f /tmp/pura-smtp-setup.php /tmp/pura-smtp-test.php

echo "== 6. pura doctor"
run pura doctor | mask
echo "== done"
