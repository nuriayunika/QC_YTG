<?php
require 'config.php';
require __DIR__ . '/vendor/autoload.php';

if (!isset($_GET['id']) || !ctype_digit($_GET['id'])) {
    http_response_code(400);
    die('ID checksheet tidak valid.');
}
$id = (int) $_GET['id'];

$stmt = $pdo->prepare("SELECT * FROM checksheet_results WHERE id = :id");
$stmt->execute([':id' => $id]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$row) {
    http_response_code(404);
    die('Data checksheet dengan ID tersebut tidak ditemukan.');
}

$items  = json_decode($row['items'], true) ?: [];
$photos = json_decode($row['photos'], true) ?: [];

$masterStmt = $pdo->query("SELECT * FROM checklist_master ORDER BY category_no, item_order");
$master = $masterStmt->fetchAll(PDO::FETCH_ASSOC);

$categories = [];
foreach ($master as $m) {
    $catNo = $m['category_no'];
    if (!isset($categories[$catNo])) {
        $categories[$catNo] = ['name' => $m['category_name'], 'items' => []];
    }
    $categories[$catNo]['items'][] = $m;
}

// ============================================================
// Helper
// ============================================================
function e($str) { return htmlspecialchars($str ?? '-', ENT_QUOTES); }
function val($row, $key) { return isset($row[$key]) && $row[$key] !== '' ? e($row[$key]) : '-'; }

$engine_no    = val($row, 'engine_no');
$tanggal      = isset($row['tanggal']) && $row['tanggal'] ? date('d/m/Y', strtotime($row['tanggal'])) : '-';

// ============================================================
// Build baris checklist
// ============================================================
$rowsHtmlLeft = '';
$rowsHtmlRight = '';
$no = 0;
$totalItems = 0;
foreach ($categories as $cat) $totalItems += count($cat['items']);
$halfPoint = ceil($totalItems / 2);
$runningCount = 0;

foreach ($categories as $catNo => $cat) {
    $catHtml = '<tr><td colspan="3" style="background:#F7E9EB;color:#5E1A27;font-weight:bold;text-align:left;padding:2px 5px;font-size:7.5pt;">'
        . e($catNo . '. ' . strtoupper($cat['name'])) . '</td></tr>';

    $targetIsLeft = $runningCount < $halfPoint;
    if ($targetIsLeft) { $rowsHtmlLeft .= $catHtml; } else { $rowsHtmlRight .= $catHtml; }

    foreach ($cat['items'] as $item) {
        $no++;
        $code = $item['item_code'];
        $hasilRaw = $items[$code] ?? '';
        $hasilText = $hasilRaw === 'ok' ? 'OK' : ($hasilRaw === 'dash' ? '-' : '(kosong)');
        $cls = $hasilRaw === 'ok' ? 'badge-ok' : ($hasilRaw === 'dash' ? 'badge-dash' : 'badge-empty');
        $itemHtml = '<tr>'
            . '<td style="text-align:center;width:26px;">' . $item['item_order'] . '</td>'
            . '<td>' . e($item['item_label']) . '</td>'
            . '<td style="text-align:center;width:60px;"><span class="' . $cls . '">' . $hasilText . '</span></td>'
            . '</tr>';

        $isLeft = $runningCount < $halfPoint;
        if ($isLeft) { $rowsHtmlLeft .= $itemHtml; } else { $rowsHtmlRight .= $itemHtml; }
        $runningCount++;
    }
}

// ============================================================
// Build galeri foto
// ============================================================
$photosHtml = '';
$filledPhotos = array_values(array_filter($photos));
if (count($filledPhotos) > 0) {
    $photosHtml .= '<pagebreak /><div class="section-title">FOTO DOKUMENTASI</div><table style="width:100%;border-collapse:collapse;"><tr>';
    $photoWidth = floor(100 / max(count($filledPhotos), 1));
    foreach ($filledPhotos as $i => $photo) {
        $photosHtml .= '<td style="text-align:center;padding:4px;width:' . $photoWidth . '%;">'
            . '<img src="' . e($photo) . '" style="max-width:100%;max-height:150px;border:1px solid #ddd;border-radius:4px;object-fit:contain;">'
            . '<div style="font-size:7.5pt;color:#aaa;margin-top:3px;">Foto ' . ($i + 1) . '</div></td>';
    }
    $photosHtml .= '</tr></table>';
}

ob_start(); ?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<style>
    * { font-family: Arial, sans-serif; font-size: 9pt; margin:0; padding:0; box-sizing:border-box; }
    body { color: #222; }

    .header-table { width:100%; border-collapse:collapse; margin-bottom:4px; }
    .header-logo  { width:100px; text-align:center; vertical-align:middle; }
    .header-title { text-align:center; vertical-align:middle; }
    .header-title h1 { font-size:14pt; font-weight:bold; color:#7B2334; letter-spacing:1px; }
    .header-title h2 { font-size:9pt; font-weight:normal; color:#555; margin-top:2px; }
    .header-right { width:120px; text-align:right; vertical-align:top; font-size:8pt; color:#888; }

    .divider { border:none; border-top:2px solid #7B2334; margin:3px 0; }
    .divider-thin { border:none; border-top:0.5px solid #ddd; margin:4px 0; }

    .info-table { width:100%; border-collapse:collapse; margin-bottom:4px; }
    .info-table td { padding:2px 6px; font-size:8.5pt; vertical-align:top; }
    .info-label { color:#7B2334; font-weight:bold; width:120px; }
    .info-val   { color:#222; }

    .section-title {
        background:#7B2334; color:#fff; font-weight:bold; font-size:8pt;
        padding:3px 8px; margin:3px 0 2px 0; letter-spacing:0.5px;
    }

    table.chk-table { width:100%; border-collapse:collapse; margin-bottom:4px; }
    table.chk-table th { background:#7B2334; color:#fff; font-size:7pt; padding:2px 5px; text-align:left; border:0.5px solid #5a1a27; }
    table.chk-table td { padding:1px 5px; border:0.5px solid #ddd; font-size:7pt; vertical-align:middle; line-height:1.05; }
    table.chk-table tr:nth-child(even) td { background:#fdf5f5; }
    .badge-ok    { color:#198754; font-weight:bold; }
    .badge-dash  { color:#6B7280; font-weight:bold; }
    .badge-empty { color:#dc3545; font-weight:bold; }

    .pdf-footer { text-align:center; font-size:7.5pt; color:#aaa; margin-top:6px; border-top:0.5px solid #eee; padding-top:3px; }
</style>
</head>
<body>

<table class="header-table">
    <tr>
        <td class="header-logo">
            <div style="width:60px;height:40px;background:#F7E9EB;border:1px solid #ddd;display:flex;align-items:center;justify-content:center;font-size:7pt;color:#7B2334;text-align:center;">QC<br>GENSET</div>
        </td>
        <td class="header-title">
            <h1>FINAL CHECK SHEET GENERATOR SET</h1>
            <h2>Quality Control &mdash; Factory Assembly Checksheet</h2>
        </td>
        <td class="header-right">
            <?php echo date('d/m/Y'); ?>
        </td>
    </tr>
</table>
<hr class="divider">

<table class="info-table">
    <tr>
        <td class="info-label">Date of Report</td><td class="info-val"><?php echo $tanggal; ?></td>
        <td class="info-label">Model</td><td class="info-val"><?php echo val($row,'model'); ?></td>
        <td class="info-label">Voltage</td><td class="info-val"><?php echo val($row,'voltage'); ?></td>
        <td class="info-label">Frequency</td><td class="info-val"><?php echo val($row,'frequency'); ?></td>
    </tr>
    <tr>
        <td class="info-label">Destination</td><td class="info-val"><?php echo val($row,'destination'); ?></td>
        <td class="info-label">Engine Model</td><td class="info-val"><?php echo val($row,'engine_model'); ?></td>
        <td class="info-label">Engine No.</td><td class="info-val"><?php echo $engine_no; ?></td>
        <td class="info-label">Generator No.</td><td class="info-val"><?php echo val($row,'generator_no'); ?></td>
    </tr>
    <tr>
        <td class="info-label">Frame No.</td><td class="info-val"><?php echo val($row,'frame_no'); ?></td>
        <td class="info-label">Remarks</td><td class="info-val" colspan="5"><?php echo !empty($row['remarks']) ? nl2br(e($row['remarks'])) : '-'; ?></td>
    </tr>
</table>

<div class="section-title">CHECKLIST PEMERIKSAAN</div>
<table style="width:100%;border-collapse:collapse;">
<tr>
<td style="width:49%;vertical-align:top;padding-right:6px;">
    <table class="chk-table">
        <thead><tr><th style="width:26px;">#</th><th>Item</th><th style="width:60px;">Hasil</th></tr></thead>
        <tbody><?php echo $rowsHtmlLeft; ?></tbody>
    </table>
</td>
<td style="width:49%;vertical-align:top;padding-left:6px;">
    <table class="chk-table">
        <thead><tr><th style="width:26px;">#</th><th>Item</th><th style="width:60px;">Hasil</th></tr></thead>
        <tbody><?php echo $rowsHtmlRight; ?></tbody>
    </table>
</td>
</tr>
</table>

<?php echo $photosHtml; ?>

<?php
function approval_card($label, $status, $by, $at) {
    $statusHtml = '<div style="color:#B7791F;font-weight:bold;font-size:9pt;">&#9203; PENDING</div>';
    if ($status === 'approved') {
        $statusHtml = '<div style="color:#198754;font-weight:bold;font-size:9pt;">&#10003; APPROVED</div>';
    } elseif ($status === 'rejected') {
        $statusHtml = '<div style="color:#dc3545;font-weight:bold;font-size:9pt;">&#10007; REJECTED</div>';
    }
    $dateHtml = $at ? '<div style="color:#888;font-size:7.5pt;margin-top:2px;">' . date('d/m/Y H:i', strtotime($at)) . '</div>' : '';
    $nameHtml = $by ? '<div style="color:#7B2334;font-weight:bold;font-size:11pt;margin:3px 0;">' . htmlspecialchars($by) . '</div>' : '<div style="color:#ccc;font-size:9pt;margin:8px 0;">&mdash;</div>';
    return '<td style="border:0.5px solid #ddd;padding:12px 8px;text-align:center;width:33%;vertical-align:top;">'
        . '<div style="color:#888;font-size:7.5pt;text-transform:uppercase;letter-spacing:0.5px;">' . $label . '</div>'
        . $nameHtml . $statusHtml . $dateHtml
        . '</td>';
}
?>
<table style="width:100%;border-collapse:collapse;margin-top:6px;">
    <tr>
        <?php echo approval_card('Foreman', $row['foreman_status'], $row['foreman_by'], $row['foreman_at']); ?>
        <?php echo approval_card('Supervisor', $row['supervisor_status'], $row['supervisor_by'], $row['supervisor_at']); ?>
        <?php echo approval_card('Manager', $row['manager_status'], $row['manager_by'], $row['manager_at']); ?>
    </tr>
</table>

<div class="pdf-footer">
    Dokumen ini digenerate otomatis oleh sistem QC &mdash; <?php echo date('d/m/Y H:i:s'); ?>
</div>

</body>
</html>
<?php
$html = ob_get_clean();

$mpdf = new \Mpdf\Mpdf([
    'mode'          => 'utf-8',
    'format'        => 'A4-L',
    'margin_top'    => 12,
    'margin_bottom' => 10,
    'margin_left'   => 12,
    'margin_right'  => 12,
]);
$mpdf->img_dpi = 96;
$mpdf->SetTitle('Final Check Sheet Generator Set - ' . $engine_no);

preg_match('/<style>(.*?)<\/style>/s', $html, $style_match);
$css  = $style_match[1] ?? '';
$body = preg_replace('/<style>.*?<\/style>/s', '', $html);

$mpdf->WriteHTML($css, \Mpdf\HTMLParserMode::HEADER_CSS);
$mpdf->WriteHTML($body, \Mpdf\HTMLParserMode::HTML_BODY);

$safeEngineNo = preg_replace('/[^A-Za-z0-9_-]/', '_', $row['engine_no']);
$filename = 'Checksheet_' . $safeEngineNo . '_' . $row['id'] . '.pdf';

$mpdf->Output($filename, 'D');