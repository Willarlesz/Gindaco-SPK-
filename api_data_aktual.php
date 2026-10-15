<?php
/**
 * API Backend untuk Memvalidasi, Mengoreksi, dan Mengaudit Data Aktual Pemakaian
 * Hanya dapat dieksekusi oleh pengguna dengan hak akses MANAGER.
 */
include 'db.php';
require_once 'includes/auth.php';

header('Content-Type: application/json');

// 1. Cek hak akses Manager di server-side
if (!is_manager()) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'Akses ditolak: Operasi pembaharuan data aktual hanya diizinkan untuk Manager.'
    ]);
    exit();
}

$action = $_POST['action'] ?? '';

if ($action === 'update_aktual') {
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    $nilai_baru = isset($_POST['pemakaian_aktual']) ? floatval($_POST['pemakaian_aktual']) : -1;
    $alasan = trim($_POST['alasan'] ?? 'Koreksi verifikasi data aktual fisik gudang');

    if ($id <= 0 || $nilai_baru < 0) {
        echo json_encode([
            'success' => false,
            'message' => 'Parameter tidak valid. Nilai pemakaian aktual tidak boleh negatif.'
        ]);
        exit();
    }

    // Ambil data lama
    $q_old = $conn->query("SELECT h.*, b.nama_bahan, b.kode_bahan, b.satuan 
                           FROM data_historis h 
                           JOIN bahan_baku b ON h.id_bahan = b.id 
                           WHERE h.id = $id LIMIT 1");
    if (!$q_old || $q_old->num_rows === 0) {
        echo json_encode([
            'success' => false,
            'message' => 'Data historis tidak ditemukan.'
        ]);
        exit();
    }

    $old = $q_old->fetch_assoc();
    $nilai_lama = (float)$old['pemakaian_aktual'];

    // Update nilai di database
    $stmt = $conn->prepare("UPDATE data_historis SET pemakaian_aktual = ? WHERE id = ?");
    $stmt->bind_param("di", $nilai_baru, $id);

    if ($stmt->execute()) {
        // Catat Audit Log
        $detail_audit = [
            'id_bahan' => $old['id_bahan'],
            'kode_bahan' => $old['kode_bahan'],
            'nama_bahan' => $old['nama_bahan'],
            'periode' => $old['periode'],
            'satuan' => $old['satuan'],
            'nilai_sebelum' => $nilai_lama,
            'nilai_sesudah' => $nilai_baru,
            'keterangan' => $alasan
        ];
        catat_audit_log($conn, 'UPDATE_AKTUAL', 'data_historis', $id, $detail_audit);

        echo json_encode([
            'success' => true,
            'message' => "Data aktual {$old['nama_bahan']} ({$old['periode']}) berhasil diperbarui dari " . number_format($nilai_lama, 2) . " menjadi " . number_format($nilai_baru, 2) . " {$old['satuan']}.",
            'id' => $id,
            'nilai_lama' => $nilai_lama,
            'nilai_baru' => $nilai_baru
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Gagal memperbarui database: ' . $conn->error
        ]);
    }
    exit();
}

if ($action === 'get_audit_history') {
    $q_log = $conn->query("SELECT * FROM audit_log WHERE tabel_terkait = 'data_historis' ORDER BY id DESC LIMIT 15");
    $logs = [];
    if ($q_log) {
        while ($l = $q_log->fetch_assoc()) {
            $logs[] = [
                'id' => $l['id'],
                'user' => $l['nama_user'] . ' (' . $l['nik'] . ')',
                'aksi' => $l['aksi'],
                'detail' => json_decode($l['detail_perubahan'], true) ?? $l['detail_perubahan'],
                'waktu' => date('d M Y, H:i', strtotime($l['created_at']))
            ];
        }
    }
    echo json_encode(['success' => true, 'logs' => $logs]);
    exit();
}

echo json_encode([
    'success' => false,
    'message' => 'Action tidak dikenali.'
]);
exit();

