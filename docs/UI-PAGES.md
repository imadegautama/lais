# Daftar Halaman & Isi UI

Dokumen ini adalah turunan dari [PRD.md](PRD.md), untuk brainstorming dan membangun frontend (Laravel 13 + Inertia 2 + React 19 + TypeScript + Tailwind 4 + shadcn/ui).

**Prioritas**

- **P0**: dibutuhkan skenario demo (PRD §2.6), jadi wajib ada.
- **P1**: bagian dari MVP, tapi tidak muncul di video.
- **P2**: pelengkap, dikerjakan jika sempat.

**Yang sudah disediakan starter kit React Laravel 13:**

- layout sidebar/header
- halaman auth (Fortify): login, register, reset password, verifikasi
- settings: profil, password, tampilan (light/dark)
- komponen shadcn/ui

Starter kit tinggal disesuaikan. Login diganti ke nomor WA (Fortify `username` → `phone`) dan verifikasi email diganti OTP WA.

---

## Peta Halaman

| #     | Halaman                       | Route                                 | Pengguna             | Prioritas             | PRD               |
| ----- | ----------------------------- | ------------------------------------- | -------------------- | --------------------- | ----------------- |
| **A** | **Publik**                    |                                       |                      |                       |                   |
| A1    | Landing page                  | `/`                                   | Juri, calon pengguna | P1                    | (baru)            |
| A2    | Halaman toko                  | `/t/{slug}`                           | Pelanggan            | P0                    | US-08             |
| A3    | Checkout / form pesanan       | `/t/{slug}/pesan`                     | Pelanggan            | P0                    | US-08             |
| A4    | Status pesanan & nota         | `/p/{token}`                          | Pelanggan            | P0                    | US-10             |
| **B** | **Auth & onboarding**         |                                       |                      |                       |                   |
| B1    | Daftar                        | `/register`                           | Pemilik              | P0                    | US-01             |
| B2    | Verifikasi OTP WA             | `/verify-phone`                       | Pemilik              | P0                    | US-01             |
| B3    | Masuk                         | `/login`                              | Pemilik              | P0                    | US-01             |
| B4    | Lupa & reset password         | `/forgot-password`, `/reset-password` | Pemilik              | P2                    | US-01             |
| B5    | Onboarding wizard             | `/onboarding`                         | Pemilik              | P0                    | US-01             |
| **C** | **Aplikasi pemilik**          |                                       |                      |                       |                   |
| C1    | Dashboard                     | `/dashboard`                          | Pemilik              | P0                    | US-13             |
| C2    | Daftar transaksi              | `/transaksi`                          | Pemilik              | P0                    | US-05             |
| C3    | Catat transaksi               | `/transaksi/baru`                     | Pemilik              | P0                    | US-05, US-07      |
| C4    | Detail transaksi              | `/transaksi/{id}`                     | Pemilik              | P1                    | US-05             |
| C5    | Daftar produk & stok          | `/produk`                             | Pemilik              | P0                    | US-02             |
| C6    | Form produk (tambah/ubah)     | `/produk/baru`, `/produk/{id}/edit`   | Pemilik              | P0                    | US-02             |
| C7    | Detail produk                 | `/produk/{id}`                        | Pemilik              | P1                    | US-03             |
| C8    | Koreksi stok (opname)         | `/produk/opname`                      | Pemilik              | P1                    | US-04             |
| C9    | Daftar pesanan                | `/pesanan`                            | Pemilik              | P0                    | US-09             |
| C10   | Detail pesanan                | `/pesanan/{kode}`                     | Pemilik              | P0                    | US-09             |
| C11   | Utang-piutang                 | `/utang-piutang`                      | Pemilik              | P1                    | US-11             |
| C12   | Kontak (pelanggan & supplier) | `/kontak`, `/kontak/{id}`             | Pemilik              | P1                    | §4.5 (hapus data) |
| C13   | Pusat laporan                 | `/laporan`                            | Pemilik              | P0                    | US-12             |
| C14   | Laba rugi                     | `/laporan/laba-rugi`                  | Pemilik              | P0                    | US-12             |
| C15   | Neraca                        | `/laporan/neraca`                     | Pemilik              | P0                    | US-12             |
| C16   | Buku kas                      | `/laporan/buku-kas`                   | Pemilik              | P1                    | US-12             |
| C17   | Perubahan modal               | `/laporan/perubahan-modal`            | Pemilik              | P0                    | US-12             |
| C18   | Produk terlaris & margin      | `/laporan/produk`                     | Pemilik              | P1                    | US-12             |
| C19   | Asisten AI                    | `/asisten` (+ panel samping)          | Pemilik              | P0                    | US-14             |
| C20   | Pengaturan                    | `/pengaturan/*`                       | Pemilik              | P0 (WA), P1 (lainnya) | US-01, US-15      |
| C21   | Log aktivitas                 | `/log-aktivitas`                      | Pemilik              | P1                    | §4.5              |
| C22   | Kas & rekening                | `/rekening`                           | Pemilik              | P1                    | US-07             |
| **D** | **Sistem**                    |                                       |                      |                       |                   |
| D1    | Halaman error & kosong        | 404, 403, 500, toko tutup             | Semua                | P2                    |                   |

Totalnya sekitar 30 layar. Sebanyak 19 di antaranya P0.

---

## Layout Global

### Layout aplikasi pemilik

- **Desktop (≥ 1024 px):** sidebar kiri yang bisa diciutkan jadi ikon, plus top bar.
- **Mobile:** top bar + bottom navigation berisi 5 slot: Beranda · Transaksi · **[ + Catat ]** · Pesanan · Lainnya. Tombol tengah lebih menonjol.
- **Menu sidebar:** Dashboard, Transaksi, Pesanan, Kas & Rekening, Produk & Stok, Utang-Piutang, Laporan, Kontak, Asisten AI, Log aktivitas, Pengaturan.
- **Top bar:**
    - nama usaha
    - tombol **Catat** (desktop)
    - tombol **Tanya AI** yang membuka panel asisten (Sheet kanan)
    - badge jumlah pesanan baru
    - avatar dengan menu (profil, keluar)
- **Banner status** di atas konten jika WA belum terhubung atau ada stok minus.

### Tombol "+ Catat" (dari semua halaman)

Membuka action sheet (Drawer di mobile, Dialog di desktop) berisi 5 pilihan besar:

- Penjualan
- Belanja
- Biaya
- Utang/Piutang
- Modal

Setiap pilihan langsung membuka C3 dengan tab yang sesuai. Di bawahnya ada catatan kecil: "Lebih cepat lewat WA: kirim _jual 3 nasgor_ ke bot".

### Layout publik (toko & nota)

Satu kolom, maksimal 640 px, dioptimalkan untuk HP. Header berisi logo dan nama toko. Footer kecil "Dibuat dengan [Nama Produk]".

---

## A. Halaman Publik

### A1. Landing page `/`

Tujuannya menjelaskan produk ke juri dan calon pengguna dalam 30 detik.

- **Hero:** judul nilai utama ("Catat usaha cukup lewat chat WA"), subjudul, tombol **Daftar gratis** dan **Lihat toko contoh**.
- **Mockup:** HP berisi percakapan WA (jual 3 nasgor → konfirmasi → tersimpan) di samping cuplikan dashboard.
- **Masalah:** 3 kartu, yaitu uang tercampur, stok tidak terkontrol, dan tidak sempat mencatat. Masing-masing dengan satu angka data.
- **Cara kerja:** 3 langkah sesuai tiga saluran (WA, dashboard, halaman toko).
- **Fitur utama:** 6 kartu ikon.
- **Untuk siapa:** kuliner, kelontong, fashion, kerajinan (dua mode produk).
- **CTA penutup** + footer.

### A2. Halaman toko `/t/{slug}`

- **Header toko:**
    - logo, nama toko, deskripsi singkat
    - badge **Buka** / **Tutup**
    - tombol **Hubungi penjual** (`wa.me`)
- **Pencarian produk:** input di bagian atas.
- **Grid produk** (2 kolom di HP, 3–4 di desktop):
    - Kartu berisi foto (rasio 1:1, ada placeholder), nama, dan harga.
    - Tombol **Tambah** berubah jadi stepper `− 2 +` setelah ditekan.
    - Produk habis diberi badge **Habis** dan tampil abu-abu.
- **Bar keranjang lengket di bawah:** "3 item · Rp45.000 · **Lanjut pesan →**". Hanya muncul jika keranjang tidak kosong.
- **State:** toko tutup (banner, tombol pesan nonaktif), katalog kosong, dan slug tidak ditemukan (D1).

### A3. Checkout `/t/{slug}/pesan`

- **Ringkasan keranjang:** daftar item dengan stepper qty, subtotal, total, dan tautan "Ubah pesanan".
- **Form data pemesan:**
    - Nama
    - Nomor WA (placeholder `08…`, validasi format)
    - **Ambil sendiri / Diantar** (segmented control). Jika diantar, muncul textarea alamat.
    - Waktu ambil/antar (pilih tanggal + jam)
    - Catatan (opsional, misalnya "pedas level 2")
- **Catatan privasi:** "Data hanya dipakai untuk pesanan ini".
- **Tombol Kirim pesanan** dengan state loading. Field honeypot tersembunyi.
- **Error:** pesan per field dan pesan rate limit ("Terlalu banyak pesanan, coba lagi 10 menit lagi").

### A4. Status pesanan & nota `/p/{token}`

- **Header:** kode pesanan (A012), tanggal, nama toko.
- **Stepper status:** Menunggu konfirmasi → Diproses → Siap/Diantar → Selesai, dengan waktu tiap langkah. Pesanan yang ditolak tampil sebagai banner merah.
- **Rincian:** tabel item (nama, qty, harga, subtotal), total, **sudah dibayar**, **sisa**, dan badge status bayar (Belum bayar / DP / Lunas).
- **Kartu pembayaran:**
    - gambar QRIS toko (bisa diperbesar dan diunduh)
    - nominal yang harus dibayar
    - petunjuk 3 langkah
    - catatan "Konfirmasi pembayaran dilakukan penjual"
- **Info ambil/antar:** metode, waktu, alamat.
- **Aksi:** **Hubungi penjual** (`wa.me` dengan pesan berisi kode pesanan) dan **Simpan/Cetak nota** (tampilan cetak via `@media print`).
- **Auto-refresh status** setiap 30 detik (Inertia polling).

---

## B. Auth & Onboarding

### B1. Daftar `/register`

- Field: nama, nomor WA, password, konfirmasi password.
- Penjelasan: "Kode verifikasi akan dikirim ke WA Anda".
- Tautan ke halaman masuk. Layout auth bertipe _split_: kiri form, kanan ilustrasi atau cuplikan chat WA.

### B2. Verifikasi OTP `/verify-phone`

- Teks: "Masukkan 6 digit kode yang kami kirim ke WA 0812-xxxx-1234 dari nomor **[nomor bot]**".
- Input OTP 6 kotak (shadcn `InputOTP`) yang otomatis terkirim begitu 6 digit terisi.
- Hitung mundur "Kirim ulang dalam 0:45" + tautan **Ganti nomor**.
- **Error:** kode salah (sisa percobaan), kedaluwarsa, dan batas permintaan tercapai.

### B3. Masuk `/login`

- Nomor WA, password, "Ingat saya", tautan **Lupa password**, tautan daftar.

### B4. Lupa & reset password

Satu alur 3 langkah: nomor WA → OTP (memakai ulang B2) → password baru.

### B5. Onboarding wizard `/onboarding`

- Stepper di atas dengan tombol **Kembali / Lanjut**. Setiap langkah tersimpan otomatis, jadi wizard bisa dilanjutkan nanti.
- **Langkah:**
    1. **Profil usaha:**
        - nama usaha
        - jenis usaha (kuliner / kelontong / fashion / kerajinan / lainnya), untuk memilih contoh produk
        - link toko (`/t/` + slug, dicek ketersediaannya secara langsung)
        - logo (opsional)
        - upload gambar QRIS
    2. **Uang usaha saat ini:** saldo kas tunai dan saldo non-tunai (QRIS/rekening). Di sini ada edukasi singkat: "Hanya uang usaha, jangan dicampur uang rumah tangga".
    3. **Peralatan usaha:** baris dinamis (nama + perkiraan nilai), misalnya kompor Rp1,5 jt. Bisa dilewati.
    4. **Produk & bahan:**
        - Tambah cepat dalam tabel: nama, jenis, harga jual, stok awal, biaya per unit.
        - Tombol **Pakai contoh** (template sesuai jenis usaha).
        - Tombol **Lewati, nanti saja**.
    5. **Utang awal** (opsional): ke siapa dan berapa.
- **Ringkasan akhir:**
    - Kartu neraca awal: total aset − utang = **Modal awal Anda Rp X**, dengan badge "Seimbang ✓".
    - Kartu **Hubungkan WhatsApp**: nomor bot, tombol "Simpan kontak bot" (vCard), dan tombol `wa.me` "Kirim _halo_ ke bot".
    - Tombol **Masuk ke dashboard**.

---

## C. Aplikasi Pemilik

### C1. Dashboard `/dashboard`

- **Header:** sapaan + tanggal, dan pemilih periode (Hari ini / Minggu ini / Bulan ini).
- **Baris kartu KPI** (4 kartu, 2×2 di HP):
    - Penjualan (Rp + jumlah transaksi, persentase vs periode lalu)
    - Laba bersih
    - Saldo kas usaha (rincian tunai/non-tunai)
    - **Diambil untuk pribadi**
- **Kartu "Dua Dompet"** (fitur pembeda): visual dua kantong, yaitu kas usaha vs diambil pribadi bulan ini, dengan teks "Pengambilan pribadi = 35% dari laba".
- **Kartu Kesehatan Usaha:** 4 indikator (laba > 0, kas cukup, utang ≤ modal, prive ≤ laba). Setiap indikator diberi ikon ✓/⚠, warna, dan satu kalimat penjelasan. Ada tombol **Tanya AI kenapa**.
- **Kartu Perubahan Modal:** modal awal + laba − diambil pribadi = modal akhir, dalam bentuk mini waterfall/bar.
- **Grafik penjualan 30 hari:** area chart (shadcn Chart/Recharts) dengan tooltip per hari.
- **Stok menipis:** daftar 5 item berisi stok, satuan, dan "habis ±2 hari". Tombol **Catat belanja** langsung membuka C3 tab Belanja dengan item terisi.
- **Pesanan aktif:** 5 pesanan terbaru (kode, nama, waktu, status), tautan ke C9.
- **Utang & piutang:** dua angka total dengan tautan ke C11.
- **Aktivitas terbaru:** 10 transaksi terakhir, lengkap dengan ikon sumber (WA / web / toko).
- **Empty state pengguna baru:** checklist "Langkah pertama", yaitu tambah produk, catat penjualan pertama, hubungkan WA, dan bagikan link toko.

### C2. Daftar transaksi `/transaksi`

- **Bar filter:**
    - periode
    - jenis (multi-select chip: Penjualan, Belanja, Biaya, Utang-Piutang, Modal)
    - metode bayar
    - sumber (WA / web / toko)
    - pencarian
- **Ringkasan atas:** total masuk, total keluar, selisih.
- **Desktop:** tabel (TanStack Table) dengan kolom tanggal/jam, jenis (badge berwarna), keterangan (misalnya "3 Nasi Goreng, 2 Es Teh"), kontak, metode, sumber (ikon), dan total (hijau +, merah −).
- **Mobile:** kartu yang dikelompokkan per tanggal dengan subtotal harian.
- **Transaksi dibatalkan** tampil dicoret dengan badge **Dibatalkan**.
- Pagination atau infinite scroll. Tombol **+ Catat** dan **Ekspor PDF**.

### C3. Catat transaksi `/transaksi/baru?jenis=…`

Tab/segmented di atas: **Penjualan · Belanja · Biaya · Utang-Piutang · Modal · Pindah dana**. Di desktop, form ada di kiri dan **panel pratinjau dampak** di kanan. Di mobile, pratinjau tampil di bawah, di atas tombol simpan.

- **Penjualan:**
    - tanggal (default sekarang)
    - baris item: combobox produk dengan pencarian (nama + alias), stepper qty, harga (default harga jual, bisa diubah)
    - tombol **+ item**
    - metode bayar: Tunai / Non-tunai / **Belum lunas**. Jika belum lunas, muncul combobox pelanggan dan field nominal yang sudah dibayar.
    - catatan
    - _Pratinjau:_ total, estimasi HPP & laba, dan **stok yang berkurang** (misalnya beras −0,6 kg, telur −3 butir), sehingga efek resep terlihat.
- **Belanja:**
    - supplier (opsional, combobox + tambah baru)
    - jenis: bahan/barang dagang atau peralatan
    - baris item dengan qty + harga beli
    - **Dibayar dari:** Kas tunai / Non-tunai / **Uang pribadi** / **Belum dibayar (utang)**
    - upload foto nota
    - _Pratinjau:_ stok bertambah dan biaya rata-rata berubah (misalnya telur Rp2.000 → Rp2.100/butir).
- **Biaya:**
    - chip subkategori (Listrik & air, Gas & BBM, Sewa, Upah karyawan, Transport & ongkir, Pemasaran, Lain-lain)
    - nominal, dibayar dari, catatan, foto bukti
- **Utang-Piutang:**
    - toggle **Terima bayaran dari pelanggan** / **Bayar utang ke supplier**
    - combobox kontak yang menampilkan sisa tagihan
    - nominal (default sisa), metode
- **Modal:**
    - toggle **Setor modal** / **Ambil untuk pribadi**
    - nominal, dari/ke tunai atau non-tunai, catatan
    - info box: "Mengambil uang pribadi tidak mengurangi laba, tetapi mengurangi modal usaha"
- **Pindah dana:**
    - **dari** dan **ke** (pilihan rekening, termasuk Kas tunai), nominal, dan catatan
    - keterangan dibuat otomatis: "Setor tunai ke BCA", "Tarik tunai dari BCA", "Pindah dari BCA ke GoPay"
    - _Pratinjau:_ saldo kedua rekening sebelum → sesudah; uang usaha tetap
- **Rekening non-tunai:** kalau rekening non-tunainya lebih dari satu, pilihan rekening muncul saat Non-tunai dipilih. Kalau cuma satu, rekening itu langsung dipakai. Panel pratinjau menampilkan saldo rekening itu sebelum → sesudah.
- **Input uang** mengerti format "15rb", "1,5jt", dan "15k", lalu otomatis ditampilkan sebagai `Rp15.000`.
- **Tombol Simpan** (+ "Simpan & catat lagi"). Setelah tersimpan, muncul toast dengan tautan ke detail.

### C4. Detail transaksi `/transaksi/{id}`

- **Header:** badge jenis, total besar, tanggal/jam, metode, sumber.
- **Kartu "Pesan asli dari WA"** (jika sumbernya WA): bubble chat berisi pesan pemilik, untuk transparansi bahwa AI hanya membaca dan pemilik yang mengonfirmasi.
- **Rincian:** tabel baris item (qty, harga, subtotal, HPP).
- **Mutasi stok:** item, perubahan, saldo setelah.
- **Bukti:** thumbnail foto yang bisa diperbesar.
- **"Lihat jurnal"** (collapsible, tertutup secara default): tabel akun dengan debit/kredit dan total yang seimbang. Bagian ini untuk menunjukkan kualitas teknis ke juri tanpa membingungkan pengguna awam.
- **Aksi:**
    - **Batalkan**: dialog konfirmasi + alasan (opsional), lalu membuat jurnal dan mutasi stok pembalik. Tidak ada baris transaksi pembalik.
    - **Ubah** (`/transaksi/{id}/ubah`): membuka C3 dengan data yang sudah terisi, tanpa tab jenis. Saat disimpan, transaksi lama dibatalkan dan yang baru dicatat dalam satu langkah. Kalau pemilik keluar dari form, tidak ada yang berubah.
- Banner jika sudah dibatalkan. Kalau dibatalkan karena diubah, banner menautkan ke versi baru, dan versi baru menautkan ke versi lama. Jurnal pembaliknya tampil di "Lihat jurnal".
- **Riwayat:** siapa yang mencatat, mengubah, atau membatalkan catatan ini, kapan, dan lewat mana. Kalau diubah, yang berubah ditampilkan lama → baru. Datanya dari Log aktivitas (C21).

### C5. Daftar produk & stok `/produk`

- **Tab:** Semua · Dijual · Bahan baku.
- **Kartu ringkasan:** total nilai persediaan, jumlah item stok menipis, jumlah item stok minus.
- **Tabel/kartu:**
    - foto, nama
    - badge jenis (**Biasa** / **Komposisi** / **Bahan**)
    - harga jual, HPP, **margin %** (hijau ≥ 30%, kuning 15–30%, merah < 15%)
    - stok + satuan (merah jika di bawah minimum, badge **Minus**)
    - toggle **Tampil di toko**
- **Aksi:** **+ Tambah produk**, **Koreksi stok**, pencarian.
- **Empty state:** dua kartu ajakan, "Tambah produk biasa" dan "Tambah produk dengan resep".

### C6. Form produk `/produk/baru`, `/produk/{id}/edit`

- **Pilih jenis** (3 kartu besar dengan contoh):
    - **Produk biasa**: "dibeli/dibuat lalu dijual apa adanya: air mineral, baju, sabun jadi"
    - **Produk dengan resep**: "dibuat dari bahan: nasi goreng, kue, sabun racik"
    - **Bahan baku**: "tidak dijual langsung: beras, telur, minyak"
- **Field dasar:**
    - nama
    - **alias** (tag input: "nasgor", "nasi goreng biasa"), dengan penjelasan "biar bot WA paham"
    - satuan (select: pcs, porsi, kg, gram, liter, ml, butir, bungkus, + lainnya)
    - harga jual (kecuali bahan)
    - stok minimum
    - foto
    - toggle tampil di toko, toggle tersedia
- **Khusus produk biasa/bahan (saat dibuat):** stok awal + biaya per unit.
- **Khusus produk dengan resep: pembuat resep**
    - Baris berisi combobox bahan (+ "buat bahan baru" inline), qty, satuan (mengikuti bahan), dan biaya (otomatis dihitung).
    - **Panel HPP langsung:** HPP per porsi, harga jual, **margin Rp dan %**, dengan peringatan jika margin < 15%.
- **Riwayat perubahan** (hanya saat mengubah): siapa yang mengubah harga, resep, satuan, atau stok (koreksi), dan kapan, dengan nilai lama → baru.

### C7. Detail produk `/produk/{id}`

- **Header:** foto, nama, badge jenis, harga, tombol Ubah.
- **Kartu statistik:** terjual bulan ini, pendapatan, laba kotor, margin.
- **Resep** (produk komposisi): tabel bahan + biaya.
- **"Dipakai di"** (bahan): daftar produk yang memakai bahan ini.
- **Stok & prediksi:** stok saat ini, rata-rata pemakaian per hari, **perkiraan habis**, tombol **Catat belanja** dan **Koreksi**.
- **Riwayat mutasi stok:** tanggal, sumber (penjualan #, belanja #, koreksi), ±qty, saldo.
- **Grafik HPP/margin 30 hari** (P2, persiapan US-18).

### C8. Koreksi stok `/produk/opname`

- Tabel semua item yang punya stok: nama, **stok sistem**, input **stok fisik**, **selisih** (otomatis, berwarna), dan nilai selisih (Rp).
- Filter hanya item yang berubah. Ringkasan total selisih Rp.
- Tombol **Simpan koreksi** dengan dialog konfirmasi. Versi satu item tersedia sebagai Dialog dari C5/C7.

### C9. Daftar pesanan `/pesanan`

- **Tab status** dengan jumlah: Menunggu (badge merah) · Diproses · Siap/Diantar · Selesai · Ditolak.
- **Kartu pesanan:**
    - kode, nama pelanggan, waktu ambil/antar (badge "30 menit lagi")
    - ringkasan item, total
    - badge status bayar
    - **tombol aksi cepat sesuai status**: Terima/Tolak → Tandai siap → Selesai
- **Desktop:** opsi tampilan kanban (kolom per status).
- **Bagian atas:**
    - toggle **Terima pesanan online** (buka/tutup toko)
    - tombol **Salin link toko**
    - tombol **QR toko** (dialog berisi QR link toko yang bisa diunduh untuk dicetak dan ditempel di meja)
- Filter tanggal + pencarian nama/kode.

### C10. Detail pesanan `/pesanan/{kode}`

- **Header:** kode, tanggal masuk, badge status + status bayar.
- **Stepper status** dengan tombol aksi utama untuk langkah berikutnya.
- **Pelanggan:** nama, nomor WA (tombol chat), ambil/antar, alamat, waktu, catatan.
- **Item:** tabel + total.
- **Pembayaran:**
    - total, dibayar, sisa
    - daftar pembayaran (tanggal, DP/pelunasan, metode, nominal)
    - tombol **Catat pembayaran** (dialog: nominal, metode, jenis DP/pelunasan)
- **Kirim kabar ke pelanggan:** pratinjau teks pesan yang bisa diedit, berisi link nota, lalu tombol **Buka WhatsApp** (`wa.me`).
- **Link nota:** salin / buka A4.
- **Riwayat:** log perubahan status dengan waktu dan asal (web / WA).

### C11. Utang-piutang `/utang-piutang`

- **Tab:** **Piutang** (pelanggan berutang ke saya) · **Utang** (saya berutang ke supplier).
- **Kartu ringkasan:** total, jumlah kontak, dan yang tertua (hari).
- **Daftar:**
    - kontak, sisa
    - tanggal transaksi
    - **umur** (badge warna: < 7 hari, 7–30, > 30)
    - tombol **Catat pembayaran**
    - tombol **Ingatkan** (`wa.me` berisi pesan pengingat; v1.1, P2)
- Klik baris untuk membuka rincian tagihan per kontak.

### C12. Kontak `/kontak`, `/kontak/{id}`

- **Daftar:**
    - tab Pelanggan / Supplier
    - kolom: nama, nomor WA, total transaksi, terakhir bertransaksi, sisa piutang/utang
    - pencarian
- **Detail:**
    - info kontak (bisa diubah)
    - riwayat pesanan & transaksi
    - saldo utang/piutang
    - tombol **Hapus data kontak** (dialog konfirmasi, untuk prinsip UU PDP)

### C13. Pusat laporan `/laporan`

- **Pemilih periode global**, yang terbawa ke semua sub-laporan.
- **Grid kartu laporan:** setiap kartu berisi nama, angka kunci, dan satu kalimat penjelasan.
    - Laba Rugi ("laba bersih Rp3,8 jt")
    - Neraca ("Seimbang ✓")
    - Buku Kas
    - Perubahan Modal
    - Utang-Piutang
    - Produk Terlaris
- **Semua sub-laporan punya bar aksi yang sama:**
    - pemilih periode
    - **Ekspor PDF**
    - **Kirim ke WA**
    - **Tanya AI tentang laporan ini** (membuka panel asisten dengan konteks laporan)

### C14. Laba rugi `/laporan/laba-rugi`

- **Format laporan bertingkat:** Penjualan → HPP → **Laba kotor** → Biaya operasional (rincian per subkategori, bisa dibuka-tutup) → Selisih persediaan → **Laba bersih**.
- Kolom pembanding periode sebelumnya + persentase perubahan (opsional).
- Tooltip (ikon ⓘ) di setiap istilah, misalnya "HPP = modal bahan dari produk yang terjual".

### C15. Neraca `/laporan/neraca`

- Pemilih tanggal "per tanggal".
- **Dua kolom** (bertumpuk di HP):
    - **Aset**: kas tunai, kas non-tunai, piutang, persediaan, peralatan
    - **Kewajiban + Ekuitas**: utang usaha, uang muka pesanan, modal, prive, laba
- Baris total di kedua sisi + **badge besar "Seimbang ✓"**.
- Catatan kaki: "Peralatan dicatat sebesar nilai perolehan, tanpa penyusutan".

### C16. Buku kas `/laporan/buku-kas`

- **Tab:** Tunai · Non-tunai · Gabungan.
- Saldo awal, lalu tabel tanggal, keterangan, masuk, keluar, **saldo berjalan**, lalu saldo akhir.
- Klik baris untuk membuka C4.

### C17. Perubahan modal `/laporan/perubahan-modal`

- **Grafik waterfall:** modal awal → + setoran → + laba bersih → − diambil pribadi → **modal akhir**.
- Tabel angka yang sama.
- **Kartu "Laba vs Kas"** (rekonsiliasi): menjelaskan kenapa laba ≠ perubahan kas. Isinya laba → − kenaikan stok → − piutang naik → + utang naik → − diambil pribadi → … → perubahan kas. Ada tombol **Jelaskan dengan AI**. Ini visual untuk skenario demo 4.

### C18. Produk terlaris & margin `/laporan/produk`

- Toggle urutan **Jumlah terjual** / **Laba**.
- Bar chart horizontal untuk 10 produk teratas.
- **Tabel margin semua produk:** HPP, harga, margin, terjual, kontribusi laba. Baris dengan margin rendah ditandai.

### C19. Asisten AI `/asisten` + panel samping

- **Area chat:** bubble pemilik dan asisten. Jawaban bisa berisi **kartu data** (angka kunci atau tabel kecil yang dirender dari output tool) dan baris kecil **"Sumber: laba_rugi(bulan_ini)"**.
- **Chip saran pertanyaan** saat kosong: "Untung bulan ini berapa?", "Produk paling laku minggu ini?", "Kenapa laba naik tapi kas turun?", "Siapa yang belum bayar?", "Telur cukup berapa hari?".
- Indikator mengetik dan state error ("Asisten sedang gangguan, coba lagi").
- **Input:** textarea + tombol kirim.
- Disclaimer kecil: "Angka dihitung sistem dari data Anda. AI hanya menjelaskan."
- **Panel samping** (Sheet kanan) dengan isi yang sama, bisa dibuka dari halaman mana pun. Konteks halaman ikut terkirim, misalnya sedang membuka Laba Rugi bulan ini.

### C20. Pengaturan `/pengaturan/*` (tab kiri / tab atas di HP)

- **Profil usaha:**
    - nama, jenis usaha
    - link toko (slug) + tombol salin
    - logo, deskripsi
    - **upload QRIS** (pratinjau)
    - toggle buka/tutup toko
- **Akun:** nama, nomor WA (ganti nomor lewat OTP ulang), password.
- **WhatsApp & notifikasi:**
    - **status koneksi**: nomor terverifikasi ✓, nomor bot, tombol "Mulai chat dengan bot"
    - **contekan perintah** (contoh pesan per jenis transaksi)
    - **jam ringkasan harian**
    - toggle ringkasan mingguan / peringatan stok / cek kesehatan
    - ambang peringatan stok (hari)
    - **riwayat 20 pesan terakhir** dengan bot (P2, berguna saat demo dan debugging)
- **Tampilan:** light / dark / sistem (sudah ada di starter kit).
- **Keamanan:** di bawah pengaturan password dan 2FA ada **Aktivitas akun**, berisi 10 aktivitas terbaru (masuk, gagal masuk dengan email yang dicoba dan IP, ganti password), dengan tautan ke C21.

### C21. Log aktivitas `/log-aktivitas`

- Semua aktivitas usaha, terbaru di atas. Kolomnya waktu, aktivitas (kalimat + perubahan lama → baru + keterangan seperti alasan batal), siapa, dan lewat (Web/WA).
- **Saringan:** periode, kategori (Transaksi, Produk, Kontak, Akun), dan pencarian aktivitas atau nama.
- Kalimat aktivitas menautkan ke datanya selama data itu masih bisa dibuka.
- **Hanya baca:** tidak ada tombol ubah atau hapus.
- **Di HP:** daftar per tanggal.

### C22. Kas & rekening `/rekening`

- **Isi:** daftar Kas tunai dan rekening non-tunai beserta saldonya (dari jurnal), lalu total uang usaha di bawahnya.
- **Aksi:**
    - **Pindah dana** (aksi utama, membuka C3 tab Pindah dana)
    - **Tambah rekening** (dialog nama)
    - **Ganti nama** per rekening
    - **Arsipkan** per rekening non-tunai, dengan aturan:
        - ditolak kalau saldonya belum Rp0 (pindahkan dulu lewat Pindah dana)
        - Kas tunai dan rekening non-tunai terakhir tidak bisa diarsipkan
- **Saldo minus** diberi keterangan bahwa saldo awalnya mungkin belum dicatat.
- **Tidak ada hapus:** rekening punya riwayat, jadi hanya bisa diarsipkan.

---

## D. Halaman Sistem

- **404 / 403 / 500:** ilustrasi sederhana + tombol kembali.
- **Toko tidak ditemukan / tutup:** pesan ramah + tombol hubungi penjual jika slug valid.
- **Nota tidak ditemukan:** token salah atau kedaluwarsa.
- **Skeleton loading** untuk kartu, tabel, dan laporan (shadcn `Skeleton`).

---

## Komponen Bersama (dibuat sekali, dipakai di banyak halaman)

| Komponen                                 | Dipakai di         | Catatan                                              |
| ---------------------------------------- | ------------------ | ---------------------------------------------------- |
| `MoneyInput`                             | C3, C6, C10, B5    | Mengerti "15rb", "1,5jt", "15k". Tampilan `Rp15.000` |
| `QtyStepper`                             | A2, A3, C3         | Mendukung desimal untuk kg/liter                     |
| `ItemCombobox`                           | C3, C6             | Cari nama + alias, tampilkan stok & harga            |
| `ContactCombobox`                        | C3, C10            | Bisa membuat kontak baru inline                      |
| `PaymentMethodToggle`                    | C3, C10            | Tunai / Non-tunai / Belum lunas / Uang pribadi       |
| `PeriodPicker`                           | C1, C2, C13–C18    | Hari ini, minggu ini, bulan ini, bulan lalu, rentang |
| `KpiCard`                                | C1, C5, C11, C13   | Angka, label, delta vs periode lalu                  |
| `CategoryBadge`                          | C2, C4, C1         | Warna per jenis transaksi                            |
| `OrderStatusBadge`, `PaymentStatusBadge` | A4, C9, C10        |                                                      |
| `SourceIcon`                             | C1, C2, C4         | WA / web / toko / foto nota                          |
| `HealthCheckCard`                        | C1                 | 4 aturan + tombol tanya AI                           |
| `EquityChangeCard` / `Waterfall`         | C1, C17            |                                                      |
| `StockAlertList`                         | C1, C7             | Dengan perkiraan hari habis                          |
| `ReportStatement`                        | C14, C15           | Layout laporan bertingkat + tooltip istilah          |
| `ReportActions`                          | C14–C18            | Periode, PDF, kirim WA, tanya AI                     |
| `AssistantPanel`                         | C19, semua halaman | Sheet kanan                                          |
| `WaBubble`                               | A1, C4, C20        | Pratinjau pesan gaya WA                              |
| `ImageUpload`                            | B5, C3, C6, C20    | Pratinjau, batas 5 MB                                |
| `EmptyState`                             | Semua daftar       | Ikon, judul, ajakan aksi                             |
| `ConfirmDialog`                          | C4, C8, C12        |                                                      |

**Warna jenis transaksi (usulan):**

- Penjualan: hijau
- Belanja: biru
- Biaya: oranye
- Utang-Piutang: ungu
- Modal: abu-abu/amber

Warna ini dipakai konsisten di badge, grafik, dan ikon.

---

## Temuan Saat Menyusun UI (belum ada di PRD, perlu diputuskan)

1. **Pesanan manual oleh pemilik.** Saat ini hanya pelanggan yang bisa membuat pesanan. Pre-order lewat telepon atau chat pribadi juga umum. Usulan: tombol "+ Pesanan" di C9 yang memakai form A3. Usahanya kecil.
2. **Toggle buka/tutup toko.** Belum ada di PRD. Usulan: cukup satu toggle, tanpa jadwal jam buka.
3. **QR link toko untuk dicetak.** Usahanya kecil dan berguna untuk UMKM offline.
4. **Landing page (A1).** Belum ada di PRD, padahal juri pertama kali membuka URL utama.
5. **Halaman Kontak (C12).** Tersirat di PRD (hapus data, piutang per kontak) tapi belum eksplisit.
6. **Kartu "Laba vs Kas" (C17).** Tool `rekonsiliasi_laba_kas` sudah ada di PRD, tetapi tampilannya belum. Kartu ini memperkuat skenario demo 4.
7. **"Lihat jurnal" di detail transaksi (C4).** PRD melarang istilah debit/kredit di UI. Usulan: tetap tersembunyi secara default dan hanya terlihat jika dibuka.
8. **Kategori produk di toko.** Tidak ada di PRD. Usulan: tidak dibuat, karena katalog UMKM kecil (< 30 item) dan pencarian sudah cukup.
