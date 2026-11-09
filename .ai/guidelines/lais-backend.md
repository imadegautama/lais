# Lais: backend guideline (wajib untuk setiap perubahan PHP)

Aturan akuntansi dan data ada di `docs/PRD.md` §4. Bagian ini mengatur letak dan bentuk kodenya.

## Data dan query

- Primary key setiap tabel bernama `id`, sedangkan foreign key bernama `<model>_id` (`item_id`, `transaction_id`). Ini konvensi Eloquent; jangan diganti.
- Setiap join, subquery, atau SQL mentah wajib menulis nama tabel di depan kolom (`items.id`, `transactions.contact_id`, `$query->qualifyColumn()`). Dengan begitu, join yang ditambah belakangan tidak membuat kolom jadi ambigu. `App\Support\TableQuery` sudah menambahkan nama tabel ke nama kolom polos di sorts, filters, dan search.
- Uang selalu BIGINT rupiah, tanpa desimal.
- Zona waktu satu sumber: `config('app.timezone')` dari `APP_TIMEZONE` (bawaan `Asia/Jakarta`; usaha di Bali memakai `Asia/Makassar`). Jadwal di `routes/console.php` dan `DemoData` membacanya, dan label jam memakai format `T` (WIB/WITA/WIT). Kolom waktu disimpan tanpa zona, jadi set `APP_TIMEZONE` sebelum ada data dan jangan diganti sesudahnya: data lama ikut bergeser.
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
- Kode akun sistem memakai konstanta `App\Models\Account` (`Account::INVENTORY`, `Account::COGS`, dan seterusnya). Bentuknya konstanta, bukan enum, karena dipakai sebagai key array di `Ledger::lines()`.
    - Akun sistem punya `business_id` null dan terkunci. Usaha hanya bisa menambah akun Beban (mulai 6501) dan Pendapatan (mulai 4201), mengganti nama, dan mengarsipkan; tidak ada hapus.
    - Kode akun usaha bisa sama di dua usaha, jadi pencarian akun wajib lewat `Account::visibleTo($business)`.
    - Semua akun laba rugi berkode 4000 ke atas. `Reports::profit()` dan `profitAndLoss()` membaca rentang kode, jadi akun usaha otomatis ikut laba.
    - Setiap jenis biaya punya akun beban sistem sendiri (6102 Listrik & air … 6107 Pemasaran, Lain-lain di 6101) lewat `TransactionKind::expenseAccount()`, juga untuk biaya dari AI dan bot. Akun yang dipilih pemilik (`account_id`) tetap menang. `profitAndLoss()['expenses']` dan `expensesByKind()` membaca `Account::OPERATING_EXPENSES` (6101–6107) dan mengelompokkan per `transactions.kind`, jadi biaya lama di 6101 tetap terbaca per jenis. Peta jenis ke kode diekspor ke TS sebagai `transactionKindExpenseAccounts` (`lais:enums`).
    - Akun usaha dibuat hanya lewat `Account::addTo()` (Daftar akun dan "Akun khusus" di setup).
- Validasi daftar nilai memakai `Rule::enum(...)`. Untuk subset (cara bayar per jenis), pakai `Rule::in(array_column($type->methods(), 'value'))`, karena `Rule::enum()->only([])` justru meloloskan semuanya.
- Setelah enum ditambah atau diubah, jalankan `php artisan lais:enums` supaya `resources/js/types/enums.ts` ikut berubah. `VocabularyTest` gagal kalau lupa.

## Letak kode

- `app/Services`: aturan bisnis dan pembukuan (`TransactionService`, `Inventory`, `Ledger`). Bot WhatsApp nanti memakai service yang sama dengan web, jadi aturan yang perlu data (saldo, stok) dilempar sebagai `ValidationException` dari sini.
- `app/Http/Requests`: bentuk input dan pesan validasi berbahasa Indonesia.
- `app/Http/Resources`: bentuk data untuk props halaman. Controller memanggil `XResource::make($model)->resolve()` atau `XResource::collection($models)->resolve()`, dan tidak menyusun array model sendiri. `resolve()` dipakai supaya tidak ada pembungkus `data`.
- Controller hanya mengatur alur: otorisasi, panggil service, render, flash.
- Produk dan bahan dibuat lewat `App\Services\Catalog::create()` (atau `createMany()`, yang memetakan resep lewat nama bahan). Form produk, wizard onboarding, dan seeder demo memakai jalur yang sama.
- Pembukuan dibuka lewat `App\Services\Onboarding::complete()`: saldo awal dalam satu DB transaction, bertanggal `started_on`, ditambah akun khusus (`custom_accounts`) dari langkah Akun. Batasnya `Onboarding::latestStart()`. Langkah wizard di server (`OnboardingRequest::STEPS`), kunci draf (`OnboardingController::update`), dan langkah klien (`STEPS`, `STEP_OF_FIELD` di `features/onboarding/onboarding.ts`) harus selalu sama. Selesai setup mengarah ke `dashboard?tur=1`.
- Misi pertama di dashboard dihitung dari data di `DashboardController::missions()`, tidak pernah ditandai manual.
- Angka laporan (laba rugi per periode, prive, modal, cek kesehatan, stok menipis, piutang terbuka, penjualan per hari) dihitung di `App\Services\Reports`, selalu dari jurnal. Dashboard, halaman laporan, dan tool baca AI memakai method yang sama, jadi angkanya tidak pernah berbeda antarlayar.
    - Pembatalan tidak perlu disaring, karena jurnal cerminnya bertanggal sama sehingga nilainya nol dalam periode apa pun. Daftar yang memakai baris transaksi (umur piutang, pemakaian stok) menyaring `TransactionStatus::Posted`.
    - Modal awal sebuah periode dihitung dengan `Reports::openingEquity()`. Di periode saat pembukuan dibuka, nilainya adalah neraca pembukaan (`started_on` 00.00).
    - Laporan memakai `Reports::profitAndLoss()`, `expensesByKind()`, `equityChanges()`, `balanceSheet()`, dan `cashReconciliation()`.
    - Periode laporan selalu lewat `App\Support\ReportRange` (`?periode=` atau `?dari=&sampai=`), dan kesimpulan di puncak laporan dari `App\Services\ReportStory`, dihitung dari angka jurnal tanpa AI.
        - Pergerakan sebuah periode dihitung mulai `started_on` + 1 detik kalau pembukuan dibuka di dalam periode itu (`periodStart()`). Dengan begitu saldo awal menjadi modal awal, bukan setoran.
        - Setoran = −net 3101 di jendela itu: setor modal, belanja dengan uang pribadi, dan stok awal produk baru.
        - Rekonsiliasi laba vs kas harus selalu bersisa Rp0. Akun baru di bagan akun wajib ditambahkan ke `Reports::CASH_STEPS`, karena `LedgerTest` akan gagal.
- Acuan akuntansi adalah SAK EMKM. Lais hanya boleh menyebut "mengacu pada SAK EMKM", bukan "patuh", selama ada batasan yang ditulis di CALK (`resources/js/features/reports/vocabulary.ts`). Aturan baru yang mengubah kebijakan wajib memperbarui teks kebijakan di sana.
- Penyusutan hanya lewat `App\Services\Depreciation`: garis lurus tanpa nilai residu, per bulan penuh mulai bulan perolehan, sisa pembulatan di bulan terakhir, bertanggal akhir bulan, idempoten lewat `depreciated_through`. Aset dengan `useful_life_months` null tidak dijurnal. Pembatalan pembelian membalik jurnal penyusutannya di tanggal aslinya.
- Pesanan (M4) hanya bergerak lewat `App\Services\Orders`: `place`, `accept`, `reject`, `markReady`, `complete`, `cancel`, `recordPayment`.
    - Harga selalu diambil dari katalog di server dan disimpan sebagai snapshot di `order_lines`. Browser hanya mengirim `item_id` dan `qty`.
    - Uang tidak disimpan di pesanan. Pembayaran sebelum Selesai adalah transaksi `utang_piutang` kind `uang_muka` (Kas/2201), Selesai mencatat penjualan dengan `deposit` (2201 dipakai, sisanya piutang), dan pembayaran sesudahnya `terima_piutang`. Status bayar dihitung dari `Orders::paid()`, yang membaca sisa tagihan penjualannya dari `Debts`.
    - Transaksi dengan `order_id` dikunci di halaman Transaksi; ubah atau batalkan dari pesanannya. Batal pesanan membatalkan uang mukanya lewat `TransactionService::cancel()`.
    - `uang_muka` tidak ada di `TransactionType::kinds()`, jadi tidak bisa dipilih di form manual maupun oleh AI.
    - Batas 5 pesanan per IP per 10 menit dihitung hanya dari pesanan yang berhasil (`ShopController::store`).
    - Toko hanya menerima sampai `Orders::left()`: stok dikurangi unit di pesanan aktif (`Orders::held()`, status `OrderStatus::holdsStock()`), karena stok baru keluar saat Selesai. `place()` mengunci item dengan `lockForUpdate()` supaya unit terakhir tidak terjual dua kali. Produk resep tidak dihitung (saklar Tersedia), dan pesanan manual pemilik tidak dibatasi karena bisa dibuat atau dibeli belakangan. Katalog mengirim `left` ke browser lewat `ShopController::catalog()`.
- Bayar online hanya lewat `App\Services\Doku` (DOKU Checkout, non-SNAP, satu akun platform, sandbox untuk lomba). Ditawarkan kalau `businesses.online_payment` menyala dan `DOKU_CLIENT_ID`/`DOKU_SECRET_KEY` terisi (`Doku::offeredBy()`).
    - Satu halaman DOKU = satu `OnlinePayment` (invoice `LAIS{order_id}{8 huruf/angka}`, maksimal 30 karakter tanpa simbol). Halaman yang masih terbuka dipakai ulang.
    - Status datang dari notifikasi `POST /webhooks/doku` (signature HMAC, `Request-Target` = path notifikasi) atau dari Check Status saat nota dibuka lebih dari semenit kemudian. Keduanya lewat `Doku::apply()`, dan `settle()` mengunci baris supaya pembayaran hanya dicatat sekali.
    - Uang masuk menjadi uang muka lewat `Orders::recordPayment()` ke rekening non-tunai "Saldo DOKU", dan pesanan yang masih Menunggu otomatis diterima. Jumlah yang tidak sama diabaikan dan dilaporkan.
    - Pembayaran untuk pesanan yang sudah ditolak, atau lebih dari sisa tagihan, tidak dijurnal; pemilik dikabari lewat bot untuk refund manual di dashboard DOKU, karena Checkout tidak punya API refund. Biaya DOKU tidak ada di notifikasi, jadi dicatat pemilik sebagai beban saat settlement.
    - Waktu `expired_date` dari DOKU ditulis dalam UTC+7 dan diubah ke zona aplikasi sebelum disimpan.
- Masuk dan daftar dengan Google hanya lewat `App\Http\Controllers\Auth\GoogleController` (Socialite). Email Google disambungkan ke akun yang sama hanya kalau profil Google menandai `email_verified` = true; tanpa itu ditolak, karena penyambungan lewat email bisa dipakai mengambil alih akun. Akun dengan 2FA tetap ke `two-factor.login` (session `login.id`), Google tidak melewati kode. Akun baru dari Google dibuat tanpa password (`password` null), langsung terverifikasi, lalu ke onboarding. Selama belum ada password, alias `password.confirm` (`RequirePasswordIfSet`) tidak meminta konfirmasi dan Keamanan menampilkan "Buat password" tanpa password lama; hapus akun tetap wajib password. `google_id` ada di `Hidden`, jadi tidak pernah sampai ke browser. Tombolnya hanya tampil kalau `services.google.client_id` diisi (prop `googleLogin`).
- Pesan bawaan login, daftar, dan reset password ada di `lang/id` (`APP_LOCALE=id`). Form lain tetap menulis pesannya di `FormRequest`.
- Landing page (`LandingController`) membaca toko contoh `warung-bu-rina` dari data asli lewat `ShopController::catalog()`. Kalau toko itu tidak ada atau tidak punya produk yang bisa dipesan, bagiannya tidak tampil. Nomor WA dan QRIS toko tidak ikut dikirim.
- Halaman error (`errors/show`) dirender dari `bootstrap/app.php` hanya saat `APP_DEBUG` mati dan bukan permintaan JSON. `APP_DEBUG` wajib `false` di server.
- Menghapus data orang hanya lewat `App\Services\Contacts`: kontak berriwayat dianonimkan, bukan dihapus, supaya pembukuan tetap utuh. `Business::contacts()` menyembunyikan yang terhapus. Nomor HP tidak boleh masuk log aktivitas, karena log tidak bisa diubah.
- Sisa utang-piutang per tagihan hanya dihitung di `App\Services\Debts` (`books()`, `summary()`, `remaining()`). Aturannya: pembayaran yang punya `order_id` melunasi tagihan pesanan itu dulu, sisanya melunasi tagihan paling lama dulu. Halaman Utang-piutang, aksi piutang di dashboard, dan `Orders::paid()` memakai jalur ini, jadi status bayar pesanan ikut berubah walaupun dibayar dari halaman Utang-piutang. Saldo total per kontak tetap boleh dibaca dari `Ledger::contactBalances()`; keduanya sama.
- Bot: salurannya dipilih `BOT_CHANNEL` lewat interface `App\Services\WhatsApp\BotChannel` (`Telegram` atau `Gowa`). Nama kelas, tabel, dan rute tetap memakai "WhatsApp" selama Telegram sementara. Hal yang berbeda per saluran masuk `BotChannel`, bukan `Inbox`. Tombol di bawah pesan ditulis sekali sebagai `App\Services\WhatsApp\Buttons` (kirim kata atau buka halaman). Telegram menampilkannya sebagai `inline_keyboard`, dan tombol yang ditekan masuk ke `Inbox` sebagai teks biasa. GOWA dan log percakapan memakai `Buttons::asText()`, jadi mengetik kata itu selalu berlaku. Semua pesan masuk lewat `App\Services\WhatsApp\Inbox` (dari job `ProcessWhatsAppMessage`), dan semua pesan keluar hanya lewat job `SendWhatsApp`. Perintah tetap (kode `LAIS`, perintah pesanan di `OrderCommands`, "1"/"2", pertanyaan laba vs kas) dibaca tanpa LLM. Selain itu memakai `FormAssistant::reply()` dengan sumber `BotChannel::source()`. Bot hanya membalas pemilik yang terhubung dan tidak pernah mengirim ke pelanggan; kabar ke pelanggan berupa link `wa.me` dari `App\Support\OrderMessages`.
- Notifikasi HP (web push) hanya lewat `App\Services\Push` (kapan dan isinya) dan job `SendPush`. Hanya `App\Services\WebPushSender` yang memanggil library `minishlink/web-push`, supaya tes bisa menggantinya. Langganan yang dijawab kedaluwarsa (404/410) dihapus. Kunci VAPID hanya di env (`lais:vapid` menulis ke `.env` lokal tanpa mencetak kunci privat); kunci kosong berarti fitur ini mati dan kartunya tersembunyi.
- Bukti transaksi lewat `App\Support\Proof::attach()`, dipanggil **setelah** transaksi tersimpan. Disimpan di disk privat `Proof::disk()` (`PROOF_DISK`: `local` di lokal dan tes, bucket R2 `r2-private` di produksi) dan hanya dilayani `TransactionController::proof` dengan `Gate::authorize('manage')`. Jangan pernah membuat URL publik atau temporary URL untuknya. File bukti tidak pernah dihapus; "Ubah" menyalin `proof_path` ke versi baru kecuali diganti atau dihapus. Aturan validasinya `Proof::RULES` dan `Proof::MESSAGES`.
- Foto produk dan gambar QRIS disimpan di disk publik `App\Support\Media` (`MEDIA_DISK`: `public` di lokal dan tes, bucket R2 `r2-public` dengan domainnya sendiri di produksi), karena memang ditampilkan ke publik. URL-nya lewat `Item::photoUrl()` dan `Business::qrisUrl()`, yang memanggil `Media::url()`: `asset()` untuk disk lokal supaya ikut host yang membuka halaman, URL bucket untuk R2. Jangan menulis nama disk langsung di kode; pakai `Proof::disk()` atau `Media::disk()`.
- Deploy: image Docker dibangun GitHub Actions, didorong ke Docker Hub **publik**, lalu dijalankan Coolify (`Dockerfile`, `docker/`, `.github/workflows/`). Rahasia hanya ada di env Coolify, tidak pernah di image; file baru yang berisi data pribadi atau rahasia wajib masuk `.dockerignore`. PHP di-pin ke rilis patch keamanan (`ARG PHP_VERSION`); naikkan dengan sengaja saat ada rilis keamanan baru. Satu container menjalankan Apache, `queue:work`, `schedule:work`, dan `lais:telegram` lewat supervisor, jadi jangan menambah replika (satu token Telegram satu poller).
- Transaksi yang sudah tercatat tidak pernah diedit atau dihapus. Batalkan lewat `TransactionService::cancel()` dan ubah lewat `replace()`. Keduanya membuat jurnal cermin bertanggal transaksi asli dan membalik setiap mutasi stoknya (`Inventory::reverse()`).

## AI

- Model hanya dipanggil lewat `App\Services\Ai\LlmClient::toolCall()`, dengan `config('services.llm.driver')`: `claude` (SDK resmi, produksi) atau `gateway` (9Router, bawaan tes). Pemanggil memakai bentuk OpenAI; terjemahan ke Claude ada di `LlmClient`. Kunci API hanya ada di `.env` dan tidak pernah ditulis ke log atau ke `properties` log aktivitas.
    - Bagian prompt yang tetap masuk `$system` (di-cache) dan yang berubah masuk `$context`. Waktu, draf, dan katalog jangan ditaruh di `$system`, karena satu karakter berbeda membatalkan cache.
    - `phpunit.xml` mengunci `LLM_DRIVER=gateway` supaya tes biasa tidak memakai kredit. Eval berbayar: `LLM_DRIVER=claude LOG_CHANNEL=single php artisan test --group=llm`.
- AI tidak pernah menyimpan tanpa konfirmasi pemilik, dan tidak ada tool untuk menyimpan.
    - Baris yang tidak ada di katalog disimpan di `values.unknown` draf transaksi dan memblokir simpan. Jangan membuangnya diam-diam. Hanya pemilik yang bisa melepasnya (`FormAssistant::skipsMissing()`, tanpa model); model tidak diberi cara untuk itu.
    - Selama ada draf terbuka, model hanya menerima 2 giliran terakhir, karena riwayat lama menarik model kembali ke permintaan yang sudah lewat. Yang perlu diingat harus ada di draf atau di `context()`.
    - Perubahan prompt diuji dengan eval beberapa kali, bukan sekali, karena jawaban model berbeda antarputaran.
    - Pertanyaan usaha yang tidak tercakup `tanya_angka` dan `tanya_harga` memakai `tanya_data`: panggilan kedua membaca `BusinessFacts::sheet()`. Model tidak menghitung; angka baru ditambahkan ke lembar fakta, bukan ke prompt. Jawaban selalu melewati `BusinessFacts::grounded()`. Nomor HP, alamat, dan foto tidak masuk lembar.
    - Konfirmasi dideteksi deterministik lewat `FormAssistant::confirms()` ("iya", "oke gas", "simpan"), hanya untuk draf yang sudah ditunjukkan ke pemilik.
    - Draf yang dikirim balik dari browser diperlakukan seperti isian form: ditimpakan ke nilai awal form (`TransactionDraft::fill([], …)`), divalidasi ulang dengan `FormRequest`-nya, lalu disimpan lewat `TransactionService::record()` atau `Catalog::create()`.
- Argumen tool diperlakukan seperti input pengguna:
    - enum dibaca lewat `tryFrom()`, dan nilai yang tidak dikenal dibuang
    - nama item, bahan, kontak, dan rekening dicocokkan lewat `Names::match()`, hanya dengan data usaha itu sendiri
    - AI tidak menghitung angka; misalnya harga satuan dari total baris dihitung server
- Data yang kurang dinilai oleh `FormRequest` milik form itu (`rules()` dan `messages()`), bukan oleh AI. Lihat `FormAssistant::problems()`.
- Balasan ke pemilik disusun dari template. Teks bebas dari model hanya dipakai untuk pertanyaan `tanya_balik` (maksimal 200 huruf). Sapaan, pertanyaan laporan, dan permintaan di luar urusan usaha memakai `jawab_singkat` dengan kalimat tetap.
- Foto nota masuk sebagai data URI yang divalidasi `AssistantRequest::isPhoto()`: JPG, PNG, atau WebP sungguhan, maksimal 5 MB.
    - Foto hanya dibaca dan tidak disimpan.
    - Gambar hanya dikirim ke `FormAssistant::readReceipt()`, yang memakai prompt pendek dan satu tool `tulis_nota`. Gambar jangan pernah digabung dengan prompt katalog: model macet.
    - Salinan nota lalu masuk ke alur teks biasa.
- Saran pembukuan ("dicatat di mana?") dan letak fitur ("di mana daftar akun?") hanya berasal dari `App\Enums\GuideTopic` (`explanation()`, `needs()`, `draft()`, `link()`). Model hanya memilih topik lewat tool `jelaskan`. Topik baru ditambahkan di enum itu, bukan di prompt, dan setiap topik selain `belum_didukung` menyebut jalur menunya serta punya `link()` kalau ada halamannya.
- Rekening tujuan QRIS satu sumbernya: `businesses.qris_money_account_id` (`Business::qrisAccount()`), diatur di Pesanan → Pengaturan. Dipakai `FormAssistant::context()` (tanda "QRIS masuk ke sini") dan `OrderCommands::pay()`; nama rekening yang disebut pemilik selalu menang. Draf yang masih menunggu rekening (`TransactionDraft::PICK_ACCOUNT`) menawarkan nama rekening sebagai `options`, dan jawaban yang persis nama rekening diisi tanpa model.
- Pertanyaan dari halaman laporan membawa `report` (komponen Inertia + `periode`/`dari`/`sampai`, `FormAssistant::REPORTS`). Konteksnya masuk ke lembar fakta sebagai bagian "LAPORAN YANG DIBUKA" (`BusinessFacts::sheet($business, $open, $name)`), bukan ke prompt, supaya `grounded()` tetap menjaganya. Model membaca `FormAssistant::MEMORY` (10) giliran terakhir; saat ada draf tetap 2.
- Arti istilah hanya berasal dari `App\Enums\Term` (`definition()`, `answer()`, `link()`), lewat tool `artikan`. Enum ini juga satu-satunya sumber ⓘ di layar: `lais:enums` menulis `termDefinitions`, dan `components/app/term-hint.tsx` tidak boleh menyimpan salinan sendiri.
- "Produk ini layak?" dijawab `ReportAnswers::productVerdict()` dengan aturan tetap dari `App\Enums\MarginLevel` dan penjualan 30 hari terakhir, lewat tool `nilai_produk`. Model tidak menilai. Ambang margin (`MarginLevel::THIN`, `HEALTHY`) juga dipakai badge margin di layar lewat `lais:enums`.
- Test memakai `Http::fake()` dengan `tool_calls` tiruan dan `Http::preventStrayRequests()`. Eval dengan model sungguhan memakai `#[Group('llm')]`, yang tidak ikut test biasa.

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
- Jalur tanpa sesi login, misalnya bot, memanggil `Activity::defaultCauser($owner, $callback)` (facade `Spatie\Activitylog\Facades\Activity`) dan `Context::add('activity_source', …)` di titik masuknya, seperti `Inbox::handle()`.
- Password, token, kode OTP/2FA, dan secret lain tidak pernah masuk `properties` atau `logOnly`.

## Test

- Test fitur class-based dengan `RefreshDatabase`.
- Payload HTTP dan kode akun di test ditulis sebagai literal (`'tunai'`, `'1301'`), karena itulah kontraknya. Atribut yang sudah di-cast dibandingkan dengan enumnya (`ContactType::Supplier`), karena string tidak akan pernah sama dengan enum.
- Hitungan di frontend yang meniru aturan server diuji dengan angka yang sama dengan test PHP-nya.
