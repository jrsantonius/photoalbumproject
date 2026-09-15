<?php
/**
 * GET — tabel harga lengkap untuk frontend.
 */

declare(strict_types=1);
require_once __DIR__ . '/../config.php';
require_method('GET');

$sizes = [];
foreach (PRICE_TABLE as $code => $covers) {
    $sizes[] = [
        'code'        => $code,
        'label'       => SIZE_LABELS[$code]['label'] ?? $code,
        'description' => SIZE_LABELS[$code]['desc'] ?? '',
        'prices'      => $covers,
    ];
}

$covers = [];
foreach (COVER_LABELS as $code => $meta) {
    $covers[] = [
        'code'        => $code,
        'label'       => $meta['label'],
        'description' => $meta['desc'],
    ];
}

$papers = [];
foreach (PAPER_LABELS as $code => $meta) {
    $papers[] = [
        'code'        => $code,
        'label'       => $meta['label'],
        'description' => $meta['desc'],
        'surcharge'   => PAPER_SURCHARGE[$code] ?? 0,
    ];
}

$couriers = [];
foreach (SHIPPING_TABLE as $code => $meta) {
    $couriers[] = [
        'code'  => $code,
        'name'  => $meta['name'],
        'cost'  => $meta['cost'],
        'eta'   => $meta['eta'],
    ];
}

response_json([
    'success'  => true,
    'currency' => 'IDR',
    'limits'   => [
        'maxPhotos'     => MAX_PHOTOS,
        'maxFileSizeMB' => MAX_FILE_SIZE_MB,
        'allowedFormats' => ['jpg', 'png', 'webp'],
        'maxCopies'     => 10,
    ],
    'sizes'     => $sizes,
    'covers'    => $covers,
    'papers'    => $papers,
    'couriers'  => $couriers,
    'priceTable' => PRICE_TABLE,
    'paperSurcharge' => PAPER_SURCHARGE,
]);
