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

            const currentUser = window.GindacoDB ? window.GindacoDB.getCurrentUser() : { nama: 'Manager Outlet MAG', level: 'manager' };
            const isManager = currentUser.level === 'manager';

            const navItems = [
                { id: 'dashboard', href: 'index.html', icon: 'fa-chart-pie', label: 'Dashboard' },
                { id: 'smoothing', href: 'smoothing.html', icon: 'fa-calculator', label: 'Smoothing Bulanan' },
                { id: 'weekly_forecast', href: 'weekly_forecast.html', icon: 'fa-calendar-week', label: 'Weekly Forecast', badge: 'New' },
                { id: 'rekomendasi', href: 'rekomendasi.html', icon: 'fa-clipboard-check', label: 'Rekomendasi SPK' },
                { id: 'bahan', href: 'bahan.html', icon: 'fa-boxes-stacked', label: 'Data Bahan' },
                { id: 'historis', href: 'historis.html', icon: 'fa-clock-rotate-left', label: 'Data Historis' },
                { id: 'import_csv', href: 'import_csv.html', icon: 'fa-file-csv', label: 'Import CSV' },
                { id: 'laporan', href: 'laporan.html', icon: 'fa-file-invoice', label: 'Laporan SPK' }
            ];

            let navLinksHtml = navItems.map(item => {
                const isActive = activePage === item.id ? 'active' : '';
                const badgeHtml = item.badge ? `<span class="badge bg-warning text-dark ms-1 small" style="font-size: 0.65rem;">${item.badge}</span>` : '';
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
                <nav class="navbar navbar-expand-xl navbar-gindaco sticky-top no-print">
                    <div class="container-fluid px-lg-4">
                        <a class="navbar-brand d-flex align-items-center gap-2" href="index.html">
                            <img src="assets/img/gindaco_logo.webp" alt="Gindaco Logo" class="brand-logo-img" style="height: 38px; width: auto; object-fit: contain;">
                            <div>
                                <span class="brand-text-main">GINDACO SPK</span>
                                <p class="brand-text-sub"><i class="fas fa-store me-1 text-warning"></i> Mall Artha Gading</p>
                            </div>
                        </a>

                        <button class="navbar-toggler text-white border-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#navbarMain" aria-controls="navbarMain" aria-expanded="false" aria-label="Toggle navigation">
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
                                    <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0 mt-2">
                                        <li>
                                            <div class="px-3 py-2 border-bottom">
                                                <small class="text-muted d-block">Masuk sebagai</small>
                                                <strong>${currentUser.nama}</strong>
                                                <span class="badge ${isManager ? 'bg-danger' : 'bg-secondary'} ms-1">${currentUser.level}</span>
                                            </div>
                                        </li>
                                        <li><h6 class="dropdown-header">Switch Demo User</h6></li>
                                        <li><a class="dropdown-item" href="javascript:void(0)" onclick="Components.switchDemoUser('manager')"><i class="fas fa-user-tie text-danger me-2"></i>Login Manager</a></li>
                                        <li><a class="dropdown-item" href="javascript:void(0)" onclick="Components.switchDemoUser('staff')"><i class="fas fa-user-gear text-primary me-2"></i>Login Staff</a></li>
                                        <li><hr class="dropdown-divider"></li>
                                        <li><a class="dropdown-item text-warning" href="javascript:void(0)" onclick="Components.resetDemoData()"><i class="fas fa-rotate-left me-2"></i>Reset Database ke Awal</a></li>
                                        <li><hr class="dropdown-divider"></li>
                                        <li><a class="dropdown-item text-danger" href="login.html"><i class="fas fa-right-from-bracket me-2"></i>Logout</a></li>
                                    </ul>
                                </li>
                            </ul>
                        </div>
                    </div>
                </nav>
            `;
        },

        // Render Kop Surat Dokumen Resmi Cetak
        renderPrintHeader: function(containerId, judul, subjudul) {
            const el = document.getElementById(containerId);
            if (!el) return;

            el.innerHTML = `
                <div class="print-only mb-4">
                    <div style="display: flex; align-items: center; justify-content: space-between; border-bottom: 3px double #333; padding-bottom: 12px; margin-bottom: 16px;">
                        <div style="flex: 0 0 100px; text-align: left;">
                            <img src="assets/img/gindaco_logo.webp" alt="Logo Gindaco" style="max-height: 75px; max-width: 95px; object-fit: contain;">
                        </div>
                        <div style="flex: 1; text-align: center; padding: 0 15px;">
                            <h2 style="margin: 0; font-size: 19pt; font-weight: 800; letter-spacing: 1px; color: #111; text-transform: uppercase;">
                                GINDACO MALL ARTHA GADING
                            </h2>
                            <p style="margin: 4px 0 0 0; font-size: 9.5pt; color: #444; line-height: 1.35;">
                                Jl. Boulevard Artha Gading No.1, Kelapa Gading Barat, Kecamatan Kelapa Gading, Kota Jakarta Utara, DKI Jakarta 14240
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
                            ${subjudul || 'Sistem Dynamic Safety Stock &bull; Outlet Mall Artha Gading &bull; ' + new Date().toLocaleDateString('id-ID')}
                        </p>
                    </div>
                </div>
            `;
        },

        // Render Footer Cetak Dokumen Resmi
        renderPrintFooter: function(containerId) {
            const el = document.getElementById(containerId);
            if (!el) return;

            const printInfo = getFormattedPrintDate();
            const currentUser = window.GindacoDB ? window.GindacoDB.getCurrentUser() : { nama: 'Manager Outlet MAG', level: 'manager' };

            el.innerHTML = `
                <div class="print-only mt-5 pt-3">
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
                                ${currentUser.level === 'manager' ? 'Store Manager' : 'Staff Inventory'} &bull; Mall Artha Gading
                            </div>
                        </div>
                    </div>
                </div>
            `;
        },

        // Switch demo user
        switchDemoUser: function(role) {
            if (!window.GindacoDB) return;
            const users = window.GindacoDB.getUsers();
            const target = users.find(u => u.level === role) || users[0];
            window.GindacoDB.setCurrentUser(target);
            alert(`Berhasil beralih ke akun: ${target.nama} (${target.level.toUpperCase()})`);
            window.location.reload();
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

