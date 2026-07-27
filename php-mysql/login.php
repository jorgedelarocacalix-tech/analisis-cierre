<?php
require_once __DIR__ . '/auth.php';
header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true) ?: [];
$pin = (string)($data['pin'] ?? '');

if ($pin !== '' && $pin === pinGuardado()) {
    $_SESSION['ac_auth'] = true;
    echo json_encode(['ok' => true]);
} else {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'PIN incorrecto']);
}
