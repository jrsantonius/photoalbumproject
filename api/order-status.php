<?php
/**
 * GET ?orderId=TIS-xxx — status pesanan.
 */

declare(strict_types=1);
require_once __DIR__ . '/../config.php';
require_method('GET');

$orderId = trim((string) ($_GET['orderId'] ?? ''));

if ($orderId === '' || !preg_match('/^TIS-[0-9]+$/', $orderId)) {
    response_json(['success' => false, 'message' => 'Parameter orderId tidak valid.'], 400);
}

$order = load_order($orderId);
if ($order === null) {
    response_json(['success' => false, 'message' => 'Pesanan tidak ditemukan.'], 404);
}

response_json([
    'success' => true,
    'order'   => [
        'orderId'   => $order['orderId'] ?? $orderId,
        'status'    => $order['status'] ?? 'pending',
        'payment'   => [
            'paymentLink' => $order['payment']['paymentLink'] ?? null,
        ],
        'pricing'   => $order['pricing'] ?? null,
        'createdAt' => $order['createdAt'] ?? null,
    ],
]);
