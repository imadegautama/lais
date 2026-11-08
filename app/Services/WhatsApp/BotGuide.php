<?php

namespace App\Services\WhatsApp;

use App\Enums\ItemKind;
use App\Models\Item;
use App\Models\User;

/**
 * What the bot says when the owner asks how to use it: a welcome on /start
 * and one guide per job, each with buttons to the next. Fixed words, no
 * model, so it answers at once and the same way every time.
 *
 * @phpstan-import-type Button from Buttons
 *
 * @phpstan-type Message array{text: string, buttons: list<Button>}
 */
class BotGuide
{
    /** The guides, by the command that opens them (also Telegram's Menu). */
    public const COMMANDS = [
        'start' => 'Mulai dan panduan',
        'catat' => 'Cara mencatat',
        'tanya' => 'Cara bertanya',
        'pesanan' => 'Pesanan toko online',
        'ringkasan' => 'Ringkasan hari ini',
        'batal' => 'Batalkan catatan yang belum disimpan',
    ];

    /**
     * @return Message
     */
    public static function welcome(User $owner): array
    {
        $first = strtok(trim($owner->name), ' ') ?: $owner->name;

        return [
            'text' => "Halo, *{$first}*! Saya asisten pembukuan *{$owner->currentBusiness()->name}*.\n\n"
                ."Yang bisa saya bantu:\n"
                ."• mencatat penjualan, belanja, dan biaya dari chat atau foto nota\n"
                ."• menjawab angka usaha: untung, kas, utang-piutang\n"
                ."• mengabari pesanan dari toko online\n\n"
                .'Pilih panduan di bawah, atau langsung ceritakan yang mau dicatat.',
            'buttons' => [
                Buttons::command('Cara mencatat', '/catat'),
                Buttons::command('Cara bertanya', '/tanya'),
                Buttons::command('Pesanan toko', '/pesanan'),
                Buttons::command('Ringkasan', '/ringkasan'),
                Buttons::link(['label' => 'Buka Lais', 'href' => route('dashboard', absolute: false)]),
            ],
        ];
    }

    /**
     * @return Message
     */
    public static function record(User $owner): array
    {
        $product = self::firstProduct($owner);

        return [
            'text' => "*Cara mencatat*\nCeritakan saja seperti ngobrol biasa:\n"
                ."• Jual: _jual 3 nasi goreng tunai_, _2 es teh qris_\n"
                ."• Belanja: _beli beras 5 kg 70rb_\n"
                ."• Biaya: _bayar listrik 350rb_\n"
                ."• Ambil uang usaha: _ambil 200rb buat rumah_\n"
                ."• Atau kirim foto nota belanja\n\n"
                .'Saya siapkan catatannya dulu, lalu kamu tekan *Simpan*. Kalau ada yang salah, ketik saja koreksinya, misalnya _eh 5 bukan 3_.',
            'buttons' => [
                ...($product === null ? [] : [Buttons::command("Coba: jual 1 {$product->name} tunai", "jual 1 {$product->name} tunai")]),
                Buttons::command('Cara bertanya', '/tanya'),
            ],
        ];
    }

    /**
     * @return Message
     */
    public static function ask(User $owner): array
    {
        $product = self::firstProduct($owner);

        return [
            'text' => "*Cara bertanya*\nTanya apa saja soal usahamu:\n"
                ."• Angka: _untung bulan ini berapa?_, _kas ada berapa?_, _siapa yang belum bayar?_\n"
                ."• Tempat mencatat: _utang ke supplier dicatat di mana?_\n"
                ."• Istilah: _apa itu HPP?_\n"
                .'• Produk: _'.mb_strtolower($product->name ?? 'es teh')." worth it?_\n\n"
                .'Angkanya selalu dari pembukuanmu, bukan tebakan.',
            'buttons' => [
                Buttons::command('Untung bulan ini?', 'untung bulan ini berapa?'),
                Buttons::command('Cara mencatat', '/catat'),
            ],
        ];
    }

    /**
     * @return Message
     */
    public static function orders(User $owner): array
    {
        $business = $owner->currentBusiness();

        return [
            'text' => "*Pesanan toko online*\nPesanan dari link tokomu masuk ke chat ini, lengkap dengan tombol *Terima pesanan* dan *Tolak*. Setelah itu tandai siap, lalu selesai.\n\n"
                ."Bisa juga diketik:\n"
                ."• _A012 dp 20rb qris_: catat uang muka\n"
                ."• _A012 lunas_: catat pelunasan\n"
                .'• _A012 selesai_: pesanan diambil, masuk penjualan',
            'buttons' => [
                Buttons::link(['label' => 'Buka toko saya', 'href' => route('shop.show', $business->slug, absolute: false)]),
                Buttons::link(['label' => 'Buka Pesanan', 'href' => route('orders.index', absolute: false)]),
            ],
        ];
    }

    /**
     * For a chat not linked to any account yet.
     *
     * @return Message
     */
    public static function stranger(string $channel): array
    {
        return [
            'text' => "Halo! Saya bot pembukuan *Lais*.\n\n"
                ."Supaya bisa mencatat dari sini, hubungkan dulu akun Lais kamu:\n"
                ."1. Daftar atau masuk di Lais\n"
                ."2. Buka *Pengaturan → Bot*, lalu *Buat kode*\n"
                ."3. Tekan *Buka {$channel}*, lalu kirim kodenya ke sini\n\n"
                .'Setelah terhubung, saya kirim panduan lengkapnya.',
            'buttons' => [
                Buttons::link(['label' => 'Daftar', 'href' => route('register', absolute: false)]),
                Buttons::link(['label' => 'Masuk', 'href' => route('login', absolute: false)]),
            ],
        ];
    }

    /** A product the owner really sells, so the examples work when tried. */
    private static function firstProduct(User $owner): ?Item
    {
        return $owner->currentBusiness()->items()
            ->whereIn('items.kind', ItemKind::sold())
            ->orderBy('items.name')
            ->first();
    }
}
