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
M="${MAILBOX:-}"
MAIL_HOST="mail.$D"
SERVER_HOST="$(hostname -f 2>/dev/null || hostname)"
P=''

run() { su -s /bin/bash "$U" -c "wp --path='$W' $*" 2>&1; }
# `run` merges stderr, and su/PAM print locale warnings there; the last line is wp's answer.
run_last() { run "$@" | tail -n 1; }
mask() { if [ -n "$P" ]; then sed "s/$P/***/g"; else cat; fi; }
mask_json() { sed -E 's/"(password|access_key|secret_key|api_key|client_secret|auth_token|access_token|refresh_token)":"[^"]*"/"\1":"***"/g'; }
tls_check() {
  printf '  %-45s ' "$1:465"
  timeout 15 openssl s_client -connect "$1:465" -servername "$1" -verify_hostname "$1" </dev/null 2>/dev/null \
    | grep -m1 -E '^Verification|Verify return code' || echo "no TLS answer"
}

echo "== FluentSMTP connections now (secrets masked)"
run option get fluentmail-settings --format=json | mask_json | head -c 3000; echo
echo "== wp-config.php credentials"
for c in FLUENTMAIL_SMTP_USERNAME FLUENTMAIL_SMTP_PASSWORD; do
  if run config has "$c" --type=constant >/dev/null; then
    if [ "$c" = FLUENTMAIL_SMTP_USERNAME ]; then echo "  $c = $(run_last config get "$c" --type=constant)"; else echo "  $c: defined"; fi
  else echo "  $c: missing"; fi
done
echo "== FluentSMTP log, last 5"
run db query "'SELECT id, status, created_at, LEFT(response, 220) AS response FROM wp_fsmpt_email_logs ORDER BY id DESC LIMIT 5'" | grep -v -E 'perl: warning|LANGUAGE|LC_ALL|LANG =|are supported|^\s*$'
echo "== mailboxes on $D"
uapi --output=json --user="$U" Email list_pops | grep -o '"email":"[^"]*"' | cut -d'"' -f4
echo "== TLS certificate check (an SSL connection needs the name to verify)"
tls_check "$MAIL_HOST"
tls_check "$SERVER_HOST"

echo "== authentication: what the world sees for $D (public resolver)"
echo "  nameservers: $(dig +short NS "$D" @8.8.8.8 | tr '\n' ' ')"
echo "  MX:          $(dig +short MX "$D" @8.8.8.8 | tr '\n' ' ')"
echo "  SPF:         $(dig +short TXT "$D" @8.8.8.8 | grep -i 'v=spf1' || echo '(none)')"
echo "  DKIM:        $(dig +short TXT "default._domainkey.$D" @8.8.8.8 | tr -d '"' | head -c 90 || true)$( [ -z "$(dig +short TXT "default._domainkey.$D" @8.8.8.8)" ] && echo '(none)')"
echo "  DMARC:       $(dig +short TXT "_dmarc.$D" @8.8.8.8 || true)$( [ -z "$(dig +short TXT "_dmarc.$D" @8.8.8.8)" ] && echo '(none)')"
echo "  this server's outbound IPv4: $(curl -s -m 8 -4 https://api.ipify.org || hostname -I | awk '{print $1}')"
echo "== authentication: what cPanel has locally"
echo "  DNS zone hosted here: $( [ -f "/var/named/$D.db" ] && echo yes || echo no )"
echo "  DKIM key present:     $( [ -f "/var/cpanel/domain_keys/private/$D" ] && echo yes || echo no )"
if [ -f "/var/cpanel/domain_keys/public/$D" ]; then
  echo "  DKIM public key (DNS value for default._domainkey.$D):"
  echo "    v=DKIM1; k=rsa; p=$(grep -v -- '-----' "/var/cpanel/domain_keys/public/$D" | tr -d '\n')"
fi
echo "  Exim log for the last mail to the notification address:"
grep -h 'codecharmer@codecharmer.io' /var/log/exim_mainlog 2>/dev/null | grep -E '<=|=>|\*\*' | tail -n 4 | sed -E 's/^/    /'

if [ "${APPLY:-false}" != "true" ]; then
  echo "== inspect only (apply=true reuses the wp-config credentials; apply=true + mailbox=<name> creates that mailbox)"
  exit 0
fi

if [ -z "$M" ]; then
  echo "== 1. reusing the credentials already in wp-config.php"
  SENDER="$(run_last config get FLUENTMAIL_SMTP_USERNAME --type=constant)"
  case "$SENDER" in
    *@*) echo "  sender: $SENDER" ;;
    *) echo "  FLUENTMAIL_SMTP_USERNAME is not defined; run again with a mailbox name."; exit 1 ;;
  esac
  run config has FLUENTMAIL_SMTP_PASSWORD --type=constant >/dev/null || { echo "  FLUENTMAIL_SMTP_PASSWORD is not defined; run again with a mailbox name."; exit 1; }
else
  SENDER="$M@$D"
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
fi

echo "== 3. FluentSMTP tables (re-run the plugin's activation)"
run plugin deactivate fluent-smtp | mask
run plugin activate fluent-smtp | mask

echo "== 4. FluentSMTP connection + plugin sender (verifies the SMTP login first)"
chmod 644 /tmp/pura-smtp-setup.php /tmp/pura-smtp-test.php
run eval-file /tmp/pura-smtp-setup.php "$SENDER" "$MAIL_HOST" "$SERVER_HOST" | mask
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
