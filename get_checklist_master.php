<?php
require 'auth.php';
header('Content-Type: application/json');

$rows = $pdo->query("SELECT * FROM checklist_master ORDER BY category_no, item_order")->fetchAll(PDO::FETCH_ASSOC);

$categories = [];
foreach ($rows as $r) {
    $catNo = (int) $r['category_no'];
    if (!isset($categories[$catNo])) {
        $categories[$catNo] = ['no' => $catNo, 'title' => $r['category_name'], 'items' => []];
    }
    $categories[$catNo]['items'][] = $r['item_label'];
}

echo json_encode(array_values($categories));