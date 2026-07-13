# Periyodik Kontrol (Etiket) ve Cari Hesap Planı

Tarih: 2026-07-09. Müşterinin eski masaüstü uygulamasının (Excel/VBA "Hesap Takip")
ekran görüntüleri analiz edilerek çıkarıldı. İki eksik katman tespit edildi:

1. **Periyodik kontrol / etiket takibi** — muayene kuruluşu mühendisinin verdiği
   etiketin (yeşil/mavi/sarı/kırmızı) sisteme işlenmesi ve takibi.
2. **Cari hesap (finans)** — bina/asansör bazlı borçlandırma, tahsilat, bakiye ve
   ciro raporları. Mevcut depo/stok modülü ([work-order-inventory-plan.md](work-order-inventory-plan.md))
   yalnızca **gider** tarafını çözüyor; **gelir** tarafı hiç yok.

Önerilen sıra: önce etiket modülü (küçük, bağımsız, müşterinin güncel derdi),
sonra cari hesap (büyük modül), en son Excel'den veri göçü.

## Modül 1 — Periyodik Kontrol / Etiket

> **Durum (2026-07-09):** Uygulandı — `elevator_inspections` +
> `inspection_findings` tabloları, asansör üzerinde etiket önbellek kolonları
> (`current_label`, `last_inspection_at`, `next_inspection_due`,
> `follow_up_due`), CRUD + bulgu PATCH ucu, kırmızı +30 / sarı +60 gün takip
> önerisi, kusurlardan revizyon iş emri açma
> (`POST elevator-inspections/{uuid}/work-order`, `InspectionWorkOrderService`),
> web'de Periyodik Kontrol sayfası + asansör listesinde etiket rozeti +
> dashboard bildirimleri. Rapor PDF yükleme dosya altyapısıyla (S3) gelecek.

### Mevzuat arka planı

Asansör Periyodik Kontrol Yönetmeliği: A tipi muayene kuruluşu yıllık kontrol
yapar ve etiket verir:

| Etiket | Anlamı | Süre |
|---|---|---|
| Yeşil | Uygun | — |
| Mavi | Hafif kusurlu | Bir sonraki periyodik kontrole kadar |
| Sarı | Kusurlu | 60 gün içinde giderilmeli + takip kontrolü |
| Kırmızı | Güvensiz | 30 gün içinde giderilmezse mühürlenir |

Eski uygulamadaki karşılıkları: "Etiket Rengi", "Kontrol Tarihi" (periyodik),
"Takip Tarihi" (takip kontrolü), "En Yakın Takip / En Yakın Periyodik".

Muayene kuruluşlarının açık API'si yok; rapor bakımcıya PDF gelir. Giriş
web/mobil'den manuel yapılır, sistem türev alanları otomatikleştirir.

### Domain modeli

Mevcut kalıba uyar (UUID, soft delete, `BelongsToCompany`, `*_uuid` payload,
`ListQuery`, `ApiResponse`).

- **elevator_inspections** — `elevator_id`, `type` (`periodic` | `follow_up`),
  `inspection_body` (muayene kuruluşu adı), `inspected_at`,
  `label` (`green|blue|yellow|red`), `report_number`,
  `follow_up_due_date` (etiketten otomatik önerilir: kırmızı +30g, sarı +60g;
  elle değiştirilebilir), `next_inspection_date`, `notes`, `created_by`.
  Rapor PDF'i Faz 4'teki dosya altyapısıyla (S3) eklenecek.
- **inspection_findings** — kusur satırları: `elevator_inspection_id`,
  `description`, `is_resolved`. Kusurlar revizyon iş emrine kopyalanabilir
  (checklist kalıbıyla aynı mantık).
- **Elevator** önbellek kolonları (liste filtreleme/sıralama için):
  `current_label`, `last_inspection_at`, `next_inspection_due`,
  `follow_up_due`. Kontrol kaydedilince güncellenir; gerçek kaynak
  `elevator_inspections` tablosudur.
- İş emri bağlantısı: `elevator_inspections.work_order_id` (nullable) —
  kusurları gidermek için açılan revizyon iş emri. Kırmızı/sarı etikette
  arayüz tek tıkla "revizyon iş emri aç" önerir; kusurlar iş emri
  checklist'ine kopyalanır.

### İş kuralları / arayüz

- Dashboard uyarıları: takip tarihi yaklaşan/geçen asansörler (kırmızı vurgulu,
  eski uygulamadaki gibi), periyodik kontrolü yaklaşanlar, etiket dağılımı.
- Bina listesi/detayında etiket rengi rozeti (eski uygulamadaki etiket sütunu).
- Mobil: teknisyen sahada kontrol sonucunu girebilir.
- Bildirim: mevcut bildirim altyapısına "takip süresi doluyor" uyarısı;
  SMS entegrasyonu ileride (eski uygulamada Vatan SMS var).

## Modül 2 — Cari Hesap / Finans

> **Durum (2026-07-09):** Uygulandı — `payment_methods` + `account_transactions`
> (değiştirilemez defter; update/destroy ucu yok), `materials.default_sale_price`
> + `work_order_items.sale_unit_price` (maliyet/satış ayrımı), iş emri
> tamamlanınca stok düşümüyle aynı transaction'da `part_charge`/`revision_charge`
> (repair/modernization → revizyon; satış fiyatı yoksa maliyete düşer),
> `ledger:accrue-maintenance` komutu + ayın 1'i 03:00 zamanlaması (sözleşme+ay
> bazında idempotent), `GET account-transactions/summary` özet ucu, web'de
> "Cari Hesap" sayfası (özet kartları, ekstre, tahsilat/manuel kayıt
> diyalogları, ödeme kanalı yönetimi). Ayrıca QR akışı: mobil teknisyen QR
> okutup periyodik kontrol girebiliyor (`filter[qr_identifier]` exact match).
> KDV şimdilik web'de sabit %20 gösterim (`LedgerPage.VAT_RATE`).

### Eski uygulamanın modeli

Bina başına cari ekstre ("İşlem Dökümü"): Bakım / Parça / Revizyon sütunları
borçlandırma, "B. Ödemesi" tahsilat, "Devir" yıl başı bakiyesi, "Toplam"
yürüyen bakiye. Özet: kalem toplamları, KDV %20, kalan bakiye. Aylık bakım
ücreti her ay otomatik borç yazılır; parça müşteriye **satış fiyatıyla**
yansıtılır; tahsilatta kanal (Banka GR / Banka Resmi / Elden), tahsil eden
personel ve ödeyen kişi tutulur.

### Domain modeli

`stock_movements`'ın finans ikizi: değiştirilemez hareket defteri.

- **account_transactions** — `building_id`, `elevator_id` (nullable),
  `type` (`opening_balance` | `maintenance_fee` | `part_charge` |
  `revision_charge` | `payment`), `amount` (pozitif; yön `type`'tan),
  `occurred_at`, `work_order_id` (nullable), `payment_method_id` (nullable),
  `collected_by` (nullable, tahsil eden kullanıcı), `payer_name`,
  `description`, `created_by`. Update/delete yok; düzeltme = ters kayıt.
  Bakiye = hareket toplamı.
- **payment_methods** — tanımlanabilir kanal listesi (Banka GR, Banka Resmi,
  Elden…), koda gömülmez.
- **work_order_items**'a `sale_unit_price` (müşteriye satış fiyatı; mevcut
  `unit_price` maliyet snapshot'ı olarak kalır), malzeme kartına
  `default_sale_price` eklenir.

### İş kuralları

- **Aylık tahakkuk:** scheduled job her ay başı aktif `ServiceContract`'lardan
  `maintenance_fee` kaydı üretir. Sözleşme asansör başına olduğundan ciro
  asansör seviyesinde oluşur; bina cirosu toplamıdır (eski uygulama yalnızca
  bina seviyesini görebiliyordu).
- **Parça borçlandırma:** iş emri tamamlanınca, stok düşümüyle aynı
  transaction'da satış fiyatı toplamı `part_charge` yazılır. Revizyon iş
  emirlerinde `revision_charge`.
- **Tahsilat:** tek tutar; bakım/parça/revizyon kırılımı opsiyonel
  (⚠ müşteriyle netleştirilecek — eski formda kırılımla giriliyor).
- **Raporlar:** bina/asansör ekstresi (eski "İşlem Dökümü"nün karşılığı),
  dönemsel ciro kırılımı (bakım/parça/revizyon), kalan bakiye listesi,
  KDV dahil/hariç görünüm, **kâr raporu** (satış − stok maliyeti — eski
  uygulamanın yapamadığı şey).
- KDV: şirket geneli tek oran, yalnızca gösterim (fatura kesilmiyor).
- "Devir" ritüeli kaldırılır: bakiye defterden sürekli hesaplanır;
  `opening_balance` yalnızca Excel'den ilk import için kullanılır. Ekstre
  görünümünde dönem başı bakiyesi "Devir" satırı olarak gösterilir.

### Açık sorular (müşteriyle netleştirilecek)

1. Tahsilatta bakım/parça/revizyon kırılımı zorunlu mu, tek tutar yeterli mi?
2. KDV kalem bazında mı, şirket geneli tek oran mı? (Varsayım: tek oran.)
3. Revizyon işleri için fiyat nereden gelir — iş emrine elle girilen toplam mı,
   malzeme satırları + işçilik mi?

## Modül 3 — Excel veri göçü

Eski veriler `D:\GÜNCEL\HESAP TAKİP\Cariler\<Bina>.xlsm` dosyalarında (bina
başına bir dosya) + ana "Hesap Takip" çalışma kitabında. Import komutu:

- Binalar, yöneticiler, telefonlar, sözleşme tarihleri/ücretleri.
- Devir bakiyeleri (`opening_balance`) — istenirse tüm işlem geçmişi.
- Etiket rengi, kontrol/takip tarihleri.

Örnek bir `.xlsm` dosyası alınınca format çıkarılacak.

## Kapsam dışı (şimdilik)

Resmî fatura/e-fatura, muhasebe entegrasyonu, muayene kuruluşu API entegrasyonu
(mevcut değil), SMS gönderimi (bildirim altyapısı hazır olduğunda ayrı iş).
