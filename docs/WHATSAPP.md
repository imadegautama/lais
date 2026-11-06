# WhatsApp lewat GOWA

> Saat ini bot berjalan di Telegram (`docs/TELEGRAM.md`) karena nomor WhatsApp lewat GOWA berisiko diblokir. Panduan ini berlaku kalau `BOT_CHANNEL=whatsapp`.

Bot WhatsApp Lais memakai [GOWA (go-whatsapp-web-multidevice)](https://github.com/aldinokemal/go-whatsapp-web-multidevice).
Satu nomor WA cadangan menjadi nomor bot; pemilik usaha chat ke nomor itu dari WA-nya sendiri.

## Alur

1. Pemilik mengirim pesan ke nomor bot.
2. GOWA meneruskannya ke `POST /webhooks/gowa`, ditandatangani HMAC-SHA256 (`X-Hub-Signature-256`).
3. `GowaWebhookController` memeriksa tanda tangan, menyimpan pesan ke `wa_messages` (sekali per id pesan), lalu langsung membalas 204.
4. Job `ProcessWhatsAppMessage` (antrean) menjalankan `App\Services\WhatsApp\Inbox`:
    - **Tanpa AI:** kode `LAIS ######` (menghubungkan nomor), perintah pesanan (`terima A012`, `tolak A012 alasan`, `A012 siap`, `A012 selesai`, `batal A012`, `A012 dp 20rb qris`, `A012 lunas`), dan `1` / `2` (simpan / batalkan draf).
    - **Selain itu** dibaca `FormAssistant` (otak yang sama dengan Tanya AI di web), termasuk foto nota.
5. Balasan dikirim job `SendWhatsApp` lewat `POST /send/message` GOWA.

Bot hanya membalas pemilik yang nomornya sudah terhubung. Nomor lain dibalas sekali per 24 jam dengan link pendaftaran, dan bot tidak pernah mengirim pesan ke pelanggan.

## Menjalankan di laptop

**Syarat:** Docker, satu nomor WA cadangan untuk bot, dan aplikasi Lais berjalan di `127.0.0.1:8002`.

**1. Isi `.env` Laravel.** `GOWA_BASIC_AUTH` dan `GOWA_WEBHOOK_SECRET` sudah berisi nilai acak. Kalau belum ada, buat sendiri: password dan secret masing-masing minimal 32 karakter acak, tidak pernah di-commit.

```dotenv
BOT_CHANNEL=whatsapp
GOWA_URL=http://127.0.0.1:3000
GOWA_BASIC_AUTH=lais:<password-acak>
GOWA_DEVICE_ID=lais
GOWA_WEBHOOK_SECRET=<secret-acak>
GOWA_WEBHOOK_URL=http://host.docker.internal:8002/webhooks/gowa
GOWA_BOT_NUMBER=
```

**2. Jalankan GOWA.** Rahasianya diambil dari `.env` yang sama.

```bash
docker compose --env-file .env -f docker/gowa/compose.yaml up -d
```

**3. Pasangkan nomor bot.**

- Buka http://localhost:3000 dengan user dan password dari `GOWA_BASIC_AUTH`.
- Buat perangkat dengan id `lais` (sama dengan `GOWA_DEVICE_ID`), lalu pindai QR-nya dari WA nomor bot (Perangkat tertaut > Tautkan perangkat).
- Lewat API:

```bash
curl -u "$GOWA_BASIC_AUTH" -X POST http://localhost:3000/devices -H 'Content-Type: application/json' -d '{"device_id":"lais"}'
curl -u "$GOWA_BASIC_AUTH" http://localhost:3000/devices/lais/login   # qr_link: buka lalu pindai
```

**4. Isi `GOWA_BOT_NUMBER`** dengan nomor bot berformat `62…`, lalu `php artisan config:clear`.

**5. Jalankan antrean dan penjadwal** di terminal terpisah. `composer run dev` sudah menjalankan `queue:listen`.

```bash
php artisan queue:work --timeout=150
php artisan schedule:work            # ringkasan malam jam 21.00 WIB
```

**6. Hubungkan nomor pemilik.** Di aplikasi, buka **Pengaturan > Bot > Buat kode**, lalu ketuk **Buka WhatsApp** dari HP pemilik dan kirim pesannya. Halaman berubah sendiri setelah terhubung.

## Mencoba skenario demo

| Kirim ke bot                                       | Hasil                                                                          |
| -------------------------------------------------- | ------------------------------------------------------------------------------ |
| `jual 3 nasgor 1 aqua qris`, lalu `1`              | Penjualan tersimpan, stok bahan berkurang sesuai resep                         |
| (pelanggan pesan di `/t/{slug}`)                   | Bot mengabari pesanan baru                                                     |
| `terima A001`, `A001 dp 20rb qris`, `A001 selesai` | Status dan pembukuan berubah; sisa jadi piutang. Ada link `wa.me` ke pelanggan |
| `ambil 200rb buat rumah`, lalu `1`                 | Prive tercatat; laba tetap                                                     |
| `kenapa laba naik tapi kas turun?`                 | Jawaban dari rekonsiliasi laba vs kas bulan ini                                |
| foto nota belanja, lalu `1`                        | Belanja tersimpan, fotonya jadi bukti transaksi                                |

Ringkasan malam bisa dikirim kapan saja untuk demo:

```bash
php artisan lais:ringkasan --usaha=<id usaha>
```

## Di VPS

- GOWA ikut di `docker compose` server, dengan port 3000 hanya di `127.0.0.1` (sudah begitu di `compose.yaml`).
- `GOWA_WEBHOOK_URL=https://<domain>/webhooks/gowa`.
- Volume `gowa_storages` (sesi WA) wajib tetap ada; kalau hilang, nomor bot harus dipasangkan ulang.
- Worker antrean dan cron `* * * * * php artisan schedule:run` dijalankan oleh supervisor/cron server.

## Kalau bermasalah

- **Tidak ada balasan:**
    - cek `php artisan queue:work` jalan
    - cek tabel `failed_jobs`
    - cek `GET http://localhost:3000/devices/lais/status` (`is_logged_in: true`)
- **Webhook 401:** `GOWA_WEBHOOK_SECRET` di `.env` dan di GOWA berbeda. Jalankan ulang `docker compose … up -d` setelah mengubah `.env`.
- **GOWA tidak bisa menjangkau aplikasi:** jalankan `php artisan serve --host=0.0.0.0 --port=8002`, atau ganti `GOWA_WEBHOOK_URL` ke IP laptop.
- **Nomor bot logout:** pindai ulang QR di langkah 3. Draf yang menunggu kedaluwarsa sendiri setelah 15 menit.
