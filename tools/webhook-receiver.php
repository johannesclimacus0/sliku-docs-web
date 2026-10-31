<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';
Dotenv\Dotenv::createImmutable(dirname(__DIR__))->safeLoad();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    http_response_code(405);
    exit;
}
$body = file_get_contents('php://input', false, null, 0, 1024 * 1024 + 1);
if ($body === false || strlen($body) > 1024 * 1024) {
    http_response_code(413);
    exit;
}
$id = $_SERVER['HTTP_X_WEBHOOK_ID'] ?? '';
$timestamp = $_SERVER['HTTP_X_WEBHOOK_TIMESTAMP'] ?? '';
$signature = $_SERVER['HTTP_X_WEBHOOK_SIGNATURE'] ?? '';
$secret = $_ENV['WEBHOOK_USER_SECRET'] ?? '';
if (!new App\Webhooks\WebhookSignature()->verify($id, $body, $timestamp, $signature, $secret)) {
    http_response_code(401);
    exit;
}
try {
    $payload = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
} catch (JsonException) {
    http_response_code(400);
    exit;
}
if (!is_array($payload) || !is_string($payload['event'] ?? null)
    || $payload['event'] !== ($_SERVER['HTTP_X_WEBHOOK_EVENT'] ?? '')) {
    http_response_code(400);
    exit;
}
$status = (int) ($_ENV['WEBHOOK_RECEIVER_STATUS'] ?? 204);
if ($status < 200 || $status > 599) {
    $status = 204;
}
error_log('Webhook signature verified; delivery_id=' . $id . '; status=' . $status);
http_response_code($status);
