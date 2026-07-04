# Asansör Bakım ve Servis Yönetim Sistemi - Solution Architecture

Bu doküman, ilk sürümde tek bir asansör bakım firması için geliştirilecek Asansör Bakım ve Servis Yönetim Sistemi için resmi mimari referans belgesidir. Amaç; kurum içi operasyonları güvenilir biçimde yöneten, bakımı kolay, güvenli, ölçeklenebilir ve ileride çoklu firma desteğine genişletilebilecek bir mimari tanımlamaktır.

Bu belge herhangi bir framework kurulumu, uygulama kodu, migration veya veritabanı şeması içermez. Teknoloji kararları bu dokümanda resmi mimari karar olarak tanımlanmış, önemli gerekçeler ADR mantığıyla kayıt altına alınmıştır.

## 1. Proje Vizyonu

Projenin vizyonu; ilk sürümde tek bir asansör bakım firmasının sözleşme, periyodik bakım, arıza, saha operasyonu, müşteri iletişimi, raporlama ve temel ticari takip süreçlerini tek merkezden yönetebileceği profesyonel bir kurumsal yazılım oluşturmaktır.

Sistem yalnızca kayıt tutan bir uygulama değil, operasyonel kararları destekleyen bir yönetim platformu olmalıdır. Bakım planlarının aksamasını önlemek, arıza müdahale sürelerini düşürmek, saha ekiplerinin iş yükünü görünür kılmak ve yönetime ticari analiz sağlayacak raporlar üretmek temel değer önerileridir.

Ürün, ilk aşamada tek firma operasyonuna odaklanmalıdır. Ancak mimari; modülerlik, Company (Firma) bazlı veri sahipliği, yetkilendirme esnekliği, denetlenebilirlik ve ileride çoklu firma ya da SaaS modeline genişleyebilme ihtimali üzerine korunmalıdır.

## 2. Hedefler

Başlıca ürün hedefleri şunlardır:

- Bakım firmalarının tüm operasyonel kayıtlarını merkezi ve güvenilir bir platformda toplamak.
- Periyodik bakım planlarını otomatik takip edilebilir hale getirmek.
- Arıza, servis ve bakım iş emirlerini saha ekiplerine atanabilir ve izlenebilir yapmak.
- Yönetici, müşteri ve saha personeli için farklı kullanıcı deneyimleri sunmak.
- Tek firma kullanımını sade ve güvenilir tutarken, gelecekte çoklu firma desteğine genişleyebilecek bir altyapı kurmak.
- Kurumsal müşteriler için raporlama, audit ve veri dışa aktarma ihtiyaçlarını desteklemek.
- İleride mobil uygulama, entegrasyon, bildirim ve gelişmiş analiz modüllerini sorunsuz ekleyebilecek bir temel oluşturmak.

Teknik hedefler:

- Domain odaklı ve modüler bir kod organizasyonu.
- Açık API sözleşmeleri ve sürdürülebilir versiyonlama.
- Test edilebilir, gözlemlenebilir ve denetlenebilir servis yapısı.
- Local development, staging ve production ortamları arasında tutarlı deployment yaklaşımı.
- Vendor lock-in riskini azaltan, ancak ürün hızını da yavaşlatmayan pragmatik teknoloji seçimleri.

## 3. Kullanıcı Rolleri

Sistem, farklı sorumluluk seviyelerine sahip kullanıcıları destekleyecek şekilde tasarlanmalıdır.

- **Sistem Yöneticisi**: İlk sürümde kurulum, sistem geneli teknik ayarlar ve operasyonel destek işlemlerinden sorumludur. Gelecekte çoklu firma desteği geldiğinde platform seviyesinde yönetim rolüne genişleyebilir.
- **Firma Sahibi**: Bakım firmasının ana hesabını yönetir. Firma bilgileri, kullanıcılar ve temel ayarlardan sorumludur.
- **Firma Yöneticisi**: Operasyonel süreçleri takip eder. Bakım planları, arıza kayıtları, ekip performansı, müşteri ilişkileri ve raporlamayı yönetir.
- **Operasyon Sorumlusu / Dispeçer**: Gelen talepleri iş emrine dönüştürür, saha ekiplerine atama yapar, iş emri durumlarını takip eder.
- **Teknisyen / Saha Personeli**: Kendisine atanan bakım ve servis işlerini görüntüler, işlem adımlarını tamamlar, fotoğraf veya belge ekler, servis sonucunu raporlar.
- **Müşteri Yetkilisi**: Kendi tesislerine ait asansörleri, bakım geçmişini, açık talepleri ve raporları görüntüler. Gerektiğinde arıza veya servis talebi oluşturur.
- **Muhasebe / Finans Kullanıcısı**: Sözleşme, hizmet bedeli, tahsilat ve faturalama süreçlerini takip eder.
- **Denetçi / Read-Only Kullanıcı**: Yetkisi dahilindeki kayıtları değiştirmeden inceler. Audit, kalite veya yasal denetim senaryolarında kullanılır.

Roller sabit bir liste olarak değil, ileride role-based access control ve permission-based access control kombinasyonu ile yönetilebilecek esnek bir model olarak ele alınmalıdır.

## 4. Sistem Modülleri

İlk mimari kapsam aşağıdaki modüler yapıyı hedefler:

- **Company (Firma) Yönetimi**: Bakım firmasının temel bilgileri, kullanıcıları, ayarları ve operasyonel tercihleri.
- **Kullanıcı ve Yetki Yönetimi**: Kullanıcı hesapları, roller, izinler, davetler ve oturum yönetimi.
- **Müşteri Yönetimi**: Bakım firmasının hizmet verdiği kurum, site, bina veya bireysel müşterilerin yönetimi.
- **Tesis ve Lokasyon Yönetimi**: Bina, blok, adres, coğrafi konum ve bağlantılı varlıkların modellenmesi.
- **Asansör Envanteri**: Asansör kimlik bilgileri, teknik özellikler, seri numarası, marka/model, bakım periyodu ve durum bilgileri.
- **Sözleşme Yönetimi**: Bakım sözleşmeleri, başlangıç/bitiş tarihleri, kapsam, fiyatlandırma ve yenileme takibi.
- **Periyodik Bakım Planlama**: Bakım takvimleri, otomatik planlama, gecikme takibi ve ekip atamaları.
- **Arıza ve Servis Talepleri**: Müşteri veya operasyon ekibi tarafından oluşturulan taleplerin yönetimi.
- **İş Emri Yönetimi**: Bakım, arıza, kontrol ve montaj benzeri saha işlerinin yaşam döngüsü.
- **Saha Operasyonları**: Teknisyen görev listesi, işlem formları, fotoğraf, imza, konum ve durum güncellemeleri.
- **Bildirimler**: E-posta, SMS, push ve uygulama içi bildirim senaryoları.
- **Raporlama ve Analitik**: Bakım uyumluluğu, arıza yoğunluğu, müdahale süresi, ekip performansı ve ticari metrikler.
- **Dosya ve Belge Yönetimi**: Servis formları, sözleşmeler, fotoğraflar, periyodik raporlar ve yasal belgeler.
- **Audit ve Loglama**: Kritik işlemlerin izlenmesi, değişiklik geçmişi ve operasyonel loglar.
- **Ayarlar ve Konfigürasyon**: Firma bazlı parametreler, çalışma saatleri, SLA tanımları ve bildirim tercihleri.

Modüller başlangıçta tek monorepo içinde geliştirilebilir; ancak sınırlar ileride servis ayrışmasına izin verecek açıklıkta tanımlanmalıdır.

## 5. Domain Modeli

Domain modeli, iş süreçlerini teknik tablolardan önce iş kavramlarıyla ifade eder. Ana domain varlıkları şunlardır:

- **Company (Firma)**: İlk sürümde sistemi kullanan bakım firmasını temsil eder. Gelecekte çoklu firma desteğinde veri sahipliği ve izolasyon sınırının temelidir.
- **User**: Sisteme giriş yapan gerçek kişi veya servis hesabı.
- **Role / Permission**: Kullanıcının sistemde yapabileceği işlemleri belirleyen yetki modeli.
- **Customer**: Bakım firmasının hizmet verdiği müşteri.
- **Site / Building / Location**: Müşteriye ait fiziksel hizmet noktaları.
- **Elevator**: Bakımı yapılan asansör varlığı.
- **QRIdentity / ElevatorIdentity**: Asansörün sahada QR kod, benzersiz kimlik etiketi veya dijital referans ile doğrulanmasını sağlayan kimlik nesnesi. Teknisyenin doğru asansör üzerinde işlem yaptığını kanıtlamak, hızlı servis kaydı açmak ve müşteri/tesis bazlı karışıklıkları önlemek için ayrı ele alınır.
- **Contract**: Müşteri veya tesis ile yapılan bakım/hizmet anlaşması.
- **MaintenancePlan**: Asansör için tanımlanan periyodik bakım planı.
- **ServiceRequest**: Müşteri, sistem veya operasyon ekibi tarafından oluşturulan servis/ariza talebi.
- **WorkOrder**: Bakım, arıza, kontrol veya özel servis operasyonunu temsil eden saha iş emri. Talep, plan veya manuel operasyon sonucunda oluşabilir; durum, atama, zaman, öncelik, SLA ve tamamlanma bilgilerini taşır.
- **TechnicianAssignment**: İş emrinin saha personeli veya ekip ile ilişkisi.
- **ChecklistTemplate**: Belirli iş türleri için kullanılacak standart kontrol listesi şablonu. Periyodik bakım, arıza müdahalesi, güvenlik kontrolü veya müşteri kabul süreçlerinde kalite standardını korur.
- **ChecklistItem**: ChecklistTemplate içindeki tekil kontrol maddesi. Kontrolün sırası, zorunluluğu, cevap tipi ve açıklama ihtiyacı gibi kuralları taşır.
- **ChecklistAnswer**: Teknisyenin belirli bir iş emri kapsamında ChecklistItem için verdiği cevap. Sahada gerçekten yapılan kontrolün kanıtı olduğu için template maddesinden ayrı saklanır.
- **ServiceReport**: Sahada yapılan işlemlerin sonucu, notları, fotoğrafları ve onay bilgileri.
- **Attachment**: Sisteme yüklenen ve domain kayıtlarıyla ilişkilendirilen dosya üst kavramı. Company (Firma), dosya sahibi kayıt, erişim yetkisi, saklama anahtarı, MIME type ve audit bilgileri bu nesne üzerinden yönetilir.
- **Photo**: Servis, arıza, bakım veya hasar durumunu görsel olarak kanıtlayan Attachment alt türü.
- **Signature**: Müşteri onayı, teknisyen teslimi veya servis kapanışı gibi süreçlerde kullanılan imza dosyası.
- **Document**: Sözleşme, yasal evrak, uygunluk belgesi veya müşteri tarafından paylaşılan doküman.
- **Report**: Sistem tarafından üretilen veya yüklenen bakım, servis, denetim ve performans raporları.
- **Notification**: Kullanıcıya veya müşteriye iletilen mesaj kaydı.
- **AuditLog**: Kritik veri değişikliklerinin denetlenebilir kaydı.
- **Invoice / Payment**: İleride finans modülü kapsamında ele alınacak faturalama ve tahsilat varlıkları.

Domain tasarımında temel prensip; operasyonel gerçekliği doğru modellemek, gereksiz erken soyutlamadan kaçınmak ve her varlığın yaşam döngüsünü net tanımlamaktır. Örneğin bir servis talebi ile iş emri aynı kavram değildir: talep müşteriden gelen ihtiyacı, iş emri ise bu ihtiyaca karşılık planlanan operasyonel görevi temsil eder.

QRIdentity, WorkOrder, ChecklistTemplate, ChecklistItem, ChecklistAnswer ve Attachment ayrı domain nesneleri olarak modellenir; çünkü her birinin yaşam döngüsü, doğrulama kuralı, audit ihtiyacı ve iş anlamı farklıdır. QRIdentity sahadaki fiziksel varlığı dijital kayıtla eşler. WorkOrder operasyonun yürütülebilir görev halidir. ChecklistTemplate standart kalite beklentisini, ChecklistItem kontrol kuralını, ChecklistAnswer ise sahadaki gerçekleşmiş cevabı temsil eder. Attachment ve alt türleri ise dosyanın yalnızca teknik bir yükleme olmadığını; kanıt, onay, belge veya rapor gibi farklı hukuki ve operasyonel anlamlar taşıdığını açıkça ifade eder.

## 6. Genel Sistem Mimarisi

Sistem, modüler monolith yaklaşımıyla tasarlanacaktır. Bu karar, erken aşamada geliştirme hızını artırır, dağıtım karmaşıklığını azaltır ve domain sınırlarının olgunlaşmasına izin verir. Modüller, ileride ihtiyaç doğarsa ayrı servislere ayrılabilecek şekilde gevşek bağımlılıklarla organize edilmelidir.

Önerilen genel mimari katmanları:

- **Client Layer**: Web yönetim paneli, mobil uygulama ve ileride müşteri portalı.
- **API Layer**: İstemcilerle iletişim kuran HTTP API, kimlik doğrulama, rate limiting ve request validation katmanı.
- **Application Layer**: Use case akışları, transaction sınırları, orchestration ve yetki kontrolleri.
- **Domain Layer**: İş kuralları, domain servisleri, aggregate mantığı ve invariant kontrolleri.
- **Infrastructure Layer**: Veritabanı, dosya depolama, e-posta/SMS servisleri, queue, cache ve dış entegrasyonlar.
- **Observability Layer**: Log, metric, trace, audit ve hata takip bileşenleri.

Başlangıçta tek deploy edilebilir Laravel backend uygulaması kullanılacaktır. Arka planda queue worker, scheduler ve Laravel Reverb süreçleri ayrı runtime olarak konumlanabilir. Bu ayrım mikroservis zorunluluğu değildir; operasyonel sorumlulukların net ayrılmasıdır.

## 7. Teknoloji Seçimleri ve Gerekçeleri

Bu aşamada hiçbir teknoloji kurulmayacaktır. Ancak ürünün resmi teknoloji yığını kesinleştirilmiştir ve uygulama geliştirme bu kararlar üzerinden ilerleyecektir.

Nihai teknoloji yığını:

- **Backend: Laravel 12**: Kurumsal operasyon yazılımı geliştirme hızını artıran olgun ekosistemi, yerleşik queue/scheduler yapısı, güçlü authentication/authorization seçenekleri, bakım kolaylığı ve Türkiye pazarındaki geliştirici bulunabilirliği nedeniyle seçilmiştir.
- **PHP: 8.3+**: Laravel 12 ile uyumlu modern dil özellikleri, performans iyileştirmeleri, tip güvenliği kabiliyetleri ve uzun vadeli bakım avantajı nedeniyle kullanılacaktır.
- **Web: React + TypeScript**: Veri yoğun operasyon panelleri için zengin component ekosistemi, güçlü state yönetimi seçenekleri, TypeScript ile tip güvenliği ve büyük frontend kod tabanlarında sürdürülebilirlik sağlaması nedeniyle seçilmiştir.
- **Mobile: Flutter**: Tek kod tabanıyla Android ve iOS üretimi, saha operasyonları için tutarlı kullanıcı deneyimi, offline-first senaryolarda güçlü kontrol ve yüksek performanslı arayüz kabiliyeti nedeniyle seçilmiştir.
- **Database: PostgreSQL**: İlişkisel veri bütünlüğü, transaction güvenilirliği, gelişmiş index seçenekleri, JSONB desteği, raporlama kabiliyeti ve kurumsal operasyon sistemlerinde kanıtlanmış olgunluğu nedeniyle seçilmiştir.
- **Cache & Queue: Redis**: Cache, rate limiting, queue altyapısı ve kısa ömürlü operasyonel veriler için hızlı, sade ve Laravel ekosistemiyle uyumlu olması nedeniyle kullanılacaktır.
- **Realtime: Laravel Reverb**: Laravel ekosistemiyle doğal entegrasyonu, websocket tabanlı gerçek zamanlı olay iletimi ve operasyon ekranlarında düşük gecikmeli güncelleme ihtiyacını karşılaması nedeniyle seçilmiştir.
- **Container: Docker**: Local development, staging ve production ortamlarında tutarlı çalışma ortamı sağlamak, bağımlılık farklarını azaltmak ve deployment süreçlerini standartlaştırmak için kullanılacaktır.
- **Object Storage: S3 uyumlu depolama**: Fotoğraf, imza, belge ve rapor dosyalarının uygulama sunucusundan bağımsız, ölçeklenebilir ve taşınabilir biçimde saklanması için kullanılacaktır.
- **API: REST (v1)**: Web ve mobil istemciler için anlaşılır, yaygın, test edilebilir ve üçüncü taraf entegrasyonlara uygun bir sözleşme sunması nedeniyle ilk API standardı olarak belirlenmiştir.

Teknoloji kararlarında temel kriterler; ürün geliştirme hızı, ekip bulunabilirliği, bakım maliyeti, güvenlik, ölçeklenebilirlik, test edilebilirlik, ekosistem olgunluğu ve kurumsal operasyon süreçlerine uygunluktur.

## 8. Monorepo Yapısı

Mevcut monorepo iskeleti aşağıdaki sorumluluk ayrımını temel alır:

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

Bu yapı, farklı uygulama yüzeylerini aynı ürün ve domain kararları altında tutar. Monorepo yaklaşımı; ortak dokümantasyon, uyumlu versiyonlama, merkezi kalite kontrolleri ve çapraz ekip görünürlüğü sağlar.

Klasör sorumlulukları:

- `backend/`: API, domain servisleri, background job, scheduler ve backend testleri.
- `web/`: Yönetim paneli, müşteri portalı ve web tabanlı operasyon ekranları.
- `mobile/`: Teknisyen ve saha operasyonu odaklı mobil uygulamalar.
- `docker/`: Local development, staging ve production container tanımları.
- `scripts/`: Kurulum, kalite kontrol, deployment ve bakım betikleri.
- `docs/`: Ürün, mimari, API, operasyon, test ve güvenlik dokümantasyonu.

Monorepo içinde framework kurulumu yapıldığında her uygulamanın kendi bağımlılık yönetimi bulunacaktır; ancak ortak lint, test, CI ve versiyonlama stratejisi kökten yönetilmelidir.

### Architecture Decision Records (ADR)

Architecture Decision Record, mimari açıdan önemli kararların nedenleriyle birlikte kayıt altına alınması için kullanılacaktır. ADR'ler `docs/adr/` klasörü altında tutulmalı, kararın bağlamını, seçilen yaklaşımı, alternatifleri ve sonuçlarını açıklamalıdır.

ADR kullanımı şu amaçlara hizmet eder:

- Mimari kararların zaman içinde neden alındığını görünür kılmak.
- Yeni ekip üyelerinin teknik yönü hızlı anlamasını sağlamak.
- Geriye dönük karar tartışmalarında kişisel hafızaya bağımlılığı azaltmak.
- Büyük teknoloji veya mimari değişikliklerde kontrollü karar süreci oluşturmak.

Her ADR numaralı, kısa başlıklı ve durum bilgisi içeren bir doküman olarak hazırlanmalıdır. Durum değerleri `Proposed`, `Accepted`, `Deprecated` veya `Superseded` olarak kullanılır. Kabul edilen kararlar ancak yeni bir ADR ile değiştirilmeli, eski karar silinmemelidir.

İlk ADR kararları:

#### ADR-001 - Neden Laravel?

- **Durum**: Accepted
- **Karar**: Backend Laravel 12 ve PHP 8.3+ ile geliştirilecektir.
- **Gerekçe**: Laravel; hızlı kurumsal uygulama geliştirme, olgun ekosistem, queue, scheduler, broadcasting, authentication, authorization ve test araçlarıyla ürünün ihtiyaçlarını tek çatı altında karşılar. Türkiye pazarında geliştirici bulunabilirliği yüksek olduğu için uzun vadeli bakım riskini azaltır.
- **Sonuç**: Backend modüler monolith olarak Laravel üzerinde inşa edilecek; iş mantığı Controller içinde değil Service / Action ve domain katmanlarında tutulacaktır.

#### ADR-002 - Neden React?

- **Durum**: Accepted
- **Karar**: Web uygulaması React + TypeScript ile geliştirilecektir.
- **Gerekçe**: React ekosistemi veri yoğun operasyon panelleri, component tabanlı ekranlar ve karmaşık kullanıcı etkileşimleri için güçlüdür. TypeScript, büyük web kod tabanlarında tip güvenliği ve refactor güvenilirliği sağlar.
- **Sonuç**: Web arayüzü domain odaklı component yapısıyla geliştirilecek; API iletişimi standart client katmanı üzerinden yürütülecektir.

#### ADR-003 - Neden Flutter?

- **Durum**: Accepted
- **Karar**: Mobil uygulama Flutter ile geliştirilecektir.
- **Gerekçe**: Flutter, saha ekipleri için Android ve iOS tarafında tutarlı kullanıcı deneyimi sunar. Offline-first akışlar, medya yükleme, imza alma ve hızlı arayüz geliştirme ihtiyaçlarında tek kod tabanı avantajı sağlar.
- **Sonuç**: Mobil uygulama teknisyen deneyimine odaklanacak; yönetim panelinin kopyası olmayacak ve saha operasyonu için optimize edilecektir.

#### ADR-004 - Neden PostgreSQL?

- **Durum**: Accepted
- **Karar**: Ana ilişkisel veritabanı PostgreSQL olacaktır.
- **Gerekçe**: PostgreSQL; transaction güvenilirliği, güçlü constraint desteği, gelişmiş index seçenekleri, JSONB kabiliyeti ve raporlama ihtiyaçlarına uygunluğu nedeniyle kurumsal operasyon verisi için güvenilir temeldir.
- **Sonuç**: Domain verileri PostgreSQL üzerinde Company (Firma) bazlı veri sahipliği, foreign key, unique constraint ve audit ihtiyaçları dikkate alınarak tasarlanacaktır.

#### ADR-005 - Neden Modüler Monolith?

- **Durum**: Accepted
- **Karar**: Sistem başlangıç ve ana ürün geliştirme aşamasında modüler monolith olarak tasarlanacaktır.
- **Gerekçe**: Modüler monolith, erken aşamada mikroservis karmaşıklığını önlerken domain sınırlarının net çizilmesine izin verir. Tek deployment modeli operasyon maliyetini azaltır, ancak modül sınırları doğru kurulduğunda ileride servis ayrışmasına kapı açık kalır.
- **Sonuç**: Modüller Company (Firma), kullanıcı, müşteri, asansör, iş emri, bakım, dosya, bildirim ve audit gibi domain sınırlarına göre organize edilecek; çapraz bağımlılıklar kontrollü tutulacaktır.

## 9. Backend Mimarisi

Backend mimarisi Laravel 12 üzerinde domain odaklı ve modüler monolith olarak tasarlanacaktır. Bu, her domain modülünün kendi use case, validation, policy, event ve persistence sınırlarına sahip olması anlamına gelir.

Backend katmanları:

- **Presentation / API**: Controller, request validation, response transformation ve API versioning.
- **Application**: Use case servisleri, transaction yönetimi, workflow orchestration.
- **Domain**: Entity, value object, domain service, business rule ve domain event tanımları.
- **Infrastructure**: ORM, repository implementasyonları, queue, cache, mail, SMS, object storage ve dış servis adaptörleri.

Backend karar prensipleri:

- Controller katmanı ince tutulmalı, iş mantığı use case veya application servislerine taşınmalıdır.
- Domain kuralları yalnızca UI veya controller seviyesinde bırakılmamalıdır.
- Kritik işlemler transaction sınırlarıyla korunmalıdır.
- Dış servis entegrasyonları adapter arkasında izole edilmelidir.
- Company (Firma) bazlı veri sahipliği backend'in tüm veri erişim yollarında korunmalıdır; çoklu firma desteği geldiğinde bu yapı merkezi izolasyon kuralına genişleyebilmelidir.
- Background işler idempotent tasarlanmalı, tekrar çalıştırıldığında veri tutarlılığını bozmamalıdır.

Backend, REST API v1 standardı ile ilerleyecektir. Realtime ihtiyaçlar Laravel Reverb ile, uzun süren işler Redis queue ile, periyodik işler Laravel scheduler ile yönetilecektir. Raporlama ve entegrasyon ihtiyaçları olgunlaştıkça ayrı endpoint grupları, queue eventleri ve domain eventleriyle genişletilecektir.

## 10. Web Mimarisi

Web uygulaması React + TypeScript ile geliştirilecektir ve bakım firmasının günlük operasyonlarını yönettiği ana arayüz olacaktır. Bu nedenle hız, okunabilirlik, veri yoğun ekranlarda performans, tip güvenliği ve rol bazlı görünürlük önceliklidir.

Temel web alanları:

- Dashboard ve operasyon özeti.
- Müşteri ve tesis yönetimi.
- Asansör envanteri.
- Bakım takvimi.
- Arıza ve servis talepleri.
- İş emri yönetimi.
- Teknisyen takip ekranları.
- Raporlama ve dışa aktarma.
- Kullanıcı, rol ve firma ayarları.

Web mimarisi karar prensipleri:

- Arayüz bileşenleri yeniden kullanılabilir ve domain kavramlarına uygun isimlendirilmelidir.
- API iletişimi merkezi bir client katmanı üzerinden yapılmalıdır.
- Yetki kontrolleri yalnızca frontend'de bırakılmamalı, backend ile tutarlı çalışmalıdır.
- Büyük veri tabloları için server-side pagination, filtering ve sorting desteklenmelidir.
- Formlar validasyon, hata gösterimi ve erişilebilirlik açısından standartlaştırılmalıdır.
- Dashboard ekranlarında gerçek zamanlı güncellemeler opsiyonel, kritik iş akışlarında ise kontrollü kullanılmalıdır.

Web uygulaması bir pazarlama sitesi değil, operasyonel yönetim paneli olarak tasarlanmalıdır. Bu nedenle bilgi yoğunluğu, filtreleme, hızlı işlem ve güvenilir geri bildirimler görsel gösterişten daha önceliklidir.

## 11. Mobil Mimari

Mobil uygulama Flutter ile geliştirilecektir ve öncelikli kullanıcısı saha personelidir. Bu nedenle mimari; düşük bağlantı kalitesi, hızlı işlem, fotoğraf yükleme, konum bilgisi, imza alma ve çevrimdışı çalışma senaryolarını dikkate almalıdır.

Temel mobil alanlar:

- Teknisyen giriş ve oturum yönetimi.
- Günlük görev listesi.
- İş emri detayı.
- Bakım kontrol listeleri.
- Fotoğraf ve belge yükleme.
- Müşteri onayı veya dijital imza.
- Konum doğrulama.
- İş durumu güncelleme.
- Offline kayıt kuyruğu.

Mobil mimari karar prensipleri:

- Ağ bağlantısı kopmalarına karşı yerel geçici kayıt mekanizması tasarlanmalıdır.
- Offline oluşturulan kayıtlar senkronizasyon kuyruğuna alınmalı ve çakışma stratejisi açıkça tanımlanmalıdır.
- Büyük medya dosyaları sıkıştırma, arka plan yükleme ve retry stratejisi ile yönetilmelidir.
- Mobil uygulama yalnızca teknisyen deneyimine odaklanmalı; yönetim panelinin birebir kopyası yapılmamalıdır.
- Güvenlik nedeniyle cihazda saklanan hassas veriler minimumda tutulmalı ve mümkünse şifrelenmelidir.

## 12. API Tasarım Standartları

API tasarımı, uzun vadeli bakım ve üçüncü taraf entegrasyonlar için tutarlı olmalıdır.

Standartlar:

- Tüm endpointler `/api/v1` altında başlamalıdır.
- Kaynak isimleri çoğul ve domain odaklı olmalıdır.
- HTTP metodları semantik kullanılmalıdır: `GET`, `POST`, `PUT/PATCH`, `DELETE`.
- Liste endpointleri pagination, filtering, sorting ve search standartlarını desteklemelidir.
- Hata cevapları tutarlı bir formatla dönmelidir.
- Başarılı cevaplarda veri şekli öngörülebilir olmalıdır.
- Tarih/saat alanları timezone stratejisiyle birlikte standartlaştırılmalıdır.
- Idempotency gerektiren işlemler için idempotency key yaklaşımı değerlendirilmelidir.
- Dosya yükleme ve indirme işlemleri için ayrı güvenlik kuralları uygulanmalıdır.

Standart başarılı response formatı:

```json
{
  "success": true,
  "data": {},
  "message": "Operation completed successfully.",
  "meta": {}
}
```

Standart hata response formatı:

```json
{
  "success": false,
  "message": "Validation failed.",
  "error": {
    "code": "VALIDATION_ERROR",
    "details": {}
  }
}
```

Validation hatalarında `details` alanı field bazlı hata listesini taşımalıdır. Yetkilendirme, Company (Firma) kapsamı, bulunamayan kayıt ve business rule ihlali gibi hata türleri standart hata kodlarıyla ayrıştırılmalıdır.

Pagination standardı:

```json
{
  "success": true,
  "data": [],
  "meta": {
    "pagination": {
      "page": 1,
      "per_page": 25,
      "total": 100,
      "total_pages": 4
    }
  }
}
```

Liste endpointlerinde varsayılan `per_page` değeri 25 olmalı, maksimum değer API güvenliği ve performansına göre sınırlandırılmalıdır. Pagination parametreleri `page` ve `per_page` olarak standartlaştırılmalıdır.

Filtreleme ve sıralama standardı:

- Filtreler query string üzerinden `filter[field]=value` formatıyla taşınmalıdır.
- Tarih aralıkları `filter[created_at_from]` ve `filter[created_at_to]` gibi açık parametrelerle ifade edilmelidir.
- Sıralama `sort=field` ve ters sıralama `sort=-field` formatıyla yapılmalıdır.
- Birden fazla sıralama gerektiğinde virgül ayrımı kullanılmalıdır: `sort=-created_at,status`.
- Arama için `search` parametresi kullanılmalı, hangi alanlarda arama yapılacağı endpoint sözleşmesinde belirtilmelidir.
- Company (Firma) kapsamı client'tan gelen filtreye bırakılmamalı, backend tarafından zorunlu olarak uygulanmalıdır.

API sözleşmeleri ileride OpenAPI/Swagger gibi makine tarafından okunabilir formatlarla dokümante edilmelidir. Public API ile internal API ayrımı baştan düşünülmeli, entegrasyon müşterilerine açılacak yüzey kontrollü tutulmalıdır.

## 13. Veritabanı Tasarım Prensipleri

Veritabanı PostgreSQL üzerinde tasarlanacaktır. Veri bütünlüğü, transaction güvenilirliği ve geçmiş izleme bu sistemde kritik önemdedir; çünkü asansör bakım sistemi yasal, operasyonel ve ticari kayıtlar içerir.

Prensipler:

- Her ana tabloda Company (Firma) bazlı veri sahipliğini veya bu sahipliğe giden ilişki zincirini destekleyen alanlar bulunmalıdır.
- Kritik kayıtlar için oluşturulma, güncellenme ve silinme bilgileri tutulmalıdır.
- Soft delete yalnızca iş gereksinimi olan varlıklarda kullanılmalıdır.
- Finans, sözleşme ve servis raporu gibi kayıtlar için geçmişi bozacak güncellemeler kontrollü yapılmalıdır.
- Foreign key ve unique constraint gibi veritabanı seviyesindeki bütünlük kuralları ihmal edilmemelidir.
- Index stratejisi gerçek sorgu desenlerine göre belirlenmelidir.
- Büyük log ve audit tabloları için arşivleme veya partitioning stratejisi ileride değerlendirilmelidir.
- Migration dosyaları geri alınabilir, okunabilir ve küçük adımlı olmalıdır.

Bu aşamada migration oluşturulmayacaktır. Veritabanı modeli, gereksinimler netleştikçe ayrı tasarım dokümanları ve ADR kayıtlarıyla olgunlaştırılmalıdır.

## 14. Company (Firma) ve Gelecekte Çoklu Firma Yaklaşımı

İlk sürüm tek bir asansör bakım firması için geliştirilecektir. Bu nedenle V1'de abonelik, paket, firma limiti, plan özelliği veya SaaS onboarding akışı ürün kapsamına dahil değildir.

Buna rağmen domain modelinde Company (Firma) kavramı korunmalıdır. Company, ilk sürümde sistemi kullanan bakım firmasının kurumsal kimliğini temsil eder; gelecekte çoklu firma desteği gerektiğinde veri sahipliği ve izolasyon sınırının temelini oluşturur.

Başlangıç için önerilen yaklaşım:

- Tek uygulama, ortak veritabanı ve tek aktif bakım firması kullanımı.
- Domain kayıtlarında Company (Firma) sahipliğini doğrudan veya ilişki zinciri üzerinden koruyan model.
- Backend veri erişim katmanının ileride merkezi Company scope uygulayabilecek şekilde tasarlanması.
- Firma ayarları, çalışma saatleri ve operasyonel tercihlerin Company altında tutulması.
- Abonelik, paket, kullanım limiti, plan özelliği ve firma bazlı metrik yönetiminin gelecekte eklenebilir modüller olarak değerlendirilmesi.

Bu yaklaşım V1 için gereksiz SaaS karmaşıklığını önlerken mevcut domain modelini bozmaz. Çoklu firma ihtiyacı ortaya çıktığında Company bazlı izolasyon, ayrı veritabanı, ayrı schema, dedicated deployment veya abonelik yönetimi kararları ayrı ADR süreciyle ele alınacaktır.

## 15. Kimlik Doğrulama ve Yetkilendirme

Kimlik doğrulama ve yetkilendirme mimarisi kurumsal veri güvenliğinin merkezindedir.

Kimlik doğrulama prensipleri:

- Güvenli parola saklama ve parola politikaları uygulanmalıdır.
- Oturum yönetimi web ve mobil kullanım senaryolarına göre ayrı tasarlanmalıdır.
- MFA desteği özellikle yönetici rolleri için ileride planlanmalıdır.
- Şifre sıfırlama ve kullanıcı daveti süreçleri token süreleriyle korunmalıdır.
- Başarısız giriş denemeleri rate limiting ve audit ile izlenmelidir.

Yetkilendirme prensipleri:

- Rol bazlı erişim kontrolü temel alınmalıdır.
- Kritik işlemler permission bazlı ayrıntılandırılmalıdır.
- Company (Firma) sınırları yetkilendirme modelinin ayrılmaz parçası olmalıdır.
- Müşteri kullanıcıları yalnızca kendi tesis ve kayıtlarına erişebilmelidir.
- Teknisyen kullanıcıları yalnızca atanmış iş emirlerini ve gerekli minimum müşteri bilgisini görmelidir.

Yetkilendirme kararları frontend görünürlüğünden bağımsız olarak backend'de enforce edilmelidir.

## 16. Gerçek Zamanlı İletişim

Gerçek zamanlı iletişim; operasyon ekranlarında iş emri güncellemeleri, yeni arıza bildirimleri, teknisyen durumları ve kritik uyarılar için kullanılacaktır.

Gerçek zamanlı altyapı Laravel Reverb üzerine kurulacaktır. Reverb, Laravel event/broadcasting ekosistemiyle doğal uyumu sayesinde backend domain eventlerinin web ve mobil istemcilere kontrollü şekilde aktarılmasını sağlar. Polling veya periyodik yenileme yalnızca düşük öncelikli ekranlarda ya da websocket bağlantısının geçici olarak kullanılamadığı durumlarda fallback yaklaşımı olarak kullanılmalıdır.

Potansiyel realtime olayları:

- Yeni arıza talebi oluşturuldu.
- İş emri atandı veya durumu değişti.
- Teknisyen işi başlattı/tamamladı.
- Kritik bakım gecikmesi oluştu.
- Müşteri onayı bekleyen rapor oluştu.

Realtime sistem tasarlanırken bağlantı yönetimi, Company (Firma) kapsamı, yetki kontrolü, event replay ihtiyacı ve mobil bağlantı kopmaları dikkate alınmalıdır.

## 17. Dosya Yönetimi

Sistem; servis fotoğrafları, bakım formları, sözleşmeler, imzalı belgeler, periyodik raporlar ve müşteri evrakları gibi farklı dosya tiplerini S3 uyumlu object storage üzerinde yönetecektir.

Dosya yönetimi prensipleri:

- Dosyalar uygulama sunucusunda kalıcı olarak saklanmamalı, S3 uyumlu object storage kullanılmalıdır.
- Dosya metadata bilgileri veritabanında tutulmalıdır.
- Her dosya Company (Firma), ilgili domain varlığı ve erişim izniyle ilişkilendirilmelidir.
- Dosya isimleri kullanıcı girdisine doğrudan bağımlı olmamalı, güvenli benzersiz anahtarlarla saklanmalıdır.
- Virüs tarama, MIME type kontrolü ve maksimum dosya boyutu sınırları değerlendirilmelidir.
- İndirme linkleri süreli ve yetki kontrollü olmalıdır.
- Mobil fotoğraf yüklemelerinde sıkıştırma ve arka plan retry stratejisi kullanılmalıdır.
- Photo, Signature, Document ve Report alt türleri farklı erişim, saklama, audit ve iş akışı kurallarına sahip olduğu için Attachment üst modeli altında açıkça ayrıştırılmalıdır.

Dosya yönetimi, ileride hukuki ve operasyonel denetimlerde kanıt niteliği taşıyabileceği için audit kayıtlarıyla desteklenmelidir.

## 18. Bildirim Sistemi

Bildirim sistemi, kullanıcıların operasyonel aksiyonlardan zamanında haberdar olmasını sağlar.

Bildirim kanalları:

- Uygulama içi bildirim.
- E-posta.
- SMS.
- Mobil push bildirimi.
- İleride WhatsApp veya benzeri iş mesajlaşma entegrasyonları.

Bildirim prensipleri:

- Bildirim gönderimi arka plan job olarak çalışmalıdır.
- Kanal seçimi firma ve kullanıcı tercihleriyle yönetilebilmelidir.
- Kritik bildirimler ile bilgilendirme bildirimleri ayrıştırılmalıdır.
- Gönderim denemeleri, hata durumları ve teslim bilgileri loglanmalıdır.
- Aynı olay için gereksiz tekrar bildirim gönderimini önleyen deduplication stratejisi olmalıdır.
- Şablonlar çok dil ve firma özelleştirmesine uygun tasarlanmalıdır.

Örnek bildirim olayları:

- Yeni iş emri atandı.
- Bakım tarihi yaklaşıyor.
- Bakım gecikti.
- Arıza talebi oluşturuldu.
- Servis raporu müşteri onayına gönderildi.
- Sözleşme bitiş tarihi yaklaşıyor.

## 19. Loglama ve Audit

Loglama ve audit birbirinden ayrı ele alınmalıdır.

**Operational logging**, sistemin teknik sağlığını izlemek için kullanılır. Hata kayıtları, performans metrikleri, job sonuçları ve entegrasyon hataları bu kapsamdadır.

**Audit logging**, kullanıcıların yaptığı kritik iş işlemlerini denetlenebilir şekilde kaydeder. Kim, ne zaman, hangi Company (Firma) kapsamında, hangi kaydı, hangi değişiklikle etkiledi sorularına cevap vermelidir.

Audit kapsamına girmesi gereken örnek işlemler:

- Kullanıcı oluşturma, silme ve rol değiştirme.
- Sözleşme oluşturma veya kritik sözleşme değişiklikleri.
- Asansör kaydı oluşturma, pasife alma veya silme.
- İş emri durumu değiştirme.
- Servis raporu onaylama.
- Dosya indirme veya kritik belge görüntüleme.
- Firma ayarlarını değiştirme.

Log kayıtlarında hassas veri maskeleme uygulanmalıdır. Parola, token, kişisel veri veya finansal detayların düz loglara yazılması engellenmelidir.

## 20. Test Stratejisi

Test stratejisi, ürünün ticari güvenilirliği için erken aşamada tanımlanmalıdır.

Test türleri:

- **Unit Test**: Domain kuralları, hesaplama mantığı, policy ve küçük servisler.
- **Feature / Integration Test**: API endpointleri, use case akışları, veritabanı etkileşimleri.
- **Contract Test**: Backend ve frontend/mobile arasında API sözleşme uyumluluğu.
- **End-to-End Test**: Kritik kullanıcı akışları; iş emri oluşturma, atama, tamamlama, raporlama.
- **Mobile Sync Test**: Offline kayıt, tekrar deneme ve çakışma senaryoları.
- **Security Test**: Yetki aşımı, Company (Firma) kapsamı, rate limiting ve dosya erişimi.
- **Performance Test**: Büyük müşteri verisi, yoğun iş emri listeleri ve raporlama sorguları.

Test öncelikleri:

- Company (Firma) kapsamı ve yetki sınırı hataları.
- Yetki kontrolü hataları.
- İş emri yaşam döngüsü.
- Bakım planı üretimi ve gecikme hesaplama.
- Dosya erişim güvenliği.
- Bildirim tekrarları ve job idempotency.

CI sürecinde lint, statik analiz, test ve güvenlik kontrolleri kademeli olarak eklenmelidir.

## 21. Deployment Mimarisi

Deployment mimarisi Docker temelli, sade, tekrarlanabilir ve gözlemlenebilir olmalıdır.

Önerilen ortamlar:

- **Local**: Geliştirici makinesinde Docker destekli çalışma ortamı.
- **Development**: Ekip içi ortak test ortamı.
- **Staging**: Production'a yakın konfigürasyonla kabul testleri.
- **Production**: Gerçek müşteri verisi ve operasyonel kullanım.

Temel deployment bileşenleri:

- Laravel 12 backend API runtime.
- Redis queue worker.
- Laravel scheduler.
- Laravel Reverb realtime runtime.
- React + TypeScript web frontend runtime veya statik hosting.
- Mobil uygulama dağıtım süreçleri.
- PostgreSQL veritabanı.
- Redis cache ve queue bileşeni.
- S3 uyumlu object storage.
- Log ve monitoring altyapısı.

Deployment prensipleri:

- Ortam değişkenleri koddan ayrı yönetilmelidir.
- Secret değerler repository içine yazılmamalıdır.
- Migration işlemleri kontrollü ve geri dönüş planıyla çalıştırılmalıdır.
- Backup ve restore prosedürleri production öncesinde test edilmelidir.
- Health check ve readiness check mekanizmaları kullanılmalıdır.
- Zero-downtime deployment hedeflenmeli, ancak başlangıçta kontrollü bakım pencereleri kabul edilebilir.

## 22. Kodlama Standartları

Kodlama standartları Laravel 12, PHP 8.3+, React + TypeScript ve Flutter ekosistemleriyle uyumlu olacak şekilde tanımlanacaktır. Bu aşamadaki üst seviye prensipler şunlardır:

- Kod domain kavramlarını açıkça yansıtmalıdır.
- İsimlendirme tutarlı ve iş diliyle uyumlu olmalıdır.
- Controller, UI component veya job gibi giriş noktaları iş mantığıyla şişirilmemelidir.
- Tekrarlayan kritik kurallar merkezi domain veya application katmanında toplanmalıdır.
- Hata yönetimi tutarlı yapılmalı, kullanıcıya teknik detay sızdırılmamalıdır.
- Her modül kendi sorumluluğu içinde kalmalı, çapraz bağımlılıklar kontrollü olmalıdır.
- Formatlama, lint ve statik analiz otomatik hale getirilmelidir.
- Kod inceleme süreci güvenlik, Company (Firma) kapsamı, test ve domain doğruluğu açısından yapılmalıdır.
- **SOLID** prensipleri özellikle application service, action, policy ve domain servislerinde uygulanmalıdır.
- **Clean Architecture** yaklaşımıyla presentation, application, domain ve infrastructure sorumlulukları ayrıştırılmalıdır.
- **Domain Driven Design (DDD)** prensipleri domain dili, aggregate sınırları, value object, domain event ve iş kuralı modellemesinde referans alınmalıdır.
- **PSR-12** PHP kod formatlama standardı olarak kullanılmalıdır.
- **Conventional Commits** commit mesaj standardı olarak kullanılmalıdır.
- Controller katmanı ince tutulmalı; request alma, yetki kontrolüne yönlendirme, validation tetikleme ve response dönme sorumluluklarıyla sınırlandırılmalıdır.
- Business logic yalnızca Service / Action katmanında ve gerektiğinde domain nesnelerinde konumlandırılmalıdır.
- Repository pattern yalnızca karmaşık sorgu, persistence soyutlama ihtiyacı veya test edilebilirlik açısından gerçek fayda sağladığında kullanılmalıdır; basit CRUD işlemleri için gereksiz soyutlama oluşturulmamalıdır.
- Event Driven yaklaşım tercih edilmeli; bildirim, audit, rapor üretimi, entegrasyon ve yan etkiler domain/application eventleri üzerinden ayrıştırılmalıdır.

Commit ve branch stratejisi ekip yapısına göre detaylandırılacaktır; commit mesajları Conventional Commits formatıyla yazılacaktır. Ticari ürün geliştirmede küçük, gözden geçirilebilir ve test edilebilir değişiklikler tercih edilmelidir.

## 23. Gelecekte Eklenebilecek Modüller

Ürün büyüdükçe aşağıdaki modüller ürün yol haritasına alınacaktır:

- **Faturalama ve Tahsilat**: Sözleşme bazlı fatura üretimi, tahsilat takibi, cari hesap.
- **Teklif ve Satış CRM**: Potansiyel müşteri, teklif, satış fırsatı ve dönüşüm takibi.
- **Stok ve Yedek Parça Yönetimi**: Parça envanteri, teknisyen zimmeti, servis sırasında parça kullanımı.
- **Satın Alma Yönetimi**: Tedarikçi, sipariş ve maliyet takibi.
- **Gelişmiş SLA Yönetimi**: Müdahale süresi, çözüm süresi ve müşteri bazlı SLA raporları.
- **IoT ve Uzaktan İzleme**: Asansör sensör verileri, arıza sinyalleri ve kestirimci bakım.
- **Harita ve Rota Optimizasyonu**: Teknisyen konumları, günlük rota planlama ve yakıt/verimlilik analizi.
- **Müşteri Portalı**: Müşterinin rapor, sözleşme ve taleplerini yönetebileceği ayrı deneyim.
- **Yasal Uygunluk ve Denetim**: Regülasyon bazlı kontrol listeleri, belge süre takibi ve denetim raporları.
- **BI ve Veri Ambarı**: Yönetim raporları, trend analizi, müşteri karlılığı ve operasyonel tahminleme.
- **Entegrasyon Merkezi**: Muhasebe sistemleri, SMS sağlayıcıları, ödeme kuruluşları ve kurumsal ERP bağlantıları.
- **Çoklu Dil ve Bölge Desteği**: Farklı ülke, para birimi, tarih formatı ve mevzuat desteği.

Bu modüllerin her biri ayrı ürün kararı ve teknik mimari değerlendirme gerektirir. Ana mimari hedef, bugünkü iskeletin bu genişlemeleri taşıyabilecek kadar düzenli ve esnek kalmasıdır.
