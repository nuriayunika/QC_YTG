<?php
require 'auth.php';
require_role(['admin']);

function e($s) { return htmlspecialchars($s ?? '', ENT_QUOTES); }

$msg = '';
$msgType = '';

// ============================================================
// Proses aksi
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // ---- MODEL ----
    if ($action === 'add_model') {
        $modelName = trim($_POST['model_name'] ?? '');
        $engineModel = trim($_POST['engine_model'] ?? '');
        if ($modelName === '' || $engineModel === '') {
            $msg = 'Nama model dan Engine Model wajib diisi.'; $msgType = 'err';
        } else {
            $check = $pdo->prepare("SELECT id FROM models WHERE model_name = :m");
            $check->execute([':m' => $modelName]);
            if ($check->fetch()) {
                $msg = 'Model dengan nama itu sudah ada.'; $msgType = 'err';
            } else {
                $stmt = $pdo->prepare("INSERT INTO models (model_name, engine_model) VALUES (:m, :e)");
                $stmt->execute([':m' => $modelName, ':e' => $engineModel]);
                $msg = "Model $modelName berhasil ditambahkan."; $msgType = 'ok';
            }
        }
    }

    if ($action === 'edit_model') {
        $id = (int) ($_POST['id'] ?? 0);
        $modelName = trim($_POST['model_name'] ?? '');
        $engineModel = trim($_POST['engine_model'] ?? '');
        if ($id > 0 && $modelName !== '' && $engineModel !== '') {
            $stmt = $pdo->prepare("UPDATE models SET model_name=:m, engine_model=:e WHERE id=:id");
            $stmt->execute([':m' => $modelName, ':e' => $engineModel, ':id' => $id]);
            $msg = 'Model berhasil diperbarui.'; $msgType = 'ok';
        }
    }

    if ($action === 'delete_model') {
        $id = (int) ($_POST['id'] ?? 0);
        $stmt = $pdo->prepare("DELETE FROM models WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $msg = 'Model berhasil dihapus.'; $msgType = 'ok';
    }

    // ---- CHECKLIST ITEM ----
    if ($action === 'add_item') {
        $categoryNo = (int) ($_POST['category_no'] ?? 0);
        $categoryName = trim($_POST['category_name'] ?? '');
        $itemLabel = trim($_POST['item_label'] ?? '');
        if ($categoryNo <= 0 || $categoryName === '' || $itemLabel === '') {
            $msg = 'Kategori dan nama item wajib diisi.'; $msgType = 'err';
        } else {
            $maxOrder = $pdo->prepare("SELECT COALESCE(MAX(item_order),0) FROM checklist_master WHERE category_no = :c");
            $maxOrder->execute([':c' => $categoryNo]);
            $nextOrder = (int) $maxOrder->fetchColumn() + 1;
            $itemCode = $categoryNo . '-' . $nextOrder;

            $stmt = $pdo->prepare("INSERT INTO checklist_master (item_code, category_no, category_name, item_order, item_label) VALUES (:code, :cno, :cname, :ord, :label)");
            $stmt->execute([':code' => $itemCode, ':cno' => $categoryNo, ':cname' => $categoryName, ':ord' => $nextOrder, ':label' => $itemLabel]);
            $msg = "Item '$itemLabel' berhasil ditambahkan ke kategori $categoryName."; $msgType = 'ok';
        }
    }

    if ($action === 'edit_item') {
        $itemCode = $_POST['item_code'] ?? '';
        $itemLabel = trim($_POST['item_label'] ?? '');
        $categoryName = trim($_POST['category_name'] ?? '');
        if ($itemCode !== '' && $itemLabel !== '') {
            $stmt = $pdo->prepare("UPDATE checklist_master SET item_label=:label WHERE item_code=:code");
            $stmt->execute([':label' => $itemLabel, ':code' => $itemCode]);
            // Update nama kategori di semua item satu kategori (biar konsisten)
            if ($categoryName !== '') {
                $catNo = explode('-', $itemCode)[0];
                $stmt2 = $pdo->prepare("UPDATE checklist_master SET category_name=:cname WHERE category_no=:cno");
                $stmt2->execute([':cname' => $categoryName, ':cno' => $catNo]);
            }
            $msg = 'Item berhasil diperbarui.'; $msgType = 'ok';
        }
    }

    if ($action === 'delete_item') {
        $itemCode = $_POST['item_code'] ?? '';
        $stmt = $pdo->prepare("DELETE FROM checklist_master WHERE item_code = :code");
        $stmt->execute([':code' => $itemCode]);
        $msg = 'Item berhasil dihapus.'; $msgType = 'ok';
    }
}

$models = $pdo->query("SELECT * FROM models ORDER BY model_name")->fetchAll(PDO::FETCH_ASSOC);
$masterRows = $pdo->query("SELECT * FROM checklist_master ORDER BY category_no, item_order")->fetchAll(PDO::FETCH_ASSOC);

$categories = [];
foreach ($masterRows as $r) {
    $categories[$r['category_no']]['name'] = $r['category_name'];
    $categories[$r['category_no']]['items'][] = $r;
}
$nextCategoryNo = $categories ? max(array_keys($categories)) + 1 : 1;
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Kelola Model & Checklist</title>
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
  .topbar-title{color:#fff;font-weight:700;font-size:15px;}
  .topbar-right{color:#F1D9DD;font-size:12.5px;}
  .topbar-right a{color:#F1D9DD;text-decoration:none;background:rgba(255,255,255,0.1);padding:7px 14px;border-radius:6px;font-weight:600;margin-left:8px;}
  .tabs{display:flex;gap:8px;padding:16px 20px 0;}
  .tab-btn{background:#fff;border:1px solid var(--border);color:var(--text);border-radius:8px 8px 0 0;padding:10px 18px;font-size:13px;font-weight:600;cursor:pointer;}
  .tab-btn.active{background:var(--maroon);color:#fff;border-color:var(--maroon);}

  .content-pad{padding:0 20px 20px;}
  .form-msg{padding:10px 14px;border-radius:6px;font-size:13px;margin:14px 0;}
  .form-msg.ok{background:var(--green-bg);color:var(--green);}
  .form-msg.err{background:var(--red-bg);color:var(--red);}

  .card{background:var(--panel);border:1px solid var(--border);border-radius:0 6px 6px 6px;margin-bottom:18px;overflow:hidden;}
  .card-head{background:var(--maroon);color:#fff;padding:12px 18px;font-weight:700;font-size:14px;}
  .card-body{padding:18px;}

  .field-row{display:flex;gap:12px;flex-wrap:wrap;align-items:end;margin-bottom:14px;}
  .field{flex:1;min-width:150px;}
  .field label{display:block;font-size:12px;color:var(--text-muted);margin-bottom:5px;font-weight:500;}
  .field input, .field select{width:100%;border:1px solid var(--border);border-radius:5px;padding:9px 11px;font-size:14px;font-family:'Inter',sans-serif;}

  .btn-primary{background:var(--maroon);color:#fff;border:none;border-radius:6px;padding:10px 18px;font-weight:600;font-size:13px;cursor:pointer;}
  .btn-mini{background:#fff;border:1px solid var(--border);color:var(--text);border-radius:5px;padding:5px 10px;font-size:11.5px;cursor:pointer;font-weight:600;}
  .btn-mini:hover{border-color:var(--maroon);color:var(--maroon);}
  .btn-mini.danger:hover{border-color:var(--red);color:var(--red);}

  table{width:100%;border-collapse:collapse;font-size:13px;}
  th{text-align:left;background:#F7F8FA;color:var(--text-muted);font-weight:600;font-size:11px;text-transform:uppercase;padding:8px 12px;border-bottom:1px solid var(--border);}
  td{padding:8px 12px;border-bottom:1px solid var(--border-soft);}
  .cat-block{margin-bottom:16px;border:1px solid var(--border);border-radius:6px;overflow:hidden;}
  .cat-title{background:var(--maroon-tint);color:var(--maroon-dark);font-weight:700;font-size:13px;padding:8px 14px;}
  .mono-cell{font-family:'JetBrains Mono',monospace;font-size:11.5px;color:var(--text-dim);}
</style>
</head>
<body>

<div class="topbar">
  <div class="topbar-title">&#9881; Kelola Model &amp; Checklist</div>
  <div class="topbar-right">
    <?php echo e($current_user['full_name']); ?> &middot; Admin
    <a href="manage_users.php">Kelola User</a>
    <a href="logout.php">Logout</a>
  </div>
</div>

<div class="tabs">
  <button class="tab-btn active" onclick="showTab('models')" id="tabbtn-models">Model Genset</button>
  <button class="tab-btn" onclick="showTab('checklist')" id="tabbtn-checklist">Checklist Item</button>
</div>

<div class="content-pad">
  <?php if ($msg): ?>
    <div class="form-msg <?php echo $msgType; ?>"><?php echo e($msg); ?></div>
  <?php endif; ?>

  <div id="tab-models">
    <div class="card">
      <div class="card-head">Tambah Model Baru</div>
      <div class="card-body">
        <form method="POST" class="field-row">
          <input type="hidden" name="action" value="add_model">
          <div class="field"><label>Nama Model</label><input type="text" name="model_name" placeholder="YTG 5.0 SE" required></div>
          <div class="field"><label>Engine Model</label><input type="text" name="engine_model" placeholder="TF90M-E2GN" required></div>
          <button type="submit" class="btn-primary">Tambah</button>
        </form>
      </div>
    </div>

    <div class="card">
      <div class="card-head">Daftar Model (<?php echo count($models); ?>)</div>
      <table>
        <thead><tr><th>Nama Model</th><th>Engine Model</th><th style="width:130px;">Aksi</th></tr></thead>
        <tbody>
          <?php foreach ($models as $m): ?>
          <tr>
            <td id="view-model-<?php echo $m['id']; ?>"><?php echo e($m['model_name']); ?></td>
            <td><?php echo e($m['engine_model']); ?></td>
            <td>
              <button type="button" class="btn-mini" onclick="editModelPrompt(<?php echo $m['id']; ?>, '<?php echo e($m['model_name']); ?>', '<?php echo e($m['engine_model']); ?>')">Edit</button>
              <form method="POST" style="display:inline;" onsubmit="return confirm('Yakin hapus model ini?');">
                <input type="hidden" name="action" value="delete_model">
                <input type="hidden" name="id" value="<?php echo $m['id']; ?>">
                <button type="submit" class="btn-mini danger">Hapus</button>
              </form>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div id="tab-checklist" style="display:none;">
    <div class="card">
      <div class="card-head">Tambah Item ke Kategori yang Sudah Ada</div>
      <div class="card-body">
        <form method="POST" class="field-row">
          <input type="hidden" name="action" value="add_item">
          <div class="field"><label>Kategori</label>
            <select name="category_no" id="selCategory" onchange="fillCatName()" required>
              <?php foreach ($categories as $no => $c): ?>
                <option value="<?php echo $no; ?>" data-name="<?php echo e($c['name']); ?>"><?php echo $no . '. ' . e($c['name']); ?></option>
              <?php endforeach; ?>
            </select>
            <input type="hidden" name="category_name" id="hiddenCatName">
          </div>
          <div class="field"><label>Nama Item Baru</label><input type="text" name="item_label" placeholder="misal: Radiator Cap" required></div>
          <button type="submit" class="btn-primary">Tambah Item</button>
        </form>
      </div>
    </div>

    <div class="card">
      <div class="card-head">Tambah Kategori Baru (sekalian item pertamanya)</div>
      <div class="card-body">
        <form method="POST" class="field-row">
          <input type="hidden" name="action" value="add_item">
          <input type="hidden" name="category_no" value="<?php echo $nextCategoryNo; ?>">
          <div class="field"><label>Nomor Kategori</label><input type="text" value="<?php echo $nextCategoryNo; ?>" disabled></div>
          <div class="field"><label>Nama Kategori Baru</label><input type="text" name="category_name" placeholder="misal: Radiator" required></div>
          <div class="field"><label>Item Pertama</label><input type="text" name="item_label" placeholder="misal: Radiator Cap" required></div>
          <button type="submit" class="btn-primary">Tambah Kategori</button>
        </form>
      </div>
    </div>

    <div class="card">
      <div class="card-head">Daftar Checklist (<?php echo count($masterRows); ?> item)</div>
      <div class="card-body">
        <?php foreach ($categories as $no => $c): ?>
        <div class="cat-block">
          <div class="cat-title"><?php echo $no . '. ' . e($c['name']); ?></div>
          <table>
            <thead><tr><th style="width:60px;">Kode</th><th>Item</th><th style="width:130px;">Aksi</th></tr></thead>
            <tbody>
              <?php foreach ($c['items'] as $item): ?>
              <tr>
                <td class="mono-cell"><?php echo e($item['item_code']); ?></td>
                <td><?php echo e($item['item_label']); ?></td>
                <td>
                  <button type="button" class="btn-mini" onclick="editItemPrompt('<?php echo e($item['item_code']); ?>', '<?php echo e($item['item_label']); ?>', '<?php echo e($c['name']); ?>')">Edit</button>
                  <form method="POST" style="display:inline;" onsubmit="return confirm('Yakin hapus item ini?');">
                    <input type="hidden" name="action" value="delete_item">
                    <input type="hidden" name="item_code" value="<?php echo e($item['item_code']); ?>">
                    <button type="submit" class="btn-mini danger">Hapus</button>
                  </form>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</div>

<form method="POST" id="editModelForm" style="display:none;">
  <input type="hidden" name="action" value="edit_model">
  <input type="hidden" name="id" id="em_id">
  <input type="hidden" name="model_name" id="em_model_name">
  <input type="hidden" name="engine_model" id="em_engine_model">
</form>

<form method="POST" id="editItemForm" style="display:none;">
  <input type="hidden" name="action" value="edit_item">
  <input type="hidden" name="item_code" id="ei_item_code">
  <input type="hidden" name="item_label" id="ei_item_label">
  <input type="hidden" name="category_name" id="ei_category_name">
</form>

<script>
function showTab(tab){
  document.getElementById('tab-models').style.display = tab==='models' ? 'block' : 'none';
  document.getElementById('tab-checklist').style.display = tab==='checklist' ? 'block' : 'none';
  document.getElementById('tabbtn-models').classList.toggle('active', tab==='models');
  document.getElementById('tabbtn-checklist').classList.toggle('active', tab==='checklist');
}

function fillCatName(){
  const sel = document.getElementById('selCategory');
  document.getElementById('hiddenCatName').value = sel.selectedOptions[0].dataset.name;
}
fillCatName();

function editModelPrompt(id, modelName, engineModel){
  const newModelName = prompt('Nama Model:', modelName);
  if (newModelName === null) return;
  const newEngineModel = prompt('Engine Model:', engineModel);
  if (newEngineModel === null) return;
  document.getElementById('em_id').value = id;
  document.getElementById('em_model_name').value = newModelName;
  document.getElementById('em_engine_model').value = newEngineModel;
  document.getElementById('editModelForm').submit();
}

function editItemPrompt(itemCode, itemLabel, categoryName){
  const newLabel = prompt('Nama Item:', itemLabel);
  if (newLabel === null) return;
  document.getElementById('ei_item_code').value = itemCode;
  document.getElementById('ei_item_label').value = newLabel;
  document.getElementById('ei_category_name').value = categoryName;
  document.getElementById('editItemForm').submit();
}
</script>
</body>
</html>