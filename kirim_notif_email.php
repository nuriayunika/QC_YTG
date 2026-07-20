<?php
// ============================================================
// kirim_notif_email.php
// Notifikasi email via Brevo (Sendinblue) API
// ============================================================

$_env = parse_ini_file(__DIR__ . '/.env') ?: [];
define('BREVO_API_KEY', $_env['BREVO_API_KEY'] ?? '');
define('BREVO_FROM_EMAIL', 'noreply@yanmar.co.id'); // email pengirim terdaftar di Brevo
define('BREVO_FROM_NAME',  'QC System - Yanmar');

/**
 * Kirim email via Brevo API
 *
 * @param string|array $to      Email tujuan
 * @param string $subject       Subject email
 * @param string $body_html     Body HTML
 * @return bool
 */
function kirimEmail($to, $subject, $body_html) {
    $recipients = [];
    if (is_array($to)) {
        foreach ($to as $email) {
            if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $recipients[] = ['email' => $email];
            }
        }
    } else {
        if (filter_var($to, FILTER_VALIDATE_EMAIL)) {
            $recipients[] = ['email' => $to];
        }
    }

    if (empty($recipients)) return false;

    $payload = json_encode([
        'sender'      => ['name' => BREVO_FROM_NAME, 'email' => BREVO_FROM_EMAIL],
        'to'          => $recipients,
        'subject'     => $subject,
        'htmlContent' => emailTemplate($subject, $body_html),
    ]);

    $ch = curl_init('https://api.brevo.com/v3/smtp/email');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_HTTPHEADER     => [
            'accept: application/json',
            'api-key: ' . BREVO_API_KEY,
            'content-type: application/json',
        ],
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode >= 200 && $httpCode < 300) {
        return true;
    } else {
        error_log("Brevo email gagal: HTTP $httpCode — $response");
        return false;
    }
}

/**
 * Template HTML email (tema maroon, sama seperti dashboard)
 */
function emailTemplate($title, $content) {
    return '<!DOCTYPE html>
<html>
<head><meta charset="UTF-8">
<style>
  body { font-family: Arial, sans-serif; background:#f5f5f5; margin:0; padding:0; }
  .wrap { max-width:560px; margin:30px auto; background:#fff; border-radius:10px; overflow:hidden; box-shadow:0 2px 12px rgba(0,0,0,.08); }
  .header { background:linear-gradient(135deg,#5E1A27,#7B2334); padding:20px 24px; text-align:center; }
  .header h1 { color:#fff; font-size:16px; margin:0; letter-spacing:0.5px; }
  .header p  { color:rgba(255,255,255,0.7); font-size:11px; margin:4px 0 0; }
  .body { padding:24px; color:#333; font-size:13px; line-height:1.7; }
  .info-box { background:#fdf5f5; border-left:4px solid #7B2334; border-radius:4px; padding:12px 16px; margin:14px 0; }
  .info-box table { width:100%; border-collapse:collapse; }
  .info-box td { padding:3px 0; font-size:12px; }
  .info-box td:first-child { color:#7B2334; font-weight:bold; width:130px; }
  .btn { display:inline-block; background:#7B2334; color:#fff !important; text-decoration:none; padding:10px 24px; border-radius:6px; font-size:13px; font-weight:bold; margin:14px 0; }
  .footer { background:#f5f5f5; padding:12px 24px; text-align:center; font-size:10px; color:#aaa; }
  .badge-approved { color:#198754; font-weight:bold; }
  .badge-rejected { color:#dc3545; font-weight:bold; }
  .badge-pending  { color:#f59e0b; font-weight:bold; }
</style>
</head>
<body>
<div class="wrap">
  <div class="header">
    <h1>QC Management System</h1>
    <p>Yanmar &mdash; Final Check Sheet Generator Set (F-FAB-01)</p>
  </div>
  <div class="body">' . $content . '</div>
  <div class="footer">
    Email ini dikirim otomatis oleh sistem. Jangan membalas email ini.<br>
    &copy; ' . date('Y') . ' Yanmar &mdash; QC System
  </div>
</div>
</body></html>';
}

/**
 * Ambil email user berdasarkan role dari DB
 */
function getEmailsByRole($pdo, $role) {
    $stmt = $pdo->prepare(
        "SELECT email FROM users
         WHERE role = :role
           AND email IS NOT NULL
           AND email != ''"
    );
    $stmt->execute([':role' => $role]);
    return array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'email');
}

/**
 * Notifikasi setelah approve checksheet:
 * - Foreman approve    -> email ke Supervisor
 * - Supervisor approve -> email ke Manager
 * - Manager approve    -> tidak ada notifikasi lagi (tahap akhir)
 */
function notifApprovalChecksheet($pdo, $role, $entry, $approved_by) {

    $next_role  = null;
    $next_label = '';

    if ($role === 'foreman') {
        $next_role  = 'supervisor';
        $next_label = 'Supervisor';
    } elseif ($role === 'supervisor') {
        $next_role  = 'manager';
        $next_label = 'Manager';
    } else {
        return 'skipped: role ini tidak butuh notifikasi lanjutan (Manager = tahap akhir)';
    }

    $to = getEmailsByRole($pdo, $next_role);
    if (empty($to)) return "skipped: tidak ada email terdaftar untuk role '$next_role'";

    $model      = $entry['model'] ?? '-';
    $engine_no  = $entry['engine_no'] ?? '-';
    $gen_no     = $entry['generator_no'] ?? '-';
    $tanggal    = $entry['tanggal'] ?? '-';
    $role_label = ucfirst($role);

    $subject = "Checksheet Menunggu Approval Anda - Engine $engine_no";
    $body = "
        <p>Halo <strong>$next_label</strong>,</p>
        <p>Checksheet Final Check Sheet Generator Set untuk Engine <strong>$engine_no</strong>
        telah disetujui oleh <strong>$role_label ($approved_by)</strong>
        dan sekarang memerlukan persetujuan Anda.</p>
        <div class='info-box'>
            <table>
                <tr><td>Model</td><td><strong>$model</strong></td></tr>
                <tr><td>Engine No.</td><td>$engine_no</td></tr>
                <tr><td>Generator No.</td><td>$gen_no</td></tr>
                <tr><td>Tanggal</td><td>$tanggal</td></tr>
                <tr><td>Approved by</td><td>$approved_by ($role_label)</td></tr>
                <tr><td>Status</td><td><span class='badge-pending'>Menunggu Approval $next_label</span></td></tr>
            </table>
        </div>
        <p>Silakan login ke sistem QC untuk melakukan review dan approval.</p>
        <a href='http://localhost/QC_YTG/approval.php' class='btn'>Buka Dashboard Approval</a>
    ";

    $sent = kirimEmail($to, $subject, $body);
    return $sent ? "terkirim ke: " . implode(', ', $to) : "GAGAL kirim ke: " . implode(', ', $to) . ' (cek BREVO_API_KEY / log error)';
}