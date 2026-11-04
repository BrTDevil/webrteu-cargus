# Changelog

## v1.0.0 - 2026-11-04

- Prima versiune stabilă (1.0.0). Fără schimbări de API față de 0.1.x.
- Descriere și keywords unificate pentru Packagist; homepage corectat.
- Necesită `webrteu/shipping` ^1.0.

## v0.1.x

### Adăugat
- `CargusCarrier` (UrgentOnlineAPI): cost, AWB, etichetă PDF (binar sau base64), trace multiplu, anulare, Ship & Go, comandă de ridicare.
- Header-ele `Ocp-Apim-Subscription-Key` + Bearer pe fiecare cerere; token din `LoginUser` în cache, un singur re-login la 401.
- Eroare clară în `local` când lipsește cheia de abonament.
- Comenzile `cargus:sync-locations`, `cargus:sync-pudo`, `cargus:pickup-locations`.
