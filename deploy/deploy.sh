#!/bin/bash
##
## deploy.sh — Setmobile Production Deploy Script
## Çalıştırma: bash deploy/deploy.sh
##
set -e

APP_DIR="/var/www/setmobile"
LOG_PREFIX="[deploy]"

echo "$LOG_PREFIX ── Setmobile Deploy Başlıyor ──────────────────────────"
cd "$APP_DIR"

# 1. Git pull
echo "$LOG_PREFIX git pull..."
git pull origin main

# 2. Bağımlılıkları yükle
echo "$LOG_PREFIX npm install..."
npm install --omit=dev

# 3. React driver app build
echo "$LOG_PREFIX Building driver app..."
npx vite build --config vite.config.js

# 4. React passenger app build (eğer varsa)
if [ -f "vite.config.passenger.js" ]; then
    echo "$LOG_PREFIX Building passenger app..."
    npx vite build --config vite.config.passenger.js
fi

# 5. Prisma client üret
echo "$LOG_PREFIX Prisma generate..."
npx prisma generate

# 6. DB schema senkronize et (yeni kolonlar/tablolar)
echo "$LOG_PREFIX Prisma db push..."
npx prisma db push --accept-data-loss

# 7. PM2 restart
echo "$LOG_PREFIX Restarting API via PM2..."
pm2 restart setmobile-api 2>/dev/null || pm2 start deploy/pm2.config.cjs

# 8. Nginx reload
echo "$LOG_PREFIX Nginx reload..."
sudo nginx -t && sudo systemctl reload nginx

echo "$LOG_PREFIX ── Deploy tamamlandı! ───────────────────────────────────"
pm2 status setmobile-api
