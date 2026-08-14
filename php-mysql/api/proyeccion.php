<?php
// Snapshot mensual de proyección de cobro por cartera (tabla cierre_proyeccion).
// GET  ?mes_key=2026-07 -> lista de proyecciones de ese mes.
// POST { cartera_id, mes_key, datos, cerrado_por } -> crea o reemplaza (upsert) el snapshot del mes.
require_once __DIR__ . '/../auth.php';
requireAuth();
header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $mesKey = $_GET['mes_key'] ?? '';
    if ($mesKey === '') {
        http_response_code(400);
        echo json_encode(['error' => 'mes_key es obligatorio.']);
        exit;
    }
    $stmt = db()->prepare(
        "SELECT cartera_id, mes_key, datos, cerrado_por, cerrado_at FROM cierre_proyeccion WHERE mes_key = ?"
    );
    $stmt->execute([$mesKey]);
    $rows = $stmt->fetchAll();
    foreach ($rows as &$r) {
        $r['datos'] = $r['datos'] !== null ? json_decode($r['datos'], true) : [];
    }
    echo json_encode($rows);
    exit;
}

if ($method === 'POST') {
    $d = json_decode(file_get_contents('php://input'), true) ?: [];
    if (empty($d['cartera_id']) || empty($d['mes_key']) || !isset($d['datos'])) {
        http_response_code(400);
        echo json_encode(['error' => 'cartera_id, mes_key y datos son obligatorios.']);
        exit;
    }
    $stmt = db()->prepare(
        "INSERT INTO cierre_proyeccion (cartera_id, mes_key, datos, cerrado_por, cerrado_at)
         VALUES (?,?,?,?,NOW())
         ON DUPLICATE KEY UPDATE datos = VALUES(datos), cerrado_por = VALUES(cerrado_por), cerrado_at = NOW()"
    );
    $stmt->execute([$d['cartera_id'], $d['mes_key'], json_encode($d['datos']), $d['cerrado_por'] ?? null]);
    echo json_encode(['ok' => true]);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Método no permitido']);
