<?php

namespace App\Services\Ai;

use App\Enums\GuideTopic;
use App\Enums\ItemKind;
use App\Enums\MoneyAccountKind;
use App\Enums\PaymentMethod;
use App\Enums\TransactionKind;
use App\Enums\TransactionType;
use App\Http\Requests\ItemRequest;
use App\Http\Requests\TransactionRequest;
use App\Models\Business;
use App\Models\Contact;
use App\Models\Item;
use App\Models\MoneyAccount;
use App\Models\User;
use App\Services\Catalog;
use App\Services\TransactionService;
use App\Support\Format;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Routing\Redirector;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * "Tanya AI" (PRD M7, US-14a): the owner says what to record, the assistant
 * drafts it, and the owner confirms in the chat. The model only picks a tool
 * and its arguments; the server maps them onto the business's own items,
 * judges what is missing with the same rules as Simpan, and writes the
 * reply. Saving never comes from the model: only a confirming message
 * ("iya", "simpan") on a draft the owner was shown, through the same
 * FormRequest and service as the form.
 *
 * @phpstan-type Card array{title: string, lines: list<array{name: string, qty: float|null, unit: string|null, price: int|null, amount: int|null}>, total: array{label: string, amount: int}|null, notes: list<string>}
 * @phpstan-type Link array{label: string, href: string}
 * @phpstan-type Offer array{label: string, reply: string, form: string|null, values: array<string, mixed>|null, card: Card|null, missing: list<string>, confirm: bool, target: string|null}
 * @phpstan-type Guide array{topic: string, needs: list<string>, offer: Offer|null}
 * @phpstan-type Reply array{reply: string, form: string|null, values: array<string, mixed>|null, card: Card|null, missing: list<string>, options: list<string>, confirm: bool, target: string|null, saved: Link|null, guide: Guide|null, link: Link|null}
 */
class FormAssistant
{
    /** The forms the assistant drafts. */
    public const FORMS = ['transaksi', 'produk'];

    private const SCOPE = 'Maaf, saya hanya bisa membantu mencatat transaksi dan produk baru. Ceritakan saja, misalnya "jual 3 nasi goreng tunai".';

    /** jawab_singkat replies; the model picks one, it never writes them. */
    private const REPLIES = [
        'sapaan' => 'Halo! Saya bisa membantu mencatat transaksi dan produk baru. Ceritakan saja, misalnya "jual 3 nasi goreng tunai".',
        'terima_kasih' => 'Sama-sama. Kalau ada yang mau dicatat lagi, tulis saja di sini.',
        'di_luar_tugas' => self::SCOPE,
        // shortcut: report questions wait for US-14 (next sprint); until then the Dashboard has the numbers.
        'pertanyaan_laporan' => 'Pertanyaan soal laporan, misalnya untung atau produk terlaris, belum bisa saya jawab. Untuk sekarang, angkanya ada di Dashboard.',
    ];

    public function __construct(private LlmClient $llm, private TransactionService $transactions, private Catalog $catalog) {}

    /**
     * Whether the whole message only agrees ("iya", "oke gas", "simpan").
     * "iya tapi 4 kg" is not a confirmation: it goes to the model.
     */
    public static function confirms(string $message): bool
    {
        return preg_match('/^(?:(?:iy+a+|y+a+|y|yes|ok|oke+|okey|okay|gas+|simpan|lanjut|lanjutkan|betul|benar|bener|sip+|siap|boleh|setuju|mantap|yup)[\s,.!]*)+$/u', mb_strtolower(trim($message))) === 1;
    }

    /**
     * @param  list<array{role: string, text: string}>  $history  earlier turns, oldest first
     * @param  array<string, mixed>  $current  the open form's values
     * @param  array{form: string, values: array<string, mixed>}|null  $draft  the draft the owner was last shown
     * @return Reply
     */
    public function reply(User $user, string $message, ?string $form, array $history, array $current, ?array $draft = null): array
    {
        $business = $user->currentBusiness();
        $items = $business->items()->orderBy('items.name')->limit(100)->get();
        $contacts = $business->contacts()->orderBy('contacts.name')->limit(100)->get();
        $moneyAccounts = $business->moneyAccounts()->orderBy('money_accounts.id')->get();

        if ($draft !== null && self::confirms($message)) {
            return $this->save($user, $draft, $items, $contacts, $moneyAccounts);
        }

        // A follow-up builds on the draft; without one, on what the open form holds.
        $base = fn (string $tool) => $draft !== null && $draft['form'] === $tool ? $draft['values'] : ($form === $tool ? $current : []);

        $call = $this->llm->toolCall(
            $this->instructions($business, $form, $draft['values'] ?? $current, $items, $contacts, $moneyAccounts),
            [
                ...array_map(fn (array $turn) => ['role' => $turn['role'], 'content' => $turn['text']], array_slice($history, -6)),
                ['role' => 'user', 'content' => $message],
            ],
            self::tools(),
        );
        $args = $call['arguments'];

        return match ($call['name']) {
            'isi_transaksi' => $this->transaction($user, TransactionDraft::fill($args, $base('transaksi'), $items, $contacts, $moneyAccounts), $items, $contacts, $moneyAccounts),
            'isi_produk' => $this->product($user, ItemDraft::fill($args, $base('produk'), $items), $items),
            'tanya_balik' => self::result([
                'reply' => mb_substr(self::text($args['pertanyaan'] ?? null), 0, 200) ?: 'Bisa diperjelas sedikit?',
                'options' => self::options($args['pilihan'] ?? null),
            ]),
            'jelaskan' => $this->guide($user, $args, $items, $contacts, $moneyAccounts),
            default => self::text($args['jenis'] ?? null) === 'pertanyaan_laporan'
                ? self::result(['reply' => self::REPLIES['pertanyaan_laporan'], 'link' => ['label' => 'Buka Dashboard', 'href' => route('dashboard', absolute: false)]])
                : self::result(['reply' => self::REPLIES[self::text($args['jenis'] ?? null)] ?? self::SCOPE]),
        };
    }

    /**
     * "Dicatat di mana?": the explanation is GuideTopic's, never the model's.
     * Topics that lead to a record come with a draft ready to open; nothing
     * becomes the live draft until the owner taps it.
     *
     * @param  array<string, mixed>  $args
     * @param  Collection<int, Item>  $items
     * @param  Collection<int, Contact>  $contacts
     * @param  Collection<int, MoneyAccount>  $moneyAccounts
     * @return Reply
     */
    private function guide(User $user, array $args, Collection $items, Collection $contacts, Collection $moneyAccounts): array
    {
        $topic = GuideTopic::tryFrom(self::text($args['topik'] ?? null)) ?? GuideTopic::BelumDidukung;
        $draft = $topic->draft();
        $offer = null;

        if ($draft !== null) {
            $amount = is_numeric($args['nominal'] ?? null) && (int) $args['nominal'] > 0 ? (int) $args['nominal'] : null;
            $prepared = $this->transaction($user, TransactionDraft::fill([
                ...$draft,
                'nominal' => $amount,
                'kontak' => $args['kontak'] ?? null,
                // Equipment is named in the note; other topics have no use for it.
                'catatan' => $topic === GuideTopic::BeliPeralatan ? ($args['barang'] ?? null) : null,
            ], [], $items, $contacts, $moneyAccounts), $items, $contacts, $moneyAccounts);

            $offer = [
                'label' => $topic->actionLabel().($amount === null ? '' : ' '.Format::rupiah($amount)),
                'reply' => $prepared['reply'],
                'form' => $prepared['form'],
                'values' => $prepared['values'],
                'card' => $prepared['card'],
                'missing' => $prepared['missing'],
                'confirm' => $prepared['confirm'],
                'target' => $prepared['target'],
            ];
        }

        $link = $topic->link($user->currentBusiness());

        return self::result([
            'reply' => $topic->explanation(),
            'guide' => ['topic' => $topic->value, 'needs' => $topic->needs(), 'offer' => $offer],
            ...($link === null ? [] : ['link' => $link]),
        ]);
    }

    /**
     * @param  array{values: array<string, mixed>, notes: list<string>}  $draft
     * @param  Collection<int, Item>  $items
     * @param  Collection<int, Contact>  $contacts
     * @param  Collection<int, MoneyAccount>  $moneyAccounts
     * @return Reply
     */
    private function transaction(User $user, array $draft, Collection $items, Collection $contacts, Collection $moneyAccounts, string $refused = ''): array
    {
        $values = $draft['values'];
        $missing = array_values(array_unique([...$draft['notes'], ...self::validate(TransactionRequest::class, TransactionDraft::payload($values, $contacts), $user)['errors']]));

        return self::result([
            'reply' => $refused ?: ($missing === [] ? 'Simpan catatan ini?' : 'Masih ada yang kurang. Balas di sini atau lengkapi di form.'),
            'form' => 'transaksi',
            'values' => $values,
            'card' => TransactionDraft::card($values, $items, $contacts, $moneyAccounts),
            'missing' => $missing,
            'confirm' => $missing === [],
            'target' => route('transactions.create', ['jenis' => $values['type']], absolute: false),
        ]);
    }

    /**
     * @param  array{values: array<string, mixed>, notes: list<string>}  $draft
     * @param  Collection<int, Item>  $items
     * @return Reply
     */
    private function product(User $user, array $draft, Collection $items, string $refused = ''): array
    {
        $values = $draft['values'];
        $missing = array_values(array_unique([...$draft['notes'], ...self::validate(ItemRequest::class, ItemDraft::payload($values), $user)['errors']]));

        return self::result([
            'reply' => $refused ?: ($missing === [] ? 'Simpan produk ini?' : 'Masih ada yang kurang. Balas di sini atau lengkapi di form.'),
            'form' => 'produk',
            'values' => $values,
            'card' => ItemDraft::card($values, $items),
            'missing' => $missing,
            'confirm' => $missing === [],
            'target' => route('items.create', ['jenis' => $values['kind']], absolute: false),
        ]);
    }

    /**
     * The owner confirmed the draft they were shown. It comes back from the
     * browser, so it is laid over the form defaults and judged again by the
     * form's own request before the same service as Simpan records it.
     *
     * @param  array{form: string, values: array<string, mixed>}  $draft
     * @param  Collection<int, Item>  $items
     * @param  Collection<int, Contact>  $contacts
     * @param  Collection<int, MoneyAccount>  $moneyAccounts
     * @return Reply
     */
    private function save(User $user, array $draft, Collection $items, Collection $contacts, Collection $moneyAccounts): array
    {
        $business = $user->currentBusiness();
        $refused = 'Belum bisa disimpan. Lengkapi dulu yang kurang.';

        if ($draft['form'] === 'produk') {
            $filled = ItemDraft::fill([], $draft['values'], $items);
            $checked = self::validate(ItemRequest::class, ItemDraft::payload($filled['values']), $user);

            if ($checked['errors'] !== [] || $filled['notes'] !== []) {
                return $this->product($user, $filled, $items, $refused);
            }

            try {
                $item = $this->catalog->create($business, $checked['data']);
            } catch (ValidationException) {
                return $this->product($user, $filled, $items, $refused);
            }

            return self::result(['reply' => "{$item->name} tersimpan.", 'saved' => ['label' => 'Lihat produk', 'href' => route('items.edit', $item, absolute: false)]]);
        }

        $filled = TransactionDraft::fill([], $draft['values'], $items, $contacts, $moneyAccounts);
        $checked = self::validate(TransactionRequest::class, TransactionDraft::payload($filled['values'], $contacts), $user);

        if ($checked['errors'] !== [] || $filled['notes'] !== []) {
            return $this->transaction($user, $filled, $items, $contacts, $moneyAccounts, $refused);
        }

        try {
            $transaction = $this->transactions->record($business, $checked['data']);
        } catch (ValidationException $exception) {
            // Rules that need the books (stock, balances) are only known when recording.
            $filled['notes'] = array_values(Arr::flatten($exception->errors()));

            return $this->transaction($user, $filled, $items, $contacts, $moneyAccounts, $refused);
        }

        return self::result([
            'reply' => $transaction->type->savedMessage(),
            'saved' => ['label' => 'Lihat transaksi', 'href' => route('transactions.show', $transaction, absolute: false)],
        ]);
    }

    /**
     * The form's own rules and messages, run without saving anything.
     *
     * @param  class-string<FormRequest>  $requestClass
     * @param  array<string, mixed>  $payload
     * @return array{data: array<string, mixed>, errors: list<string>}
     */
    private static function validate(string $requestClass, array $payload, User $user): array
    {
        $request = $requestClass::create('/', 'POST', $payload);
        $request->setUserResolver(fn () => $user)->setContainer(app())->setRedirector(app(Redirector::class));

        try {
            $request->validateResolved();

            return ['data' => $request->validated(), 'errors' => []];
        } catch (ValidationException $exception) {
            return ['data' => [], 'errors' => array_values(array_unique(Arr::flatten($exception->errors())))];
        }
    }

    /**
     * @param  array{reply?: string, form?: string, values?: array<string, mixed>, card?: Card, missing?: list<string>, options?: list<string>, confirm?: bool, target?: string, saved?: Link, guide?: Guide, link?: Link}  $fields
     * @return Reply
     */
    private static function result(array $fields): array
    {
        return [...['reply' => '', 'form' => null, 'values' => null, 'card' => null, 'missing' => [], 'options' => [], 'confirm' => false, 'target' => null, 'saved' => null, 'guide' => null, 'link' => null], ...$fields];
    }

    /**
     * @return list<string>
     */
    private static function options(mixed $choices): array
    {
        return is_array($choices)
            ? array_values(array_slice(array_filter(array_map(fn (mixed $choice) => mb_substr(self::text($choice), 0, 80), $choices)), 0, 5))
            : [];
    }

    /**
     * @param  array<string, mixed>  $current
     * @param  Collection<int, Item>  $items
     * @param  Collection<int, Contact>  $contacts
     * @param  Collection<int, MoneyAccount>  $moneyAccounts
     */
    private function instructions(Business $business, ?string $form, array $current, Collection $items, Collection $contacts, Collection $moneyAccounts): string
    {
        $page = match ($form) {
            'transaksi' => 'form Catat transaksi',
            'produk' => 'form Produk baru',
            default => 'bukan form; pilih isi_transaksi atau isi_produk sesuai permintaan',
        };
        $catalog = $items->map(fn (Item $item) => "- {$item->name}"
            .($item->aliases ? ' (alias: '.implode(', ', $item->aliases).')' : '')
            ." | {$item->unit} | ".mb_strtolower($item->kind->label())
            .($item->price === null ? '' : " | harga jual {$item->price}"))->implode("\n") ?: '(belum ada)';
        $people = $contacts->map(fn (Contact $contact) => "- {$contact->name} ({$contact->type->label()})")->implode("\n") ?: '(belum ada)';
        $accounts = $moneyAccounts->map(fn (MoneyAccount $account) => "- {$account->name} (".($account->kind === MoneyAccountKind::Tunai ? 'tunai' : 'non_tunai').')')->implode("\n");
        $now = Format::date(now()).', pukul '.now()->format('H.i').' WIB';
        $filled = $current === [] ? '(kosong)' : (string) json_encode($current, JSON_UNESCAPED_UNICODE);
        $topics = implode("\n", array_map(fn (GuideTopic $topic) => "- {$topic->value}: {$topic->label()}", GuideTopic::cases()));

        return <<<PROMPT
        Kamu asisten pencatatan di aplikasi Lais untuk usaha "{$business->name}". Tugasmu hanya mengubah kalimat pemilik menjadi catatan transaksi atau produk baru dengan memanggil tepat satu tool. Kamu tidak menyimpan apa pun: sistem menunjukkan ringkasannya, lalu pemilik yang mengonfirmasi.

        Aturan:
        - Pakai nama produk, bahan, kontak, dan rekening persis seperti di daftar.
        - Jangan mengarang harga atau nominal; kosongkan kalau pemilik tidak menyebutnya. Jangan menjumlahkan, membagi, atau menghitung apa pun: harga yang disebut untuk keseluruhan baris masuk ke total, bukan harga.
        - Baca angka: "15rb" = 15000, "1,5jt" = 1500000, "setengah" = 0,5.
        - Ubah satuan hanya kalau pasti dan sejenis dengan satuan di daftar: gram, ons (0,1 kg), dan kg; ml dan liter; lusin (12) dan kodi (20) hanya untuk barang yang dihitung per buah. Kalau tidak sejenis, misalnya kodi atau karung untuk barang yang satuannya kg, panggil tanya_balik dan tanyakan jumlahnya dalam satuan di daftar.
        - Nama yang tidak ada di daftar tetap ditulis apa adanya; sistem yang memberi tahu pemilik. Jangan menawarkan untuk mendaftarkannya.
        - Panggil tanya_balik hanya kalau maksud pemilik ragu, misalnya satu nama cocok dengan beberapa produk di daftar, atau satuannya tidak sejenis. Pertanyaannya hanya soal isian catatan. Beri pilihan singkat.
        - Kalau pemilik bertanya cara atau tempat mencatat sesuatu ("dicatat di mana", "masuk apa", "gimana nyatatnya", "bingung"), panggil jelaskan dengan topik yang paling cocok, dan isi nominal, kontak, atau barang kalau disebut. Kalau pemilik menyuruh mencatat, tetap panggil isi_transaksi.
        - Pertanyaan soal pencatatan usaha yang tidak cocok dengan topik mana pun memakai jelaskan dengan topik belum_didukung. Pertanyaan soal laporan (untung, omzet, produk terlaris) memakai jawab_singkat dengan jenis pertanyaan_laporan.
        - jawab_singkat dengan jenis di_luar_tugas hanya untuk yang bukan soal usaha, misalnya minta dibuatkan kode, resep masakan, tulisan, atau pertanyaan umum. Jangan menjawab isinya.

        Topik untuk jelaskan:
        {$topics}

        Cara bayar: tunai = uang tunai; non_tunai = QRIS, transfer, atau e-wallet (isi rekening kalau disebut); kredit = belum dibayar, ngutang, bon; pribadi = dibayar dari uang pribadi pemilik (hanya pembelian dan biaya).
        Jenis transaksi: penjualan; pembelian (jenis_biaya barang untuk bahan atau barang dagangan, peralatan untuk alat); biaya (jenis_biaya listrik_air, gas_bbm, sewa, upah, transport, pemasaran, lain); modal (setor = menambah modal, prive = mengambil uang usaha untuk pribadi); utang_piutang (terima_piutang = pelanggan melunasi utangnya, bayar_utang = membayar utang ke supplier).
        Jenis produk: produk = dijual dan punya stok sendiri; bahan = bahan baku yang tidak dijual; komposisi = dijual dan dibuat dari resep bahan.

        Sekarang: {$now}.
        Halaman yang dibuka: {$page}.
        Draf saat ini (lengkapi; jangan mengulang dari awal kecuali diminta): {$filled}

        Produk dan bahan (nama | satuan | jenis | harga jual):
        {$catalog}

        Kontak:
        {$people}

        Rekening:
        {$accounts}
        PROMPT;
    }

    /**
     * The tools the model chooses from, in the vocabulary of PRD §3.1.
     *
     * @return list<array<string, mixed>>
     */
    private static function tools(): array
    {
        $function = fn (string $name, string $description, array $properties, array $required) => [
            'type' => 'function',
            'function' => ['name' => $name, 'description' => $description, 'parameters' => ['type' => 'object', 'properties' => $properties, 'required' => $required]],
        ];
        $kinds = array_values(array_map(
            fn (TransactionKind $kind) => $kind->value,
            array_filter(TransactionKind::cases(), fn (TransactionKind $kind) => in_array($kind->type(), TransactionDraft::TYPES, true)),
        ));

        return [
            $function('isi_transaksi', 'Isi form catat transaksi dari kalimat pemilik.', [
                'jenis' => ['type' => 'string', 'enum' => array_map(fn (TransactionType $type) => $type->value, TransactionDraft::TYPES)],
                'jenis_biaya' => ['type' => 'string', 'enum' => $kinds, 'description' => 'Sub-jenis untuk pembelian, biaya, modal, dan utang_piutang.'],
                'metode_bayar' => ['type' => 'string', 'enum' => PaymentMethod::values()],
                'rekening' => ['type' => 'string', 'description' => 'Nama rekening non-tunai, kalau disebut.'],
                'tanggal' => ['type' => 'string', 'description' => 'YYYY-MM-DD HH:MM, hanya kalau pemilik menyebut waktu lain (misalnya kemarin).'],
                'kontak' => ['type' => 'string', 'description' => 'Nama pelanggan atau supplier.'],
                'baris' => ['type' => 'array', 'items' => ['type' => 'object', 'properties' => [
                    'item' => ['type' => 'string'],
                    'qty' => ['type' => 'number'],
                    'harga' => ['type' => 'integer', 'description' => 'Harga per satuan, hanya kalau disebut per satuan.'],
                    'total' => ['type' => 'integer', 'description' => 'Harga keseluruhan baris itu kalau yang disebut totalnya, misalnya "beras 5 kg 70rb" = 70000.'],
                ], 'required' => ['item', 'qty']]],
                'nominal' => ['type' => 'integer', 'description' => 'Jumlah uang untuk biaya, modal, utang-piutang, atau pembelian peralatan.'],
                'dibayar' => ['type' => 'integer', 'description' => 'Uang yang sudah dibayar pada penjualan yang belum lunas.'],
                'catatan' => ['type' => 'string', 'description' => 'Keterangan singkat; untuk pembelian peralatan, nama peralatannya.'],
            ], ['jenis']),
            $function('isi_produk', 'Isi form produk baru dari kalimat pemilik.', [
                'jenis' => ['type' => 'string', 'enum' => ItemKind::values()],
                'nama' => ['type' => 'string'],
                'alias' => ['type' => 'array', 'items' => ['type' => 'string'], 'description' => 'Nama lain yang biasa dipakai pemilik.'],
                'satuan' => ['type' => 'string', 'description' => 'Misalnya porsi, pcs, kg, botol.'],
                'harga' => ['type' => 'integer', 'description' => 'Harga jual, hanya kalau disebut.'],
                'stok_awal' => ['type' => 'number'],
                'biaya_per_unit' => ['type' => 'integer', 'description' => 'Modal per satuan untuk stok awal.'],
                'stok_minimum' => ['type' => 'number'],
                'resep' => ['type' => 'array', 'items' => ['type' => 'object', 'properties' => [
                    'bahan' => ['type' => 'string'],
                    'qty' => ['type' => 'number', 'description' => 'Dalam satuan bahan itu.'],
                ], 'required' => ['bahan', 'qty']]],
            ], ['jenis', 'nama']),
            $function('tanya_balik', 'Tanyakan isian yang kurang jelas sebelum mencatat.', [
                'pertanyaan' => ['type' => 'string'],
                'pilihan' => ['type' => 'array', 'items' => ['type' => 'string'], 'description' => 'Dua sampai empat jawaban singkat.'],
            ], ['pertanyaan']),
            $function('jelaskan', 'Jelaskan cara atau tempat mencatat sesuatu, untuk pemilik yang bertanya atau bingung.', [
                'topik' => ['type' => 'string', 'enum' => GuideTopic::values()],
                'nominal' => ['type' => 'integer', 'description' => 'Jumlah uang kalau disebut.'],
                'kontak' => ['type' => 'string', 'description' => 'Nama pelanggan atau supplier kalau disebut.'],
                'barang' => ['type' => 'string', 'description' => 'Nama barang atau peralatan kalau disebut.'],
            ], ['topik']),
            $function('jawab_singkat', 'Balasan tetap untuk sapaan, ucapan terima kasih, pertanyaan laporan, atau permintaan di luar urusan usaha.', [
                'jenis' => ['type' => 'string', 'enum' => array_keys(self::REPLIES)],
            ], ['jenis']),
        ];
    }

    private static function text(mixed $value): string
    {
        return is_string($value) ? trim($value) : '';
    }
}
