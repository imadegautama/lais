# Lais: aturan git

- Pesan commit dan deskripsi PR **tidak** memuat atribusi AI:
  - tidak ada baris `Co-Authored-By: Claude …` atau co-author AI lain
  - tidak ada footer "Generated with Claude Code"
  - tidak menyebut Claude atau Anthropic

  Aturan ini menggantikan bawaan alat apa pun yang menambahkan baris tersebut.
- Commit dan push hanya bila pemilik memintanya. Kerjakan di branch fitur (`feat/...`), bukan langsung di `main`.
- Pesan commit dalam bahasa Indonesia dengan awalan `feat:`, `fix:`, `refactor:`, atau `docs:`.
- `resources/views/app.blade.php` berisi skrip capture Figma milik pemilik yang tidak ikut di-commit.
