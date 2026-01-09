# Quick Deployment Commands

## STEP 1: SSH ke Server
```bash
ssh fdx@192.168.0.73
# Password: k2Zd2qS2j
```

## STEP 2: Di dalam server, jalankan command berikut:

```bash
# Masuk ke direktori dockerizer
cd /home/fdx/dockerizer/phalcon-wifi

# Buat file .env
cat > .env << 'EOF'
NGINX_PORT=8181
DB_NAME=phalcon_db
DB_USER=phalcon_user
DB_PASSWORD=phalcon_secure_2024
EOF

# Cek isi .env
cat .env

# Deploy dengan Docker Compose
docker-compose down
docker-compose up -d --build

# Cek status container
docker-compose ps

# Cek logs (optional)
docker-compose logs -f
```

## STEP 3: Import Database Schema (jika diperlukan)

```bash
# Import schema
docker exec -i phalcon_mysql mysql -uphalcon_user -pphalcon_secure_2024 phalcon_db < database/schema.sql

# Atau masuk ke MySQL secara interaktif
docker exec -it phalcon_mysql mysql -uphalcon_user -pphalcon_secure_2024 phalcon_db
```

## Akses Aplikasi
- **Web App**: http://192.168.0.73:8181
- **phpMyAdmin**: http://192.168.0.73:8182

## Update Code (untuk deployment berikutnya)

```bash
# Di server
cd /home/fdx/dockerizer/phalcon-wifi
git pull origin main
docker-compose down
docker-compose up -d --build
```

## Troubleshooting

**Cek logs container:**
```bash
docker-compose logs -f app
docker-compose logs -f nginx
docker-compose logs -f mysql
```

**Restart semua container:**
```bash
docker-compose restart
```

**Stop semua container:**
```bash
docker-compose down
```

**Remove semua dan rebuild:**
```bash
docker-compose down -v
docker-compose up -d --build
```

**Cek port yang digunakan:**
```bash
docker-compose ps
netstat -tulpn | grep 8181
```

**Masuk ke container:**
```bash
# App container
docker exec -it phalcon_app bash

# MySQL container
docker exec -it phalcon_mysql bash

# Nginx container
docker exec -it phalcon_nginx sh
```
