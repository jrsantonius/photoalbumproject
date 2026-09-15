<?php
/**
 * The Innovators Studio - Album Foto Cetak Custom
 * Konfigurasi global, konstanta, dan fungsi helper.
 */

declare(strict_types=1);

date_default_timezone_set('Asia/Jakarta');

/* Response API harus JSON murni: error tetap dicatat, tapi tidak dicetak ke output */
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

/* ------------------------------------------------------------------
 * 1. Parser .env manual (baris per baris, split key=value)
 * ------------------------------------------------------------------ */

function load_env(string $path): array
{
    $env = [];
    if (!is_file($path)) {
        return $env;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        return $env;
    }

    foreach ($lines as $line) {
        $line = trim($line);

        // Lewati baris kosong dan komentar
        if ($line === '' || str_starts_with($line, '#') || str_starts_with($line, ';')) {
            continue;
        }

        $pos = strpos($line, '=');
        if ($pos === false) {
            continue;
        }

        $key   = trim(substr($line, 0, $pos));
        $value = trim(substr($line, $pos + 1));

        // Buang tanda kutip pembungkus jika ada
        $len = strlen($value);
        if ($len >= 2) {
            $first = $value[0];
            $last  = $value[$len - 1];
            if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                $value = substr($value, 1, -1);
            }
        }

        if ($key !== '') {
            $env[$key] = $value;
        }
    }

    return $env;
}

$__env = load_env(__DIR__ . '/.env');

function env(string $key, string $default = ''): string
{
    global $__env;
    return isset($__env[$key]) && $__env[$key] !== '' ? $__env[$key] : $default;
}

/* ------------------------------------------------------------------
 * 2. Konstanta aplikasi
 * ------------------------------------------------------------------ */

define('MAYAR_API_KEY',    env('MAYAR_API_KEY', ''));
define('MAYAR_ENV',        env('MAYAR_ENV', 'sandbox'));
define('MAX_PHOTOS',       (int) env('MAX_PHOTOS', '40'));
define('MAX_FILE_SIZE_MB', (int) env('MAX_FILE_SIZE_MB', '10'));
define('MAX_FILE_SIZE',    MAX_FILE_SIZE_MB * 1024 * 1024);
define('UPLOADS_DIR',      __DIR__ . '/uploads');
define('ORDERS_DIR',       __DIR__ . '/orders');

define('MAYAR_ENDPOINT', MAYAR_ENV === 'production'
    ? 'https://api.mayar.id/hl/v2/invoices/create'
    : 'https://api.mayar.io/hl/v2/invoices/create');

define('ALLOWED_EXT',  ['jpg', 'jpeg', 'png', 'webp']);
define('ALLOWED_MIME', ['image/jpeg', 'image/pjpeg', 'image/png', 'image/webp']);

/* ------------------------------------------------------------------
 * 3. Pastikan folder penyimpanan tersedia & bisa ditulis
 * ------------------------------------------------------------------ */

foreach ([UPLOADS_DIR, ORDERS_DIR] as $dir) {
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    if (is_dir($dir) && !is_writable($dir)) {
        @chmod($dir, 0755);
    }
}

/* ------------------------------------------------------------------
 * 4. Tabel harga
 * ------------------------------------------------------------------ */

const PRICE_TABLE = [
    'A5' => ['softcover' => 150000, 'hardcover' => 200000],
    'A4' => ['softcover' => 200000, 'hardcover' => 280000],
    'A3' => ['softcover' => 300000, 'hardcover' => 400000],
];

const PAPER_SURCHARGE = [
    'matte'  => 0,
    'glossy' => 20000,
    'satin'  => 15000,
];

const SHIPPING_TABLE = [
    'jnt'     => ['name' => 'J&T Express',    'cost' => 12000, 'eta' => '2-3 hari'],
    'jne_reg' => ['name' => 'JNE Regular',    'cost' => 15000, 'eta' => '3-5 hari'],
    'sicepat' => ['name' => 'SiCepat',        'cost' => 13000, 'eta' => '2-3 hari'],
    'jne_yes' => ['name' => 'JNE YES',        'cost' => 25000, 'eta' => 'Esok hari tiba'],
    'gosend'  => ['name' => 'GoSend Instant', 'cost' => 20000, 'eta' => 'Hari ini (Jabodetabek)'],
];

const SIZE_LABELS = [
    'A5' => ['label' => 'A5', 'desc' => '14.8 x 21 cm - ringkas & mudah dibawa'],
    'A4' => ['label' => 'A4', 'desc' => '21 x 29.7 cm - ukuran paling populer'],
    'A3' => ['label' => 'A3', 'desc' => '29.7 x 42 cm - tampilan besar & megah'],
];

const COVER_LABELS = [
    'softcover' => ['label' => 'Softcover', 'desc' => 'Sampul lentur, laminasi doff'],
    'hardcover' => ['label' => 'Hardcover', 'desc' => 'Sampul tebal, jahit benang'],
];

const PAPER_LABELS = [
    'matte'  => ['label' => 'Matte',  'desc' => 'Tidak memantul, warna kalem'],
    'glossy' => ['label' => 'Glossy', 'desc' => 'Mengkilap, warna tajam'],
    'satin'  => ['label' => 'Satin',  'desc' => 'Semi-kilap, kesan premium'],
];

/* ------------------------------------------------------------------
 * 5. Fungsi helper
 * ------------------------------------------------------------------ */

/**
 * Kirim response JSON lalu hentikan eksekusi.
 */
function response_json($data, int $code = 200): void
{
    if (!headers_sent()) {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Headers: Content-Type, X-Session-Id');
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    }
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/**
 * Tangani preflight CORS dan batasi HTTP method.
 */
function require_method(string $method): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
        response_json(['success' => true]);
    }
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== $method) {
        response_json(['success' => false, 'message' => 'Method tidak diizinkan. Gunakan ' . $method . '.'], 405);
    }
}

/**
 * Baca body request sebagai array asosiatif.
 */
function read_json_body(): array
{
    $raw = file_get_contents('php://input');
    if ($raw === false || trim($raw) === '') {
        return [];
    }
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

/**
 * UUID v4 sederhana.
 */
function generate_uuid(): string
{
    $bytes = random_bytes(16);
    $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
    $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
}

/**
 * Bersihkan session id agar aman dipakai sebagai nama folder.
 */
function sanitize_session_id(?string $id): string
{
    $id = (string) $id;
    $clean = preg_replace('/[^a-zA-Z0-9\-]/', '', $id) ?? '';
    return (strlen($clean) >= 8 && strlen($clean) <= 64) ? $clean : '';
}

/**
 * Harga satu eksemplar album = harga ukuran+cover + surcharge kertas.
 */
function calc_price(string $size, string $cover, string $paper): int
{
    $size  = strtoupper(trim($size));
    $cover = strtolower(trim($cover));
    $paper = strtolower(trim($paper));

    if (!isset(PRICE_TABLE[$size]) || !isset(PRICE_TABLE[$size][$cover])) {
        return 0;
    }

    $base      = PRICE_TABLE[$size][$cover];
    $surcharge = PAPER_SURCHARGE[$paper] ?? 0;

    return $base + $surcharge;
}

/**
 * Ongkos kirim berdasarkan kode kurir.
 */
function calc_shipping(string $courier): int
{
    $courier = strtolower(trim($courier));
    return SHIPPING_TABLE[$courier]['cost'] ?? 0;
}

/**
 * Hitung rincian harga lengkap.
 */
function calc_total(array $printOptions, array $shipping): array
{
    $size   = (string) ($printOptions['size'] ?? '');
    $cover  = (string) ($printOptions['cover'] ?? '');
    $paper  = (string) ($printOptions['paper'] ?? '');
    $copies = (int) ($printOptions['copies'] ?? 1);

    if ($copies < 1) { $copies = 1; }
    if ($copies > 10) { $copies = 10; }

    $courier      = (string) ($shipping['courier'] ?? '');
    $pricePerCopy = calc_price($size, $cover, $paper);
    $subtotal     = $pricePerCopy * $copies;
    $shippingCost = calc_shipping($courier);

    return [
        'pricePerCopy' => $pricePerCopy,
        'copies'       => $copies,
        'subtotal'     => $subtotal,
        'shippingCost' => $shippingCost,
        'total'        => $subtotal + $shippingCost,
        'currency'     => 'IDR',
    ];
}

/**
 * Format angka ke Rupiah.
 */
function format_rupiah(int $amount): string
{
    return 'Rp ' . number_format($amount, 0, ',', '.');
}

/**
 * Kirim payload invoice ke Mayar via cURL.
 * Return: ['ok' => bool, 'status' => int, 'data' => array|null, 'error' => string|null]
 */
function send_to_mayar(array $payload): array
{
    if (MAYAR_API_KEY === '' || MAYAR_API_KEY === 'your_mayar_api_key_here') {
        return ['ok' => false, 'status' => 0, 'data' => null, 'error' => 'MAYAR_API_KEY belum diisi di file .env'];
    }

    if (!function_exists('curl_init')) {
        return ['ok' => false, 'status' => 0, 'data' => null, 'error' => 'Ekstensi cURL tidak tersedia di server'];
    }

    $ch = curl_init(MAYAR_ENDPOINT);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . MAYAR_API_KEY,
            'Content-Type: application/json',
            'Accept: application/json',
        ],
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    ]);

    $body   = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err    = curl_error($ch);
    curl_close($ch);

    if ($body === false || $err !== '') {
        return ['ok' => false, 'status' => $status, 'data' => null, 'error' => $err !== '' ? $err : 'Gagal menghubungi Mayar'];
    }

    $decoded = json_decode((string) $body, true);
    if (!is_array($decoded)) {
        return ['ok' => false, 'status' => $status, 'data' => null, 'error' => 'Response Mayar bukan JSON valid'];
    }

    if ($status < 200 || $status >= 300) {
        $msg = $decoded['messages'] ?? $decoded['message'] ?? 'Mayar menolak permintaan (HTTP ' . $status . ')';
        return ['ok' => false, 'status' => $status, 'data' => $decoded, 'error' => is_string($msg) ? $msg : json_encode($msg)];
    }

    return ['ok' => true, 'status' => $status, 'data' => $decoded, 'error' => null];
}

/**
 * Path file order berdasarkan orderId.
 */
function order_path(string $orderId): string
{
    $clean = preg_replace('/[^A-Za-z0-9\-]/', '', $orderId) ?? '';
    return ORDERS_DIR . '/' . $clean . '.json';
}

/**
 * Tulis order ke file JSON (atomic-ish, dengan lock).
 */
function save_order(string $orderId, array $order): bool
{
    $json = json_encode($order, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    return file_put_contents(order_path($orderId), $json, LOCK_EX) !== false;
}

/**
 * Baca order dari file JSON. Null jika tidak ada.
 */
function load_order(string $orderId): ?array
{
    $path = order_path($orderId);
    if (!is_file($path)) {
        return null;
    }
    $raw = file_get_contents($path);
    if ($raw === false) {
        return null;
    }
    $data = json_decode($raw, true);
    return is_array($data) ? $data : null;
}
