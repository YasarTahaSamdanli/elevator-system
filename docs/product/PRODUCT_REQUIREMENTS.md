# Product Requirements Document

# Asansör Bakım ve Servis Yönetim Sistemi

Bu doküman, Asansör Bakım ve Servis Yönetim Sistemi ürününün ne yapacağını kullanıcı, süreç ve iş değeri açısından tanımlar. Doküman teknik uygulama detayı, veritabanı tasarımı, API tasarımı veya framework kararı içermez.

## 1. Ürün Amacı

Ürünün amacı, ilk sürümde tek bir asansör bakım firmasının tüm bakım, arıza, servis, müşteri, tesis, asansör, saha ekibi ve raporlama süreçlerini tek merkezden yönetmesini sağlamaktır. Mimari, gelecekte birden fazla firma kullanımına genişletilebilecek şekilde korunmalıdır.

Sistem; bakım takibini manuel defter, Excel, telefon görüşmesi ve dağınık mesajlaşma süreçlerinden çıkararak izlenebilir, ölçülebilir ve denetlenebilir hale getirmelidir. Kullanıcılar hangi asansörün ne zaman bakıma gireceğini, hangi iş emrinin kimde olduğunu, hangi arızanın beklediğini ve hangi müşterinin hangi hizmeti aldığını açıkça görebilmelidir.

Ürün, bakım firmasına şu temel değerleri sunmalıdır:

- Bakım kaçaklarını ve gecikmeleri azaltmak.
- Arıza müdahale süresini kısaltmak.
- Teknisyenlerin günlük işlerini düzenlemek.
- Müşteri iletişimini kayıt altına almak.
- Servis ve bakım geçmişini güvenilir şekilde saklamak.
- Yöneticiye operasyonel ve ticari görünürlük sağlamak.
- Saha işlemlerini fotoğraf, imza, QR kod ve raporlarla kanıtlanabilir hale getirmek.

## 2. Hedef Kullanıcılar

Ürün aşağıdaki kullanıcı grupları için tasarlanır:

- **Asansör bakım firması sahipleri**: Operasyonu, müşteri memnuniyetini, ekip performansını ve hizmet karlılığını izlemek ister.
- **Operasyon yöneticileri**: Günlük bakım ve arıza akışını planlamak, ekipleri yönlendirmek ve gecikmeleri yönetmek ister.
- **Dispeçer / planlama personeli**: Gelen talepleri iş emrine çevirmek, teknisyen atamak ve takvimi yönetmek ister.
- **Teknisyenler**: Kendilerine atanan işleri kolayca görmek, sahada işlem yapmak, fotoğraf/imza eklemek ve işi kapatmak ister.
- **Müşteri yetkilileri**: Kendi tesislerine ait bakım durumunu, arıza taleplerini ve servis raporlarını görmek ister.
- **Muhasebe / finans kullanıcıları**: Sözleşme, hizmet kapsamı, faturalama ve tahsilat süreçlerine girdi olacak kayıtları takip etmek ister.
- **Denetçi veya kalite sorumluları**: Yapılan işlerin kanıtlarını, bakım geçmişini ve audit kayıtlarını incelemek ister.

## 3. Kullanıcı Rolleri

Ürün rol bazlı çalışma mantığını desteklemelidir. Her kullanıcı yalnızca sorumluluğu ve yetkisi dahilindeki bilgileri görmelidir.

- **Sistem Yöneticisi**: İlk sürümde kurulum, sistem seviyesi teknik ayarlar ve destek süreçlerini yönetir. Gelecekte çoklu firma desteği gelirse platform seviyesinde yönetim rolüne genişleyebilir.
- **Firma Sahibi**: Kendi firmasının kullanıcılarını, müşterilerini, modül erişimlerini ve firma ayarlarını yönetir.
- **Firma Yöneticisi**: Tüm operasyonu izler, raporları görüntüler, ekiplerin iş yükünü takip eder.
- **Operasyon Sorumlusu**: Servis taleplerini değerlendirir, bakım planlarını takip eder, iş emirlerini oluşturur ve atar.
- **Teknisyen**: Kendisine atanmış işleri görür, QR kod okutur, checklist doldurur, fotoğraf/imza ekler ve işi tamamlar.
- **Müşteri Yetkilisi**: Kendi tesisleri, asansörleri, talepleri ve raporlarıyla sınırlı şekilde sisteme erişir.
- **Finans Kullanıcısı**: Sözleşme ve hizmet kayıtlarını finansal takip amacıyla inceler.
- **Read-Only Kullanıcı**: Denetim veya gözlem amacıyla kayıtları değiştirmeden görüntüler.

## 4. Kullanıcı Senaryoları (User Stories)

### Firma Sahibi

- Firma sahibi olarak tüm müşterilerimin bakım durumunu tek ekranda görmek isterim; böylece hangi müşterilerde gecikme veya risk olduğunu anlayabilirim.
- Firma sahibi olarak teknisyen performanslarını görmek isterim; böylece ekip kapasitesini ve iş dağılımını daha doğru yönetebilirim.
- Firma sahibi olarak sözleşmesi bitmek üzere olan müşterileri görmek isterim; böylece yenileme sürecini kaçırmam.

### Operasyon Sorumlusu

- Operasyon sorumlusu olarak yaklaşan bakımları takvimde görmek isterim; böylece ekip planlamasını önceden yapabilirim.
- Operasyon sorumlusu olarak yeni gelen arıza talebini uygun teknisyene atamak isterim; böylece müdahale süresi kısalır.
- Operasyon sorumlusu olarak açık iş emirlerini durumlarına göre filtrelemek isterim; böylece bekleyen işleri hızlıca yönetebilirim.

### Teknisyen

- Teknisyen olarak günlük görev listemi mobil uygulamada görmek isterim; böylece hangi müşteriye, hangi adrese ve hangi asansöre gideceğimi bilirim.
- Teknisyen olarak sahada QR kod okutarak doğru asansörde olduğumu doğrulamak isterim; böylece yanlış varlık üzerinde işlem yapma riski azalır.
- Teknisyen olarak bakım checklistini mobilde doldurmak isterim; böylece yapılan kontrol maddeleri kayıt altına alınır.
- Teknisyen olarak fotoğraf ve müşteri imzası eklemek isterim; böylece yaptığım işi kanıtlayabilirim.
- Teknisyen olarak internet bağlantısı olmadığında işlemi kaydedip sonra senkronize etmek isterim; böylece saha işi bağlantıya bağımlı kalmaz.

### Müşteri Yetkilisi

- Müşteri yetkilisi olarak kendi tesislerime ait asansörleri görmek isterim; böylece hizmet kapsamımı takip edebilirim.
- Müşteri yetkilisi olarak arıza talebi oluşturmak isterim; böylece bakım firmasına hızlıca ulaşabilirim.
- Müşteri yetkilisi olarak servis raporlarını görüntülemek isterim; böylece yapılan işlemleri denetleyebilirim.

### Denetçi / Kalite Sorumlusu

- Denetçi olarak bakım geçmişini ve servis kanıtlarını görmek isterim; böylece işlemlerin zamanında ve eksiksiz yapıldığını kontrol edebilirim.
- Kalite sorumlusu olarak eksik checklist, eksik fotoğraf veya imzasız raporları görmek isterim; böylece kalite süreçlerini iyileştirebilirim.

## 5. Sistem Modülleri

V1 ürün kapsamı aşağıdaki modüllerden oluşur:

- Company (Firma) yönetimi.
- Kullanıcı, rol ve yetki yönetimi.
- Müşteri yönetimi.
- Tesis, bina ve lokasyon yönetimi.
- Asansör envanteri.
- QR kod / asansör kimliği yönetimi.
- Sözleşme ve hizmet kapsamı takibi.
- Periyodik bakım planlama.
- Arıza ve servis talebi yönetimi.
- İş emri yönetimi.
- Mobil saha operasyonları.
- Checklist yönetimi.
- Dosya, fotoğraf, imza ve rapor yönetimi.
- Bildirimler.
- Raporlama.
- Audit ve işlem geçmişi.

## 6. Her Modülün Yapacağı İşler

### Company (Firma) Yönetimi

- Bakım firmasının temel bilgilerini tutar.
- Firma kullanıcılarını ve yetki sınırlarını yönetir.
- Firma ayarlarını, çalışma saatlerini ve operasyonel tercihleri saklar.
- V1'de abonelik, paket, kullanım limiti veya plan yönetimi içermez; bu bilgiler gelecekte çoklu firma ya da SaaS modeli gerektiğinde ayrı modül olarak değerlendirilebilir.

### Kullanıcı, Rol ve Yetki Yönetimi

- Kullanıcı hesaplarını oluşturur, davet eder, pasifleştirir.
- Kullanıcılara rol ve izin atar.
- Her kullanıcının yalnızca yetkili olduğu ekran ve kayıtlara erişmesini sağlar.
- Teknisyen, müşteri yetkilisi ve yönetici deneyimlerini birbirinden ayırır.

### Müşteri Yönetimi

- Bakım firmasının hizmet verdiği müşteri kayıtlarını tutar.
- Müşteri iletişim bilgilerini, yetkililerini ve hizmet kapsamını gösterir.
- Müşteriye bağlı tesis, asansör, sözleşme ve talepleri ilişkilendirir.

### Tesis, Bina ve Lokasyon Yönetimi

- Müşteriye ait site, bina, blok, adres ve konum bilgilerini yönetir.
- Her asansörün hangi fiziksel lokasyonda olduğunu netleştirir.
- Teknisyenin sahaya giderken doğru adrese ulaşmasına yardımcı olur.

### Asansör Envanteri

- Asansörün teknik ve operasyonel bilgilerini tutar.
- Marka, model, seri numarası, kapasite, bakım periyodu ve durum bilgilerini gösterir.
- Asansöre bağlı bakım geçmişini, arıza kayıtlarını ve servis raporlarını görüntüler.

### QR Kod / Asansör Kimliği Yönetimi

- Her asansör için benzersiz bir kimlik oluşturur.
- QR kod ile sahada doğru asansör doğrulaması yapılmasını sağlar.
- QR kod okutulduğunda ilgili asansör, açık iş emirleri ve geçmiş kayıtlar kullanıcı yetkisine göre gösterilir.
- Sahada yanlış asansör üzerinde işlem yapılmasını önlemeye yardımcı olur.

### Sözleşme ve Hizmet Kapsamı Takibi

- Müşteri veya tesis bazlı hizmet sözleşmelerini izler.
- Sözleşme başlangıç, bitiş, bakım periyodu ve hizmet kapsamını gösterir.
- Süresi yaklaşan veya biten sözleşmeler için operasyon ve yönetim kullanıcılarını uyarır.

### Periyodik Bakım Planlama

- Asansörlerin bakım periyotlarını takip eder.
- Yaklaşan, zamanı gelen ve geciken bakımları gösterir.
- Bakım planlarından iş emri oluşturulmasını sağlar.
- Operasyon ekibine takvim ve iş yükü görünürlüğü sunar.

### Arıza ve Servis Talebi Yönetimi

- Müşteri, operasyon ekibi veya yetkili kullanıcılar tarafından arıza talebi oluşturulmasını sağlar.
- Talebin önceliğini, açıklamasını, lokasyonunu ve ilgili asansörünü kaydeder.
- Talebin iş emrine dönüştürülmesini ve takibini sağlar.

### İş Emri Yönetimi

- Bakım, arıza, kontrol ve özel servis işleri için iş emri oluşturur.
- İş emrini teknisyene veya ekibe atar.
- İş emrinin durumunu, önceliğini, planlanan zamanını ve tamamlanma bilgisini takip eder.
- Yöneticiye açık, gecikmiş, tamamlanmış ve iptal edilmiş işleri gösterir.

### Mobil Saha Operasyonları

- Teknisyenin günlük görevlerini mobilde görüntülemesini sağlar.
- İş emri detayına, müşteri bilgisine, lokasyon bilgisine ve asansör kimliğine erişim sunar.
- QR kod okuma, checklist doldurma, fotoğraf ekleme, imza alma ve iş kapatma işlemlerini destekler.
- Bağlantı olmadığında saha işlemlerinin kaybolmamasını sağlar.

### Checklist Yönetimi

- Bakım ve servis türlerine göre kontrol listeleri tanımlar.
- Teknisyenin sahada adım adım kontrol yapmasını sağlar.
- Zorunlu maddeler tamamlanmadan işin kapatılmasını engelleyebilir.
- Eksik veya uygunsuz cevapları raporlama için görünür hale getirir.

### Dosya, Fotoğraf, İmza ve Rapor Yönetimi

- İş emri, asansör, müşteri ve sözleşme ile ilişkili dosyaları saklar.
- Servis fotoğraflarını, müşteri imzalarını, belgeleri ve raporları ilişkilendirir.
- Yetkili kullanıcıların dosyaları görüntülemesini veya indirmesini sağlar.

### Bildirimler

- Kullanıcıları yeni iş emri, yaklaşan bakım, geciken bakım, yeni arıza ve rapor onayı gibi olaylarda bilgilendirir.
- Kanal tercihlerine göre uygulama içi bildirim, e-posta, SMS veya mobil push senaryolarını destekler.

### Raporlama

- Bakım uyumluluğu, arıza yoğunluğu, iş emri durumu, teknisyen performansı ve müşteri bazlı hizmet geçmişi sunar.
- Yöneticiye operasyonun genel sağlığını gösterir.
- Müşteri yetkilisine kendi tesisleriyle sınırlı raporlar sunar.

### Audit ve İşlem Geçmişi

- Kritik işlemlerin kim tarafından ve ne zaman yapıldığını gösterir.
- İş emri durum değişiklikleri, kullanıcı yetki değişiklikleri ve önemli kayıt güncellemelerini izler.
- Denetim ve kalite kontrol süreçlerine kanıt sağlar.

## 7. Bakım Süreci (Başlangıçtan Bitişe)

Bakım süreci, planlı ve kanıtlanabilir bir saha operasyonu olarak çalışmalıdır.

1. Asansör envantere eklenir ve bakım periyodu belirlenir.
2. Müşteri, tesis ve sözleşme bilgileri asansörle ilişkilendirilir.
3. Sistem yaklaşan bakım tarihlerini operasyon ekibine gösterir.
4. Operasyon sorumlusu bakım için iş emri oluşturur veya planlanan bakımdan iş emri üretir.
5. İş emri uygun teknisyene atanır.
6. Teknisyen mobil uygulamada görevi görür.
7. Teknisyen sahaya gider ve QR kod okutarak doğru asansörü doğrular.
8. Teknisyen bakım checklistini doldurur.
9. Gerekli fotoğrafları ve notları ekler.
10. Müşteri onayı veya imzası gerekiyorsa mobil uygulama üzerinden alır.
11. Teknisyen işi tamamlandı olarak işaretler.
12. Sistem servis raporunu oluşturur veya rapor için gerekli verileri hazırlar.
13. Operasyon ekibi tamamlanan işi kontrol eder.
14. Müşteri yetkilisi servis raporunu görüntüler.
15. Bakım geçmişi asansör kaydında saklanır.

Kullanıcı açısından bakım süreci; planlama, sahada doğru varlığı doğrulama, standarda uygun kontrol yapma ve kanıtlı raporlama adımlarını kesintisiz sunmalıdır.

## 8. Arıza Süreci

Arıza süreci hızlı kayıt, doğru önceliklendirme ve izlenebilir müdahale üzerine kurulmalıdır.

1. Müşteri yetkilisi, operasyon ekibi veya yetkili kullanıcı arıza talebi oluşturur.
2. Talepte müşteri, tesis, asansör, arıza açıklaması, öncelik ve varsa fotoğraf bilgisi yer alır.
3. Operasyon sorumlusu talebi inceler.
4. Talep uygun görülürse iş emrine dönüştürülür.
5. İş emri uygun teknisyene atanır.
6. Teknisyen mobil uygulamada arıza görevini görür.
7. Teknisyen sahada QR kod ile asansörü doğrular.
8. Arıza tespiti yapılır, not ve fotoğraf eklenir.
9. Sorun çözüldüyse iş emri tamamlanır.
10. Sorun çözülemediyse ek parça, ikinci ziyaret veya yönetici onayı gibi takip aksiyonu oluşturulur.
11. Müşteri arıza durumundan ve tamamlanma bilgisinden haberdar edilir.
12. Arıza geçmişi asansör ve müşteri kayıtlarında görünür hale gelir.

Arıza süreci kullanıcı açısından hızlı ve sade olmalıdır. Müşteri talep oluştururken karmaşık formlarla uğraşmamalı, operasyon ekibi ise talebi kolayca önceliklendirip sahaya aktarabilmelidir.

## 9. QR Kod Süreci

QR kod süreci, sahadaki fiziksel asansör ile sistemdeki dijital kaydın eşleştirilmesini sağlar.

1. Her asansör için benzersiz QR kimliği oluşturulur.
2. QR kod fiziksel asansöre veya uygun görülen teknik noktaya yerleştirilir.
3. Teknisyen sahaya gittiğinde mobil uygulama ile QR kodu okutur.
4. Sistem teknisyenin ilgili asansör ve iş emrine erişim yetkisini kontrol eder.
5. Doğru iş emri ve doğru asansör eşleşirse teknisyen işleme devam eder.
6. Yanlış asansör veya yetkisiz erişim varsa kullanıcı uyarılır.
7. QR okutma bilgisi iş emri geçmişine kanıt olarak eklenir.

QR kod kullanıcı açısından şu faydaları sağlar:

- Teknisyen doğru asansörde işlem yaptığını doğrular.
- Operasyon ekibi sahadaki işlemin fiziksel varlıkla eşleştiğini görür.
- Müşteri yapılan bakımın kendi asansörü üzerinde gerçekleştiğini kanıtlı şekilde takip eder.
- Bakım ve arıza geçmişi hızlıca ilgili asansör üzerinden açılır.

## 10. İş Emri (Work Order) Yaşam Döngüsü

İş emri, saha operasyonunun merkezindeki kayıttır. Yaşam döngüsü kullanıcıya açık ve izlenebilir olmalıdır.

Temel durumlar:

- **Draft**: İş emri oluşturulmuştur ancak henüz planlanmamış veya atanma hazır değildir.
- **Planned**: İşin yapılacağı tarih veya zaman aralığı belirlenmiştir.
- **Assigned**: İş teknisyene veya ekibe atanmıştır.
- **Accepted**: Teknisyen işi kabul etmiştir.
- **En Route**: Teknisyen sahaya gitmektedir.
- **On Site**: Teknisyen sahaya ulaşmıştır.
- **In Progress**: İş üzerinde aktif çalışma yapılmaktadır.
- **Waiting**: Parça, müşteri onayı, ek ekip veya yönetici kararı beklenmektedir.
- **Completed**: Teknisyen işi tamamlamıştır.
- **Reviewed**: Operasyon veya yönetici işi kontrol etmiştir.
- **Closed**: İş tamamen kapatılmıştır.
- **Cancelled**: İş iptal edilmiştir.

İş emri yaşam döngüsü aşağıdaki bilgileri kullanıcıya göstermelidir:

- İşin kimde olduğu.
- Hangi müşteri, tesis ve asansöre ait olduğu.
- Planlanan ve gerçekleşen zamanlar.
- Öncelik ve gecikme durumu.
- Yapılan işlemler.
- Checklist cevapları.
- Eklenen fotoğraf, imza ve belgeler.
- Müşteri bilgilendirme durumu.

## 11. Bildirim Senaryoları

Bildirimler kullanıcıyı doğru zamanda, doğru aksiyona yönlendirmelidir.

V1 bildirim senaryoları:

- Yeni iş emri teknisyene atandığında teknisyene bildirim gönderilir.
- İş emri zamanı yaklaştığında teknisyen bilgilendirilir.
- Bakım tarihi yaklaşan asansörler için operasyon ekibi uyarılır.
- Bakım geciktiğinde operasyon sorumlusu ve yönetici bilgilendirilir.
- Yeni arıza talebi oluşturulduğunda operasyon ekibi bilgilendirilir.
- Yüksek öncelikli arıza oluşturulduğunda yöneticiye de bildirim gönderilir.
- Teknisyen işi tamamladığında operasyon sorumlusu bilgilendirilir.
- Servis raporu hazır olduğunda müşteri yetkilisi bilgilendirilir.
- Sözleşme bitiş tarihi yaklaştığında firma yöneticisi uyarılır.
- Offline kaydedilen iş başarıyla senkronize olduğunda teknisyene bilgi verilir.
- Senkronizasyon hatası olduğunda teknisyene ve gerekirse operasyon ekibine uyarı gösterilir.

Bildirimler gereksiz tekrar üretmemeli, kullanıcıyı yormamalı ve kritik aksiyonları önceliklendirmelidir.

## 12. Offline Çalışma Gereksinimleri

Mobil uygulama saha şartlarında internet bağlantısının zayıf veya kesik olabileceğini kabul etmelidir.

Offline gereksinimler:

- Teknisyen kendisine atanmış işlerin temel bilgilerini bağlantı yokken görebilmelidir.
- İş emri detayları, müşteri adresi, asansör bilgisi ve checklist sahada erişilebilir olmalıdır.
- Teknisyen checklist cevaplarını offline kaydedebilmelidir.
- Fotoğraf, not ve imza offline olarak geçici saklanabilmelidir.
- Bağlantı geldiğinde offline kayıtlar otomatik senkronize edilmelidir.
- Senkronizasyon durumu kullanıcıya açıkça gösterilmelidir.
- Çakışma veya hata oluşursa kullanıcı anlaşılır şekilde yönlendirilmelidir.
- Offline tamamlanan işlerin kaybolmaması temel ürün gereksinimidir.

Offline mod tüm sistemi kapsamak zorunda değildir. Öncelik, teknisyenin sahadaki işini kesintisiz tamamlayabilmesidir.

## 13. Raporlama Gereksinimleri

Raporlama, yöneticinin operasyonu ölçmesine ve müşterinin hizmeti denetlemesine yardımcı olmalıdır.

V1 raporları:

- Günlük, haftalık ve aylık iş emri özeti.
- Açık, gecikmiş, tamamlanmış ve iptal edilmiş iş emirleri.
- Müşteri bazlı bakım ve arıza geçmişi.
- Asansör bazlı servis geçmişi.
- Teknisyen bazlı tamamlanan iş sayısı ve gecikme durumu.
- Bakım uyumluluk raporu.
- Arıza yoğunluğu raporu.
- Sözleşmesi bitmek üzere olan müşteriler.
- Eksik checklist, eksik fotoğraf veya imzasız servis raporları.

Raporlar kullanıcı açısından filtrelenebilir, anlaşılır ve dışa aktarılabilir olmalıdır. Müşteri yetkilileri yalnızca kendi tesislerine ait raporları görebilmelidir.

## 14. Performans Gereksinimleri

Ürün günlük operasyon içinde hızlı ve güvenilir çalışmalıdır.

Performans beklentileri:

- Kullanıcılar ana operasyon ekranlarında bekleme hissi yaşamamalıdır.
- Liste ekranları büyük veri setlerinde filtreleme ve sayfalama ile çalışmalıdır.
- Mobil uygulama düşük kaliteli bağlantılarda da kullanılabilir olmalıdır.
- Fotoğraf yükleme işlemleri kullanıcıyı uzun süre bloke etmemelidir.
- Dashboard ve rapor ekranları yöneticiye makul sürede sonuç vermelidir.
- QR kod okutma ve iş emri açma işlemleri sahada hızlı gerçekleşmelidir.
- Aynı anda birden fazla teknisyen ve operasyon kullanıcısı çalışırken sistem tutarlı kalmalıdır.

Performans gereksinimleri kullanıcı deneyimi üzerinden ölçülmelidir: teknisyen sahada beklememeli, operasyon ekibi liste ve takvimlerde takılmamalı, müşteri rapora erişirken gecikme yaşamamalıdır.

## 15. Güvenlik Gereksinimleri

Sistem ilk sürümde tek bir bakım firması tarafından kullanılacak olsa da güvenlik temel gereksinimdir. Firma içi rol sınırları, müşteri yetkilisi erişimi ve gelecekte çoklu firma desteğine genişleyebilecek veri ayrımı baştan korunmalıdır.

Güvenlik gereksinimleri:

- Company (Firma) kapsamı korunmalı; gelecekte çoklu firma desteği geldiğinde her firma yalnızca kendi verilerine erişebilmelidir.
- Müşteri yetkilisi yalnızca kendi tesislerini ve raporlarını görebilmelidir.
- Teknisyen yalnızca kendisine atanmış işler ve gerekli minimum müşteri bilgisine erişebilmelidir.
- Rol ve izinler açık şekilde yönetilebilmelidir.
- Kritik işlemler işlem geçmişine yazılmalıdır.
- Kullanıcı girişleri güvenli şekilde yapılmalı ve başarısız girişler izlenmelidir.
- Dosya, fotoğraf, imza ve rapor erişimleri yetki kontrollü olmalıdır.
- Hassas bilgiler yetkisiz kullanıcıya gösterilmemelidir.
- Silme, iptal, rol değiştirme ve sözleşme güncelleme gibi kritik işlemler denetlenebilir olmalıdır.
- Mobil cihazda saklanan veriler minimumda tutulmalıdır.

Güvenlik kullanıcı açısından güven ve sınır anlamına gelir: firma sahibi kurumsal verinin yetkisiz kişilerle karışmayacağından emin olmalı, müşteri yalnızca kendi tesislerini görmeli, teknisyen ise sadece işini yapacak kadar bilgiye erişmelidir.

## 16. V1 Kapsamında Olacak Özellikler

V1, bakım firmasının temel operasyonunu uçtan uca yönetebileceği çekirdek ürünü kapsar.

V1 özellikleri:

- Firma hesabı ve temel firma ayarları.
- Kullanıcı, rol ve yetki yönetimi.
- Müşteri kayıtları.
- Tesis, bina ve lokasyon kayıtları.
- Asansör envanteri.
- Asansör QR kimliği.
- Sözleşme ve bakım periyodu bilgileri.
- Periyodik bakım takibi.
- Arıza talebi oluşturma ve takip.
- İş emri oluşturma, atama ve durum yönetimi.
- Teknisyen mobil görev listesi.
- QR kod ile asansör doğrulama.
- Bakım ve servis checklistleri.
- Fotoğraf, belge ve imza ekleme.
- Servis raporu görüntüleme.
- Temel bildirimler.
- Offline saha kaydı ve senkronizasyon.
- Temel operasyon raporları.
- Audit ve işlem geçmişi.

V1'in amacı tüm olası modülleri bitirmek değil, bakım ve arıza operasyonunu güvenilir, izlenebilir ve kullanılabilir hale getirmektir.

## 17. V2 ve Sonraki Sürümlere Bırakılacak Özellikler

V2 ve sonrası, çekirdek operasyon başarıyla oturduktan sonra ürünün ticari ve analitik kabiliyetlerini genişletecektir.

Sonraki sürümlere bırakılacak özellikler:

- Gelişmiş faturalama ve tahsilat yönetimi.
- Cari hesap ve muhasebe entegrasyonları.
- Teklif ve satış CRM modülü.
- Stok ve yedek parça yönetimi.
- Teknisyen zimmet ve araç envanteri.
- Satın alma ve tedarikçi yönetimi.
- Gelişmiş SLA yönetimi.
- Harita tabanlı rota optimizasyonu.
- Canlı teknisyen konum takibi.
- IoT cihazlarıyla uzaktan asansör izleme.
- Kestirimci bakım ve arıza tahmini.
- Gelişmiş müşteri portalı.
- Çoklu dil ve ülke/bölge uyarlamaları.
- Gelişmiş BI dashboardları.
- Otomatik rapor gönderimi.
- WhatsApp ve gelişmiş mesajlaşma entegrasyonları.
- Kurumsal ERP entegrasyonları.
- Çoklu firma desteği, abonelik/paket yönetimi, firma kullanım limitleri ve SaaS operasyon kabiliyetleri.
- Dedicated firma kurulumu veya özel deployment seçenekleri.

Bu özellikler ürünün büyüme yol haritasında değerlendirilecektir. Öncelik, V1'de bakım firmalarının günlük operasyonunu gerçekten çalışır ve güvenilir şekilde yönetebilmesidir.
