# Installations- og Upgrade Guide
## Rigtig For Mig Plugin v2.8.6

## 🚀 Ny Installation

### Trin 1: Upload Plugin
1. Download plugin zip-filen: `rigtig-for-mig-v2.8.6.zip`
2. Gå til WordPress admin → **Plugins → Tilføj ny**
3. Klik **Upload Plugin**
4. Vælg zip-filen og klik **Installer nu**
5. Klik **Aktiver Plugin**

### Trin 2: Basis Konfiguration
1. Gå til **Rigtig For Mig → Indstillinger**
2. Sæt priser for Standard og Premium abonnementer
3. Konfigurer email verifikation indstillinger
4. Gem indstillingerne

### Trin 3: Profil Felter Setup
1. Gå til **Rigtig For Mig → Profil Felter**
2. Gennemgå standard felt-grupper
3. Tilpas limits og subscription requirements efter behov
4. (Valgfrit) Tilføj nye felt-grupper

### Trin 4: Opret Sider
Opret følgende sider med deres shortcodes:

**Expert Dashboard** (f.eks. /ekspert-dashboard/)
```
[rfm_expert_profile_editor]
```

**Login Side** (f.eks. /ekspert-login/)
```
[rfm_expert_login]
```

**Registrering** (f.eks. /bliv-ekspert/)
```
[rfm_expert_registration]
```

**Glemt Adgangskode** (f.eks. /glemt-adgangskode/)
```
[rfm_lost_password]
```

**Nulstil Adgangskode** (f.eks. /nulstil-adgangskode/)
```
[rfm_reset_password]
```

> **Note**: Password reset links sendes automatisk til den korrekte side når brugere klikker "Glemt adgangskode?" på login-siden.

### Trin 5: Test
1. Registrer en test-ekspert
2. Log ind på expert dashboard
3. Test profil-redigering
4. Verificer at felter vises korrekt baseret på subscription
5. Test "Glemt adgangskode" funktionalitet
6. Verificer at reset email bliver sendt

---

## 🔄 Upgrade fra v2.4.2 til v2.5.0

### Før Upgrade - VIGTIGT
**Tag backup af:**
- WordPress database
- Plugin filer
- Theme customizations

### Upgrade Proces

#### Metode 1: Automatisk (Anbefalet)
1. Deaktiver det nuværende plugin
2. Slet den gamle plugin-folder (data bevares i databasen)
3. Upload og aktiver v2.5.0
4. Plugin vil automatisk opdatere database struktur

#### Metode 2: Manuel
1. Gå til **Plugins** i WordPress admin
2. Deaktiver "Rigtig For Mig" plugin'et
3. Slet plugin'et (data bevares!)
4. Upload den nye version v2.5.0
5. Aktiver plugin'et

### Efter Upgrade

#### 1. Verificer Installation
- Gå til **Rigtig For Mig → Indstillinger** og tjek at alle indstillinger er intakte
- Verificer at eksisterende ekspert-profiler stadig virker

#### 2. Konfigurer Nye Features
- Gå til **Rigtig For Mig → Profil Felter** (ny menu)
- Gennemgå standard felt-konfigurationer
- Tilpas subscription requirements hvis nødvendigt

#### 3. Opdater Expert Dashboard Side
Tilføj det nye shortcode til ekspert dashboard siden:
```
[rfm_expert_profile_editor]
```

Dette erstatter ikke eksisterende funktionalitet, men giver eksperter adgang til det nye felt-system.

#### 4. Test Funktionalitet
1. Log ind som en ekspert
2. Gå til dashboard
3. Test profil-redigering med det nye felt-system
4. Verificer at subscription limits fungerer

### Kompatibilitet

✅ **Bagudkompatibel** - Alle eksisterende data bevares
✅ **Eksisterende features** - Alt fungerer som før + nye features
✅ **Database** - Automatisk migration hvis nødvendig
✅ **Shortcodes** - Alle eksisterende shortcodes virker stadig

### Potentielle Problemer & Løsninger

#### Problem: "Profil Felter" menu vises ikke
**Løsning:** 
- Ryd browser cache
- Log ud og ind igen
- Tjek at plugin'et er korrekt aktiveret

#### Problem: Felter vises ikke i frontend
**Løsning:**
- Verificer at shortcode `[rfm_expert_profile_editor]` er tilføjet korrekt
- Tjek at brugeren er logget ind
- Verificer at brugeren har "Ekspert" rolle

#### Problem: Subscription tier virker ikke
**Løsning:**
Sæt subscription tier manuelt for test:
```php
update_user_meta($user_id, 'rfm_subscription_tier', 'standard');
```

#### Problem: Permalinks virker ikke
**Løsning:**
1. Gå til **Indstillinger → Permalinks**
2. Klik "Gem ændringer" (intet behov for at ændre noget)
3. Dette flusher rewrite rules

---

## 📊 Database Ændringer i v2.5.0

### Nye Option Keys
- `rfm_profile_fields` - Gemmer felt-konfigurationer

### Nye User Meta Keys
Alle profil-felter gemmes som:
- `rfm_profile_{field_key}` - Eksempel: `rfm_profile_phone`
- `rfm_profile_uddannelser` - Array af uddannelser
- `rfm_profile_certifikater` - Array af certifikater
- osv.

### Eksisterende Data
- Alle eksisterende user meta bevares
- Ingen data går tabt ved upgrade
- Gammel og ny funktionalitet koeksisterer

---

## 🔧 Fejlfinding

### Debug Mode
Aktivér WordPress debug mode i `wp-config.php`:
```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
```

Check `/wp-content/debug.log` for fejl.

### Clear Cache
Ryd alle caches:
- Plugin cache (hvis du bruger et cache plugin)
- Browser cache
- Object cache (Redis/Memcached)

### Permalink Flush
Kør dette i MySQL eller via plugin som Code Snippets:
```php
flush_rewrite_rules();
```

---

---

## 🆕 Ny Funktionalitet i v2.8.6

### Komplet Password Reset System

Version 2.8.6 løser det kritiske problem hvor brugere der forsøgte at nulstille deres adgangskode mødte en "Nothing found" side. Nu er der et komplet, sikkert password reset system.

#### Funktionalitet
- **Glemt Adgangskode**: Sikker email-baseret password reset
- **Token System**: 24-timers gyldighed på reset links
- **Email Notifications**: Automatiske bekræftelses-emails
- **Brugervenlig**: Klare instruktioner og feedback

#### Nye Sider der Skal Oprettes
For at aktivere funktionaliteten skal du oprette to nye sider:

**1. Glemt Adgangskode Side** (slug: `/glemt-adgangskode/`)
```
Titel: Glemt Adgangskode
Shortcode: [rfm_lost_password]
```

**2. Nulstil Adgangskode Side** (slug: `/nulstil-adgangskode/`)
```
Titel: Nulstil Adgangskode  
Shortcode: [rfm_reset_password]
```

#### Hvordan det Fungerer
1. Bruger klikker "Glemt adgangskode?" på login-siden
2. Kommer til glemt-adgangskode siden
3. Indtaster sin email adresse
4. Modtager email med reset link
5. Klikker på link (gyldig i 24 timer)
6. Kommer til nulstil-adgangskode siden
7. Indtaster ny adgangskode
8. Modtager bekræftelses-email
9. Kan nu logge ind med ny adgangskode

#### Email Konfiguration
Systemet bruger WordPress's standard wp_mail() funktion. Hvis emails ikke ankommer:
- Check at din WordPress installation kan sende emails
- Overvej at bruge et SMTP plugin som "WP Mail SMTP"
- Verificer at emails ikke havner i spam

#### Sikkerhed
- Tokens er gyldige i 24 timer
- Tokens er kryptografisk sikre
- Brugt tokens kan ikke genbruges
- Ingen information om eksistensen af emails lækkes

---

## 🆕 Ny Funktionalitet i v2.8.5

### Diplom/Certifikat Billede Upload

Version 2.8.5 introducerer muligheden for eksperter med betalte medlemskaber at uploade billeder af deres diplomer og certifikater direkte til deres uddannelser.

#### Funktionalitet
- **Subscription krav**: Standard og Premium medlemmer
- **Placering**: Integreret i "Uddannelser" repeater feltet
- **Filtyper**: Alle standard billedformater (JPG, PNG, GIF, WEBP)
- **Automatisk**: Ingen ekstra konfiguration nødvendig

#### Hvordan det Fungerer
1. Ekspert logger ind på deres dashboard
2. Går til "Uddannelser" sektionen
3. Tilføjer eller redigerer en uddannelse
4. Ser "Diplom/Certifikat" felt (kun for Standard/Premium)
5. Klikker på upload-området eller træk-og-slip billede
6. Preview vises øjeblikkelig
7. Billede gemmes automatisk ved profil-lagring

#### For Free Medlemmer
Free medlemmer ser feltet med en låst-indikation og besked om at opgradere til Standard eller Premium for at bruge funktionen.

#### Teknisk Implementation
- Billeder uploades til WordPress media library
- Attachment ID gemmes i profil data
- Billeder kan fjernes og genuploades
- Responsive preview med slet-knap
- Validering af filtyper i frontend

---

## 📞 Support

Hvis du oplever problemer:
1. Tjek denne guide grundigt
2. Aktiver debug mode og check logs
3. Kontakt support: support@rigtigformig.dk

---

## ✅ Verification Checklist

Efter installation/upgrade, verificer:

- [ ] Plugin er aktiveret
- [ ] "Rigtig For Mig" menu vises i admin
- [ ] "Profil Felter" submenu er tilgængelig
- [ ] Standard felt-grupper vises i admin
- [ ] Expert dashboard side med shortcode fungerer
- [ ] Eksperter kan logge ind
- [ ] Profil-redigering virker
- [ ] Subscription limits respekteres
- [ ] Låste felter viser upgrade-prompts
- [ ] Data gemmes korrekt
- [ ] Eksisterende ekspert-profiler fungerer stadig
- [ ] Billede upload i uddannelser virker for Standard/Premium
- [ ] Free medlemmer ser låst diplom/certifikat felt
- [ ] Billede preview vises korrekt
- [ ] Billeder kan fjernes og genuploades
- [ ] "Glemt adgangskode" side er oprettet
- [ ] "Nulstil adgangskode" side er oprettet
- [ ] Password reset email sendes korrekt
- [ ] Password reset link virker
- [ ] Ny adgangskode kan sættes

---

## 🎉 Du er Klar!

Plugin'et er nu installeret og konfigureret. 

Næste skridt:
1. Tilpas felt-konfigurationer efter dine behov
2. Test med forskellige subscription tiers
3. Tilføj custom felter når nødvendigt
4. Integrer med dit theme

God fornøjelse med Rigtig For Mig v2.8.6! 🚀
