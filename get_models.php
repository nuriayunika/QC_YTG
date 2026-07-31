<?php
require 'auth.php';
header('Content-Type: application/json');

$rows = $pdo->query("SELECT model_name, engine_model FROM models ORDER BY model_name")->fetchAll(PDO::FETCH_ASSOC);
$map = [];
foreach ($rows as $r) {
    $map[$r['model_name']] = $r['engine_model'];
}
echo json_encode($map);