<?php
header('Content-Type: application/json');
require 'config.php';

try {
    $stmt = $pdo->query("SELECT * FROM checksheet_results ORDER BY created_at DESC");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rows as &$r) {
        $r['items']  = json_decode($r['items'], true);
        $r['photos'] = json_decode($r['photos'], true);
    }

    echo json_encode($rows);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Gagal mengambil data: ' . $e->getMessage()]);
}