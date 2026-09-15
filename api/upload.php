<?php
/**
 * POST multipart/form-data — upload foto ke uploads/{sessionId}/
 * Header opsional: X-Session-Id
 */

declare(strict_types=1);
require_once __DIR__ . '/../config.php';
require_method('POST');

/* Session id dari header, generate baru jika tidak ada / tidak valid */
$headerSession = $_SERVER['HTTP_X_SESSION_ID'] ?? '';
$sessionId     = sanitize_session_id($headerSession);
if ($sessionId === '') {
    $sessionId = generate_uuid();
}

$sessionDir = UPLOADS_DIR . '/' . $sessionId;
if (!is_dir($sessionDir) && !@mkdir($sessionDir, 0755, true) && !is_dir($sessionDir)) {
    response_json(['success' => false, 'message' => 'Gagal membuat folder penyimpanan.'], 500);
}

/* Normalisasi $_FILES: dukung field "photos", "photos[]", atau field apa pun */
$incoming = [];
foreach ($_FILES as $field => $file) {
    if (is_array($file['name'])) {
        $count = count($file['name']);
        for ($i = 0; $i < $count; $i++) {
            $incoming[] = [
                'name'     => (string) $file['name'][$i],
                'type'     => (string) $file['type'][$i],
                'tmp_name' => (string) $file['tmp_name'][$i],
                'error'    => (int) $file['error'][$i],
                'size'     => (int) $file['size'][$i],
            ];
        }
    } else {
        $incoming[] = [
            'name'     => (string) $file['name'],
            'type'     => (string) $file['type'],
            'tmp_name' => (string) $file['tmp_name'],
            'error'    => (int) $file['error'],
            'size'     => (int) $file['size'],
        ];
    }
}

if (count($incoming) === 0) {
    response_json(['success' => false, 'message' => 'Tidak ada file yang dikirim.'], 400);
}

/* Batas total foto per sesi */
$existing = glob($sessionDir . '/*') ?: [];
$existingCount = count(array_filter($existing, 'is_file'));

if ($existingCount + count($incoming) > MAX_PHOTOS) {
    $sisa = max(0, MAX_PHOTOS - $existingCount);
    response_json([
        'success' => false,
        'message' => 'Maksimal ' . MAX_PHOTOS . ' foto per album. Sisa slot: ' . $sisa . '.',
    ], 400);
}

$finfo  = function_exists('finfo_open') ? finfo_open(FILEINFO_MIME_TYPE) : false;
$saved  = [];
$errors = [];

foreach ($incoming as $file) {
    $original = $file['name'];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errors[] = $original . ': gagal diunggah (kode ' . $file['error'] . ').';
        continue;
    }

    if ($file['size'] > MAX_FILE_SIZE) {
        $errors[] = $original . ': ukuran melebihi ' . MAX_FILE_SIZE_MB . 'MB.';
        continue;
    }

    $ext = strtolower((string) pathinfo($original, PATHINFO_EXTENSION));
    if (!in_array($ext, ALLOWED_EXT, true)) {
        $errors[] = $original . ': format harus JPG, PNG, atau WEBP.';
        continue;
    }

    /* Validasi MIME sebenarnya, bukan hanya ekstensi */
    $mime = $finfo !== false ? (string) finfo_file($finfo, $file['tmp_name']) : (string) $file['type'];
    if (!in_array(strtolower($mime), ALLOWED_MIME, true)) {
        $errors[] = $original . ': tipe file tidak dikenali (' . $mime . ').';
        continue;
    }

    if (!is_uploaded_file($file['tmp_name'])) {
        $errors[] = $original . ': sumber file tidak valid.';
        continue;
    }

    if ($ext === 'jpeg') { $ext = 'jpg'; }

    $filename = time() . '-' . bin2hex(random_bytes(4)) . '.' . $ext;
    $target   = $sessionDir . '/' . $filename;

    if (!move_uploaded_file($file['tmp_name'], $target)) {
        $errors[] = $original . ': gagal menyimpan file di server.';
        continue;
    }

    @chmod($target, 0644);

    $saved[] = [
        'filename'     => $filename,
        'originalname' => $original,
        'url'          => 'uploads/' . $sessionId . '/' . $filename,
        'size'         => (int) $file['size'],
    ];
}

if (count($saved) === 0) {
    response_json([
        'success' => false,
        'message' => count($errors) > 0 ? implode(' ', $errors) : 'Tidak ada foto yang berhasil diunggah.',
    ], 400);
}

response_json([
    'success'   => true,
    'sessionId' => $sessionId,
    'files'     => $saved,
    'message'   => count($errors) > 0
        ? count($saved) . ' foto berhasil diunggah. ' . implode(' ', $errors)
        : count($saved) . ' foto berhasil diunggah.',
]);
