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
- Kode akun memakai konstanta `App\Models\Account` (`Account::INVENTORY`, `Account::COGS`, dan seterusnya). Bentuknya konstanta, bukan enum, karena dipakai sebagai key array di `Ledger::lines()`.
- Validasi daftar nilai memakai `Rule::enum(...)`. Untuk subset (cara bayar per jenis), pakai `Rule::in(array_column($type->methods(), 'value'))`, karena `Rule::enum()->only([])` justru meloloskan semuanya.
- Setelah enum ditambah atau diubah, jalankan `php artisan lais:enums` supaya `resources/js/types/enums.ts` ikut berubah. `VocabularyTest` gagal kalau lupa.

## Letak kode

- `app/Services`: aturan bisnis dan pembukuan (`TransactionService`, `Inventory`, `Ledger`). Bot WhatsApp nanti memakai service yang sama dengan web, jadi aturan yang perlu data (saldo, stok) dilempar sebagai `ValidationException` dari sini.
- `app/Http/Requests`: bentuk input dan pesan validasi berbahasa Indonesia.
- `app/Http/Resources`: bentuk data untuk props halaman. Controller memanggil `XResource::make($model)->resolve()` atau `XResource::collection($models)->resolve()`, dan tidak menyusun array model sendiri. `resolve()` dipakai supaya tidak ada pembungkus `data`.
- Controller hanya mengatur alur: otorisasi, panggil service, render, flash.
- Transaksi yang sudah tercatat tidak pernah diedit atau dihapus. Batalkan lewat `TransactionService::cancel()` dan ubah lewat `replace()`. Keduanya membuat jurnal cermin bertanggal transaksi asli dan membalik setiap mutasi stoknya (`Inventory::reverse()`).

## Test

- Test fitur class-based dengan `RefreshDatabase`.
- Payload HTTP dan kode akun di test ditulis sebagai literal (`'tunai'`, `'1301'`), karena itulah kontraknya. Atribut yang sudah di-cast dibandingkan dengan enumnya (`ContactType::Supplier`), karena string tidak akan pernah sama dengan enum.
- Hitungan di frontend yang meniru aturan server diuji dengan angka yang sama dengan test PHP-nya.
