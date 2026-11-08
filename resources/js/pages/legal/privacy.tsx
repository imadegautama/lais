import { CONTACT_EMAIL, LegalPage } from '@/features/legal/legal-page';

export default function Privacy() {
    return (
        <LegalPage title="Kebijakan Privasi" updated="8 November 2026">
            <p>
                Lais adalah aplikasi pembukuan untuk UMKM yang dikelola oleh Tim
                Lais. Halaman ini menjelaskan data apa yang kami simpan, untuk
                apa data itu dipakai, dan bagaimana kamu bisa menghapusnya.
            </p>

            <h2>Data yang kami simpan</h2>
            <ul>
                <li>
                    <strong>Akun:</strong> nama, email, dan password. Password
                    disimpan dalam bentuk acak (hash), jadi tidak bisa dibaca
                    siapa pun, termasuk kami. Kalau kamu masuk dengan Google,
                    kami hanya mengambil nama, email, dan ID akun Google-mu.
                </li>
                <li>
                    <strong>Data usaha yang kamu catat:</strong> produk, resep,
                    stok, transaksi, rekening, kontak pelanggan dan pemasok
                    (nama dan nomor HP), pesanan, serta foto bukti transaksi,
                    foto produk, dan gambar QRIS yang kamu unggah.
                </li>
                <li>
                    <strong>Pesan ke bot Lais:</strong> isi pesan yang kamu
                    kirim ke bot di Telegram atau WhatsApp, balasannya, dan ID
                    chat-mu.
                </li>
                <li>
                    <strong>Pesanan dari halaman toko:</strong> nama dan nomor
                    WhatsApp pembeli yang memesan, supaya pemilik toko bisa
                    menghubunginya.
                </li>
                <li>
                    <strong>Catatan keamanan:</strong> waktu masuk dan keluar,
                    alamat IP, dan jenis browser, supaya kamu bisa melihat
                    aktivitas akunmu di halaman Pengaturan.
                </li>
            </ul>

            <h2>Untuk apa data dipakai</h2>
            <ul>
                <li>Menyusun pembukuan, stok, dan laporan usahamu.</li>
                <li>
                    Mengirim email verifikasi dan atur ulang password, serta
                    ringkasan harian lewat bot kalau kamu menyalakannya.
                </li>
                <li>Menjaga keamanan akunmu.</li>
            </ul>
            <p>
                Kami tidak menjual data, tidak memasang iklan, dan tidak memakai
                alat pelacak atau analitik dari pihak lain.
            </p>

            <h2>Layanan lain yang kami pakai</h2>
            <ul>
                <li>
                    <strong>Anthropic (Claude):</strong> membaca pesan dan foto
                    nota yang kamu kirim ke asisten AI untuk menyusun draf
                    catatan. Draf tidak pernah tersimpan ke pembukuan tanpa
                    persetujuanmu, dan foto nota yang dibaca asisten tidak kami
                    simpan.
                </li>
                <li>
                    <strong>Cloudflare R2:</strong> menyimpan foto produk, QRIS,
                    dan bukti transaksi. Bukti transaksi disimpan privat dan
                    hanya bisa dibuka pemilik usahanya.
                </li>
                <li>
                    <strong>Telegram atau WhatsApp:</strong> menyampaikan pesan
                    antara kamu dan bot.
                </li>
                <li>
                    <strong>Resend:</strong> mengirim email verifikasi dan atur
                    ulang password.
                </li>
                <li>
                    <strong>Google:</strong> kalau kamu memilih masuk dengan
                    Google.
                </li>
            </ul>

            <h2>Cookie</h2>
            <p>
                Kami hanya memakai cookie yang dibutuhkan supaya kamu tetap
                masuk dan form aman dikirim, ditambah satu cookie untuk
                mengingat sidebar terbuka atau tertutup. Tidak ada cookie iklan.
            </p>

            <h2>Berapa lama disimpan dan cara menghapus</h2>
            <ul>
                <li>Data disimpan selama akunmu aktif.</li>
                <li>
                    Transaksi yang sudah tercatat tidak dihapus satu per satu,
                    melainkan dibatalkan, supaya pembukuan tetap bisa
                    ditelusuri.
                </li>
                <li>
                    Kamu bisa menghapus akun dari Pengaturan, Profil. Akun dan
                    data usaha di database ikut terhapus: produk, transaksi,
                    pesanan, kontak, dan riwayat aktivitas usaha.
                </li>
                <li>
                    Yang belum ikut terhapus otomatis: file foto yang pernah
                    diunggah, riwayat pesan bot, dan catatan masuk yang tidak
                    terikat ke usaha. Kirim email ke{' '}
                    <a href={`mailto:${CONTACT_EMAIL}`}>{CONTACT_EMAIL}</a> dan
                    kami akan menghapusnya, lalu mengabarimu lewat email.
                </li>
            </ul>

            <h2>Keamanan</h2>
            <p>
                Semua halaman memakai HTTPS, password disimpan dalam bentuk
                hash, bukti transaksi tidak pernah punya alamat publik, dan kamu
                bisa menyalakan autentikasi dua langkah di Pengaturan. Tidak ada
                sistem yang sepenuhnya kebal, jadi jaga juga password-mu.
            </p>

            <h2>Perubahan</h2>
            <p>
                Kalau kebijakan ini berubah, tanggal di atas ikut diperbarui.
                Perubahan penting akan kami kabarkan lewat email.
            </p>

            <h2>Kontak</h2>
            <p>
                Pertanyaan atau permintaan soal datamu:{' '}
                <a href={`mailto:${CONTACT_EMAIL}`}>{CONTACT_EMAIL}</a>.
            </p>
        </LegalPage>
    );
}
