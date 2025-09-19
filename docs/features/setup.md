# Setup Guide - Flowsent Backend

Panduan ini menjelaskan cara menyiapkan environment dan menjalankan project Flowsent Backend.

---

## 1. Prasyarat
Sebelum memulai, pastikan sudah menginstall:
- PHP >= 8.3.x
- Composer
- Laravel CLI
- Git
- WSL Ubuntu (opsional)

---

## 2. Clone Repository
```bash
git clone -b fadli git@source.kirim.email:internship/flowsent-back.git (-b untuk clone langsung branch fadli)
cd nama-project
composer install
php artisan serve
docker compose up -d --build redis

