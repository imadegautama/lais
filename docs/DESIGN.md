# DESIGN.md: Pedoman Visual Laris

Dokumen ini menjadi acuan visual untuk semua halaman. Referensi hidupnya ada di **`/design-system`**, yang hanya tersedia di lingkungan lokal (`APP_ENV=local`).

- Nilai token ada di `resources/css/app.css`.
- Komponen ada di `resources/js/components/app/`.
- Kalau dokumen ini, halaman `/design-system`, dan kode tidak sama, **kode yang benar**. Perbarui dokumen ini di commit yang sama.
- Aturan ringkas untuk agen AI ada di `.ai/guidelines/laris-frontend.md`, dan Laravel Boost menggabungkannya ke `CLAUDE.md`. Setelah clone, jalankan `composer install` lalu `php artisan boost:install --guidelines --skills --mcp -n`.

> **Sumber arah desain.** Arah desain dipilih pemilik proyek:
>
> - nuansa oranye "Tangerine + tinta gelap"
> - font Plus Jakarta Sans
> - referensi struktur dashboard "Nexus": latar abu muda, kartu putih berborder tipis, sidebar dengan grup menu
>
> Bagian yang ditandai **(Usulan)** adalah usulan agen dan boleh dicoret.

## 1. Design Read & dial

> Reading this as: aplikasi operasional (dashboard, form, toko publik) untuk pemilik UMKM non-teknis yang banyak memakai HP, dengan bahasa visual hangat-utilitarian, dial **ENERGY 2 / RHYTHM 2 / MOTION 1**.

| Dial   | Nilai | Artinya di produk ini                                                                                                                                      |
| ------ | ----- | ---------------------------------------------------------------------------------------------------------------------------------------------------------- |
| ENERGY | 2     | Oranye hangat dan angka yang tegas, tapi tenang untuk dibuka puluhan kali sehari                                                                           |
| RHYTHM | 2     | Pola halaman konsisten, dengan pemecah yang disengaja (kartu fokus "Perlu tindakan")                                                                       |
| MOTION | 1     | Hanya hover, fokus, dan transisi fungsional (sheet, dialog, toast). Tanpa animasi scroll atau animasi grafik, karena HP pengguna sering spesifikasi rendah |

Struktur referensi Nexus dipakai sebagai inspirasi, bukan disalin. Dashboard disusun dari tugas layarnya ("apakah usaha saya aman hari ini dan apa yang harus saya lakukan"), bukan dari cangkang dashboard berisi 4 kartu statistik.

## 2. Alasan keputusan (satu baris per keputusan)

| Keputusan                                               | Alasan                                                                                                                                                                                  |
| ------------------------------------------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Oranye tangerine `#C25205` (rona `#F97316`, digelapkan) | Pilihan pemilik proyek: hangat, dekat dengan dunia warung dan pasar, berbeda dari aplikasi kasir yang umumnya biru. Digelapkan secukupnya pada rona yang sama supaya teks putih terbaca |
| Tombol utama: teks putih di `#C25205`, semua ukuran     | Pilihan pemilik (teks putih). `#C25205` adalah oranye paling terang dengan rona `#F97316` yang masih lolos 4,5:1 (4,66:1). `#F97316` dengan teks putih hanya 2,80:1                     |
| `primary-text` `#C2410C` untuk teks oranye              | Teks oranye cerah di atas putih hanya 2,80:1. `#C2410C` 5,18:1                                                                                                                          |
| Latar halaman putih                                     | Pilihan pemilik proyek. Latar abu hangat sebelumnya membuat tampilan pucat                                                                                                              |
| Netral abu hangat (stone), bukan abu dingin             | Abu dingin terlihat kotor di sebelah oranye                                                                                                                                             |
| Plus Jakarta Sans                                       | Dibuat studio Jakarta (Tokotype), x-height lebar, angkanya jelas di ukuran kecil, dan memberi identitas lokal                                                                           |
| Kategori transaksi dibedakan ikon dan kata              | Lima warna kategori akan melampaui 2–3 warna inti dan bersaing dengan warna status                                                                                                      |
| Kartu: border + `shadow-card` sangat halus              | Kartu putih di atas latar putih butuh pemisah. Bayangan besar tetap hanya untuk overlay                                                                                                 |
| Inner shadow pada tombol                                | Pilihan pemilik proyek: kilau tipis di atas dan sisi bawah gelap menandai "bisa ditekan", dan berubah saat ditekan                                                                      |
| Border field `#DAD5CF` (override pemilik)               | Border field 1,46:1 di bawah pedoman 3:1, dipilih pemilik; diimbangi label selalu terlihat dan ring fokus 3px                                                                           |
| Lucide icons                                            | Sudah dipakai komponen shadcn, jadi tidak ada dua gaya ikon. Ikon dipilih karena relevan dan selalu disertai label                                                                      |
| Light mode saja                                         | Pengguna utama memakai HP di warung yang terang, dan laporan dicetak ke PDF. Tidak ada toggle, jadi tidak ada mode setengah jadi                                                        |
| Label grup sentence case                                | Huruf kapital dengan spasi lebar adalah gaya default AI dan lebih sulit dibaca                                                                                                          |
| Bottom nav di bawah 1024 px                             | HP dan tablet dipegang tangan. Lima tujuan utama harus terjangkau jempol, dan konten tablet mendapat lebar penuh                                                                        |
| Motif tepi nota **(Usulan)**                            | Nota adalah artefak nyata UMKM. Tepi bergerigi menandai "ini bukti transaksi"                                                                                                           |

## 3. Token

### Warna

Semua pasangan dihitung dengan `contrast-check.py` (antislop-human).

| Token                              | Nilai                 | Pemakaian                                            | Kontras                                     |
| ---------------------------------- | --------------------- | ---------------------------------------------------- | ------------------------------------------- |
| `background`, `card`, `popover`    | `#FFFFFF`             | Latar halaman, kartu, dialog, sheet                  | Tinta 17,5:1                                |
| `foreground`                       | `#1C1917`             | Teks utama                                           |                                             |
| `muted-foreground`                 | `#6B645E`             | Teks sekunder                                        | 5,82:1 di putih. (`#78716C` gagal)          |
| `secondary` / `muted`              | `#F5F3F0`             | Badge netral, panel sekunder                         | Teks muted 5,25:1                           |
| `border`                           | `#ECE8E4`             | Border kartu, pemisah                                | Dekoratif                                   |
| `input`                            | `#DAD5CF`             | Border field form                                    | 1,46:1. Override pemilik, lihat §2          |
| `primary`                          | `#C25205`             | Isian tombol utama, FAB "+ Catat", centang, kalender | Teks putih 4,66:1                           |
| `primary-foreground`               | `#FFFFFF`             | Teks dan ikon di atas `primary`                      |                                             |
| `primary-hover`                    | `#A94704`             | Hover dan border tombol utama                        | Teks putih 5,85:1                           |
| `primary-text`                     | `#C2410C`             | **Semua teks oranye** (link, angka oranye)           | 5,18:1 di putih, 4,88:1 di `accent`         |
| `accent` + `accent-foreground`     | `#FFF7ED` + `#9A3412` | Menu aktif, pilihan aktif, kartu fokus               | 6,88:1                                      |
| `brand`                            | `#F97316`             | Isian dekoratif besar                                | 2,80:1. Bukan untuk teks atau ikon bermakna |
| `brand-strong`                     | `#EA580C`             | Seri grafik disorot, ikon oranye, ring fokus         | 3,56:1 di putih (non-teks)                  |
| `brand-soft`                       | `#FFEDD5`             | Border kartu fokus                                   |                                             |
| `success` / `success-soft`         | `#047857` / `#ECFDF5` | Naik, aman, uang masuk                               | 5,21:1                                      |
| `warning` / `warning-soft`         | `#B45309` / `#FFFBEB` | Stok menipis, perlu perhatian                        | 4,84:1                                      |
| `destructive` / `destructive-soft` | `#B91C1C` / `#FEF2F2` | Turun, gagal, batalkan                               | 5,91:1                                      |
| `chart-1`                          | `#EA580C`             | Seri yang disorot                                    | 3,56:1                                      |
| `chart-2`                          | `#958D85`             | Seri lain                                            | 3,27:1                                      |
| `chart-3`                          | `#0D9488`             | Seri pembanding (jarang)                             | 3,74:1. Teks teal pakai `#0F766E`           |

### Palet aktif dan disiplin aksen

Palet dibatasi (antislop R-29):

- **Inti:** netral hangat + tinta, dan oranye.
- **Aksen jarang:** teal, hanya untuk seri pembanding di grafik.
- **Warna status** (success, warning, destructive) hanya dipakai untuk status nyata, dan selalu disertai ikon atau kata.

Oranye hanya muncul di:

1. Tombol aksi utama dan FAB (satu per area, misalnya **Catat**): `#C25205` dengan teks atau ikon putih.
2. Item menu dan pilihan yang aktif.
3. Seri grafik yang disorot.
4. Kartu fokus, satu per layar.

Oranye tidak dipakai untuk ikon biasa, garis dekoratif, atau latar section. **Teks oranye selalu memakai `text-primary-text`**, dan ikon oranye memakai `text-brand-strong`. Jangan pakai `text-primary` untuk teks.

### Tipografi

**Font:** Plus Jakarta Sans 400–800, dimuat lewat helper `bunny()` di `vite.config.ts`. Fallback-nya dioptimalkan oleh `fontaine`.

| Peran                   | Ukuran / berat                     | Kelas                           |
| ----------------------- | ---------------------------------- | ------------------------------- |
| Angka fokus             | 28 px (HP) / 32 px, 700            | `<Money size="xl">`             |
| Judul halaman           | 20 px (HP) / 24 px, 600            | `PageHeader`                    |
| Judul kartu             | 16 px, 600                         | `SectionCard`                   |
| Isi                     | 14 px                              | `text-sm`                       |
| Isi form & halaman toko | 16 px (mencegah zoom otomatis iOS) | `text-base`                     |
| Keterangan              | 12 px                              | `text-xs text-muted-foreground` |
| Label grup              | 12 px, 600, sentence case          | `SidebarGroupLabel`             |

**Suara tipografi angka:**

- Semua nominal memakai `<Money>`: prefix "Rp" sedikit lebih kecil dan `tabular-nums` (kelas `.num`).
- Uang masuk: hijau dengan "+".
- Uang keluar: tinta dengan "−" (U+2212).
- Saldo: tanpa tanda.

### Bentuk, spasi, bayangan

- **Radius:** kartu `rounded-xl` (16 px), tombol dan input `rounded-lg` (12 px), badge `rounded-md`. Tidak ada elemen pill selain avatar dan tombol tengah bottom nav.
- **Spasi:** grid 4 px.
    - Padding halaman: 16 px (HP), 24 px (≥ 768).
    - Padding kartu: 16 / 20 px.
    - Jarak antarkartu: 16 / 24 px.
- **Bayangan** (token di `@theme`, `resources/css/app.css`):
    - `shadow-button` / `shadow-button-pressed`: inner shadow tombol utama dan destruktif, serta keadaan saat ditekan.
    - `shadow-button-soft`: tombol outline dan secondary.
    - `shadow-field`: bayangan dalam tipis pada input, select, dan textarea.
    - `shadow-card`: kartu (`SectionCard`, `Panel`).
    - `shadow-lg`: hanya untuk popover, dialog, drawer, dan sheet.

## 4. Breakpoint dan layout

| Rentang              | Navigasi                                       | Konten                                                                 |
| -------------------- | ---------------------------------------------- | ---------------------------------------------------------------------- |
| < 768 px (HP)        | Bottom nav + sidebar sebagai sheet ("Lainnya") | Satu kolom. Tabel menjadi daftar kartu per tanggal                     |
| 768–1023 px (tablet) | Sama dengan HP                                 | Grid 2 kolom. Kartu penuh untuk tindakan, ringkasan, grafik, dan tabel |
| 1024–1279 px         | Sidebar (bisa diciutkan ke ikon)               | Grid 2 kolom                                                           |
| ≥ 1280 px            | Sidebar                                        | Grid 12 kolom                                                          |

- Batas navigasi diatur di `hooks/use-mobile.tsx` (`MOBILE_BREAKPOINT = 1024`) dan kelas `lg:` di shell.
- Batas tabel ke daftar (768) diatur di `AppDataTable`.
- **Target sentuh:** semua kontrol memakai `pointer-coarse:h-11` (44 px) di perangkat sentuh, dan tetap ringkas saat dipakai dengan mouse.
- **Bottom nav:** konten diberi `padding-bottom` setinggi bottom nav + `safe-area-inset-bottom`, supaya baris terakhir tidak tertutup.
- **Scroll horizontal:** tidak boleh ada di halaman. Sudah dicek di 360, 768, 1100, dan 1440 px.

## 5. Katalog komponen

| Komponen                                                                                                                     | Path                                                | Kapan dipakai                                                                                                                                                                                                                                                    |
| ---------------------------------------------------------------------------------------------------------------------------- | --------------------------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `PageHeader`                                                                                                                 | `components/app/page-header.tsx`                    | Judul halaman + meta (badge) + aksi                                                                                                                                                                                                                              |
| `SectionCard`, `IconChip`                                                                                                    | `components/app/section-card.tsx`                   | Semua kartu. Judul menyebut tugas atau pertanyaannya. `tone="focus"` maksimal satu per layar                                                                                                                                                                     |
| `MetricFocus`, `MetricInline`, `MetricTile`, `MetricCount`, `DeltaBadge`                                                     | `components/app/metric.tsx`                         | Satu angka fokus, sisanya inline. `MetricTile` untuk baris ringkasan di kartu fokus: abu (`bg-secondary`) untuk angka sehat, `warning`/`danger` hanya jika ada masalah nyata, selalu dengan ikon dan kata. Delta hanya jika periode pembanding nyata dan bernama |
| `Money`                                                                                                                      | `components/app/money.tsx`                          | Setiap nominal                                                                                                                                                                                                                                                   |
| `CategoryBadge`, `SourceLabel`, `OrderStatusBadge`, `PaymentStatusBadge`, `ItemKindBadge`, `StockStatusBadge`, `MarginLabel` | `components/app/badges.tsx`                         | Label status dan kategori. Jenis produk netral (ikon + kata). Stok hanya diberi badge kalau `Menipis` atau `Minus`. Margin: Sehat ≥ 30% hijau, Cukup 15–30% kuning, Tipis < 15% atau Rugi merah (UI-PAGES C5)                                                    |
| `AppDataTable`                                                                                                               | `components/app/app-data-table.tsx`                 | Semua tabel. Memakai TanStack Table v9 dengan `<Table>` shadcn. Sorting, pagination, state, dan daftar mobile sudah tersedia                                                                                                                                     |
| `TransactionTable`                                                                                                           | `components/app/transaction-table.tsx`              | Daftar transaksi (dashboard, halaman transaksi)                                                                                                                                                                                                                  |
| `FilterBar`                                                                                                                  | `components/app/filter-bar.tsx`                     | Chip filter + pencarian. Di halaman asli dihubungkan ke query Inertia (`router.get`, `preserveState`)                                                                                                                                                            |
| `EmptyState`, `ErrorState`, `LoadingBlock`                                                                                   | `components/app/states.tsx`                         | Tiga state wajib untuk setiap tampilan data                                                                                                                                                                                                                      |
| `PeriodPicker`                                                                                                               | `components/app/period-picker.tsx`                  | Pilihan hari ini / minggu ini / bulan ini                                                                                                                                                                                                                        |
| `MoneyInput`, `QtyStepper`                                                                                                   | `components/app/money-input.tsx`, `qty-stepper.tsx` | Input uang ("15rb", "1,5jt") dan jumlah                                                                                                                                                                                                                          |
| `SalesBarChart`                                                                                                              | `components/app/sales-bar-chart.tsx`                | Grafik batang dengan satu batang disorot dan jawaban tertulis                                                                                                                                                                                                    |
| `ActionButton`, `SoonTag`                                                                                                    | `components/app/action-button.tsx`                  | Tombol yang tujuannya mungkin belum dibangun                                                                                                                                                                                                                     |
| `DemoDataBadge`                                                                                                              | `components/app/demo-data-badge.tsx`                | Wajib di halaman yang memakai `DemoData`                                                                                                                                                                                                                         |
| `StockAdjustDialog`                                                                                                          | `components/app/stock-adjust-dialog.tsx`            | Koreksi stok satu item. Selisih dinilai dengan biaya rata-rata, sama seperti `Inventory::adjust()`                                                                                                                                                               |
| `QuickRecordSheet`                                                                                                           | `components/app/quick-record-sheet.tsx`             | Pintu masuk "+ Catat". Drawer di bawah 1024 px, dialog di atasnya                                                                                                                                                                                                |
| `MobileBottomNav`                                                                                                            | `components/app/mobile-bottom-nav.tsx`              | Navigasi HP dan tablet                                                                                                                                                                                                                                           |
| `ReceiptCard` **(Usulan)**                                                                                                   | `components/app/receipt-card.tsx`                   | Hanya untuk permukaan bukti transaksi                                                                                                                                                                                                                            |

Komponen dasar shadcn ada di `components/ui/` dan sudah disesuaikan:

- ring fokus memakai warna penuh
- target sentuh 44 px
- tombol memakai inner shadow, field memakai `shadow-field` dan border lembut
- `ui/card` tanpa bayangan (halaman memakai `SectionCard` dengan `shadow-card`)
- skeleton `motion-safe`

**Tabel:** ReUI DataGrid sudah dievaluasi dan tidak dipakai. Registry-nya mengimpor path internal Next.js mereka (`@/app/(create)/...`), menambahkan paket npm `cn` yang keliru, dan varian radix-nya tetap membutuhkan `@base-ui`, sementara ukurannya ±430 KB untuk fitur yang tidak kita perlukan. `AppDataTable` membungkus TanStack Table secara langsung.

## 6. Pola halaman

1. **Mulai dari tugas layar.** Tulis satu kalimat: keputusan apa yang diambil pemilik di layar ini. Bagian yang paling mendukung keputusan itu menjadi kartu fokus.
2. **Struktur:** `PageHeader` → kartu fokus → kartu pendukung. Hindari pola "4 kartu statistik + grafik + tabel" kalau tidak sesuai tugasnya.
3. **Judul grafik** berupa pertanyaan, dan jawabannya ditulis sebagai kalimat di atas grafik.
4. **Kolom tabel** dipilih dari keputusan yang diambil di tabel itu. Nominal selalu terlihat. Menu titik tiga hanya jika aksinya ada.
5. **Setiap tampilan data punya empat keadaan:**
    - normal
    - kosong: sebab + satu tindakan
    - memuat: menyebut apa yang dimuat
    - gagal: apa yang gagal + cara melanjutkan
6. **Halaman dengan data contoh** menampilkan `DemoDataBadge` sampai datanya berasal dari ledger asli.
7. **Tujuan yang belum dibangun** memakai `{ comingSoon: true }` atau `ActionButton`: tampil nonaktif dengan label "Segera" dan komentar `TODO(kode halaman)`. Tidak boleh ada link atau tombol mati.
8. **Dialog berisi form sendiri** yang dipasang di dalam `<form>` halaman wajib memanggil `event.stopPropagation()` di `onSubmit`. React meneruskan event lewat portal, jadi tanpa itu form halaman ikut terkirim.

## 7. Aturan konten

- **Bahasa Indonesia sehari-hari.** Di UI dan bot tidak ada istilah debit/kredit. Istilah akuntansi di laporan diberi penjelasan.
- **CTA menyebut tindakannya:** "Catat penjualan", "Terima pesanan", "Kirim pengingat". Tidak memakai "Mulai", "Pelajari", atau "Jelajahi".
- **Larangan:** tidak memakai em dash (tanda pisah panjang), tidak ada buzzword ("AI Powered", "revolusioner", "seamless"), tidak ada emoji dekoratif, dan tidak ada ikon sparkle/robot untuk fitur AI ("Tanya AI" memakai gelembung chat).
- **Format:** Rupiah `Rp15.000`, ringkas `Rp1,2 jt`, tanggal `17 Okt 2026`, zona waktu WIB. Semua lewat `lib/format.ts`.
- **Kejujuran angka:** angka, delta, nama, dan testimoni harus nyata atau jelas berlabel contoh.

## 8. Checklist sebelum halaman dianggap selesai

- [ ] Teks ≥ 4,5:1 dan elemen non-teks ≥ 3:1, dicek dengan alat, bukan ditebak.
- [ ] Tab/Shift+Tab mengikuti urutan visual, ring fokus terlihat, Esc menutup dialog/sheet.
- [ ] Tidak ada scroll horizontal di 360, 768, 1024, dan 1440 px. Bottom nav tidak menutupi konten.
- [ ] Target sentuh ≥ 44 px di perangkat sentuh.
- [ ] Empty, loading, dan error ada untuk setiap tampilan data.
- [ ] Setiap tombol dan link punya perilaku nyata, atau berlabel "Segera".
- [ ] `npm run types:check`, `npm run check`, dan `php artisan test` hijau. Konsol browser bersih.
- [ ] Laporan Delivery Gate antislop disertakan saat menyerahkan.
