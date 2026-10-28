/**
 * assets/js/components.js
 * Modular UI Components for Gindaco SPK Static Web Application
 * Renders consistent Navbar, Print Header, Print Footer, and User Badge
 */

(function(window) {
    'use strict';

    function getFormattedPrintDate() {
        const d = new Date();
        const days = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
        const months = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
        
        const dayName = days[d.getDay()];
        const dateNum = d.getDate();
        const monthName = months[d.getMonth()];
        const year = d.getFullYear();
        const hours = String(d.getHours()).padStart(2, '0');
        const mins = String(d.getMinutes()).padStart(2, '0');
        
        return {
            fullDate: `Jakarta Utara, ${dayName}, ${dateNum} ${monthName} ${year}`,
            time: `Pukul: ${hours}.${mins} WIB`
        };
    }

    const Components = {
        // Render Navbar Utama
        renderNavbar: function(activePage = 'dashboard') {
            const container = document.getElementById('navbar-container');
            if (!container) return;

            const currentUser = (window.GindacoDB && window.GindacoDB.getCurrentUser()) || { nama: 'Petugas Gindaco', level: 'staff', outlet: 'Mall Artha Gading' };
            const isManager = currentUser.level === 'manager';

            const navItems = [
                { id: 'dashboard', href: 'index.html', icon: 'fa-chart-pie', label: 'Dashboard' },
                { id: 'weekly_forecast', href: 'weekly_forecast.html', icon: 'fa-calendar-week', label: 'Weekly Forecast' },
                { id: 'rekomendasi', href: 'rekomendasi.html', icon: 'fa-clipboard-check', label: 'Rekomendasi SPK' },
                { id: 'bahan', href: 'bahan.html', icon: 'fa-boxes-stacked', label: 'Data Bahan' },
                { id: 'historis', href: 'historis.html', icon: 'fa-clock-rotate-left', label: 'Data Historis' },
                { id: 'laporan', href: 'laporan.html', icon: 'fa-file-invoice', label: 'Laporan SPK' }
            ];

            let navLinksHtml = navItems.map(item => {
                const isActive = activePage === item.id ? 'active' : '';
                const badgeHtml = item.badge 
                    ? `<span class="badge ${isActive ? 'bg-danger text-white' : 'bg-danger-subtle text-danger border border-danger-subtle'} ms-1" style="font-size: 0.62rem; padding: 2px 5px; font-weight: 700;">${item.badge}</span>` 
                    : '';
                return `
                    <li class="nav-item">
                        <a class="nav-link-custom ${isActive}" href="${item.href}">
                            <i class="fas ${item.icon}"></i>
                            <span>${item.label}</span>
                            ${badgeHtml}
                        </a>
                    </li>
                `;
            }).join('');

            container.innerHTML = `
                <nav class="navbar navbar-expand-xl navbar-gindaco sticky-top no-print d-print-none">
                    <div class="container-fluid px-lg-4">
                        <a class="navbar-brand d-flex align-items-center gap-2" href="index.html">
                            <img src="assets/img/gindaco_logo.webp" alt="Gindaco Logo" class="brand-logo-img">
                            <div class="d-flex flex-column">
                                <span class="brand-text-main">GINDACO SPK</span>
                                <span class="brand-outlet-badge">
                                    <i class="fas fa-store me-1 text-muted"></i>${currentUser.outlet || 'Gindaco Indonesia'}
                                </span>
                            </div>
                        </a>

                        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarMain" aria-controls="navbarMain" aria-expanded="false" aria-label="Toggle navigation">
                            <i class="fas fa-bars"></i>
                        </button>

                        <div class="collapse navbar-collapse mt-3 mt-xl-0" id="navbarMain">
                            <ul class="navbar-nav ms-auto mb-2 mb-xl-0 gap-1 align-items-xl-center">
                                ${navLinksHtml}

                                <!-- USER MENU -->
                                <li class="nav-item dropdown ms-xl-2 border-top border-xl-top-0 pt-2 pt-xl-0 mt-2 mt-xl-0">
                                    <a class="user-pill dropdown-toggle text-decoration-none" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                        <div class="user-avatar">
                                            <i class="fas fa-user"></i>
                                        </div>
                                        <div class="user-info">
                                            <span class="user-name">${currentUser.nama || 'User Gindaco'}</span>
                                            <span class="user-role">${(currentUser.level || 'staff').toUpperCase()}</span>
                                        </div>
                                    </a>
                                    <ul class="dropdown-menu dropdown-menu-end shadow-sm border mt-2 py-2" style="border-color: #e2e8f0; border-radius: 12px; min-width: 230px;">
                                        <li>
                                            <div class="px-3 py-2 border-bottom">
                                                <small class="text-muted d-block" style="font-size: 0.72rem;">Masuk sebagai</small>
                                                <div class="fw-bold text-dark mt-0" style="font-size: 0.88rem;">${currentUser.nama}</div>
                                                <div class="d-flex align-items-center gap-2 mt-1">
                                                    <span class="badge ${isManager ? 'bg-danger-subtle text-danger border border-danger-subtle' : 'bg-secondary-subtle text-secondary border border-secondary-subtle'} text-uppercase" style="font-size: 0.65rem;">${currentUser.level}</span>
                                                    <span class="text-muted small" style="font-size: 0.75rem;"><i class="fas fa-store me-1 text-slate-400"></i>${currentUser.outlet || 'Gindaco Indonesia'}</span>
                                                </div>
                                            </div>
                                        </li>
                                        <li>
                                            <a class="dropdown-item py-2 text-slate-600 d-flex align-items-center gap-2" href="javascript:void(0)" onclick="Components.resetDemoData()">
                                                <i class="fas fa-rotate-left text-warning"></i>
                                                <span>Reset Database ke Awal</span>
                                            </a>
                                        </li>
                                        <li><hr class="dropdown-divider my-1"></li>
                                        <li>
                                            <a class="dropdown-item py-2 text-danger fw-semibold d-flex align-items-center gap-2" href="javascript:void(0)" onclick="Components.logout()">
                                                <i class="fas fa-right-from-bracket"></i>
                                                <span>Keluar (Logout)</span>
                                            </a>
                                        </li>
                                    </ul>
                                </li>
                            </ul>
                        </div>
                    </div>
                </nav>
            `;
        },

        // Render Kop Surat Dokumen Resmi Cetak (HANYA terlihat saat @media print)
        renderPrintHeader: function(containerId, judul, subjudul) {
            const el = document.getElementById(containerId);
            if (!el) return;

            const currentUser = (window.GindacoDB && window.GindacoDB.getCurrentUser()) || { nama: 'Petugas Gindaco', level: 'staff', outlet: 'Gindaco Indonesia' };
            const outletUpper = (currentUser.outlet || 'INDONESIA').toUpperCase();

            el.className = 'print-only print-header-section d-none d-print-block';
            el.innerHTML = `
                <div class="print-header-content mb-4 print-only d-none d-print-block">
                    <div style="display: flex; align-items: center; justify-content: space-between; border-bottom: 3px double #333; padding-bottom: 12px; margin-bottom: 16px;">
                        <div style="flex: 0 0 100px; text-align: left;">
                            <img src="assets/img/gindaco_logo.webp" alt="Logo Gindaco" style="max-height: 75px; max-width: 95px; object-fit: contain;">
                        </div>
                        <div style="flex: 1; text-align: center; padding: 0 15px;">
                            <h2 style="margin: 0; font-size: 19pt; font-weight: 800; letter-spacing: 1px; color: #111; text-transform: uppercase;">
                                GINDACO ${outletUpper}
                            </h2>
                            <p style="margin: 4px 0 0 0; font-size: 9.5pt; color: #444; line-height: 1.35;">
                                Sistem Pendukung Keputusan Pengadaan Bahan Baku &bull; Operasional Terpadu Seluruh Cabang
                            </p>
                        </div>
                        <div style="flex: 0 0 100px; text-align: right;">
                            <div style="border: 1px solid #777; padding: 4px 8px; font-size: 8pt; font-family: monospace; text-align: center; border-radius: 4px;">
                                DOKUMEN RESMI<br>OUTLET AKTIF
                            </div>
                        </div>
                    </div>

                    <div style="text-align: center; margin-bottom: 15px;">
                        <h4 style="margin: 0; font-size: 13pt; font-weight: 700; text-transform: uppercase; text-decoration: underline;">
                            ${judul || 'Laporan Rekomendasi Pengadaan Bahan Baku (SPK Mingguan)'}
                        </h4>
                        <p style="margin: 3px 0 0 0; font-size: 9pt; color: #555;">
                            ${subjudul || ('Sistem Dynamic Safety Stock &bull; ' + (currentUser.outlet || 'Gindaco Indonesia') + ' &bull; ' + new Date().toLocaleDateString('id-ID'))}
                        </p>
                    </div>
                </div>
            `;
        },

        // Render Footer Cetak Dokumen Resmi (Tanda Tangan - HANYA terlihat saat @media print)
        renderPrintFooter: function(containerId) {
            const el = document.getElementById(containerId);
            if (!el) return;

            const printInfo = getFormattedPrintDate();
            const currentUser = (window.GindacoDB && window.GindacoDB.getCurrentUser()) || { nama: 'Manager Outlet MAG', level: 'manager' };

            el.className = 'print-only print-footer-section d-none d-print-block';
            el.innerHTML = `
                <div class="print-footer-content mt-5 pt-3 print-only d-none d-print-block">
                    <div style="display: flex; justify-content: space-between; align-items: flex-end; page-break-inside: avoid;">
                        <div style="font-size: 8.5pt; color: #555; line-height: 1.5;">
                            <div>Dicetak otomatis melalui: <strong>Sistem Peramalan & SPK Gindaco</strong></div>
                            <div>Status Dokumen: <em>Telah Diverifikasi & Sah untuk Pengadaan</em></div>
                            <div style="font-family: monospace; margin-top: 4px;">ID Cetak: SPK-${Date.now().toString(36).toUpperCase()}</div>
                        </div>

                        <div style="text-align: center; min-width: 250px;">
                            <div style="font-size: 9.5pt; color: #222; margin-bottom: 2px;">
                                ${printInfo.fullDate}
                            </div>
                            <div style="font-size: 8.5pt; color: #555; margin-bottom: 8px;">
                                ${printInfo.time}
                            </div>
                            <div style="font-size: 9pt; font-weight: 600; color: #333; margin-bottom: 55px;">
                                Petugas Pemeriksa / Pencetak,
                            </div>
                            <div style="border-bottom: 1.5px solid #222; width: 85%; margin: 0 auto 4px auto;"></div>
                            <div style="font-size: 10pt; font-weight: 700; color: #111;">
                                ${currentUser.nama || 'Petugas Pengadaan'}
                            </div>
                            <div style="font-size: 8.5pt; color: #666; text-transform: uppercase;">
                                ${currentUser.level === 'manager' ? 'Store Manager' : 'Staff Inventory'} &bull; ${currentUser.outlet || 'Mall Artha Gading'}
                            </div>
                        </div>
                    </div>
                </div>
            `;
        },

        // Logout
        logout: function() {
            if (window.GindacoDB) {
                window.GindacoDB.logout();
            } else {
                localStorage.removeItem('gindaco_current_user');
            }
            window.location.replace('login.html');
        },

        // Reset demo data
        resetDemoData: function() {
            if (confirm('Apakah Anda yakin ingin mereset seluruh database lokal ke kondisi awal master Gindaco? Data manual akan dikembalikan ke default.')) {
                if (window.GindacoDB) {
                    window.GindacoDB.init(true);
                    alert('Database lokal berhasil direset!');
                    window.location.reload();
                }
            }
        }
    };

    window.Components = Components;

})(window);

