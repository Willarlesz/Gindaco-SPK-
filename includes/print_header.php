<?php
/**
 * Komponen Kop Surat Resmi Gindaco untuk Cetak / Print Dokumen
 */
if (!isset($judul_laporan)) {
    $judul_laporan = $page_title ?? 'Laporan Sistem Pendukung Keputusan';
}
if (!isset($subjudul_laporan)) {
    $subjudul_laporan = 'Periode Analisis: ' . date('F Y');
}
?>
<!-- KOP SURAT PRINT RESMI (Otomatis tampil saat Cetak / Print) -->
<div class="print-header-section d-none d-print-block mb-3">
    <table style="width: 100%; border: none !important; border-bottom: 2px solid #000 !important; padding-bottom: 8px; margin-bottom: 12px;">
        <tr style="border: none !important;">
            <td style="width: 140px; vertical-align: middle; border: none !important; text-align: left; padding: 0 10px 8px 0;">
                <img src="assets/img/gindaco_logo.webp" alt="Logo Gindaco" style="max-height: 42px; width: auto; object-fit: contain; display: block;">
            </td>
            <td style="vertical-align: middle; border: none !important; text-align: center; padding: 0 10px 8px 10px;">
                <h3 class="fw-bold mb-1 text-uppercase text-dark" style="font-size: 1.25rem; letter-spacing: 0.05em; font-family: 'Times New Roman', serif; margin: 0;">
                    GINDACO MALL ARTHA GADING
                </h3>
                <p class="mb-0 text-dark" style="font-size: 0.8rem; line-height: 1.35; font-family: 'Times New Roman', serif; margin: 0;">
                    Jl. Boulevard Artha Gading No.1, Kelapa Gading Barat, Kecamatan Kelapa Gading,<br>
                    Kota Jakarta Utara, DKI Jakarta 14240
                </p>
            </td>
            <td style="width: 140px; border: none !important;">
                <!-- Penyeimbang sisi kanan agar teks tengah presisi dan simetris -->
            </td>
        </tr>
    </table>
    
    <!-- Judul Dokumen Laporan -->
    <div class="text-center pt-3 pb-1">
        <h4 class="fw-bold text-dark text-uppercase mb-1" style="font-size: 1.15rem; letter-spacing: 0.02em; font-family: 'Times New Roman', serif; text-decoration: underline;">
            <?php echo htmlspecialchars($judul_laporan); ?>
        </h4>
        <div class="text-dark small fw-semibold" style="font-size: 0.85rem; font-family: 'Times New Roman', serif;">
            <?php echo htmlspecialchars($subjudul_laporan); ?>
        </div>
    </div>
</div>

