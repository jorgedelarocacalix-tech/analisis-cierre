<?php
// Carteras de cobro con sus clientes.
// GET  -> lista todas las carteras {id, clientes}
// POST { id, clientes } -> crea o reemplaza (upsert) una cartera completa.
require_once __DIR__ . '/../auth.php';
requireAuth();
header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $stmt = db()->query("SELECT id, clientes FROM carteras ORDER BY id");
    $rows = $stmt->fetchAll();
    foreach ($rows as &$r) {
        $r['clientes'] = $r['clientes'] !== null ? json_decode($r['clientes'], true) : [];
    }
    echo json_encode($rows);
    exit;
}

if ($method === 'POST') {
    $d = json_decode(file_get_contents('php://input'), true) ?: [];
    if (empty($d['id']) || !isset($d['clientes'])) {
        http_response_code(400);
        echo json_encode(['error' => 'id y clientes son obligatorios.']);
        exit;
    }
    $stmt = db()->prepare(
        "INSERT INTO carteras (id, clientes) VALUES (?, ?)
         ON DUPLICATE KEY UPDATE clientes = VALUES(clientes), updated_at = NOW()"
    );
    $stmt->execute([$d['id'], json_encode($d['clientes'])]);
    echo json_encode(['ok' => true]);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Método no permitido']);
