<?php
require 'auth.php';
require 'image_helper.php';
require_role(['operator']);

function e($s) { return htmlspecialchars($s ?? '', ENT_QUOTES); }

$editId = isset($_GET['id']) ? (int) $_GET['id'] : 0;

// ============================================================
// Proses simpan perubahan (POST)
// ============================================================
$msg = '';
$msgType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int) ($_POST['id'] ?? 0);

    $stmt = $pdo->prepare("SELECT * FROM checksheet_results WHERE id = :id");
    $stmt->execute([':id' => $id]);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);

    $fullyApproved = $existing && $existing['foreman_status'] === 'approved'
        && $existing['supervisor_status'] === 'approved'
        && $existing['manager_status'] === 'approved';

    if (!$existing) {
        $msg = 'Data tidak ditemukan.'; $msgType = 'err';
    } elseif ($fullyApproved) {
        $msg = 'Checksheet ini sudah full approved, tidak bisa diedit lagi.'; $msgType = 'err';
    } else {
        $items = json_decode($_POST['items'] ?? '[]', true) ?: [];
        $oldPhotos = json_decode($existing['photos'], true) ?: [null,null,null,null,null];
        $newPhotos = $oldPhotos;

        $uploadDir = __DIR__ . '/uploads/checksheet/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

        for ($i = 1; $i <= 5; $i++) {
            $key = 'photo_' . $i;
            if (isset($_FILES[$key]) && $_FILES[$key]['error'] === UPLOAD_ERR_OK) {
                $filename = 'chk_' . date('Ymd_His') . '_' . $i . '_' . substr(md5(uniqid('', true)), 0, 8) . '.jpg';
                $destPath = $uploadDir . $filename;
                $saved = resizeAndSaveImage($_FILES[$key]['tmp_name'], $destPath, 1200, 75);
                if ($saved) {
                    $newPhotos[$i - 1] = 'uploads/checksheet/' . basename($saved);
                }
            }
            // Kalau ada tombol "Hapus" ditekan untuk slot ini
            if (isset($_POST['remove_photo_' . $i])) {
                $newPhotos[$i - 1] = null;
            }
        }

        $sql = "UPDATE checksheet_results SET
                    tanggal=:tanggal, model=:model, voltage=:voltage, frequency=:frequency,
                    destination=:destination, engine_model=:engine_model, engine_no=:engine_no,
                    generator_no=:generator_no, frame_no=:frame_no, remarks=:remarks,
                    items=:items, photos=:photos,
                    last_edited_by_nik=:nik, last_edited_by_name=:name, last_edited_at=NOW()";

        // Kalau ada tahap yang statusnya "rejected", reset jadi "pending" lagi
        // biar masuk antrian approval ulang setelah diperbaiki
        $params = [
            ':tanggal' => $_POST['tanggal'] ?? '',
            ':model' => $_POST['model'] ?? '',
            ':voltage' => $_POST['voltage'] ?? '',
            ':frequency' => $_POST['frequency'] ?? '',
            ':destination' => $_POST['destination'] ?? '',
            ':engine_model' => $_POST['engine_model'] ?? '',
            ':engine_no' => $_POST['engine_no'] ?? '',
            ':generator_no' => $_POST['generator_no'] ?? '',
            ':frame_no' => $_POST['frame_no'] ?? '',
            ':remarks' => $_POST['remarks'] ?? '',
            ':items' => json_encode($items, JSON_UNESCAPED_UNICODE),
            ':photos' => json_encode($newPhotos, JSON_UNESCAPED_UNICODE),
            ':nik' => $current_user['nik'],
            ':name' => $current_user['full_name'],
            ':id' => $id,
        ];

        foreach (['foreman', 'supervisor', 'manager'] as $role) {
            if ($existing[$role . '_status'] === 'rejected') {
                $sql .= ", {$role}_status='pending', {$role}_by=NULL, {$role}_at=NULL, {$role}_reject_reason=NULL";
            }
        }

        $sql .= " WHERE id=:id";
        $upd = $pdo->prepare($sql);
        $upd->execute($params);

        header('Location: edit_checksheet.php?saved=1');
        exit;
    }
}

// ============================================================
// Ambil data buat edit (kalau ada ?id=)
// ============================================================
$editData = null;
if ($editId > 0) {
    $stmt = $pdo->prepare("SELECT * FROM checksheet_results WHERE id = :id");
    $stmt->execute([':id' => $editId]);
    $editData = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($editData) {
        $fullyApproved = $editData['foreman_status'] === 'approved'
            && $editData['supervisor_status'] === 'approved'
            && $editData['manager_status'] === 'approved';
        if ($fullyApproved) {
            $editData = null;
            $msg = 'Checksheet ini sudah full approved, tidak bisa diedit lagi.';
            $msgType = 'err';
        }
    }
}

// ============================================================
// List checksheet yang masih bisa diedit
// ============================================================
$rows = $pdo->query("
    SELECT * FROM checksheet_results
    WHERE NOT (foreman_status='approved' AND supervisor_status='approved' AND manager_status='approved')
    ORDER BY created_at DESC
")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Edit Checksheet</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@500;600&display=swap" rel="stylesheet">
<style>
  :root{
    --maroon:#7B2334; --maroon-dark:#5E1A27; --maroon-tint:#F7E9EB;
    --bg:#EEF0F3; --panel:#FFFFFF; --border:#E1E4E9; --border-soft:#EEF0F3;
    --text:#23262B; --text-muted:#6B7280; --text-dim:#9CA3AF;
    --green:#1F9D55; --green-bg:#E7F7EE; --amber:#B7791F; --amber-bg:#FBF3E1; --red:#C0362C; --red-bg:#FBEAEA;
  }
  *{box-sizing:border-box;}
  body{margin:0;background:var(--bg);color:var(--text);font-family:'Inter',sans-serif;}
  .topbar{background:var(--maroon);display:flex;align-items:center;justify-content:space-between;padding:10px 20px;gap:16px;flex-wrap:wrap;}
  .btn-back{background:rgba(255,255,255,0.12);color:#fff;text-decoration:none;padding:8px 14px;border-radius:6px;font-size:12.5px;font-weight:600;}
  .topbar-title{color:#fff;font-weight:700;font-size:15px;display:flex;align-items:center;gap:12px;}
  .topbar-right{color:#F1D9DD;font-size:12.5px;}
  .topbar-right a{color:#F1D9DD;text-decoration:none;background:rgba(255,255,255,0.1);padding:7px 14px;border-radius:6px;font-weight:600;margin-left:8px;}

  .content-pad{padding:20px;}
  .form-msg{padding:10px 14px;border-radius:6px;font-size:13px;margin-bottom:14px;}
  .form-msg.ok{background:var(--green-bg);color:var(--green);}
  .form-msg.err{background:var(--red-bg);color:var(--red);}

  .card{background:var(--panel);border:1px solid var(--border);margin-bottom:18px;}
  .card-body{padding:18px 20px;}
  table{width:100%;border-collapse:collapse;font-size:13px;}
  th{text-align:left;background:#F7F8FA;color:var(--text-muted);font-weight:600;font-size:11.5px;text-transform:uppercase;letter-spacing:0.03em;padding:10px 12px;border-bottom:1px solid var(--border);}
  td{padding:10px 12px;border-bottom:1px solid var(--border-soft);vertical-align:top;}
  tr:hover td{background:#FAFBFC;}
  .badge{display:inline-block;font-size:11px;font-weight:600;padding:3px 9px;border-radius:20px;}
  .badge.approved{background:var(--green-bg);color:var(--green);}
  .badge.rejected{background:var(--red-bg);color:var(--red);}
  .badge.pending{background:var(--amber-bg);color:var(--amber);}
  .btn-mini{background:#fff;border:1px solid var(--maroon);color:var(--maroon);border-radius:5px;padding:6px 12px;font-size:12px;cursor:pointer;font-weight:600;text-decoration:none;display:inline-block;}
  .mono-cell{font-family:'JetBrains Mono',monospace;font-size:12px;color:var(--text-muted);}
  .empty-hint{padding:2.5rem;text-align:center;color:var(--text-dim);font-size:13px;}
  .edited-note{font-size:11px;color:var(--text-dim);font-style:italic;margin-top:2px;}

  .section-head{background:var(--maroon);color:#fff;padding:12px 20px;font-weight:700;font-size:15px;}
  .field-row{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:16px 20px;margin-bottom:16px;}
  .field label{display:block;font-size:12.5px;color:var(--text-muted);margin-bottom:6px;font-weight:500;}
  .field input, .field select, .field textarea{width:100%;background:#fff;border:1px solid var(--border);color:var(--text);border-radius:5px;padding:9px 11px;font-size:14px;font-family:'Inter',sans-serif;}
  .field input:focus, .field select:focus, .field textarea:focus{outline:none;border-color:var(--maroon);}

  table.chk-table th{padding:10px 12px;}
  table.chk-table td{padding:9px 12px;}
  table.chk-table tr.cat-row td{background:var(--maroon-tint);color:var(--maroon-dark);font-weight:700;font-size:12.5px;text-transform:uppercase;}
  .col-hasil{width:140px;}
  .hasil-select{width:100%;border:1px solid var(--border);border-radius:5px;padding:7px 9px;font-size:13px;}
  .hasil-select.val-ok{border-color:var(--green);color:var(--green);background:var(--green-bg);}
  .hasil-select.val-dash{border-color:var(--text-dim);color:var(--text-muted);background:#F5F6F8;}

  .photo-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:14px;}
  .photo-slot{background:#F9FAFB;border:1px dashed var(--border);border-radius:6px;padding:12px;text-align:center;}
  .photo-slot img{width:100%;height:110px;object-fit:cover;border-radius:5px;margin-bottom:8px;border:1px solid var(--border);}
  .photo-slot input[type=file]{width:100%;font-size:11px;margin-top:6px;}
  .photo-slot label.remove-lbl{display:block;margin-top:6px;font-size:11px;color:var(--red);}

  .btn-primary{background:var(--maroon);color:#fff;border:none;border-radius:6px;padding:12px 24px;font-weight:700;font-size:14px;cursor:pointer;}
</style>
</head>
<body>

<div class="topbar">
  <div style="display:flex;align-items:center;gap:12px;">
    <a href="index.php" class="btn-back">&larr; Kembali</a>
    <div class="topbar-title">&#9998; Edit Checksheet</div>
  </div>
  <div class="topbar-right">
    <?php echo e($current_user['full_name']); ?> &middot; Operator
    <a href="logout.php">Logout</a>
  </div>
</div>

<div class="content-pad">
  <?php if (isset($_GET['saved'])): ?>
    <div class="form-msg ok">Checksheet berhasil diperbarui.</div>
  <?php endif; ?>
  <?php if ($msg): ?>
    <div class="form-msg <?php echo $msgType; ?>"><?php echo e($msg); ?></div>
  <?php endif; ?>

  <?php if (!$editData): ?>
  <!-- ================= LIST MODE ================= -->
  <div class="card">
    <table>
      <thead>
        <tr><th>Tanggal</th><th>Model</th><th>Engine No.</th><th>Generator No.</th><th>Foreman</th><th>Supervisor</th><th>Manager</th><th style="width:80px;">Aksi</th></tr>
      </thead>
      <tbody>
        <?php if (empty($rows)): ?>
        <tr><td colspan="8"><div class="empty-hint">Tidak ada checksheet yang bisa diedit (semua sudah full approved atau belum ada data).</div></td></tr>
        <?php else: foreach ($rows as $r): ?>
        <tr>
          <td class="mono-cell"><?php echo e($r['tanggal']); ?></td>
          <td><?php echo e($r['model']); ?>
            <?php if ($r['last_edited_by_name']): ?>
              <div class="edited-note">Diedit oleh <?php echo e($r['last_edited_by_name']); ?> &middot; <?php echo date('d/m/y H:i', strtotime($r['last_edited_at'])); ?></div>
            <?php endif; ?>
          </td>
          <td class="mono-cell"><?php echo e($r['engine_no']); ?></td>
          <td class="mono-cell"><?php echo e($r['generator_no']); ?></td>
          <td><span class="badge <?php echo $r['foreman_status']; ?>"><?php echo ucfirst($r['foreman_status']); ?></span>
            <?php if ($r['foreman_status']==='rejected' && $r['foreman_reject_reason']): ?><div class="edited-note" style="color:var(--red);">"<?php echo e($r['foreman_reject_reason']); ?>"</div><?php endif; ?>
          </td>
          <td><span class="badge <?php echo $r['supervisor_status']; ?>"><?php echo ucfirst($r['supervisor_status']); ?></span>
            <?php if ($r['supervisor_status']==='rejected' && $r['supervisor_reject_reason']): ?><div class="edited-note" style="color:var(--red);">"<?php echo e($r['supervisor_reject_reason']); ?>"</div><?php endif; ?>
          </td>
          <td><span class="badge <?php echo $r['manager_status']; ?>"><?php echo ucfirst($r['manager_status']); ?></span>
            <?php if ($r['manager_status']==='rejected' && $r['manager_reject_reason']): ?><div class="edited-note" style="color:var(--red);">"<?php echo e($r['manager_reject_reason']); ?>"</div><?php endif; ?>
          </td>
          <td><a href="edit_checksheet.php?id=<?php echo $r['id']; ?>" class="btn-mini">Edit</a></td>
        </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>

  <?php else: ?>
  <!-- ================= EDIT FORM MODE ================= -->
  <?php
    $items = json_decode($editData['items'], true) ?: [];
    $photos = json_decode($editData['photos'], true) ?: [null,null,null,null,null];
  ?>
  <form method="POST" enctype="multipart/form-data" id="editForm">
    <input type="hidden" name="id" value="<?php echo $editData['id']; ?>">
    <input type="hidden" name="items" id="itemsInput">

    <div class="card">
      <div class="section-head">General Checksheet</div>
      <div class="card-body">
        <div class="field-row">
          <div class="field"><label>Date of Report</label><input type="date" name="tanggal" value="<?php echo e($editData['tanggal']); ?>" required></div>
          <div class="field"><label>Model</label>
            <select name="model" id="f_model" required></select>
          </div>
          <div class="field"><label>Voltage</label>
            <select name="voltage" required>
              <option <?php echo $editData['voltage']==='110V / 220V'?'selected':''; ?>>110V / 220V</option>
              <option <?php echo $editData['voltage']==='127V / 220V'?'selected':''; ?>>127V / 220V</option>
              <option <?php echo $editData['voltage']==='220V / 380V'?'selected':''; ?>>220V / 380V</option>
            </select>
          </div>
          <div class="field"><label>Frequency</label>
            <select name="frequency" required>
              <option <?php echo $editData['frequency']==='50Hz'?'selected':''; ?>>50Hz</option>
              <option <?php echo $editData['frequency']==='60Hz'?'selected':''; ?>>60Hz</option>
            </select>
          </div>
        </div>
        <div class="field-row">
          <div class="field"><label>Destination</label><input type="text" name="destination" value="<?php echo e($editData['destination']); ?>" required></div>
          <div class="field"><label>Engine Model</label><input type="text" name="engine_model" id="f_engine_model" value="<?php echo e($editData['engine_model']); ?>" disabled>
            <input type="hidden" name="engine_model" id="f_engine_model_hidden" value="<?php echo e($editData['engine_model']); ?>"></div>
          <div class="field"><label>Engine No.</label><input type="text" name="engine_no" value="<?php echo e($editData['engine_no']); ?>" required></div>
          <div class="field"><label>Generator No.</label><input type="text" name="generator_no" value="<?php echo e($editData['generator_no']); ?>" required></div>
        </div>
        <div class="field-row">
          <div class="field"><label>Frame No.</label><input type="text" name="frame_no" value="<?php echo e($editData['frame_no']); ?>" required></div>
          <div class="field" style="grid-column:span 3;"><label>Remarks</label><textarea name="remarks" rows="1"><?php echo e($editData['remarks']); ?></textarea></div>
        </div>
      </div>
    </div>

    <div class="card">
      <div class="section-head">Checklist Pemeriksaan</div>
      <table class="chk-table">
        <thead><tr><th style="width:30px;">#</th><th>Item</th><th class="col-hasil">Hasil</th></tr></thead>
        <tbody id="checklistBody"></tbody>
      </table>
    </div>

    <div class="card">
      <div class="section-head">Foto Dokumentasi</div>
      <div class="card-body">
        <div class="photo-grid" id="photoGrid">
          <?php for ($i = 0; $i < 5; $i++): $p = $photos[$i] ?? null; ?>
          <div class="photo-slot">
            <div style="font-size:12px;color:var(--text-muted);margin-bottom:6px;">Foto <?php echo $i+1; ?></div>
            <?php if ($p): ?>
              <img src="<?php echo e($p); ?>">
              <label class="remove-lbl"><input type="checkbox" name="remove_photo_<?php echo $i+1; ?>" value="1"> Hapus foto ini</label>
            <?php else: ?>
              <div style="height:110px;background:#eee;border-radius:5px;margin-bottom:8px;"></div>
            <?php endif; ?>
            <input type="file" name="photo_<?php echo $i+1; ?>" accept="image/*">
          </div>
          <?php endfor; ?>
        </div>
      </div>
    </div>

    <div class="card">
      <div class="card-body" style="display:flex;justify-content:space-between;align-items:center;">
        <span class="edited-note">Diedit oleh: <?php echo e($current_user['full_name']); ?></span>
        <button type="submit" class="btn-primary">Simpan Perubahan</button>
      </div>
    </div>
  </form>
  <?php endif; ?>
</div>

<?php if ($editData): ?>
<script>
const existingItems = <?php echo json_encode($items); ?>;
const existingModel = <?php echo json_encode($editData['model']); ?>;
const state = {...existingItems};

const modelSel = document.getElementById('f_model');

async function initEditFormData(){
  const [modelsRes, checklistRes] = await Promise.all([
    fetch('get_models.php'),
    fetch('get_checklist_master.php')
  ]);
  const MODEL_MAP = await modelsRes.json();
  const CHECKLIST = await checklistRes.json();

  modelSel.innerHTML = '<option value="">- Pilih Model -</option>' + Object.keys(MODEL_MAP).map(m=>'<option value="'+m+'"'+(m===existingModel?' selected':'')+'>'+m+'</option>').join('');
  modelSel.onchange = ()=>{
    document.getElementById('f_engine_model').value = MODEL_MAP[modelSel.value] || '';
    document.getElementById('f_engine_model_hidden').value = MODEL_MAP[modelSel.value] || '';
  };

  const body = document.getElementById('checklistBody');
  CHECKLIST.forEach(cat=>{
    const catRow = document.createElement('tr');
    catRow.className = 'cat-row';
    catRow.innerHTML = '<td colspan="3">'+cat.no+'. '+cat.title+'</td>';
    body.appendChild(catRow);
    cat.items.forEach((label,i)=>{
      const id = cat.no+'-'+(i+1);
      const tr = document.createElement('tr');
      const val = state[id] || '';
      tr.innerHTML =
        '<td>'+(i+1)+'</td>'+
        '<td>'+label+'</td>'+
        '<td class="col-hasil"><select class="hasil-select '+(val==='ok'?'val-ok':val==='dash'?'val-dash':'')+'" data-id="'+id+'">'+
          '<option value="">- Pilih -</option>'+
          '<option value="ok"'+(val==='ok'?' selected':'')+'>OK</option>'+
          '<option value="dash"'+(val==='dash'?' selected':'')+'>-</option>'+
        '</select></td>';
      body.appendChild(tr);
    });
  });

  document.querySelectorAll('.hasil-select').forEach(sel=>{
    sel.onchange = ()=>{
      state[sel.dataset.id] = sel.value;
      sel.classList.remove('val-ok','val-dash');
      if(sel.value==='ok') sel.classList.add('val-ok');
      if(sel.value==='dash') sel.classList.add('val-dash');
    };
  });
}
initEditFormData();

document.getElementById('editForm').addEventListener('submit', ()=>{
  document.getElementById('itemsInput').value = JSON.stringify(state);
});
</script>
<?php endif; ?>

</body>
</html>