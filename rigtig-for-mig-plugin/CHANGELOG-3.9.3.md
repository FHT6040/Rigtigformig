# Changelog v3.9.3

**Release Date:** 20. januar 2026

## 🎯 Hovedfunktioner

### Kategori-filtrering for Specialiseringer
- ✅ **Admin UI**: Tilføj "Tilhørende Kategorier" felt når du opretter/redigerer specialiseringer
- ✅ **Multi-kategori support**: Specialiseringer kan nu tilhøre flere kategorier samtidig
- ✅ **Dynamisk filtrering**: Expert Dashboard viser kun relevante specialiseringer for hver kategori
- ✅ **Automatisk tildeling**: One-click værktøj til at tildele kategorier til eksisterende specialiseringer

### Admin Værktøj: Tildel Kategorier
- ✅ **Nyt admin menu**: Eksperter → Tildel Kategorier
- ✅ **Intelligent matching**: Tildeler automatisk kategorier baseret på specialiserings-navne
- ✅ **Pattern matching**: Over 100+ keyword patterns for præcis kategori-tildeling
- ✅ **Detaljeret rapport**: Viser hvilke specialiseringer der blev opdateret og til hvilke kategorier

## 🔧 Tekniske Forbedringer

### RFM_Taxonomies Klasse
```php
// Nye metoder:
- add_specialization_category_field() // Admin UI for ny specialisering
- edit_specialization_category_field() // Admin UI for redigering
- save_specialization_category_field() // Gem kategori-relationer
- get_specializations_for_category($category_id) // Hent filtrerede specialiseringer
```

### Expert Dashboard
- Opdateret til at bruge `RFM_Taxonomies::get_specializations_for_category()`
- Viser kun specialiseringer der hører til den aktive kategori
- Fallback: Specialiseringer uden kategori vises i alle kategorier (backwards compatible)

### Database Schema
**Ny term meta:**
- `rfm_categories` - Array af category IDs for hver specialisering
- Gemmes som serialized array i `wp_termmeta` tabellen

## 📦 Nye Filer

### Admin Tool
- `admin/assign-categories-tool.php` - One-time kategori-tildelings værktøj
  - Intelligent keyword matching
  - Supports 4 kategorier: Hjerne & Psyke, Krop & Bevægelse, Mad & Sundhed, Sjæl & Mening
  - Over 100+ patterns for præcis matching

## 🎨 Bruger Oplevelse

### For Administratorer:
1. **Tildel kategorier automatisk:**
   - Gå til Eksperter → Tildel Kategorier
   - Klik "Tildel Kategorier til Alle Specialiseringer"
   - Se detaljeret rapport over hvad der blev opdateret

2. **Manuel justering:**
   - Gå til Specialiseringer i admin
   - Rediger enhver specialisering
   - Vælg ønskede kategorier via checkboxes

3. **Fremtidige specialiseringer:**
   - Når du opretter nye specialiseringer, vælg kategorier med det samme
   - Opdateringer vises øjeblikkeligt i Expert Dashboard

### For Eksperter:
- **Klarere valg:** Ser kun specialiseringer relevante for deres valgte kategori
- **Mindre forvirring:** Ikke oversvømmet med irrelevante specialiseringer
- **Bedre organisering:** Lettere at finde de rigtige specialiseringer

## 📊 Kategori Mappings

### Hjerne & Psyke
Angst, depression, stress, coaching, psykoterapi, mindfulness, parterapi, mm.

### Krop & Bevægelse
Fysioterapi, yoga, personlig træning, massage, kiropraktik, rehabilitering, mm.

### Mad & Sundhed
Ernæring, vægtreduktion, diætist, allergi, sporternæring, kosttilskud, mm.

### Sjæl & Mening
Spirituel vejledning, healing, meditation, tarot, astrologi, shamanic, mm.

## 🔄 Migrationsguide

**Efter opdatering til v3.9.3:**

1. Gå til WP Admin → Eksperter → **Tildel Kategorier**
2. Klik "Tildel Kategorier til Alle Specialiseringer"
3. Verificer resultatet i rapporten
4. Juster manuelt hvis nødvendigt under Specialiseringer

**Bemærk:** Specialiseringer uden kategori vil stadig vises i alle kategorier (backwards compatible).

## 🐛 Rettelser

- ✅ Løst: Alle specialiseringer blev vist under alle kategorier
- ✅ Forbedret: Expert Dashboard performance (kun henter relevante specialiseringer)

## 📝 Ændringer i Filer

### Modificeret:
- `includes/class-rfm-taxonomies.php` - Tilføjet kategori-filtrering og admin UI
- `includes/class-rfm-expert-dashboard.php` - Bruger nu filtrerede specialiseringer
- `rigtig-for-mig.php` - Version bump til 3.9.3 + load admin tool

### Tilføjet:
- `admin/assign-categories-tool.php` - Nyt admin værktøj

## 🔐 Sikkerhed

- ✅ Nonce verification på admin værktøj
- ✅ Capability check (`manage_options` required)
- ✅ Data sanitization ved gemning af kategori-relationer

## 📚 Dokumentation

**Keyword Patterns:**
- Over 30 patterns per kategori
- Understøtter både danske og engelske termer
- Case-insensitive matching

**Eksempler:**
- "Mindfulness" → Hjerne & Psyke + Sjæl & Mening
- "Yoga" → Krop & Bevægelse
- "Ernæringsrådgivning" → Mad & Sundhed
- "Shamanic healing" → Sjæl & Mening

## 🎁 Bonus Features

- **Multi-kategori support**: Specialiseringer kan tilhøre flere kategorier
- **Real-time opdateringer**: Ingen cache-rensning nødvendig
- **Backwards compatible**: Eksisterende specialiseringer uden kategori virker stadig
- **Admin-venligt**: Visuelt feedback i admin værktøj

---

**Testede Komponenter:**
- ✅ Kategori-filtrering i Expert Dashboard
- ✅ Admin UI for kategori-valg
- ✅ Automatisk tildelings-værktøj
- ✅ Multi-kategori support
- ✅ Backwards compatibility

**Næste Version Planer:**
Se GitHub issues for planlagte features til v3.9.4
