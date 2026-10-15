<?php
/**
 * api_explode_bom.php
 * Endpoint AJAX untuk memproses konversi Menu Penjualan CSV Mingguan ke Bahan Baku (Recipe Explosion)
 * serta menghitung Pure Weekly Exponential Smoothing pada level Bahan Baku secara reaktif.
 */

include 'db.php';
require_once 'includes/auth.php';
require_once 'includes/bom_service.php';

header('Content-Type: application/json');

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Silakan login terlebih dahulu.']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    echo json_encode(['success' => false, 'message' => 'Invalid JSON input.']);
    exit();
}

if (session_status() === PHP_SESSION_NONE) session_start();

// Handle quick state save from rekomendasi.php
if (isset($input['action']) && $input['action'] === 'save_spk_state') {
    if (isset($input['materials']) && is_array($input['materials'])) {
        $_SESSION['spk_weekly_materials'] = $input['materials'];
    }
    if (isset($input['buffer_unit'])) {
        $_SESSION['spk_buffer_unit'] = $input['buffer_unit'];
    }
    if (isset($input['buffer_value'])) {
        $_SESSION['spk_buffer_value'] = floatval($input['buffer_value']);
    }
    $_SESSION['spk_weekly_updated_at'] = date('Y-m-d H:i:s');
    echo json_encode(['success' => true, 'message' => 'State SPK berhasil disimpan.']);
    exit();
}

$menu_w1 = $input['menu_w1'] ?? []; // [ article_code => { name, qty } ]
$menu_w2 = $input['menu_w2'] ?? [];
$alpha = isset($input['alpha']) ? floatval($input['alpha']) : 0.3;
if ($alpha < 0.1) $alpha = 0.1;
if ($alpha > 0.9) $alpha = 0.9;

$buffer_unit = $input['buffer_unit'] ?? ($_SESSION['spk_buffer_unit'] ?? 'days');
$buffer_value = isset($input['buffer_value']) ? floatval($input['buffer_value']) : floatval($_SESSION['spk_buffer_value'] ?? 1.0);
if ($buffer_value < 0) $buffer_value = 0.0;

// 1. Jalankan Recipe Explosion secara independen untuk kedua minggu
$bahan_w1 = explodeMenuSalesToIngredients($conn, $menu_w1);
$bahan_w2 = explodeMenuSalesToIngredients($conn, $menu_w2);

// 2. Ambil seluruh data master bahan baku aktif
$all_raw = get_all_raw_materials($conn);

// 3. Kalkulasi Pure Weekly Exponential Smoothing pada Level Bahan Baku
$spk_materials = [];
$total_saran_order_items = 0;
$total_qty_saran_order = 0.0;
$total_pemakaian_w1 = 0.0;
$total_pemakaian_w2 = 0.0;

foreach ($all_raw as $kode_bahan => $b) {
    $aktual_lalu = isset($bahan_w1[$kode_bahan]) ? (float)$bahan_w1[$kode_bahan] : 0.0;
    $aktual_sekarang = isset($bahan_w2[$kode_bahan]) ? (float)$bahan_w2[$kode_bahan] : 0.0;

    // Baseline ramalan minggu lalu (F_t-1). Jika baru/pertama kali, set sama dengan Aktual Lalu (Bahan)
    $ramalan_lalu = $aktual_lalu;

    // Single Exponential Smoothing Bahan: Forecast = (Alpha * Aktual_Sekarang) + ((1 - Alpha) * Ramalan_Lalu)
    $raw_forecast = ($alpha * $aktual_sekarang) + ((1.0 - $alpha) * $ramalan_lalu);
    $forecast_sistem = (float)round($raw_forecast, 2);

    // Default Final Forecast Bahan = Forecast Sistem (sebelum override user)
    $final_forecast = $forecast_sistem;

    $stok_akhir = (float)$b['stok_aktual'];

    // 1. Logika Perhitungan Dynamic Safety Stock:
    // Daily Usage = Final Forecast Mingguan / 7 hari
    $daily_usage = $final_forecast / 7.0;
    if ($buffer_unit === 'percent') {
        $dynamic_safety_stock = (float)round($final_forecast * ($buffer_value / 100.0));
    } else {
        $dynamic_safety_stock = (float)round($daily_usage * $buffer_value);
    }

    // 2. Formula Saran Order SPK:
    // Target Total Stok = Final Forecast Mingguan + Safety Stock Dinamis
    $target_stok = (float)round($final_forecast + $dynamic_safety_stock, 2);

    // Saran Order = Target Total Stok - Stok Fisik Outlet (Min: 0)
    $saran_order = $target_stok - $stok_akhir;
    if ($saran_order < 0) {
        $saran_order = 0.0;
    }
    $saran_order = (float)round($saran_order, 2);

    $is_order = ($saran_order > 0);
    if ($is_order) {
        $total_saran_order_items++;
    }
    $total_qty_saran_order += $saran_order;
    $total_pemakaian_w1 += $aktual_lalu;
    $total_pemakaian_w2 += $aktual_sekarang;

    $spk_materials[] = [
        'id' => $b['id'],
        'kode_bahan' => $b['kode_bahan'],
        'nama_bahan' => $b['nama_bahan'],
        'satuan' => $b['satuan'],
        'aktual_lalu' => $aktual_lalu,
        'ramalan_lalu' => $ramalan_lalu,
        'aktual_sekarang' => $aktual_sekarang,
        'forecast_sistem' => $forecast_sistem,
        'manual_override' => null,
        'final_forecast' => $final_forecast,
        'daily_usage' => round($daily_usage, 2),
        'dynamic_safety_stock' => $dynamic_safety_stock,
        'target_stok' => $target_stok,
        'stok_akhir' => $stok_akhir,
        'saran_order' => $saran_order,
        'is_order' => $is_order
    ];
}

// Simpan ke session SPK agar halaman rekomendasi.php dan modul cetak langsung tersinkronisasi
$_SESSION['spk_weekly_materials'] = $spk_materials;
$_SESSION['spk_weekly_alpha'] = $alpha;
$_SESSION['spk_buffer_unit'] = $buffer_unit;
$_SESSION['spk_buffer_value'] = $buffer_value;
$_SESSION['spk_weekly_updated_at'] = date('Y-m-d H:i:s');

echo json_encode([
    'success' => true,
    'total_materials' => count($spk_materials),
    'stats' => [
        'total_materials' => count($spk_materials),
        'total_saran_order_items' => $total_saran_order_items,
        'total_qty_saran_order' => round($total_qty_saran_order, 2),
        'total_pemakaian_w1' => round($total_pemakaian_w1, 2),
        'total_pemakaian_w2' => round($total_pemakaian_w2, 2)
    ],
    'materials' => $spk_materials
]);
exit();

