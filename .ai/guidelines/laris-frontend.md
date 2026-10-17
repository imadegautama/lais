# Laris: frontend guideline (wajib untuk setiap perubahan UI)

Proyek GTNIC 2026: sistem operasional UMKM berbasis web + WhatsApp. Stack: Laravel 13, Inertia 3, React 19, TypeScript, Tailwind 4, shadcn/ui. Spesifikasi produk ada di `docs/PRD.md`, daftar halaman di `docs/UI-PAGES.md`.

## Sumber kebenaran desain

- `docs/DESIGN.md`: token, alasan keputusan, katalog komponen, pola halaman, checklist.
- `/design-system` (hanya `APP_ENV=local`): referensi visual hidup untuk semua komponen dan state.
- `resources/css/app.css`: nilai token. Kode adalah sumber kebenaran. Kalau dokumen berbeda dengan kode, perbaiki dokumennya di commit yang sama.

## Sebelum mengubah UI

1. Baca `docs/DESIGN.md` dan lihat `/design-system`.
2. Pakai komponen yang sudah ada sebelum membuat yang baru: `resources/js/components/app/*` (PageHeader, SectionCard, MetricFocus/MetricInline, DeltaBadge, Money, badges, AppDataTable (mode client atau server), TransactionTable, FilterBar/ChipFilter/SearchFilter, `useTableQuery` + `App\Support\TableQuery` untuk tabel server, EmptyState/ErrorState/LoadingBlock, PeriodPicker, MoneyInput, QtyStepper, SearchSelect, FormActionBar, ActionButton, DemoDataBadge, QuickRecordSheet) dan `resources/js/components/ui/*` (shadcn).
3. Muat skill UI: antislop (mode DURING + laporan Delivery Gate), frontend-design, ui-ux-pro-max. Mode ponytail aktif: perubahan terkecil yang lengkap.

## Aturan wajib

1. **Hanya token.** Tidak ada hex atau rgb mentah di komponen; nilai baru masuk `app.css` (`:root` + `@theme`).
2. **Oranye:**
    - tombol utama `bg-primary` (`#C25205`) selalu dengan `text-primary-foreground` (putih, 4,66:1)
    - teks oranye selalu `text-primary-text` (`#C2410C`)
    - ikon, sorotan grafik, dan ring fokus memakai `brand-strong` (`#EA580C`)
    - jangan pakai `text-primary` untuk teks
3. **Kontras diukur, bukan ditebak:** `contrast-check.py` dari skill antislop-human. Teks ≥ 4,5:1, non-teks ≥ 3:1. Satu-satunya pengecualian yang dicatat: border field `#DAD5CF` (pilihan pemilik).
4. **Latar putih dengan wash hangat** (`bg-page-wash` di layout, jangan dipasang per halaman). Tidak ada teks oranye langsung di atas wash. Kartu memakai `border` + `shadow-card` (sudah berisi inner shadow tipis), kotak ringkasan memakai `shadow-tile`. Tombol memakai inner shadow (`shadow-button`, `shadow-button-pressed`, `shadow-button-soft`). Field memakai `shadow-field` dan latar putih solid. `shadow-lg` hanya untuk overlay.
5. **Setiap tampilan data punya state** normal, kosong (sebab + satu tindakan), memuat (menyebut apa yang dimuat), dan gagal (apa yang gagal + coba lagi). Tujuan yang belum dibangun memakai `{ comingSoon: true }` / `ActionButton` dengan label "Segera". Tidak boleh ada link atau tombol mati.
6. **Mobile-first.**
    - Target sentuh 44 px lewat `pointer-coarse:`.
    - Bottom nav dan sidebar sheet di bawah 1024 px.
    - Tabel berubah jadi daftar kartu di bawah 768 px.
    - Tidak ada scroll horizontal di 360 px.
7. **Konten:** Bahasa Indonesia sehari-hari, CTA menyebut tindakannya, tanpa em dash, buzzword, atau emoji. Uang dan tanggal hanya lewat `resources/js/lib/format.ts` dan `<Money>`. Angka atau data contoh selalu berlabel `DemoDataBadge`.
8. **Light mode saja.** Grafik tanpa animasi (MOTION 1). Hormati `prefers-reduced-motion`.
9. **Perubahan keputusan desain** wajib memperbarui `docs/DESIGN.md` dan `/design-system` di commit yang sama.

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
