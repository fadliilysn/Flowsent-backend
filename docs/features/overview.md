# Overview — FlowSent (React Webmail Client)

## Deskripsi Proyek

**FlowSent** adalah aplikasi **webmail client** berbasis **React (frontend)** dan **Laravel (backend)**.  
Tujuannya adalah memberikan pengalaman mirip layanan email populer (Gmail, Outlook), dengan fitur **mengirim, menerima, mengelola, dan mengorganisasi email** secara modern.

---

## Fitur Utama

- **Authentication**

  - Login dengan email & password (`/api/login`).
  - Logout user (`/api/logout`).
  - Proteksi route berbasis token.

- **Manajemen Email**

  - **Inbox**: daftar email masuk.
  - **Sent**: email terkirim.
  - **Drafts**: email tersimpan sementara.
  - **Starred**: email berbintang/ditandai penting.
  - **Archive**: email terarsip.
  - **Junk**: email spam.
  - **Trash**: email terhapus dengan opsi _Empty Trash_.

- **Email Detail**

  - Menampilkan header (From, To, Date, Subject).
  - Isi email (text / HTML).
  - Preview lampiran dengan **PreviewModal** (image, PDF, text).
  - Aksi toolbar (Reply, Forward, Archive, Delete, Download, Star, dll).

- **Bulk Actions**

  - Seleksi banyak email sekaligus.
  - Aksi massal: move to archive, move to trash, move to junk/spam, mark as read, mark as unread, delete, refresh.

- **Compose Email**

  - Modal Compose untuk menulis email baru.
  - Dukungan attachment.
  - Simpan ke draft.

- **UI & Experience**
  - Sidebar navigasi dengan **MainLayout**.
  - Topbar dengan pencarian global (integrasi `EmailContext`).
  - Notifikasi interaktif (`Notification`).
  - Dialog konfirmasi (`ConfirmDialog`).
  - Preview file lampiran (`PreviewModal`).
  - Indikator loading (`LoadingIcon`).

---

## Arsitektur Aplikasi

- **Frontend**: React + Vite.
- **Backend**: Laravel API.
- **State Management**: Context API (`EmailContext`) untuk email, pencarian, notifikasi, dan preview.
- **Routing**: React Router DOM.
- **UI Library**: Tailwind CSS + Lucide Icons.
- **API Communication**: Wrapper `services/api.js` menggunakan `fetch` dengan Bearer token.

---

## Alur Pengguna

1. **Login**

   - User diarahkan ke `/login`.
   - Input email & password → token disimpan di `localStorage`.
   - Redirect ke `/inbox`.

2. **Navigasi & Aksi**

   - Navigasi folder via sidebar (`Inbox`, `Sent`, `Drafts`, `Starred`, `Archive`, `Junk`, `Trash`).
   - Klik email → tampil detail.
   - Lakukan aksi:
     - Tandai bintang.
     - Archive / Delete.
     - Unduh / Preview attachment.
     - Bulk action.

3. **Compose**

   - Klik "Compose" → modal terbuka.
   - Tulis email, tambah lampiran.
   - Kirim atau simpan ke draft.

4. **Trash Management**

   - Email otomatis dihapus setelah 30 hari.
   - User bisa melakukan _Empty Trash_ manual.

5. **Logout**
   - Token dihapus.
   - Redirect ke `/login`.

---

## Integrasi API

Semua endpoint didokumentasikan di [`api/endpoints.md`](../api/endpoints.md). Beberapa yang utama:

- **Auth**

  - `POST /api/login` — login user.
  - `POST /api/logout` — logout user.

- **Email**
  - `GET /api/emails/all` — ambil semua email (dipisahkan per folder oleh `EmailContext`).
  - `POST /api/emails/send` — kirim email baru.
  - `POST /api/emails/draft` — simpan draft.
  - `POST /api/emails/move` — pindahkan email ke folder lain.
  - `DELETE /api/emails/:id` — hapus permanen email.
  - `PATCH /api/emails/:id/star` — tandai / hapus tanda bintang.
  - `PATCH /api/emails/:id/read` — tandai sebagai dibaca.

> Detail lengkap endpoint + contoh request/response: lihat [`api/endpoints.md`](../api/endpoints.md).

---
