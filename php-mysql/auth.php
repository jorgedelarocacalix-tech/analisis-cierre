<?php
session_start();
require_once __DIR__ . '/config.php';

function pinGuardado(): string {
    $stmt = db()->query("SELECT valor FROM config WHERE clave = 'pin'");
    $row = $stmt->fetch();
    return $row ? $row['valor'] : '1234';
}

function requireAuth(): void {
    if (empty($_SESSION['ac_auth'])) {
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'No autenticado']);
        exit;
    }
}
