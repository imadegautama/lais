# Lais: backend guideline (wajib untuk setiap perubahan PHP)

Aturan akuntansi dan data ada di `docs/PRD.md` §4. Bagian ini mengatur letak dan bentuk kodenya.

## Data dan query

- Primary key setiap tabel bernama `id`, sedangkan foreign key bernama `<model>_id` (`item_id`, `transaction_id`). Ini konvensi Eloquent; jangan diganti.
- Setiap join, subquery, atau SQL mentah wajib menulis nama tabel di depan kolom (`items.id`, `transactions.contact_id`, `$query->qualifyColumn()`). Dengan begitu, join yang ditambah belakangan tidak membuat kolom jadi ambigu. `App\Support\TableQuery` sudah menambahkan nama tabel ke nama kolom polos di sorts, filters, dan search.
- Uang selalu BIGINT rupiah, tanpa desimal.
- Input dari pengguna tidak pernah disisipkan ke SQL mentah; selalu lewat binding (`orderByRaw('items.kind = ?', [ItemKind::Komposisi->value])`). Hanya konstanta kode, seperti nilai enum di `Item::UNIT_COST_SQL`, yang boleh ditulis langsung.

## Istilah domain

- Semua istilah (jenis item, jenis dan subjenis transaksi, cara bayar, sumber, status, jenis kontak, alasan stok, periode) adalah enum string di `app/Enums`. Buat dengan `php artisan make:enum`.
    - Label dan aturan yang menempel pada istilah ditulis sebagai method di enumnya, misalnya `label()`, `TransactionType::methods()`, `savedMessage()`, dan `ContactType::for()`.
    - Kolomnya di-cast ke enum di model.
- Logika bisnis tidak boleh memakai literal seperti `'kredit'` atau `'komposisi'`. Bandingkan dengan enumnya: `$transaction->method === PaymentMethod::Kredit`.
- Baris jurnal 1101/1102 selalu menyebut rekeningnya (`money_account_id`), dan `Ledger::post()` menolak baris kas tanpa rekening atau dengan jenis rekening yang salah.
    - Di `Ledger::lines()` kuncinya `kode@rekening`, lewat `Ledger::cashKey($moneyAccount)`. Dengan begitu satu jurnal bisa memuat dua rekening 1102 (Pindah dana BCA → GoPay).
    - Saldo rekening dibaca dari `Ledger::moneyBalances()`, tidak disimpan di tabel.
- Saldo awal dicatat sebagai `TransactionType::SaldoAwal`, dengan kind `saldo_kas`, `saldo_peralatan`, `saldo_utang`, atau `saldo_piutang`. Modal Pemilik selalu jadi lawannya.
    - Cara bayarnya diturunkan otomatis (`TransactionKind::openingMethod()`), sama seperti Pindah dana, sehingga `TransactionType::methods()` kosong.
    - Ringkasan uang masuk/keluar tidak menghitung saldo awal.
- Kode akun memakai konstanta `App\Models\Account` (`Account::INVENTORY`, `Account::COGS`, dan seterusnya). Bentuknya konstanta, bukan enum, karena dipakai sebagai key array di `Ledger::lines()`.
- Validasi daftar nilai memakai `Rule::enum(...)`. Untuk subset (cara bayar per jenis), pakai `Rule::in(array_column($type->methods(), 'value'))`, karena `Rule::enum()->only([])` justru meloloskan semuanya.
- Setelah enum ditambah atau diubah, jalankan `php artisan lais:enums` supaya `resources/js/types/enums.ts` ikut berubah. `VocabularyTest` gagal kalau lupa.

## Letak kode

- `app/Services`: aturan bisnis dan pembukuan (`TransactionService`, `Inventory`, `Ledger`). Bot WhatsApp nanti memakai service yang sama dengan web, jadi aturan yang perlu data (saldo, stok) dilempar sebagai `ValidationException` dari sini.
- `app/Http/Requests`: bentuk input dan pesan validasi berbahasa Indonesia.
- `app/Http/Resources`: bentuk data untuk props halaman. Controller memanggil `XResource::make($model)->resolve()` atau `XResource::collection($models)->resolve()`, dan tidak menyusun array model sendiri. `resolve()` dipakai supaya tidak ada pembungkus `data`.
- Controller hanya mengatur alur: otorisasi, panggil service, render, flash.
- Produk dan bahan dibuat lewat `App\Services\Catalog::create()` (atau `createMany()`, yang memetakan resep lewat nama bahan). Form produk, wizard onboarding, dan seeder demo memakai jalur yang sama.
- Pembukuan dibuka lewat `App\Services\Onboarding::complete()`: saldo awal dalam satu DB transaction, bertanggal `started_on`. Batasnya `Onboarding::latestStart()`.
- Angka laporan (laba rugi per periode, prive, modal, cek kesehatan, stok menipis, piutang terbuka, penjualan per hari) dihitung di `App\Services\Reports`, selalu dari jurnal. Dashboard, halaman laporan, dan tool baca AI memakai method yang sama, jadi angkanya tidak pernah berbeda antarlayar.
    - Pembatalan tidak perlu disaring, karena jurnal cerminnya bertanggal sama sehingga nilainya nol dalam periode apa pun. Daftar yang memakai baris transaksi (umur piutang, pemakaian stok) menyaring `TransactionStatus::Posted`.
    - Modal awal sebuah periode dihitung dengan `Reports::openingEquity()`. Di periode saat pembukuan dibuka, nilainya adalah neraca pembukaan (`started_on` 00.00).
- Transaksi yang sudah tercatat tidak pernah diedit atau dihapus. Batalkan lewat `TransactionService::cancel()` dan ubah lewat `replace()`. Keduanya membuat jurnal cermin bertanggal transaksi asli dan membalik setiap mutasi stoknya (`Inventory::reverse()`).

## Log aktivitas (audit)

- `spatie/laravel-activitylog` v5, dengan model `App\Models\Activity`. Log hanya bisa ditambah: model itu menolak update dan delete, dan `activitylog:clean` tidak pernah dijadwalkan. `business_id` diisi otomatis dari subject atau causer.
- Data master (`Item`, `Contact`, `User`) memakai trait `LogsActivity` dengan `logOnly` untuk kolom yang diatur pemilik, ditambah `logOnlyDirty()->dontLogEmptyChanges()`. Kolom yang bergerak sendiri, seperti stok, jangan ikut dicatat.
- Peristiwa yang tidak tertangkap event model dicatat eksplisit dengan `activity(ActivityCategory::…)->event(ActivityEvent::…->value)`:
    - transaksi (`TransactionService`)
    - resep (`ItemController::update`, karena resep disimpan dengan hapus lalu buat ulang)
    - koreksi stok (`Inventory::adjust`)

    Perbedaan nilai lama dan baru dikirim lewat `withChanges(['old' => …, 'attributes' => …])`.

- Jurnal dan mutasi stok tidak dicatat ke log aktivitas, karena keduanya sudah menjadi jejak audit uang.
- Label field dan format tampilannya ada di `ActivityResource::FIELDS`. Field baru yang perlu terlihat di riwayat ditambahkan di sana.
- Jalur tanpa sesi login, misalnya bot WhatsApp, memanggil `Activity::defaultCauser($business->user)` (facade `Spatie\Activitylog\Facades\Activity`) dan `Context::add('activity_source', 'wa')` di titik masuknya.
- Password, token, kode OTP/2FA, dan secret lain tidak pernah masuk `properties` atau `logOnly`.

## Test

- Test fitur class-based dengan `RefreshDatabase`.
- Payload HTTP dan kode akun di test ditulis sebagai literal (`'tunai'`, `'1301'`), karena itulah kontraknya. Atribut yang sudah di-cast dibandingkan dengan enumnya (`ContactType::Supplier`), karena string tidak akan pernah sama dengan enum.
- Hitungan di frontend yang meniru aturan server diuji dengan angka yang sama dengan test PHP-nya.
