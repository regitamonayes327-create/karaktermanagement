# SIKAPDIK - Sistem Informasi Pemantauan Perilaku Siswa

**SD Negeri 04 Jatigunung**

Aplikasi berbasis web untuk pemantauan perkembangan karakter siswa, mencakup presensi QR Code, pencatatan perilaku, poin keteladanan dan pelanggaran, tindak lanjut pembinaan, prestasi, potensi siswa, dan dashboard manajerial.

## Teknologi

- PHP Native 8.2+
- MySQL/MariaDB
- Tailwind CSS (CDN)
- JavaScript
- QR Code Scanner (html5-qrcode)
- Chart.js

## Persyaratan Server

- PHP >= 8.0 dengan ekstensi: PDO, PDO_MySQL, mbstring, fileinfo, json
- MySQL >= 5.7 atau MariaDB >= 10.3
- Apache dengan mod_rewrite aktif
- HTTPS (wajib untuk akses kamera QR Scanner)

## Instalasi

### Cara Cepat (Recommended)

1. Upload seluruh isi folder `sikapdik/` ke root domain/subdomain hosting
2. Buka browser dan akses: `https://sikapdik.sdn4jatigunung.sch.id/install.php`
3. Isi konfigurasi database dan akun admin
4. Klik **Mulai Instalasi**
5. **HAPUS file `install.php`** setelah instalasi berhasil!

### Cara Manual

1. Upload seluruh file ke hosting
2. Import file `database/sikapdik.sql` ke MySQL database
3. Edit file `config/database.php` sesuai kredensial database Anda
4. Buat folder `uploads/`, `uploads/qrcodes/`, `uploads/prestasi/` dengan permission 755
5. Akses aplikasi melalui browser
6. Login dengan:
   - Username: `admin`
   - Password: (sesuai hash di database, gunakan install.php untuk set password)

## Struktur Folder

```
sikapdik/
├── config/          # Konfigurasi database & aplikasi
├── database/        # File SQL schema
├── includes/        # Core classes (Database, Auth, Security, Helpers)
├── modules/
│   ├── admin/       # Modul Admin/Operator
│   ├── auth/        # Login, logout, profil, ubah password
│   ├── guru_mapel/  # Modul Guru Mapel
│   ├── kepala_sekolah/ # Modul Kepala Sekolah
│   ├── orang_tua/   # Modul Orang Tua
│   └── wali_kelas/  # Modul Wali Kelas
├── templates/       # Header, footer, error pages
├── uploads/         # File upload (QR, prestasi)
├── assets/          # CSS, JS, Images (jika custom)
├── .htaccess        # Security & rewrite rules
├── index.php        # Entry point
├── install.php      # Installer (hapus setelah instalasi!)
└── README.md
```

## Fitur Lengkap

### Admin/Operator
- Dashboard operasional
- Manajemen pengguna, guru, kelas, siswa, orang tua
- Generate & cetak QR Code siswa
- Pengaturan kategori perilaku & poin
- Rekap presensi
- Laporan otomatis (presensi, perilaku, tindak lanjut, prestasi)
- Audit log
- Pengaturan sistem (sekolah, akademik, presensi, validasi)

### Kepala Sekolah
- Dashboard manajerial (persentase kehadiran, grafik, perbandingan kelas)
- Siswa perlu perhatian
- Siswa teladan
- Pemantauan tindak lanjut
- Laporan

### Wali Kelas
- Dashboard kelas
- Scan QR presensi (kamera)
- Presensi manual
- Input poin keteladanan & pelanggaran
- Validasi catatan guru mapel
- Tindak lanjut pembinaan
- Pencatatan prestasi & potensi siswa
- Profil siswa terpadu (multi-tab)
- Laporan kelas

### Guru Mapel
- Dashboard personal
- Input keteladanan siswa
- Input pelanggaran/pembinaan
- Riwayat input pribadi
- Kelas yang diajar

### Orang Tua
- Dashboard anak (presensi, perilaku positif, prestasi)
- Riwayat presensi
- Catatan keteladanan
- Prestasi anak
- Catatan pembinaan (yang ditampilkan sekolah)
- Notifikasi

## Keamanan

- Password hashing (bcrypt cost 12)
- CSRF token protection
- Prepared statements (SQL injection prevention)
- XSS sanitization
- Rate limiting login
- Session security (httponly, secure, strict mode)
- Role-based access control
- QR token menggunakan random bytes (bukan ID siswa)
- .htaccess protection untuk file sensitif

## Akun Default

Setelah instalasi via `install.php`:
- **Username**: sesuai yang Anda buat saat instalasi
- **Password**: sesuai yang Anda buat saat instalasi

## Lisensi

Hak Cipta © 2024-2025 SD Negeri 04 Jatigunung. Semua hak dilindungi.
