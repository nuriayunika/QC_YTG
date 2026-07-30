<?php
require 'auth.php';
require 'kirim_notif_email.php';
require_role(['foreman', 'supervisor', 'manager']);
header('Content-Type: application/json');

$myRole = $current_user['role'];

$input     = json_decode(file_get_contents('php://input'), true) ?: [];
$entryId   = (int) ($input['entry_id'] ?? 0);
$action    = $input['action'] ?? '';
$reason    = trim($input['reason'] ?? '');

if ($entryId <= 0 || !in_array($action, ['approve', 'reject'])) {
    echo json_encode(['status' => 'error', 'message' => 'Data tidak valid.']);
    exit;
}

if ($action === 'reject' && $reason === '') {
    echo json_encode(['status' => 'error', 'message' => 'Alasan reject wajib diisi.']);
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM checksheet_results WHERE id = :id");
$stmt->execute([':id' => $entryId]);
$entry = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$entry) {
    echo json_encode(['status' => 'error', 'message' => 'Data tidak ditemukan.']);
    exit;
}

// Cek giliran (Foreman -> Supervisor -> Manager)
$canAct = false;
if ($myRole === 'foreman' && $entry['foreman_status'] === 'pending') {
    $canAct = true;
} elseif ($myRole === 'supervisor' && $entry['foreman_status'] === 'approved' && $entry['supervisor_status'] === 'pending') {
    $canAct = true;
} elseif ($myRole === 'manager' && $entry['supervisor_status'] === 'approved' && $entry['manager_status'] === 'pending') {
    $canAct = true;
}

if (!$canAct) {
    echo json_encode(['status' => 'error', 'message' => 'Checksheet ini bukan giliran Anda untuk diproses.']);
    exit;
}

$status = $action === 'approve' ? 'approved' : 'rejected';

// Update kolom status di checksheet_results (cache status terkini)
$sql = "UPDATE checksheet_results
        SET {$myRole}_status = :status,
            {$myRole}_by = :by,
            {$myRole}_at = NOW(),
            {$myRole}_reject_reason = :reason
        WHERE id = :id";
$upd = $pdo->prepare($sql);
$upd->execute([
    ':status' => $status,
    ':by'     => $current_user['full_name'],
    ':reason' => $action === 'reject' ? $reason : null,
    ':id'     => $entryId,
]);

// Catat ke tabel history approvals
$histStmt = $pdo->prepare(
    "INSERT INTO approvals (entry_id, role, status, approved_by, reason) VALUES (:entry_id, :role, :status, :by, :reason)"
);
$histStmt->execute([
    ':entry_id' => $entryId,
    ':role'     => $myRole,
    ':status'   => $status,
    ':by'       => $current_user['full_name'],
    ':reason'   => $action === 'reject' ? $reason : null,
]);

// ---- Notifikasi email ----
if ($status === 'approved' && $myRole === 'foreman') {
    // Foreman approve -> email instan ke Supervisor
    notifApprovalChecksheet($pdo, $myRole, $entry, $current_user['full_name']);
}
// Manager approve sengaja tidak instan (pakai rekap harian daily_digest.php)

if ($status === 'rejected') {
    // Reject -> email ke operator yang isi checksheet ini
    if (!empty($entry['created_by_nik'])) {
        $opStmt = $pdo->prepare("SELECT email, full_name FROM users WHERE nik = :nik LIMIT 1");
        $opStmt->execute([':nik' => $entry['created_by_nik']]);
        $operator = $opStmt->fetch(PDO::FETCH_ASSOC);

        if ($operator && !empty($operator['email'])) {
            $subject = "Checksheet Ditolak - Engine " . $entry['engine_no'];
            $body = "
                <p>Halo <strong>" . htmlspecialchars($operator['full_name']) . "</strong>,</p>
                <p>Checksheet yang Anda isi untuk Engine <strong>" . htmlspecialchars($entry['engine_no']) . "</strong>
                telah <span class='badge-rejected'>DITOLAK</span> oleh <strong>" . ucfirst($myRole) . " (" . htmlspecialchars($current_user['full_name']) . ")</strong>.</p>
                <div class='info-box'>
                    <table>
                        <tr><td>Model</td><td><strong>" . htmlspecialchars($entry['model']) . "</strong></td></tr>
                        <tr><td>Engine No.</td><td>" . htmlspecialchars($entry['engine_no']) . "</td></tr>
                        <tr><td>Generator No.</td><td>" . htmlspecialchars($entry['generator_no']) . "</td></tr>
                        <tr><td>Ditolak oleh</td><td>" . htmlspecialchars($current_user['full_name']) . " (" . ucfirst($myRole) . ")</td></tr>
                        <tr><td>Alasan</td><td>" . htmlspecialchars($reason) . "</td></tr>
                    </table>
                </div>
                <p>Silakan cek kembali dan lakukan perbaikan sesuai catatan di atas.</p>
                <a href='http://localhost/QC_YTG/index.php' class='btn'>Buka Dashboard Checksheet</a>
            ";
            kirimEmail($operator['email'], $subject, $body);
        }
    }
}

echo json_encode([
    'status'    => 'ok',
    'action'    => $status,
    'by'        => $current_user['full_name'],
    'at'        => date('d/m/y H:i'),
    'role'      => $myRole,
    'reason'    => $action === 'reject' ? $reason : null,
]);