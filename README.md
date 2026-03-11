# Global IP Monitor - WHMCS Addon

Müşteri giriş IP adreslerini ve portlarını loglar.

## Kurulum

1. `global_ip_monitor` klasörünü `modules/addons/` altına kopyala
2. **Admin Panel** → Kurulum → Eklenti Modülleri → Global IP Monitor → **Aktif Et**
3. Eklentiler → Global IP Monitor

## Gereksinimler

- WHMCS 8.x
- PHP 7.4+
- MySQL 5.6+

## Yapı

```
modules/addons/global_ip_monitor/
├── global_ip_monitor.php
├── hooks.php
├── lang/turkish.php
└── assets/
    ├── css/style.css
    └── js/monitor.js
```

## Kaydedilen Bilgiler

| Alan | Açıklama |
|------|----------|
| `ip_address` | Gerçek IP (Cloudflare/proxy destekli) |
| `port` | Bağlantı portu |
| `hostname` | rDNS |
| `user_agent` | Tarayıcı bilgisi |
| `login_type` | client\_area / admin\_X |
| `login_at` | Tarih ve saat |

<img width="1359" height="706" alt="image" src="https://github.com/user-attachments/assets/3f3315ff-3943-464e-be3a-f1f260db9d08" />


## Kaldırma

Eklenti Modülleri → Devre Dışı Bırak (DB tablosu otomatik silinir)
