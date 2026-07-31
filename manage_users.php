<?php
require 'auth.php';
require_role(['admin']);

$msg = '';
$msgType = '';

// ============================================================
// Proses aksi: tambah, edit, toggle aktif
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $nik      = trim($_POST['nik'] ?? '');
        $fullName = trim($_POST['full_name'] ?? '');
        $role     = $_POST['role'] ?? '';
        $email    = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if ($nik === '' || $fullName === '' || $role === '' || $password === '') {
            $msg = 'NIK, Nama, Role, dan Password wajib diisi.';
            $msgType = 'err';
        } elseif (!in_array($role, ['operator', 'foreman', 'supervisor', 'manager', 'admin'])) {
            $msg = 'Role tidak valid.';
            $msgType = 'err';
        } else {
            $check = $pdo->prepare("SELECT id FROM users WHERE nik = :nik");
            $check->execute([':nik' => $nik]);
            if ($check->fetch()) {
                $msg = 'NIK sudah terdaftar.';
                $msgType = 'err';
            } else {
                $stmt = $pdo->prepare("INSERT INTO users (nik, full_name, role, email, password, is_active) VALUES (:nik, :full_name, :role, :email, :password, 1)");
                $stmt->execute([
                    ':nik' => $nik, ':full_name' => $fullName, ':role' => $role,
                    ':email' => $email ?: null, ':password' => $password,
                ]);
                $msg = "User $fullName berhasil ditambahkan.";
                $msgType = 'ok';
            }
        }
    }

    if ($action === 'edit') {
        $id       = (int) ($_POST['id'] ?? 0);
        $fullName = trim($_POST['full_name'] ?? '');
        $role     = $_POST['role'] ?? '';
        $email    = trim($_POST['email'] ?? '');
        $newPass  = $_POST['password'] ?? '';

        if ($id <= 0 || $fullName === '' || $role === '') {
            $msg = 'Data tidak lengkap.';
            $msgType = 'err';
        } else {
            if ($newPass !== '') {
                $stmt = $pdo->prepare("UPDATE users SET full_name=:full_name, role=:role, email=:email, password=:password WHERE id=:id");
                $stmt->execute([':full_name'=>$fullName, ':role'=>$role, ':email'=>$email ?: null, ':password'=>$newPass, ':id'=>$id]);
            } else {
                $stmt = $pdo->prepare("UPDATE users SET full_name=:full_name, role=:role, email=:email WHERE id=:id");
                $stmt->execute([':full_name'=>$fullName, ':role'=>$role, ':email'=>$email ?: null, ':id'=>$id]);
            }
            $msg = 'User berhasil diperbarui.';
            $msgType = 'ok';
        }
    }

    if ($action === 'toggle_active') {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id === (int) $current_user['id']) {
            $msg = 'Tidak bisa menonaktifkan akun Anda sendiri.';
            $msgType = 'err';
        } else {
            $stmt = $pdo->prepare("UPDATE users SET is_active = 1 - is_active WHERE id = :id");
            $stmt->execute([':id' => $id]);
            $msg = 'Status user berhasil diubah.';
            $msgType = 'ok';
        }
    }
}

$users = $pdo->query("SELECT * FROM users ORDER BY role, full_name")->fetchAll(PDO::FETCH_ASSOC);
function e($s) { return htmlspecialchars($s ?? '', ENT_QUOTES); }
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Kelola User</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@500;600&display=swap" rel="stylesheet">
<style>
  :root{
    --maroon:#7B2334; --maroon-dark:#5E1A27; --maroon-tint:#F7E9EB;
    --bg:#EEF0F3; --panel:#FFFFFF; --border:#E1E4E9; --border-soft:#EEF0F3;
    --text:#23262B; --text-muted:#6B7280; --text-dim:#9CA3AF;
    --green:#1F9D55; --green-bg:#E7F7EE; --red:#C0362C; --red-bg:#FBEAEA;
  }
  *{box-sizing:border-box;}
  body{margin:0;background:var(--bg);color:var(--text);font-family:'Inter',sans-serif;}
  .topbar{background:var(--maroon);display:flex;align-items:center;justify-content:space-between;padding:10px 20px;gap:16px;flex-wrap:wrap;}
  .topbar-left{display:flex;align-items:center;gap:12px;}
  .btn-back{background:rgba(255,255,255,0.12);color:#fff;text-decoration:none;padding:8px 14px;border-radius:6px;font-size:12.5px;font-weight:600;}
  .topbar-title{color:#fff;font-weight:700;font-size:15px;}
  .topbar-right{color:#F1D9DD;font-size:12.5px;}
  .topbar-right a{color:#F1D9DD;text-decoration:none;background:rgba(255,255,255,0.1);padding:7px 14px;border-radius:6px;font-weight:600;margin-left:8px;}

  .content-pad{padding:20px;max-width:1100px;margin:0 auto;}
  .form-msg{padding:10px 14px;border-radius:6px;font-size:13px;margin-bottom:14px;}
  .form-msg.ok{background:var(--green-bg);color:var(--green);}
  .form-msg.err{background:var(--red-bg);color:var(--red);}

  .card{background:var(--panel);border:1px solid var(--border);border-radius:6px;margin-bottom:18px;overflow:hidden;}
  .card-head{background:var(--maroon);color:#fff;padding:12px 18px;font-weight:700;font-size:14px;}
  .card-body{padding:18px;}

  .field-row{display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:14px;margin-bottom:14px;}
  .field label{display:block;font-size:12px;color:var(--text-muted);margin-bottom:5px;font-weight:500;}
  .field input, .field select{width:100%;border:1px solid var(--border);border-radius:5px;padding:9px 11px;font-size:14px;font-family:'Inter',sans-serif;}
  .field input:focus, .field select:focus{outline:none;border-color:var(--maroon);}

  .btn-primary{background:var(--maroon);color:#fff;border:none;border-radius:6px;padding:10px 20px;font-weight:600;font-size:13.5px;cursor:pointer;}
  .btn-primary:hover{background:var(--maroon-dark);}

  table{width:100%;border-collapse:collapse;font-size:13px;}
  th{text-align:left;background:#F7F8FA;color:var(--text-muted);font-weight:600;font-size:11.5px;text-transform:uppercase;letter-spacing:0.03em;padding:10px 12px;border-bottom:1px solid var(--border);}
  td{padding:10px 12px;border-bottom:1px solid var(--border-soft);vertical-align:middle;}
  tr:hover td{background:#FAFBFC;}
  .role-pill{display:inline-block;font-size:11px;font-weight:600;padding:3px 9px;border-radius:20px;background:var(--maroon-tint);color:var(--maroon-dark);text-transform:capitalize;}
  .status-active{color:var(--green);font-weight:600;font-size:12px;}
  .status-inactive{color:var(--text-dim);font-weight:600;font-size:12px;}
  .btn-mini{background:#fff;border:1px solid var(--border);color:var(--text);border-radius:5px;padding:5px 11px;font-size:12px;cursor:pointer;font-weight:600;}
  .btn-mini:hover{border-color:var(--maroon);color:var(--maroon);}
  .btn-mini.danger{color:var(--red);border-color:var(--red);}

  .modal-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,0.4);align-items:center;justify-content:center;z-index:100;}
  .modal-overlay.open{display:flex;}
  .modal-box{background:#fff;border-radius:8px;width:100%;max-width:420px;overflow:hidden;}
  .modal-head{background:var(--maroon);color:#fff;padding:14px 18px;font-weight:700;font-size:14px;display:flex;justify-content:space-between;align-items:center;}
  .modal-close{background:none;border:none;color:#fff;font-size:18px;cursor:pointer;}
  .modal-body{padding:18px;}
</style>
</head>
<body>

<div class="topbar">
  <div class="topbar-left">
    
    <div class="topbar-title">&#128101; Kelola User</div>
  </div>
  <div class="topbar-right">
    <?php echo e($current_user['full_name']); ?> &middot; Admin
    <a href="manage_master.php">Model &amp; Checklist</a>
    <a href="logout.php">Logout</a>
  </div>
</div>

<div class="content-pad">
  <?php if ($msg): ?>
    <div class="form-msg <?php echo $msgType; ?>"><?php echo e($msg); ?></div>
  <?php endif; ?>

  <div class="card">
    <div class="card-head">Tambah User Baru</div>
    <div class="card-body">
      <form method="POST">
        <input type="hidden" name="action" value="add">
        <div class="field-row">
          <div class="field"><label>NIK</label><input type="text" name="nik" required></div>
          <div class="field"><label>Nama Lengkap</label><input type="text" name="full_name" required></div>
          <div class="field"><label>Role</label>
            <select name="role" required>
              <option value="">- Pilih -</option>
              <option value="operator">Operator</option>
              <option value="foreman">Foreman</option>
              <option value="supervisor">Supervisor</option>
              <option value="manager">Manager</option>
              <option value="admin">Admin</option>
            </select>
          </div>
          <div class="field"><label>Email (opsional)</label><input type="email" name="email"></div>
          <div class="field"><label>Password</label><input type="text" name="password" required></div>
        </div>
        <button type="submit" class="btn-primary">Tambah User</button>
      </form>
    </div>
  </div>

  <div class="card">
    <div class="card-head">Daftar User (<?php echo count($users); ?>)</div>
    <table>
      <thead>
        <tr><th>NIK</th><th>Nama</th><th>Role</th><th>Email</th><th>Status</th><th style="width:170px;">Aksi</th></tr>
      </thead>
      <tbody>
        <?php foreach ($users as $u): ?>
        <tr>
          <td class="mono-cell"><?php echo e($u['nik']); ?></td>
          <td><?php echo e($u['full_name']); ?></td>
          <td><span class="role-pill"><?php echo e($u['role']); ?></span></td>
          <td><?php echo e($u['email']) ?: '-'; ?></td>
          <td>
            <?php if ($u['is_active']): ?>
              <span class="status-active">&#9679; Aktif</span>
            <?php else: ?>
              <span class="status-inactive">&#9679; Nonaktif</span>
            <?php endif; ?>
          </td>
          <td>
            <button type="button" class="btn-mini" onclick="openEdit(<?php echo htmlspecialchars(json_encode($u), ENT_QUOTES); ?>)">Edit</button>
            <?php if ($u['id'] != $current_user['id']): ?>
            <form method="POST" style="display:inline;" onsubmit="return confirm('Yakin ubah status user ini?');">
              <input type="hidden" name="action" value="toggle_active">
              <input type="hidden" name="id" value="<?php echo $u['id']; ?>">
              <button type="submit" class="btn-mini <?php echo $u['is_active'] ? 'danger' : ''; ?>">
                <?php echo $u['is_active'] ? 'Nonaktifkan' : 'Aktifkan'; ?>
              </button>
            </form>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="modal-overlay" id="editModal">
  <div class="modal-box">
    <div class="modal-head">
      <span>Edit User</span>
      <button type="button" class="modal-close" onclick="closeEdit()">&times;</button>
    </div>
    <div class="modal-body">
      <form method="POST">
        <input type="hidden" name="action" value="edit">
        <input type="hidden" name="id" id="edit_id">
        <div class="field" style="margin-bottom:12px;"><label>NIK</label><input type="text" id="edit_nik" disabled></div>
        <div class="field" style="margin-bottom:12px;"><label>Nama Lengkap</label><input type="text" name="full_name" id="edit_full_name" required></div>
        <div class="field" style="margin-bottom:12px;"><label>Role</label>
          <select name="role" id="edit_role" required>
            <option value="operator">Operator</option>
            <option value="foreman">Foreman</option>
            <option value="supervisor">Supervisor</option>
            <option value="manager">Manager</option>
            <option value="admin">Admin</option>
          </select>
        </div>
        <div class="field" style="margin-bottom:12px;"><label>Email</label><input type="email" name="email" id="edit_email"></div>
        <div class="field" style="margin-bottom:14px;"><label>Password baru (kosongkan kalau tidak ganti)</label><input type="text" name="password" id="edit_password" placeholder="•••••"></div>
        <button type="submit" class="btn-primary">Simpan Perubahan</button>
      </form>
    </div>
  </div>
</div>

<script>
function openEdit(u){
  document.getElementById('edit_id').value = u.id;
  document.getElementById('edit_nik').value = u.nik;
  document.getElementById('edit_full_name').value = u.full_name;
  document.getElementById('edit_role').value = u.role;
  document.getElementById('edit_email').value = u.email || '';
  document.getElementById('edit_password').value = '';
  document.getElementById('editModal').classList.add('open');
}
function closeEdit(){
  document.getElementById('editModal').classList.remove('open');
}
</script>
</body>
</html>