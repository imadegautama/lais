import type { MoneyDirection } from '@/types/domain';
import {
    BusinessCategory,
    TransactionKind,
    TransactionType,
    transactionKindLabels,
    transactionTypeLabels,
} from '@/types/enums';

/**
 * What the landing page's chat demo plays, with no model behind it. The
 * words follow the bot's real replies (Formatter, ReportAnswers), and
 * tests/Feature/LandingTest.php checks every number against the books.
 */
export type DemoExample = {
    id: 'jual' | 'belanja' | 'prive' | 'tanya';
    chip: string;
    message: string;
    draft: {
        title: string;
        lines: string[];
        totalLabel: string;
        total: number;
        note: string;
    } | null;
    saved: string | null;
    answer: string[] | null;
    stock: Array<{ name: string; qty: number; unit: string }>;
    cash: { label: string; amount: number };
    profit: { label: string; amount: number; note: string };
    journal: Array<{ account: string; debit: number; credit: number }>;
    /** Read out when the example changes. */
    summary: string;
};

export const CONFIRM = 'Balas 1 untuk simpan, 2 untuk batal.';

/**
 * OCBC Business Fitness Index 2024, quoted from OCBC's own press release.
 * The 77% counts only businesses that already keep records.
 */
export const surveyFacts = [
    {
        value: 46,
        text: 'UMKM yang sepenuhnya memisahkan uang usaha dan uang pribadi.',
    },
    {
        value: 77,
        text: 'UMKM yang sudah mencatat keuangan, masih melakukannya secara manual.',
    },
] as const;

/**
 * The four problems the landing page names, in the order the answers below
 * them come. Every answer is something the app does today.
 */
export const problems = [
    {
        id: 'uang',
        title: 'Uang usaha dan pribadi tercampur',
        pain: 'Belanja bahan pakai uang dapur, ambil uang buat rumah tanpa dicatat.',
        answer: 'Uang pribadi dicatat terpisah, laba tetap jelas.',
        detail: 'Ambil uang untuk rumah, kas turun tetapi laba tidak berubah. Belanja usaha memakai uang pribadi tercatat sebagai setoran modal.',
    },
    {
        id: 'catat',
        title: 'Mau mencatat, tapi ribet dan manual',
        pain: 'Buku tulis penuh coretan, aplikasi pembukuan penuh istilah debit dan kredit.',
        answer: 'Cukup chat, jurnalnya tersusun sendiri.',
        detail: 'Ketik di Telegram atau Tanya AI, atau kirim foto nota. Lais membuat draf, kamu yang menyetujui. Pesanan dari toko online ikut tercatat.',
    },
    {
        id: 'untung',
        title: 'Tidak tahu untung sebenarnya',
        pain: 'Harga jual ditebak, modal per porsi tidak pernah dihitung.',
        answer: 'Untung tiap porsi kelihatan.',
        detail: 'HPP dihitung dari resep, dan laba rugi tersusun dari jurnal, mengacu pada SAK EMKM.',
    },
    {
        id: 'stok',
        title: 'Stok habis, utang terlupa',
        pain: 'Bahan habis saat ramai, pelanggan lupa ditagih.',
        answer: 'Stok dan utang terpantau sebelum jadi masalah.',
        detail: 'Stok bahan berkurang sesuai resep, ada peringatan saat menipis, dan daftar siapa yang belum bayar beserta umurnya.',
    },
] as const;

/**
 * Real shops, not stock art. Each photo is CC BY-SA, so its credit and the
 * note that it was cropped stay next to it (DESIGN.md §6).
 */
export const businessPhotos = [
    {
        category: BusinessCategory.Kuliner,
        file: 'usaha-kuliner',
        text: 'Stok bahan berkurang sesuai resep',
        alt: 'Gerobak soto kaki lima di pinggir jalan Jakarta.',
        author: 'Gunawan Kartapranata',
        page: 'https://commons.wikimedia.org/wiki/File:Kakilima_street_vendors_in_Jakarta.jpg',
        license: 'CC BY-SA 3.0',
        licenseUrl: 'https://creativecommons.org/licenses/by-sa/3.0/',
    },
    {
        category: BusinessCategory.Kelontong,
        file: 'usaha-kelontong',
        text: 'Stok dan utang pembeli terpantau',
        alt: 'Warung kelontong dengan rak penuh jajanan, galon air, dan mainan.',
        author: 'SATELITBM',
        page: 'https://commons.wikimedia.org/wiki/File:Warung_Kelontong.jpg',
        license: 'CC BY-SA 4.0',
        licenseUrl: 'https://creativecommons.org/licenses/by-sa/4.0/',
    },
    {
        category: BusinessCategory.Fashion,
        file: 'usaha-fashion',
        text: 'Harga pokok tiap barang terlihat',
        alt: 'Penjahit berkaus merah menjahit kain batik di mesin jahit.',
        author: 'Randy Novian R',
        page: 'https://commons.wikimedia.org/wiki/File:Penjahit_Pakaian.jpg',
        license: 'CC BY-SA 4.0',
        licenseUrl: 'https://creativecommons.org/licenses/by-sa/4.0/',
    },
    {
        category: BusinessCategory.Kerajinan,
        file: 'usaha-kerajinan',
        text: 'Biaya bahan tiap produk terhitung',
        alt: 'Tangan pembatik menggoreskan canting pada kain batik.',
        author: 'Bhrigu bayan',
        page: 'https://commons.wikimedia.org/wiki/File:The_Art_of_Batik.jpg',
        license: 'CC BY-SA 4.0',
        licenseUrl: 'https://creativecommons.org/licenses/by-sa/4.0/',
    },
] as const;

/**
 * The real app on the laptop. The pictures come from `public/images/landing`,
 * taken from the example books (DemoHistorySeeder).
 */
export const appScreens = [
    {
        id: 'dashboard',
        label: 'Dashboard',
        caption:
            'Penjualan, untung, dan uang usaha, plus hal yang perlu kamu tindak.',
        alt: 'Dashboard Lais berisi penjualan, untung bersih, uang usaha, daftar yang perlu ditindak, dan kesehatan usaha.',
    },
    {
        id: 'produk',
        label: 'Produk & stok',
        caption: 'Harga jual, HPP, margin, dan sisa stok tiap produk.',
        alt: 'Halaman Produk dan stok berisi nilai persediaan, stok menipis, dan daftar produk dengan harga jual, HPP, dan margin.',
    },
    {
        id: 'piutang',
        label: 'Utang-piutang',
        caption:
            'Siapa yang belum bayar, sudah berapa lama, dan berapa sisanya.',
        alt: 'Halaman Utang-piutang berisi pelanggan yang belum bayar, umur tagihan, dan sisanya.',
    },
    {
        id: 'laporan',
        label: 'Laporan',
        caption: 'Laba rugi dengan kesimpulan satu kalimat, lalu rinciannya.',
        alt: 'Laporan laba rugi dengan kesimpulan bulan lalu untung, rincian penjualan sampai laba bersih, dan grafik laba.',
    },
] as const;

/** The books on the right of the chat diagram, in their order there. */
export const books = [
    {
        id: TransactionType.Penjualan,
        label: transactionTypeLabels[TransactionType.Penjualan],
    },
    {
        id: TransactionType.Pembelian,
        label: transactionTypeLabels[TransactionType.Pembelian],
    },
    {
        id: TransactionType.Biaya,
        label: transactionTypeLabels[TransactionType.Biaya],
    },
    {
        id: TransactionType.UtangPiutang,
        label: transactionTypeLabels[TransactionType.UtangPiutang],
    },
    {
        id: TransactionType.Modal,
        label: transactionTypeLabels[TransactionType.Modal],
    },
    { id: 'produk', label: 'Produk' },
] as const;

export type SampleRecord = {
    message: string;
    book: (typeof books)[number]['id'];
    record: string;
    amount: number;
    direction: MoneyDirection;
};

/**
 * Chats from AssistantEvalTest and the book each lands in, so every pair is
 * one the assistant is known to read right. Amounts come from the message
 * or the starter catalog's prices. Their order differs from the books' on
 * purpose, so the lines cross.
 */
export const sampleRecords: SampleRecord[] = [
    {
        message: 'bayar listrik 350rb',
        book: TransactionType.Biaya,
        record: transactionKindLabels[TransactionKind.ListrikAir],
        amount: 350_000,
        direction: 'out',
    },
    {
        message: 'jual 3 nasgor 2 es teh qris',
        book: TransactionType.Penjualan,
        record: 'Non-tunai',
        amount: 55_000,
        direction: 'in',
    },
    {
        message: 'setor modal 2jt tunai',
        book: TransactionType.Modal,
        record: transactionKindLabels[TransactionKind.Setor],
        amount: 2_000_000,
        direction: 'in',
    },
    {
        message: 'beli beras 5 kg 70rb di toko makmur',
        book: TransactionType.Pembelian,
        record: 'Stok beras +5 kg',
        amount: 70_000,
        direction: 'out',
    },
    {
        message: 'tambah menu mie goreng 14rb pakai telur 1 dan beras 1 ons',
        book: 'produk',
        record: 'Mie Goreng + resep',
        amount: 14_000,
        direction: 'none',
    },
    {
        message: 'bu sari bayar utangnya 30rb',
        book: TransactionType.UtangPiutang,
        record: 'Bayaran dari Bu Sari',
        amount: 30_000,
        direction: 'in',
    },
];

export const examples: DemoExample[] = [
    {
        id: 'jual',
        chip: 'Jual 3 nasgor',
        message: 'jual 3 nasgor 1 aqua qris',
        draft: {
            title: 'Penjualan',
            lines: [
                '3 porsi Nasi Goreng × Rp15.000 = Rp45.000',
                '1 botol Air Mineral 600 ml = Rp4.000',
            ],
            totalLabel: 'Total',
            total: 49_000,
            note: 'Non-tunai',
        },
        saved: 'Penjualan tersimpan.',
        answer: null,
        stock: [
            { name: 'Beras', qty: -0.45, unit: 'kg' },
            { name: 'Telur', qty: -3, unit: 'butir' },
            { name: 'Kerupuk', qty: -3, unit: 'bungkus' },
            { name: 'Minyak goreng', qty: -0.06, unit: 'liter' },
            { name: 'Bumbu & kecap', qty: -3, unit: 'porsi' },
            { name: 'Air Mineral 600 ml', qty: -1, unit: 'botol' },
        ],
        cash: { label: 'Kas non-tunai', amount: 49_000 },
        profit: {
            label: 'Laba',
            amount: 24_900,
            note: 'Penjualan dikurangi HPP.',
        },
        journal: [
            { account: 'Kas Non-Tunai', debit: 49_000, credit: 0 },
            { account: 'Penjualan', debit: 0, credit: 49_000 },
            { account: 'HPP', debit: 24_100, credit: 0 },
            { account: 'Persediaan', debit: 0, credit: 24_100 },
        ],
        summary:
            'Satu pesan penjualan Rp49.000 mengurangi stok bahan sesuai resep, menambah kas non-tunai, dan menambah laba Rp24.900.',
    },
    {
        id: 'belanja',
        chip: 'Beli telur',
        message: 'beli telur 30 butir 66rb tunai',
        draft: {
            title: 'Belanja',
            lines: ['30 butir Telur × Rp2.200 = Rp66.000'],
            totalLabel: 'Total',
            total: 66_000,
            note: 'Tunai',
        },
        saved: 'Belanja tersimpan.',
        answer: null,
        stock: [{ name: 'Telur', qty: 30, unit: 'butir' }],
        cash: { label: 'Kas tunai', amount: -66_000 },
        profit: {
            label: 'Laba',
            amount: 0,
            note: 'Uang berubah jadi stok.',
        },
        journal: [
            { account: 'Persediaan', debit: 66_000, credit: 0 },
            { account: 'Kas Tunai', debit: 0, credit: 66_000 },
        ],
        summary:
            'Belanja Rp66.000 menambah stok telur dan mengurangi kas tunai, sedangkan laba tetap.',
    },
    {
        id: 'prive',
        chip: 'Ambil 200rb',
        message: 'ambil 200rb buat rumah',
        draft: {
            title: 'Ambil untuk pribadi',
            lines: [],
            totalLabel: 'Nominal',
            total: 200_000,
            note: 'Tunai',
        },
        saved: 'Catatan modal tersimpan.',
        answer: null,
        stock: [],
        cash: { label: 'Kas tunai', amount: -200_000 },
        profit: {
            label: 'Laba',
            amount: 0,
            note: 'Uang pribadi bukan biaya.',
        },
        journal: [
            { account: 'Prive', debit: 200_000, credit: 0 },
            { account: 'Kas Tunai', debit: 0, credit: 200_000 },
        ],
        summary:
            'Mengambil Rp200.000 untuk pribadi mengurangi kas tunai, sedangkan laba tetap.',
    },
    {
        id: 'tanya',
        chip: 'Kenapa kas turun?',
        message: 'kenapa laba naik tapi kas turun?',
        draft: null,
        saved: null,
        answer: [
            'Bulan ini laba bersih Rp24.900, sedangkan uang usaha turun Rp217.000.',
            'Bedanya karena:',
            '• Uang jadi stok (belanja bahan/barang): −Rp41.900',
            '• Diambil untuk pribadi: −Rp200.000',
        ],
        stock: [],
        cash: { label: 'Uang usaha bulan ini', amount: -217_000 },
        profit: {
            label: 'Laba bulan ini',
            amount: 24_900,
            note: 'Dari tiga catatan sebelumnya.',
        },
        journal: [],
        summary:
            'Lais menjelaskan laba Rp24.900 dan uang usaha turun Rp217.000 dari tiga catatan sebelumnya, tanpa mengubah pembukuan.',
    },
];
