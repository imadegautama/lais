# Lais: frontend guideline (wajib untuk setiap perubahan UI)

Proyek GTNIC 2026: sistem operasional UMKM berbasis web + WhatsApp. Stack: Laravel 13, Inertia 3, React 19, TypeScript, Tailwind 4, shadcn/ui. Spesifikasi produk ada di `docs/PRD.md`, daftar halaman di `docs/UI-PAGES.md`.

## Sumber kebenaran desain

- `docs/DESIGN.md`: token, alasan keputusan, katalog komponen, pola halaman, checklist.
- `/design-system` (hanya `APP_ENV=local`): referensi visual hidup untuk semua komponen dan state.
- `resources/css/app.css`: nilai token. Kode adalah sumber kebenaran. Kalau dokumen berbeda dengan kode, perbaiki dokumennya di commit yang sama.

## Sebelum mengubah UI

1. Baca `docs/DESIGN.md` dan lihat `/design-system`.
2. Pakai komponen yang sudah ada sebelum membuat yang baru: `resources/js/components/app/*` (PageHeader, SectionCard, MetricFocus/MetricInline, DeltaBadge, Money, badges, AppDataTable (mode client atau server), FilterBar/ChipFilter/SearchFilter, `useTableQuery` + `App\Support\TableQuery` untuk tabel server, EmptyState/ErrorState/LoadingBlock, PeriodPicker, MoneyInput, QtyStepper, SearchSelect, ChoiceGroup, SwitchRow, DetailRow, StockMovementList, FormActionBar, Field, ActionButton, DemoDataBadge, QuickRecordSheet), `resources/js/features/*` (TransactionTable, ItemTable, StockAdjustDialog, dan komponen form per fitur), dan `resources/js/components/ui/*` (shadcn).
3. Muat skill UI: antislop (mode DURING + laporan Delivery Gate), frontend-design, ui-ux-pro-max. Mode ponytail aktif: perubahan terkecil yang lengkap.

## Aturan wajib

1. **Hanya token.** Tidak ada hex atau rgb mentah di komponen; nilai baru masuk `app.css` (`:root` + `@theme`).
2. **Oranye:**
    - tombol utama `bg-primary` (`#C25205`) selalu dengan `text-primary-foreground` (putih, 4,66:1)
    - teks oranye selalu `text-primary-text` (`#C2410C`)
    - ikon, sorotan grafik, ring fokus, dan progress bar navigasi Inertia memakai `brand-strong` (`#EA580C`)
    - jangan pakai `text-primary` untuk teks
3. **Kontras diukur, bukan ditebak:** `contrast-check.py` dari skill antislop-human. Teks ≥ 4,5:1, non-teks ≥ 3:1. Satu-satunya pengecualian yang dicatat: border field `#DAD5CF` (pilihan pemilik).
4. **Latar putih dengan wash hangat** (`bg-page-wash` di layout, jangan dipasang per halaman). Tidak ada teks oranye langsung di atas wash. Kartu memakai `border` + `shadow-card` (sudah berisi inner shadow tipis), kotak ringkasan memakai `shadow-tile`. Tombol memakai inner shadow (`shadow-button`, `shadow-button-pressed`, `shadow-button-soft`). Field memakai `shadow-field` dan latar putih solid. `shadow-lg` hanya untuk overlay.
5. **Setiap tampilan data punya state** normal, kosong (sebab + satu tindakan), memuat (menyebut apa yang dimuat), dan gagal (apa yang gagal + coba lagi). Tujuan yang belum dibangun memakai `{ comingSoon: true }` / `ActionButton` dengan label "Segera". Tidak boleh ada link atau tombol mati.
6. **Mobile-first.**
    - Target sentuh 44 px lewat `pointer-coarse:`.
    - Bottom nav dan sidebar sheet di bawah 1024 px.
    - Tabel berubah jadi daftar kartu di bawah 768 px.
    - Tidak ada scroll horizontal di 360 px.
7. **Konten:** Bahasa Indonesia sehari-hari, CTA menyebut tindakannya, tanpa em dash, buzzword, atau emoji. Uang dan tanggal hanya lewat `resources/js/lib/format.ts` dan `<Money>`. Zona waktu mengikuti server (`APP_TIMEZONE`, dikirim sebagai prop `timeZone` dan dipasang di `app.tsx`). Jangan menulis `'Asia/Jakarta'` atau "WIB" langsung; pakai `appTimeZone()` untuk `Intl` dan `zoneLabel()` untuk label jam. Angka atau data demo di aplikasi selalu berlabel `DemoDataBadge` ("Data demo"). Di landing, label itu hanya di toko demo Bu Rina; ilustrasi landing lain tidak berlabel (keputusan pemilik).
8. **Light mode saja.** Grafik tanpa animasi (MOTION 1). Hormati `prefers-reduced-motion`.
9. **Perubahan keputusan desain** wajib memperbarui `docs/DESIGN.md` dan `/design-system` di commit yang sama.

## Letak kode

- `pages/`: halaman tipis. Isinya props, `useForm`, dan susunan komponen. Tidak ada aturan bisnis, peta label, atau helper di file halaman.
- `features/<fitur>/` (`items`, `transactions`): komponen dan logika satu fitur.
    - Aturan murni ditulis di file `.ts` dengan test di sebelahnya, misalnya `transactions/record.ts` dan `items/cost.ts`.
    - Teks UI per jenis ditulis di `vocabulary.ts`.
    - Satu fitur tidak mengimpor fitur lain. Halaman boleh mengimpor fitur mana pun.
- `components/app/`: UI lintas fitur. Tidak mengimpor dari `features/`. Shell (header, bottom nav) termasuk di sini, karena itu `QuickRecordSheet` ada di sini.
- `lib/`: fungsi murni bersama, masing-masing dengan test:
    - `number.ts`: angka ketikan pemilik dan pembulatan cara PHP
    - `stock.ts`: aturan nilai stok `Inventory`
    - `format.ts`
    - `form.ts`: `fieldSetter`, `focusFirstError`, `listEditor`, `rowError`
- `hooks/`: hanya untuk yang memegang state React (`useTableQuery`).
- `types/enums.ts` **dibuat otomatis** oleh `php artisan lais:enums` dari `app/Enums`. Jangan diubah manual. Nilai ditulis lewat konstantanya (`TransactionType.Penjualan`), label lewat `transactionTypeLabels` dan sejenisnya, bukan string tulisan tangan. `badges.tsx` hanya menambahkan ikon.
- Hitungan yang meniru server (HPP, rata-rata, biaya resep, efek kas) memakai `lib/stock.ts` atau `features/*`, dengan test yang memakai angka dari test PHP.

## Setup awal dan tur

- Istilah akuntansi dijelaskan dari satu sumber: `terms` dan `TermHint` di `components/app/term-hint.tsx` (ⓘ di laporan, chip "Apa itu …?" di setup). Istilah baru ditambahkan di sana, satu kalimat sehari-hari.
- Setup (`/onboarding`) adalah pengecualian gerak kedua setelah landing: transisi langkah, lencana, dan meter progres saja, semuanya `motion-safe:` dari `tw-animate-css`, transform dan opacity. Aplikasi lain tetap MOTION 1.
- Gamifikasi hanya progres, lencana, dan misi yang tercentang dari data. Tanpa poin, level, atau konfeti.
- Panduan per halaman ada di `components/app/page-guide.tsx` (`GUIDES`, berdasarkan nama komponen Inertia), dan tombolnya di header atas. Langkahnya mencari kartu lewat judulnya (`card()`), jadi mengganti judul kartu atau label tombol di halaman yang punya panduan berarti memperbarui `GUIDES` juga. Halaman fitur baru sebaiknya ikut mendapat panduan.
- Mesin tur di `components/app/product-tour.tsx` (React Joyride 3). Target diberi `data-tour` atau dicari lewat link yang tampil di layar; langkah yang targetnya tidak tampil dibuang sebelum tur mulai. Tooltip memakai komponen Lais, bukan gaya bawaan Joyride. Tur terbuka sendiri selama `users.tour_completed_at` kosong, dan hanya Selesai atau Lewati yang mengisinya (`POST /tur-selesai`); jangan simpan tanda tur di `localStorage`, karena pemilik berganti perangkat.

## Landing page

- Isi demo chat ada di `features/landing/examples.ts`, tanpa memanggil AI. Setiap angkanya dibuktikan `tests/Feature/LandingTest.php`, jadi kalau katalog awal atau balasan bot berubah, perbarui keduanya.
- Animasi landing ada di `resources/css/landing.css` dan `use-reveal.ts`, dan hanya untuk landing. Lenis (`use-smooth-scroll.ts`) dipasang di halaman ini saja dan dihancurkan saat pindah halaman; jangan dipakai di aplikasi. Aplikasi tetap MOTION 1. Hanya hook yang boleh menyembunyikan elemen, dan hanya yang di bawah layar, supaya tanpa JS halaman tetap utuh.
- Gambar laptop (`public/images/landing/app-*.webp`) adalah tangkapan layar asli pada 1440×900. Ambil ulang kalau tampilan aplikasi berubah, dari database SQLite sementara yang diisi `php artisan db:seed`, jangan dari database lokal pemilik.
- Foto landing hanya dari sumber yang lisensinya bisa diperiksa, dengan kredit di dekat fotonya (`businessPhotos` di `examples.ts`). Jangan memakai foto yang asalnya tidak diketahui.
- Angka statistik hanya boleh yang sumbernya bisa diperiksa, dan ditulis bersama sumbernya. Tidak ada testimoni, logo mitra, atau jumlah pengguna sampai datanya nyata.

## Selesai berarti

- `npm run types:check`, `npm run check`, dan `php artisan test` hijau.
- Dicek di browser pada 360 dan 1440 px. Konsol bersih. Setiap elemen interaktif diklik.
- Lighthouse accessibility tetap 100.
- Laporan Delivery Gate antislop disertakan.

## Catatan alat

- Setelah `shadcn add`, ganti `import { cn } from "cn"` menjadi `@/lib/utils`. Jangan timpa komponen kit tanpa memeriksa diff.
- Jalankan npx lewat `npm exec --yes -- <paket>`, karena hook rtk merusak `npx paket@versi`.
- Server dev biasanya sudah berjalan dari `composer run dev` milik pengguna (`http://127.0.0.1:8002`). Jangan menyalakan server kedua.
- `CLAUDE.md`, `AGENTS.md`, `.mcp.json`, `boost.json`, `.claude/skills`, dan `.agents/` dibuat Laravel Boost (semuanya gitignored). Ubah aturan di `.ai/guidelines/`, lalu jalankan `php artisan boost:install --guidelines --skills --mcp -n`. `boost:update` menolak berjalan di versi 2.10.3.

<!-- antislop:start -->

## antislop

Untuk pekerjaan UI, copy, aksesibilitas, layout mobile, atau komentar kode, baca skill antislop (core) lalu skill sesuai tugasnya: antislop-ui, antislop-copywriting, antislop-human, antislop-layoutmobile, antislop-code.
Mode proyek ini: **during** (pilihan pemilik). Umumkan `antislop active: during (session override).` sekali sebelum edit pertama, kecuali pengguna memilih mode lain di sesi itu.
<!-- antislop:end -->
