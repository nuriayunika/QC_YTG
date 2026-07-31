<?php
require 'auth.php';
require_role(['foreman', 'supervisor', 'manager']);

$myRole = $current_user['role']; // foreman | supervisor | manager

// ============================================================
// Ambil data
// ============================================================
$rows = $pdo->query("SELECT * FROM checksheet_results ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);

$master = $pdo->query("SELECT * FROM checklist_master ORDER BY category_no, item_order")->fetchAll(PDO::FETCH_ASSOC);
$categories = [];
foreach ($master as $m) {
    $catNo = $m['category_no'];
    if (!isset($categories[$catNo])) {
        $categories[$catNo] = ['name' => $m['category_name'], 'items' => []];
    }
    $categories[$catNo]['items'][] = $m;
}

function e($str) { return htmlspecialchars($str ?? '-', ENT_QUOTES); }

function badge($status, $by, $at, $reason = null) {
    if ($status === 'approved') {
        $sub = $by ? htmlspecialchars($by) . ($at ? ' &middot; ' . date('d/m/y H:i', strtotime($at)) : '') : '';
        return '<span class="badge approved">Approved</span><div class="badge-sub">' . $sub . '</div>';
    }
    if ($status === 'rejected') {
        $sub = $by ? htmlspecialchars($by) . ($at ? ' &middot; ' . date('d/m/y H:i', strtotime($at)) : '') : '';
        $reasonHtml = $reason ? '<div class="badge-reason">"' . htmlspecialchars($reason) . '"</div>' : '';
        return '<span class="badge rejected">Rejected</span><div class="badge-sub">' . $sub . '</div>' . $reasonHtml;
    }
    return '<span class="badge pending">Pending</span>';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Approval - Final Check Sheet Generator Set</title>
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
  .wrap{width:100%;margin:0;padding:0 0 3rem;}
  .topbar{background:var(--maroon);display:flex;align-items:center;justify-content:space-between;padding:10px 20px;gap:16px;flex-wrap:wrap;}
  .topbar-left{display:flex;align-items:center;gap:10px;}
  .logo-dot{width:34px;height:34px;border-radius:6px;background:rgba(255,255,255,0.12);display:flex;align-items:center;justify-content:center;color:#fff;font-size:16px;}
  .topbar-title{color:#fff;font-weight:700;font-size:14px;}
  .topbar-right{display:flex;align-items:center;gap:10px;color:#F1D9DD;font-size:12.5px;}
  .topbar-right a{color:#F1D9DD;text-decoration:none;background:rgba(255,255,255,0.1);padding:7px 14px;border-radius:6px;font-size:12.5px;font-weight:600;}

  .content-pad{padding:20px;}
  .form-msg{padding:10px 14px;border-radius:6px;font-size:13px;margin-bottom:14px;}
  .form-msg.ok{background:var(--green-bg);color:var(--green);}
  .form-msg.err{background:var(--red-bg);color:var(--red);}

  .card{background:var(--panel);border:1px solid var(--border);}
  table.appr{width:100%;border-collapse:collapse;font-size:13px;}
  table.appr th{text-align:left;background:#F7F8FA;color:var(--text-muted);font-weight:600;font-size:11.5px;text-transform:uppercase;letter-spacing:0.03em;padding:10px 12px;border-bottom:1px solid var(--border);}
  table.appr td{padding:10px 12px;border-bottom:1px solid var(--border-soft);vertical-align:top;}
  table.appr tr:hover{background:#FAFBFC;}
  .mono-cell{font-family:'JetBrains Mono',monospace;font-size:12px;color:var(--text-muted);}

  .badge{display:inline-block;font-size:11px;font-weight:600;padding:3px 9px;border-radius:20px;}
  .badge.pending{background:var(--amber-bg);color:var(--amber);}
  .badge.approved{background:var(--green-bg);color:var(--green);}
  .badge.rejected{background:var(--red-bg);color:var(--red);}
  .badge-sub{font-size:10.5px;color:var(--text-dim);margin-top:3px;}
  .badge-reason{font-size:10.5px;color:var(--red);margin-top:3px;font-style:italic;max-width:150px;}
  .badge-note{font-size:10.5px;color:var(--red);margin-top:2px;font-style:italic;}

  .action-btns{display:flex;gap:6px;flex-wrap:nowrap;white-space:nowrap;}
  .btn-review{background:#fff;color:var(--maroon);border:1px solid var(--maroon);border-radius:5px;padding:6px 10px;font-size:11px;font-weight:600;cursor:pointer;white-space:nowrap;}
  .btn-approve{background:var(--green);color:#fff;border:none;border-radius:5px;padding:6px 10px;font-size:11px;font-weight:600;cursor:pointer;white-space:nowrap;}
  .btn-reject{background:#fff;color:var(--red);border:1px solid var(--red);border-radius:5px;padding:6px 10px;font-size:11px;font-weight:600;cursor:pointer;white-space:nowrap;}
  .btn-download{background:var(--maroon);color:#fff;text-decoration:none;padding:6px 10px;border-radius:5px;font-size:11px;font-weight:600;display:inline-block;white-space:nowrap;}
  .waiting-note{font-size:11.5px;color:var(--text-dim);font-style:italic;}
  .empty-hint{padding:2.5rem;text-align:center;color:var(--text-dim);font-size:13px;}

  tr.data-row{cursor:pointer;}
  .chev{color:var(--text-dim);text-align:center;transition:transform .15s;}
  .chev.open{transform:rotate(90deg);}
  tr.detail-row td{background:#FAFBFC;padding:0;}
  .detail-box{display:none;padding:16px 20px;}
  .detail-box.open{display:block;}
  .detail-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:10px;margin-bottom:14px;}
  .stat-tile{background:#fff;border:1px solid var(--border);border-left:3px solid var(--maroon);border-radius:6px;padding:9px 11px;}
  .stat-label{font-size:10.5px;color:var(--text-dim);text-transform:uppercase;letter-spacing:0.04em;font-weight:600;margin-bottom:3px;}
  .stat-value{font-size:14px;color:var(--text);font-weight:700;}
  .detail-cat{font-weight:700;font-size:12px;text-transform:uppercase;letter-spacing:0.03em;color:var(--maroon);margin:10px 0 4px;}
  .detail-item{display:flex;justify-content:space-between;font-size:12.5px;padding:4px 0;color:var(--text-muted);border-bottom:1px solid var(--border-soft);}
  .detail-item b{color:var(--text);font-weight:600;}
  .detail-cols{display:grid;grid-template-columns:1fr 1fr;gap:0 24px;}
  .photo-thumbs{display:grid;grid-template-columns:repeat(auto-fit,minmax(110px,1fr));gap:8px;margin:8px 0 14px;}
  .photo-thumbs img{width:100%;height:90px;object-fit:cover;border-radius:5px;border:1px solid var(--border);}

</style>
</head>
<body>
<div class="wrap">
  <div class="topbar">
    <div class="topbar-left">
      <div class="logo-dot">&#9989;</div>
      <div class="topbar-title">Dashboard Approval</div>
    </div>
    <div class="topbar-right">
      <span><?php echo htmlspecialchars($current_user['full_name']); ?> &middot; <?php echo role_label($current_user['role']); ?></span>
      <?php if (in_array($current_user['role'], ['supervisor', 'manager'])): ?>
      <a href="batch_pdf.php">Batch Download PDF</a>
      <?php endif; ?>
      <a href="logout.php">Logout</a>
    </div>
  </div>

  <div class="content-pad">
    <div id="ajaxMsg" class="form-msg" style="display:none;"></div>

    <div style="display:flex;gap:10px;margin-bottom:14px;flex-wrap:wrap;">
      <input type="text" id="searchApproval" placeholder="Cari engine no, model..." style="flex:1;min-width:220px;background:#fff;border:1px solid var(--border);color:var(--text);border-radius:5px;padding:9px 12px;font-size:13px;">
      <select id="filterStatusApproval" style="background:#fff;border:1px solid var(--border);color:var(--text);border-radius:5px;padding:9px 12px;font-size:13px;">
        <option value="">Semua status</option>
        <option value="mine">Perlu tindakan saya</option>
        <option value="rejected">Ada yang ditolak</option>
        <option value="done">Sudah full approved</option>
      </select>
    </div>

    <div class="card">
      <table class="appr">
        <thead>
          <tr>
            <th>Tanggal</th>
            <th>Model</th>
            <th>Engine No.</th>
            <th>Generator No.</th>
            <th>Foreman</th>
            <th>Supervisor</th>
            <th>Manager</th>
            <th style="width:260px;">Aksi</th>
          </tr>
        </thead>
        <tbody id="apprBody">
          <?php if (empty($rows)): ?>
          <tr><td colspan="8"><div class="empty-hint">Belum ada checksheet yang masuk.</div></td></tr>
          <?php else: foreach ($rows as $r):
              $canAct = false;
              if ($myRole === 'foreman' && $r['foreman_status'] === 'pending') $canAct = true;
              elseif ($myRole === 'supervisor' && $r['foreman_status'] === 'approved' && $r['supervisor_status'] === 'pending') $canAct = true;
              elseif ($myRole === 'manager' && $r['supervisor_status'] === 'approved' && $r['manager_status'] === 'pending') $canAct = true;

              $items = json_decode($r['items'], true) ?: [];
              $photos = json_decode($r['photos'], true) ?: [];
              $filledPhotos = array_values(array_filter($photos));
              $rowId = 'row' . $r['id'];
          ?>
          <tr id="datarow-<?php echo $rowId; ?>"
              data-search="<?php echo e(strtolower($r['engine_no'].' '.$r['generator_no'].' '.$r['model'])); ?>"
              data-canact="<?php echo $canAct ? '1' : '0'; ?>"
              data-rejected="<?php echo ($r['foreman_status']==='rejected'||$r['supervisor_status']==='rejected'||$r['manager_status']==='rejected') ? '1' : '0'; ?>"
              data-done="<?php echo $r['manager_status']==='approved' ? '1' : '0'; ?>">
            <td class="mono-cell"><?php echo e($r['tanggal']); ?></td>
            <td><?php echo e($r['model']); ?></td>
            <td class="mono-cell"><?php echo e($r['engine_no']); ?></td>
            <td class="mono-cell"><?php echo e($r['generator_no']); ?></td>
            <td id="badge-foreman-<?php echo $rowId; ?>"><?php echo badge($r['foreman_status'], $r['foreman_by'], $r['foreman_at'], $r['foreman_reject_reason']); ?></td>
            <td id="badge-supervisor-<?php echo $rowId; ?>"><?php echo badge($r['supervisor_status'], $r['supervisor_by'], $r['supervisor_at'], $r['supervisor_reject_reason']); ?></td>
            <td id="badge-manager-<?php echo $rowId; ?>"><?php echo badge($r['manager_status'], $r['manager_by'], $r['manager_at'], $r['manager_reject_reason']); ?></td>
            <td id="action-<?php echo $rowId; ?>">
              <div class="action-btns">
                <button type="button" class="btn-review" onclick="toggleDetail('<?php echo $rowId; ?>')">Review</button>
                <?php if ($canAct): ?>
                <button type="button" class="btn-approve" onclick="doApprove(<?php echo $r['id']; ?>, '<?php echo $rowId; ?>')">Approve</button>
                <button type="button" class="btn-reject" onclick="doReject(<?php echo $r['id']; ?>, '<?php echo $rowId; ?>')">Reject</button>
                <?php endif; ?>
              </div>
            </td>
          </tr>
          <tr class="detail-row">
            <td colspan="8">
              <div class="detail-box" id="detail-<?php echo $rowId; ?>">

                <div class="detail-grid">
                  <div class="stat-tile"><div class="stat-label">Destination</div><div class="stat-value"><?php echo e($r['destination']); ?></div></div>
                  <div class="stat-tile"><div class="stat-label">Voltage</div><div class="stat-value"><?php echo e($r['voltage']); ?></div></div>
                  <div class="stat-tile"><div class="stat-label">Frequency</div><div class="stat-value"><?php echo e($r['frequency']); ?></div></div>
                  <div class="stat-tile"><div class="stat-label">Engine Model</div><div class="stat-value"><?php echo e($r['engine_model']); ?></div></div>
                  <div class="stat-tile"><div class="stat-label">Frame No.</div><div class="stat-value"><?php echo e($r['frame_no']); ?></div></div>
                </div>

                <?php if (!empty($r['remarks'])): ?>
                <div style="color:var(--text-muted);margin-bottom:12px;font-size:13px;">Remarks: <span style="color:var(--text);"><?php echo nl2br(e($r['remarks'])); ?></span></div>
                <?php endif; ?>

                <?php if (!empty($r['last_edited_by_name'])): ?>
                <div style="color:var(--amber);margin-bottom:12px;font-size:12px;font-style:italic;">
                  &#9998; Terakhir diedit oleh <?php echo e($r['last_edited_by_name']); ?> &middot; <?php echo date('d/m/y H:i', strtotime($r['last_edited_at'])); ?>
                </div>
                <?php endif; ?>

                <div class="detail-cat" style="margin-top:0;">Checklist Pemeriksaan</div>
                <div class="detail-cols">
                  <div>
                  <?php $half = ceil(count($categories) / 2); $i = 0; foreach ($categories as $catNo => $cat): $i++; if ($i > $half) continue; ?>
                    <div class="detail-cat"><?php echo $catNo . '. ' . strtoupper($cat['name']); ?></div>
                    <?php foreach ($cat['items'] as $item):
                        $v = $items[$item['item_code']] ?? '';
                        $txt = $v === 'ok' ? 'OK' : ($v === 'dash' ? '-' : '(kosong)');
                    ?>
                      <div class="detail-item"><span><?php echo e($item['item_label']); ?></span><b><?php echo $txt; ?></b></div>
                    <?php endforeach; ?>
                  <?php endforeach; ?>
                  </div>
                  <div>
                  <?php $i = 0; foreach ($categories as $catNo => $cat): $i++; if ($i <= $half) continue; ?>
                    <div class="detail-cat"><?php echo $catNo . '. ' . strtoupper($cat['name']); ?></div>
                    <?php foreach ($cat['items'] as $item):
                        $v = $items[$item['item_code']] ?? '';
                        $txt = $v === 'ok' ? 'OK' : ($v === 'dash' ? '-' : '(kosong)');
                    ?>
                      <div class="detail-item"><span><?php echo e($item['item_label']); ?></span><b><?php echo $txt; ?></b></div>
                    <?php endforeach; ?>
                  <?php endforeach; ?>
                  </div>
                </div>

                <?php if (count($filledPhotos) > 0): ?>
                <div class="detail-cat">Foto Dokumentasi</div>
                <div class="photo-thumbs">
                  <?php foreach ($filledPhotos as $p): ?>
                    <img src="<?php echo e($p); ?>">
                  <?php endforeach; ?>
                </div>
                <?php endif; ?>

              </div>
            </td>
          </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
      <div id="apprPagination" style="display:flex;align-items:center;justify-content:space-between;padding:14px 20px;flex-wrap:wrap;gap:10px;"></div>
    </div>
  </div>
</div>
<script>
function toggleDetail(rowId){
  document.getElementById('detail-'+rowId).classList.toggle('open');
}

const searchBox = document.getElementById('searchApproval');
const filterSel = document.getElementById('filterStatusApproval');

const APPR_PAGE_SIZE = 50;
let apprCurrentPage = 1;

function applyApprovalFilter(){
  const term = searchBox.value.trim().toLowerCase();
  const status = filterSel.value;
  const rows = document.querySelectorAll('#apprBody tr[id^="datarow-"]');
  const matched = [];

  rows.forEach(row=>{
    let match = true;
    if (term && !row.dataset.search.includes(term)) match = false;
    if (status === 'mine' && row.dataset.canact !== '1') match = false;
    if (status === 'rejected' && row.dataset.rejected !== '1') match = false;
    if (status === 'done' && row.dataset.done !== '1') match = false;
    if (match) matched.push(row);
  });

  const totalPages = Math.max(1, Math.ceil(matched.length / APPR_PAGE_SIZE));
  if (apprCurrentPage > totalPages) apprCurrentPage = totalPages;
  const startIdx = (apprCurrentPage - 1) * APPR_PAGE_SIZE;
  const pageRows = matched.slice(startIdx, startIdx + APPR_PAGE_SIZE);

  rows.forEach(row=>{
    const show = pageRows.includes(row);
    row.style.display = show ? '' : 'none';
    const detailRow = row.nextElementSibling;
    if (detailRow && detailRow.classList.contains('detail-row')) {
      detailRow.style.display = show ? '' : 'none';
    }
  });

  renderApprPagination(matched.length, totalPages);
}

function renderApprPagination(totalItems, totalPages){
  const container = document.getElementById('apprPagination');
  if(!container) return;
  if(totalItems === 0){
    container.innerHTML = '<span style="font-size:12.5px;color:var(--text-dim);">Tidak ada data yang cocok.</span>';
    return;
  }
  const startIdx = (apprCurrentPage - 1) * APPR_PAGE_SIZE + 1;
  const endIdx = Math.min(apprCurrentPage * APPR_PAGE_SIZE, totalItems);
  container.innerHTML =
    '<span style="font-size:12.5px;color:var(--text-muted);">Menampilkan '+startIdx+'-'+endIdx+' dari '+totalItems+' data</span>'+
    '<div style="display:flex;gap:6px;">'+
      '<button type="button" class="btn-review" id="btnApprPrev" '+(apprCurrentPage<=1?'disabled':'')+'>&larr; Sebelumnya</button>'+
      '<span style="padding:6px 10px;font-size:12.5px;color:var(--text-muted);">Halaman '+apprCurrentPage+' / '+totalPages+'</span>'+
      '<button type="button" class="btn-review" id="btnApprNext" '+(apprCurrentPage>=totalPages?'disabled':'')+'>Selanjutnya &rarr;</button>'+
    '</div>';

  const prevBtn = document.getElementById('btnApprPrev');
  const nextBtn = document.getElementById('btnApprNext');
  if(prevBtn) prevBtn.onclick = ()=>{ apprCurrentPage--; applyApprovalFilter(); };
  if(nextBtn) nextBtn.onclick = ()=>{ apprCurrentPage++; applyApprovalFilter(); };
}

if (searchBox) {
  searchBox.addEventListener('input', ()=>{ apprCurrentPage = 1; applyApprovalFilter(); });
  filterSel.addEventListener('change', ()=>{ apprCurrentPage = 1; applyApprovalFilter(); });
}
applyApprovalFilter();

const MY_ROLE = '<?php echo $myRole; ?>';

function badgeHtml(status, by, at, reason){
  if(status === 'approved'){
    const sub = by ? (by + (at ? ' &middot; ' + at : '')) : '';
    return '<span class="badge approved">Approved</span><div class="badge-sub">'+sub+'</div>';
  }
  if(status === 'rejected'){
    const sub = by ? (by + (at ? ' &middot; ' + at : '')) : '';
    const reasonHtml = reason ? '<div class="badge-reason">"'+reason+'"</div>' : '';
    return '<span class="badge rejected">Rejected</span><div class="badge-sub">'+sub+'</div>'+reasonHtml;
  }
  return '<span class="badge pending">Pending</span>';
}

function showAjaxMsg(text, type){
  const box = document.getElementById('ajaxMsg');
  box.textContent = text;
  box.className = 'form-msg ' + type;
  box.style.display = 'block';
  setTimeout(()=>{ box.style.display = 'none'; }, 4000);
}

async function sendApproval(entryId, rowId, action, reason){
  try{
    const res = await fetch('process_approve.php', {
      method: 'POST',
      headers: {'Content-Type':'application/json'},
      body: JSON.stringify({ entry_id: entryId, action: action, reason: reason || '' })
    });
    const result = await res.json();
    if(result.status !== 'ok'){
      showAjaxMsg(result.message || 'Gagal memproses.', 'err');
      return;
    }

    // Update badge role yang bersangkutan, tanpa reload
    document.getElementById('badge-'+MY_ROLE+'-'+rowId).innerHTML = badgeHtml(result.action, result.by, result.at, result.reason);

    // Hilangkan tombol Approve/Reject, sisain Review aja
    const actionCell = document.getElementById('action-'+rowId);
    actionCell.innerHTML = '<div class="action-btns"><button type="button" class="btn-review" onclick="toggleDetail(\''+rowId+'\')">Review</button></div>';

    showAjaxMsg('Checksheet berhasil ' + (action === 'approve' ? 'disetujui' : 'ditolak') + '.', 'ok');
  }catch(e){
    showAjaxMsg('Terjadi kesalahan koneksi.', 'err');
  }
}

function doApprove(entryId, rowId){
  if(!confirm('Yakin approve checksheet ini?')) return;
  sendApproval(entryId, rowId, 'approve');
}

function doReject(entryId, rowId){
  const reason = prompt('Alasan reject (wajib diisi):');
  if(reason === null) return;
  if(reason.trim() === ''){
    alert('Alasan reject tidak boleh kosong.');
    return;
  }
  sendApproval(entryId, rowId, 'reject', reason.trim());
}
</script>
</body>
</html>