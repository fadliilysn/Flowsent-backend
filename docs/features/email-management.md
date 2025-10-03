# Email Management Feature

## Deskripsi

Fitur untuk mengelola email yang terhubung ke server IMAP/SMTP.  
Mencakup pengambilan daftar email, filtering berdasarkan folder, manajemen status, draft, pengiriman, dan attachment.

## Tujuan

-   Menyediakan API agar frontend (React) bisa menampilkan email user
-   Memudahkan navigasi email berdasarkan kategori
-   Menjadi pondasi utama webmail client Flowsent

## Endpoint

- `GET /emails/all` : Mendapatkan semua email
- `POST /emails/mark-as-read` : Tandai email sebagai sudah dibaca
- `POST /emails/flag` : Tandai email sebagai penting (flagged)
- `POST /emails/unflag` : Hapus tanda penting (unflag)
- `POST /emails/move` : Pindahkan email antar folder
- `DELETE /emails/delete-permanent-all` : Hapus permanen semua email dalam folder
- `POST /emails/draft` : Simpan email sebagai draft
- `POST /emails/send` : Kirim email baru
- `GET /emails/attachments/{uid}/download/{filename}` : Unduh attachment
- `GET /emails/attachments/{uid}/preview/{filename}` : Preview attachment

## Fungsionalitas

- [x] Menampilkan semua email
- [x] Ambil daftar email dari berbagai folder
- [x] Ambil detail email berdasarkan folder & UID
- [x] Mendukung pagination/limit default (contoh: 20 email terbaru)
- [x] Tandai email (read/unread, flagged/unflagged)
- [x] Pindahkan email antar folder
- [x] Kirim email
- [x] Simpan draft email
- [x] Unduh & preview attachment
- [x] Hapus permanen email

## Alur Singkat

1. User login → dapat JWT token
2. Frontend request email → API mengambil dari IMAP server
3. API response berupa JSON list email

## Catatan Teknis

-   Menggunakan package `Webklex/IMAP` untuk komunikasi dengan server email
-   Endpoint dilindungi oleh middleware `auth.token`
-   Default limit = 20 email terbaru

## Screenshot

-   Tampilan output endpoint GET /emails/all

    > ![Output GET all](../screenshots/api-emails-all.png "Output fetch all email")

-   Tampilan output endpoint GET /emails/mark-as-read

    > ![Output POST mark-as-read](../screenshots/api-emails-mark-as-read.png "Output email pada bagian mark as read")

-   Tampilan output endpoint Get /emails/flag

    > ![Output POST flag](../screenshots/api-emails-flag.png "Output email pada bagian flag")

-   Tampilan output endpoint Get /emails/unflag

    > ![Output Post unflag](../screenshots/api-emails-unflag.png "Output email pada bagian unflag")

-   Tampilan output endpoint Get /emails/download

    > ![Output Post download](../screenshots/api-emails-download.png "Output email pada bagian download")

-   Tampilan output endpoint Get /emails/preview

    > ![Output Post preview](../screenshots/api-emails-preview.png "Output email pada bagian preview")

-   Tampilan output endpoint POST /emails/send

    > ![Output GET send](../screenshots/api-emails-send.png "Output email pada bagian send")
