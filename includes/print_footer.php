<?php
/**
 * Komponen Tanda Tangan / Footer Resmi Gindaco untuk Cetak / Print Dokumen
 */
if (!function_exists('formatTanggalIndoCetak')) {
    function formatTanggalIndoCetak($timestamp = null) {
        if (!$timestamp) $timestamp = time();
        $hari = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
        $bulan = [
            1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
            'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
        ];
        $day_name = $hari[(int)date('w', $timestamp)];
        $day = date('j', $timestamp);
        $month_name = $bulan[(int)date('n', $timestamp)];
        $year = date('Y', $timestamp);
        return "$day_name, $day $month_name $year";
    }
}

$nama_pencetak = $_SESSION['nama_pencetak'] ?? ($_SESSION['nama_petugas'] ?? ($_SESSION['user'] ?? 'Wildan Ari Leksono'));
$lokasi_kota = "Jakarta Utara";
$tanggal_sekarang = formatTanggalIndoCetak();
$jam_sekarang = date('H:i:s');
?>
<!-- FOOTER CETAK / TANDA TANGAN (Otomatis tampil saat Cetak / Print) -->
<div class="print-footer-section d-none d-print-block mt-4 pt-3" style="page-break-inside: avoid;">
    <div class="d-flex justify-content-end">
        <div class="text-start" style="min-width: 260px; font-family: 'Times New Roman', serif; font-size: 0.95rem;">
            <div><?php echo $lokasi_kota; ?>, <span id="printTanggalTxt"><?php echo $tanggal_sekarang; ?></span></div>
            <div>Pukul: <span id="printJamTxt"><?php echo $jam_sekarang; ?></span></div>
            <div style="height: 65px;"></div> <!-- Area tanda tangan -->
            <div class="fw-bold text-decoration-underline text-dark" style="font-size: 1rem;">
                <?php echo htmlspecialchars($nama_pencetak); ?>
            </div>
            <div class="text-muted small">Admin Outlet Gindaco Mall Artha Gading</div>
        </div>
    </div>
</div>

<script>
// Perbarui jam cetak secara real-time saat dialog window.print() dipanggil
window.addEventListener('beforeprint', function() {
    const now = new Date();
    const pad = n => n.toString().padStart(2, '0');
    const jam = `${pad(now.getHours())}:${pad(now.getMinutes())}:${pad(now.getSeconds())}`;
    const elJam = document.getElementById('printJamTxt');
    if (elJam) elJam.textContent = jam;
});

// Jika parameter ?print=true diberikan di URL, otomatis buka dialog cetak
<?php if (isset($_GET['print'])): ?>
window.addEventListener('load', function() {
    setTimeout(function() { window.print(); }, 600);
});
<?php endif; ?>
</script>
