<?php
require_once __DIR__ . '/auth.php';
requireAuth();
header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true) ?: [];
$pin = (string)($data['pin'] ?? '');

if (!preg_match('/^\d{4}$/', $pin)) {
    http_response_code(400);
    echo json_encode(['error' => 'El PIN debe tener 4 dígitos.']);
    exit;
}

$stmt = db()->prepare("INSERT INTO config (clave, valor) VALUES ('pin', ?) ON DUPLICATE KEY UPDATE valor = ?");
$stmt->execute([$pin, $pin]);
echo json_encode(['ok' => true]);
