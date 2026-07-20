<?php
// ============================================================
// SEED_USERS.PHP - jalankan SEKALI lewat browser untuk bikin
// 4 akun default (satu per role), lalu HAPUS file ini.
// ============================================================
require 'config.php';

// Ganti NIK di bawah ini sesuai NIK asli karyawan kamu
$defaultUsers = [
    ['nik' => '10001', 'password' => 'operator123',   'full_name' => 'Operator',   'role' => 'operator'],
    ['nik' => '10002', 'password' => 'foreman123',    'full_name' => 'Foreman',    'role' => 'foreman'],
    ['nik' => '10003', 'password' => 'supervisor123', 'full_name' => 'Supervisor', 'role' => 'supervisor'],
    ['nik' => '10004', 'password' => 'manager123',    'full_name' => 'Manager',    'role' => 'manager'],
];

echo "<h3>Membuat akun default...</h3><ul>";

foreach ($defaultUsers as $u) {
    $check = $pdo->prepare("SELECT id FROM users WHERE nik = :nik");
    $check->execute([':nik' => $u['nik']]);
    if ($check->fetch()) {
        echo "<li>NIK {$u['nik']} — sudah ada, dilewati.</li>";
        continue;
    }

    $stmt = $pdo->prepare("INSERT INTO users (nik, password, full_name, role) VALUES (:nik, :password, :full_name, :role)");
    $stmt->execute([
        ':nik'       => $u['nik'],
        ':password'  => $u['password'],
        ':full_name' => $u['full_name'],
        ':role'      => $u['role'],
    ]);
    echo "<li>NIK {$u['nik']} / password: {$u['password']} (role: {$u['role']}) — berhasil dibuat.</li>";
}

echo "</ul><p><strong>PENTING: hapus file seed_users.php ini sekarang juga setelah selesai, jangan biarkan tersisa di server.</strong></p>";