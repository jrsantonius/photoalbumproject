<?php
/**
 * POST JSON dari Mayar — update status pembayaran order.
 */

declare(strict_types=1);
require_once __DIR__ . '/../config.php';
require_method('POST');

$body = read_json_body();

/* Mayar mengirim payload di root atau di dalam "data" */
$payload = is_array($body['data'] ?? null) ? $body['data'] : $body;

$status    = strtolower(trim((string) ($payload['status'] ?? $body['status'] ?? '')));
$extraData = $payload['extraData'] ?? $body['extraData'] ?? [];

if (is_string($extraData)) {
    $decoded   = json_decode($extraData, true);
    $extraData = is_array($decoded) ? $decoded : [];
}

$orderId = trim((string) ($extraData['orderId'] ?? ''));

if ($orderId === '' || !preg_match('/^TIS-[0-9]+$/', $orderId)) {
    response_json(['success' => true, 'message' => 'Payload diterima, orderId tidak dikenali.']);
}

$order = load_order($orderId);
if ($order === null) {
    response_json(['success' => true, 'message' => 'Payload diterima, order tidak ditemukan.']);
}

/* Petakan status Mayar ke status internal */
$newStatus = $order['status'] ?? 'pending';
if (in_array($status, ['paid', 'settlement', 'success'], true)) {
    $newStatus = 'paid';
} elseif ($status === 'expired') {
    $newStatus = 'expired';
} elseif (in_array($status, ['cancelled', 'canceled'], true)) {
    $newStatus = 'cancelled';
}

$order['status']    = $newStatus;
$order['updatedAt'] = date('c');

if (!isset($order['webhookLogs']) || !is_array($order['webhookLogs'])) {
    $order['webhookLogs'] = [];
}

$order['webhookLogs'][] = [
    'receivedAt'  => date('c'),
    'mayarStatus' => $status,
    'mappedTo'    => $newStatus,
    'payload'     => $payload,
];

save_order($orderId, $order);

response_json(['success' => true]);
