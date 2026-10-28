#!/bin/bash
# Runs on the VPS as root (piped through `ssh … bash -s`). Inspects, and with APPLY=true edits,
# the mail-authentication TXT records of the zone this server is authoritative for:
#
#   - the apex SPF record is replaced with $SPF (one v=spf1 record; the others are left alone);
#   - the _dmarc TXT record is added, or replaced with $DMARC if one exists.
#
# Everything goes through the WHM API (whmapi1 dumpzone / editzonerecord / addzonerecord), so
# cPanel bumps the serial and, when a DNS cluster is configured, pushes the zone to the peers.
# Expects: APPLY ("true" to change anything), SPF, DMARC.
set -u
export PYTHONIOENCODING=utf-8  # the root shell over ssh has an ASCII locale

D=capoeiracuernavaca.com
SPF="${SPF:-}"
DMARC="${DMARC:-}"

echo "== delegation: registry vs. the zone's own NS/SOA"
echo "  registry delegation:  $(dig +norecurse +time=5 +tries=1 NS "$D" @a.gtld-servers.net 2>/dev/null | awk '$4=="NS"{print tolower($5)}' | sort -u | tr '\n' ' ')$(command -v whois >/dev/null && timeout 20 whois "$D" 2>/dev/null | grep -i 'name server' | awk '{print "(whois: "tolower($NF)")"}' | sort -u | tr '\n' ' ')"
echo "  public resolver NS:   $(dig +short NS "$D" @8.8.8.8 | tr '\n' ' ')"
echo "  public resolver SOA:  $(dig +short SOA "$D" @8.8.8.8)"
for ns in ns1.codecharmer.io ns2.codecharmer.io; do
  echo "  $ns -> $(dig +short A "$ns" @8.8.8.8 | tr '\n' ' ')"
done
echo "  this server's IPv4:   $(hostname -I 2>/dev/null | awk '{print $1}')"
echo "== cPanel zone $D on this server"
echo "  zone file:            $( [ -f "/var/named/$D.db" ] && echo "/var/named/$D.db ($(stat -c %y "/var/named/$D.db" | cut -c1-19))" || echo missing )"
echo "  DNS clustering:       $( [ -f /var/cpanel/useclusteringdns ] && echo enabled || echo 'not enabled' )"
if [ -d /var/cpanel/cluster/root/config ]; then
  echo "  cluster peers:        $(ls /var/cpanel/cluster/root/config 2>/dev/null | grep -v -E '\.(cache|dnsrole)$' | tr '\n' ' ')"
fi
echo "  python3:              $(command -v python3 || echo missing)"
echo "  NS / SOA / TXT records in the zone (whmapi1 dumpzone):"
whmapi1 --output=json dumpzone domain="$D" > /tmp/capoeira-zone.json 2>/dev/null
python3 - "$D" /tmp/capoeira-zone.json <<'PY'
import json, sys
zone = json.load(open(sys.argv[2]))
records = [r for z in zone.get("data", {}).get("zone", []) for r in z.get("record", [])]
if not records:
    print("    (no records parsed; raw head: " + open(sys.argv[2]).read(300).replace("\n", " ") + ")")
for r in records:
    t = r.get("type")
    if t == "SOA":
        print(f"    line {r.get('Line')}: {r.get('name')} SOA mname={r.get('mname')} rname={r.get('rname')} serial={r.get('serial')}")
    elif t == "NS":
        print(f"    line {r.get('Line')}: {r.get('name')} NS {r.get('nsdname')}")
    elif t == "TXT":
        data = r.get("txtdata", "")
        print(f"    line {r.get('Line')}: {r.get('name')} TXT {data[:110]}{'...' if len(data) > 110 else ''}")
PY

if [ "${APPLY:-false}" != "true" ]; then
  echo "== inspect only (apply=true replaces the SPF record and adds/replaces _dmarc)"
  rm -f /tmp/capoeira-zone.json
  exit 0
fi

if [ -z "$SPF" ] || [ -z "$DMARC" ]; then echo "SPF and DMARC must both be given."; exit 1; fi
case "$SPF" in v=spf1*) ;; *) echo "SPF must start with v=spf1"; exit 1;; esac
case "$DMARC" in v=DMARC1*) ;; *) echo "DMARC must start with v=DMARC1"; exit 1;; esac

# Line numbers of the apex SPF record and of an existing _dmarc record, from the dump above.
read -r SPF_LINE DMARC_LINE <<EOF
$(python3 - "$D" /tmp/capoeira-zone.json <<'PY'
import json, sys
d = sys.argv[1]
zone = json.load(open(sys.argv[2]))
records = [r for z in zone.get("data", {}).get("zone", []) for r in z.get("record", [])]
spf = [r["Line"] for r in records if r.get("type") == "TXT" and r.get("name") in (d + ".", d, "@") and str(r.get("txtdata", "")).lower().startswith("v=spf1")]
dmarc = [r["Line"] for r in records if r.get("type") == "TXT" and r.get("name") in ("_dmarc." + d + ".", "_dmarc." + d, "_dmarc")]
print(spf[0] if spf else 0, dmarc[0] if dmarc else 0)
PY
)
EOF
rm -f /tmp/capoeira-zone.json
case "${SPF_LINE:-}${DMARC_LINE:-}" in *[!0-9]*|"") echo "  could not read the zone dump (SPF_LINE='${SPF_LINE:-}' DMARC_LINE='${DMARC_LINE:-}'); nothing changed."; exit 1;; esac
echo "  zone dump: SPF at line ${SPF_LINE}, _dmarc at line ${DMARC_LINE} (0 = absent)"
echo "== 1. SPF (apex TXT)"
ok() { grep -q '"result":1' <<<"$1" && echo "  ok" || { echo "  WHM API refused: $(grep -o '"reason":"[^"]*"' <<<"$1")"; exit 1; }; }
# whmapi1 URL-decodes its key=value arguments, so a literal "+" (as in +a +mx) would turn into a
# space and "%" would start an escape; encode both before handing a record value over.
enc() { printf '%s' "$1" | sed -e 's/%/%25/g' -e 's/+/%2B/g'; }
SPF_ARG="$(enc "$SPF")"
DMARC_ARG="$(enc "$DMARC")"
if [ "${SPF_LINE:-0}" -gt 0 ]; then
  echo "  replacing line $SPF_LINE with: $SPF"
  ok "$(whmapi1 --output=json editzonerecord domain="$D" line="$SPF_LINE" name="$D." class=IN ttl=14400 type=TXT txtdata="$SPF_ARG")"
else
  echo "  no v=spf1 record found; adding: $SPF"
  ok "$(whmapi1 --output=json addzonerecord domain="$D" name="$D." class=IN ttl=14400 type=TXT txtdata="$SPF_ARG")"
fi
echo "== 2. DMARC (_dmarc TXT)"
if [ "${DMARC_LINE:-0}" -gt 0 ]; then
  echo "  replacing line $DMARC_LINE with: $DMARC"
  ok "$(whmapi1 --output=json editzonerecord domain="$D" line="$DMARC_LINE" name="_dmarc.$D." class=IN ttl=14400 type=TXT txtdata="$DMARC_ARG")"
else
  echo "  adding: $DMARC"
  ok "$(whmapi1 --output=json addzonerecord domain="$D" name="_dmarc.$D." class=IN ttl=14400 type=TXT txtdata="$DMARC_ARG")"
fi
echo "== 3. zone now (TXT records and serial)"
whmapi1 --output=json dumpzone domain="$D" 2>/dev/null | python3 -c '
import json, sys
zone = json.load(sys.stdin)
for z in zone.get("data", {}).get("zone", []):
    for r in z.get("record", []):
        if r.get("type") == "SOA":
            print("    serial %s" % r.get("serial"))
        if r.get("type") == "TXT":
            print("    %s TXT %s" % (r.get("name"), str(r.get("txtdata", ""))[:110]))
'
echo "  answer from this server: $(dig +short TXT "_dmarc.$D" @127.0.0.1 | head -1)"
echo "== done"
