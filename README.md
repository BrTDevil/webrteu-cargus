# webrteu/cargus

[![PHP](https://img.shields.io/badge/PHP-%5E8.2-777bb4)](https://www.php.net/)
[![Laravel](https://img.shields.io/badge/Laravel-12%E2%80%9313-ff2d20)](https://laravel.com/)
[![Licență MIT](https://img.shields.io/badge/licen%C8%9B%C4%83-MIT-green)](LICENSE.md)
[![Packagist](https://img.shields.io/packagist/v/webrteu/cargus)](https://packagist.org/packages/webrteu/cargus)

Integrare Laravel pentru Cargus (fost Urgent Cargus), API „UrgentOnlineAPI” din Azure API Management: AWB-uri,
etichete, trace pe mai multe AWB-uri, Ship & Go și comenzi de ridicare. Implementează contractele din `webrteu/shipping`.

Nu este un pachet oficial Cargus.

## Cerințe

- PHP `^8.2`, Laravel 12–13
- abonament aprobat la UrgentOnlineAPI + utilizator de client Cargus
- `webrteu/shipping`

### Acces la API

1. Cont pe `https://urgentcargus.portal.azure-api.net/` (nu se folosesc credențialele WebExpress)
2. PRODUCTS → UrgentOnlineAPI → Subscribe; echipa Cargus aprobă manual
3. După aprobare: profil → **Primary key** = `CARGUS_SUBSCRIPTION_KEY`
4. Separat: utilizatorul și parola de client Cargus, pentru `LoginUser`

## Instalare

```bash
composer require webrteu/cargus
php artisan vendor:publish --tag=cargus-config
php artisan vendor:publish --tag=cargus-migrations
php artisan migrate
php artisan cargus:pickup-locations
```

## Configurare

```dotenv
CARGUS_SUBSCRIPTION_KEY=     # Primary key din urgentcargus.portal.azure-api.net (după aprobarea abonamentului)
CARGUS_USERNAME=             # utilizator client Cargus (pentru LoginUser)
CARGUS_PASSWORD=
CARGUS_PICKUP_LOCATION_ID=   # vezi php artisan cargus:pickup-locations
CARGUS_PRICE_TABLE_ID=
CARGUS_SERVICE_ID=           # Standard / Economic Standard
CARGUS_PUDO_SERVICE_ID=      # Ship & Go
CARGUS_COD_TYPE=cash         # cash = CashRepayment, bank = BankRepayment (niciodată ambele)
CARGUS_OPEN_PACKAGE=false
CARGUS_TIMEOUT=15
```

În mediul `local`, lipsa `CARGUS_SUBSCRIPTION_KEY` oprește aplicația cu un mesaj clar. În celelalte medii, prima
cerere aruncă `CargusException` înainte de a trimite ceva.

⚠️ Numele endpoint-urilor și ale câmpurilor urmează „Documentatie API V3” și trebuie confirmate pe portal; durata token-ului
e presupusă 24h (cache 20h, re-login la 401).

## Utilizare

```php
$awb = Shipping::carrier('cargus')->createShipment($data);
$pdf = Shipping::carrier('cargus')->label($awb->awb);
```

| Metodă | Cargus |
|---|---|
| `services()` | lista `cargus.services` din config |
| `quote($data)` | `POST /ShippingCalculation` (`GrandTotal`) |
| `createShipment($data)` | `POST /Awbs`; `CustomString` = referința comenzii; 400 → `ShipmentValidationException`; timeout → `ShipmentStateUnknownException` |
| `label($awb, A4Pdf\|A6Pdf)` | `GET /AwbDocuments` (PDF binar sau base64, ambele decodate) |
| `track` / `trackMany` | `GET /AwbTrace` |
| `cancel($awb)` | `DELETE /Awbs` |
| `pickupPoints()` | Ship & Go (`cargus_pudo_points` sau `/PudoPoints`) |
| `orderPickup($request)` | `POST /Orders` ⚠️ |

Ship & Go: `pickupPointId` → `DeliveryPudoPoint` ⚠️ (câmpul exact se confirmă) + `CARGUS_PUDO_SERVICE_ID`.

## Comenzi Artisan

| Comandă | Ce face |
|---|---|
| `cargus:sync-locations` | județe + localități (`cargus_localities`; id-urile se trimit pe destinatar când există) |
| `cargus:sync-pudo` | punctele Ship & Go |
| `cargus:pickup-locations` | `LocationId`-urile contului |

## Statusuri

⚠️ `CargusStatusMap` e goală până la primele trasee reale. Statusul se deduce din descrierea evenimentului.
Suprascriere: `config('shipping.status_map.cargus')`. Fără webhook → `shipping:sync`.

## Ce nu face pachetul

- nu obține abonamentul pe portal
- nu suportă expeditori diferiți de punctele de ridicare ale contului
- nu face retry pe crearea AWB-urilor

## Testare

```bash
composer test
CARGUS_SANDBOX_SUBSCRIPTION_KEY=... CARGUS_SANDBOX_USERNAME=... CARGUS_SANDBOX_PASSWORD=... CARGUS_SANDBOX_LOCATION_ID=... vendor/bin/pest --group=sandbox
```

Fixture-urile sunt după documentație. **De înlocuit cu răspunsuri reale**.

## Changelog

Vezi [CHANGELOG.md](CHANGELOG.md).

## Autor

[WeBRTeu](https://webrteu.eu)

## Licență

MIT. Vezi [LICENSE.md](LICENSE.md).
