<?php
session_start();
require 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Variabel siap pakai di halaman manapun yang require file ini
$current_user = [
    'id'        => $_SESSION['user_id'],
    'nik'       => $_SESSION['nik'],
    'full_name' => $_SESSION['full_name'],
    'role'      => $_SESSION['role'],
];

function role_label($role) {
    $map = [
        'operator'   => 'Operator',
        'foreman'    => 'Foreman',
        'supervisor' => 'Supervisor',
        'manager'    => 'Manager',
    ];
    return $map[$role] ?? $role;
}

// Panggil ini di halaman yang harus dibatasi role tertentu.
// Contoh: require_role(['foreman','supervisor','manager']);
function require_role($allowedRoles) {
    global $current_user;
    if (!in_array($current_user['role'], $allowedRoles)) {
        if ($current_user['role'] === 'operator') {
            header('Location: index.php');
        } else {
            header('Location: approval.php');
        }
        exit();
    }
}