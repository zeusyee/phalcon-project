# 📶 WiFi Password Generator - Phalcon PHP

Sistem Generate Password WiFi Otomatis untuk User Baru menggunakan Phalcon PHP Framework dengan Docker.

## 📋 Persyaratan

- Docker Desktop (sudah terinstall & running)

---

## 🚀 Cara Menjalankan Project

### 1. Buat File .env

Jalankan di PowerShell:

```powershell
@"
APP_ENV=development
APP_DEBUG=true

DB_HOST=mysql
DB_PORT=3306
DB_NAME=phalcon_db
DB_USER=phalcon_user
DB_PASSWORD=phalcon_password

NGINX_PORT=8080
PHPMYADMIN_PORT=8081
"@ | Out-File -FilePath .env -Encoding UTF8
```

### 2. Jalankan Docker

```powershell
docker-compose up -d
```

Tunggu 30-60 detik untuk MySQL siap.

### 3. Import Database

**Cara 1: Via PowerShell (Cepat)**
```powershell
Get-Content database/schema.sql | docker exec -i phalcon_mysql mysql -u phalcon_user -pphalcon_password phalcon_db
```

**Cara 2: Via phpMyAdmin (Mudah)**
1. Buka http://localhost:8081
2. Login → Username: `phalcon_user`, Password: `phalcon_password`
3. Klik database `phalcon_db`
4. Tab "Import" → Pilih file `database/schema.sql` → Import

### 4. Akses Aplikasi

| URL | Fungsi |
|-----|--------|
| http://localhost:8080 | Homepage |
| http://localhost:8080/wifi | Generate Password WiFi |
| http://localhost:8080/wifi/history | Riwayat Password |
| http://localhost:8081 | phpMyAdmin |

---

## 🔧 Perintah Docker

### Lihat Status
```powershell
docker-compose ps
```

### Lihat Log
```powershell
docker-compose logs -f
```

### Stop/Start/Restart
```powershell
docker-compose stop
docker-compose start
docker-compose restart
```

### Hapus Container
```powershell
# Hapus container (data tetap ada)
docker-compose down

# Hapus semua termasuk data
docker-compose down -v
```

### Masuk ke Container
```powershell
# Container PHP
docker exec -it phalcon_app bash

# Container MySQL
docker exec -it phalcon_mysql bash
```

---

## 📊 Struktur Database

Tabel `wifi_passwords`:

| Field | Type | Keterangan |
|-------|------|------------|
| id | INT(11) | Primary Key |
| user_name | VARCHAR(100) | Nama user |
| password | VARCHAR(20) | Password WiFi |
| created_at | DATETIME | Waktu dibuat |
| expired_at | DATETIME | Waktu expired (+7 hari) |
| is_active | TINYINT(1) | Status: 1=aktif, 0=expired |

---

## ❓ Troubleshooting

### Port Sudah Digunakan
Ubah port di `.env`:
```env
NGINX_PORT=8090
PHPMYADMIN_PORT=8091
```
Lalu restart:
```powershell
docker-compose down
docker-compose up -d
```

### MySQL Belum Ready
Cek log:
```powershell
docker-compose logs mysql | Select-String "ready for connections"
```
Harus muncul 2 baris.

### Import SQL Gagal
Cek file ada:
```powershell
Test-Path database/schema.sql
```

### Aplikasi Error
Cek log:
```powershell
docker-compose logs -f app
docker-compose logs -f nginx
```

---

## 📁 Struktur Folder

```
app/
├── config/          # Konfigurasi (loader, router, services)
├── controllers/     # Controller (IndexController, WifiController)
├── models/          # Model database (WifiPassword)
└── views/           # Template HTML
database/            # File SQL schema
docker/              # Konfigurasi Docker
public/              # Entry point & assets (css, js, img)
```

---

## 📚 Dokumentasi

- [Phalcon Docs](https://docs.phalcon.io/)
- [Docker Docs](https://docs.docker.com/)

---
