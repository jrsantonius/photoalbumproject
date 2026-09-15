<?php
/**
 * POST JSON — buat pesanan baru, simpan ke orders/{orderId}.json,
 * lalu terbitkan invoice di Mayar.
 */

declare(strict_types=1);
require_once __DIR__ . '/../config.php';
require_method('POST');

$body = read_json_body();

$customer     = is_array($body['customer'] ?? null) ? $body['customer'] : [];
$photos       = is_array($body['photos'] ?? null) ? $body['photos'] : [];
$albumType    = trim((string) ($body['albumType'] ?? ''));
$template     = is_array($body['template'] ?? null) ? $body['template'] : [];
$printOptions = is_array($body['printOptions'] ?? null) ? $body['printOptions'] : [];
$shipping     = is_array($body['shipping'] ?? null) ? $body['shipping'] : [];
$sessionId    = sanitize_session_id($body['sessionId'] ?? '');

/* ---------------------- Validasi field wajib ---------------------- */

$errors = [];

foreach (['name' => 'Nama lengkap', 'email' => 'Email', 'phone' => 'Nomor WhatsApp'] as $key => $label) {
    if (trim((string) ($customer[$key] ?? '')) === '') {
        $errors[] = $label . ' wajib diisi.';
    }
}

if (isset($customer['email']) && trim((string) $customer['email']) !== ''
    && !filter_var(trim((string) $customer['email']), FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'Format email tidak valid.';
}

if (count($photos) === 0) {
    $errors[] = 'Foto album belum dipilih.';
}
if (count($photos) > MAX_PHOTOS) {
    $errors[] = 'Jumlah foto melebihi batas ' . MAX_PHOTOS . '.';
}
if ($albumType === '') {
    $errors[] = 'Jenis album wajib dipilih.';
}
if (trim((string) ($template['id'] ?? '')) === '') {
    $errors[] = 'Template desain wajib dipilih.';
}
if ($sessionId === '') {
    $errors[] = 'Session tidak valid, silakan unggah ulang foto.';
}

$size   = strtoupper(trim((string) ($printOptions['size'] ?? '')));
$cover  = strtolower(trim((string) ($printOptions['cover'] ?? '')));
$paper  = strtolower(trim((string) ($printOptions['paper'] ?? '')));
$copies = (int) ($printOptions['copies'] ?? 0);

if (!isset(PRICE_TABLE[$size]))                   { $errors[] = 'Ukuran album tidak valid.'; }
if (!isset(COVER_LABELS[$cover]))                 { $errors[] = 'Jenis cover tidak valid.'; }
if (!isset(PAPER_SURCHARGE[$paper]))              { $errors[] = 'Jenis kertas tidak valid.'; }
if ($copies < 1 || $copies > 10)                  { $errors[] = 'Jumlah eksemplar harus 1-10.'; }

foreach (['recipient' => 'Nama penerima', 'address' => 'Alamat lengkap', 'city' => 'Kota',
          'province' => 'Provinsi', 'postalCode' => 'Kode pos'] as $key => $label) {
    if (trim((string) ($shipping[$key] ?? '')) === '') {
        $errors[] = $label . ' wajib diisi.';
    }
}

$courier = strtolower(trim((string) ($shipping['courier'] ?? '')));
if (!isset(SHIPPING_TABLE[$courier])) {
    $errors[] = 'Kurir pengiriman wajib dipilih.';
}

if (count($errors) > 0) {
    response_json(['success' => false, 'message' => implode(' ', $errors), 'errors' => $errors], 400);
}

/* ---------------------- Hitung harga di server -------------------- */

$printOptions = ['size' => $size, 'cover' => $cover, 'paper' => $paper, 'copies' => $copies];
$shipping['courier']     = $courier;
$shipping['courierName'] = SHIPPING_TABLE[$courier]['name'];
$shipping['eta']         = SHIPPING_TABLE[$courier]['eta'];

$pricing = calc_total($printOptions, $shipping);
$shipping['cost'] = $pricing['shippingCost'];

/* ---------------------- Simpan order ------------------------------ */

$orderId   = 'TIS-' . time();
$createdAt = date('c');

$itemDescription = sprintf(
    'Album %s %s %s',
    $size,
    COVER_LABELS[$cover]['label'],
    PAPER_LABELS[$paper]['label']
);

$order = [
    'orderId'      => $orderId,
    'status'       => 'pending',
    'createdAt'    => $createdAt,
    'updatedAt'    => $createdAt,
    'sessionId'    => $sessionId,
    'customer'     => [
        'name'      => trim((string) $customer['name']),
        'email'     => trim((string) $customer['email']),
        'phone'     => trim((string) $customer['phone']),
        'instagram' => trim((string) ($customer['instagram'] ?? '')),
    ],
    'albumType'    => $albumType,
    'template'     => $template,
    'photos'       => $photos,
    'photoCount'   => count($photos),
    'printOptions' => $printOptions,
    'shipping'     => $shipping,
    'pricing'      => $pricing,
    'payment'      => [
        'provider'    => 'mayar',
        'environment' => MAYAR_ENV,
        'paymentLink' => null,
        'invoiceId'   => null,
    ],
    'webhookLogs'  => [],
];

if (!save_order($orderId, $order)) {
    response_json(['success' => false, 'message' => 'Gagal menyimpan pesanan di server.'], 500);
}

/* ---------------------- Terbitkan invoice Mayar ------------------- */

$payload = [
    'name'        => $order['customer']['name'],
    'email'       => $order['customer']['email'],
    'mobile'      => $order['customer']['phone'],
    'description' => 'Album Foto ' . $albumType . ' - Order #' . $orderId,
    'expiredAt'   => gmdate('c', time() + 86400),
    'items'       => [
        [
            'quantity'    => $copies,
            'rate'        => $pricing['pricePerCopy'],
            'description' => $itemDescription,
        ],
        [
            'quantity'    => 1,
            'rate'        => $pricing['shippingCost'],
            'description' => 'Pengiriman via ' . $shipping['courierName'],
        ],
    ],
    'extraData'   => ['orderId' => $orderId],
];

$mayar       = send_to_mayar($payload);
$paymentLink = null;
$message     = 'Pesanan berhasil dibuat.';

if ($mayar['ok']) {
    $data        = $mayar['data']['data'] ?? [];
    $paymentLink = $data['link'] ?? null;
    $invoiceId   = $data['id'] ?? null;

    $order['payment']['paymentLink'] = $paymentLink;
    $order['payment']['invoiceId']   = $invoiceId;
    $order['payment']['raw']         = $data;
    $order['updatedAt']              = date('c');
    save_order($orderId, $order);

    $message = $paymentLink
        ? 'Pesanan berhasil dibuat. Silakan lanjutkan pembayaran.'
        : 'Pesanan berhasil dibuat, namun link pembayaran belum tersedia.';
} else {
    /* Mayar gagal — order tetap tersimpan agar bisa ditindaklanjuti manual */
    $order['payment']['error'] = $mayar['error'];
    $order['updatedAt']        = date('c');
    save_order($orderId, $order);

    $message = 'Pesanan berhasil dicatat, namun link pembayaran belum bisa dibuat. Tim kami akan menghubungi Anda via WhatsApp.';
}

response_json([
    'success'     => true,
    'orderId'     => $orderId,
    'paymentLink' => $paymentLink,
    'pricing'     => $pricing,
    'message'     => $message,
]);
