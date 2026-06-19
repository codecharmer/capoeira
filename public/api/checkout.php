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
    }

    // Always acknowledge so Stripe stops retrying.
    http_response_code(200);
    echo json_encode(['received' => true]);
    exit;
}

jsonResponse(404, ['ok' => false, 'error' => 'Acción no encontrada']);
