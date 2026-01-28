# Changelog v3.9.2

**Release Date:** 20. januar 2026

## 🎯 Hovedfunktioner

### Expert Dashboard - Nye felter
- ✅ **Adresse felt** - Eksperter kan nu indtaste deres adresse
- ✅ **Postnummer felt** - Med automatisk GPS koordinat lookup
- ✅ **By felt** - Indtast by/lokalitet
- ✅ **Profilbillede upload** - Upload og opdater profilbillede direkte fra dashboard (alle planer)
- ✅ **Banner billede upload** - Upload banner billede (kun Premium abonnement)

### Lokationssøgning Integration
- ✅ **Automatisk koordinat opdatering** - Når ekspert gemmer postnummer, hentes GPS koordinater automatisk fra den danske postnummerdatabase
- ✅ **Radius søgning aktiv** - Eksperter der har indtastet postnummer vil nu automatisk vises i lokationsbaserede søgninger

### Upload Manager - Isolerede Upload Mapper
- ✅ **Custom upload stier** - Ekspert uploads gemmes nu i isolerede mapper: `/wp-content/uploads/rfm/experts/{expert_id}/`
- ✅ **Adskilt mediebibliotek** - RFM uploads vises ikke i WordPress standard mediebibliotek, hvilket forhindrer forvirring
- ✅ **Automatisk tagging** - Alle uploads bliver tagget med ejer-information (owner_type, owner_id, upload_type)
- ✅ **Automatisk oprydning** - Når en ekspert slettes, fjernes alle deres uploads automatisk

## 🔧 Tekniske Forbedringer

### AJAX Handlers
- Ny handler: `rfm_upload_expert_avatar` - Håndterer profilbillede upload
- Ny handler: `rfm_upload_expert_banner` - Håndterer banner upload (Premium only)
- Validering af filtype (JPG, PNG, GIF, WebP)
- Validering af filstørrelse (5MB for avatar, 10MB for banner)
- Sikkerhedscheck af faktisk MIME type (ikke kun extension)

### JavaScript Funktionalitet
- Real-time image preview efter upload
- Client-side validering (filtype og størrelse)
- Loading states under upload
- Fejlhåndtering med brugervenlige beskeder

### Backend Forbedringer
- Postnummer validering via RFM_Postal_Codes klasse
- Auto-population af _rfm_latitude og _rfm_longitude meta fields
- Koordinater slettes hvis postnummer er ugyldigt

## 📦 Filer Modificeret

### Nye funktioner:
- `ajax-handler.php` - Tilføjet `rfm_direct_upload_expert_avatar()` og `rfm_direct_upload_expert_banner()`
- `assets/js/expert-dashboard.js` - Tilføjet image upload handlers
- `includes/class-rfm-expert-dashboard.php` - Tilføjet UI for adresse, postnummer, by og billede uploads
- `includes/class-rfm-upload-manager.php` - Opdateret til at håndtere nye upload actions

### Version opdatering:
- `rigtig-for-mig.php` - Version bump til 3.9.2

## 🔐 Sikkerhed

- ✅ Nonce verification på alle AJAX requests
- ✅ User permission checks (ejer verificering)
- ✅ MIME type validation (tjekker faktisk filindhold)
- ✅ File extension validation
- ✅ File size limits (5MB/10MB)
- ✅ Premium plan check for banner uploads

## 📝 Bruger Oplevelse

**For Eksperter:**
- Nemt at opdatere adresse og lokation direkte i dashboard
- Upload billeder uden at skulle gennem WordPress mediebibliotek
- Automatisk opsætning af lokationssøgning når postnummer indtastes
- Visuel feedback med image preview

**For Administrator:**
- Rent og organiseret mediebibliotek (RFM uploads er separeret)
- Automatisk oprydning når eksperter slettes
- Nem måde at identificere RFM uploads via custom kolonner

## 🎁 Bonus Features

- Upload manager skjuler RFM uploads fra standard mediebibliotek view
- Custom "RFM Ejer" kolonne i mediebiblioteket viser hvilken ekspert/bruger der ejer hver fil
- Directory struktur holder automatisk styr på uploads per ekspert

## 📌 Noter

- Banner upload kræver Premium abonnement - non-Premium eksperter ser en opgraderingsbesked
- Postnummer skal være gyldigt dansk postnummer (fra RFM_Postal_Codes database) for at GPS koordinater kan hentes
- Billeder uploades til `/wp-content/uploads/rfm/experts/{expert_id}/` i stedet for standard WordPress upload mappe

---

**Testede Komponenter:**
- ✅ AJAX upload funktionalitet
- ✅ Custom upload directory routing
- ✅ File validation (type og størrelse)
- ✅ Coordinate auto-population
- ✅ Premium plan gating for banner uploads
- ✅ Image preview opdatering

**Næste Version Planer:**
Se GitHub issues for planlagte features til v3.9.3
