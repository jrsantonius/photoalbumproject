<?php
/**
 * POST JSON { sessionId, filename } — hapus satu foto dari uploads/.
 */

declare(strict_types=1);
require_once __DIR__ . '/../config.php';
require_method('POST');

$body      = read_json_body();
$sessionId = sanitize_session_id($body['sessionId'] ?? '');
$filename  = basename((string) ($body['filename'] ?? ''));

if ($sessionId === '' || $filename === '') {
    response_json(['success' => false, 'message' => 'sessionId dan filename wajib diisi.'], 400);
}

$target   = UPLOADS_DIR . '/' . $sessionId . '/' . $filename;
$realBase = realpath(UPLOADS_DIR);
$realFile = realpath($target);

/* Cegah path traversal: file harus benar-benar berada di dalam uploads/ */
if ($realBase === false || $realFile === false || !str_starts_with($realFile, $realBase . DIRECTORY_SEPARATOR)) {
    response_json(['success' => false, 'message' => 'File tidak ditemukan.'], 404);
}

if (!is_file($realFile)) {
    response_json(['success' => false, 'message' => 'File tidak ditemukan.'], 404);
}

if (!@unlink($realFile)) {
    response_json(['success' => false, 'message' => 'Gagal menghapus file.'], 500);
}

response_json(['success' => true, 'message' => 'Foto dihapus.']);
