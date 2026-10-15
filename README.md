# GINDACO SPK • Mall Artha Gading
### Sistem Pendukung Keputusan Pengadaan Bahan Baku (Single Exponential Smoothing, Recipe Explosion BOM & Dynamic Safety Stock)
Project Skripsi Teknik Informatika - Wildan Ari Leksono

---

## 🚀 Fitur Unggulan Versi Static (Zero Server / Pure Client-Side)
Aplikasi ini telah dikonversi secara penuh menjadi **Pure Static HTML, CSS, & JavaScript** sehingga dapat dijalankan secara instan **tanpa memerlukan server PHP maupun database MySQL**. 

- **100% Client-Side LocalStorage Database Engine:** Seluruh data master (64 bahan baku, 115 menu, 110 resep BOM, dan ratusan data historis) telah di-seed dan berjalan secara offline/lokal di browser.
- **Client-Side CSV Parser (FileReader API):** Unggah 2 file CSV laporan penjualan POS mingguan langsung diparsing di memori browser dengan filter otomatis `void == False`.
- **Pure JavaScript Recipe Explosion (BOM Mapping):** Mengonversi transaksi penjualan menu Takoyaki & Taiyaki ke kebutuhan gramasi bahan baku secara otomatis di sisi klien.
- **Dynamic Safety Stock Controller:** Buffer cadangan fleksibel (1 hari, 2 hari, 15%, 20%, atau custom) yang langsung menghitung ulang target stok dan rekomendasi order SPK secara real-time.
- **Standar Cetak Dokumen Resmi:** Dilengkapi kop surat resmi Gindaco Mall Artha Gading, logo resolusi tinggi, alamat store, dan footer blok tanda tangan sah.

---

## 🌐 Cara Deploy ke GitHub Pages (Gratis & Cepat)
1. Buat repository baru di GitHub (misal: `gindaco-spk`).
2. Upload seluruh file dari folder projek ini ke repository GitHub Anda:
   ```bash
   git init
   git add .
   git commit -m "Deploy Gindaco SPK Static Web App"
   git branch -M main
   git remote add origin https://github.com/USERNAME/gindaco-spk.git
   git push -u origin main
   ```
3. Di halaman repository GitHub Anda, klik menu **Settings** > **Pages**.
4. Pada bagian **Build and deployment** > **Branch**, pilih `main` dan folder `/(root)`, lalu klik **Save**.
5. Tunggu sekitar 1–2 menit, website Anda akan langsung aktif di URL:
   `https://USERNAME.github.io/gindaco-spk/`

---

## ▲ Cara Deploy ke Vercel (Gratis & Rekomendasi)
1. Buka [vercel.com](https://vercel.com/) dan login menggunakan akun GitHub Anda.
2. Klik tombol **Add New...** > **Project**.
3. Pilih repository `gindaco-spk` Anda, lalu klik **Import**.
4. Biarkan konfigurasi default (Framework Preset: *Other*, Root Directory: `./`).
5. File `vercel.json` sudah disediakan dalam projek untuk mengelola routing static.
6. Klik **Deploy**. Dalam 15 detik, aplikasi Anda sudah live dengan domain kustom Vercel (contoh: `https://gindaco-spk.vercel.app/`).

---

## 💻 Menjalankan di Komputer Lokal (Tanpa XAMPP)
Karena sudah berformat static murni, Anda bahkan tidak perlu menyalakan Apache/MySQL di XAMPP!

### Opsi 1: Buka Langsung di Browser
Cukup klik ganda file `index.html` pada File Explorer untuk membukanya di Google Chrome / Edge.

### Opsi 2: Menggunakan VS Code Live Server
1. Buka folder projek di VS Code.
2. Klik kanan pada file `index.html` dan pilih **Open with Live Server**.

### Opsi 3: Menggunakan Python HTTP Server
Jalankan perintah berikut di terminal:
```bash
python -m http.server 8000
```
Buka browser pada `http://localhost:8000`.

---

## 👤 Akun Demo Bawaan
Tersedia tombol **Akses Cepat Demo (One-Click Login)** di halaman [login.html](login.html):
* **Store Manager:** NIK: `manager` | Password: `password`
* **Staff Inventory:** NIK: `staff` | Password: `password`
* **Administrator:** NIK: `admin` | Password: `admin`
