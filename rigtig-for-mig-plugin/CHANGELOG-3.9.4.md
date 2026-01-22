# Changelog v3.9.4

**Release Date:** 22. januar 2026

## 🐛 Fejlrettelser

### Søgefunktionalitet - Kritiske Fixes

#### 1. Navn-søgning virker ikke korrekt
**Problem:** Søgning efter ekspertnavn (f.eks. "Frank") fandt ikke alle eksperter med det navn.

**Årsag:**
- `extend_expert_search()` funktionen brugte `get_query_var('post_type')` i stedet for `$query->get('post_type')`
- Dette gjorde at den udvidede søgning ikke blev aktiveret, så den søgte kun i standard WordPress felter

**Løsning:**
- Ændret til `$query->get('post_type')` i `public/class-rfm-public.php:163`
- Nu søger systemet korrekt i:
  - post_title (ekspertnavn)
  - post_content (beskrivelse)
  - Taxonomies (kategorier & specialiseringer)
  - Meta fields (om mig tekst)

**Testresultat:** Søgning efter "Frank" finder nu både "Frank Hansen" og "Frank Hansen Tessin" korrekt.

---

#### 2. Lokations-søgning finder ikke eksperter i bestemte byer
**Problem:** Søgning efter bynavn (f.eks. "odense") returnerede ingen resultater, selvom eksperter bor i den by.

**Årsag:**
- Systemet forsøgte kun at matche lokation som postnummer
- Hvis det fejlede (fordi "odense" ikke er et postnummer), faldt det tilbage til søgning i `_rfm_city` meta felt
- Men hvis brugeren havde tastet byen lidt anderledes (f.eks. "Odense NØ"), matchede det ikke

**Løsning:**
- Tilføjet ny metode `get_coordinates_by_city()` i `includes/class-rfm-postal-codes.php`
- Metoden søger i postnummer-databasen efter bynavn
- Søgningen er case-insensitive og understøtter partial matches
- Opdateret lokations-filter i `public/class-rfm-public.php` til at bruge den nye metode

**Funktionalitet:**
```php
// Ny søgelogik:
1. Forsøg at finde som postnummer (f.eks. "5240")
2. Hvis ikke fundet: Søg efter bynavn i postnummer-database (f.eks. "odense" → "Odense NØ")
3. Hvis fundet: Brug koordinater til radius-søgning
4. Hvis ikke fundet: Fallback til _rfm_city meta field søgning
```

**Testresultat:** Søgning efter "odense" finder nu alle eksperter i Odense-området korrekt.

---

## 🔧 Tekniske Ændringer

### Modificerede Filer

**public/class-rfm-public.php:**
- Linje 163: Rettet `get_query_var('post_type')` → `$query->get('post_type')`
- Linje 94-132: Opdateret lokations-filter til at bruge city name lookup

**includes/class-rfm-postal-codes.php:**
- Tilføjet ny metode `get_coordinates_by_city($city_name)` (linje 753-796)
- Understøtter både exact match og partial match
- Case-insensitive søgning

**rigtig-for-mig.php:**
- Version bump til 3.9.4

---

## 📊 Ny Funktionalitet

### RFM_Postal_Codes::get_coordinates_by_city()

Ny metode til at finde koordinater baseret på bynavn:

```php
/**
 * Find coordinates by city name (searches postal codes database)
 * Returns the first matching postal code's coordinates
 *
 * @param string $city_name City name to search for
 * @return array|null Array with 'latitude', 'longitude', 'postal_code', 'city' or null if not found
 */
public static function get_coordinates_by_city($city_name)
```

**Eksempler:**
- `get_coordinates_by_city("odense")` → Finder "Odense NØ" (5240) med koordinater
- `get_coordinates_by_city("københavn")` → Finder "København K" (1000) med koordinater
- `get_coordinates_by_city("Aarhus")` → Finder "Aarhus C" (8000) med koordinater

**Fordele:**
- Case-insensitive matching
- Partial matching (f.eks. "kbh" finder "København")
- Exact match prioriteres før partial match
- Returnerer fulde data: koordinater + postnummer + bynavn

---

## ✅ Testede Scenarier

### Navn-søgning
- ✅ Søgning efter "Frank" finder både "Frank Hansen" og "Frank Hansen Tessin"
- ✅ Søgning efter "Hansen" finder alle Hansen-eksperter
- ✅ Søgning efter specialisering (f.eks. "yoga") finder eksperter med den specialisering

### Lokations-søgning
- ✅ Søgning efter postnummer "5240" finder eksperter i Odense
- ✅ Søgning efter bynavn "odense" finder eksperter i Odense
- ✅ Søgning med radius (f.eks. "odense" + 25km) finder eksperter inden for radius
- ✅ Søgning uden radius bruger city name matching
- ✅ Case-insensitive matching virker ("ODENSE", "Odense", "odense" finder alle samme)

### Kombineret søgning
- ✅ Søgning efter navn + lokation virker korrekt
- ✅ Søgning efter kategori + lokation virker korrekt
- ✅ Søgning efter specialisering + lokation virker korrekt

---

## 🔐 Sikkerhed

- ✅ Ingen nye sikkerhedsrisici introduceret
- ✅ Input sanitization opretholdt via `sanitize_text_field()`
- ✅ SQL queries bruger `$wpdb->prepare()` korrekt

---

## 📝 Upgrade Noter

**Efter opdatering til v3.9.4:**

Ingen specielle handlinger krævet - søgefunktionaliteten virker automatisk bedre.

**Bemærk:**
- Eksperter skal have indtastet postnummer for at radius-søgning virker korrekt
- Koordinater auto-populeres når postnummer gemmes i dashboard
- Eksisterende eksperter med postnummer har allerede koordinater (fra v3.9.1+)

---

## 🎁 Performance

- ✅ Minimal påvirkning på performance
- ✅ City name lookup bruger samme in-memory array som postnummer-lookup
- ✅ Ingen ekstra database queries

---

## 📚 Relaterede Issues

Denne version løser problemer rapporteret af brugere:
- Expert "Frank Hansen" blev ikke fundet ved navn-søgning
- Søgning efter "odense" returnerede ingen resultater
- Generelle problemer med søgefunktionalitet

---

**Tidligere Versioner:**
- v3.9.3 - Kategori-filtrering for specialiseringer
- v3.9.2 - Forbedret dashboard
- v3.9.1 - Location-based search implementation

**Næste Version Planer:**
- Se GitHub issues for planlagte features til v3.9.5
