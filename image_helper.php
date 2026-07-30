<?php
// ============================================================
// image_helper.php
// Fungsi buat resize & kompres foto pas upload, supaya file yang
// tersimpan nggak terlalu besar (foto langsung dari HP kamera bisa
// 3-5MB per file, kalau ada banyak checklist item = berat banget
// pas dibuka di modal Detail atau di-generate ke PDF).
// ============================================================

/**
 * Resize gambar ke lebar maksimum tertentu & kompres kualitasnya,
 * lalu simpan ke path tujuan. Aman dipakai untuk jpg/jpeg/png/webp.
 *
 * @param string $src_tmp_path  Path file sementara hasil upload ($_FILES[...]['tmp_name'])
 * @param string $dest_path     Path tujuan penyimpanan akhir
 * @param int    $max_width     Lebar maksimum dalam pixel (tinggi menyesuaikan proporsional)
 * @param int    $jpeg_quality  Kualitas JPEG 0-100 (default 75, cukup bagus tapi jauh lebih kecil)
 * @return bool True kalau berhasil disimpan
 */
function resizeAndSaveImage($src_tmp_path, $dest_path, $max_width = 1200, $jpeg_quality = 75) {
    if (!function_exists('imagecreatetruecolor')) {
        // GD library tidak tersedia -> fallback, simpan file asli tanpa resize
        $ok = @move_uploaded_file($src_tmp_path, $dest_path) || @copy($src_tmp_path, $dest_path);
        return $ok ? $dest_path : false;
    }

    $info = @getimagesize($src_tmp_path);
    if (!$info) {
        // Bukan file gambar valid -> fallback simpan asli
        $ok = @move_uploaded_file($src_tmp_path, $dest_path) || @copy($src_tmp_path, $dest_path);
        return $ok ? $dest_path : false;
    }

    list($orig_w, $orig_h) = $info;
    $mime = $info['mime'];

    switch ($mime) {
        case 'image/jpeg': $src_img = @imagecreatefromjpeg($src_tmp_path); break;
        case 'image/png':  $src_img = @imagecreatefrompng($src_tmp_path);  break;
        case 'image/webp': $src_img = function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($src_tmp_path) : false; break;
        default: $src_img = false;
    }

    if (!$src_img) {
        // Format tidak didukung GD -> fallback simpan asli
        $ok = @move_uploaded_file($src_tmp_path, $dest_path) || @copy($src_tmp_path, $dest_path);
        return $ok ? $dest_path : false;
    }

    // Hitung ukuran baru (jangan diperbesar kalau aslinya sudah lebih kecil dari max_width)
    if ($orig_w > $max_width) {
        $new_w = $max_width;
        $new_h = intval($orig_h * ($max_width / $orig_w));
    } else {
        $new_w = $orig_w;
        $new_h = $orig_h;
    }

    $new_img = imagecreatetruecolor($new_w, $new_h);

    // Pertahankan transparansi untuk PNG
    if ($mime === 'image/png') {
        imagealphablending($new_img, false);
        imagesavealpha($new_img, true);
    }

    imagecopyresampled($new_img, $src_img, 0, 0, 0, 0, $new_w, $new_h, $orig_w, $orig_h);

    // Auto-rotate berdasarkan EXIF orientation (foto dari HP sering kesimpen "miring" tanpa ini)
    if (function_exists('exif_read_data') && $mime === 'image/jpeg') {
        $exif = @exif_read_data($src_tmp_path);
        if (!empty($exif['Orientation'])) {
            switch ($exif['Orientation']) {
                case 3: $new_img = imagerotate($new_img, 180, 0); break;
                case 6: $new_img = imagerotate($new_img, -90, 0); break;
                case 8: $new_img = imagerotate($new_img, 90, 0); break;
            }
        }
    }

    // Selalu simpan sebagai JPEG (paling kompak), kecuali PNG yang butuh transparansi
    $saved = false;
    if ($mime === 'image/png') {
        $dest_path = preg_replace('/\.(jpg|jpeg|webp)$/i', '.png', $dest_path);
        $saved = imagepng($new_img, $dest_path, 6);
    } else {
        $dest_path = preg_replace('/\.(png|webp)$/i', '.jpg', $dest_path);
        $saved = imagejpeg($new_img, $dest_path, $jpeg_quality);
    }

    imagedestroy($src_img);
    imagedestroy($new_img);

    return $saved ? $dest_path : false;
}