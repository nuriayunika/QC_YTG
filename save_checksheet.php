<?php
require 'auth.php';
require 'image_helper.php';
header('Content-Type: application/json');

// ---- Ambil field teks biasa ----
$tanggal      = $_POST['tanggal'] ?? '';
$model        = $_POST['model'] ?? '';
$voltage      = $_POST['voltage'] ?? '';
$frequency    = $_POST['frequency'] ?? '';
$destination  = $_POST['destination'] ?? '';
$engine_model = $_POST['engine_model'] ?? '';
$engine_no    = $_POST['engine_no'] ?? '';
$generator_no = $_POST['generator_no'] ?? '';
$frame_no     = $_POST['frame_no'] ?? '';
$remarks      = $_POST['remarks'] ?? '';
$itemsJson    = $_POST['items'] ?? '';

$items = json_decode($itemsJson, true);
if (!is_array($items)) $items = [];

$required = [$tanggal, $model, $voltage, $frequency, $destination, $engine_no, $generator_no, $frame_no];
foreach ($required as $val) {
    if ($val === '') {
        http_response_code(400);
        echo json_encode(['error' => 'Ada field wajib yang kosong.']);
        exit;
    }
}

// ---- Proses upload 5 foto ----
$uploadDir = __DIR__ . '/uploads/checksheet/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

$photoPaths = [];
for ($i = 1; $i <= 5; $i++) {
    $key = 'photo_' . $i;
    if (isset($_FILES[$key]) && $_FILES[$key]['error'] === UPLOAD_ERR_OK) {
        $ext = 'jpg';
        $filename = 'chk_' . date('Ymd_His') . '_' . $i . '_' . substr(md5(uniqid('', true)), 0, 8) . '.' . $ext;
        $destPath = $uploadDir . $filename;

        $saved = resizeAndSaveImage($_FILES[$key]['tmp_name'], $destPath, 1200, 75);
        if ($saved) {
            // simpan path RELATIF (dari folder project), biar gampang dipakai di <img> dan mPDF
            $photoPaths[] = 'uploads/checksheet/' . basename($saved);
        } else {
            $photoPaths[] = null;
        }
    } else {
        $photoPaths[] = null;
    }
}

// Hitung status kelengkapan
$totalItems = count($items);
$filledItems = 0;
foreach ($items as $v) {
    if (!empty($v)) $filledItems++;
}
$status = ($filledItems === $totalItems && $totalItems > 0) ? 'lengkap' : 'belum';

$sql = "INSERT INTO checksheet_results
    (tanggal, model, voltage, frequency, destination, engine_model, engine_no, generator_no, frame_no, remarks, items, photos, status, created_by_nik, created_by_name)
    VALUES
    (:tanggal, :model, :voltage, :frequency, :destination, :engine_model, :engine_no, :generator_no, :frame_no, :remarks, :items, :photos, :status, :created_by_nik, :created_by_name)";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':tanggal'         => $tanggal,
        ':model'           => $model,
        ':voltage'         => $voltage,
        ':frequency'       => $frequency,
        ':destination'     => $destination,
        ':engine_model'    => $engine_model,
        ':engine_no'       => $engine_no,
        ':generator_no'    => $generator_no,
        ':frame_no'        => $frame_no,
        ':remarks'         => $remarks,
        ':items'           => json_encode($items, JSON_UNESCAPED_UNICODE),
        ':photos'          => json_encode($photoPaths, JSON_UNESCAPED_UNICODE),
        ':status'          => $status,
        ':created_by_nik'  => $current_user['nik'],
        ':created_by_name' => $current_user['full_name'],
    ]);

    echo json_encode(['success' => true, 'id' => $pdo->lastInsertId(), 'status' => $status]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Gagal menyimpan: ' . $e->getMessage()]);
}