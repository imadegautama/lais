<?php

namespace App\Enums;

use App\Concerns\EnumValues;
use App\Models\Business;

/**
 * "Dicatat di mana?" answers for Tanya AI (PRD M7, US-14b). The model only
 * picks the topic; what the owner reads is written here, so bookkeeping
 * advice never comes from the model.
 */
enum GuideTopic: string
{
    use EnumValues;

    case UangPribadiMasuk = 'uang_pribadi_masuk';
    case BayarPakaiUangPribadi = 'bayar_pakai_uang_pribadi';
    case AmbilUangUsaha = 'ambil_uang_usaha';
    case BeliPeralatan = 'beli_peralatan';
    case BiayaOperasional = 'biaya_operasional';
    case PindahUang = 'pindah_uang';
    case JualBelumDibayar = 'jual_belum_dibayar';
    case TerimaBayaranPiutang = 'terima_bayaran_piutang';
    case BayarUtangSupplier = 'bayar_utang_supplier';
    case StokTidakSesuai = 'stok_tidak_sesuai';
    case BaruMulai = 'baru_mulai';
    case BelumDidukung = 'belum_didukung';

    /** What the question is about, for the model to choose by. */
    public function label(): string
    {
        return match ($this) {
            self::UangPribadiMasuk => 'uang pribadi pemilik dimasukkan ke usaha, atau pemilik bingung soal uang pribadinya',
            self::BayarPakaiUangPribadi => 'belanja atau biaya usaha dibayar pakai uang pribadi',
            self::AmbilUangUsaha => 'uang usaha diambil untuk keperluan pribadi atau rumah',
            self::BeliPeralatan => 'membeli alat yang dipakai lama (kompor, etalase, blender), atau bingung peralatan atau biaya',
            self::BiayaOperasional => 'pengeluaran rutin seperti listrik, gas, sewa, gaji, transport, iklan',
            self::PindahUang => 'memindahkan uang antara kas tunai dan rekening usaha',
            self::JualBelumDibayar => 'penjualan yang belum dibayar pelanggan (ngutang, bon)',
            self::TerimaBayaranPiutang => 'pelanggan membayar utangnya',
            self::BayarUtangSupplier => 'belanja yang belum dibayar atau membayar utang ke supplier',
            self::StokTidakSesuai => 'stok rusak, hilang, kedaluwarsa, atau beda dengan hitungan',
            self::BaruMulai => 'baru mulai memakai Lais, uang atau barang yang sudah ada sebelumnya',
            self::BelumDidukung => 'soal pencatatan usaha lain yang tidak ada di daftar ini, misalnya pinjaman bank',
        };
    }

    public function explanation(): string
    {
        return match ($this) {
            self::UangPribadiMasuk => 'Uang pribadi yang dimasukkan ke kas atau rekening usaha dicatat sebagai Setor modal. Kalau uangnya tetap untuk keperluan pribadi, tidak perlu dicatat, karena Lais hanya mencatat uang usaha.',
            self::BayarPakaiUangPribadi => 'Catat belanja atau biayanya seperti biasa, lalu pilih cara bayar Uang pribadi. Kas usaha tidak berkurang, dan sistem menghitungnya sebagai tambahan modal. Ceritakan saja belanjanya, misalnya "beli gas 25rb pakai uang pribadi".',
            self::AmbilUangUsaha => 'Uang usaha yang dipakai untuk keperluan pribadi dicatat sebagai Ambil untuk pribadi, supaya laba tetap terlihat apa adanya. Usahakan tidak melebihi laba bulan itu.',
            self::BeliPeralatan => 'Barang yang dipakai lama dan tidak dijual, seperti kompor, etalase, atau blender, dicatat sebagai Belanja peralatan, bukan biaya. Bahan yang habis dipakai masuk Belanja bahan atau barang dagang.',
            self::BiayaOperasional => 'Pengeluaran rutin yang habis dipakai, seperti listrik, gas, sewa, upah, transport, dan iklan, dicatat sebagai Biaya. Pilih jenis biayanya supaya laporannya rapi.',
            self::PindahUang => 'Memindahkan uang antara kas dan rekening usaha, misalnya setor tunai ke bank atau tarik tunai, dicatat sebagai Pindah dana. Labanya tidak berubah.',
            self::JualBelumDibayar => 'Penjualan yang belum dibayar dicatat sebagai Penjualan dengan cara bayar Belum lunas, lengkap dengan nama pelanggannya. Saat pelanggan membayar, catat sebagai Terima bayaran dari pelanggan. Ceritakan saja penjualannya, misalnya "bu sari ngutang 2 nasi goreng".',
            self::TerimaBayaranPiutang => 'Uang dari pelanggan yang melunasi utangnya dicatat di Utang-piutang sebagai Terima bayaran dari pelanggan. Ini bukan penjualan baru, jadi labanya tidak terhitung dua kali.',
            self::BayarUtangSupplier => 'Belanja yang belum dibayar dicatat sebagai Belanja dengan cara bayar Belum lunas. Saat dilunasi, catat di Utang-piutang sebagai Bayar utang ke supplier.',
            self::StokTidakSesuai => 'Stok yang rusak, hilang, atau beda dengan hitungan dikoreksi di Produk & stok lewat Koreksi stok, bukan lewat transaksi. Selisihnya dinilai dengan biaya rata-rata.',
            self::BaruMulai => 'Uang, peralatan, utang, dan piutang yang sudah ada saat mulai memakai Lais dicatat sekali sebagai Saldo awal. Setelah itu, catat transaksi harian seperti biasa.',
            self::BelumDidukung => 'Itu belum bisa dicatat di Lais, misalnya pinjaman bank. Untuk sementara, simpan catatannya terpisah dulu.',
        };
    }

    /**
     * @return list<string>
     */
    public function needs(): array
    {
        return match ($this) {
            self::UangPribadiMasuk => ['Nominal', 'Masuk ke kas tunai atau rekening mana'],
            self::BayarPakaiUangPribadi => ['Apa yang dibeli atau dibayar', 'Nominal'],
            self::AmbilUangUsaha => ['Nominal', 'Diambil dari kas tunai atau rekening mana'],
            self::BeliPeralatan => ['Nama peralatan', 'Harga', 'Cara bayar'],
            self::BiayaOperasional => ['Jenis biaya', 'Nominal', 'Cara bayar'],
            self::PindahUang => ['Dari kas atau rekening mana', 'Ke kas atau rekening mana', 'Nominal'],
            self::JualBelumDibayar => ['Barang dan jumlahnya', 'Nama pelanggan'],
            self::TerimaBayaranPiutang => ['Nama pelanggan', 'Nominal yang dibayar'],
            self::BayarUtangSupplier => ['Nama supplier', 'Nominal yang dibayar'],
            self::StokTidakSesuai => ['Produk atau bahannya', 'Jumlah stok yang sebenarnya'],
            self::BaruMulai => ['Saldo kas dan rekening', 'Peralatan yang sudah ada', 'Utang dan piutang yang belum lunas'],
            self::BelumDidukung => [],
        };
    }

    /**
     * The record the topic leads to, as the first isi_transaksi arguments.
     *
     * @return array{jenis: string, jenis_biaya?: string}|null
     */
    public function draft(): ?array
    {
        $kind = fn (TransactionKind $kind) => ['jenis' => $kind->type()->value, 'jenis_biaya' => $kind->value];

        return match ($this) {
            self::UangPribadiMasuk => $kind(TransactionKind::Setor),
            self::AmbilUangUsaha => $kind(TransactionKind::Prive),
            self::BeliPeralatan => $kind(TransactionKind::Peralatan),
            // The kind of expense is the owner's to pick; until then it is listed as missing.
            self::BiayaOperasional => ['jenis' => TransactionType::Biaya->value],
            self::TerimaBayaranPiutang => $kind(TransactionKind::TerimaPiutang),
            self::BayarUtangSupplier => $kind(TransactionKind::BayarUtang),
            default => null,
        };
    }

    /** The button that opens the draft. */
    public function actionLabel(): string
    {
        return match ($this) {
            self::UangPribadiMasuk => 'Catat setor modal',
            self::AmbilUangUsaha => 'Catat ambil untuk pribadi',
            self::BeliPeralatan => 'Catat belanja peralatan',
            self::BiayaOperasional => 'Catat biaya',
            self::TerimaBayaranPiutang => 'Catat terima bayaran',
            self::BayarUtangSupplier => 'Catat bayar utang',
            default => '',
        };
    }

    /**
     * The page to go to when the assistant does not draft it.
     *
     * @return array{label: string, href: string}|null
     */
    public function link(Business $business): ?array
    {
        return match ($this) {
            self::PindahUang => ['label' => 'Buka form Pindah dana', 'href' => route('transactions.create', ['jenis' => TransactionType::PindahDana->value], absolute: false)],
            self::StokTidakSesuai => ['label' => 'Buka Produk & stok', 'href' => route('items.index', absolute: false)],
            self::BaruMulai => $business->onboarded_at === null
                ? ['label' => 'Mulai catat saldo awal', 'href' => route('onboarding.show', absolute: false)]
                : ['label' => 'Catat saldo awal', 'href' => route('transactions.create', ['jenis' => TransactionType::SaldoAwal->value], absolute: false)],
            default => null,
        };
    }
}
