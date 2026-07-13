# Rapor Yazdırma Ajanı

Ofisteki bir Windows PC'de çalışan küçük Python scripti. Backend'in
`print-jobs` kuyruğunu 30 saniyede bir yoklar; bekleyen işin rapor PDF'ini
indirir, **ilk 2 sayfasını siyah-beyaz** olarak yazıcıya basar ve işi
`done`/`failed` olarak işaretler.

RoyalCert raporu mailden içeri alındığında backend otomatik bir print job
kuyruğa koyar (`INSPECTION_IMPORT_AUTO_PRINT=true` ise). Web arayüzünden
"yeniden yazdır" da aynı kuyruğa iş ekler.

## Kurulum (ofis PC'si)

1. Python 3.10+ kurulu olmalı.
2. Bağımlılıklar:

   ```
   pip install -r requirements.txt
   ```

3. Ayar dosyası:

   ```
   copy config.example.ini config.ini
   ```

   `config.ini` içine `api_url` ve `token` girin. `printer` boş kalırsa
   Windows varsayılan yazıcısı kullanılır.

4. Çalıştır:

   ```
   python print_agent.py
   ```

   Kalıcı çalışması için Görev Zamanlayıcı'ya "oturum açılışında başlat"
   görevi olarak eklenebilir.

## Ajan kullanıcısı ve token

Ajan, API'ye normal bir kullanıcı gibi Sanctum token ile bağlanır. Ayrı bir
"Yazıcı Ajanı" kullanıcısı açıp uzun ömürlü token üretin:

```
php artisan tinker
>>> $u = App\Models\User::create([...ajan kullanıcısı...]);
>>> $u->createToken('print-agent')->plainTextToken;
```

Çıkan değeri `config.ini` → `token` alanına yapıştırın. Token sızarsa
`php artisan tinker` ile ilgili kullanıcının tokenlarını silip yenisini
üretin (`$u->tokens()->delete()`).

## Davranış detayları

- İş sahiplenme yarışı güvenlidir: `PATCH status=printing` 422 dönerse işi
  başka bir ajan almıştır, atlanır.
- Ajan yazdırma ortasında ölürse iş 15 dakika sonra tekrar `pending`
  listesinde görünür ve yeniden denenir (`attempts` sayacı artar).
- Yazdırma hatası işi `failed` yapar ve hata mesajını kaydeder; web
  arayüzünden yeni bir print job açılarak tekrar denenebilir.
