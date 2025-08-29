# Flowsent Backend - Dokumentasi Fitur

Dokumentasi ini berisi daftar fitur utama yang tersedia di backend Flowsent.  
Setiap fitur memiliki file markdown terpisah yang menjelaskan endpoint, fungsionalitas, serta catatan teknis.

## Daftar Fitur

-   [Auth](./docs/features/auth.md)
-   [Email Management](./docs/features/email-management.md)

---

## Catatan

-   Semua endpoint API berada di prefix `/api`
-   Sebagian besar fitur dilindungi oleh middleware `auth.token` (JWT)
-   Dokumentasi ini akan diperbarui seiring penambahan fitur baru
