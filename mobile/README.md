# Asansör Saha — Mobil Uygulama

Teknisyen odaklı saha uygulaması (Flutter, ADR-003). Yönetim panelinin kopyası
değildir; günlük görev listesi, iş emri detayı ve durum güncelleme akışlarına
odaklanır (`SOLUTION_ARCHITECTURE.md` §11).

## Kurulum

Bu dizin `flutter create` çıktısındaki platform klasörleri (`android/`, `ios/`)
**olmadan** commit'lenmiştir. İlk kurulumda üretin:

```bash
cd mobile
flutter create --platforms=android,ios --org com.asansor --project-name asansor_mobile .
flutter pub get
```

`flutter create .` var olan dosyaları (özellikle `lib/`) ezmez, yalnızca eksik
platform iskeletini ekler.

## Çalıştırma

Backend'i başlatın (repo kökünden `docker compose up -d` veya `backend/` içinden
`php artisan serve`), sonra:

```bash
# Android emülatör (10.0.2.2 host makineye işaret eder — varsayılan budur)
flutter run

# Gerçek cihaz: makinenizin LAN IP'sini geçin
flutter run --dart-define=API_BASE_URL=http://192.168.1.20:8000/api/v1
```

## Mimari

```
lib/
├── main.dart / app.dart / router.dart   # giriş, MaterialApp.router, go_router + auth redirect
├── core/
│   ├── config/       # AppConfig (--dart-define ile API_BASE_URL)
│   ├── network/      # ApiClient (Dio), ApiException, Paginated — §12 zarf sözleşmesi
│   ├── storage/      # TokenStorage (flutter_secure_storage)
│   └── theme/        # Material 3 tema
└── features/         # feature başına data / domain / presentation
    ├── auth/         # login, me, logout — Sanctum Bearer token
    └── work_orders/  # liste, detay, durum geçişi (assigned → in_progress → completed)
```

Kararlar:

- **State**: Riverpod (`flutter_riverpod`). Codegen'siz (build_runner bağımlılığı
  bilinçli olarak yok); JSON eşlemeleri elle yazılmış `fromJson` factory'leri.
- **HTTP**: Dio + interceptor. Token her isteğe `Authorization: Bearer` olarak
  eklenir; 401'de token silinir ve router login'e yönlendirir.
- **API sözleşmesi**: Tüm yanıtlar backend `ApiResponse` zarfıyla uyumlu parse
  edilir: başarıda `{success, data, message, meta}`, hatada
  `{success, message, error: {code, details}}` → `ApiException`.
- **Navigasyon**: go_router; `redirect` auth durumuna bakar
  (yükleniyor → splash, oturumsuz → login).

## Sonraki fazlar (§11 kapsamı, henüz yok)

- Offline kayıt kuyruğu ve senkronizasyon (çakışma stratejisiyle)
- Bakım kontrol listeleri (`PATCH /work-orders/{wo}/checklist-items/{item}` ucu hazır)
- Malzeme kullanımı (`/work-orders/{wo}/items` uçları hazır)
- Fotoğraf/belge yükleme (sıkıştırma + arka plan retry)
- Dijital imza, konum doğrulama, push bildirim (Reverb)
