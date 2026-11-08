import { CONTACT_EMAIL, LegalPage } from '@/features/legal/legal-page';
import { privacy } from '@/routes';

export default function Terms() {
    return (
        <LegalPage title="Syarat dan Ketentuan" updated="8 November 2026">
            <p>
                Lais dikelola oleh Tim Lais. Dengan membuat akun atau memakai
                Lais, kamu setuju dengan syarat di bawah ini dan dengan{' '}
                <a href={privacy().url}>Kebijakan Privasi</a> kami.
            </p>

            <h2>Layanan</h2>
            <p>
                Lais membantu UMKM mencatat penjualan, belanja, stok, dan
                utang-piutang, lalu menyusun laporan keuangan yang mengacu pada
                SAK EMKM. Saat ini Lais gratis. Lais masih terus dikembangkan,
                jadi fitur bisa berubah.
            </p>

            <h2>Akun</h2>
            <ul>
                <li>Isi data akun dengan benar.</li>
                <li>
                    Jaga kerahasiaan password-mu. Kamu bertanggung jawab atas
                    semua yang terjadi di akunmu.
                </li>
                <li>
                    Kalau ada yang memakai akunmu tanpa izin, segera kabari kami
                    lewat{' '}
                    <a href={`mailto:${CONTACT_EMAIL}`}>{CONTACT_EMAIL}</a>.
                </li>
            </ul>

            <h2>Pencatatan dan asisten AI</h2>
            <ul>
                <li>
                    Kamu bertanggung jawab atas kebenaran data yang kamu catat.
                </li>
                <li>
                    Asisten AI hanya membuat draf. Catatan baru tersimpan
                    setelah kamu menyetujuinya, jadi periksa drafnya dulu.
                </li>
                <li>
                    Angka di laporan dihitung sistem dari catatanmu, bukan
                    dikarang AI.
                </li>
            </ul>

            <h2>Bukan pengganti akuntan</h2>
            <p>
                Laporan Lais membantu kamu memahami usaha. Laporan itu bukan
                laporan resmi yang diaudit, dan bukan nasihat akuntansi atau
                pajak. Keputusan usaha dan kewajiban pajak tetap tanggung
                jawabmu.
            </p>

            <h2>Halaman toko</h2>
            <p>
                Pemilik toko bertanggung jawab atas produk, harga, dan pelayanan
                kepada pembeli. Lais hanya meneruskan pesanan dan tidak
                memproses pembayaran; pembayaran terjadi langsung antara pembeli
                dan pemilik toko.
            </p>

            <h2>Yang tidak boleh</h2>
            <ul>
                <li>Memakai Lais untuk kegiatan yang melanggar hukum.</li>
                <li>
                    Mencoba membobol, membebani, atau mengganggu sistem Lais.
                </li>
                <li>Menyamar sebagai orang atau usaha lain.</li>
            </ul>

            <h2>Ketersediaan dan tanggung jawab</h2>
            <p>
                Kami berusaha agar Lais selalu berjalan baik, tetapi tidak bisa
                menjamin layanan bebas gangguan atau kesalahan. Sejauh diizinkan
                hukum, Tim Lais tidak bertanggung jawab atas kerugian yang
                timbul dari gangguan layanan atau dari keputusan yang diambil
                berdasarkan laporan.
            </p>

            <h2>Menghentikan akun</h2>
            <p>
                Kamu bisa menghapus akun kapan saja dari Pengaturan. Kami dapat
                menangguhkan akun yang melanggar syarat ini.
            </p>

            <h2>Perubahan dan hukum yang berlaku</h2>
            <p>
                Kalau syarat ini berubah, tanggal di atas ikut diperbarui, dan
                perubahan penting akan kami kabarkan lewat email. Syarat ini
                tunduk pada hukum Republik Indonesia.
            </p>

            <h2>Kontak</h2>
            <p>
                <a href={`mailto:${CONTACT_EMAIL}`}>{CONTACT_EMAIL}</a>
            </p>
        </LegalPage>
    );
}
