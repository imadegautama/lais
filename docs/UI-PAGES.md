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
| C19   | Asisten AI                    | Panel di setiap halaman aplikasi      | Pemilik              | P0                    | US-14             |
| C20   | Pengaturan                    | `/pengaturan/*`                       | Pemilik              | P0 (WA), P1 (lainnya) | US-01, US-15      |
| C21   | Log aktivitas                 | `/log-aktivitas`                      | Pemilik              | P1                    | §4.5              |
| C22   | Kas & rekening                | `/rekening`                           | Pemilik              | P1                    | US-07             |
| **D** | **Sistem**                    |                                       |                      |                       |                   |
| D1    | Halaman error & kosong        | 403, 404, 419, 500, 503, toko tutup   | Semua                | P2                    |                   |

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

Tujuannya menjelaskan produk ke juri dan calon pengguna dalam 30 detik. Isinya ada di `resources/js/features/landing`.

- **Hero:** judul "Cukup chat, pembukuan usahamu tersusun sendiri.", tombol **Daftar gratis** dan **Lihat Demo Toko**, lalu demo chat: empat contoh pesan yang bisa dipilih, HP berisi percakapan, dan kartu Stok, Kas, Laba, Jurnal yang ikut berubah. Demo tidak memanggil AI.
- **Contoh pesan:** diagram "Cukup tulis seperti biasa, Lais yang memilah." Enam chat dari eval `AssistantEvalTest` di kiri, simpul AI di tengah, enam buku di kanan. Satu chat menyala bergantian dan garisnya mengalir ke buku yang tepat, lalu catatannya muncul. Di HP disusun tegak. Kursor di atas sebuah chat menampilkan jalurnya dan menghentikan putaran; sentuhan dan fokus keyboard juga menghentikannya.
- **Masalah:** panel oranye. Judul "Usaha jalan terus, tapi untungnya ke mana?", dua angka OCBC Business Fitness Index 2024 sebagai grid 100 titik (46% memisahkan uang usaha dan pribadi, 77% dari yang sudah mencatat masih manual), lalu empat masalah bernomor.
- **Jawaban Lais** (`#cara-kerja`): empat jawaban dengan nomor dan urutan yang sama, masing-masing dengan potongan tampilan aplikasi. Daftar masalah menempel di kiri, menyala dan tercoret saat digulir.
- **Aplikasi:** laptop berisi tangkapan layar asli dengan empat tab: Dashboard, Produk & stok, Utang-piutang, Laporan. Tombol tabnya rata tengah, dan kalimat pengantarnya mengarahkan ke tab itu.
- **Untuk siapa:** judul "Dari gerobak soto sampai penjahit batik" dengan sub "Apa pun yang kamu jual, caranya sama: cukup chat, lalu penjualan, stok bahan, dan untungnya tercatat sendiri." (Lais untuk usaha yang jualan; usaha jasa murni belum diuji). Kartu foto kuliner, kelontong, fashion, kerajinan berjalan pelan sebagai carousel, dengan kredit fotografer di bawah tiap kartu. Berhenti saat kursor bergerak di atasnya, saat disentuh, atau saat difokus; saat gerak dikurangi menjadi kisi diam.
- **Kenapa bisa dipercaya:** AI tidak menyimpan tanpa konfirmasi, angka dari sistem, data tiap usaha terpisah.
- **Demo Toko:** kartu `warung-bu-rina` dari data asli dengan tombol **Coba pesan**. Kalau toko itu tidak ada atau tidak punya produk yang bisa dipesan, bagian dan tombolnya tidak tampil.
- **CTA penutup** + footer.

> **Perubahan dari rancangan awal (7 Nov):** tata letak mengikuti referensi pemilik. Tiga kartu masalah dengan angka diganti satu angka, karena hanya angka itu yang sumbernya bisa diperiksa. Enam kartu ikon dibuang (DESIGN.md §6). Judul memakai "chat", bukan "WA", karena bot berjalan di Telegram. Tidak ada testimoni, logo mitra, atau jumlah pengguna sampai datanya nyata.

> **Animasi (8 Nov):** gerakan mengikuti referensi pemilik, dengan Lenis untuk roda mouse. Aturannya di DESIGN.md §6 poin 16.

### A2. Halaman toko `/t/{slug}`

- **Kartu toko** (`ShopIntro`):
    - nama toko sebagai `h1`, lalu "{Kategori} · Ambil sendiri atau diantar"
    - badge **Buka** / **Tutup** dan tombol **Hubungi penjual** (`wa.me`)
    - **Cara pesan** 3 langkah: Pilih produk → Kirim pesanan (nama dan nomor WA saja, tanpa akun) → Penjual konfirmasi lewat WA, bayar tunai atau QRIS. Toko ini untuk pesan dulu lalu ambil sendiri atau diantar, bukan pesan di meja.
- **Pencarian produk:** input di atas daftar.
- **Daftar produk** (`ProductRow`; 1 kolom di HP, 2 kolom mulai 768 px):
    - Baris berisi foto kecil (atau ubin oranye muda dengan huruf depan), nama, harga per satuan, dan tombol **+** yang berubah jadi stepper `− 2 +`.
    - Stepper berhenti di **sisa**: stok dikurangi pesanan yang belum Selesai atau Ditolak. Saat sisa ≤ 5 tampil "Sisa 3 botol". Produk resep tidak dihitung dan mengikuti saklar Tersedia.
    - Produk habis (atau habis dipesan) diberi badge **Habis**, foto abu-abu, dan tombol nonaktif.
- **Keranjang:** di bawah 1024 px berupa bar lengket di bawah ("3 item · Rp45.000 · **Lanjut pesan**"), mulai 1024 px berupa panel lengket di samping daftar. Bar hanya muncul jika keranjang tidak kosong.
- **State:** toko tutup (banner, tombol pesan nonaktif), katalog kosong, slug tidak ditemukan (D1), dan pesanan ditolak server karena stoknya keburu dipesan orang lain (pesan merah di atas daftar).

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

- **Cara bayar** (hanya kalau toko menyalakan bayar online dan kunci DOKU terpasang): **Bayar online sekarang** (bawaan) atau **Bayar ke penjual nanti**. Bayar online membawa pelanggan ke halaman DOKU (mode uji) setelah pesanan terkirim, dengan tombol "Kirim dan bayar". Kalau DOKU gagal membuka halaman, pesanan tetap tersimpan dan nota menampilkan pesan untuk mencoba lagi.

> **Catatan implementasi (4 Nov):** A2 dan A3 dibuat sebagai satu halaman `/t/{slug}` dengan dua langkah (katalog, lalu form), dan form dikirim ke `POST /t/{slug}/pesan`. Keranjang disimpan per toko di `sessionStorage`. Batas 5 pesanan per IP dihitung dari pesanan yang berhasil saja.

### A4. Status pesanan & nota `/p/{token}`

- **Header:** kode pesanan (A012), tanggal, nama toko.
- **Stepper status:** Menunggu konfirmasi → Diproses → Siap/Diantar → Selesai, dengan waktu tiap langkah. Pesanan yang ditolak tampil sebagai banner merah.
- **Rincian:** tabel item (nama, qty, harga, subtotal), total, **sudah dibayar**, **sisa**, dan badge status bayar (Belum bayar / DP / Lunas).
- **Kartu pembayaran:**
    - gambar QRIS toko (bisa diperbesar dan diunduh)
    - nominal yang harus dibayar
    - petunjuk 3 langkah
    - catatan "Konfirmasi pembayaran dilakukan penjual"
    - **Bayar online** (DOKU, mode uji) selama pesanan masih terbuka dan ada sisa tagihan, juga saat masih Menunggu. Kalau halaman DOKU masih terbuka: **Lanjutkan bayar** dan **Cek pembayaran**. Pembayaran yang masuk otomatis menerima pesanan dan dicatat sebagai uang muka di rekening Saldo DOKU. Selama tombol ini tampil, ia menjadi tombol primary dan Hubungi penjual turun menjadi outline.
- **Info ambil/antar:** metode, waktu, alamat.
- **Aksi:** **Hubungi penjual** (`wa.me` dengan pesan berisi kode pesanan) dan **Simpan/Cetak nota** (tampilan cetak via `@media print`).
- **Auto-refresh status** setiap 30 detik (Inertia polling).

---

### A5. Kebijakan Privasi `/privasi` dan Syarat dan Ketentuan `/syarat`

- Halaman publik untuk consent screen Google dan untuk pendaftar. Pengelola ditulis "Tim Lais", kontak `halo@lais.web.id` (diteruskan Cloudflare Email Routing).
- Isinya harus jujur terhadap kode: data yang disimpan, layanan pihak ketiga (Anthropic, Cloudflare R2, Telegram atau WhatsApp, Resend, Google), tanpa analitik atau iklan, dan apa yang belum ikut terhapus saat akun dihapus. Tanpa klaim patuh UU PDP; laporan "mengacu pada SAK EMKM".
- Ditautkan dari footer landing dan dari halaman Daftar. Kalau perilaku data berubah, perbarui teks dan tanggal berlakunya.

## B. Auth & Onboarding

### B1. Daftar `/register`

- **Lanjutkan dengan Google** di atas form (`GoogleController`, tampil hanya kalau `GOOGLE_CLIENT_ID` diisi), lalu "atau daftar dengan email".
- Field: nama, email, password (minimal 8 karakter, dengan tombol lihat). Tanpa konfirmasi password.
- Daftar email lanjut ke verifikasi email; daftar Google langsung terverifikasi dan masuk ke onboarding.
- Tautan ke halaman masuk. Layout auth _split_ untuk semua halaman auth: kiri form putih dengan logo sejajar field, kanan (mulai 1024 px) panel oranye `bg-auth-panel` berisi judul, cuplikan chat, dan "Cocok untuk" jenis usaha. Tanpa testimoni atau logo pihak lain.

### B2. Verifikasi OTP `/verify-phone`

- Teks: "Masukkan 6 digit kode yang kami kirim ke WA 0812-xxxx-1234 dari nomor **[nomor bot]**".
- Input OTP 6 kotak (shadcn `InputOTP`) yang otomatis terkirim begitu 6 digit terisi.
- Hitung mundur "Kirim ulang dalam 0:45" + tautan **Ganti nomor**.
- **Error:** kode salah (sisa percobaan), kedaluwarsa, dan batas permintaan tercapai.

### B3. Masuk `/login`

- Lanjutkan dengan Google di atas (passkey dimatikan di `config/fortify.php`, atas permintaan pemilik), lalu "atau dengan email": email, password, **Lupa password?** di kanan bawah field password, "Ingat saya", tautan daftar.
- Email Google yang sudah terdaftar disambungkan ke akun itu (hanya kalau Google menandai emailnya terverifikasi). Akun dengan 2FA tetap diminta kode.

### B4. Lupa & reset password

Satu alur 3 langkah: nomor WA → OTP (memakai ulang B2) → password baru.

### B5. Onboarding wizard `/onboarding`

- **Tampilan:** halaman sendiri tanpa sidebar, berjudul **Siapkan buku usahamu**. Di header ada logo dan link **Lewati, nanti saja**. Di atas ada meter **Buku usahamu N% siap** dengan baris langkah yang menjadi hijau saat selesai. Setiap langkah berjudul pertanyaan sehari-hari ("Ada uang usaha berapa sekarang?"), satu kalimat kenapa perlu, dan chip **Apa itu …?** untuk istilahnya.
- **Navigasi:** tombol **Kembali / Lanjut**. Setiap kali Lanjut ditekan, langkah itu diperiksa lalu disimpan sebagai draf, jadi wizard bisa dilanjutkan nanti dari langkah berikutnya.
- **Masuk ke wizard:** setelah daftar, pengguna langsung diarahkan ke sini. Selama belum selesai, dashboard dan Kas & rekening menampilkan kartu **Saldo awal belum diisi → Lanjutkan pengisian**. Setelah selesai, `/onboarding` mengalihkan ke dashboard.
- **Langkah:**
    1. **Usaha:**
        - nama usaha
        - jenis usaha (kuliner / kelontong / fashion / kerajinan / lainnya), untuk memilih contoh produk
        - mulai dicatat sejak (paling lambat hari ini, atau hari catatan pertama kalau sudah ada)
    2. **Uang:** saldo tiap rekening pada tanggal mulai. Rekening bisa diganti nama atau ditambah. Ada edukasi "Hanya uang usaha, jangan dicampur uang rumah tangga".
    3. **Peralatan:** baris nama + perkiraan nilai. Bisa dilewati.
    4. **Produk & bahan:**
        - baris tambah cepat: nama, jenis (dijual / bahan), satuan, harga jual, stok awal, dan biaya per unit
        - **Pakai contoh** sesuai jenis usaha; barisnya berlabel "Contoh, ubah sesuai usahamu"
        - produk resep dari contoh tampil dengan ringkasan resepnya
        - bisa dilewati
    5. **Utang & piutang:** utang ke supplier dan kasbon pelanggan, masing-masing nama + sisa. Bisa dilewati.
    6. **Akun (COA):** "Ini daftar akun usahamu". Daftar akun sistem dalam lima kelompok (Aset, Kewajiban, Modal, Pendapatan, Beban) dengan kode, nama, dan saldo awal dari langkah sebelumnya; tiap kelompok punya ⓘ penjelasan. Di bawahnya **Akun khusus (boleh dilewati)**: chip saran per jenis usaha (misalnya Beban Kemasan, Pendapatan Jasa antar) dan isian nama bebas dengan pilihan pengeluaran/pemasukan. Akun baru tampil di kelompoknya dengan badge Baru dan bisa dihapus; kodenya (6501…, 4201…) diberikan saat disimpan.
    7. **Ringkasan neraca awal:**
        - kotak "Semua langkah beres" dengan lencana yang terkumpul (Usaha terdaftar, Kas tercatat, Peralatan tercatat, Stok siap, Utang-piutang rapi, Akun siap)
        - kas & rekening, peralatan, persediaan, piutang, total aset, utang
        - **Modal awal kamu** = aset − utang, dengan badge "Seimbang"
        - tombol **Simpan dan mulai**, yang membuka dashboard dan memulai tur singkat
- **Saat disimpan:** semuanya dicatat sebagai jenis **Saldo awal** pada tanggal mulai, dalam satu langkah. Kalau ada yang salah, wizard kembali ke langkah yang bermasalah.
- **Menyusul di slicenya masing-masing:** slug toko, logo, gambar QRIS (M4), dan kartu Hubungkan WhatsApp (GOWA).

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
- **Misi pertama:** kartu di atas ringkasan selama ada misi yang belum selesai: tambah produk, catat penjualan pertama, catat biaya pertama, atur toko online (nomor WA penjual atau QRIS), dan hubungkan bot. Setiap misi tercentang sendiri dari data (`DashboardController::missions`), dengan progres "n dari 5" dan tombol ke tempatnya. Kartu hilang setelah semua selesai.
    - **Dipandu:** tombol misi membuka halamannya dengan `?panduan=1`, dan **Panduan** halaman itu berjalan sendiri (lihat "Panduan per halaman" di bawah). Parameternya dihapus dari alamat setelah selesai, jadi muat ulang tidak mengulang.
    - **Sembunyikan:** kartu bisa dilipat jadi satu baris "Misi pertama n dari 5 · Tampilkan"; pilihannya diingat per perangkat.
    - Ringkasan dashboard tetap tampil di bawahnya sejak hari pertama, dengan angka Rp0 dan keadaan kosong yang menjelaskan apa yang nanti terisi.
- **Tur dashboard:** Panduan dashboard menunjuk Catat, Tanya AI, Pesanan, Laporan, Misi, lalu tombol Panduan itu sendiri. Panduan ini terbuka sendiri setiap kali dashboard dibuka sampai pemilik menekan **Selesai** atau **Lewati**; tandanya disimpan di akun (`users.tour_completed_at`, `POST /tur-selesai`), jadi berlaku di semua perangkat. Yang tertutup di tengah jalan (misalnya pindah halaman) muncul lagi.
- **Panduan per halaman:** tombol **Panduan** di header atas (kanan, di HP di samping logo) muncul di halaman yang punya panduan: Dashboard, Catat transaksi (langkahnya menyesuaikan jenis), Transaksi, Produk & stok, form produk, Pesanan, Kas & rekening, Utang-piutang, Laporan, dan Bot. Setiap panduan menunjuk 2 sampai 7 bagian dengan Joyride; bagian yang tidak tampil di layar dilewati.
- **Status pembuatan (slice 5):** semua angka sudah dihitung dari jurnal lewat `App\Services\Reports`.
    - Baris 4 kartu ringkasan (`MetricTile`, 2×2 di HP) mengikuti pemilih periode. Setiap kartu berisi angka, naik/turun, dan satu baris penjelasan:
        - **Penjualan:** membuka `/transaksi` dengan saringan penjualan dan periode yang sama
        - **Untung bersih:** membuka Laba rugi (C14) dengan periode yang sama
        - **Uang usaha:** saldo saat ini, membuka Kas & rekening
        - **Diambil untuk pribadi:** membuka transaksi modal
    - Kartu "Ringkasan" terpisah tidak dipakai lagi. Di sampingnya kini ada Kesehatan usaha.
    - Panel "Perlu tindakan" berisi bahan yang sudah habis atau habis ≤ 2 hari (tombol **Catat belanja** membuka tab Belanja, itemnya belum terisi) dan piutang yang lewat 7 hari (tombol **Catat bayaran**). Aksi pesanan menyusul di slice M4.
    - Kartu "Pesanan aktif" dan "Utang & piutang" menyusul bersama halaman C9 dan C11.
    - Empty state muncul selama belum ada catatan selain saldo awal. Langkah "Hubungkan WhatsApp" dan "Bagikan link toko" masih bertanda Segera.

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

Draf dari **Tanya AI** (C19) bisa dipindahkan ke form ini lewat "Ubah di form". Setelah diisi, muncul catatan "Diisi Tanya AI. Periksa lagi sebelum menekan Simpan." Form Ubah dan Saldo awal tetap diisi manual.

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
- **Saldo awal** (bukan tab, karena dicatat sekali saat mulai):
    - dibuka dari wizard onboarding, dari link "Catat saldo awal" di Kas & rekening, atau lewat Ubah
    - isinya: chip **Kas & rekening / Peralatan / Utang ke supplier / Piutang pelanggan**, nominal, lalu rekening, nama peralatan, atau kontak sesuai pilihannya
    - cara bayar tidak dipilih
    - _Pratinjau:_ saldo rekening sebelum → sesudah, dengan catatan bahwa saldo awal menambah modal awal, bukan pendapatan
- **Rekening non-tunai:** kalau rekening non-tunainya lebih dari satu, pilihan rekening muncul saat Non-tunai dipilih. Kalau cuma satu, rekening itu langsung dipakai. Panel pratinjau menampilkan saldo rekening itu sebelum → sesudah.
- **Input uang** mengerti format "15rb", "1,5jt", dan "15k", lalu otomatis ditampilkan sebagai `Rp15.000`.
- **Tombol Simpan** (+ "Simpan & catat lagi"). Setelah tersimpan, muncul toast dengan tautan ke detail.

### C4. Detail transaksi `/transaksi/{id}`

- **Header:** badge jenis, total besar, tanggal/jam, metode, sumber.
- **Kartu "Pesan asli dari WA"** (jika sumbernya WA): bubble chat berisi pesan pemilik, untuk transparansi bahwa AI hanya membaca dan pemilik yang mengonfirmasi.
- **Rincian:** tabel baris item (qty, harga, subtotal, HPP).
- **Mutasi stok:** item, perubahan, saldo setelah.
- **Bukti:** thumbnail foto yang bisa diperbesar. _(Sudah dibuat 4 Nov: kartu Bukti dengan dialog perbesar dan "Buka ukuran asli"; kolom "Bukti (opsional)" ada di semua jenis form C3, juga di dialog bayar Pesanan dan Utang-piutang saat Transfer/QRIS.)_
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
- Draf produk dari **Tanya AI** (C19), termasuk resepnya, bisa dipindahkan ke form ini lewat "Ubah di form". Bahan yang belum terdaftar disebut di daftar "Masih kurang". Form ubah tetap diisi manual.

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

> **Catatan implementasi (4 Nov):** daftar per tab berupa tabel di desktop dan daftar bertumpuk di HP, dengan pencarian (kode, nama, nomor WA), urut (masuk, waktu ambil/antar, total), dan 20 pesanan per halaman. Tab antrean (Menunggu, Diproses, Siap) menampilkan yang paling lama di atas; Selesai dan Ditolak yang terbaru di atas. Toko online menjadi bar ringkas di atas tab; nomor WA penjual dan QRIS ada di dialog "Pengaturan". Kanban, filter tanggal, dan aksi massal tidak dibuat. Daftar memuat ulang setiap 30 detik.

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

> **Catatan implementasi (4 Nov):** Riwayat status belum dibuat; waktu tiap langkah tampil di stepper. Kartu Bayar di nota baru mengajak membayar setelah pesanan diterima. Jenis pembayaran (DP atau pelunasan) tidak dipilih: sebelum Selesai otomatis uang muka, sesudahnya pelunasan.

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

> **Catatan implementasi (4 Nov):** tab berupa `?jenis=utang`. Tombol bayar berlabel "Terima bayaran" (piutang) atau "Bayar utang" (utang) dan langsung tercatat tanpa pindah halaman. Ingatkan hanya muncul untuk pelanggan yang nomor WA-nya diketahui; teksnya bisa diubah sebelum WhatsApp dibuka. Rincian per kontak ada di `/utang-piutang/{kontak}`: tagihan satu per satu (belum lunas di atas) dan daftar pembayaran.

### C12. Kontak `/kontak`, `/kontak/{id}`

**Status (7 Nov):** sudah jadi, dengan Tambah dan Ubah kontak. Pembayaran tetap di C11; detail kontak hanya menautkannya.

**Hapus data kontak:** ditolak selama masih ada sisa tagihan atau pesanan berjalan. Kontak tanpa riwayat dihapus sungguhan. Kontak berriwayat dianonimkan menjadi "Kontak terhapus #id" dan salinan datanya di pesanan dibersihkan, sehingga pembukuan tidak berubah. Nama di keterangan jurnal lama dan log aktivitas tetap ada sebagai jejak audit, dan dialognya menyatakan ini.

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

**Status pembuatan (slice 8):**

- C13 sampai C18 sudah jadi. Neraca tampil sebagai **Laporan Posisi Keuangan (Neraca)**, dan Laba rugi serta Posisi keuangan memakai kolom pembanding periode sebelumnya (di HP, angkanya di bawah angka utama).
- Tambahan 7 Nov: `/laporan/catatan` (CALK otomatis), `/laporan/coa` (Daftar akun dengan Tambah akun, Ganti nama, Arsipkan), `/laporan/buku-besar/{kode}`, `/laporan/neraca-saldo`, `/laporan/aset-tetap`.
- Form produk menampilkan saran harga jual untuk margin 30, 40, dan 50 persen; tombolnya mengisi Harga jual. Form peralatan punya field Umur pakai (tahun). Form Biaya menampilkan pilihan akun kalau usaha punya akun beban sendiri.
- Bar aksi untuk sementara hanya berisi pemilih periode (Neraca: tanggal) dan **Cetak / simpan PDF**. Kirim ke WA dan Tanya AI tentang laporan menunggu fiturnya.
- Tambahan 7 Nov, perombakan tampilan: semua laporan dibuka dengan kesimpulan satu kalimat (`ReportLead`, dihitung `App\Services\ReportStory` dari jurnal tanpa AI), lalu grafik yang menjawab satu pertanyaan, lalu laporan resmi. Baris laporan menuju Buku besar, dan rentang tanggal ikut terbawa.
- Filter **Pilih tanggal** (`?dari=&sampai=`, `App\Support\ReportRange`) tersedia di semua laporan berperiode. Pembandingnya periode sepanjang rentang yang sama tepat sebelumnya. Tanggal rusak, terbalik, atau di masa depan jatuh ke Bulan ini.
- Rincian biaya di C14 bisa dibuka-tutup di layar, tetapi selalu ikut tercetak.
- Baris bernilai nol di dua periode disembunyikan. Catatan tanpa saldo digabung menjadi satu kalimat di Dasar penyusunan.
- Buku kas, Buku besar, dan Neraca saldo memakai `AppDataTable` (Buku kas dan Buku besar 20 baris per halaman). Cetak selalu memuat semua baris lewat `usePrinting`.
- C13 berisi tiga daftar laporan (Kesehatan usaha, Uang dan produk, Pembukuan), bukan kartu seragam. Tiap baris berisi nama, satu kalimat, dan angka kunci.
- Grafik waterfall di C17 belum dibuat. Jembatan laba ke kas memakai `BreakdownBars`.
- Tambahan 7 Nov: `/laporan/jurnal` (Jurnal umum) menampilkan semua pencatatan per rentang tanggal, dengan link ke transaksi asalnya. Debit dan kredit diberi penjelasan istilah.

- **Pemilih periode global**, yang terbawa ke semua sub-laporan.
- **Daftar laporan per kelompok:** setiap baris berisi nama, angka kunci, dan satu kalimat penjelasan.
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

### C19. Asisten AI (panel "Tanya AI")

**Paham aplikasi (8 Nov):** Tanya AI dan bot menjawab tiga jenis pertanyaan baru. Model hanya memilih topik, istilah, atau produk; kalimat dan angkanya dari sistem.

- **Di mana fitur atau tempat mencatat sesuatu** ("di mana nyatet COA?", "utang ke supplier dicatat di mana?", "salah catat gimana?"): penjelasan dari `GuideTopic` dengan jalur menunya, plus tombol ke halamannya. Di bot, tombol ditulis sebagai alamat.
- **Arti istilah** ("apa itu HPP?"): definisi yang sama dengan ⓘ di layar (`Term`), plus tombol ke laporan tempat istilah itu muncul.
- **Layak tidaknya produk** ("es teh worth it?"): putusan dengan aturan tetap dari margin (`MarginLevel`: rugi, tipis di bawah 15%, cukup, sehat dari 30%) dan penjualan 30 hari terakhir, saran harga untuk margin 30% kalau rugi atau tipis, dan catatan bahwa biaya tetap belum dihitung.
- Balasan yang berbaris (putusan, daftar biaya) tampil per baris.

**Pertanyaan usaha (7 Nov):** selain angka yang sudah ada, pemilik bisa menanyakan modal, aset, stok, produk terlaris, BEP, perbandingan, dan alasan naik turun. Jawabannya selalu disertai link Laporan. Angka yang tidak ada di lembar fakta tidak pernah ditampilkan; pemilik diarahkan ke Laporan.

**Barang yang belum terdaftar (7 Nov):** baris draf yang tidak ada di daftar tetap tampil di kartu dengan jumlah dan harganya, sehingga total sesuai nota, dan draf belum bisa disimpan. Di bawah kartu ada dua pilihan:

- **Daftarkan barang yang belum ada:** AI menanyakan dijual lagi atau bahan baku, lalu harga jual kalau dijual. Semua barang tampil di satu kartu "Tambah ke daftar produk" dengan satu konfirmasi, lalu draf belanja kembali utuh dan foto nota tetap ikut sebagai bukti. Kartu ini tidak punya "Ubah di form".
- **Catat yang ada saja:** membuang baris yang belum terdaftar, tanpa memanggil AI.

**Status pembuatan (slice 6): mencatat dengan konfirmasi.** Asisten berbentuk panel, tidak punya halaman sendiri.

- **Cara membuka:**
    - tombol mengambang "Tanya AI" di kanan bawah, di atas bottom nav dan di atas action bar form
    - menu "Tanya AI" di sidebar
- **Bentuk panel:**
    - Di desktop berupa sheet kanan yang tidak mengunci halaman.
    - Di bawah 1024 px berupa drawer bawah.
    - Percakapan tetap ada saat pindah halaman.
- **Balon chat:** pesan pemilik di kanan (abu hangat), balasan di kiri (kartu putih bergaris).
- **Alur mencatat:** pemilik menulis yang mau dicatat, lalu balasan berisi **kartu draf**:
    - judul (Penjualan, Belanja, Biaya listrik & air, atau nama produk)
    - baris "Beras · 5 kg × Rp14.000" dengan subtotal di kanan, lalu Total
    - catatan kecil: cara bayar, rekening, kontak, dan waktu
    - daftar **Masih kurang** (dari aturan validasi Simpan, bukan tebakan AI)
- **Tombol di kartu:**
    - **Simpan**, hanya kalau tidak ada yang kurang. Membalas "iya", "oke", "gas", atau "simpan" sama dengan menekannya.
    - **Ubah di form** memindahkan draf ke form C3 atau C6, lalu panel tertutup.
- **Setelah tersimpan:** "Belanja tersimpan." dengan tombol **Lihat transaksi** atau **Lihat produk**. Halaman di belakang panel dimuat ulang supaya angkanya ikut baru.
- Pertanyaan balik dari AI datang dengan chip jawaban, misalnya "Beras dicatat dalam kg. 4 kodi itu berapa kg?".
- **Panduan pencatatan:** pertanyaan seperti "uang pribadi 200rb dicatat di mana?" dijawab dalam satu balon, berisi:
    - penjelasan tetap (dari `GuideTopic`)
    - kotak **Yang perlu disiapkan**
    - tombol draf ("Catat setor modal Rp200.000"), yang langsung menampilkan kartu draf tanpa menunggu AI, atau tombol halaman ("Buka Produk & stok", "Buka form Pindah dana", "Catat saldo awal")
- Pertanyaan laporan ("untung bulan ini berapa?") diarahkan ke Dashboard sampai tanya-jawab laporan dibuat.
- Permintaan di luar urusan usaha dijawab dengan kalimat tetap: "Maaf, saya hanya bisa membantu mencatat transaksi dan produk baru…".
- **Foto nota:** tombol ikon foto di samping Kirim membuka kamera atau galeri (JPG, PNG, WebP).
    - Pratinjau kecil muncul di atas input, dengan tombol "Hapus foto". Foto bisa dikirim tanpa teks.
    - Status memuat: "AI sedang membaca nota…". Hasilnya kartu draf Belanja yang dikonfirmasi seperti biasa.
    - Foto yang bukan nota dijawab dengan kalimat tetap. File yang tidak bisa dibuka menampilkan "Foto tidak bisa dibaca. Coba foto lain."
    - Foto tidak disimpan.
- **State:**
    - kosong: contoh kalimat sesuai form yang dibuka; mengetuknya hanya mengisi input
    - memuat: "AI sedang membaca…"
    - gagal: pesan sebab dan langkah berikutnya, dengan tombol **Kirim lagi**

**Sprint berikutnya: tanya-jawab soal usaha (US-14).** Panel yang sama mendapat:

- **Area chat:** bubble pemilik dan asisten. Jawaban bisa berisi **kartu data** (angka kunci atau tabel kecil yang dirender dari output tool) dan baris kecil **"Sumber: laba_rugi(bulan_ini)"**.
- **Chip saran pertanyaan** saat kosong: "Untung bulan ini berapa?", "Produk paling laku minggu ini?", "Kenapa laba naik tapi kas turun?", "Siapa yang belum bayar?", "Telur cukup berapa hari?".
- Indikator mengetik dan state error ("Asisten sedang gangguan, coba lagi").
- **Input:** textarea + tombol kirim.
- Disclaimer kecil: "Angka dihitung sistem dari data Anda. AI hanya menjelaskan."
- Konteks halaman ikut terkirim, misalnya sedang membuka Laba Rugi bulan ini.

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

> **Catatan implementasi (8 Nov):** Pengaturan tetap di `/settings/*` (bukan `/pengaturan/*`) dengan empat tab:
>
> - **Usaha** (`/settings/business`): nama dan jenis usaha, link toko (salin, buka) dengan status Terima pesanan/Tutup. Slug tidak ikut berubah saat nama diganti. Buka/tutup, nomor WA penjual, QRIS, dan bayar online tetap di `ShopBar` Pesanan dan ditautkan dari sini.
> - **Akun** (`/settings/profile`): nama, email, dan hapus akun. Teks hapus akun menyebut apa saja yang ikut terhapus.
> - **Keamanan**: password, verifikasi dua langkah, dan aktivitas akun. Akun dari Google belum punya password: halaman ini terbuka tanpa konfirmasi dan menampilkan "Buat password" tanpa password lama. Hapus akun tetap wajib password.
> - **Bot**.
>
> `/settings` membuka tab Usaha. Logo, deskripsi, tab Tampilan (aplikasi light-only), dan ganti nomor WA lewat OTP belum dibuat.

> **Catatan implementasi (6 Nov):** tab ini sekarang bernama **Bot** (`/settings/bot`) dan mengikuti saluran di `BOT_CHANNEL`. Di Telegram, tombolnya "Buka Telegram": link `t.me/<bot>?start=<kode>` yang menghubungkan akun setelah pemilik menekan Mulai. Catatan 4 Nov di bawah tetap berlaku untuk isi halamannya.

> **Catatan implementasi (4 Nov):** tab **WhatsApp** di Pengaturan (`/settings/whatsapp`) berisi status terhubung, langkah "Buat kode" → "Kirim lewat WhatsApp" (pemilik mengirim `LAIS ######` ke bot; halaman memeriksa setiap 5 detik), tombol Putuskan, dan saklar ringkasan harian 21.00. Jam ringkasan, ringkasan mingguan, peringatan stok terpisah, contekan perintah, dan riwayat pesan belum dibuat; contekan ada di `docs/WHATSAPP.md`.

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
| `AssistantPanel`                         | C19, semua halaman | Sheet kanan di desktop, drawer di HP                 |
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

1. **(Sudah dibuat, 4 Nov)** **Pesanan manual oleh pemilik.** Saat ini hanya pelanggan yang bisa membuat pesanan. Pre-order lewat telepon atau chat pribadi juga umum. Usulan: tombol "+ Pesanan" di C9 yang memakai form A3. Usahanya kecil.
2. **(Sudah dibuat)** **Toggle buka/tutup toko.** Belum ada di PRD. Usulan: cukup satu toggle, tanpa jadwal jam buka.
3. **(Sudah dibuat)** **QR link toko untuk dicetak.** Usahanya kecil dan berguna untuk UMKM offline.
4. **Landing page (A1).** Belum ada di PRD, padahal juri pertama kali membuka URL utama.
5. **Halaman Kontak (C12).** Tersirat di PRD (hapus data, piutang per kontak) tapi belum eksplisit.
6. **Kartu "Laba vs Kas" (C17).** Tool `rekonsiliasi_laba_kas` sudah ada di PRD, tetapi tampilannya belum. Kartu ini memperkuat skenario demo 4.
7. **"Lihat jurnal" di detail transaksi (C4).** PRD melarang istilah debit/kredit di UI. Usulan: tetap tersembunyi secara default dan hanya terlihat jika dibuka.
8. **Kategori produk di toko.** Tidak ada di PRD. Usulan: tidak dibuat, karena katalog UMKM kecil (< 30 item) dan pencarian sudah cukup.
