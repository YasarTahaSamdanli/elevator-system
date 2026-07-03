# Asansor Bakim ve Servis Yonetim Sistemi

Bu depo, ticari olarak gelistirilecek Asansor Bakim ve Servis Yonetim Sistemi icin profesyonel bir monorepo iskeleti olarak hazirlanmistir.

Bu asamada herhangi bir framework, uygulama kodu, veritabani semasi veya is mantigi eklenmemistir. Depo yalnizca uzun vadede backend, web, mobil, Docker ve operasyonel dokumantasyon katmanlarini tasiyabilecek sekilde duzenlenmistir.

## Amac

Sistem; asansor bakim sozlesmeleri, periyodik servis planlari, ariza kayitlari, saha ekipleri, musteri/tesis yonetimi, raporlama ve ticari operasyon sureclerini kapsayabilecek bir urun ailesi olarak ele alinacaktir.

## Monorepo Yapisi

```text
.
├── backend/
├── docker/
├── docs/
│   ├── adr/
│   ├── api/
│   ├── architecture/
│   ├── operations/
│   ├── product/
│   ├── requirements/
│   ├── security/
│   └── testing/
├── mobile/
├── scripts/
├── web/
├── .gitignore
├── README.md
└── SOLUTION_ARCHITECTURE.md
```

## Klasorlerin Rolü

- `backend/`: Gelecekte API, domain servisleri ve sunucu tarafi uygulamalar icin ayrilmistir.
- `web/`: Gelecekte yonetim paneli veya web istemcileri icin ayrilmistir.
- `mobile/`: Gelecekte saha ekipleri veya musteri mobil uygulamalari icin ayrilmistir.
- `docker/`: Gelecekte container, local environment ve deployment yardimci dosyalari icin ayrilmistir.
- `scripts/`: Gelecekte gelistirme, kalite kontrol, kurulum ve operasyon betikleri icin ayrilmistir.
- `docs/`: Urun, mimari, gereksinim, operasyon, guvenlik, test ve API dokumantasyonu icin ayrilmistir.

## Dokumantasyon Alanlari

- `docs/product/`: Urun vizyonu, modul tanimlari, ticari kapsam ve yol haritasi.
- `docs/requirements/`: Fonksiyonel ve fonksiyonel olmayan gereksinimler.
- `docs/architecture/`: Teknik mimari, kararlarin arka plani ve sistem tasarimi.
- `docs/adr/`: Architecture Decision Record dosyalari.
- `docs/api/`: API sozlesmeleri ve entegrasyon dokumantasyonu.
- `docs/operations/`: Kurulum, izleme, yedekleme ve operasyonel surecler.
- `docs/security/`: Yetkilendirme, veri koruma ve guvenlik prensipleri.
- `docs/testing/`: Test stratejisi, kabul kriterleri ve kalite yaklasimi.

## Mevcut Durum

- Framework kurulumu yapilmadi.
- Uygulama kodu eklenmedi.
- Veritabani olusturulmadi.
- Is mantigi gelistirilmedi.

Bu depo su anda yalnizca profesyonel proje organizasyonu icin baslangic iskeletidir.
