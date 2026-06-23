<?php

declare(strict_types=1);

/**
 * Checkout backend: Stripe (hosted checkout) + Printful fulfillment.
 *
 * Flow:
 *  1. action=shipping        -> get real Printful shipping rates (MXN) for the cart.
 *  2. action=create-checkout -> create a Printful DRAFT order, then a Stripe
 *                               Checkout Session (MXN). Returns the Stripe URL.
 *  3. action=webhook         -> Stripe calls this after payment. On success we
 *                               CONFIRM the Printful order so it goes to fulfillment.
 *
 * Secrets are read from environment variables first, then from files stored
 * OUTSIDE the web root (same secure pattern as printful.php). Never commit them.
 */

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    http_response_code(204);
    exit;
}

function jsonResponse(int $status, array $payload): void
{
    header('Content-Type: application/json; charset=utf-8');
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/**
 * Read a secret from an env var, then a local api/ file, then a file one level
 * above the web root (preferred secure location).
 */
function readSecret(string $envName, string $fileName): ?string
{
    $env = getenv($envName);
    if (is_string($env) && trim($env) !== '') {
        return trim($env);
    }

    $candidates = [
        __DIR__ . '/' . $fileName,            // public_html/api/<file>
        dirname(__DIR__, 2) . '/' . $fileName, // one level above web root
    ];

    foreach ($candidates as $path) {
        if (is_readable($path)) {
            $value = trim((string) file_get_contents($path));
            if ($value !== '') {
                return $value;
            }
        }
    }

    return null;
}

function siteBaseUrl(): string
{
    $base = getenv('SITE_BASE_URL');
    if (is_string($base) && trim($base) !== '') {
        return rtrim(trim($base), '/');
    }
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'capoeiracuernavaca.com';
    return $scheme . '://' . $host;
}

function storeCurrency(): string
{
    $cur = getenv('STORE_CURRENCY');
    if (is_string($cur) && trim($cur) !== '') {
        return strtoupper(trim($cur));
    }
    return 'MXN';
}

/** Multiplier applied to Printful retail prices before charging (e.g. USD->MXN). Default 1. */
function priceMultiplier(): float
{
    // 1) Environment variable takes priority.
    $m = getenv('PRICE_MULTIPLIER');
    if (is_string($m) && is_numeric($m) && (float) $m > 0) {
        return (float) $m;
    }

    // 2) Fall back to a file (same secure pattern as the secrets): a local
    //    api/.price-multiplier or, preferred, one level above the web root.
    $candidates = [
        __DIR__ . '/.price-multiplier',
        dirname(__DIR__, 2) . '/.price-multiplier',
    ];
    foreach ($candidates as $path) {
        if (is_readable($path)) {
            $value = trim((string) file_get_contents($path));
            if (is_numeric($value) && (float) $value > 0) {
                return (float) $value;
            }
        }
    }

    return 1.0;
}

/** Normalize an age-group value to one of the supported groups. */
function inscriptionGroup($raw): string
{
    $group = strtolower(trim((string) $raw));
    return $group === 'kids' ? 'kids' : 'adult';
}

/**
 * Inscription (membership) plans. Amounts are in MXN cents to avoid float drift.
 * Monthly tuition: $1,150 MXN adults / $1,000 MXN kids. Inscription fee: $1,500 MXN.
 * Trial class is a fixed $200 MXN for every group.
 * Quarterly = 3 months -15%. Yearly = 12 months -20%.
 */
function inscriptionPlans(?int $monthlyCents = null, string $group = 'adult'): array
{
    $monthly = $monthlyCents !== null ? $monthlyCents : inscriptionMonthlyAmount($group);
    $inscription = 150000; // $1,500.00 MXN

    return [
        'trial' => [
            'label' => 'Clase de Prueba',
            'amount' => 20000, // $200.00 MXN, fixed regardless of monthly rate
            'allow_addon' => false,
        ],
        'inscription' => [
            'label' => 'Solo inscripción',
            'amount' => $inscription,
            'allow_addon' => false,
        ],
        'inscription_month' => [
            'label' => 'Inscripción + primer mes',
            'amount' => $inscription + $monthly,
            'allow_addon' => false,
        ],
        'month' => [
            'label' => 'Mensualidad (1 mes)',
            'amount' => $monthly,
            'allow_addon' => true,
        ],
        'quarter' => [
            'label' => 'Paquete trimestral (3 meses, -15%)',
            'amount' => (int) round($monthly * 3 * 0.85),
            'allow_addon' => true,
        ],
        'year' => [
            'label' => 'Paquete anual (12 meses, -20%)',
            'amount' => (int) round($monthly * 12 * 0.80),
            'allow_addon' => true,
        ],
    ];
}

/** Optional inscription add-on amount (MXN cents) for month/quarter/year plans. */
function inscriptionAddonAmount(): int
{
    return 150000;
}

/** Default monthly tuition (MXN cents) for the given age group. */
function inscriptionMonthlyAmount(string $group = 'adult'): int
{
    return $group === 'kids' ? 100000 : 115000; // kids $1,000.00 / adults $1,150.00 MXN
}

/** Discounted monthly tuition for current students (MXN cents) by age group. */
function inscriptionCurrentMonthlyAmount(string $group = 'adult'): int
{
    return $group === 'kids' ? 100000 : 75000; // kids $1,000.00 / adults $750.00 MXN
}

/** Read a configurable code from env, then a file outside the web root, else a default. */
function readInscriptionCode(string $envName, string $fileName, string $default): string
{
    $code = getenv($envName);
    if (is_string($code) && trim($code) !== '') {
        return trim($code);
    }
    $candidates = [
        __DIR__ . '/' . $fileName,
        dirname(__DIR__, 2) . '/' . $fileName,
    ];
    foreach ($candidates as $path) {
        if (is_readable($path)) {
            $value = trim((string) file_get_contents($path));
            if ($value !== '') {
                return $value;
            }
        }
    }
    return $default;
}

/** Promo code that grants a 100% scholarship (beca). Configurable, defaults to BECADOPC26. */
function inscriptionPromoCode(): string
{
    return readInscriptionCode('INSCRIPTION_PROMO_CODE', '.inscription-promo', 'BECADOPC26');
}

/** Promo code for current students: $750/month and payment becomes optional. Defaults to ACTUALPC26. */
function inscriptionCurrentCode(): string
{
    return readInscriptionCode('INSCRIPTION_CURRENT_CODE', '.inscription-current', 'ACTUALPC26');
}

/**
 * Resolve a typed promo code to its effect.
 * Returns ['type' => 'beca'|'current'|'none', 'monthly' => cents, 'payment_optional' => bool, 'free' => bool].
 */
function resolveInscriptionPromo(string $code, string $group = 'adult'): array
{
    $code = trim($code);
    if ($code !== '' && strcasecmp($code, inscriptionPromoCode()) === 0) {
        return ['type' => 'beca', 'monthly' => inscriptionMonthlyAmount($group), 'payment_optional' => true, 'free' => true];
    }
    if ($code !== '' && strcasecmp($code, inscriptionCurrentCode()) === 0) {
        return ['type' => 'current', 'monthly' => inscriptionCurrentMonthlyAmount($group), 'payment_optional' => true, 'free' => false];
    }
    return ['type' => 'none', 'monthly' => inscriptionMonthlyAmount($group), 'payment_optional' => false, 'free' => false];
}


/** Append an inscription record to a JSONL file stored OUTSIDE the web root. */
function logInscription(array $entry): void
{
    $entry = ['ts' => date('c')] + $entry;
    $line = json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($line === false) {
        return;
    }
    $candidates = [
        dirname(__DIR__, 2) . '/inscriptions.jsonl',
        __DIR__ . '/inscriptions.jsonl',
    ];
    foreach ($candidates as $path) {
        $dir = dirname($path);
        if ((file_exists($path) && is_writable($path)) || is_writable($dir)) {
            @file_put_contents($path, $line . PHP_EOL, FILE_APPEND | LOCK_EX);
            return;
        }
    }
}

/**
 * Look up a previously-registered student by email in inscriptions.jsonl.
 * Returns a normalized student array (first_name, last_name, email, phone,
 * parent_name, parent_phone, emergency_phone, address, dob) for the most
 * recent matching record, or null if the email was never registered.
 */
function findInscriptionByEmail(string $email): ?array
{
    $email = strtolower(trim($email));
    if ($email === '') {
        return null;
    }

    $candidates = [
        dirname(__DIR__, 2) . '/inscriptions.jsonl',
        __DIR__ . '/inscriptions.jsonl',
    ];
    $path = null;
    foreach ($candidates as $candidate) {
        if (is_readable($candidate)) {
            $path = $candidate;
            break;
        }
    }
    if ($path === null) {
        return null;
    }

    $fh = @fopen($path, 'r');
    if ($fh === false) {
        return null;
    }

    $match = null; // last (most recent) matching record wins
    while (($raw = fgets($fh)) !== false) {
        $line = trim($raw);
        if ($line === '') {
            continue;
        }
        $entry = json_decode($line, true);
        if (!is_array($entry)) {
            continue;
        }

        $student = isset($entry['student']) && is_array($entry['student']) ? $entry['student'] : [];
        $entryEmail = strtolower(trim((string) ($student['email'] ?? ($entry['email'] ?? ''))));
        if ($entryEmail === '' || $entryEmail !== $email) {
            continue;
        }

        // Prefer a record that carries full student details over a flat one.
        if (!empty($student)) {
            $match = [
                'first_name' => (string) ($student['first_name'] ?? ''),
                'last_name' => (string) ($student['last_name'] ?? ''),
                'parent_name' => (string) ($student['parent_name'] ?? ''),
                'address' => (string) ($student['address'] ?? ''),
                'email' => $entryEmail,
                'phone' => (string) ($student['phone'] ?? ''),
                'parent_phone' => (string) ($student['parent_phone'] ?? ''),
                'emergency_phone' => (string) ($student['emergency_phone'] ?? ''),
                'dob' => (string) ($student['dob'] ?? ''),
            ];
        } else {
            // Flat record (e.g. webhook "paid"): only name + email available.
            $name = trim((string) ($entry['student_name'] ?? ''));
            $first = $name;
            $last = '';
            if ($name !== '' && strpos($name, ' ') !== false) {
                $parts = explode(' ', $name, 2);
                $first = $parts[0];
                $last = $parts[1] ?? '';
            }
            $match = [
                'first_name' => $first,
                'last_name' => $last,
                'parent_name' => '',
                'address' => '',
                'email' => $entryEmail,
                'phone' => '',
                'parent_phone' => '',
                'emergency_phone' => '',
                'dob' => '',
            ];
        }
    }

    @fclose($fh);
    return $match;
}


/**
 * Email address(es) that should be notified of new inscriptions. Reads
 * INSCRIPTION_NOTIFY_EMAIL (env) or an .inscription-notify file outside the web
 * root. Multiple recipients may be comma-separated. Returns [] if unset.
 */
function inscriptionNotifyEmails(): array
{
    $raw = readInscriptionCode('INSCRIPTION_NOTIFY_EMAIL', '.inscription-notify', '');
    if ($raw === '') {
        return [];
    }
    $emails = array_filter(array_map('trim', explode(',', $raw)), static function ($e) {
        return $e !== '' && filter_var($e, FILTER_VALIDATE_EMAIL);
    });
    return array_values($emails);
}

/** Optional "From" address for notification emails. Defaults to no-reply@<host>. */
function inscriptionNotifyFrom(): string
{
    $from = readInscriptionCode('INSCRIPTION_NOTIFY_FROM', '.inscription-notify-from', '');
    if ($from !== '' && filter_var($from, FILTER_VALIDATE_EMAIL)) {
        return $from;
    }
    $host = preg_replace('/[^a-z0-9.\-]/i', '', (string) ($_SERVER['HTTP_HOST'] ?? 'capoeiracuernavaca.com'));
    $host = preg_replace('/^www\./i', '', $host) ?: 'capoeiracuernavaca.com';
    return 'no-reply@' . $host;
}

/**
 * Read SMTP configuration. Each value comes from an environment variable first,
 * then from a key=value ".smtp" file stored OUTSIDE the web root. Returns null
 * if no host is configured (so the caller can fall back to mail()).
 *
 * Recognized keys (env name / file key):
 *   SMTP_HOST     host
 *   SMTP_PORT     port      (default 587)
 *   SMTP_USER     user
 *   SMTP_PASS     pass
 *   SMTP_SECURE   secure    (tls | ssl | none; default tls)
 *   SMTP_FROM     from      (optional; overrides the From address)
 *   SMTP_FROM_NAME from_name (optional; default "Pura Capoeira")
 */
function smtpSettings(): ?array
{
    // Load key=value pairs from a ".smtp" file outside the web root, if present.
    $fileValues = [];
    $candidates = [
        __DIR__ . '/.smtp',
        dirname(__DIR__, 2) . '/.smtp',
    ];
    foreach ($candidates as $path) {
        if (is_readable($path)) {
            foreach (preg_split('/\r\n|\r|\n/', (string) file_get_contents($path)) as $rawLine) {
                $line = trim($rawLine);
                if ($line === '' || $line[0] === '#' || strpos($line, '=') === false) {
                    continue;
                }
                [$k, $v] = explode('=', $line, 2);
                $fileValues[strtolower(trim($k))] = trim($v);
            }
            break;
        }
    }

    $get = static function (string $envName, string $fileKey, string $default = '') use ($fileValues): string {
        $env = getenv($envName);
        if (is_string($env) && trim($env) !== '') {
            return trim($env);
        }
        if (isset($fileValues[$fileKey]) && $fileValues[$fileKey] !== '') {
            return $fileValues[$fileKey];
        }
        return $default;
    };

    $host = $get('SMTP_HOST', 'host');
    if ($host === '') {
        return null;
    }

    $secure = strtolower($get('SMTP_SECURE', 'secure', 'tls'));
    if (!in_array($secure, ['tls', 'ssl', 'none'], true)) {
        $secure = 'tls';
    }
    $port = (int) $get('SMTP_PORT', 'port', $secure === 'ssl' ? '465' : '587');

    return [
        'host' => $host,
        'port' => $port > 0 ? $port : 587,
        'user' => $get('SMTP_USER', 'user'),
        'pass' => $get('SMTP_PASS', 'pass'),
        'secure' => $secure,
        'from' => $get('SMTP_FROM', 'from'),
        'from_name' => $get('SMTP_FROM_NAME', 'from_name', 'Pura Capoeira'),
    ];
}

/** Read one line (or multiline) SMTP reply and return [code, fullText]. */
function smtpReadReply($conn): array
{
    $text = '';
    $code = 0;
    while (($line = fgets($conn, 1024)) !== false) {
        $text .= $line;
        // Lines look like "250-..." (more to come) or "250 ..." (final).
        if (strlen($line) >= 4 && ($line[3] === ' ' )) {
            $code = (int) substr($line, 0, 3);
            break;
        }
    }
    return [$code, $text];
}

/** Send one SMTP command and assert the reply starts with an expected code. */
function smtpCommand($conn, string $cmd, array $expected): array
{
    if ($cmd !== '') {
        fwrite($conn, $cmd . "\r\n");
    }
    [$code, $text] = smtpReadReply($conn);
    return [in_array($code, $expected, true), $code, $text];
}

/**
 * Minimal dependency-free SMTP sender. Supports implicit TLS (ssl), STARTTLS
 * (tls) and plain (none), with AUTH LOGIN. Returns true on success.
 */
function smtpSend(array $cfg, string $fromEmail, string $fromName, string $to, string $subject, string $body, string $replyTo = ''): bool
{
    $host = $cfg['host'];
    $port = (int) $cfg['port'];
    $secure = $cfg['secure'];

    $transport = $secure === 'ssl' ? 'ssl://' . $host : $host;
    $context = stream_context_create([
        'ssl' => ['verify_peer' => true, 'verify_peer_name' => true, 'SNI_enabled' => true],
    ]);

    $errno = 0;
    $errstr = '';
    $conn = @stream_socket_client(
        $transport . ':' . $port,
        $errno,
        $errstr,
        15,
        STREAM_CLIENT_CONNECT,
        $context
    );
    if (!$conn) {
        error_log('SMTP connect failed: ' . $errstr . ' (' . $errno . ')');
        return false;
    }
    stream_set_timeout($conn, 15);

    $fail = static function (string $msg) use ($conn): bool {
        error_log('SMTP error: ' . $msg);
        @fclose($conn);
        return false;
    };

    [$ok] = smtpCommand($conn, '', [220]);
    if (!$ok) {
        return $fail('no greeting');
    }

    $ehloHost = preg_replace('/[^a-z0-9.\-]/i', '', (string) ($_SERVER['HTTP_HOST'] ?? 'localhost')) ?: 'localhost';

    [$ok] = smtpCommand($conn, 'EHLO ' . $ehloHost, [250]);
    if (!$ok) {
        return $fail('EHLO rejected');
    }

    if ($secure === 'tls') {
        [$ok] = smtpCommand($conn, 'STARTTLS', [220]);
        if (!$ok) {
            return $fail('STARTTLS rejected');
        }
        $crypto = STREAM_CRYPTO_METHOD_TLS_CLIENT;
        if (defined('STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT')) {
            $crypto |= STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT;
        }
        if (!@stream_socket_enable_crypto($conn, true, $crypto)) {
            return $fail('TLS negotiation failed');
        }
        // Re-issue EHLO over the now-encrypted channel.
        [$ok] = smtpCommand($conn, 'EHLO ' . $ehloHost, [250]);
        if (!$ok) {
            return $fail('EHLO after STARTTLS rejected');
        }
    }

    if ($cfg['user'] !== '' && $cfg['pass'] !== '') {
        [$ok] = smtpCommand($conn, 'AUTH LOGIN', [334]);
        if (!$ok) {
            return $fail('AUTH LOGIN unsupported');
        }
        [$ok] = smtpCommand($conn, base64_encode($cfg['user']), [334]);
        if (!$ok) {
            return $fail('username rejected');
        }
        [$ok] = smtpCommand($conn, base64_encode($cfg['pass']), [235]);
        if (!$ok) {
            return $fail('authentication failed');
        }
    }

    [$ok] = smtpCommand($conn, 'MAIL FROM:<' . $fromEmail . '>', [250]);
    if (!$ok) {
        return $fail('MAIL FROM rejected');
    }
    [$ok] = smtpCommand($conn, 'RCPT TO:<' . $to . '>', [250, 251]);
    if (!$ok) {
        return $fail('RCPT TO rejected');
    }
    [$ok] = smtpCommand($conn, 'DATA', [354]);
    if (!$ok) {
        return $fail('DATA rejected');
    }

    $date = date('r');
    $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
    $encodedFromName = '=?UTF-8?B?' . base64_encode($fromName) . '?=';
    $headers = [
        'Date: ' . $date,
        'From: ' . $encodedFromName . ' <' . $fromEmail . '>',
        'To: ' . $to,
        'Subject: ' . $encodedSubject,
        'MIME-Version: 1.0',
        'Content-Type: text/plain; charset=UTF-8',
        'Content-Transfer-Encoding: 8bit',
    ];
    if ($replyTo !== '') {
        $headers[] = 'Reply-To: ' . $replyTo;
    }

    // Dot-stuff the body so lines starting with "." are not treated as EOF.
    $normalizedBody = preg_replace('/\r\n|\r|\n/', "\r\n", $body);
    $normalizedBody = preg_replace('/^\./m', '..', $normalizedBody);

    $message = implode("\r\n", $headers) . "\r\n\r\n" . $normalizedBody . "\r\n.";
    [$ok] = smtpCommand($conn, $message, [250]);
    if (!$ok) {
        return $fail('message not accepted');
    }

    smtpCommand($conn, 'QUIT', [221]);
    @fclose($conn);
    return true;
}

/**
 * Send a plain-text email notification about a new inscription. Best-effort:
 * failures are swallowed so they never block the checkout/webhook response.
 */
function notifyInscription(array $entry): void
{
    $recipients = inscriptionNotifyEmails();
    if (empty($recipients)) {
        return;
    }

    $statusLabels = [
        'paid' => 'Pago recibido',
        'pending_payment' => 'Pago iniciado (pendiente)',
        'pending_payment_offline' => 'Registro sin pago (pagará en persona)',
        'free' => 'Inscripción con beca (gratis)',
    ];
    $status = (string) ($entry['status'] ?? '');
    $statusText = $statusLabels[$status] ?? $status;

    $student = $entry['student'] ?? [];
    $name = trim((string) (
        ($entry['student_name'] ?? '')
        ?: trim((string) ($student['first_name'] ?? '') . ' ' . (string) ($student['last_name'] ?? ''))
    ));
    $email = (string) ($entry['email'] ?? ($student['email'] ?? ''));
    $phone = (string) ($student['phone'] ?? '');
    $currency = (string) ($entry['currency'] ?? storeCurrency());
    $amount = $entry['amount'];

    $lines = [
        'Nueva inscripción en Pura Capoeira Cuernavaca',
        '',
        'Tipo: ' . (!empty($entry['member']) ? 'Pago de mensualidad (alumno existente)' : 'Nueva inscripción'),
        'Grupo: ' . (($entry['group'] ?? 'adult') === 'kids' ? 'Niños (3-7)' : 'Adultos / mixto (8+)'),
        'Estado: ' . $statusText,
        'Paquete: ' . (string) ($entry['label'] ?? ($entry['plan'] ?? '')),
    ];
    if (is_numeric($amount)) {
        $lines[] = 'Monto: ' . number_format((float) $amount, 2) . ' ' . $currency;
    }
    if ($name !== '') {
        $lines[] = 'Alumno: ' . $name;
    }
    if ($email !== '') {
        $lines[] = 'Correo: ' . $email;
    }
    if ($phone !== '') {
        $lines[] = 'Teléfono: ' . $phone;
    }
    if (!empty($entry['trial_date'])) {
        $lines[] = 'Fecha de Clase de Prueba: ' . (string) $entry['trial_date'];
    }
    if (!empty($entry['promocode'])) {
        $lines[] = 'Código promocional: ' . (string) $entry['promocode'];
    }
    if (!empty($student['emergency_phone'])) {
        $lines[] = 'Teléfono de emergencia: ' . (string) $student['emergency_phone'];
    }
    if (!empty($student['address'])) {
        $lines[] = 'Dirección: ' . (string) $student['address'];
    }
    if (!empty($student['dob'])) {
        $lines[] = 'Fecha de nacimiento: ' . (string) $student['dob'];
    }
    $lines[] = '';
    $lines[] = 'Fecha de registro: ' . ($entry['ts'] ?? date('c'));

    $subject = 'Nueva inscripción (' . $statusText . ')' . ($name !== '' ? ' — ' . $name : '');
    $body = implode("\n", $lines);

    $replyTo = ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) ? $email : '';

    // Prefer SMTP when configured; fall back to PHP mail().
    $smtp = smtpSettings();
    if ($smtp !== null) {
        $fromEmail = $smtp['from'] !== '' ? $smtp['from'] : inscriptionNotifyFrom();
        $fromName = $smtp['from_name'] !== '' ? $smtp['from_name'] : 'Pura Capoeira';
        foreach ($recipients as $to) {
            smtpSend($smtp, $fromEmail, $fromName, $to, $subject, $body, $replyTo);
        }
        return;
    }

    $from = inscriptionNotifyFrom();
    $headers = [
        'From: Pura Capoeira <' . $from . '>',
        'Content-Type: text/plain; charset=UTF-8',
        'X-Mailer: PHP/' . phpversion(),
    ];
    if ($replyTo !== '') {
        $headers[] = 'Reply-To: ' . $replyTo;
    }

    $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
    foreach ($recipients as $to) {
        @mail($to, $encodedSubject, $body, implode("\r\n", $headers));
    }
}


function printfulRequest(string $method, string $path, string $token, ?array $body = null): array
{
    $baseUrl = getenv('PRINTFUL_API_BASE_URL');
    if (!is_string($baseUrl) || trim($baseUrl) === '') {
        $baseUrl = 'https://api.printful.com';
    }

    $url = rtrim($baseUrl, '/') . '/' . ltrim($path, '/');
    $ch = curl_init($url);
    if ($ch === false) {
        return ['ok' => false, 'status' => 500, 'error' => 'No se pudo inicializar cURL'];
    }

    $headers = [
        'Authorization: Bearer ' . $token,
        'Accept: application/json',
    ];

    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => strtoupper($method),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTPHEADER => $headers,
    ]);

    if ($body !== null) {
        $jsonBody = json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($jsonBody === false) {
            return ['ok' => false, 'status' => 500, 'error' => 'No se pudo codificar la solicitud'];
        }
        curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonBody);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array_merge($headers, ['Content-Type: application/json']));
    }

    $raw = curl_exec($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($raw === false) {
        return ['ok' => false, 'status' => 502, 'error' => $curlError !== '' ? $curlError : 'Error de conexión con Printful'];
    }

    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        return ['ok' => false, 'status' => $httpCode > 0 ? $httpCode : 502, 'error' => 'Respuesta inválida de Printful', 'raw' => $raw];
    }

    $isSuccess = $httpCode >= 200 && $httpCode < 300 && (($decoded['code'] ?? 0) === 200 || isset($decoded['result']));

    return ['ok' => $isSuccess, 'status' => $httpCode > 0 ? $httpCode : 500, 'payload' => $decoded];
}

/**
 * Call the Stripe REST API with form-encoded params (no SDK required).
 */
function stripeRequest(string $method, string $path, string $secretKey, array $params = []): array
{
    $url = 'https://api.stripe.com/v1/' . ltrim($path, '/');
    $ch = curl_init($url);
    if ($ch === false) {
        return ['ok' => false, 'status' => 500, 'error' => 'No se pudo inicializar cURL'];
    }

    $opts = [
        CURLOPT_CUSTOMREQUEST => strtoupper($method),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $secretKey,
            'Content-Type: application/x-www-form-urlencoded',
        ],
    ];

    if (!empty($params)) {
        $opts[CURLOPT_POSTFIELDS] = http_build_query($params, '', '&');
    }

    curl_setopt_array($ch, $opts);

    $raw = curl_exec($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($raw === false) {
        return ['ok' => false, 'status' => 502, 'error' => $curlError !== '' ? $curlError : 'Error de conexión con Stripe'];
    }

    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        return ['ok' => false, 'status' => $httpCode > 0 ? $httpCode : 502, 'error' => 'Respuesta inválida de Stripe', 'raw' => $raw];
    }

    return ['ok' => $httpCode >= 200 && $httpCode < 300, 'status' => $httpCode, 'payload' => $decoded];
}

/**
 * Verify a Stripe webhook signature (Stripe-Signature header).
 */
function verifyStripeSignature(string $payload, string $sigHeader, string $secret, int $tolerance = 300): bool
{
    $timestamp = null;
    $signatures = [];
    foreach (explode(',', $sigHeader) as $part) {
        $kv = explode('=', trim($part), 2);
        if (count($kv) !== 2) {
            continue;
        }
        if ($kv[0] === 't') {
            $timestamp = (int) $kv[1];
        } elseif ($kv[0] === 'v1') {
            $signatures[] = $kv[1];
        }
    }

    if ($timestamp === null || empty($signatures)) {
        return false;
    }

    if (abs(time() - $timestamp) > $tolerance) {
        return false;
    }

    $expected = hash_hmac('sha256', $timestamp . '.' . $payload, $secret);
    foreach ($signatures as $candidate) {
        if (hash_equals($expected, $candidate)) {
            return true;
        }
    }

    return false;
}

function readJsonBody(): array
{
    $raw = file_get_contents('php://input');
    $data = json_decode((string) $raw, true);
    return is_array($data) ? $data : [];
}

function sanitizeRecipient(array $r): array
{
    return [
        'name' => trim((string) ($r['name'] ?? '')),
        'email' => trim((string) ($r['email'] ?? '')),
        'address1' => trim((string) ($r['address1'] ?? '')),
        'city' => trim((string) ($r['city'] ?? '')),
        'state_code' => trim((string) ($r['state_code'] ?? '')),
        'country_code' => strtoupper(trim((string) ($r['country_code'] ?? ''))),
        'zip' => trim((string) ($r['zip'] ?? '')),
    ];
}

$action = isset($_GET['action']) ? (string) $_GET['action'] : '';

/* ---------------------------------------------------------------------------
 * 0) CONFIG (currency + price multiplier for the storefront)
 * ------------------------------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'config') {
    jsonResponse(200, [
        'ok' => true,
        'currency' => storeCurrency(),
        'price_multiplier' => priceMultiplier(),
    ]);
}

/* ---------------------------------------------------------------------------
 * 0b) INSCRIPTION CONFIG (membership plan prices for the inscriptions page)
 * ------------------------------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'inscription-config') {
    $group = inscriptionGroup($_GET['group'] ?? 'adult');
    $plans = [];
    foreach (inscriptionPlans(null, $group) as $id => $p) {
        $plans[] = [
            'id' => $id,
            'label' => $p['label'],
            'amount' => $p['amount'] / 100,
            'allow_addon' => $p['allow_addon'],
        ];
    }
    jsonResponse(200, [
        'ok' => true,
        'currency' => storeCurrency(),
        'group' => $group,
        'addon_amount' => inscriptionAddonAmount() / 100,
        'plans' => $plans,
    ]);
}

/* ---------------------------------------------------------------------------
 * 0c) VALIDATE PROMO CODE (returns the code's effect for the inscriptions page)
 * ------------------------------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'validate-promo') {
    $data = readJsonBody();
    $code = trim((string) ($data['promocode'] ?? ''));
    if ($code === '') {
        jsonResponse(400, ['ok' => false, 'error' => 'Escribe un código para validarlo']);
    }
    $group = inscriptionGroup($data['group'] ?? 'adult');

    $promo = resolveInscriptionPromo($code, $group);
    if ($promo['type'] === 'none') {
        jsonResponse(200, ['ok' => true, 'valid' => false]);
    }

    $plans = [];
    foreach (inscriptionPlans($promo['monthly'], $group) as $id => $p) {
        $plans[] = [
            'id' => $id,
            'label' => $p['label'],
            'amount' => $p['amount'] / 100,
            'allow_addon' => $p['allow_addon'],
        ];
    }

    jsonResponse(200, [
        'ok' => true,
        'valid' => true,
        'type' => $promo['type'],
        'free' => $promo['free'],
        'payment_optional' => $promo['payment_optional'],
        'monthly' => $promo['monthly'] / 100,
        'group' => $group,
        'addon_amount' => inscriptionAddonAmount() / 100,
        'plans' => $plans,
    ]);
}

/* ---------------------------------------------------------------------------
 * 1) SHIPPING RATES
 * ------------------------------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'shipping') {
    $printfulToken = readSecret('PRINTFUL_API_KEY', '.printful-token');
    if ($printfulToken === null) {
        jsonResponse(500, ['ok' => false, 'error' => 'Falta configurar PRINTFUL_API_KEY']);
    }

    $data = readJsonBody();
    $recipient = sanitizeRecipient($data['recipient'] ?? []);
    $items = $data['items'] ?? [];

    if ($recipient['country_code'] === '' || $recipient['address1'] === '' || !is_array($items) || count($items) === 0) {
        jsonResponse(400, ['ok' => false, 'error' => 'Dirección e items son obligatorios para calcular envío']);
    }

    $rateItems = [];
    foreach ($items as $item) {
        $variantId = (int) ($item['variant_id'] ?? 0); // Printful catalog variant id
        $qty = max(1, (int) ($item['quantity'] ?? 1));
        if ($variantId > 0) {
            $rateItems[] = ['variant_id' => $variantId, 'quantity' => $qty];
        }
    }

    if (count($rateItems) === 0) {
        jsonResponse(400, ['ok' => false, 'error' => 'No se reconocieron variantes válidas']);
    }

    $payload = [
        'recipient' => [
            'address1' => $recipient['address1'],
            'city' => $recipient['city'],
            'country_code' => $recipient['country_code'],
            'state_code' => $recipient['state_code'],
            'zip' => $recipient['zip'],
        ],
        'items' => $rateItems,
        'currency' => storeCurrency(),
        'locale' => 'es_ES',
    ];

    $response = printfulRequest('POST', '/shipping/rates', $printfulToken, $payload);
    if (!$response['ok']) {
        jsonResponse($response['status'] ?? 500, [
            'ok' => false,
            'error' => 'No fue posible calcular el envío',
            'details' => $response['payload'] ?? ($response['error'] ?? null),
        ]);
    }

    jsonResponse(200, [
        'ok' => true,
        'currency' => storeCurrency(),
        'rates' => $response['payload']['result'] ?? [],
    ]);
}

/* ---------------------------------------------------------------------------
 * 2) CREATE CHECKOUT (Printful draft order + Stripe Checkout Session)
 * ------------------------------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'create-checkout') {
    $printfulToken = readSecret('PRINTFUL_API_KEY', '.printful-token');
    $stripeSecret = readSecret('STRIPE_SECRET_KEY', '.stripe-secret');

    if ($printfulToken === null) {
        jsonResponse(500, ['ok' => false, 'error' => 'Falta configurar PRINTFUL_API_KEY']);
    }
    if ($stripeSecret === null) {
        jsonResponse(500, ['ok' => false, 'error' => 'Falta configurar STRIPE_SECRET_KEY']);
    }

    $data = readJsonBody();
    $recipient = sanitizeRecipient($data['recipient'] ?? []);
    $items = $data['items'] ?? [];
    $shippingId = trim((string) ($data['shipping_id'] ?? ''));
    $shippingName = trim((string) ($data['shipping_name'] ?? 'Envío'));
    $shippingRate = (float) ($data['shipping_rate'] ?? 0);

    if ($recipient['name'] === '' || $recipient['email'] === '' || $recipient['address1'] === '' || $recipient['country_code'] === '') {
        jsonResponse(400, ['ok' => false, 'error' => 'Datos de envío incompletos']);
    }
    if (!is_array($items) || count($items) === 0) {
        jsonResponse(400, ['ok' => false, 'error' => 'El carrito está vacío']);
    }
    if ($shippingId === '') {
        jsonResponse(400, ['ok' => false, 'error' => 'Selecciona un método de envío']);
    }

    $currency = storeCurrency();
    $multiplier = priceMultiplier();

    // Build Printful order items (sync_variant_id) and Stripe line items in parallel.
    $orderItems = [];
    $stripeParams = [
        'mode' => 'payment',
        'success_url' => siteBaseUrl() . '/tienda.html?checkout=success&session_id={CHECKOUT_SESSION_ID}',
        'cancel_url' => siteBaseUrl() . '/tienda.html?checkout=cancel',
        'customer_email' => $recipient['email'],
        'payment_method_types' => ['card'],
    ];

    $lineIndex = 0;
    foreach ($items as $item) {
        $syncVariantId = (int) ($item['sync_variant_id'] ?? 0);
        $qty = max(1, (int) ($item['quantity'] ?? 1));
        $unitPrice = (float) ($item['retail_price'] ?? 0) * $multiplier;
        $name = trim((string) ($item['name'] ?? 'Producto'));

        if ($syncVariantId <= 0 || $unitPrice <= 0) {
            continue;
        }

        $orderItems[] = [
            'sync_variant_id' => $syncVariantId,
            'quantity' => $qty,
            'retail_price' => number_format($unitPrice, 2, '.', ''),
        ];

        $stripeParams['line_items'][$lineIndex] = [
            'quantity' => $qty,
            'price_data' => [
                'currency' => strtolower($currency),
                'unit_amount' => (int) round($unitPrice * 100),
                'product_data' => ['name' => $name],
            ],
        ];
        $lineIndex++;
    }

    if (count($orderItems) === 0) {
        jsonResponse(400, ['ok' => false, 'error' => 'No hay artículos válidos para cobrar']);
    }

    // Shipping as a Stripe shipping option (charged on top of items).
    // Printful already returns the shipping rate in the store currency (MXN),
    // so it must NOT be passed through the USD->MXN multiplier.
    if ($shippingRate > 0) {
        $stripeParams['shipping_options'][0] = [
            'shipping_rate_data' => [
                'type' => 'fixed_amount',
                'display_name' => $shippingName,
                'fixed_amount' => [
                    'amount' => (int) round($shippingRate * 100),
                    'currency' => strtolower($currency),
                ],
            ],
        ];
    }

    // --- Step A: create the Printful DRAFT order (confirm=false). ---
    $orderPayload = [
        'external_id' => 'capoeira-' . time() . '-' . bin2hex(random_bytes(3)),
        'recipient' => $recipient,
        'items' => $orderItems,
        'shipping' => $shippingId,
        'confirm' => false,
        'retail_costs' => ['currency' => $currency],
    ];

    $orderResponse = printfulRequest('POST', '/orders', $printfulToken, $orderPayload);
    if (!$orderResponse['ok']) {
        jsonResponse($orderResponse['status'] ?? 500, [
            'ok' => false,
            'error' => 'No fue posible preparar la orden en Printful',
            'details' => $orderResponse['payload'] ?? ($orderResponse['error'] ?? null),
        ]);
    }

    $printfulOrderId = $orderResponse['payload']['result']['id'] ?? null;
    if ($printfulOrderId === null) {
        jsonResponse(502, ['ok' => false, 'error' => 'Printful no devolvió un id de orden']);
    }

    // Tie the Stripe session to the Printful order so the webhook can confirm it.
    $stripeParams['metadata'] = ['printful_order_id' => (string) $printfulOrderId];
    $stripeParams['client_reference_id'] = (string) $printfulOrderId;

    // --- Step B: create the Stripe Checkout Session. ---
    $sessionResponse = stripeRequest('POST', 'checkout/sessions', $stripeSecret, $stripeParams);
    if (!$sessionResponse['ok'] || empty($sessionResponse['payload']['url'])) {
        jsonResponse($sessionResponse['status'] ?? 500, [
            'ok' => false,
            'error' => 'No fue posible iniciar el pago con Stripe',
            'details' => $sessionResponse['payload']['error'] ?? ($sessionResponse['error'] ?? null),
        ]);
    }

    jsonResponse(200, [
        'ok' => true,
        'url' => $sessionResponse['payload']['url'],
        'printful_order_id' => $printfulOrderId,
    ]);
}

/* ---------------------------------------------------------------------------
 * 3) STRIPE WEBHOOK -> confirm Printful order after successful payment
 * ------------------------------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'webhook') {
    $payload = (string) file_get_contents('php://input');
    $sigHeader = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';
    $webhookSecret = readSecret('STRIPE_WEBHOOK_SECRET', '.stripe-webhook-secret');
    $printfulToken = readSecret('PRINTFUL_API_KEY', '.printful-token');

    if ($webhookSecret === null || $printfulToken === null) {
        http_response_code(500);
        exit;
    }

    if (!verifyStripeSignature($payload, $sigHeader, $webhookSecret)) {
        http_response_code(400);
        exit;
    }

    $event = json_decode($payload, true);
    if (!is_array($event)) {
        http_response_code(400);
        exit;
    }

    $type = $event['type'] ?? '';

    if ($type === 'checkout.session.completed' || $type === 'checkout.session.async_payment_succeeded') {
        $session = $event['data']['object'] ?? [];
        $paymentStatus = $session['payment_status'] ?? '';
        $orderId = $session['metadata']['printful_order_id']
            ?? ($session['client_reference_id'] ?? null);

        if ($paymentStatus === 'paid' && $orderId) {
            // Confirm the draft for fulfillment.
            printfulRequest('POST', '/orders/' . rawurlencode((string) $orderId) . '/confirm', $printfulToken);
        }

        // Inscription (membership) payments: record a paid entry.
        $meta = $session['metadata'] ?? [];
        if (($meta['type'] ?? '') === 'inscription' && $paymentStatus === 'paid') {
            $paidEntry = [
                'status' => 'paid',
                'plan' => $meta['plan'] ?? '',
                'label' => $meta['label'] ?? '',
                'currency' => strtoupper((string) ($session['currency'] ?? storeCurrency())),
                'amount' => isset($session['amount_total']) ? ((int) $session['amount_total']) / 100 : null,
                'student_name' => $meta['student_name'] ?? '',
                'email' => $meta['email'] ?? ($session['customer_email'] ?? ''),
                'trial_date' => $meta['trial_date'] ?? '',
                'group' => $meta['group'] ?? 'adult',
                'session_id' => $session['id'] ?? '',
            ];
            logInscription($paidEntry);
            notifyInscription($paidEntry);
        }
    }

    // Always acknowledge so Stripe stops retrying.
    http_response_code(200);
    echo json_encode(['received' => true]);
    exit;
}

/* ---------------------------------------------------------------------------
 * 4) INSCRIPTION CHECKOUT (membership sign-up + Stripe payment)
 * ------------------------------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'inscription') {
    $data = readJsonBody();

    // Existing students can pay a recurring fee with just their email. New
    // students must complete the full form.
    $isMember = !empty($data['member']);
    $group = inscriptionGroup($data['group'] ?? 'adult');

    if ($isMember) {
        $memberEmail = trim((string) ($data['email'] ?? ''));
        if ($memberEmail === '' || !filter_var($memberEmail, FILTER_VALIDATE_EMAIL)) {
            jsonResponse(400, ['ok' => false, 'error' => 'Ingresa un correo electrónico válido']);
        }
        $found = findInscriptionByEmail($memberEmail);
        if ($found === null) {
            // No prior registration: steer them to the full inscription form.
            jsonResponse(404, [
                'ok' => false,
                'not_registered' => true,
                'error' => 'No encontramos un registro con ese correo. Completa el formulario de inscripción para continuar.',
            ]);
        }
        $student = $found;
        $student['email'] = $memberEmail;
    } else {
        $student = [
            'first_name' => trim((string) ($data['first_name'] ?? '')),
            'last_name' => trim((string) ($data['last_name'] ?? '')),
            'parent_name' => trim((string) ($data['parent_name'] ?? '')),
            'address' => trim((string) ($data['address'] ?? '')),
            'email' => trim((string) ($data['email'] ?? '')),
            'phone' => trim((string) ($data['phone'] ?? '')),
            'parent_phone' => trim((string) ($data['parent_phone'] ?? '')),
            'emergency_phone' => trim((string) ($data['emergency_phone'] ?? '')),
            'dob' => trim((string) ($data['dob'] ?? '')),
        ];

        $required = ['first_name', 'last_name', 'address', 'email', 'phone', 'emergency_phone', 'dob'];
        foreach ($required as $field) {
            if ($student[$field] === '') {
                jsonResponse(400, ['ok' => false, 'error' => 'Faltan datos obligatorios del formulario']);
            }
        }
        if (!filter_var($student['email'], FILTER_VALIDATE_EMAIL)) {
            jsonResponse(400, ['ok' => false, 'error' => 'El correo electrónico no es válido']);
        }
    }

    $plans = inscriptionPlans(null, $group);
    $planId = (string) ($data['plan'] ?? '');
    if (!isset($plans[$planId])) {
        jsonResponse(400, ['ok' => false, 'error' => 'Selecciona un plan válido']);
    }

    // Trial class requires a chosen date (must be today or in the future).
    $trialDate = trim((string) ($data['trial_date'] ?? ''));
    if ($planId === 'trial') {
        $d = DateTime::createFromFormat('Y-m-d', $trialDate);
        $validFormat = $d !== false && $d->format('Y-m-d') === $trialDate;
        if (!$validFormat) {
            jsonResponse(400, ['ok' => false, 'error' => 'Selecciona una fecha válida para tu Clase de Prueba']);
        }
        $today = new DateTime('today');
        if ($d < $today) {
            jsonResponse(400, ['ok' => false, 'error' => 'La fecha de la Clase de Prueba no puede estar en el pasado']);
        }
    } else {
        $trialDate = '';
    }

    // Resolve the promo code first so plan prices reflect the right monthly rate.
    $promo = trim((string) ($data['promocode'] ?? ''));
    $promoEffect = resolveInscriptionPromo($promo, $group);
    $plans = inscriptionPlans($promoEffect['monthly'], $group);
    $plan = $plans[$planId];

    $amount = $plan['amount']; // MXN cents
    $addInscription = !empty($data['add_inscription']) && $plan['allow_addon'];
    if ($addInscription) {
        $amount += inscriptionAddonAmount();
    }
    $label = $plan['label'] . ($addInscription ? ' + inscripción' : '');

    // Beca code -> 100% scholarship (free).
    if ($promoEffect['type'] === 'beca') {
        $amount = 0;
    }

    $studentName = trim($student['first_name'] . ' ' . $student['last_name']);
    $currency = storeCurrency();

    // Current-student code -> payment is optional. If the student chose to pay
    // later (or the total is zero), register without charging.
    $payLater = false;
    if ($promoEffect['payment_optional']) {
        $mode = strtolower(trim((string) ($data['payment_mode'] ?? '')));
        if ($mode === 'later' || $promoEffect['free']) {
            $payLater = true;
        }
    }

    // Full scholarship or opt-out of immediate payment: register directly.
    if ($amount <= 0 || $payLater) {
        $free = ($amount <= 0);
        $offlineEntry = [
            'status' => $free ? 'free' : 'pending_payment_offline',
            'plan' => $planId,
            'label' => $label,
            'amount' => $amount / 100,
            'currency' => $currency,
            'promocode' => $promo,
            'promo_type' => $promoEffect['type'],
            'trial_date' => $trialDate,
            'group' => $group,
            'member' => $isMember ? 1 : 0,
            'student' => $student,
        ];
        logInscription($offlineEntry);
        notifyInscription($offlineEntry);
        $message = $free
            ? 'Inscripción registrada con beca del 100%. Te contactaremos para confirmar tu lugar.'
            : 'Inscripción registrada. Te contactaremos para coordinar el pago de ' . number_format($amount / 100, 2) . ' ' . $currency . '.';
        jsonResponse(200, [
            'ok' => true,
            'free' => true,
            'message' => $message,
        ]);
    }

    $stripeSecret = readSecret('STRIPE_SECRET_KEY', '.stripe-secret');
    if ($stripeSecret === null) {
        jsonResponse(500, ['ok' => false, 'error' => 'Falta configurar STRIPE_SECRET_KEY']);
    }

    logInscription([
        'status' => 'pending_payment',
        'plan' => $planId,
        'label' => $label,
        'amount' => $amount / 100,
        'currency' => $currency,
        'promocode' => $promo,
        'promo_type' => $promoEffect['type'],
        'trial_date' => $trialDate,
        'group' => $group,
        'member' => $isMember ? 1 : 0,
        'student' => $student,
    ]);

    notifyInscription([
        'status' => 'pending_payment',
        'plan' => $planId,
        'label' => $label,
        'amount' => $amount / 100,
        'currency' => $currency,
        'promocode' => $promo,
        'promo_type' => $promoEffect['type'],
        'trial_date' => $trialDate,
        'group' => $group,
        'member' => $isMember ? 1 : 0,
        'student' => $student,
    ]);

    $stripeParams = [
        'mode' => 'payment',
        'success_url' => siteBaseUrl() . '/inscripciones.html?inscription=success&session_id={CHECKOUT_SESSION_ID}',
        'cancel_url' => siteBaseUrl() . '/inscripciones.html?inscription=cancel',
        'customer_email' => $student['email'],
        'payment_method_types' => ['card'],
        'line_items' => [[
            'quantity' => 1,
            'price_data' => [
                'currency' => strtolower($currency),
                'unit_amount' => $amount,
                'product_data' => ['name' => 'Pura Capoeira — ' . $label],
            ],
        ]],
        'metadata' => [
            'type' => 'inscription',
            'plan' => $planId,
            'label' => $label,
            'student_name' => $studentName,
            'email' => $student['email'],
            'phone' => $student['phone'],
            'emergency_phone' => $student['emergency_phone'],
            'dob' => $student['dob'],
            'trial_date' => $trialDate,
            'group' => $group,
            'member' => $isMember ? '1' : '0',
        ],
    ];

    $sessionResponse = stripeRequest('POST', 'checkout/sessions', $stripeSecret, $stripeParams);
    if (!$sessionResponse['ok'] || empty($sessionResponse['payload']['url'])) {
        jsonResponse($sessionResponse['status'] ?? 500, [
            'ok' => false,
            'error' => 'No fue posible iniciar el pago con Stripe',
            'details' => $sessionResponse['payload']['error'] ?? ($sessionResponse['error'] ?? null),
        ]);
    }

    jsonResponse(200, [
        'ok' => true,
        'url' => $sessionResponse['payload']['url'],
    ]);
}

jsonResponse(404, ['ok' => false, 'error' => 'Acción no encontrada']);
