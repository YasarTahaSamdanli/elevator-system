# İş Emri Detayı ve Depo/Stok Yönetimi Planı

Tarih: 2026-07-07. `SOLUTION_ARCHITECTURE.md` §14'teki "Stok ve Yedek Parça Yönetimi"
gelecek kapsamının somutlaştırılmış yol haritasıdır. Ürün sahibiyle birlikte alınan
kararlar aşağıda "Kesinleşen kararlar" bölümündedir.

## Kesinleşen kararlar

1. **Stok düşüm anı:** Malzeme satırları iş emri üzerinde taslak durur; stok hareketi
   iş emri **Tamamlandı** durumuna geçtiğinde kesinleşir. İptal/yeniden açma ters
   kayıtla (iade hareketi) çözülür — hareket defteri asla güncellenmez/silinmez.
2. **Depo yapısı:** İlk sürümde tek merkez depo. Veri modeli çoklu depoyu baştan
   destekler (`warehouses` tablosu + hareketlerde `warehouse_id`); araç/teknisyen
   depoları Faz 3'te açılır.
3. **Fiyatlama:** Fiyat hareket başına kaydedilir (alım anındaki gerçek fiyat).
   Malzeme kartında yalnızca güncel varsayılan fiyat durur; iş emri malzeme satırı
   eklenirken bu varsayılan kopyalanır (snapshot), sonradan kart fiyatı değişse de
   eski iş emirlerinin maliyeti değişmez.
4. **Negatif stok:** Uyar ama izin ver. Malzeme seçiminde anlık stok gösterilir,
   stok yetersizse arayüz uyarır fakat kaydı engellemez; farklar sayım
   düzeltmesiyle kapatılır.

## Domain modeli (yeni tablolar)

Tümü mevcut kalıba uyar: UUID kimlik, soft delete, `BelongsToCompany` scope,
`*_uuid` payload referansları, `ListQuery` liste uçları, `ApiResponse` sözleşmesi.

- **materials** — malzeme kataloğu: `code` (firma içinde benzersiz), `name`,
  `unit` (adet/metre/kg/litre/takım), `category`, `min_stock_level`,
  `default_unit_price`, `is_active`, `notes`. İş emrine serbest metin değil
  katalogdan malzeme seçilir.
- **warehouses** — `name`, `type` (`main` | `vehicle`), `user_id` (araç deposunda
  zimmetli teknisyen, merkezde null), `is_active`.
- **stock_movements** — değiştirilemez hareket defteri: `material_id`,
  `warehouse_id`, `type` (`purchase_in`, `work_order_out`, `work_order_return`,
  `transfer_in`, `transfer_out`, `adjustment`), `quantity` (pozitif; yön `type`'tan),
  `unit_price`, `work_order_id` (nullable), `transfer_group_uuid` (transferin iki
  bacağını eşler), `occurred_at`, `created_by`, `note`. Anlık stok = hareket toplamı
  (görünüm/önbelleklenmiş sorgu). Update/delete yok; düzeltme = ters kayıt.
- **work_order_items** — iş emri malzeme satırı: `work_order_id`, `material_id`,
  `quantity`, `unit_price` (karttan kopyalanan snapshot), `note`. İş emri
  tamamlanınca her satır bir `work_order_out` hareketi üretir.
- **checklist_templates + checklist_template_items** — iş emri tipine göre standart
  kontrol maddeleri (örn. periyodik bakım formu).
- **work_order_checklist_items** — iş emrine kopyalanan maddeler: `label`,
  `is_done`, `note`. Şablon sonradan değişse de eski iş emirleri etkilenmez.

## İş kuralları

- İş emri durum geçişleri backend'de doğrulanır:
  `draft → planned → assigned → in_progress → completed`; `cancelled` her durumdan
  ulaşılabilir. Tamamlanmış iş emri yeniden açılırsa üretilen çıkışlar
  `work_order_return` ile iade edilir.
- Stok düşümü Service/Action sınıfında, iş emri tamamlama işlemiyle aynı DB
  transaction'ında yapılır (yarım düşüm kalmaz).
- Mal kabul (satın alma girişi) ekranı `purchase_in` hareketleri üretir; fiyat
  girilirse malzeme kartındaki varsayılan fiyat da güncellenir (opsiyonel onay).
- Minimum stok altına düşen malzemeler dashboard'da uyarı listesi olarak görünür
  (ileride bildirim/e-posta).

## Fazlar

- **Faz 1 — İş emri modülü tamamlama:** İş emirleri sayfasına CRUD arayüzü
  (binalar/asansörler/sözleşmelerdeki kalıp) + iş emri detay sayfası + kontrol
  listesi + malzeme satırları (katalog henüz yokken geçici serbest satır YOK —
  Faz 2'deki katalogla birlikte açılır; Faz 1'de detay sayfası zaman çizelgesi ve
  checklist ile gelir).
- **Faz 2 — Depo çekirdeği:** `materials`, `warehouses` (tek merkez), 
  `stock_movements` backend + malzeme kataloğu ve mal kabul ekranları + iş emri
  malzeme satırları arayüzü.
- **Faz 3 — Otomasyon:** Tamamlanınca otomatik düşüm/iade, araç depoları ve
  transfer/zimmet ekranları, min. stok uyarıları.
- **Faz 4 — Raporlama:** İş emri başına parça maliyeti, en çok tüketilen parçalar,
  depo bazında stok değeri; fotoğraf/imza (S3) mobil ile birlikte.

## Kapsam dışı (şimdilik)

Satın alma siparişi/tedarikçi yönetimi, barkodlu sayım, seri/lot takibi,
muhasebe entegrasyonu. Bunlar hareket defteri modeliyle çelişmeden sonradan
eklenebilir.
