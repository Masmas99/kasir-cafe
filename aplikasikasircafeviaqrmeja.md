# PRD: Aplikasi Kasir Cafe dengan Pemesanan via QR Meja

**Versi:** 1.1 (Draft siap pakai untuk agent)
**Tanggal:** 3 November 2026
**Sumber:** Ringkasan percakapan klarifikasi kebutuhan (Pertanyaan 1-8 dan 28-31 terlihat; Pertanyaan 9-27 tidak tersedia, sehingga hal terkait ditandai `[ASUMSI]`)

---

## 0. Petunjuk untuk Agent

- Dokumen ini adalah **sumber kebenaran produk** (fitur, alur, aturan bisnis, kriteria penerimaan).
- Framework: **Laravel** (sudah terinstal di proyek). **Jangan** menginstal ulang atau mengubah struktur dasar proyek tanpa diminta.
- Dokumen ini tidak menentukan struktur folder maupun kode. Ikuti konvensi Laravel dan pola yang sudah ada di repo.
- Item bertanda `[ASUMSI]` belum dikonfirmasi pengguna; jika ragu, tanyakan sebelum mengimplementasikan.
- Item bertanda `[DIPUTUSKAN]` sudah dikonfirmasi pengguna.

---

## 1. Ringkasan Produk

Aplikasi kasir (POS) untuk **cafe** yang memungkinkan pelanggan **memindai QR code di meja**, melihat menu, memesan, dan membayar dari smartphone tanpa mengunduh aplikasi. Pesanan masuk real-time ke dashboard kasir. Kasir juga dapat memasukkan pesanan secara manual untuk meja tertentu. Pembayaran digital menggunakan **payment gateway DOKU**.

## 2. Tujuan

1. Mengurangi antrean dan beban pelayan saat jam sibuk.
2. Mengurangi kesalahan pencatatan pesanan.
3. Memberi pemilik cafe data penjualan yang akurat dan real-time.
4. Mencegah pesanan tercampur antar pelanggan pada meja yang sama (lihat aturan sesi meja).

## 3. Ruang Lingkup

### Dalam lingkup (MVP)
- Jenis usaha: **cafe / coffee shop** `[DIPUTUSKAN]`.
- Platform **web application** `[DIPUTUSKAN]`; pelanggan memesan via QR meja lewat browser HP, **tanpa login** `[DIPUTUSKAN]`.
- Sistem pemesanan **QR + Kasir**: pelanggan memesan mandiri, kasir juga bisa input manual `[DIPUTUSKAN]`.
- Dashboard Super Admin dan Kasir.
- Manajemen menu dan manajemen meja (termasuk QR per meja).
- Pembayaran **dua jalur**: di kasir atau online via payment gateway DOKU `[DIPUTUSKAN]`.
- Tabel transaksi/pesanan, kartu statistik, dan grafik penjualan.
- Tema Light/Dark Mode.

### Di luar lingkup
- **Takeaway dan delivery** `[DIPUTUSKAN]`: sistem hanya mendukung **dine-in**.
- Pemilihan ulang teknologi/stack dan proses instalasi `[DIPUTUSKAN]`.
- Role Admin, Kitchen/Barista, dan Waiter; hanya Super Admin dan Kasir yang dipakai `[DIPUTUSKAN]`.
- Pengelompokan meja per area (Indoor/Outdoor/VIP) `[ASUMSI]`; sistem meja bersifat dinamis tanpa area.
- Program loyalitas, voucher, multi-cabang `[ASUMSI]` (fase berikutnya).

## 4. Peran Pengguna

| Peran | Deskripsi | Perangkat utama |
|---|---|---|
| **Pelanggan** | Makan di cafe, memesan lewat QR meja | Smartphone |
| **Kasir** | Memantau pesanan, input pesanan manual, memproses pembayaran, menutup meja | Desktop/tablet/HP |
| **Super Admin** | Mengelola menu, meja, pengguna, pengaturan, dan laporan | Desktop/tablet/HP |

Hanya dua role dashboard: Super Admin dan Kasir `[DIPUTUSKAN]`. Pelanggan tidak perlu login. Aktivitas dapur (lewat akun Kasir atau tampilan khusus) belum ditentukan, lihat bagian 15. Hak akses detail ada di bagian 8.

## 5. Alur Pengguna Utama

### 5.1 Alur Pelanggan (via QR)
1. Pelanggan duduk dan memindai QR di meja.
2. Halaman menu terbuka dengan **nomor meja terisi otomatis**.
3. Pelanggan memilih menu, jumlah, dan catatan per item.
4. Pelanggan meninjau keranjang lalu mengirim pesanan.
5. Pesanan **langsung masuk ke kasir tanpa menunggu persetujuan** (direct ordering) `[DIPUTUSKAN]`.
5a. Pembayaran: pelanggan bebas memilih bayar online (QRIS/DOKU) atau bayar di kasir `[DIPUTUSKAN]`.
6. Pelanggan melihat status pesanan.
7. Pelanggan dapat menambah pesanan pada sesi meja yang sama selama sesi aktif; pesanan tambahan otomatis masuk ke tagihan meja aktif `[ASUMSI]`.

### 5.2 Alur Kasir
1. Pesanan baru muncul di dashboard dengan notifikasi visual/suara `[ASUMSI]`.
2. Kasir dapat melihat detail, mengubah status, atau membatalkan item (misalnya stok habis).
3. Kasir dapat membuat pesanan manual untuk meja tertentu (wajib memilih meja) `[DIPUTUSKAN]`.
4. Saat pembayaran, kasir dapat memisahkan tagihan meja bila diperlukan `[ASUMSI]`.
5. Setelah tagihan lunas, kasir mengonfirmasi **Tutup Meja** (lihat 6.4).

### 5.3 Alur Super Admin
1. Login ke dashboard.
2. Mengelola menu, kategori, harga, ketersediaan.
3. Mengelola meja dan mencetak/mengunduh QR meja.
4. Mengelola akun kasir, pengaturan cafe, dan melihat laporan.

---

## 6. Kebutuhan Fungsional

### 6.1 Pemesanan Pelanggan via QR

| ID | Kebutuhan | Prioritas |
|---|---|---|
| FR-01 | Setiap meja memiliki QR unik yang mengarah ke halaman menu dengan identitas meja | Must |
| FR-02 | Menu ditampilkan per kategori dengan nama, foto, deskripsi, harga, dan status tersedia/habis | Must |
| FR-03 | Pelanggan dapat menambah/mengurangi item, memberi catatan, dan melihat total | Must |
| FR-04 | Pelanggan dapat mengirim pesanan; pesanan terhubung ke meja dan sesi aktif, serta langsung masuk ke dashboard kasir tanpa persetujuan | Must |
| FR-05 | Pelanggan melihat status pesanan (misalnya: Menunggu, Diproses, Siap, Disajikan) | Must |
| FR-06 | Item berstatus habis tidak dapat dipesan | Must |
| FR-07 | Halaman pelanggan responsif dan ringan untuk smartphone | Must |
| FR-08 | Pelanggan dapat menambah pesanan selama sesi meja masih aktif | Must |
| FR-09 | Pelanggan tidak perlu login atau membuat akun untuk memesan | Must |

### 6.2 Dashboard Kasir

| ID | Kebutuhan | Prioritas |
|---|---|---|
| FR-10 | Daftar pesanan masuk real-time (atau pembaruan otomatis berkala) | Must |
| FR-11 | Tampilan status semua meja (tersedia, terisi, menunggu bayar) | Must |
| FR-12 | Input pesanan manual oleh kasir; **wajib memilih meja** (tidak ada pesanan tanpa meja) | Must |
| FR-13 | Ubah status pesanan dan batalkan item/pesanan dengan alasan | Must |
| FR-14 | Memproses pembayaran di kasir (tunai/lainnya `[ASUMSI]`) dan menandai lunas | Must |
| FR-15 | Cetak/tampilkan struk `[ASUMSI]` | Should |
| FR-16 | Tabel transaksi dengan filter tanggal, status, dan meja | Must |
| FR-17 | Kasir dapat memisahkan tagihan meja saat pembayaran (mis. pelanggan berkelompok) `[ASUMSI]` | Should |

### 6.3 Pembayaran (DOKU)

| ID | Kebutuhan | Prioritas |
|---|---|---|
| FR-20 | Integrasi payment gateway **DOKU** untuk pembayaran digital `[DIPUTUSKAN]` | Must |
| FR-21 | Status pembayaran diperbarui otomatis melalui notifikasi/callback DOKU | Must |
| FR-22 | Callback diverifikasi (tanda tangan/validasi) dan bersifat idempoten (tidak menimbulkan pembayaran ganda) | Must |
| FR-23 | Pembayaran gagal/kedaluwarsa tidak menandai pesanan lunas dan dapat diulang | Must |
| FR-24 | Pembayaran di kasir dicatat dengan metode dan nominal; pelanggan bebas memilih bayar di kasir atau online `[DIPUTUSKAN]` | Must |

### 6.4 Pengelolaan Sesi Meja `[DIPUTUSKAN: opsi 3 direkomendasikan, hasil akhir mengikuti keputusan pengguna]`

> Catatan: percakapan berhenti sebelum pengguna menjawab Pertanyaan 31. Opsi 3 adalah rekomendasi yang tertulis dan dipakai di dokumen ini. Ubah bila keputusan akhir berbeda.

| ID | Kebutuhan | Prioritas |
|---|---|---|
| FR-30 | Saat pesanan pertama dibuat di meja kosong, sistem membuka **sesi meja** baru | Must |
| FR-31 | Pesanan tambahan dari meja yang sama otomatis masuk ke tagihan sesi aktif; kasir dapat memisahkannya saat pembayaran (FR-17). Pertanyaan 8 belum dijawab; opsi 3 (fleksibel) direkomendasikan | Must |
| FR-32 | Setelah pembayaran lunas, sistem **otomatis menandai meja "Lunas"** | Must |
| FR-33 | Meja berstatus Lunas **belum tersedia** untuk pelanggan berikutnya sampai kasir menekan **Tutup Meja** | Must |
| FR-34 | Pesanan baru dari QR pada meja berstatus Lunas **tidak boleh masuk** ke tagihan sebelumnya; tampilkan pesan bahwa meja sedang diproses/ hubungi kasir | Must |
| FR-35 | Setelah Tutup Meja, sesi ditutup dan meja kembali tersedia; pesanan berikutnya membuka sesi baru | Must |
| FR-36 | Tutup Meja dicatat (siapa, kapan) dalam log aktivitas | Should |

### 6.5 Manajemen Menu dan Meja (Super Admin)

| ID | Kebutuhan | Prioritas |
|---|---|---|
| FR-40 | CRUD kategori dan menu (nama, deskripsi, harga, foto, status tersedia) | Must |
| FR-41 | Manajemen meja dinamis: tambah, ubah nomor/nama, dan hapus meja `[DIPUTUSKAN]` | Must |
| FR-42 | Generate QR otomatis untuk setiap meja, dapat diunduh dan dicetak | Must |
| FR-43 | CRUD akun kasir dan pengaturan hak akses | Must |
| FR-44 | Pengaturan dasar cafe (nama, logo, pajak/service charge `[ASUMSI]`) | Should |
| FR-45 | Super Admin melihat status meja (tersedia, terisi, sedang digunakan) | Must |

### 6.6 Dashboard dan Laporan

| ID | Kebutuhan | Prioritas |
|---|---|---|
| FR-50 | Kartu statistik (misalnya penjualan hari ini, jumlah pesanan, meja aktif) | Must |
| FR-51 | Grafik penjualan (harian/mingguan/bulanan) | Must |
| FR-52 | Tabel transaksi dan pesanan dengan pencarian dan filter | Must |
| FR-53 | Ekspor laporan `[ASUMSI]` | Could |

---

## 7. Kebutuhan UI/UX

| ID | Kebutuhan |
|---|---|
| UX-01 | Konsep: **Modern POS Dashboard** `[DIPUTUSKAN]`: sidebar navigasi modern, kartu statistik, grafik penjualan, tabel transaksi/pesanan, manajemen menu dan meja |
| UX-02 | Desain bersih, profesional, mudah digunakan |
| UX-03 | **Responsif** untuk desktop, tablet, dan smartphone `[DIPUTUSKAN]`; dashboard Super Admin dan Kasir dapat dipakai di HP |
| UX-04 | **Light Mode + Dark Mode** `[DIPUTUSKAN]`: pengguna mengganti tema lewat pengaturan |
| UX-05 | Preferensi tema **tersimpan pada akun pengguna** (Super Admin dan Kasir) |
| UX-06 | Dashboard Super Admin dan Kasir mendukung kedua tema sepenuhnya |
| UX-07 | Halaman menu pelanggan responsif; tema untuk pelanggan mengikuti pengaturan sistem/perangkat `[ASUMSI]` |
| UX-08 | Aksen warna mengikuti identitas cafe `[ASUMSI]` |

---

## 8. Hak Akses `[ASUMSI]`

| Fitur | Pelanggan | Kasir | Super Admin |
|---|:---:|:---:|:---:|
| Lihat menu dan memesan via QR | Ya | Ya (manual) | Ya |
| Lihat pesanan masuk dan ubah status | - | Ya | Ya |
| Input pesanan manual | - | Ya | Ya |
| Proses pembayaran di kasir | - | Ya | Ya |
| Tutup Meja | - | Ya | Ya |
| Batalkan pesanan/item | - | Ya (dengan alasan) | Ya |
| Kelola menu | - | - | Ya |
| Kelola meja dan QR | - | - | Ya |
| Kelola pengguna dan pengaturan | - | - | Ya |
| Lihat laporan lengkap | - | Terbatas | Ya |
| Ganti tema (tersimpan di akun) | - | Ya | Ya |

---

## 9. Aturan Bisnis

1. Sistem hanya melayani **dine-in**; setiap pesanan **wajib terhubung ke meja**.
2. Satu meja hanya memiliki **satu sesi aktif** pada satu waktu.
3. Pesanan dalam satu sesi meja digabung dalam satu tagihan secara default; kasir dapat memisahkannya saat pembayaran `[ASUMSI]`.
4. Meja berstatus **Lunas** tidak menerima pesanan baru sampai kasir **Tutup Meja**.
5. Pesanan tidak dianggap lunas sampai pembayaran terkonfirmasi (callback DOKU tervalidasi atau konfirmasi kasir).
6. Item berstatus habis tidak dapat dipesan.
7. Pembatalan pesanan/item oleh kasir wajib mencantumkan alasan `[ASUMSI]`.

## 10. Status Utama `[ASUMSI]`

- **Pesanan:** Menunggu → Diproses → Siap → Disajikan; atau Dibatalkan.
- **Pembayaran:** Belum dibayar → Menunggu pembayaran → Lunas; atau Gagal/Kedaluwarsa.
- **Meja:** Tersedia → Terisi → Lunas (menunggu ditutup) → Tersedia (setelah Tutup Meja).

---

## 11. Kebutuhan Non-Fungsional

- **Keamanan:** autentikasi untuk Super Admin dan Kasir, otorisasi berbasis peran, HTTPS, validasi callback DOKU, tidak menyimpan data kartu, log aktivitas untuk pembatalan/tutup meja.
- **Perlindungan QR:** QR tidak boleh membuka sesi di meja yang nonaktif; pertimbangkan token sesi agar QR tidak mudah disalahgunakan dari luar cafe `[ASUMSI]`.
- **Performa:** halaman menu pelanggan termuat cepat pada jaringan seluler; pesanan tampil di kasir dalam hitungan detik `[ASUMSI]`.
- **Keandalan:** konsistensi data transaksi (tidak ada pesanan ganda/hilang), penanganan callback ulang dari DOKU.
- **Kompatibilitas:** browser modern di Android/iOS dan desktop.
- **Pemeliharaan:** perubahan konfigurasi (menu, meja, tema) tidak memerlukan perubahan kode.

---

## 12. Kriteria Penerimaan (Contoh Utama)

**Pemesanan QR**
- Diberikan QR meja 5, ketika dipindai, maka halaman menu terbuka dan pesanan yang dikirim tercatat untuk Meja 5.
- Diberikan item berstatus habis, maka pelanggan tidak dapat menambahkannya ke keranjang.

**Sesi meja**
- Diberikan Meja 5 sudah Lunas tetapi belum ditutup, ketika pelanggan lain memindai QR dan mencoba memesan, maka pesanan **tidak** masuk ke tagihan sebelumnya dan pelanggan melihat pesan yang sesuai.
- Diberikan kasir menekan Tutup Meja pada Meja 5 yang Lunas, maka meja kembali Tersedia dan pesanan berikutnya membuat sesi baru.
- Diberikan Meja 5 belum Lunas, maka kasir tidak dapat menutup meja.

**Pembayaran**
- Diberikan pembayaran DOKU berhasil, maka status pesanan menjadi Lunas otomatis tanpa aksi kasir.
- Diberikan callback DOKU yang sama diterima dua kali, maka pembayaran hanya tercatat sekali.
- Diberikan pembayaran gagal/kedaluwarsa, maka pesanan tetap belum lunas dan dapat dibayar ulang.

**Tema dan perangkat**
- Diberikan pengguna memilih Dark Mode lalu login dari perangkat lain, maka tema Dark Mode tetap diterapkan.
- Diberikan layar HP, tablet, dan desktop, maka seluruh dashboard dapat digunakan tanpa elemen terpotong.

**Hak akses**
- Diberikan akun Kasir, maka menu pengelolaan pengguna dan pengaturan tidak dapat diakses.

---

## 13. Metrik Keberhasilan `[ASUMSI]`

| Metrik | Target |
|---|---|
| Waktu dari scan QR sampai pesanan terkirim | < 3 menit |
| Pesanan dikoreksi/dikomplain | < 2% |
| Pesanan yang masuk via QR | > 60% |
| Pesanan salah masuk ke tagihan meja | 0 kasus |

---

## 14. Risiko dan Mitigasi

| Risiko | Mitigasi |
|---|---|
| Pelanggan baru memesan di meja yang tagihannya belum ditutup | Aturan sesi meja (6.4): meja Lunas harus ditutup kasir |
| QR dipindai dari luar cafe/pesanan iseng | Token sesi meja, konfirmasi kasir, atau pembayaran di muka |
| Callback DOKU terlambat/ganda | Idempotensi dan pengecekan status pembayaran |
| Kasir lupa menekan Tutup Meja | Penanda visual meja menunggu ditutup di dashboard |
| Internet cafe tidak stabil | Pesan galat jelas dan opsi input manual oleh kasir |

---

## 15. Pertanyaan Terbuka

1. Jawaban final Pertanyaan 8 (opsi 3 fleksibel dipakai sementara) dan Pertanyaan 31 (opsi 3 dipakai sementara).
2. Aktivitas dapur/barista: dikelola lewat akun Kasir atau tampilan khusus dapur?
3. Pertanyaan 9-27 tidak termuat di percakapan; cocokkan keputusan tersebut dengan dokumen ini.
4. Apakah pelanggan wajib bayar di muka atau boleh bayar setelah makan?
5. Metode pembayaran DOKU yang diaktifkan (QRIS, e-wallet, VA, kartu)?
6. Apakah perlu pajak/service charge dan cetak struk?
7. Apakah perlu notifikasi suara atau printer dapur?
8. Apakah ada stok/inventori sederhana?
