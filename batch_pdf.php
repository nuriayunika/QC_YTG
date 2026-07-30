<?php
require 'auth.php';
require_role(['supervisor', 'manager']);

$rows = $pdo->query("SELECT id, tanggal, model, engine_no, generator_no, destination FROM checksheet_results WHERE manager_status = 'approved' ORDER BY model, tanggal DESC")->fetchAll(PDO::FETCH_ASSOC);

$byModel = [];
foreach ($rows as $r) {
    $byModel[$r['model']][] = $r;
}
ksort($byModel);

function e($str) { return htmlspecialchars($str ?? '-', ENT_QUOTES); }
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Batch Download PDF</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@500;600&display=swap" rel="stylesheet">
<style>
  :root{
    --maroon:#7B2334; --maroon-dark:#5E1A27; --maroon-tint:#F7E9EB;
    --bg:#EEF0F3; --panel:#FFFFFF; --border:#E1E4E9; --border-soft:#EEF0F3;
    --text:#23262B; --text-muted:#6B7280; --text-dim:#9CA3AF;
  }
  *{box-sizing:border-box;}
  body{margin:0;background:var(--bg);color:var(--text);font-family:'Inter',sans-serif;}
  .topbar{background:var(--maroon);display:flex;align-items:center;justify-content:space-between;padding:10px 20px;gap:16px;flex-wrap:wrap;}
  .topbar-left{display:flex;align-items:center;gap:12px;}
  .btn-back{background:rgba(255,255,255,0.12);color:#fff;text-decoration:none;padding:8px 14px;border-radius:6px;font-size:12.5px;font-weight:600;}
  .topbar-title{color:#fff;font-weight:700;font-size:15px;display:flex;align-items:center;gap:8px;}
  .topbar-right{display:flex;align-items:center;gap:10px;color:#F1D9DD;font-size:12.5px;}
  .role-pill{background:rgba(255,255,255,0.15);padding:4px 10px;border-radius:4px;font-size:11px;font-weight:700;letter-spacing:0.03em;}

  .layout{display:grid;grid-template-columns:1fr 340px;gap:18px;padding:20px;align-items:start;}
  .panel{background:var(--panel);border:1px solid var(--border);border-radius:6px;overflow:hidden;}
  .panel-head{background:var(--maroon);color:#fff;padding:12px 18px;display:flex;align-items:center;justify-content:space-between;font-weight:700;font-size:14px;}
  .panel-head-btns{display:flex;gap:8px;}
  .btn-mini{background:rgba(255,255,255,0.15);color:#fff;border:none;border-radius:5px;padding:6px 12px;font-size:11.5px;font-weight:600;cursor:pointer;}
  .panel-body{padding:16px 18px;}

  .tabs{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:14px;}
  .tab-btn{background:#fff;border:1px solid var(--border);color:var(--text);border-radius:20px;padding:8px 16px;font-size:12.5px;font-weight:600;cursor:pointer;}
  .tab-btn.active{background:var(--maroon);color:#fff;border-color:var(--maroon);}
  .tab-btn .count{background:rgba(0,0,0,0.08);border-radius:10px;padding:1px 7px;margin-left:6px;font-size:10.5px;}
  .tab-btn.active .count{background:rgba(255,255,255,0.25);}

  .search-row{display:flex;gap:8px;margin-bottom:16px;}
  .search-row input{flex:1;border:1px solid var(--border);border-radius:6px;padding:10px 12px;font-size:13.5px;font-family:'Inter',sans-serif;}
  .search-row input:focus{outline:none;border-color:var(--maroon);}

  .model-group-head{background:var(--maroon-tint);color:var(--maroon-dark);font-weight:700;font-size:12.5px;padding:9px 12px;border-radius:5px;margin:12px 0 8px;display:flex;align-items:center;gap:8px;}
  .model-group-head .count{background:#fff;border-radius:10px;padding:1px 8px;font-size:10.5px;color:var(--text-muted);font-weight:600;}

  .engine-item{display:flex;align-items:center;gap:12px;border:1px solid var(--border);border-radius:6px;padding:10px 12px;margin-bottom:8px;cursor:pointer;}
  .engine-item:hover{border-color:var(--maroon);}
  .engine-item input{width:16px;height:16px;}
  .engine-item .eng-no{font-weight:700;font-size:13.5px;}
  .engine-item .eng-sub{font-size:11.5px;color:var(--text-muted);margin-top:1px;}

  .empty-hint{padding:2rem;text-align:center;color:var(--text-dim);font-size:13px;}

  .sidebar-empty{color:var(--text-dim);font-size:13px;}
  .sidebar-list{list-style:none;margin:0 0 14px;padding:0;max-height:220px;overflow-y:auto;}
  .sidebar-list li{font-size:12.5px;padding:6px 0;border-bottom:1px solid var(--border-soft);display:flex;justify-content:space-between;}
  .sidebar-list li b{color:var(--text);}
  .btn-download{
    width:100%;background:var(--maroon);color:#fff;border:none;border-radius:6px;
    padding:13px;font-weight:700;font-size:13.5px;cursor:pointer;margin-top:6px;
  }
  .btn-download:disabled{background:#D1D5DB;color:#9CA3AF;cursor:not-allowed;}
  .info-note{background:var(--maroon-tint);color:var(--maroon-dark);border-radius:6px;padding:10px 12px;font-size:11.5px;margin-top:14px;line-height:1.5;}
</style>
</head>
<body>

<div class="topbar">
  <div class="topbar-left">
    <a href="approval.php" class="btn-back">&larr; Kembali</a>
    <div class="topbar-title">&#128196; Batch Download PDF</div>
  </div>
  <div class="topbar-right">
    <span><?php echo e($current_user['full_name']); ?></span>
    <span class="role-pill"><?php echo strtoupper(role_label($current_user['role'])); ?></span>
  </div>
</div>

<div class="layout">
  <div class="panel">
    <div class="panel-head">
      <span>Pilih Engine</span>
      <div class="panel-head-btns">
        <button type="button" class="btn-mini" id="btnSelectAll">Pilih Semua</button>
        <button type="button" class="btn-mini" id="btnClearAll">Bersihkan</button>
      </div>
    </div>
    <div class="panel-body">

      <div class="tabs" id="modelTabs">
        <button class="tab-btn active" data-model="">Semua <span class="count"><?php echo count($rows); ?></span></button>
        <?php foreach ($byModel as $model => $items): ?>
        <button class="tab-btn" data-model="<?php echo e($model); ?>"><?php echo e($model); ?> <span class="count"><?php echo count($items); ?></span></button>
        <?php endforeach; ?>
      </div>

      <div class="search-row">
        <input type="text" id="searchBox" placeholder="Cari engine no atau model...">
      </div>

      <div id="engineList">
        <?php if (empty($rows)): ?>
          <div class="empty-hint">Belum ada checksheet yang full approved.</div>
        <?php else: foreach ($byModel as $model => $items): ?>
          <div class="model-group" data-model="<?php echo e($model); ?>">
            <div class="model-group-head">&#9881; <?php echo e($model); ?> <span class="count"><?php echo count($items); ?> engine</span></div>
            <?php foreach ($items as $it): ?>
            <label class="engine-item" data-search="<?php echo e(strtolower($it['engine_no'].' '.$it['model'])); ?>">
              <input type="checkbox" class="row-check" value="<?php echo $it['id']; ?>"
                     data-engine="<?php echo e($it['engine_no']); ?>" data-model="<?php echo e($it['model']); ?>">
              <div>
                <div class="eng-no"><?php echo e($it['engine_no']); ?></div>
                <div class="eng-sub"><?php echo e($it['model']); ?> &middot; <?php echo isset($it['tanggal']) ? date('d/m/Y', strtotime($it['tanggal'])) : '-'; ?></div>
              </div>
            </label>
            <?php endforeach; ?>
          </div>
        <?php endforeach; endif; ?>
      </div>

    </div>
  </div>

  <div class="panel" style="align-self:flex-start;">
    <div class="panel-head"><span>&#128190; Download</span></div>
    <div class="panel-body">
      <div id="sidebarEmpty" class="sidebar-empty">Belum ada engine yang dipilih.</div>
      <ul class="sidebar-list" id="sidebarList" style="display:none;"></ul>
      <button type="button" class="btn-download" id="btnDownload" disabled>&#8681; Download PDF</button>
      <div class="info-note">PDF berisi General Checksheet, 49 item checklist, dan foto dokumentasi untuk tiap engine yang dipilih, digabung jadi satu file.</div>
    </div>
  </div>
</div>

<script>
const tabs = document.querySelectorAll('.tab-btn');
const groups = document.querySelectorAll('.model-group');
const searchBox = document.getElementById('searchBox');
let activeModel = '';

function applyFilter(){
  const term = searchBox.value.trim().toLowerCase();
  groups.forEach(g => {
    const model = g.dataset.model;
    let anyVisible = false;
    g.querySelectorAll('.engine-item').forEach(item => {
      const matchModel = !activeModel || model === activeModel;
      const matchSearch = !term || item.dataset.search.includes(term);
      const show = matchModel && matchSearch;
      item.style.display = show ? 'flex' : 'none';
      if (show) anyVisible = true;
    });
    g.style.display = anyVisible ? 'block' : 'none';
  });
}

tabs.forEach(tab => {
  tab.addEventListener('click', () => {
    tabs.forEach(t => t.classList.remove('active'));
    tab.classList.add('active');
    activeModel = tab.dataset.model;
    applyFilter();
  });
});
searchBox.addEventListener('input', applyFilter);

const rowChecks = document.querySelectorAll('.row-check');
const sidebarEmpty = document.getElementById('sidebarEmpty');
const sidebarList = document.getElementById('sidebarList');
const btnDownload = document.getElementById('btnDownload');
const STORAGE_KEY = 'batch_pdf_selected_ids';

function saveSelectedToStorage(){
  const ids = Array.from(document.querySelectorAll('.row-check:checked')).map(cb => cb.value);
  try{ localStorage.setItem(STORAGE_KEY, JSON.stringify(ids)); }catch(e){}
}

function restoreSelectedFromStorage(){
  try{
    const saved = JSON.parse(localStorage.getItem(STORAGE_KEY) || '[]');
    if(!Array.isArray(saved) || saved.length === 0) return;
    rowChecks.forEach(cb => { if(saved.includes(cb.value)) cb.checked = true; });
  }catch(e){}
}

function updateSidebar(){
  const checked = Array.from(document.querySelectorAll('.row-check:checked'));
  saveSelectedToStorage();
  if (checked.length === 0) {
    sidebarEmpty.style.display = 'block';
    sidebarList.style.display = 'none';
    btnDownload.disabled = true;
    return;
  }
  sidebarEmpty.style.display = 'none';
  sidebarList.style.display = 'block';
  btnDownload.disabled = false;
  sidebarList.innerHTML = checked.map(cb =>
    '<li><span>'+cb.dataset.engine+'</span><b>'+cb.dataset.model+'</b></li>'
  ).join('');
}

restoreSelectedFromStorage();
updateSidebar();

rowChecks.forEach(cb => cb.addEventListener('change', updateSidebar));

document.getElementById('btnSelectAll').addEventListener('click', () => {
  document.querySelectorAll('.engine-item').forEach(item => {
    if (item.style.display !== 'none') item.querySelector('.row-check').checked = true;
  });
  updateSidebar();
});

document.getElementById('btnClearAll').addEventListener('click', () => {
  rowChecks.forEach(cb => cb.checked = false);
  updateSidebar();
  try{ localStorage.removeItem(STORAGE_KEY); }catch(e){}
});

btnDownload.addEventListener('click', () => {
  const ids = Array.from(document.querySelectorAll('.row-check:checked')).map(cb => cb.value);
  if (ids.length === 0) return;
  window.location.href = 'generate_pdf_batch.php?ids=' + ids.join(',');
});
</script>
</body>
</html>