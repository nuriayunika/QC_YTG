<?php
require 'auth.php';
require_role(['operator']);
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Final Check Sheet Generator Set</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@500;600&display=swap" rel="stylesheet">
<style>
  :root{
    --maroon:#7B2334;
    --maroon-dark:#5E1A27;
    --maroon-tint:#F7E9EB;
    --blue:#2F6FED;
    --bg:#EEF0F3;
    --panel:#FFFFFF;
    --border:#E1E4E9;
    --border-soft:#EEF0F3;
    --text:#23262B;
    --text-muted:#6B7280;
    --text-dim:#9CA3AF;
    --green:#1F9D55;
    --green-bg:#E7F7EE;
    --amber:#B7791F;
    --amber-bg:#FBF3E1;
  }
  *{box-sizing:border-box;}
  body{margin:0;background:var(--bg);color:var(--text);font-family:'Inter',sans-serif;}
  .wrap{width:100%;margin:0;padding:0 0 3rem;}

  .topbar{
    background:var(--maroon);
    display:flex;align-items:center;justify-content:space-between;
    padding:10px 20px;gap:16px;flex-wrap:wrap;
  }
  .topbar-left{display:flex;align-items:center;gap:10px;flex-wrap:wrap;}
  .logo-dot{width:34px;height:34px;border-radius:6px;background:rgba(255,255,255,0.12);display:flex;align-items:center;justify-content:center;color:#fff;font-size:16px;}
  .tab-btn{
    background:rgba(255,255,255,0.1);border:none;color:#F1D9DD;
    font-weight:600;font-size:13px;padding:9px 16px;border-radius:6px;cursor:pointer;
  }
  .tab-btn.active{background:var(--blue);color:#fff;}
  .topbar-right{display:flex;align-items:center;gap:10px;color:#F1D9DD;font-size:12px;}

  .section-head{
    background:var(--maroon);color:#fff;
    padding:12px 20px;font-weight:700;font-size:15px;letter-spacing:0.02em;
    display:flex;align-items:center;gap:8px;
  }
  .content-pad{padding:20px;}

  .card{background:var(--panel);margin:0 0 18px;border:1px solid var(--border);}
  .card-body{padding:18px 20px;}

  .field-row{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:16px 20px;margin-bottom:16px;}
  .field-row:last-child{margin-bottom:0;}
  .field label{display:block;font-size:12.5px;color:var(--text-muted);margin-bottom:6px;font-weight:500;}
  .field input, .field select, .field textarea{
    width:100%;background:#fff;border:1px solid var(--border);color:var(--text);
    border-radius:5px;padding:9px 11px;font-family:'Inter',sans-serif;font-size:14px;
  }
  .field input:focus, .field select:focus, .field textarea:focus{outline:none;border-color:var(--blue);}
  .field input:disabled{color:var(--text-dim);background:#F5F6F8;}
  .field textarea{resize:vertical;}

  .panel-toolbar{display:flex;align-items:center;justify-content:space-between;padding:12px 20px;background:var(--maroon);color:#fff;}
  .panel-toolbar-title{display:flex;align-items:center;gap:8px;font-weight:700;font-size:15px;}
  .panel-toolbar-count{font-size:12.5px;color:#F1D9DD;}

  table.checklist{width:100%;border-collapse:collapse;font-size:14px;}
  table.checklist th{
    text-align:left;background:#F7F8FA;color:var(--text-muted);font-weight:600;font-size:12.5px;
    text-transform:uppercase;letter-spacing:0.03em;padding:11px 14px;border-bottom:1px solid var(--border);
  }
  table.checklist td{padding:11px 14px;border-bottom:1px solid var(--border-soft);vertical-align:middle;}
  table.checklist tr.cat-row td{
    background:var(--maroon-tint);color:var(--maroon-dark);font-weight:700;font-size:12.5px;
    text-transform:uppercase;letter-spacing:0.03em;padding:9px 14px;
  }
  table.checklist tr.item-row:hover{background:#FAFBFC;}
  .col-no{width:44px;color:var(--text-dim);font-family:'JetBrains Mono',monospace;font-size:12.5px;}
  .col-hasil{width:140px;}
  .col-foto{width:220px;}
  .hasil-select{
    width:100%;background:#fff;border:1px solid var(--border);border-radius:5px;
    padding:7px 9px;font-size:13px;color:var(--text);font-weight:500;
  }
  .hasil-select.val-ok{border-color:var(--green);color:var(--green);background:var(--green-bg);}
  .hasil-select.val-dash{border-color:var(--text-dim);color:var(--text-muted);background:#F5F6F8;}

  .file-cell{display:flex;align-items:center;gap:8px;}
  .file-btn{
    background:#F5F6F8;border:1px solid var(--border);border-radius:5px;
    padding:6px 10px;font-size:12px;color:var(--text-muted);cursor:pointer;font-weight:500;white-space:nowrap;
  }
  .file-btn:hover{border-color:var(--blue);color:var(--blue);}
  .file-name{font-size:12px;color:var(--text-dim);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;flex:1;}
  .file-thumb{width:30px;height:30px;object-fit:cover;border-radius:4px;border:1px solid var(--border);display:none;}
  input[type=file]{display:none;}

  .bottom-bar{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:16px 20px;flex-wrap:wrap;}
  .progress-note{font-size:12.5px;color:var(--text-dim);font-family:'JetBrains Mono',monospace;}
  .form-msg{font-size:13px;color:var(--text-muted);}
  .form-msg.err{color:#C0362C;}
  .form-msg.ok{color:var(--green);}
  .btn-row{display:flex;gap:10px;}
  .btn-primary{background:var(--maroon);color:#fff;border:none;border-radius:6px;font-weight:600;font-size:13.5px;padding:11px 22px;cursor:pointer;}
  .btn-primary:hover{background:var(--maroon-dark);}
  .btn-primary:disabled{opacity:0.5;cursor:not-allowed;}
  .btn-ghost{background:#fff;color:var(--text-muted);border:1px solid var(--border);border-radius:6px;font-weight:600;font-size:13px;padding:10px 16px;cursor:pointer;}
  .btn-ghost:hover{border-color:var(--maroon);color:var(--maroon);}

  .hist-filters{display:flex;gap:10px;margin-bottom:14px;flex-wrap:wrap;}
  .hist-filters select{background:#fff;border:1px solid var(--border);color:var(--text);border-radius:5px;padding:9px 12px;font-size:13px;}
  table.hist{width:100%;border-collapse:collapse;font-size:13.5px;}
  table.hist th{text-align:left;background:#F7F8FA;color:var(--text-muted);font-weight:600;font-size:12px;text-transform:uppercase;letter-spacing:0.03em;padding:11px 14px;border-bottom:1px solid var(--border);}
  table.hist td{padding:11px 14px;border-bottom:1px solid var(--border-soft);}
  table.hist tr.hrow{cursor:pointer;}
  table.hist tr.hrow:hover{background:#FAFBFC;}
  .badge{display:inline-block;font-size:11.5px;font-weight:600;padding:4px 10px;border-radius:20px;}
  .badge.lengkap{background:var(--green-bg);color:var(--green);}
  .badge.belum{background:var(--amber-bg);color:var(--amber);}
  .mono-cell{font-family:'JetBrains Mono',monospace;font-size:12.5px;color:var(--text-muted);}
  .empty-hint{padding:2.5rem;text-align:center;color:var(--text-dim);font-size:13px;}

  .toast{
    position:fixed;top:16px;left:50%;transform:translateX(-50%) translateY(-20px);
    background:var(--green);color:#fff;padding:12px 22px;border-radius:8px;
    font-size:13.5px;font-weight:600;box-shadow:0 4px 16px rgba(0,0,0,0.15);
    opacity:0;pointer-events:none;transition:opacity .25s, transform .25s;z-index:1000;
  }
  .toast.show{opacity:1;transform:translateX(-50%) translateY(0);}
  .chev{color:var(--text-dim);}

  .detail-row td{background:#FAFBFC;padding:0;}
  .detail-box{padding:16px 20px;display:none;}
  .detail-box.open{display:block;}
  .detail-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(130px,1fr));gap:10px;margin-bottom:14px;}
  .detail-grid .stat-tile{background:#fff;border:1px solid var(--border);border-left:3px solid var(--maroon);border-radius:6px;padding:10px 12px;}
  .detail-grid .stat-label{font-size:10.5px;color:var(--text-dim);text-transform:uppercase;letter-spacing:0.04em;font-weight:600;margin-bottom:4px;}
  .detail-grid .stat-value{font-size:15px;color:var(--text);font-weight:700;}
  .detail-cat{font-weight:700;font-size:12px;text-transform:uppercase;letter-spacing:0.03em;color:var(--maroon);margin:12px 0 4px;}
  .detail-item{display:flex;justify-content:space-between;align-items:center;font-size:12.5px;padding:5px 0;color:var(--text-muted);border-bottom:1px solid var(--border-soft);gap:10px;}
  .detail-item b{color:var(--text);font-weight:600;}
  .detail-item img{width:34px;height:34px;object-fit:cover;border-radius:4px;border:1px solid var(--border);}

  .photo-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:14px;}
  .photo-slot{background:#F9FAFB;border:1px dashed var(--border);border-radius:6px;padding:12px;text-align:center;}
  .photo-slot-label{font-size:12px;color:var(--text-muted);font-weight:600;margin-bottom:8px;}
  .photo-slot input[type="file"]{display:none;}
  .photo-preview{width:100%;height:110px;object-fit:cover;border-radius:5px;margin-bottom:8px;display:none;border:1px solid var(--border);}
  .photo-slot.filled .photo-preview{display:block;}
  .photo-clear{font-size:11px;color:#C0362C;background:none;border:none;cursor:pointer;margin-top:6px;display:none;text-decoration:underline;}
  .photo-slot.filled .photo-clear{display:inline-block;}

  .photo-thumbs{display:grid;grid-template-columns:repeat(auto-fit,minmax(110px,1fr));gap:8px;margin:8px 0 12px;}
  .photo-thumbs img{width:100%;height:90px;object-fit:cover;border-radius:5px;border:1px solid var(--border);}
</style>
</head>
<body>
<div class="wrap">

  <div id="toast" class="toast"></div>

  <div class="topbar">
    <div class="topbar-left">
      <div class="logo-dot">&#128203;</div>
      <button class="tab-btn active" data-tab="input">Isi Checksheet</button>
      <button class="tab-btn" data-tab="hist">Histori</button>
    </div>
    <div class="topbar-right">
      <span style="margin-right:12px;"><?php echo htmlspecialchars($current_user['full_name']); ?> &middot; <?php echo role_label($current_user['role']); ?></span>
      <a href="logout.php" style="color:#F1D9DD;text-decoration:none;background:rgba(255,255,255,0.1);padding:7px 14px;border-radius:6px;font-size:12.5px;font-weight:600;">Logout</a>
    </div>
  </div>

  <div id="tab-input">
    <div class="content-pad">

      <div class="card">
        <div class="section-head">&#9776; General Checksheet</div>
        <div class="card-body">
          <div class="field-row">
            <div class="field"><label>Date of Report</label><input id="f_tanggal" type="date" disabled></div>
            <div class="field"><label>Model *</label><select id="f_model"><option value="">- Pilih Model -</option></select></div>
            <div class="field"><label>Voltage *</label>
              <select id="f_voltage"><option value="">- Pilih Voltage -</option><option>110V / 220V</option><option>127V / 220V</option><option>220V / 380V</option></select>
            </div>
            <div class="field"><label>Frequency *</label>
              <select id="f_freq"><option value="">- Pilih Frequency -</option><option>50Hz</option><option>60Hz</option></select>
            </div>
          </div>
          <div class="field-row">
            <div class="field"><label>Destination *</label><input id="f_dest" placeholder="Tujuan pengiriman"></div>
            <div class="field"><label>Engine Model</label><input id="f_engine_model" placeholder="Otomatis dari Model" disabled></div>
            <div class="field"><label>Engine No. *</label><input id="f_engine_no" placeholder="Ketik No. Mesin..."></div>
            <div class="field"><label>Generator No. *</label><input id="f_gen_no" placeholder="Ketik No. Generator..."></div>
          </div>
          <div class="field-row">
            <div class="field"><label>Frame No. *</label><input id="f_frame_no" placeholder="Nomor frame"></div>
            <div class="field" style="grid-column:span 3;"><label>Remarks</label><textarea id="f_remarks" rows="1" placeholder="Catatan tambahan (opsional)..."></textarea></div>
          </div>
        </div>
      </div>

      <div class="card">
        <div class="panel-toolbar">
          <div class="panel-toolbar-title">&#9989; Checklist Pemeriksaan</div>
          <div class="panel-toolbar-count" id="itemCount">49 item checklist</div>
        </div>
        <table class="checklist">
          <thead>
            <tr><th style="width:44px;">#</th><th>Item</th><th class="col-hasil">Hasil</th></tr>
          </thead>
          <tbody id="checklistBody"></tbody>
        </table>
      </div>

      <div class="card">
        <div class="section-head">&#128247; Foto Dokumentasi *</div>
        <div class="card-body">
          <div class="photo-grid" id="photoGrid"></div>
        </div>
      </div>

      <div class="card">
        <div class="bottom-bar">
          <div>
            <div class="progress-note" id="progressNote"></div>
            <div class="form-msg" id="formMsg"></div>
          </div>
          <div class="btn-row">
            <button class="btn-primary" id="btnSubmit">Simpan Checksheet</button>
          </div>
        </div>
      </div>

    </div>
  </div>

  <div id="tab-hist" style="display:none;">
    <div class="content-pad">
      <div class="hist-filters">
        <select id="filterModel"><option value="">Semua model</option></select>
        <input type="text" id="searchHist" placeholder="Cari engine no..." style="flex:1;min-width:220px;background:#fff;border:1px solid var(--border);color:var(--text);border-radius:5px;padding:9px 12px;font-size:13px;">
      </div>
      <div class="card">
        <table class="hist">
          <thead><tr><th>Tanggal</th><th>Model</th><th>Engine No.</th><th>Generator No.</th><th>Destination</th><th></th></tr></thead>
          <tbody id="histBody"></tbody>
        </table>
        <div id="histEmpty"></div>
      </div>
    </div>
  </div>

</div>

<script>
const MODEL_MAP = {
  "YTG 5.0 SE":"TF90M-E2GN","YTG 5.0 SM":"TF90M-GN",
  "YTG 6.5 SE":"TF120M-E2GN","YTG 6.5 SM":"TF120M-GN",
  "YTG 6.5 TE":"TF90M-E2GN","YTG 6.5 TM":"TF90M-GN",
  "YTG 10 SE":"TF160-E2GN","YTG 10 SM":"TF160-GN",
  "YTG 9 TE":"TF120M-E2GN","YTG 9 TM":"TF120M-GN",
  "YTG 12.5 TE":"TF160-E2GN","YTG 12.5 TM":"TF160-GN",
  "YTG 15 TE":"TS190R2-EGN","YTG 15 TM":"TS190R2-GN"
};

const CHECKLIST = [
  {no:1, title:"Welding", items:["Common Bed","Frame"]},
  {no:2, title:"Painting", items:["Common Bed","Frame"]},
  {no:3, title:"Engine Mounting", items:["Tightening Bolt 250 KGF.M","Engine Mounting"]},
  {no:4, title:"Tightening Bolt", items:["Engine Bolt","Generator Bolt","Stoper Bolt"]},
  {no:5, title:"Panel Box", items:["Tightening Bolt","Amp Meter","Volt Meter","No Fuse Bracker","Pilot Lamp","Output Terminal","Sticker"]},
  {no:6, title:"Cover Belt", items:["Tightening Bolt","Sticker Starter-Electric","Caution Sticker"]},
  {no:7, title:"Starting", items:["Manual","Cable Electric","Electric"]},
  {no:8, title:"Sticker", items:["Engine Sticker","Generator Sticker","Caution V-Belt","Caution Electric","Caution Engine"]},
  {no:9, title:"Tool Set", items:["Fuel Oil Filter (1 Pc)","Cogged V-Belt Radiator (1 Pc)","Wrench 10-12 (1 Pc)","Wrench 14-17 (1 Pc)","Wrench 19-22 (1 Pc)","Driver (-) (+) (1 Pc)","Manual Book Altern (1 Pc)","Manual Book Engine (1 Pc)"]},
  {no:10, title:"Accessories", items:["Hex Bolt M10 x 20 (16 Pcs)","Spring Washer (16 Pcs)","Flange Washer (16 Pcs)","Wing Nut M10 (2 Pcs)","Rubber Wheel (4 Pcs)","Bolt Clamp Accu (2 Pcs)","Clamp Accu (1 Pc)","Cup YTG (1 Pc)","Jacket (1 Pc)"]},
  {no:11, title:"Alternator", items:["Cutting Merk Mecc Alte"]},
  {no:12, title:"V-Belt Engine", items:["Perpendicularity"]},
  {no:13, title:"Tension Belt", items:["Inside","Middle","Outside"]}
];

const state = {};
let totalItemCount = 0;
CHECKLIST.forEach(c=> totalItemCount += c.items.length);
document.getElementById('itemCount').textContent = totalItemCount + ' item checklist';

function buildChecklistTable(){
  const body = document.getElementById('checklistBody');
  CHECKLIST.forEach(cat=>{
    const catRow = document.createElement('tr');
    catRow.className = 'cat-row';
    catRow.innerHTML = '<td colspan="3">'+cat.no+'. '+cat.title+'</td>';
    body.appendChild(catRow);

    cat.items.forEach((label,i)=>{
      const id = cat.no+'-'+(i+1);
      state[id] = '';
      const tr = document.createElement('tr');
      tr.className = 'item-row';

      const tdNo = document.createElement('td');
      tdNo.className = 'col-no';
      tdNo.textContent = (i+1);
      tr.appendChild(tdNo);

      const tdItem = document.createElement('td');
      tdItem.textContent = label;
      tr.appendChild(tdItem);

      const tdHasil = document.createElement('td');
      tdHasil.className = 'col-hasil';
      const sel = document.createElement('select');
      sel.className = 'hasil-select';
      sel.innerHTML = '<option value="">- Pilih -</option><option value="ok">OK</option><option value="dash">-</option>';
      sel.onchange = ()=>{
        state[id] = sel.value;
        sel.classList.remove('val-ok','val-dash');
        if(sel.value==='ok') sel.classList.add('val-ok');
        if(sel.value==='dash') sel.classList.add('val-dash');
        updateProgress();
      };
      sel.dataset.item = id;
      tdHasil.appendChild(sel);
      tr.appendChild(tdHasil);

      body.appendChild(tr);
    });
  });
}
buildChecklistTable();

const photos = [null,null,null,null,null];
function buildPhotoSlots(){
  const grid = document.getElementById('photoGrid');
  for(let i=0;i<5;i++){
    const slot = document.createElement('div');
    slot.className = 'photo-slot';
    slot.innerHTML =
      '<div class="photo-slot-label">Foto '+(i+1)+'</div>'+
      '<img class="photo-preview" id="preview_'+i+'">'+
      '<input type="file" accept="image/*" id="file_'+i+'">'+
      '<button type="button" class="file-btn" id="pickbtn_'+i+'" style="width:100%;">Pilih Gambar</button>'+
      '<button type="button" class="photo-clear" id="clearbtn_'+i+'">Hapus</button>';
    grid.appendChild(slot);

    const fileInput = slot.querySelector('#file_'+i);
    const pickBtn = slot.querySelector('#pickbtn_'+i);
    const clearBtn = slot.querySelector('#clearbtn_'+i);
    const preview = slot.querySelector('#preview_'+i);

    pickBtn.onclick = ()=> fileInput.click();
    fileInput.onchange = (e)=>{
      const file = e.target.files[0];
      if(!file) return;
      photos[i] = file;
      preview.src = URL.createObjectURL(file);
      slot.classList.add('filled');
    };
    clearBtn.onclick = ()=>{
      photos[i] = null;
      fileInput.value = '';
      preview.src = '';
      slot.classList.remove('filled');
    };
  }
}
buildPhotoSlots();

function updateProgress(){
  const keys = Object.keys(state);
  const filled = keys.filter(k=>state[k]).length;
  document.getElementById('progressNote').textContent = filled+' / '+keys.length+' item terisi';
}
updateProgress();

const modelSel = document.getElementById('f_model');
Object.keys(MODEL_MAP).forEach(m=>{
  const opt = document.createElement('option');
  opt.value = m; opt.textContent = m;
  modelSel.appendChild(opt);
});
modelSel.onchange = ()=>{
  document.getElementById('f_engine_model').value = MODEL_MAP[modelSel.value] || '';
};

const today = new Date();
today.setMinutes(today.getMinutes() - today.getTimezoneOffset());
document.getElementById('f_tanggal').value = today.toISOString().slice(0,10);

document.querySelectorAll('.tab-btn').forEach(btn=>{
  btn.onclick = ()=>{
    document.querySelectorAll('.tab-btn').forEach(b=>b.classList.remove('active'));
    btn.classList.add('active');
    document.getElementById('tab-input').style.display = btn.dataset.tab==='input' ? 'block':'none';
    document.getElementById('tab-hist').style.display = btn.dataset.tab==='hist' ? 'block':'none';
    if(btn.dataset.tab==='hist') loadHistory();
  };
});

document.getElementById('btnSubmit').onclick = async ()=>{
  const msg = document.getElementById('formMsg');

  const headerFields = [
    ['f_model','Model'],
    ['f_voltage','Voltage'],
    ['f_freq','Frequency'],
    ['f_dest','Destination'],
    ['f_engine_no','Engine No.'],
    ['f_gen_no','Generator No.'],
    ['f_frame_no','Frame No.']
  ];
  const missing = [];
  headerFields.forEach(([id,label])=>{
    if(!document.getElementById(id).value.trim()) missing.push(label);
  });
  const unfilledItems = Object.values(state).filter(v=>!v).length;
  if(unfilledItems > 0) missing.push(unfilledItems+' item checklist belum dipilih Hasilnya');
  const unfilledPhotos = photos.filter(p=>!p).length;
  if(unfilledPhotos > 0) missing.push(unfilledPhotos+' foto dokumentasi belum diunggah');

  if(missing.length > 0){
    msg.textContent = 'Belum lengkap: ' + missing.join(', ') + '.';
    msg.className = 'form-msg err';
    return;
  }

  const model = document.getElementById('f_model').value;
  const engineNo = document.getElementById('f_engine_no').value.trim();
  const genNo = document.getElementById('f_gen_no').value.trim();
  const tanggal = document.getElementById('f_tanggal').value;

  const formData = new FormData();
  formData.append('tanggal', tanggal);
  formData.append('model', model);
  formData.append('voltage', document.getElementById('f_voltage').value);
  formData.append('frequency', document.getElementById('f_freq').value);
  formData.append('destination', document.getElementById('f_dest').value.trim());
  formData.append('engine_model', document.getElementById('f_engine_model').value);
  formData.append('engine_no', engineNo);
  formData.append('generator_no', genNo);
  formData.append('frame_no', document.getElementById('f_frame_no').value.trim());
  formData.append('remarks', document.getElementById('f_remarks').value.trim());
  formData.append('items', JSON.stringify(state));
  photos.forEach((file, i)=>{
    if(file) formData.append('photo_' + (i+1), file);
  });

  const btn = document.getElementById('btnSubmit');
  btn.disabled = true;
  try{
    const res = await fetch('save_checksheet.php', {
      method: 'POST',
      body: formData
    });
    const result = await res.json();
    if(!res.ok || result.error) throw new Error(result.error || 'gagal simpan');
    msg.textContent = '';
    resetForm();
    window.scrollTo({ top: 0, behavior: 'smooth' });
    showToast('Checksheet tersimpan ke database.');
  }catch(e){
    msg.textContent = 'Gagal menyimpan data: ' + e.message;
    msg.className = 'form-msg err';
  }finally{
    btn.disabled = false;
  }
};

let allEntries = [];

async function loadHistory(){
  const body = document.getElementById('histBody');
  const empty = document.getElementById('histEmpty');
  body.innerHTML = '';
  empty.innerHTML = '<div class="empty-hint">Memuat data...</div>';
  try{
    const res = await fetch('list_checksheet.php');
    const entries = await res.json();
    if(entries.error) throw new Error(entries.error);
    if(entries.length === 0){
      empty.innerHTML = '<div class="empty-hint">Belum ada checksheet yang diisi.</div>';
      allEntries = [];
      return;
    }
    allEntries = entries;

    const modelSet = [...new Set(entries.map(e=>e.model).filter(Boolean))];
    const fSel = document.getElementById('filterModel');
    fSel.innerHTML = '<option value="">Semua model</option>' + modelSet.map(m=>'<option value="'+escapeHtml(m)+'">'+escapeHtml(m)+'</option>').join('');

    renderHistory();
  }catch(e){
    empty.innerHTML = '<div class="empty-hint">Gagal memuat histori: '+escapeHtml(e.message)+'</div>';
  }
}

function escapeHtml(s){
  return String(s).replace(/[&<>"']/g, c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
}

function renderHistory(){
  const body = document.getElementById('histBody');
  const empty = document.getElementById('histEmpty');
  const fm = document.getElementById('filterModel').value;
  const searchTerm = document.getElementById('searchHist').value.trim().toLowerCase();
  const filtered = allEntries.filter(e => {
    if (fm && e.model !== fm) return false;
    if (searchTerm) {
      const haystack = (e.engine_no||'').toString().toLowerCase();
      if (!haystack.includes(searchTerm)) return false;
    }
    return true;
  });
  body.innerHTML = '';
  if(filtered.length === 0){
    empty.innerHTML = '<div class="empty-hint">Tidak ada data yang cocok dengan filter.</div>';
    return;
  }
  empty.innerHTML = '';
  filtered.forEach(e=>{
    const row = document.createElement('tr');
    row.className = 'hrow';
    row.innerHTML =
      '<td class="mono-cell">'+escapeHtml(e.tanggal||'-')+'</td>'+
      '<td>'+escapeHtml(e.model||'-')+'</td>'+
      '<td class="mono-cell">'+escapeHtml(e.engine_no||'-')+'</td>'+
      '<td class="mono-cell">'+escapeHtml(e.generator_no||'-')+'</td>'+
      '<td>'+escapeHtml(e.destination||'-')+'</td>'+
      '<td class="chev">&#8250;</td>';

    const detailRow = document.createElement('tr');
    detailRow.className = 'detail-row';
    const detailTd = document.createElement('td');
    detailTd.colSpan = 6;
    const detail = document.createElement('div');
    detail.className = 'detail-box';
    let itemsHtml = '';
    CHECKLIST.forEach(cat=>{
      itemsHtml += '<div class="detail-cat">'+cat.no+'. '+cat.title+'</div>';
      cat.items.forEach((label,i)=>{
        const id = cat.no+'-'+(i+1);
        const v = e.items ? e.items[id] : null;
        const txt = v==='ok' ? 'OK' : v==='dash' ? '-' : '(belum diisi)';
        itemsHtml += '<div class="detail-item"><span>'+label+'</span><b>'+txt+'</b></div>';
      });
    });
    detail.innerHTML =
      '<div class="detail-grid">'+
      '<div class="stat-tile"><div class="stat-label">Voltage</div><div class="stat-value">'+escapeHtml(e.voltage||'-')+'</div></div>'+
      '<div class="stat-tile"><div class="stat-label">Frequency</div><div class="stat-value">'+escapeHtml(e.frequency||'-')+'</div></div>'+
      '<div class="stat-tile"><div class="stat-label">Engine Model</div><div class="stat-value">'+escapeHtml(e.engine_model||'-')+'</div></div>'+
      '<div class="stat-tile"><div class="stat-label">Frame No.</div><div class="stat-value">'+escapeHtml(e.frame_no||'-')+'</div></div>'+
      '</div>'+
      (e.remarks ? '<div style="color:var(--text-muted);margin-bottom:8px;font-size:13px;">Remarks: <span style="color:var(--text);">'+escapeHtml(e.remarks)+'</span></div>' : '')+
      itemsHtml+
      (e.photos && e.photos.some(p=>p) ? '<div class="detail-cat">Foto Dokumentasi</div><div class="photo-thumbs">'+e.photos.filter(p=>p).map(p=>'<img src="'+p+'">').join('')+'</div>' : '');
    detailTd.appendChild(detail);
    detailRow.appendChild(detailTd);

    row.onclick = ()=>{
      detail.classList.toggle('open');
    };
    body.appendChild(row);
    body.appendChild(detailRow);
  });
}

document.getElementById('filterModel').onchange = renderHistory;
document.getElementById('searchHist').oninput = renderHistory;

function showToast(text){
  const toast = document.getElementById('toast');
  toast.textContent = text;
  toast.classList.add('show');
  setTimeout(()=> toast.classList.remove('show'), 2800);
}

function resetForm(){
  document.getElementById('f_model').value = '';
  document.getElementById('f_voltage').value = '';
  document.getElementById('f_freq').value = '';
  document.getElementById('f_dest').value = '';
  document.getElementById('f_engine_model').value = '';
  document.getElementById('f_engine_no').value = '';
  document.getElementById('f_gen_no').value = '';
  document.getElementById('f_frame_no').value = '';
  document.getElementById('f_remarks').value = '';

  document.querySelectorAll('.hasil-select').forEach(sel=>{
    sel.value = '';
    sel.classList.remove('val-ok','val-dash');
    state[sel.dataset.item] = '';
  });

  for(let i=0;i<5;i++){
    photos[i] = null;
    const fileInput = document.getElementById('file_'+i);
    const preview = document.getElementById('preview_'+i);
    if(fileInput) fileInput.value = '';
    if(preview){
      preview.src = '';
      preview.closest('.photo-slot').classList.remove('filled');
    }
  }

  updateProgress();

  const today = new Date();
  today.setMinutes(today.getMinutes() - today.getTimezoneOffset());
  document.getElementById('f_tanggal').value = today.toISOString().slice(0,10);
}
</script>
</body>
</html>