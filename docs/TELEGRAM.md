# Bot lewat Telegram

Untuk sementara bot Lais berjalan di Telegram (`BOT_CHANNEL=telegram`, nilai bawaan). GOWA adalah klien WhatsApp tidak resmi, jadi nomor bot bisa diblokir, sedangkan Bot API Telegram resmi. Tidak perlu Docker, QR, atau nomor cadangan.

Yang berubah hanya salurannya. Perintah, draf 15 menit, `1`/`2`, foto nota, dan ringkasan malam sama persis dengan WhatsApp. Kabar ke pelanggan tetap link `wa.me` yang dikirim pemilik dari WhatsApp pribadinya, karena itu bukan bot.

## Alur

1. `php artisan lais:telegram` menunggu pesan baru lewat `getUpdates` (long polling). Tidak perlu HTTPS, tunnel, atau endpoint publik.
2. `App\Services\WhatsApp\Telegram::receive()` hanya menerima chat pribadi yang berisi teks, caption, atau foto, lalu menyimpannya ke `wa_messages` sekali per `update_id`.
3. Job `ProcessWhatsAppMessage` menjalankan `Inbox`, sama seperti WhatsApp. Selama model membaca, bot mengirim `sendChatAction` (`typing`), jadi chat menampilkan "mengetik…".
4. Job `SendWhatsApp` membalas lewat `sendMessage`, dengan tombol di bawah pesan (`inline_keyboard`) kalau ada:
    - **Simpan · Batal** di bawah draf, satu tombol per pilihan, **Terima pesanan · Tolak** untuk pesanan baru, lalu **Tandai siap** dan **Selesai**.
    - Tombol halaman (misalnya **Lihat transaksi**) hanya muncul kalau `APP_URL` memakai https. Telegram menolak tombol ke http atau localhost, jadi di laptop alamatnya ditulis sebagai teks.
5. Tombol yang ditekan datang sebagai `callback_query`. `Telegram::receive()` menghentikan putaran loading di tombol, menghapus tombol dari pesan itu, lalu menyimpannya seperti pesan yang diketik ("1", "2", pilihan, atau "terima A001").
    - Tombol draf (`d:`) hanya berlaku di pesan terakhir bot. Tombol draf di pesan lama hanya dijawab "sudah lewat", supaya tidak menyimpan draf lain.
    - Tombol pesanan (`c:`) menyebut kodenya sendiri, jadi tetap berlaku.
    - Mengetik "1", "2", atau "terima A001" tetap bisa.

## Menjalankan di laptop

**1. Buat bot.** Di Telegram, chat [@BotFather](https://t.me/BotFather), kirim `/newbot`, lalu ikuti langkahnya. Simpan token dan username yang diberikan.

**2. Isi `.env`.** Token adalah rahasia: tulis langsung di `.env`, jangan ditempel di chat atau di-commit. Username ditulis tanpa `@`.

```dotenv
BOT_CHANNEL=telegram
TELEGRAM_BOT_TOKEN=<token dari BotFather>
TELEGRAM_BOT_USERNAME=<username_bot>
```

Lalu jalankan `php artisan config:clear`.

**3. Jalankan tiga proses** di terminal terpisah. `composer run dev` sudah menjalankan antrean.

```bash
php artisan lais:telegram            # menerima pesan
php artisan queue:work --timeout=150 # memproses dan membalas
php artisan schedule:work            # ringkasan malam jam 21.00 WIB
```

**4. Hubungkan akun.** Di aplikasi, buka **Pengaturan > Bot > Buat kode**, ketuk **Buka Telegram**, lalu tekan **Mulai**. Halaman berubah sendiri setelah terhubung. Dari perangkat lain, kirim saja `LAIS ######` ke bot.

Skenario demo ada di tabel "Mencoba skenario demo" di `docs/WHATSAPP.md`.

## Di VPS

- Di dalam container produksi, supervisor menjalankan `lais:telegram`, `queue:work`, dan `schedule:work` (`docker/supervisor/supervisord.d/`). Tidak perlu cron.
- Setiap deploy membuat container baru, jadi poller ikut mulai ulang dengan sendirinya. Jalankan satu replika saja.
- **Satu token hanya boleh satu poller.** Hentikan `lais:telegram` di laptop sebelum menyalakannya di server.

## Kalau bermasalah

- **Tidak ada balasan:** cek `lais:telegram` dan `queue:work` jalan, lalu cek tabel `failed_jobs`.
- **`getUpdates: 401`:** token salah. Periksa `.env`, lalu `php artisan config:clear` dan restart prosesnya.
- **`getUpdates: 409`:** ada poller lain untuk token yang sama.
- **Token bocor:** kirim `/revoke` ke @BotFather, lalu isi token baru di `.env`.
- **Foto dikirim sebagai file:** bot tidak membacanya. Kirim sebagai foto biasa.

## Kembali ke WhatsApp

1. Ikuti `docs/WHATSAPP.md`, lalu ganti `.env` menjadi `BOT_CHANNEL=whatsapp`.
2. Jalankan `php artisan config:clear`, restart `queue:work`, dan hentikan `lais:telegram`.
3. Hubungkan ulang akun di Pengaturan > Bot. Akun yang terhubung lewat Telegram harus diputuskan dulu.

Riwayat transaksi tetap tersimpan dengan sumbernya masing-masing.
