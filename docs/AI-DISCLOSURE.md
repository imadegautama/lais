# AI & Technology Disclosure: Lais

Dokumen ini mengikuti Lampiran C Panduan Peserta AI Web Innovation Challenge, Bali Digifest 2026. Tim: **ekonomis**, Universitas Primakara.

| Komponen                     | Isi                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                           |
| ---------------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Nama Produk                  | Lais: pembukuan, stok, pesanan, dan toko online untuk UMKM, dengan asisten AI yang bisa diajak chat                                                                                                                                                                                                                                                                                                                                                                                                                                           |
| Framework / Frontend         | React 19, Inertia 3, TypeScript, Tailwind CSS 4, komponen Radix UI (shadcn/ui), PWA                                                                                                                                                                                                                                                                                                                                                                                                                                                           |
| Backend / Runtime            | PHP 8.4, Laravel 13, antrean database dan penjadwal Laravel, bot Telegram dengan long polling                                                                                                                                                                                                                                                                                                                                                                                                                                                 |
| Database / Storage           | MySQL 8. File di Cloudflare R2: bucket privat untuk bukti transaksi, bucket publik untuk foto produk dan QRIS                                                                                                                                                                                                                                                                                                                                                                                                                                 |
| Hosting / Deployment         | Image Docker dibangun GitHub Actions, disimpan di Docker Hub, dan dijalankan Coolify di VPS dengan HTTPS. URL: https://lais.web.id                                                                                                                                                                                                                                                                                                                                                                                                            |
| AI Model / API / Method      | Claude Haiku 5.5 (`claude-haiku-5-5`) lewat Claude API dengan SDK resmi `anthropic-ai/sdk` untuk PHP. Metodenya tool calling: model hanya memilih satu fungsi yang sudah disediakan. Untuk foto nota, model yang sama membaca gambar (vision) lewat satu fungsi `tulis_nota`. Semua panggilan lewat `App\Services\Ai\LlmClient`                                                                                                                                                                                                               |
| Peran AI dalam Produk        | (1) Mengubah kalimat bebas atau foto nota menjadi draf transaksi atau produk baru. (2) Menjawab pertanyaan pemilik tentang usahanya dari angka pembukuan. (3) Menjelaskan cara mencatat, arti istilah akuntansi, dan layak tidaknya sebuah produk, dari teks dan aturan tetap. Model tidak menghitung angka dan tidak bisa menyimpan data; draf disimpan hanya setelah pemilik menekan Simpan                                                                                                                                                 |
| Dataset / Data Source        | Tidak ada pelatihan model dan tidak ada dataset luar. Data demo (Warung Bu Rina, 90 hari) adalah data sintetis buatan seeder (`database/seeders`), tanpa data pribadi nyata. Kasus eval AI (`tests/Feature/AssistantEvalTest.php`) ditulis tim sendiri                                                                                                                                                                                                                                                                                        |
| Open-source Libraries        | Daftar lengkap beserta versi dan lisensi ada di [README, bagian Library open-source](../README.md#library-open-source). Utamanya Laravel, Inertia, React, Tailwind CSS, Radix UI, TanStack Table, Recharts, React Joyride, spatie/laravel-activitylog, minishlink/web-push, dan anthropic-ai/sdk (MIT, ISC, Apache-2.0)                                                                                                                                                                                                                       |
| Template / Boilerplate       | Laravel React Starter Kit resmi (MIT) sebagai titik awal: login, layout dasar, dan komponen shadcn/ui. Semua fitur Lais (pembukuan, stok, pesanan, toko, AI, bot, laporan) ditulis di atasnya                                                                                                                                                                                                                                                                                                                                                 |
| AI Coding Assistant          | Claude Code (Anthropic) dipakai sebagai asisten pengembangan untuk menulis dan meninjau kode, tes, dan dokumentasi. Selama pengembangan, model Gemini lewat gateway 9Router juga dipakai untuk mencoba asisten di lokal (driver `gateway`), tetapi tidak dipakai di produksi. Setiap perubahan dibaca, diuji, dan diputuskan oleh pengembang; keputusan produk dan aturan akuntansi berasal dari tim                                                                                                                                          |
| Pre-existing Components      | Lais dibangun mulai 17 Oktober 2026 (commit pertama) oleh I Made Gautama untuk Garda Tech National Innovation Competition (GTNIC) 2026, kategori Web Development, sebagai tim satu orang, dengan batas pengumpulan 10 November 2026. Desak Putu Nitya Dewi memberi masukan kebutuhan pengguna dan aturan pembukuan sejak fase itu, lalu bergabung sebagai anggota tim untuk lomba ini. Untuk Tahap 1 lomba ini, yang ditambahkan hanya dokumentasi: bagian README yang diminta panduan dan dokumen ini. Riwayat lengkap ada di riwayat commit |
| Third-party Assets / Credits | Claude API (Anthropic), Telegram Bot API, DOKU Checkout (sandbox), Cloudflare R2, Google Sign-In. Font Plus Jakarta Sans (SIL OFL 1.1, lewat Bunny Fonts). Empat foto usaha di landing page dari Wikimedia Commons (CC BY-SA 3.0/4.0), dengan kredit di dekat setiap fotonya                                                                                                                                                                                                                                                                  |

## Data yang dikirim ke model

- **Yang dikirim:**
    - kalimat yang diketik pemilik
    - nama dan harga produk di katalog usaha itu
    - nama kontak beserta jenisnya (pelanggan atau supplier), tanpa nomor HP
    - nama rekening, tanggal dan jam, serta draf yang sedang terbuka
- **Untuk pertanyaan usaha**, model membaca lembar fakta yang dihitung dari jurnal (`App\Services\BusinessFacts`). Nomor HP dan foto tidak masuk lembar itu.
- **Foto nota** dikirim sekali untuk dibaca lalu tidak disimpan. Gambar diperiksa dulu: harus JPG, PNG, atau WebP sungguhan, maksimal 5 MB.
- **Kunci API** hanya ada di environment server, tidak pernah ditulis ke log maupun ke repositori. Lihat `.env.example` untuk nama variabelnya.

## Menjaga output AI

- Argumen dari model diperlakukan seperti input pengguna:
    - nilai pilihan yang tidak dikenal dibuang
    - nama produk, kontak, dan rekening dicocokkan hanya dengan data usaha itu sendiri
    - draf divalidasi dengan aturan yang sama dengan form web (`FormRequest`)
- Barang yang tidak ada di katalog tidak dibuang diam-diam. Barang itu memblokir simpan sampai pemilik memutuskan.
- Jawaban bebas atas pertanyaan usaha ditolak kalau memuat angka yang tidak ada di lembar fakta (`BusinessFacts::grounded()`).
- Saran pencatatan, arti istilah, dan penilaian produk berasal dari teks dan aturan tetap di kode (`App\Enums\GuideTopic`, `Term`, `MarginLevel`). Model hanya memilih topiknya.
- Akurasi diukur dengan eval terhadap model sungguhan, 37 kasus (`tests/Feature/AssistantEvalTest.php`). Pada 9 November 2026 eval dijalankan 10 kali, dan semua putaran 100% benar untuk jenis permintaan maupun isian draf.

## Kalau AI atau API gagal

Model yang tidak terjangkau, kehabisan waktu, atau menjawab tanpa memilih fungsi menghasilkan `App\Services\Ai\AssistantUnavailable`. Hal yang sama berlaku saat kuota harian usaha (200 panggilan model) habis.

- Di web, pemilik melihat pesan "AI sedang tidak bisa dipakai. Isi form secara manual dulu, atau coba lagi sebentar lagi."
- Di bot, pemilik diminta mengirim ulang atau mencatat lewat web.

Tidak ada data yang tersimpan dari permintaan yang gagal, dan semua form manual tetap berjalan tanpa AI.

## Pernyataan

Kami menyatakan bahwa informasi di atas benar dan lengkap. Kami bertanggung jawab atas penggunaan teknologi, source code, data, library, API, model AI, dan aset yang digunakan pada produk.

I Made Gautama (ketua) dan Desak Putu Nitya Dewi, S.E., M.Si., Universitas Primakara.
