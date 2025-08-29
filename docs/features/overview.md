# Flowsent Backend - Overview

## Deskripsi
Flowsent Backend adalah backend service untuk aplikasi webmail client **Flowsent**.  
Backend ini bertugas sebagai jembatan antara frontend (React) dengan server email (IMAP/SMTP).  

Fitur utama meliputi:
- Autentikasi user dengan JWT
- Manajemen email (inbox, sent, draft, junk, delete)
- Mengirim email

---

## Tujuan
- Memberikan API terstruktur untuk aplikasi webmail Flowsent
- Memudahkan tim frontend dalam mengakses data email
- Menyediakan layer keamanan dengan JWT
- Menjadi service modular yang bisa dikembangkan lebih lanjut

---

## Arsitektur
- **Framework:** Laravel 12 (PHP 8+)
- **Auth:** JWT (via middleware `auth.token`)
- **Email Service:** Webklex/IMAP (komunikasi dengan server email), SMTP (Protokol standar untuk mengirim email lewat jaringan (internet))
- **Frontend Consumer:** React.js + Tailwind (proyek terpisah)

---

## Struktur Folder Penting
- `app/Http/Controllers/` → Controller utama (Auth, Email, Folder)
- `app/Services/` → Service logic (EmailService, dll.)
- `routes/api.php` → Definisi endpoint API
- `config/` → Konfigurasi (JWT, IMAP, SMTP, dll.)
- `docs/` → Dokumentasi (fitur, setup, overview)

---

## Flow Singkat
1. User login melalui endpoint `/api/login`
2. Backend menghasilkan JWT token
3. Token digunakan untuk request API selanjutnya
4. Backend mengambil email dari server IMAP via Webklex/IMAP
5. Response dikirim ke frontend React dalam bentuk JSON

---

## Daftar Fitur
- [Auth](./auth.md)
- [Email Management](./email-management.md)

---

## Status
- Auth: ✅
- Email Management: ✅ (basic fetch email)

