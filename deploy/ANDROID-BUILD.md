# Android APK / AAB Build Guide (Google Play Store)

Bu kılavuz, Driver uygulamasını Android APK/AAB olarak paketleyip Google Play Store'a yükleme adımlarını açıklar.

---

## Ön Gereksinimler

| Araç | Versiyon | İndirme |
|------|----------|---------|
| Node.js | 18+ | nodejs.org |
| Android Studio | Ladybug+ | developer.android.com/studio |
| JDK | 17+ | Android Studio ile birlikte gelir |

Android Studio'yu kurduktan sonra bir kez açıp "Android SDK" kurulumunu tamamlayın.

---

## Adım 1 — Bağımlılıkları Kur

```bash
npm install
```

Capacitor paketleri otomatik yüklenir (`@capacitor/core`, `@capacitor/android`, vb.).

---

## Adım 2 — Android Projesini Oluştur (ilk kez)

```bash
npx cap add android
```

Bu komut kök dizinde `android/` klasörü oluşturur. Klasörü `.gitignore`'a **eklemeyin** — imzalama konfigürasyonu burada kalır.

---

## Adım 3 — Build Al ve Sync Et

```bash
npm run cap:sync
```

Bu komut sırayla şunları yapar:
1. `vite build --config vite.config.js` → `dist/driver/` çıktısı
2. `npx cap sync android` → web dosyalarını `android/app/src/main/assets/public/` içine kopyalar

---

## Adım 4 — Android Studio'yu Aç

```bash
npm run cap:open
```

Android Studio açılır ve `android/` projesini yükler.

---

## Adım 5 — Uygulama İkonu ve Splash Screen

`deploy/android/` klasörüne şu dosyaları koy:
- `icon.png` — 1024×1024 px
- `icon-foreground.png` — 1024×1024 px (içerik ortada 500×500 px'de kalacak şekilde)
- `splash.png` — 2732×2732 px

Sonra çalıştır:
```bash
npx @capacitor/assets generate --android
```

---

## Adım 6 — İmzalama Keystore Oluştur (ilk kez)

```bash
keytool -genkeypair -v \
  -keystore deploy/android/setmobile-release.keystore \
  -alias setmobile \
  -keyalg RSA -keysize 2048 \
  -validity 10000
```

Şifreleri güvenli bir yerde sakla. Keystore'u kaybedersen Play Store güncellemesi yapılamaz.

---

## Adım 7 — Android Studio'da Signed AAB Oluştur

1. Android Studio → **Build** → **Generate Signed Bundle / APK**
2. **Android App Bundle** seç → Next
3. Keystore: `deploy/android/setmobile-release.keystore` seç
4. Alias: `setmobile`, şifreleri gir
5. Build variant: **release** → Finish
6. Çıktı: `android/app/release/app-release.aab`

### Alternatif — Komut satırından build (CI/CD için)

```bash
cd android
./gradlew bundleRelease \
  -Pandroid.injected.signing.store.file=../deploy/android/setmobile-release.keystore \
  -Pandroid.injected.signing.store.password=KEYSTORE_PASS \
  -Pandroid.injected.signing.key.alias=setmobile \
  -Pandroid.injected.signing.key.password=KEY_PASS
```

---

## Adım 8 — Google Play Console'a Yükle

1. [play.google.com/console](https://play.google.com/console) → Yeni uygulama oluştur
2. App bilgileri: isim, kategori, içerik derecelendirmesi
3. **Production** → **Create new release** → AAB dosyasını yükle
4. Store listeleme: açıklama, ekran görüntüleri, uygulama ikonu (512×512 px)
5. İncelemeye gönder (genellikle 1-3 gün)

---

## Güncelleme Yayınlama

Kod değişikliğinde:
```bash
# 1. versionCode'u artır (android/app/build.gradle içinde)
# 2. Build ve sync
npm run cap:sync
# 3. Android Studio'da yeni signed AAB oluştur
# 4. Play Console'da yeni release oluştur
```

`android/app/build.gradle` içindeki `versionCode` her release'de **mutlaka** artmalıdır, aksi halde Play Store reddeder.

---

## Uygulama ID

`eu.setmobile.driver` — Play Store'da benzersiz tanımlayıcı, hiç değiştirilemez.

---

## Sorun Giderme

**`SDK location not found`** → Android Studio → SDK Manager'dan SDK yolunu belirle, `local.properties` dosyasına ekle:
```
sdk.dir=/Users/KULLANICI/Library/Android/sdk
```

**API 405 / network hatası** → Uygulamanın `https://driver.setmobile.eu/api` adresine erişebildiğini kontrol et. Native platformda HTTPS zorunludur.

**`INSTALL_FAILED_UPDATE_INCOMPATIBLE`** → Cihazdaki eski test APK'yı kaldır, tekrar yükle.
