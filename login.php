<?php
session_start();
require 'config.php';

if (isset($_SESSION['user_id'])) {
    header('Location: ' . ($_SESSION['role'] === 'operator' ? 'index.php' : ($_SESSION['role'] === 'admin' ? 'manage_users.php' : 'approval.php')));
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nik = trim($_POST['nik'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($nik === '' || $password === '') {
        $error = 'NIK dan password wajib diisi.';
    } else {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE nik = :nik");
        $stmt->execute([':nik' => $nik]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && !$user['is_active']) {
            $error = 'Akun Anda sudah dinonaktifkan. Hubungi administrator.';
        } elseif ($user && $password === $user['password']) {
            $_SESSION['user_id']   = $user['id'];
            $_SESSION['nik']       = $user['nik'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['role']      = $user['role'];
            header('Location: ' . ($user['role'] === 'operator' ? 'index.php' : ($user['role'] === 'admin' ? 'manage_users.php' : 'approval.php')));
            exit();
        } else {
            $error = 'NIK atau password salah.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login - Final Check Sheet Generator Set</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
  :root{
    --maroon:#7B2334;
    --maroon-dark:#5E1A27;
    --bg:#EEF0F3;
    --border:#E1E4E9;
    --text:#23262B;
    --text-muted:#6B7280;
  }
  *{box-sizing:border-box;}
  body{
    margin:0;min-height:100vh;background:var(--bg);color:var(--text);
    font-family:'Inter',sans-serif;display:flex;align-items:center;justify-content:center;
  }
  .login-card{
    background:#fff;border:1px solid var(--border);border-radius:8px;
    width:100%;max-width:360px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,0.06);
  }
  .login-header{background:var(--maroon);color:#fff;padding:24px;text-align:center;}
  .login-header h1{font-size:16px;font-weight:700;margin:0 0 4px;letter-spacing:0.02em;}
  .login-header p{font-size:11.5px;color:#F1D9DD;margin:0;}
  .login-body{padding:24px;}
  .field{margin-bottom:16px;}
  .field label{display:block;font-size:12.5px;color:var(--text-muted);margin-bottom:6px;font-weight:500;}
  .field input{
    width:100%;border:1px solid var(--border);border-radius:5px;
    padding:10px 12px;font-size:14px;font-family:'Inter',sans-serif;
  }
  .field input:focus{outline:none;border-color:var(--maroon);}
  .btn-login{
    width:100%;background:var(--maroon);color:#fff;border:none;border-radius:6px;
    padding:12px;font-weight:600;font-size:14px;cursor:pointer;margin-top:4px;
  }
  .btn-login:hover{background:var(--maroon-dark);}
  .error-msg{
    background:#FBEAEA;color:#C0362C;border:1px solid #F3C6C6;border-radius:5px;
    padding:10px 12px;font-size:13px;margin-bottom:16px;
  }
</style>
</head>
<body>
  <div class="login-card">
    <div class="login-header">
      <h1>FINAL CHECK SHEET GENERATOR SET</h1>
      <p>Silakan login untuk melanjutkan</p>
    </div>
    <div class="login-body">
      <?php if ($error): ?>
        <div class="error-msg"><?php echo htmlspecialchars($error); ?></div>
      <?php endif; ?>
      <form method="POST">
        <div class="field">
          <label>NIK</label>
          <input type="text" name="nik" autofocus required>
        </div>
        <div class="field">
          <label>Password</label>
          <input type="password" name="password" required>
        </div>
        <button type="submit" class="btn-login">Login</button>
      </form>
    </div>
  </div>
</body>
</html>