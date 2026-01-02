# Rigtig For Mig - Ekspert Markedsplads Plugin

Version: 3.1.0

En komplet markedsplads for terapeuter, coaches, mentorer og vejledere med profilsider, ratings, abonnementer, brugersystem og multi-language support.

## 🎉 Nyt i Version 3.1.0 - BRUGERSYSTEM IMPLEMENTERET!

### Komplet Brugersystem
Nu med fuld bruger-funktionalitet - opret gratis brugerprofiler og kontakt eksperter!

#### Nye Hovedfunktioner:
- 👥 **Brugerregistrering** - Gratis brugeroprettelse med e-mail verificering
- 🔐 **Fælles Login** - Login med e-mail ELLER brugernavn (både brugere og eksperter)
- 📊 **Bruger Dashboard** - Personlig dashboard med profiladministration
- 🔒 **Kontaktinfo Beskyttelse** - Telefon, e-mail og hjemmeside kun synlig for loggede brugere
- 👨‍💼 **Admin Panel** - Komplet bruger-administration med statistik og eksport
- 💬 **Messaging Infrastructure** - Database klar til beskedsystem
- 🛡️ **GDPR Compliant** - Download, ret og slet data funktioner

#### Nye Shortcodes:
- `[rfm_user_registration]` - Brugerregistrering
- `[rfm_login]` - Fælles login for brugere og eksperter
- `[rfm_user_dashboard]` - Bruger dashboard
- `[rfm_contact_login_prompt]` - Login prompt for kontaktinfo

## 📋 Hurtig Start v3.1.0

### Opret Disse Sider:
1. **Opret Bruger** (`/opret-bruger`) - Brug: `[rfm_user_registration]`
2. **Login** (`/login`) - Brug: `[rfm_login]`
3. **Bruger Dashboard** (`/bruger-dashboard`) - Brug: `[rfm_user_dashboard]`
4. **Bekræft Email** (`/bekraeft-email`) - Informationsside

Se **INSTALLATION-GUIDE-3.1.0.md** for detaljeret guide!

## ✨ Eksisterende Features fra v2.8.5

### Diplom/Certifikat Billede Upload
Betalte medlemmer (Standard og Premium) kan nu uploade billeder af deres diplomer og certifikater direkte til deres uddannelser!

#### Features:
- 📷 **Billede Upload i Uddannelser** - Upload diplomer/certifikater direkte i uddannelses-feltet
- 🔒 **Subscription Baseret** - Kun tilgængelig for Standard og Premium medlemmer
- 👁️ **Live Preview** - Se billeder øjeblikkelig efter upload
- ✏️ **Nem Håndtering** - Fjern og genupload billeder nemt
- 🎨 **Responsivt Design** - Fungerer perfekt på alle enheder

## 🚀 Eksisterende Features fra v2.5.0

### Fleksibelt Felt System
Et helt nyt **dynamisk felt-system** der giver dig fuld kontrol over profil-felter uden at skulle uploade kode.

#### Hovedfordele:
- ✅ **Admin-baseret feltkonfiguration** - Tilføj, rediger og slet felter direkte fra WordPress admin
- ✅ **Subscription-baseret adgang** - Lås felter bag Free/Standard/Premium medlemskaber
- ✅ **Repeater felter** - Perfekt til uddannelser, certifikater, specialer osv.
- ✅ **Fleksible begrænsninger** - Sæt forskellige limits per subscription tier
- ✅ **Automatisk frontend rendering** - Felter vises automatisk i ekspertens dashboard
- ✅ **Fremtidssikret** - Uendeligt skalerbar uden plugin-updates

#### Nye funktioner:
1. **Profil Felter Admin Panel** (`Rigtig For Mig → Profil Felter`)
2. **Frontend Profil Editor** (shortcode: `[rfm_expert_profile_editor]`)
3. **Standard Felt-grupper** (basis info, uddannelser, certifikater, specialer, priser)

## 📋 Installation

1. Upload plugin-folderen til `/wp-content/plugins/`
2. Aktiver plugin'et gennem 'Plugins' menuen
3. Gå til **Rigtig For Mig → Indstillinger**
4. Gå til **Rigtig For Mig → Profil Felter**
5. Tilføj shortcode `[rfm_expert_profile_editor]` til expert dashboard

## 🔧 Shortcodes

```
[rfm_user_registration]      - Brugerregistrering (NYT i v3.1.0)
[rfm_login]                  - Fælles login for brugere og eksperter (NYT i v3.1.0)
[rfm_user_dashboard]         - Bruger dashboard (NYT i v3.1.0)
[rfm_contact_login_prompt]   - Login prompt for kontaktinfo (NYT i v3.1.0)
[rfm_expert_profile_editor]  - Profil redigering med alle felter
[rfm_expert_login]           - Ekspert login (kan nu bruge [rfm_login] i stedet)
[rfm_expert_registration]    - Ekspert registrering
[rfm_expert_dashboard_tabbed]- Ekspert dashboard med tabs
[rfm_lost_password]          - Glemt adgangskode formular
[rfm_reset_password]         - Nulstil adgangskode formular
```

## 💡 Sådan Tilføjer Du Nye Felter

Gå til **Rigtig For Mig → Profil Felter** og klik "Tilføj ny felt-gruppe".

Eksempel felt-definition:
- **Felt navn**: linkedin_url
- **Label**: LinkedIn Profil
- **Type**: URL
- **Subscription**: Standard
- **Required**: No

Feltet er nu automatisk tilgængeligt i frontend!

## 📊 Visning af Data

```php
$expert_id = get_the_author_meta('ID');
$phone = get_user_meta($expert_id, 'rfm_profile_phone', true);
$uddannelser = get_user_meta($expert_id, 'rfm_profile_uddannelser', true);
```

## 📝 Changelog

### Version 3.1.0 (December 2024)
- 👥 Tilføjet komplet brugersystem med registrering og dashboard
- 🔐 Fælles login for brugere og eksperter (e-mail ELLER brugernavn)
- 🔒 Kontaktinfo beskyttelse - kun synlig for loggede brugere
- 👨‍💼 Nyt admin panel til brugerstyring
- 💬 Database infrastruktur til messaging system
- 🛡️ GDPR-compliant med download, ret og slet funktioner
- 📊 Bruger online status tracking
- ✉️ E-mail verificering for brugere

### Version 2.8.6 (November 2024)
- 🔐 Tilføjet komplet password reset system
- 📧 Email-baseret password reset med sikker token
- 📄 Nye shortcodes: [rfm_lost_password] og [rfm_reset_password]
- ✅ Løst "Nothing found" fejl ved glemt adgangskode
- 🔒 24-timers gyldighed på reset links
- 📨 Automatiske bekræftelses-emails

### Version 2.8.5 (November 2024)
- 📷 Tilføjet diplom/certifikat billede upload til uddannelser
- 🔒 Subscription-baseret adgang til billede upload (Standard og Premium)
- 👁️ Live billede preview med fjern-funktion
- ✨ Forbedret repeater item rendering med subfelt subscription checks
- 🎨 Nye UI komponenter til billede håndtering

### Version 2.5.0 (November 2024)
- ✨ Tilføjet komplet fleksibelt felt-system
- ✨ Admin panel til felt-administration
- ✨ Subscription-baseret felt-adgang
- ✨ Repeater felter med konfigurerbare limits
- ✨ Frontend profil editor med AJAX
- 🔒 Låste felter med upgrade-prompts

## 📄 Licens

GPL v2 or later
