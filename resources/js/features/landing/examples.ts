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

/** Messages from AssistantEvalTest, so each one is known to be understood. */
export const sampleMessages = [
    'jual 3 nasgor 2 es teh qris',
    'bu sari ngutang 2 nasgor',
    'beli beras 5 kg 70rb di toko makmur',
    'bayar listrik 350rb',
    'gaji karyawan 1,5jt transfer',
    'setor modal 2jt tunai',
    'bu sari bayar utangnya 30rb',
    'tambah menu mie goreng 14rb pakai telur 1 dan beras 1 ons',
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
