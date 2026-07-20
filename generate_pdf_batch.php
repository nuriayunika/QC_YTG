<?php
require 'auth.php';
require_role(['supervisor', 'manager']);
require __DIR__ . '/vendor/autoload.php';

$idsParam = $_GET['ids'] ?? '';
$ids = array_filter(array_map('intval', explode(',', $idsParam)));

if (empty($ids)) {
    die('Tidak ada checksheet yang dipilih.');
}

$placeholders = implode(',', array_fill(0, count($ids), '?'));
$stmt = $pdo->prepare("SELECT * FROM checksheet_results WHERE id IN ($placeholders) AND manager_status = 'approved' ORDER BY FIELD(id, $placeholders)");
$stmt->execute(array_merge($ids, $ids));
$entries = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($entries)) {
    die('Tidak ada checksheet yang valid (harus sudah full approved).');
}

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

$css = "
    * { font-family: Arial, sans-serif; font-size: 9pt; margin:0; padding:0; box-sizing:border-box; }
    body { color: #222; }
    .header-table { width:100%; border-collapse:collapse; margin-bottom:4px; }
    .header-logo  { width:100px; text-align:center; vertical-align:middle; }
    .header-title { text-align:center; vertical-align:middle; }
    .header-title h1 { font-size:14pt; font-weight:bold; color:#7B2334; letter-spacing:1px; }
    .header-title h2 { font-size:9pt; font-weight:normal; color:#555; margin-top:2px; }
    .header-right { width:120px; text-align:right; vertical-align:top; font-size:8pt; color:#888; }
    .divider { border:none; border-top:2px solid #7B2334; margin:3px 0; }
    .info-table { width:100%; border-collapse:collapse; margin-bottom:4px; }
    .info-table td { padding:2px 6px; font-size:8.5pt; vertical-align:top; }
    .info-label { color:#7B2334; font-weight:bold; width:120px; }
    .info-val   { color:#222; }
    .section-title { background:#7B2334; color:#fff; font-weight:bold; font-size:8pt; padding:3px 8px; margin:3px 0 2px 0; letter-spacing:0.5px; }
    table.chk-table { width:100%; border-collapse:collapse; margin-bottom:4px; }
    table.chk-table th { background:#7B2334; color:#fff; font-size:7pt; padding:2px 5px; text-align:left; border:0.5px solid #5a1a27; }
    table.chk-table td { padding:1px 5px; border:0.5px solid #ddd; font-size:7pt; vertical-align:middle; line-height:1.05; }
    table.chk-table tr:nth-child(even) td { background:#fdf5f5; }
    .badge-ok    { color:#198754; font-weight:bold; }
    .badge-dash  { color:#6B7280; font-weight:bold; }
    .badge-empty { color:#dc3545; font-weight:bold; }

    .pdf-footer { text-align:center; font-size:7.5pt; color:#aaa; margin-top:6px; border-top:0.5px solid #eee; padding-top:3px; }
";

function approval_card($label, $status, $by, $at) {
    $statusHtml = '<div style="color:#B7791F;font-weight:bold;font-size:9pt;">&#9203; PENDING</div>';
    if ($status === 'approved') {
        $statusHtml = '<div style="color:#198754;font-weight:bold;font-size:9pt;">&#10003; APPROVED</div>';
    } elseif ($status === 'rejected') {
        $statusHtml = '<div style="color:#dc3545;font-weight:bold;font-size:9pt;">&#10007; REJECTED</div>';
    }
    $dateHtml = $at ? '<div style="color:#888;font-size:7.5pt;margin-top:2px;">' . date('d/m/Y H:i', strtotime($at)) . '</div>' : '';
    $nameHtml = $by ? '<div style="color:#7B2334;font-weight:bold;font-size:11pt;margin:3px 0;">' . e($by) . '</div>' : '<div style="color:#ccc;font-size:9pt;margin:8px 0;">&mdash;</div>';
    return '<td style="border:0.5px solid #ddd;padding:12px 8px;text-align:center;width:33%;vertical-align:top;">'
        . '<div style="color:#888;font-size:7.5pt;text-transform:uppercase;letter-spacing:0.5px;">' . $label . '</div>'
        . $nameHtml . $statusHtml . $dateHtml
        . '</td>';
}

$bodyParts = [];

foreach ($entries as $idx => $row) {
    $items  = json_decode($row['items'], true) ?: [];
    $photos = json_decode($row['photos'], true) ?: [];
    $filledPhotos = array_values(array_filter($photos));

    $rowsHtmlLeft = '';
    $rowsHtmlRight = '';
    $totalItems = 0;
    foreach ($categories as $cat) $totalItems += count($cat['items']);
    $halfPoint = ceil($totalItems / 2);
    $runningCount = 0;

    foreach ($categories as $catNo => $cat) {
        $catHtml = '<tr><td colspan="3" style="background:#F7E9EB;color:#5E1A27;font-weight:bold;text-align:left;padding:2px 5px;font-size:7.5pt;">'
            . e($catNo . '. ' . strtoupper($cat['name'])) . '</td></tr>';
        $isLeftCat = $runningCount < $halfPoint;
        if ($isLeftCat) { $rowsHtmlLeft .= $catHtml; } else { $rowsHtmlRight .= $catHtml; }

        foreach ($cat['items'] as $item) {
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

    $photosHtml = '';
    if (count($filledPhotos) > 0) {
        $photoWidth = floor(100 / max(count($filledPhotos), 1));
        $photosHtml .= '<pagebreak /><div class="section-title">FOTO DOKUMENTASI</div><table style="width:100%;border-collapse:collapse;"><tr>';
        foreach ($filledPhotos as $i => $photo) {
            $photosHtml .= '<td style="text-align:center;padding:4px;width:' . $photoWidth . '%;">'
                . '<img src="' . e($photo) . '" style="max-width:100%;max-height:150px;border:1px solid #ddd;border-radius:4px;object-fit:contain;">'
                . '<div style="font-size:7.5pt;color:#aaa;margin-top:3px;">Foto ' . ($i + 1) . '</div></td>';
        }
        $photosHtml .= '</tr></table>';
    }

    $tanggal = isset($row['tanggal']) && $row['tanggal'] ? date('d/m/Y', strtotime($row['tanggal'])) : '-';
    $pageBreak = $idx > 0 ? '<pagebreak />' : '';

    $bodyParts[] = "
    {$pageBreak}
    <div>
    <table class=\"header-table\">
        <tr>
            <td class=\"header-logo\">
                <div style=\"width:60px;height:40px;background:#F7E9EB;border:1px solid #ddd;display:flex;align-items:center;justify-content:center;font-size:7pt;color:#7B2334;text-align:center;\">QC<br>GENSET</div>
            </td>
            <td class=\"header-title\">
                <h1>FINAL CHECK SHEET GENERATOR SET</h1>
                <h2>Quality Control &mdash; Factory Assembly Checksheet</h2>
            </td>
            <td class=\"header-right\">
                " . date('d/m/Y') . "
            </td>
        </tr>
    </table>
    <hr class=\"divider\">

    <table class=\"info-table\">
        <tr>
            <td class=\"info-label\">Date of Report</td><td class=\"info-val\">{$tanggal}</td>
            <td class=\"info-label\">Model</td><td class=\"info-val\">" . e($row['model']) . "</td>
            <td class=\"info-label\">Voltage</td><td class=\"info-val\">" . e($row['voltage']) . "</td>
            <td class=\"info-label\">Frequency</td><td class=\"info-val\">" . e($row['frequency']) . "</td>
        </tr>
        <tr>
            <td class=\"info-label\">Destination</td><td class=\"info-val\">" . e($row['destination']) . "</td>
            <td class=\"info-label\">Engine Model</td><td class=\"info-val\">" . e($row['engine_model']) . "</td>
            <td class=\"info-label\">Engine No.</td><td class=\"info-val\">" . e($row['engine_no']) . "</td>
            <td class=\"info-label\">Generator No.</td><td class=\"info-val\">" . e($row['generator_no']) . "</td>
        </tr>
        <tr>
            <td class=\"info-label\">Frame No.</td><td class=\"info-val\">" . e($row['frame_no']) . "</td>
            <td class=\"info-label\">Remarks</td><td class=\"info-val\" colspan=\"5\">" . (!empty($row['remarks']) ? nl2br(e($row['remarks'])) : '-') . "</td>
        </tr>
    </table>

    <div class=\"section-title\">CHECKLIST PEMERIKSAAN</div>
    <table style=\"width:100%;border-collapse:collapse;\">
    <tr>
    <td style=\"width:49%;vertical-align:top;padding-right:6px;\">
        <table class=\"chk-table\">
            <thead><tr><th style=\"width:26px;\">#</th><th>Item</th><th style=\"width:60px;\">Hasil</th></tr></thead>
            <tbody>{$rowsHtmlLeft}</tbody>
        </table>
    </td>
    <td style=\"width:49%;vertical-align:top;padding-left:6px;\">
        <table class=\"chk-table\">
            <thead><tr><th style=\"width:26px;\">#</th><th>Item</th><th style=\"width:60px;\">Hasil</th></tr></thead>
            <tbody>{$rowsHtmlRight}</tbody>
        </table>
    </td>
    </tr>
    </table>

    {$photosHtml}

    <table style=\"width:100%;border-collapse:collapse;margin-top:6px;\">
        <tr>
            " . approval_card('Foreman', $row['foreman_status'], $row['foreman_by'], $row['foreman_at']) . "
            " . approval_card('Supervisor', $row['supervisor_status'], $row['supervisor_by'], $row['supervisor_at']) . "
            " . approval_card('Manager', $row['manager_status'], $row['manager_by'], $row['manager_at']) . "
        </tr>
    </table>

    <div class=\"pdf-footer\">Dokumen ini digenerate otomatis oleh sistem QC &mdash; " . date('d/m/Y H:i:s') . "</div>
    </div>
    ";
}

$html = '<!DOCTYPE html><html><head><meta charset="UTF-8"><style>' . $css . '</style></head><body>' . implode('', $bodyParts) . '</body></html>';

$mpdf = new \Mpdf\Mpdf([
    'mode'          => 'utf-8',
    'format'        => 'A4-L',
    'margin_top'    => 12,
    'margin_bottom' => 10,
    'margin_left'   => 12,
    'margin_right'  => 12,
]);
$mpdf->img_dpi = 96;
$mpdf->SetTitle('Batch Checksheet - ' . count($entries) . ' unit');
$mpdf->WriteHTML($html);

$filename = 'Batch_Checksheet_' . count($entries) . 'unit_' . date('Ymd_His') . '.pdf';
$mpdf->Output($filename, 'D');