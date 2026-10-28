# QC Inspect

Web app Laravel untuk pencatatan dan pelaporan inspeksi quality control. Aplikasi ini menggunakan Blade dan SQLite, dengan alur master data, pemeriksaan produk, ringkasan dashboard, riwayat inspeksi, serta ekspor Excel.

## Menjalankan aplikasi

Pastikan PHP 8.2+, Composer, dan ekstensi PDO SQLite tersedia. Ekstensi GD diperlukan agar DomPDF menyertakan gambar point check; jika GD belum aktif, PDF tetap dapat diunduh dan laporan memberi keterangan bahwa gambar tidak disertakan. Untuk menyertakan gambar, aktifkan `extension=gd` pada `php.ini` XAMPP lalu restart Apache/PHP. Dari folder proyek:

```sh
composer install
php artisan migrate --seed
php artisan qc:create-admin
php artisan serve
```

Ikuti prompt untuk membuat nama, email, dan kata sandi admin. Kata sandi minimal 12 karakter. Buka URL yang ditampilkan oleh `artisan serve` dan masuk dengan akun tersebut. Jangan jalankan `migrate:fresh` pada database yang berisi data karena perintah tersebut menghapus seluruh tabel.

Untuk menambah akun admin berikutnya, jalankan lagi `php artisan qc:create-admin` dengan email yang berbeda. Semua akun yang dibuat melalui perintah ini memiliki akses penuh ke data QC.

## Contoh database

`database/exports/Checksheet-SME-anonymized.mysql.sql` adalah dump MySQL berisi data contoh yang telah dianonimkan untuk dibagikan di repositori. Kredensial, kontak, narasi, bukti gambar, dan data sesi asli tidak disertakan. Impor file ini ke database MySQL baru yang kosong; file ini bukan backup operasional.

## Fitur

- Dashboard dengan ringkasan jumlah produk, inspeksi, hasil lulus/NG, grafik aktivitas 14 hari, dan inspeksi terbaru.
- Filter dashboard untuk rentang tanggal, part, tipe produk, area/line, shift, dan hasil; menampilkan persentase hasil inspeksi dan akurasi dimensi dari seluruh hasil ukur OK/NG dengan diagram lingkaran, serta monitoring part/tipe berangka.
- Filter X-Chart diterapkan otomatis ketika pilihan berubah; produk, tipe, dan area/line saling membatasi pilihan, pemilihan produk menyelaraskan tipe serta area, dan parameter dimensi mengikuti produk terpilih.
- Pemilihan folder gedung sebelum input; area/line mengikuti produk pada gedung dan tidak perlu dipilih lagi saat inspeksi.
- Pencatatan inspeksi per tanggal, produk, inspector, shift, sesi, dan jumlah sample. Nama inspector akun yang login diisi otomatis. Inspector hanya dapat melihat hasil miliknya; admin dapat melihat seluruh hasil.
- Pengukuran dimensi mendukung mode Manual dan Otomatis. Mode Otomatis menyiapkan tombol pembacaan per parameter melalui event adaptor `qc:measurement:read`. Adaptor menangani event tersebut melalui `window.addEventListener('qc:measurement:read', event => { event.preventDefault(); event.detail.resolve(nilaiNumerik); })`; gunakan `event.detail.reject(error)` bila pembacaan gagal. Adaptor perangkat Bluetooth/Wi-Fi harus disesuaikan setelah merek/model dan protokol alat diketahui. Data pengukuran dan mode sumbernya tersimpan pada inspeksi, laporan dan ekspor CSV; jika adaptor belum tersedia, operator dapat kembali ke mode Manual. Pemeriksaan visual dan fungsi tetap manual.
- Satu foto terpisah untuk setiap parameter yang NG; bukti tersimpan privat dan tautan bukti bertanda tangan yang berlaku 24 jam dikirim melalui email.
- X-Chart untuk kestabilan parameter dimensi yang dipilih, menampilkan rata-rata ukur per hari terhadap batas bawah/atas dari toleransi master serta filter produk, parameter, area, shift, tipe, hasil, dan tanggal.
- Master area mendukung folder gedung dengan area/line di bawahnya. Data area lama tetap dapat dipakai sebagai folder gedung.
- Gambar visual point check diunggah bersama data produk (JPG, PNG, atau WebP, maksimal 25 MB) dan menjadi acuan inspeksi dimensi, visual, dan fungsi; formulir Standard Visual hanya digunakan untuk checklist visual.
- Akun akses penuh/admin dapat menyetujui laporan; laporan yang disetujui menampilkan stempel QC 2W DPM serta nama dan waktu pemberi approval.
- Akun dengan tiga peran: hanya lihat, input inspeksi, dan akses penuh/admin. Hanya admin dalam aplikasi yang dapat mengelola akun dan master data.
- Pemeriksaan dimensi yang menghitung status OK/NG di server dari nilai standar dan toleransi; checklist visual dan fungsi dengan catatan serta jenis defect.
- Menu Laporan Inspeksi menampilkan riwayat hasil terpisah dari formulir Inspeksi QC, dilengkapi filter, ekspor Excel (.xlsx), dan unduh PDF. Laporan PDF dapat digabung per tanggal, shift, dan part; laporan merangkum sesi Start, Middle, dan End, tipe part, gambar point check, serta hasil setiap sesi.
- CRUD master produk, inspector, defect, dan standar dimensi, visual, serta fungsi per produk.
- Penambahan standar dimensi, visual, dan fungsi difilter terlebih dahulu dengan line terdaftar dan tipe part; tabel standar juga menunjukkan tipe serta line part.
- Modul Temuan QC 2W DPM yang dikelola Laravel: dashboard status/kategori/monitoring part/PIC, register dengan filter dan ekspor CSV, input dan edit temuan 4M, prioritas, bukti foto privat, serta riwayat tindak lanjut.
- Setiap hasil inspeksi NG langsung membuka formulir analisis 4M satu per satu untuk tiap point check yang gagal; tanggal/shift/produk/area/hasil ukur terisi otomatis dan foto wajib dari inspeksi dilampirkan ulang secara privat. Riwayat menampilkan jumlah tindak lanjut 4M yang masih perlu dibuat dan menyediakan aksi untuk melanjutkan.
- PIC dapat membuka tautan token Laravel untuk mengirim rencana/tanggal target dan laporan penyelesaian; reporter atau admin dapat memverifikasi atau mengembalikan perbaikan. Admin mengelola PIC dan pilihan area/line, part, tipe, serta jenis masalah. Akun tetap memakai role Laravel yang sama.
- Autentikasi sesi Laravel, pembatasan percobaan login, CSRF, dan pengelolaan akun melalui perintah Artisan.

Data contoh dimasukkan oleh `php artisan migrate --seed`. Seed dapat dijalankan ulang tanpa menggandakan data contoh.

Setiap inspector harus memiliki email yang valid di Master Data. Hasil inspeksi NG otomatis dikirim ke email inspector, dan temuan 4M otomatis dikirim ke email PIC. Status waktu kirim hanya diperbarui setelah pengiriman email berhasil; pengiriman ulang tersedia dari riwayat/detail. Tautan bukti inspeksi bertanda tangan berlaku 24 jam, sedangkan tautan tindak lanjut PIC memakai token acak yang disimpan oleh Laravel.

Atur SMTP di file `.env` sebelum mengaktifkan pengiriman email di lingkungan selain testing: `MAIL_MAILER=smtp`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_SCHEME` (`smtp` umumnya untuk port 587/STARTTLS atau `smtps` untuk port 465, ikuti penyedia), serta `MAIL_FROM_ADDRESS` dan `MAIL_FROM_NAME`. Setelah mengubah konfigurasi, jalankan `php artisan config:clear`. Kredensial SMTP harus disimpan hanya di `.env`, bukan di kode atau repositori. Jika SMTP belum diatur, aplikasi menyimpan inspeksi/temuan dan menampilkan peringatan agar email dapat dicoba ulang.

Akun admin dapat mengatur data PIC dan emailnya serta pilihan yang tersedia melalui menu Pengaturan Temuan; pengelolaan akun dilakukan dari Master Data → Akun pengguna.

## Pengujian

```sh
php artisan test
```
