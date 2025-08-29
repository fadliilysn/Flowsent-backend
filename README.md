# Flowsent Backend - Dokumentasi Fitur

Dokumentasi ini berisi daftar fitur utama yang tersedia di backend Flowsent.  
Setiap fitur memiliki file markdown terpisah yang menjelaskan endpoint, fungsionalitas, serta catatan teknis.

## Struktur

-   [Setup](./docs/features/setup.md) — Cara menjalankan project (lokal).
-   [Overview](./docs/features/overview.md) — Ringkasan progress project saat ini.


## Daftar fitur
-   [Authentication](./docs/features/auth.md)
-   [Email Management](./docs/features/email-management.md)

---

## Catatan

-   Semua endpoint API berada di prefix `/api`
-   Sebagian besar fitur dilindungi oleh middleware `auth.token` (JWT)
-   Dokumentasi ini akan diperbarui seiring penambahan fitur baru
