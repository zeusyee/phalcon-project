# 📶 WiFi Password Generator - Phalcon PHP + Odoo ERP

Sistem Generate Password WiFi Otomatis untuk User Baru menggunakan Phalcon PHP Framework dengan integrasi Odoo ERP System, semuanya berjalan di Docker.

## ✨ Fitur

- 🔐 Generate Password WiFi Otomatis
- 📋 Manajemen History Password
- 🔗 **Integrasi Odoo ERP** (Baru!)
  - Customer Management
  - Product Management
  - Sales Orders
  - Invoices
  - Inventory Tracking
- 🐳 Full Docker Setup (Nginx, PHP, MySQL, Odoo, PostgreSQL)
- 📊 Database Management via PHPMyAdmin

## 📋 Persyaratan

- Docker Desktop (sudah terinstall & running)

---

## 🚀 Quick Start

### Opsi 1: Setup dengan Odoo (Recommended)

```powershell
# Clone atau extract project
# Jalankan script setup otomatis
.\setup-odoo.ps1
```

Script ini akan:
- Install dependencies
- Start semua containers (Phalcon + Odoo)
- Setup database
- Menampilkan informasi akses

### Opsi 2: Setup Manual

1. **Buat File .env**
```powershell
# File .env sudah ada, pastikan konfigurasi Odoo aktif
```

2. **Install Dependencies**
```powershell
docker-compose run --rm app composer install
```

3. **Start Containers**
```powershell
docker-compose up -d
```

4. **Import Database Phalcon**
```powershell
Get-Content database/schema.sql | docker exec -i phalcon_mysql mysql -u phalcon_user -pphalcon_password phalcon_db
```

5. **Setup Odoo Database**
- Buka http://localhost:8069
- Buat database baru:
  - Database Name: `odoo`
  - Email: `admin@example.com`
  - Password: `admin`
  - Language: Indonesian
  - Country: Indonesia

---

## 🌐 URL Akses

| Service | URL | Keterangan |
|---------|-----|------------|
| Phalcon App | http://localhost:8181 | Aplikasi utama |
| WiFi Generator | http://localhost:8181/wifi | Generate password WiFi |
| Odoo Dashboard | http://localhost:8181/odoo | Dashboard integrasi Odoo |
| Odoo Web | http://localhost:8069 | Odoo ERP System |
| PHPMyAdmin | http://localhost:8182 | MySQL Management |

---

## 🔗 Fitur Integrasi Odoo

### Test Connection
```
http://localhost:8181/odoo/test
```

### Available Endpoints
- `/odoo` - Dashboard & Customer List
- `/odoo/products` - Product List dari Odoo
- `/odoo/sales-orders` - Sales Orders
- `/odoo/invoices` - Invoice List
- `/odoo/inventory` - Stock Inventory
- `/odoo/create-customer` - Create Customer
- `/odoo/sync-wifi` - Sync WiFi data ke Odoo

### Test Script
```powershell
# Test koneksi Odoo
.\test-odoo.ps1
```

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

## 📚 Dokumentasi Lengkap

- **[ODOO_QUICKSTART.md](ODOO_QUICKSTART.md)** - Panduan cepat setup Odoo
- **[ODOO_INTEGRATION.md](ODOO_INTEGRATION.md)** - Dokumentasi lengkap integrasi Odoo
- **[ARCHITECTURE.md](ARCHITECTURE.md)** - Arsitektur sistem & network
- [Phalcon Docs](https://docs.phalcon.io/) - Dokumentasi Phalcon PHP
- [Odoo Docs](https://www.odoo.com/documentation) - Dokumentasi Odoo
- [Docker Docs](https://docs.docker.com/) - Dokumentasi Docker

---
