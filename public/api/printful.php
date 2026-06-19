<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

function respond(int $status, array $payload): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function getToken(): ?string
{
    $token = getenv('PRINTFUL_API_KEY');
    if (is_string($token) && trim($token) !== '') {
        return trim($token);
    }

    $localTokenPath = __DIR__ . '/.printful-token';
    if (is_readable($localTokenPath)) {
        $fileToken = trim((string) file_get_contents($localTokenPath));
        if ($fileToken !== '') {
            return $fileToken;
        }
    }

    // Preferred secure location: outside the web root (one level above public_html)
    $secureTokenPath = dirname(__DIR__, 2) . '/.printful-token';
    if (is_readable($secureTokenPath)) {
        $secureToken = trim((string) file_get_contents($secureTokenPath));
        if ($secureToken !== '') {
            return $secureToken;
        }
    }

    return null;
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
        return [
            'ok' => false,
            'status' => $httpCode > 0 ? $httpCode : 502,
            'error' => 'Respuesta inválida de Printful',
            'raw' => $raw,
        ];
    }

    $isSuccess = $httpCode >= 200 && $httpCode < 300 && (($decoded['code'] ?? 0) === 200 || isset($decoded['result']));

    return [
        'ok' => $isSuccess,
        'status' => $httpCode > 0 ? $httpCode : 500,
        'payload' => $decoded,
    ];
}

$token = getToken();
if ($token === null) {
    respond(500, [
        'ok' => false,
        'error' => 'Falta configurar PRINTFUL_API_KEY en el servidor',
    ]);
}

$action = isset($_GET['action']) ? (string) $_GET['action'] : '';

if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'products') {
    $response = printfulRequest('GET', '/store/products', $token);
    if (!$response['ok']) {
        respond($response['status'] ?? 500, [
            'ok' => false,
            'error' => 'No fue posible obtener productos',
            'details' => $response['payload'] ?? ($response['error'] ?? null),
        ]);
    }

    $result = $response['payload']['result'] ?? [];
    respond(200, ['ok' => true, 'products' => $result]);
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'product') {
    $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
    if ($id <= 0) {
        respond(400, ['ok' => false, 'error' => 'Parámetro id inválido']);
    }

    $response = printfulRequest('GET', '/store/products/' . $id, $token);
    if (!$response['ok']) {
        respond($response['status'] ?? 500, [
            'ok' => false,
            'error' => 'No fue posible obtener el producto',
            'details' => $response['payload'] ?? ($response['error'] ?? null),
        ]);
    }

    respond(200, ['ok' => true, 'product' => $response['payload']['result'] ?? null]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'order') {
    $rawBody = file_get_contents('php://input');
    $data = json_decode((string) $rawBody, true);

    if (!is_array($data)) {
        respond(400, ['ok' => false, 'error' => 'Body JSON inválido']);
    }

    $recipient = $data['recipient'] ?? null;
    $items = $data['items'] ?? null;

    if (!is_array($recipient) || !is_array($items) || count($items) === 0) {
        respond(400, ['ok' => false, 'error' => 'recipient e items son obligatorios']);
    }

    $payload = [
        'recipient' => $recipient,
        'items' => $items,
        'confirm' => false,
        'retail_costs' => [
            'currency' => $data['currency'] ?? 'USD',
        ],
    ];

    if (isset($data['external_id']) && is_string($data['external_id']) && $data['external_id'] !== '') {
        $payload['external_id'] = $data['external_id'];
    }

    if (isset($data['shipping']) && is_string($data['shipping']) && $data['shipping'] !== '') {
        $payload['shipping'] = $data['shipping'];
    }

    $response = printfulRequest('POST', '/orders', $token, $payload);
    if (!$response['ok']) {
        respond($response['status'] ?? 500, [
            'ok' => false,
            'error' => 'No fue posible crear la orden',
            'details' => $response['payload'] ?? ($response['error'] ?? null),
        ]);
    }

    respond(200, [
        'ok' => true,
        'order' => $response['payload']['result'] ?? null,
        'message' => 'Orden creada en estado borrador en Printful',
    ]);
}

respond(404, ['ok' => false, 'error' => 'Acción no encontrada']);
