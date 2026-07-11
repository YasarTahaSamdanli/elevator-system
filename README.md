# Asansör Bakım ve Servis Yönetim Sistemi — Tanıtım ve Kullanım Rehberi

Bu yazı, sistemi ilk kez kullanacak ofis çalışanları ve saha teknisyenleri için hazırlanmış
hem **tanıtım** hem **eğitim** dokümanıdır. Önce sistemin ne olduğunu, sonra ofis (web) ve
teknisyen (mobil) taraflarının işleyişini adım adım anlatır.

---

## 1. Sistem Nedir?

Asansör bakım firmalarının günlük işleyişini tek çatı altında toplayan bir yönetim sistemidir.
Dağınık Excel dosyaları, telefonla iş bildirme, elde tutulan cari defterler ve "teknisyen
gerçekten gitti mi?" belirsizliği yerine tek bir sistem sunar:

| İhtiyaç | Sistemdeki karşılığı |
|---|---|
| Hangi binada hangi asansör var? | Bina & Asansör envanteri |
| Kiminle sözleşmemiz var, ne zaman bitiyor? | Sözleşme takibi + bitiş uyarıları |
| Bugün kim nerede ne iş yapacak? | İş emirleri ve teknisyen ataması |
| Teknisyen sahaya gerçekten gitti mi? | QR ile kanıtlı işe başlama |
| Muayene kuruluşu hangi etiketi verdi? | Periyodik kontrol (kırmızı/sarı/yeşil etiket) takibi |
| Depoda ne var, kim ne kullandı? | Stok kataloğu ve hareket defteri |
| Hangi bina ne kadar borçlu? | Bina bazlı cari hesap |

Sistem iki uygulamadan oluşur ve **ikisi de aynı veriye bağlıdır**:

- **Web (site):** Patron ve ofis ekibi için yönetim merkezi.
- **Mobil uygulama:** Teknisyen için saha aracı — QR okutur, işi başlatır, bitirir.

Sitede açılan iş emri anında teknisyenin telefonunda görünür; teknisyenin sahada bitirdiği iş
anında sitedeki listeye, stoğa ve cariye yansır.

---

## 2. Temel Kavramlar

Sisteme veri şu zincirle girilir; her halka bir öncekine bağlıdır:

```
Bina  →  Asansör  →  Sözleşme  →  İş Emri
```

- **Bina:** Hizmet verilen adres; bina yöneticisinin adı ve telefonu da burada tutulur.
- **Asansör:** Binaya bağlı her kabin. Kaydedilirken sistem otomatik ve benzersiz bir
  **QR kimliği** üretir. Bu QR yazdırılıp asansöre yapıştırılır ve sahadaki tüm işlemlerin
  giriş kapısı olur.
- **Sözleşme:** Asansöre bağlı bakım anlaşması — dönem, aylık ücret, durum.
- **İş Emri:** Yapılacak işin kaydı. Türleri: **bakım, arıza, muayene, revizyon (onarım),
  modernizasyon**. Numarası (`WO-20260711-XXXXXXXX`) otomatik üretilir.
- **Etiket:** Muayene kuruluşunun (TSE vb.) periyodik kontrolde verdiği sonuç:
  **yeşil** (uygun), **sarı** (kusurlu), **kırmızı** (kullanıma uygun değil).
- **Cari Hesap:** Her binanın borç/tahsilat defteri.

### İş emrinin yaşam döngüsü

```
Taslak → Planlandı → Atandı → Devam Ediyor → Tamamlandı
                                  ↘  (her aşamadan) İptal Edildi
```

Durumlar yalnızca **ileri yönde** ilerler (ara adım atlanabilir, örn. Planlandı'dan doğrudan
işe başlanabilir). Tamamlanan veya iptal edilen iş emri bir daha değiştirilemez.

---

## 3. Ofis Tarafı (Web) — Adım Adım

### 3.1 Giriş ve Panel

E-posta ve şifreyle giriş yapılır. Açılış ekranı olan **Panel (Dashboard)**, günün fotoğrafını
verir: açık iş emirleri, yaklaşan işler, etiket durumları ve genel istatistikler.

### 3.2 Bina Ekleme

**Binalar → Yeni Bina.** Bina adı, adres, şehir/ilçe ve bina yöneticisinin iletişim bilgileri
girilir. Haritadan konum işaretlenebilir — teknisyen adresi ararken işine yarar. Binalar
ilçeye göre filtrelenebilir, isim/kod/yönetici adına göre aranabilir.

### 3.3 Asansör Ekleme ve QR Etiketi

**Asansörler → Yeni Asansör.** Bina seçilir; seri no, üretici, model, kapasite, durak sayısı
ve tescil no girilir. Kayıt anında sistem asansöre **benzersiz bir QR kimliği** atar — elle
girilmez, değiştirilemez, karışmaz.

Ardından **QR Etiketleri** sayfasından bu kodlar yazdırılır ve asansörlere yapıştırılır.
Bu tek seferlik kurulum, sahadaki bütün akışın temelidir: teknisyen artık hangi asansörün
başındaysa onu okutarak çalışır.

### 3.4 Sözleşme Açma

**Sözleşmeler → Yeni Sözleşme.** Asansör seçilir; başlangıç/bitiş tarihi ve **aylık bakım
ücreti** girilir. Bu ücret önemlidir: sistem her ayın 1'inde bu tutarı binanın carisine
otomatik borç olarak yazar (bkz. 3.8).

Bitişine 30 günden az kalan aktif sözleşmelerin yanında **"X gün kaldı"** uyarısı belirir —
yenileme görüşmesini kaçırmazsın.

### 3.5 İş Emri Açma ve Atama

**İş Emirleri → Yeni İş Emri.** Sözleşme, iş türü, öncelik (düşük/normal/yüksek/kritik),
planlanan tarih ve istenirse teknisyen seçilir. Açıklama alanına yapılacak iş yazılır.
İş emri açılırken kullanılacak malzemeler de baştan eklenebilir.

Üç senaryo mümkündür:

1. **Atayarak aç:** Teknisyen seçersin → iş "Atandı" durumuna gelir, teknisyenin listesine düşer.
2. **Planlayarak aç:** Teknisyen seçmezsin → iş "Planlandı" kalır; sahada QR okutan **herhangi
   bir teknisyen** işi görüp üstlenebilir.
3. **Taslak bırak:** Henüz yayınlamak istemediğin işler taslakta durur; taslaklar sahadan
   **başlatılamaz** — önce ofisin yayınlaması gerekir.

### 3.6 İş Emri Takibi (Detay Paneli)

Listede bir iş emrine tıklayınca sağda detay paneli açılır:

- **Zaman çizelgesi:** Planlandı → Başladı → Tamamlandı, saatleriyle.
- **Kontrol listesi:** İş türüne göre şablondan otomatik oluşan maddeler; kaçının bittiği görünür.
- **Malzemeler:** Kullanılan parçalar ve toplam tutar. Ofis buradan da satır ekleyip silebilir.
- **Hızlı aksiyonlar:** "Başlat", "Tamamla", "İptal Et". Ofis, teknisyenden farklı olarak QR
  okutmadan da işi başlatabilir (etiket hasarlıysa veya telefonda sorun varsa devreye girmek için).

**Tamamlama onayı bilinçli olarak iki adımlıdır:** "Tamamla" deyince sistem kaç malzeme
satırının stoktan düşüleceğini gösterir ve onay ister — çünkü tamamlanan iş emrinin malzeme
satırları **kilitlenir**, stok düşümü ve cari borç kaydı geri alınamaz (düzeltme ters kayıtla
yapılır).

### 3.7 Periyodik Kontroller (Etiket Takibi)

Muayene kuruluşu yıllık kontrolü yapıp etiket verdiğinde **Periyodik Kontroller → Yeni
Kontrol** ile sonuç girilir: asansör, tarih, kuruluş, rapor no, **etiket rengi** ve varsa
**kusur listesi**.

Sistemin otomatikleri:

- **Takip tarihi:** Kırmızı etikette +30, sarı etikette +60 gün otomatik hesaplanır
  (mevzuattaki düzeltme süreleri). Tarih geçtiği halde çözülmemiş kusur varsa listede
  kırmızı uyarıyla görünür.
- **Revizyon iş emri:** Kusurlu bir kontrolün satırından tek tıkla **"Revizyon İş Emri Aç"**
  denir; kusurlar yeni iş emrinin kontrol listesine kopyalanır. Teknisyen sahada hangi
  kusurları gidereceğini madde madde görür.
- **Asansör kartı güncellenir:** Asansörler listesinde her asansörün güncel etiketi, son
  kontrol ve sonraki kontrol tarihi görünür.

### 3.8 Envanter (Depo ve Stok)

**Envanter** sayfası üç sekmedir:

1. **Katalog:** Malzemeler — kod, birim (adet/metre/kg...), minimum stok seviyesi ve iki
   fiyat: **alış fiyatı (maliyet)** ve **satış fiyatı (müşteriye yansıtılan)**. Stok minimumun
   altına düşen malzeme listede kırmızı görünür.
2. **Depolar:** Merkez depo ile başlanır; araç depoları (teknisyene zimmetli) da tanımlanabilir.
3. **Hareketler:** Bütün stok hareketlerinin defteri — mal kabul, iş emri çıkışı, transfer,
   sayım düzeltmesi. **Hiçbir hareket silinemez;** hata ters kayıtla düzeltilir.

Günlük kullanım: Toptancıdan mal gelince **"Mal Kabul"** ile giriş yapılır (istenirse malzemenin
varsayılan alış fiyatı da bu girişle güncellenir). Araca malzeme verilecekse **"Transfer"**
kullanılır. İş emri çıkışlarını elle girmeye gerek yoktur — teknisyen işi tamamladığında
sistem otomatik düşer.

### 3.9 Cari Hesap (Borç / Tahsilat)

**Cari Hesap** sayfası her binanın borç-alacak defteridir. Kayıtların çoğu **kendiliğinden**
oluşur:

- **Aylık bakım tahakkuku:** Her ayın 1'inde, aktif sözleşmesi olan her binaya aylık ücret
  otomatik borç yazılır. Aynı ay için ikinci kez yazılmaz.
- **Parça / revizyon borcu:** İş emri tamamlandığında kullanılan malzemeler **satış
  fiyatından** binaya borç yazılır.

Elle yapılan iki işlem vardır:

- **Tahsilat Al:** Binadan para alındığında girilir — tutar, tarih, ödeme kanalı (Banka,
  Elden vb.), ödemeyi yapan kişi. Tahsil eden olarak oturumdaki kullanıcı kaydedilir.
- **Manuel Kayıt:** Devir (sisteme geçiş öncesi bakiye), düzeltme veya serbest borçlandırma.

Sayfanın üstündeki özet kutuları devir, bakım, parça, revizyon, tahsilat toplamlarını ve
**kalan bakiyeyi** (KDV dahil karşılığıyla birlikte) gösterir. Bina filtresi seçilirse tüm
özet o binaya göre hesaplanır. Defter **değiştirilemez ve silinemez** — kim ne zaman ne
girdiyse öyle kalır; bu, hem güven hem denetlenebilirlik demektir.

### 3.10 Ekip Yönetimi

**Ekip** sayfasından kullanıcılar eklenir: ad, e-posta, telefon, şifre ve rol (Teknisyen,
Ofis, Yönetici...). Pasife alınan kullanıcının erişimi kapanır. Teknisyen rolü önemlidir:
sahadaki QR zorunluluğu bu role bağlıdır.

---

## 4. Teknisyen Tarafı (Mobil) — Adım Adım

### 4.1 Giriş ve İş Listesi

Teknisyen telefonundaki uygulamaya e-posta/şifresiyle girer. Karşısına **iş emirleri
listesi** gelir: iş numarası, tür, durum, bina/asansör ve planlanan tarih. Listeden bir işe
dokununca detayı açılır.

### 4.2 QR Okutma — Asansörün Saha Ekranı

Sahadaki asıl akış QR ile başlar. Teknisyen asansörün başına gelir, uygulamadan **QR'ı
okutur**. Açılan ekran o asansörün "saha merkezi"dir:

- **Kimlik kartı:** Bina, seri no, güncel etiket rengi, son ve sonraki kontrol tarihleri.
- **Periyodik Kontroller sekmesi:** Geçmiş kontroller, kusurları ve çözüm durumlarıyla.
- **İş Emirleri sekmesi:** O asansörün **tüm** iş emirleri — kime atanmış olursa olsun.

Yani teknisyenin "bu asansörde ne varmış?" sorusunun cevabı tek okutmayla önündedir.

### 4.3 İşe Başlama — QR Kanıtı

İş emri detayında alttaki büyük düğme akışı yönetir:

1. İş **Planlandı** veya **Atandı** durumundaysa düğme **"QR Okut ve İşe Başla"** der.
2. Teknisyen düğmeye basar, kamera açılır, asansördeki QR'ı okutur.
3. Sistem okutulan kodun **o iş emrinin asansörüne ait olduğunu** doğrular. Yanlış asansörün
   kodu okutulursa iş başlamaz ve uyarı verir.
4. Doğruysa iş **"Devam Ediyor"** durumuna geçer, başlama saati kaydedilir ve **işi başlatan
   teknisyen işin sorumlusu olur** (başkasına atanmış planlı bir işi sahada devralan
   teknisyen, işi kendi üstüne almış olur; ofis ekranında da öyle görünür).

Bu mekanizma sayesinde "işe başladım" demek, **o asansörün önünde durmayı** gerektirir.

### 4.4 Sahada Çalışma: Kontrol Listesi ve Notlar

İş açıkken teknisyen:

- **Kontrol listesini** madde madde işaretler (bakım şablonundan veya muayene kusurlarından
  gelen maddeler). Her maddeye **not** düşebilir ("makine dairesi rutubetli", "halat 6 ayda
  değişmeli" gibi).
- İlerleme (örn. 5/8) hem kendi ekranında hem ofisin detay panelinde canlı görünür.

### 4.5 Malzeme Ekleme

Kullandığı parçayı **"Malzeme Ekle"** ile seçer, miktarı girer, istersen not ekler. Fiyat
girmesine gerek yoktur — sistem kataloğdaki güncel alış/satış fiyatlarını o anki değerleriyle
fotoğraflar. Yanlış eklenen satır, iş tamamlanmadan önce silinebilir.

### 4.6 İşi Tamamlama

**"Tamamla"** düğmesine basınca iş kapanır ve arka planda üç şey **tek işlemde** olur:

1. İş emri **Tamamlandı** durumuna geçer, bitiş saati yazılır.
2. Eklenen malzemeler **depodan düşer** (maliyet, stok defterine işlenir).
3. Aynı malzemeler satış fiyatından **binanın carisine borç** yazılır.

Bu üçlü ya birlikte gerçekleşir ya hiç gerçekleşmez — yarım kalmış kayıt oluşamaz.
Tamamlandıktan sonra malzeme satırları kilitlenir.

### 4.7 Periyodik Kontrol Girişi

Muayene günü teknisyen kuruluşla birlikte sahadaysa, kontrol sonucunu **oradan girer**:
QR okut → Periyodik Kontroller sekmesi → **"Yeni Kontrol Gir"** → etiket rengi, rapor no,
kusurlar. Ofise dönüp kağıttan sisteme geçirme derdi yoktur.

---

## 5. Özet Akış — Bir İşin Hayatı

```
Ofis: Bina + Asansör + Sözleşme kayıtlı, QR asansörde yapışık
  │
  ├─ Ofis iş emri açar (atar veya planlar)
  │
  ├─ Teknisyen sahada QR okutur → işi görür → QR kanıtıyla başlatır
  │
  ├─ Kontrol listesini işler, malzeme ekler, not düşer
  │
  ├─ "Tamamla" → iş kapanır + stok düşer + cariye borç yazılır (tek işlemde)
  │
  └─ Ofis: panelde işi tamamlanmış görür, ay sonunda cariden tahsilat alır
```

---

## 6. Bu Sistemi Farklı Kılan Nedir?

1. **QR ile kanıtlı saha çalışması.** Genel görev-takip uygulamalarında "başladım" bir
   düğmedir; burada asansördeki fiziksel etiketi okutmadan iş başlamaz. Bakım defteri
   "gerçekten yapıldı" kaydına dönüşür — bina yöneticisine karşı da güçlü bir şeffaflık aracıdır.

2. **Üç programın işi tek sistemde.** İş takibi + stok + cari genelde üç ayrı programda
   yürür ve araları elle taşınırken kopar. Burada işi tamamlamak stoğu ve cariyi kendiliğinden
   işler; "depoda görünüyor ama aslında takıldı" ya da "parça takıldı ama faturalanmadı"
   durumu yapısal olarak imkânsızdır.

3. **Asansör mevzuatına göre tasarım.** Kırmızı/sarı/yeşil etiket takibi, kusur listesi,
   +30/+60 gün düzeltme takibi ve kusurdan tek tıkla revizyon iş emri — bunlar genel bir
   CRM'e sonradan eklenemeyen, sektöre özgü akışlardır.

4. **Silinemez defterler.** Stok ve cari hareketleri değiştirilemez; düzeltme ters kayıtla
   yapılır. Muhasebe disiplini yazılıma gömülüdür — "o kaydı kim silmiş?" sorusu hiç sorulmaz.

5. **Maliyet ve kâr görünürlüğü.** Alış/satış fiyatı ayrımı ve iş anında fiyat fotoğraflama
   sayesinde her işin, her binanın gerçek kârlılığı hesaplanabilir.

6. **Unutulmayan tahakkuk.** Aylık bakım ücretleri sistem tarafından yazılır; kimsenin
   hatırlamasına gerek yoktur, mükerrer kayıt da oluşmaz.

7. **Veri firmaya ait.** Kullanıcı başına aylık ücretli hazır paketlerin aksine sistem
   firmanın kendi sunucusunda çalışır ve firmanın iş yapış şekline göre geliştirilebilir.

---

*Bu doküman sistemin mevcut sürümünü anlatır; yeni özellikler eklendikçe güncellenmelidir.*
