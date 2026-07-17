<?php
require_once __DIR__ . '/phpqrcode/qrlib.php';
$text = isset($_GET['text']) ? trim($_GET['text']) : '';
if (!$text) {
    http_response_code(400);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Missing QR text.';
    exit;
}
header('Content-Type: image/png');
if (isset($_GET['download']) && $_GET['download'] === '1') {
    header('Content-Disposition: attachment; filename="qr-code.png"');
}
QRcode::png($text, false, 'H', 8, 2);
exit;
