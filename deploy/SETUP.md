# Setmobile — Netcup Production Kurulumu

## Ön Gereksinimler

Netcup sunucunuzda Ubuntu 22.04 veya Debian 12 varsayılmaktadır.

---

## Adım 1 — Sunucu Paketleri

```bash
sudo apt update && sudo apt upgrade -y

# Nginx
sudo apt install -y nginx

# PHP 8.2 + FPM + PostgreSQL extension
sudo apt install -y php8.2 php8.2-fpm php8.2-pgsql php8.2-mbstring php8.2-xml php8.2-curl php8.2-zip

# Node.js 20 (LTS)
curl -fsSL https://deb.nodesource.com/setup_20.x | sudo -E bash -
sudo apt install -y nodejs

# PM2 (Node.js process manager)
sudo npm install -g pm2 tsx

# Docker (PostgreSQL için)
curl -fsSL https://get.docker.com | sudo sh
sudo usermod -aG docker $USER   # logout/login gerekir

# Certbot (Let's Encrypt SSL)
sudo apt install -y certbot python3-certbot-nginx
```

---

## Adım 2 — DNS Kayıtları

Netcup domain panelinden şu A kayıtlarını ekleyin (sunucu IP'niz ile):

```
sm1.setmobile.eu        A    <SUNUCU_IP>
driver.setmobile.eu     A    <SUNUCU_IP>
passenger.setmobile.eu  A    <SUNUCU_IP>
api.setmobile.eu        A    <SUNUCU_IP>
```

DNS yayılması ~5-30 dakika sürebilir. Kontrol: `nslookup sm1.setmobile.eu`

---

## Adım 3 — Projeyi Sunucuya Kopyalama

```bash
# Dizin oluştur
sudo mkdir -p /var/www/setmobile
sudo chown $USER:$USER /var/www/setmobile

# Git clone (ya da zip upload)
git clone https://github.com/KULLANICIAD/setmobile.git /var/www/setmobile
# VEYA: scp ile upload
# scp -r ./SetmobileApp/* user@SUNUCU_IP:/var/www/setmobile/
```

---

## Adım 4 — Environment Dosyası

```bash
cp /var/www/setmobile/deploy/.env.production.example /var/www/setmobile/.env
nano /var/www/setmobile/.env
```

Şu değerleri doldurun:
- `POSTGRES_PASSWORD` — güçlü bir şifre
- `DATABASE_URL` — aynı şifreyi içermeli
- `JWT_SECRET` — en az 32 karakter rastgele string (`openssl rand -hex 32`)

---

## Adım 5 — PostgreSQL'i Başlat

```bash
cd /var/www/setmobile
docker compose -f deploy/docker-compose.prod.yml --env-file .env up -d

# Sağlık kontrolü
docker ps
docker logs setmobile-db
```

---

## Adım 6 — Node Bağımlılıkları ve Build

```bash
cd /var/www/setmobile
npm install

# React driver app build
npx vite build --config vite.config.js

# Prisma setup
npx prisma generate
npx prisma db push

# İlk veri migrasyonu (sadece ilk kez)
npx tsx migrate.ts
```

---

## Adım 7 — Nginx Konfigürasyonu

```bash
# Nginx config dosyalarını kopyala
sudo cp /var/www/setmobile/deploy/nginx/sm1.setmobile.eu.conf       /etc/nginx/sites-available/
sudo cp /var/www/setmobile/deploy/nginx/driver.setmobile.eu.conf     /etc/nginx/sites-available/
sudo cp /var/www/setmobile/deploy/nginx/passenger.setmobile.eu.conf  /etc/nginx/sites-available/
sudo cp /var/www/setmobile/deploy/nginx/api.setmobile.eu.conf        /etc/nginx/sites-available/

# Symlink ile aktifleştir
sudo ln -s /etc/nginx/sites-available/sm1.setmobile.eu.conf       /etc/nginx/sites-enabled/
sudo ln -s /etc/nginx/sites-available/driver.setmobile.eu.conf     /etc/nginx/sites-enabled/
sudo ln -s /etc/nginx/sites-available/passenger.setmobile.eu.conf  /etc/nginx/sites-enabled/
sudo ln -s /etc/nginx/sites-available/api.setmobile.eu.conf        /etc/nginx/sites-enabled/

# Test ve başlat
sudo nginx -t
sudo systemctl reload nginx
```

---

## Adım 8 — SSL Sertifikaları (Let's Encrypt)

> DNS kayıtları yayılmadan önce bu adımı çalıştırmayın!

```bash
# Her subdomain için ayrı sertifika (önce HTTP çalışıyor olmalı)
sudo certbot --nginx -d sm1.setmobile.eu
sudo certbot --nginx -d driver.setmobile.eu
sudo certbot --nginx -d passenger.setmobile.eu
sudo certbot --nginx -d api.setmobile.eu

# Otomatik yenileme testi
sudo certbot renew --dry-run
```

---

## Adım 9 — API'yi PM2 ile Başlat

```bash
cd /var/www/setmobile

# PM2 log dizini
sudo mkdir -p /var/log/pm2
sudo chown $USER:$USER /var/log/pm2

# Başlat
pm2 start deploy/pm2.config.cjs

# Sunucu yeniden başlatmada otomatik çalışsın
pm2 save
pm2 startup   # Çıktısındaki komutu kopyalayıp çalıştırın (sudo ...)

# Kontrol
pm2 status
pm2 logs setmobile-api
```

---

## Adım 10 — PHP-FPM Ayarı

```bash
# PHP'nin pdo_pgsql extension'ını kontrol et
php -m | grep pdo_pgsql

# Eğer yoksa:
sudo phpenmod pdo_pgsql
sudo systemctl restart php8.2-fpm

# Test
sudo systemctl status php8.2-fpm
```

---

## Sonraki Deploylar

```bash
cd /var/www/setmobile
bash deploy/deploy.sh
```

---

## Sorun Giderme

```bash
# Nginx hata logu
sudo tail -f /var/log/nginx/error.log

# PHP-FPM logu
sudo tail -f /var/log/php8.2-fpm.log

# API logu
pm2 logs setmobile-api

# PostgreSQL
docker logs -f setmobile-db

# Nginx config test
sudo nginx -t
```

---

## Servis Durumu Özeti

| Servis      | Kontrol Komutu                          |
|-------------|----------------------------------------|
| Nginx       | `sudo systemctl status nginx`          |
| PHP-FPM     | `sudo systemctl status php8.2-fpm`     |
| PostgreSQL  | `docker ps && docker logs setmobile-db`|
| API (PM2)   | `pm2 status`                           |
