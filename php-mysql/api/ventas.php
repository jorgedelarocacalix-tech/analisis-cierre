<?php
// Archivos Excel de ventas por sucursal (tabla cierre_ventas), subidos desde el tab Ventas/Vendedores.
// GET  ?mes_key=2026-06 -> lista {sucursal, headers, rows, updated_at} de ese mes.
// POST { mes_key, sucursal, headers, rows } -> crea o reemplaza (upsert) el archivo de esa sucursal/mes.
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
        "SELECT sucursal, headers, filas, updated_at FROM cierre_ventas WHERE mes_key = ?"
    );
    $stmt->execute([$mesKey]);
    $rows = $stmt->fetchAll();
    $out = [];
    foreach ($rows as $r) {
        $out[] = [
            'sucursal'   => $r['sucursal'],
            'headers'    => $r['headers'] !== null ? json_decode($r['headers'], true) : [],
            'rows'       => $r['filas']   !== null ? json_decode($r['filas'], true)   : [],
            'updated_at' => $r['updated_at'],
        ];
    }
    echo json_encode($out);
    exit;
}

if ($method === 'POST') {
    $d = json_decode(file_get_contents('php://input'), true) ?: [];
    if (empty($d['mes_key']) || empty($d['sucursal'])) {
        http_response_code(400);
        echo json_encode(['error' => 'mes_key y sucursal son obligatorios.']);
        exit;
    }
    $stmt = db()->prepare(
        "INSERT INTO cierre_ventas (mes_key, sucursal, headers, filas)
         VALUES (?,?,?,?)
         ON DUPLICATE KEY UPDATE headers = VALUES(headers), filas = VALUES(filas), updated_at = NOW()"
    );
    $stmt->execute([
        $d['mes_key'],
        $d['sucursal'],
        json_encode($d['headers'] ?? []),
        json_encode($d['rows'] ?? []),
    ]);
    echo json_encode(['ok' => true]);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Método no permitido']);
