# Kargo Sayacı

WooCommerce tekil ürün sayfaları için geliştirilen **Kargo Sayacı**, siparişin aynı gün kargoya verilebilmesi için kalan süreyi gösteren, ürün bazlı ve takvim bazlı kapatma seçenekleri sunan hafif bir WordPress eklentisidir.

## Özellikler

- WooCommerce tekil ürün sayfalarında otomatik kargo geri sayımı
- Gün bazlı başlangıç ve bitiş saati planlama
- Bayram, resmi tatil ve özel operasyon dönemleri için tarih/saat aralığıyla otomatik kapatma
- Ürün arama alanıyla belirli ürünlerde sayacı kapatma
- Stokta olmayan veya backorder ürünlerde sayaç gizleme seçenekleri
- Yurtiçi Kargo logosu ve tahmini teslim metni
- Panelden yönetilebilir metinler ve renkler
- `{{today}}` etiketiyle "bugün" kelimesini ayrıca vurgulama
- Mobil uyumlu, minimal ve hafif ön yüz tasarımı

## Gereksinimler

- WordPress 5.8+
- WooCommerce 5.0+
- PHP 7.4+

## Kurulum

1. `kargo-sayaci` klasörünü `wp-content/plugins/` dizinine yükleyin.
2. WordPress yönetim panelinde **Eklentiler** ekranından **Kargo Sayacı** eklentisini etkinleştirin.
3. Sol menüden **Kargo Sayacı** ayar sayfasına gidin.
4. Günlük planlama, renkler, metinler ve özel kapatma ayarlarını düzenleyin.

## Kullanım

Eklenti yalnızca WooCommerce tekil ürün sayfalarında çalışır. Sayaç, ayarlanan gün ve saat aralığı içindeyse ürün sayfasında görünür. Süre dolduğunda, ilgili gün kapalıysa, özel kapatma tarihindeyse veya ürün ayarlara göre gizlenmeliyse otomatik olarak görünmez.

### Metin Etiketi

Vurgulu metin alanında `{{today}}` kullanabilirsiniz.

Örnek:

```text
kargon {{today}} yola çıksın!
```

Bu ifade ön yüzde şu şekilde görünür:

```text
kargon bugün yola çıksın!
```

`bugün` kelimesinin rengi ayrı olarak **"Bugün" vurgu rengi** ayarından yönetilir.

## Ayarlar

- **Genel Durum:** Sayacı genel olarak açıp kapatır.
- **Normal Metin:** Sayaç süresinden sonra gelen normal metin.
- **Vurgulu Metin:** Kargo mesajının vurgulu kısmı.
- **Tahmini Teslim Metni:** Logo yanında gösterilen teslim mesajı.
- **Sayaç Kapalı Ürünler:** Ürün arama alanından seçilen ürünlerde sayaç gösterilmez.
- **Renkler:** Üst bant, kart zemini, alt alan zemini, kenarlık, logo kutusu, metin ve teslim alanı renkleri ayrı ayrı düzenlenebilir.
- **Stok Kuralları:** Backorder veya stokta olmayan ürünlerde sayaç gizlenebilir.
- **Özel Gün Kapatmaları:** Tarih ve saat aralığına göre sayaç otomatik kapatılabilir.
- **Günlük Planlama:** Haftanın her günü için ayrı başlangıç/bitiş saati belirlenebilir.

## Performans

Eklenti yalnızca tekil ürün sayfalarında gerekli CSS ve JavaScript dosyalarını yükler. Ürün seçme alanı sadece yönetim panelindeki ayar sayfasında çalışır; müşteri tarafında ürün arama sorgusu oluşturmaz.

## Dosya Yapısı

```text
kargo-sayaci/
├── assets/
│   ├── css/
│   │   ├── admin.css
│   │   └── frontend.css
│   ├── js/
│   │   ├── admin.js
│   │   └── frontend.js
│   ├── icon*.png
│   └── yurtici-kargo.png
├── kargo-sayaci.php
└── README.md
```

## Sürüm

Mevcut sürüm: **1.6**

## Lisans

Bu proje kişisel/özel kullanım için hazırlanmıştır. Lisans ihtiyacınıza göre ayrıca bir lisans dosyası ekleyebilirsiniz.

