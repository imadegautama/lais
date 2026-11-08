import { router, usePage } from '@inertiajs/react';
import { CircleHelp } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import type { Step } from 'react-joyride';
import { Button } from '@/components/ui/button';
import { recordCopy } from '@/features/transactions/vocabulary';
import { complete as completeTour } from '@/routes/tour';
import type { TransactionType } from '@/types/enums';
import { card, GuidedTour, onScreen, withText } from './product-tour';

type Stop = Pick<Step, 'title' | 'content'>;

const save = onScreen('form button[type="submit"]');
const askAi = onScreen('[data-tour="tanya-ai"]');
const period = onScreen('[aria-label="Pilih periode"]');

/** What the record form asks, per type: the sub-kind (when there is one), the details, and the money. */
const RECORD: Record<
    TransactionType,
    { kind?: Stop; details: Stop; payment: Stop }
> = {
    penjualan: {
        details: {
            title: 'Apa yang terjual?',
            content:
                'Cari produknya lalu isi jumlahnya. Harga terisi sendiri dari daftar produk, dan stok berkurang saat disimpan.',
        },
        payment: {
            title: 'Dibayar dengan apa?',
            content:
                'Tunai, non-tunai (QRIS atau transfer), atau belum lunas sehingga jadi piutang.',
        },
    },
    pembelian: {
        kind: {
            title: 'Belanja apa?',
            content:
                'Barang untuk dijual atau bahan baku, atau peralatan seperti kompor yang dipakai bertahun-tahun.',
        },
        details: {
            title: 'Rinciannya',
            content:
                'Barang: pilih produknya, jumlah, dan harga belinya, lalu stok bertambah. Peralatan: isi nama dan umur pakainya, nilainya disusutkan tiap bulan.',
        },
        payment: {
            title: 'Dibayar dari mana?',
            content:
                'Kas, rekening, atau uang pribadi (tercatat sebagai tambahan modal). Belum lunas berarti jadi utang.',
        },
    },
    biaya: {
        kind: {
            title: 'Biaya untuk apa?',
            content:
                'Pilih jenisnya, misalnya Sewa. Biaya masuk ke akun bebannya sendiri, Beban Sewa.',
        },
        details: {
            title: 'Berapa?',
            content: 'Isi nominalnya. Catatan boleh dikosongkan.',
        },
        payment: {
            title: 'Dibayar dari mana?',
            content:
                'Kas, rekening, atau uang pribadi (tercatat sebagai tambahan modal). Belum lunas berarti jadi utang.',
        },
    },
    pemasukan_lain: {
        details: {
            title: 'Uang masuk dari mana?',
            content:
                'Uang masuk di luar penjualan, misalnya hibah atau bunga bank. Isi nominal dan keterangannya.',
        },
        payment: {
            title: 'Masuk ke mana?',
            content: 'Pilih kas atau rekening yang menerima uangnya.',
        },
    },
    utang_piutang: {
        kind: {
            title: 'Terima atau bayar?',
            content:
                'Terima bayaran dari pelanggan yang belum lunas, atau bayar utang ke supplier.',
        },
        details: {
            title: 'Berapa yang dibayar?',
            content: 'Boleh sebagian. Sisa tagihannya berkurang sendiri.',
        },
        payment: {
            title: 'Siapa dan lewat apa?',
            content:
                'Pilih kontaknya, lalu tunai atau non-tunai. Tagihan yang masih terbuka muncul saat kontak dipilih.',
        },
    },
    modal: {
        kind: {
            title: 'Tambah atau ambil?',
            content:
                'Tambah modal dari uang pribadi, atau ambil uang usaha untuk keperluan rumah. Keduanya tidak dihitung sebagai laba atau biaya.',
        },
        details: { title: 'Berapa?', content: 'Isi nominalnya.' },
        payment: {
            title: 'Lewat kas atau rekening?',
            content: 'Pilih tempat uangnya masuk atau keluar.',
        },
    },
    pindah_dana: {
        details: {
            title: 'Berapa yang dipindah?',
            content:
                'Misalnya setoran tunai ke bank. Total uang usaha tidak berubah, hanya tempatnya.',
        },
        payment: {
            title: 'Dari dan ke',
            content: 'Pilih rekening asal dan tujuannya.',
        },
    },
    saldo_awal: {
        details: {
            title: 'Saldo awal',
            content:
                'Yang sudah ada saat kamu mulai mencatat. Modal awal dihitung dari selisihnya.',
        },
        payment: {
            title: 'Keterangan',
            content: 'Tanggal dan catatan saldo awalnya.',
        },
    },
};

/**
 * The guide of each feature page, by Inertia component (UI-PAGES "Panduan").
 * Stops find their card by its title, so a renamed card title needs its stop renamed here.
 */
const GUIDES: Record<string, (props: Record<string, unknown>) => Step[]> = {
    dashboard: () => [
        {
            target: onScreen('[data-tour="catat"]'),
            title: 'Catat apa saja',
            content:
                'Penjualan, belanja, atau biaya. Isi form singkat, atau tulis seperti chat biasa.',
        },
        {
            target: askAi,
            title: 'Bingung? Tanya AI',
            content:
                'Tanya dicatat di mana, atau minta dibuatkan catatannya. AI hanya menyiapkan draf, kamu yang menyimpan.',
        },
        {
            target: onScreen('a[href$="/pesanan"]'),
            title: 'Pesanan dan toko online',
            content:
                'Pesanan dari link tokomu masuk di sini, lengkap dengan stok dan pembayarannya.',
        },
        {
            target: onScreen('a[href$="/laporan"]'),
            title: 'Laporan tersusun sendiri',
            content:
                'Laba rugi, neraca, dan daftar akun terisi dari catatanmu, tanpa rumus.',
        },
        {
            // The heading, not the whole card: on a phone the card is taller than the screen.
            target: onScreen('#misi h2'),
            title: 'Misi pertama',
            content:
                'Tekan tombol di tiap misi: Lais membuka halamannya dan memandu langkahnya satu per satu.',
        },
        {
            target: onScreen('[data-tour="panduan"]'),
            title: 'Panduan di setiap halaman',
            content:
                'Bingung di halaman mana pun? Tekan Panduan, Lais menunjukkan bagian-bagiannya satu per satu.',
        },
    ],
    'transactions/create': (props) => {
        const type = props.initialType as TransactionType;
        const { kind, details, payment } = RECORD[type];

        return [
            {
                target: onScreen('nav[aria-label="Jenis catatan"]'),
                title: 'Pilih jenis catatan',
                content:
                    'Penjualan, belanja, biaya, dan lainnya. Isi form di bawahnya mengikuti jenis yang dipilih.',
            },
            ...(kind
                ? [{ target: card(recordCopy[type].kindTitle), ...kind }]
                : []),
            {
                // Belanja barang has two cards titled "Yang dibeli": the kind, then the items.
                target: () =>
                    card('Yang terjual')() ??
                    card('Rincian')() ??
                    card('Yang dibeli', true)(),
                ...details,
            },
            { target: card(recordCopy[type].paymentTitle), ...payment },
            {
                target: card('Dampak catatan ini'),
                title: 'Cek dampaknya',
                content:
                    'Angka yang berubah saat catatan ini disimpan, dihitung langsung dari isianmu.',
            },
            {
                target: askAi,
                title: 'Malas mengetik?',
                content:
                    'Tulis di Tanya AI, misalnya "jual 3 nasi goreng tunai". Form ini terisi sendiri, kamu tinggal memeriksa lalu menyimpan.',
            },
            {
                target: save,
                title: 'Simpan',
                content:
                    'Stok, kas, dan laporan langsung diperbarui. Simpan & catat lagi untuk mencatat yang berikutnya.',
            },
        ];
    },
    'transactions/index': () => [
        {
            target: card('Uang usaha'),
            title: 'Uang masuk dan keluar',
            content:
                'Dihitung dari transaksi di daftar. Yang belum lunas dan uang pribadi tidak dihitung.',
        },
        {
            target: card('Daftar transaksi'),
            title: 'Semua catatan',
            content:
                'Dari web, bot, dan toko online. Saring per periode, jenis, cara bayar, atau sumber, lalu tekan baris untuk melihat rincian, mengubah, atau membatalkan.',
        },
        {
            target: withText('button', 'Catat transaksi'),
            title: 'Catat yang baru',
            content: 'Pilih jenisnya: penjualan, belanja, biaya, dan lainnya.',
        },
    ],
    'items/index': () => [
        {
            target: card('Persediaan sekarang'),
            title: 'Nilai stokmu',
            content:
                'Nilai persediaan, stok yang menipis, dan stok minus, dinilai dengan biaya rata-rata.',
        },
        {
            target: card('Daftar produk'),
            title: 'Produk dan bahan',
            content:
                'Harga jual, HPP, dan sisa stok. Tekan nama produk untuk mengubah harga, resep, atau stoknya.',
        },
        {
            target: withText('a', 'Tambah produk'),
            title: 'Tambah produk',
            content:
                'Produk biasa dibeli lalu dijual. Produk dengan resep dibuat dari bahan, jadi HPP-nya dihitung dari resepnya.',
        },
    ],
    'items/form': () => [
        {
            target: card('Data dasar'),
            title: 'Isi data produknya',
            content:
                'Nama, jenis, satuan, dan harga jual. Pilih produk dengan resep kalau dibuat dari bahan.',
        },
        {
            target: card('Stok awal'),
            title: 'Stok saat ini',
            content:
                'Isi berapa yang ada sekarang dan harga belinya. Kalau belum ada, biarkan 0.',
        },
        {
            target: card('Riwayat stok'),
            title: 'Riwayat stok',
            content:
                'Setiap stok masuk dan keluar tercatat di sini: dari penjualan, belanja, dan penyesuaian.',
        },
        {
            target: save,
            title: 'Simpan',
            content: 'Produknya langsung bisa dipilih saat mencatat penjualan.',
        },
    ],
    'orders/index': () => [
        {
            target: card('Terima pesanan online'),
            title: 'Toko onlinemu',
            content:
                'Saklar ini membuka atau menutup pesanan dari link tokomu.',
        },
        {
            target: withText('button', 'Salin link'),
            title: 'Bagikan link toko',
            content:
                'Tempel di status WA atau bio Instagram. Pesanan yang masuk muncul di halaman ini.',
        },
        {
            target: withText('button', 'Pengaturan'),
            title: 'Nomor WA dan QRIS',
            content:
                'Isi nomor WA penjual atau gambar QRIS di sini, supaya pelanggan bisa menghubungi dan membayar.',
        },
        {
            target: onScreen('nav[aria-label="Status pesanan"]'),
            title: 'Alur pesanan',
            content:
                'Menunggu, Diproses, Siap, lalu Selesai. Angka merah berarti ada pesanan baru yang menunggu diterima.',
        },
        {
            target: withText('button', 'Pesanan'),
            title: 'Pesanan lewat telepon atau WA',
            content:
                'Catat sendiri di sini, supaya stoknya ikut tertahan seperti pesanan online.',
        },
    ],
    'money-accounts/index': () => [
        {
            target: card('Saldo per rekening'),
            title: 'Uang usahamu',
            content:
                'Kas tunai dan rekening. Saldonya dihitung dari semua catatan, bukan diketik.',
        },
        {
            target: withText('a', 'Pindah dana'),
            title: 'Pindah dana',
            content:
                'Setor tunai ke bank atau tarik dari bank. Catat di sini supaya saldo tiap rekening tetap cocok.',
        },
        {
            target: withText('button', 'Tambah rekening'),
            title: 'Tambah rekening',
            content:
                'Punya rekening bank atau dompet digital lain untuk usaha? Tambahkan di sini.',
        },
    ],
    'debts/index': () => [
        {
            target: onScreen('nav[aria-label="Jenis tagihan"]'),
            title: 'Piutang dan utang',
            content:
                'Piutang: pelanggan yang belum bayar. Utang: supplier yang belum kamu bayar. Keduanya muncul sendiri dari catatan yang belum lunas.',
        },
        {
            target: () =>
                card('Pelanggan yang belum bayar')() ??
                card('Supplier yang belum dibayar')(),
            title: 'Siapa dan berapa',
            content:
                'Tekan Terima bayaran atau Bayar utang saat ada pembayaran. Boleh sebagian, sisanya berkurang sendiri.',
        },
    ],
    'reports/index': () => [
        {
            target: period,
            title: 'Pilih periode',
            content: 'Angka di halaman ini mengikuti periode yang dipilih.',
        },
        {
            target: card('Apakah usaha makin untung?'),
            title: 'Untung atau rugi?',
            content: 'Laba bersih per bulan, supaya kelihatan naik atau turun.',
        },
        {
            target: card('Kesehatan usaha'),
            title: 'Laporan utama',
            content:
                'Laba rugi, posisi keuangan, dan perubahan modal, disusun mengacu pada SAK EMKM.',
        },
        {
            target: card('Uang dan produk'),
            title: 'Uang dan produk',
            content: 'Buku kas, produk terlaris, dan aset tetap.',
        },
        {
            target: card('Pembukuan'),
            title: 'Isi pembukuannya',
            content:
                'Daftar akun (COA), jurnal umum, neraca saldo, dan catatan laporan, untuk yang ingin melihat debit dan kreditnya.',
        },
    ],
    'settings/whatsapp': (props) => [
        {
            target: card(`Bot ${String(props.channel)}`),
            title: 'Bot Lais',
            content:
                'Bot mencatat lewat chat dan mengirim ringkasan malam. Ikuti langkah di kartu ini untuk menghubungkannya.',
        },
        {
            target: withText('button', 'Buat kode'),
            title: 'Mulai dari sini',
            content:
                'Buat kode, lalu kirim kodenya ke bot dari HP kamu. Setelah terhubung, kartu ini menampilkan akunmu.',
        },
        {
            target: card('Ringkasan harian'),
            title: 'Ringkasan harian',
            content:
                'Ringkasan dikirim bot setiap malam. Matikan di sini kalau tidak perlu.',
        },
    ],
};

/**
 * "Panduan" in the top bar: shown on pages that have a guide. It also opens by
 * itself when a mission sends the owner here (?panduan=1), and on the
 * dashboard until the owner finishes or skips the tour (startTour).
 */
export function PageGuide() {
    const { component, url, props } = usePage();
    const steps = useMemo(
        () => GUIDES[component]?.(props) ?? null,
        [component, props],
    );
    const [running, setRunning] = useState(false);
    const startTour = props.startTour === true;
    const fromMission = new URL(url, 'http://lais.local').searchParams.has(
        'panduan',
    );
    const hasGuide = steps !== null;

    // On arriving at a page, not on every prop refresh.
    useEffect(() => {
        if (hasGuide && (fromMission || startTour)) {
            // The page renders its cards first; the guide waits a frame for them.
            const frame = window.requestAnimationFrame(() => setRunning(true));

            return () => window.cancelAnimationFrame(frame);
        }
    }, [component, hasGuide, fromMission, startTour]);

    if (!steps) {
        return null;
    }

    const done = () => {
        setRunning(false);

        if (fromMission) {
            // Off the address, so a reload or a later visit does not run it again.
            const address = new URL(window.location.href);
            address.searchParams.delete('panduan');
            router.replace({
                url: address.pathname + address.search,
                preserveScroll: true,
                preserveState: true,
            });
        }

        // Finished or skipped: the dashboard tour stops opening by itself, on every device.
        if (startTour) {
            router.post(
                completeTour.url(),
                {},
                { preserveScroll: true, preserveState: true },
            );
        }
    };

    return (
        <>
            <Button
                type="button"
                variant="outline"
                data-tour="panduan"
                className="pointer-coarse:min-h-11"
                onClick={() => setRunning(true)}
            >
                <CircleHelp aria-hidden />
                Panduan
            </Button>
            <GuidedTour steps={steps} run={running} onDone={done} />
        </>
    );
}
