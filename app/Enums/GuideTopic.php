<?php

namespace App\Enums;

use App\Concerns\EnumValues;
use App\Models\Business;

/**
 * "Dicatat di mana?" and "di mana fitur X?" answers for Tanya AI and the bot
 * (PRD M7, US-14b). The model only picks the topic; what the owner reads is
 * written here, so bookkeeping advice never comes from the model.
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
    case BeliStok = 'beli_stok';
    case TambahProduk = 'tambah_produk';
    case DaftarAkun = 'daftar_akun';
    case PesananToko = 'pesanan_toko';
    case SalahCatat = 'salah_catat';
    case RekeningBaru = 'rekening_baru';
    case HubungkanBot = 'hubungkan_bot';
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
            self::BayarUtangSupplier => 'belanja yang belum dibayar, punya utang ke supplier, melihat daftar utang, atau membayar utang ke supplier',
            self::StokTidakSesuai => 'stok rusak, hilang, kedaluwarsa, atau beda dengan hitungan',
            self::BaruMulai => 'baru mulai memakai Lais: uang, barang, utang, atau piutang yang sudah ada sebelumnya',
            self::BeliStok => 'belanja bahan baku atau barang dagangan, supaya stok bertambah',
            self::TambahProduk => 'menambah produk atau bahan baru, produk dengan resep, atau mengubah harga jual',
            self::DaftarAkun => 'daftar akun atau COA: di mana melihatnya, menambah akun sendiri, atau akun mana yang dipakai',
            self::PesananToko => 'toko online, link toko, pesanan dari pelanggan, QRIS, atau bayar online',
            self::SalahCatat => 'salah mencatat, mengubah, menghapus, atau membatalkan catatan',
            self::RekeningBaru => 'menambah rekening bank atau dompet digital usaha, atau melihat saldo tiap rekening',
            self::HubungkanBot => 'menghubungkan bot Telegram atau WhatsApp, atau mengatur ringkasan harian',
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
            self::BayarUtangSupplier => 'Belanja yang belum dibayar dicatat sebagai Belanja dengan cara bayar Belum lunas. Utangnya muncul sendiri di menu Utang-piutang, tab Utang. Saat dilunasi, catat di sana sebagai Bayar utang; boleh dicicil.',
            self::StokTidakSesuai => 'Stok yang rusak, hilang, atau beda dengan hitungan dikoreksi di Produk & stok lewat Koreksi stok, bukan lewat transaksi. Selisihnya dinilai dengan biaya rata-rata.',
            self::BaruMulai => 'Uang, peralatan, utang, dan piutang yang sudah ada saat mulai memakai Lais dicatat sekali sebagai Saldo awal. Setelah itu, catat transaksi harian seperti biasa.',
            self::BeliStok => 'Belanja bahan baku atau barang dagangan dicatat sebagai Belanja, lalu pilih Barang. Stoknya bertambah dan modal rata-ratanya diperbarui, jadi HPP penjualan berikutnya ikut benar. Ceritakan saja belanjanya, misalnya "beli beras 10 kg 120rb tunai".',
            self::TambahProduk => 'Produk dan bahan ditambah di menu Produk & stok, lalu Tambah produk. Produk yang dibuat dari bahan memakai resep, jadi HPP-nya dihitung sendiri dari harga bahannya. Harga jual bisa diubah kapan saja dari halaman produknya.',
            self::DaftarAkun => 'Daftar akun (COA) ada di menu Laporan, bagian Pembukuan, lalu Daftar akun (COA). Akun sistem sudah disiapkan dan terisi sendiri dari setiap catatan, jadi kamu tidak perlu memilih akun saat mencatat. Kalau perlu akun sendiri, tambahkan akun Beban atau Pendapatan di sana, lalu pilih akunnya saat mencatat biaya atau pemasukan lain.',
            self::PesananToko => 'Toko online diatur di menu Pesanan: buka atau tutup toko, salin link toko untuk dibagikan, dan isi nomor WA serta QRIS lewat Pengaturan. Pesanan dari link masuk ke tab Menunggu, lalu diterima, disiapkan, dan diselesaikan dari halaman yang sama.',
            self::SalahCatat => 'Catatan yang salah dibuka dari menu Transaksi, lalu pilih Ubah atau Batalkan. Lais tidak menghapus catatan: yang lama dibatalkan dengan jurnal pembalik dan riwayatnya tetap bisa dilihat, jadi laporan tetap rapi.',
            self::RekeningBaru => 'Rekening bank atau dompet digital usaha ditambah di menu Kas & rekening, lalu Tambah rekening. Saldo tiap rekening dihitung sendiri dari catatan. Uang yang sudah ada di rekening itu dicatat sekali sebagai Saldo awal.',
            self::HubungkanBot => 'Bot dihubungkan dari menu Pengaturan, lalu Bot. Buat kode di sana dan kirim kodenya ke bot dari HP kamu. Setelah terhubung, kamu bisa mencatat lewat chat dan menerima ringkasan setiap malam.',
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
            self::BeliStok => ['Barang dan jumlahnya', 'Total harga', 'Cara bayar'],
            self::TambahProduk => ['Nama dan satuan', 'Harga jual', 'Stok sekarang, atau resepnya'],
            self::RekeningBaru => ['Nama bank atau dompet digital', 'Saldo yang sudah ada'],
            self::DaftarAkun => ['Nama akun baru, kalau mau menambah', 'Beban atau Pendapatan'],
            self::PesananToko => ['Nomor WA penjual', 'Gambar QRIS, kalau ada'],
            self::SalahCatat => ['Catatan mana yang salah', 'Isian yang benar'],
            self::HubungkanBot => ['HP yang dipakai untuk chat dengan bot'],
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
     * The page where it is done or seen; topics with a draft also offer it as a button.
     *
     * @return array{label: string, href: string}|null
     */
    public function link(Business $business): ?array
    {
        $record = fn (string $label, TransactionType $type) => ['label' => $label, 'href' => route('transactions.create', ['jenis' => $type->value], absolute: false)];

        return match ($this) {
            self::PindahUang => $record('Buka form Pindah dana', TransactionType::PindahDana),
            self::BayarPakaiUangPribadi, self::BeliStok => $record('Buka form Belanja', TransactionType::Pembelian),
            self::JualBelumDibayar => $record('Buka form Penjualan', TransactionType::Penjualan),
            self::BayarUtangSupplier => ['label' => 'Lihat utang', 'href' => route('debts.index', ['jenis' => 'utang'], absolute: false)],
            self::StokTidakSesuai => ['label' => 'Buka Produk & stok', 'href' => route('items.index', absolute: false)],
            self::TambahProduk => ['label' => 'Tambah produk', 'href' => route('items.create', absolute: false)],
            self::DaftarAkun => ['label' => 'Buka Daftar akun', 'href' => route('reports.chart', absolute: false)],
            self::PesananToko => ['label' => 'Buka Pesanan', 'href' => route('orders.index', absolute: false)],
            self::SalahCatat => ['label' => 'Buka Transaksi', 'href' => route('transactions.index', absolute: false)],
            self::RekeningBaru => ['label' => 'Buka Kas & rekening', 'href' => route('money-accounts.index', absolute: false)],
            self::HubungkanBot => ['label' => 'Buka pengaturan Bot', 'href' => route('whatsapp.edit', absolute: false)],
            self::BaruMulai => $business->onboarded_at === null
                ? ['label' => 'Mulai catat saldo awal', 'href' => route('onboarding.show', absolute: false)]
                : ['label' => 'Catat saldo awal', 'href' => route('transactions.create', ['jenis' => TransactionType::SaldoAwal->value], absolute: false)],
            default => null,
        };
    }
}
