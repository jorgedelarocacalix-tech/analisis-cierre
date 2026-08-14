<?php
// Arqueos diarios de caja por sucursal.
// GET  ?desde=YYYY-MM-DD&hasta=YYYY-MM-DD  -> lista de arqueos en el rango (todas las versiones;
//      el front-end hace la deduplicación por sucursal+fecha quedándose con la versión más alta,
//      igual que hacía con Supabase).
// POST { sucursal, fecha, cobrado, gastos, depositado, diferencia, estado, analisis_json, alertas }
//      -> inserta una nueva versión del arqueo (uso manual / import, esta app normalmente solo lee).
require_once __DIR__ . '/../auth.php';
requireAuth();
header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];

function decodeArqueo(array $r): array {
    $r['analisis_json'] = $r['analisis_json'] !== null ? json_decode($r['analisis_json'], true) : null;
    $r['alertas']       = $r['alertas']       !== null ? json_decode($r['alertas'], true)       : null;
    return $r;
}

if ($method === 'GET') {
    $desde = $_GET['desde'] ?? '1970-01-01';
    $hasta = $_GET['hasta'] ?? '2999-12-31';
    $stmt = db()->prepare(
        "SELECT id, sucursal, fecha, cobrado, gastos, depositado, diferencia, estado,
                analisis_json, alertas, version
         FROM arqueos
         WHERE fecha >= ? AND fecha <= ?
         ORDER BY fecha DESC, version DESC"
    );
    $stmt->execute([$desde, $hasta]);
    echo json_encode(array_map('decodeArqueo', $stmt->fetchAll()));
    exit;
}

if ($method === 'POST') {
    $d = json_decode(file_get_contents('php://input'), true) ?: [];
    if (empty($d['sucursal']) || empty($d['fecha'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Sucursal y fecha son obligatorios.']);
        exit;
    }
    $verStmt = db()->prepare("SELECT COALESCE(MAX(version),0) AS v FROM arqueos WHERE sucursal = ? AND fecha = ?");
    $verStmt->execute([$d['sucursal'], $d['fecha']]);
    $nextVersion = (int)$verStmt->fetch()['v'] + 1;

    $stmt = db()->prepare(
        "INSERT INTO arqueos (sucursal, fecha, cobrado, gastos, depositado, diferencia, estado, analisis_json, alertas, version)
         VALUES (?,?,?,?,?,?,?,?,?,?)"
    );
    $stmt->execute([
        $d['sucursal'],
        $d['fecha'],
        $d['cobrado'] ?? 0,
        $d['gastos'] ?? 0,
        $d['depositado'] ?? 0,
        $d['diferencia'] ?? 0,
        $d['estado'] ?? null,
        isset($d['analisis_json']) ? json_encode($d['analisis_json']) : null,
        isset($d['alertas']) ? json_encode($d['alertas']) : null,
        $nextVersion,
    ]);
    echo json_encode(['id' => (int)db()->lastInsertId(), 'version' => $nextVersion]);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Método no permitido']);
