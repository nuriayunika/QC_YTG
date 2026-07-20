<?php
header('Content-Type: application/json');
require 'config.php';

$data = json_decode(file_get_contents('php://input'), true);

if (!$data) {
    http_response_code(400);
    echo json_encode(['error' => 'Data tidak valid atau kosong.']);
    exit;
}

// Validasi minimal di server (jangan cuma percaya validasi di browser)
$required = ['tanggal','model','voltage','frequency','destination','engine_no','generator_no','frame_no','items','photos'];
foreach ($required as $field) {
    if (!isset($data[$field]) || $data[$field] === '') {
        http_response_code(400);
        echo json_encode(['error' => "Field '$field' wajib diisi."]);
        exit;
    }
}

// Hitung status: lengkap kalau semua item checklist terisi (bukan kosong)
$items = $data['items'];
$totalItems = count($items);
$filledItems = 0;
foreach ($items as $v) {
    if (!empty($v)) $filledItems++;
}
$status = ($filledItems === $totalItems && $totalItems > 0) ? 'lengkap' : 'belum';

$sql = "INSERT INTO checksheet_results
    (tanggal, model, voltage, frequency, destination, engine_model, engine_no, generator_no, frame_no, remarks, items, photos, status)
    VALUES
    (:tanggal, :model, :voltage, :frequency, :destination, :engine_model, :engine_no, :generator_no, :frame_no, :remarks, :items, :photos, :status)";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':tanggal'      => $data['tanggal'],
        ':model'        => $data['model'],
        ':voltage'      => $data['voltage'],
        ':frequency'    => $data['frequency'],
        ':destination'  => $data['destination'],
        ':engine_model' => $data['engine_model'] ?? null,
        ':engine_no'    => $data['engine_no'],
        ':generator_no' => $data['generator_no'],
        ':frame_no'     => $data['frame_no'],
        ':remarks'      => $data['remarks'] ?? '',
        ':items'        => json_encode($items, JSON_UNESCAPED_UNICODE),
        ':photos'       => json_encode($data['photos'], JSON_UNESCAPED_UNICODE),
        ':status'       => $status,
    ]);

    echo json_encode(['success' => true, 'id' => $pdo->lastInsertId(), 'status' => $status]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Gagal menyimpan: ' . $e->getMessage()]);
}