# 🚀 HURTIG INSTALLATIONS-GUIDE - v3.1.0

## TRIN-FOR-TRIN OPSÆTNING AF BRUGERSYSTEM

---

## ✅ TRIN 1: Upload og Aktiver Plugin

1. Deaktiver version 3.0.7 hvis den kører
2. Upload `rigtig-for-mig-v3.1.0.zip`
3. Aktiver pluginet
4. Plugin opretter automatisk nye database-tabeller

---

## ✅ TRIN 2: Opret Nødvendige Sider

### 📄 Side 1: OPRET BRUGER

**Indstillinger:**
- **Titel:** Opret Brugerprofil
- **Slug:** `opret-bruger`
- **Template:** Standard Side
- **Indhold:** 
```
[rfm_user_registration]
```

**Publicer siden**

---

### 📄 Side 2: LOGIN (FÆLLES FOR BRUGERE OG EKSPERTER)

**Indstillinger:**
- **Titel:** Log ind
- **Slug:** `login`
- **Template:** Standard Side
- **Indhold:** 
```
[rfm_login]
```

**Publicer siden**

> **💡 TIP:** Du kan nu erstatte din eksisterende ekspert-login side med denne fælles login-side!

---

### 📄 Side 3: BRUGER DASHBOARD

**Indstillinger:**
- **Titel:** Min Profil
- **Slug:** `bruger-dashboard`
- **Template:** Standard Side
- **Indhold:** 
```
[rfm_user_dashboard]
```

**Publicer siden**

> **⚠️ VIGTIGT:** Denne side skal være beskyttet - kun loggede brugere kan se den (shortcoden håndterer dette automatisk)

---

### 📄 Side 4: BEKRÆFT EMAIL

**Indstillinger:**
- **Titel:** Bekræft din e-mail
- **Slug:** `bekraeft-email`
- **Template:** Standard Side
- **Indhold:** 

```html
<div style="text-align: center; padding: 60px 20px;">
    <h2>✉️ Tjek din e-mail!</h2>
    <p style="font-size: 18px; line-height: 1.8; max-width: 600px; margin: 20px auto;">
        Vi har sendt en bekræftelses-e-mail til din adresse.<br>
        Klik på linket i e-mailen for at aktivere din konto.
    </p>
    <p style="color: #666; margin-top: 30px;">
        Har du ikke modtaget en e-mail? Tjek din spam-mappe.
    </p>
    <div style="margin-top: 40px;">
        <a href="/" class="button" style="background: #4CAF50; color: white; padding: 15px 30px; text-decoration: none; border-radius: 5px; display: inline-block;">
            Tilbage til forsiden
        </a>
    </div>
</div>
```

**Publicer siden**

---

## ✅ TRIN 3: Opdater Navigation

### Tilføj til hovedmenu:

1. **Log ind** → `/login`
2. **Opret bruger** → `/opret-bruger`
3. **Bliv ekspert** → `/opret-ekspert` (eksisterende)

### Valgfrit - Bruger menu (når logget ind):
- **Min Profil** → `/bruger-dashboard`
- **Log ud** (håndteres af dashboard)

---

## ✅ TRIN 4: Opdater Eksisterende Ekspert-Login (VALGFRIT)

Hvis du vil bruge den fælles login-side:

1. Gå til din **Ekspert Login** side
2. Erstat indholdet med: `[rfm_login]`
3. Opdater

Nu kan både eksperter og brugere logge ind samme sted! 🎉

---

## ✅ TRIN 5: Test Systemet

### Test Bruger Flow:
1. ✅ Gå til `/opret-bruger`
2. ✅ Udfyld formularen
3. ✅ Tjek e-mail for verificering
4. ✅ Klik på verificerings-link
5. ✅ Login på `/login`
6. ✅ Se dashboard på `/bruger-dashboard`

### Test Ekspert Flow:
1. ✅ Login på `/login` med ekspert-konto
2. ✅ Verificer redirect til ekspert-dashboard

### Test Kontaktinfo Beskyttelse:
1. ✅ Log ud
2. ✅ Gå til en ekspertprofil
3. ✅ Verificer at telefon/e-mail/hjemmeside er skjult
4. ✅ Log ind som bruger
5. ✅ Gå til samme ekspertprofil
6. ✅ Verificer at kontaktinfo nu er synlig

---

## ✅ TRIN 6: Tjek Admin Panel

1. Gå til **Rigtig for mig → Brugere**
2. Se oversigt over registrerede brugere
3. Tjek online status virker
4. Test eksport funktionen

---

## 🎨 VALGFRI TILPASNINGER

### Tilføj til footer:
```html
<a href="/login">Log ind</a> | 
<a href="/opret-bruger">Opret gratis profil</a>
```

### Tilføj kontaktinfo prompt på ekspertprofiler:

På dine ekspertprofil-templates kan du tilføje:
```
[rfm_contact_login_prompt message="Log ind for at se kontaktinformation"]
```

---

## 📧 E-MAIL OPSÆTNING

### Verificer WordPress kan sende e-mails:

Test med WP Mail SMTP plugin hvis nødvendigt:
1. Installer **WP Mail SMTP**
2. Konfigurer SMTP indstillinger
3. Test e-mail sending

### E-mail Templates:

Plugin bruger WordPress' standard e-mail system. 
E-mails sendes automatisk ved:
- Ny brugerregistrering
- Password reset (hvis aktiveret)

---

## 🔐 SIKKERHED

### GDPR Compliance:

✅ **Automatisk inkluderet:**
- Samtykke checkbox ved registrering
- Download brugerdata funktion
- Slet konto funktion
- Data retention logging

✅ **Hvad du skal gøre:**
- Sørg for at have en **Privatlivspolitik** side
- Link til privatlivspolitikken fra registreringsformularen (allerede sat op)
- Informer brugere om data-behandling

---

## 🆘 TROUBLESHOOTING

### Problem: "Bruger rolle mangler" notice
**Løsning:** Klik på "Opret Bruger Rolle Nu" knappen i admin panelet

### Problem: E-mails ankommer ikke
**Løsning:** 
1. Tjek spam-mappen
2. Installer WP Mail SMTP plugin
3. Test e-mail sending fra WordPress

### Problem: Login fungerer ikke
**Løsning:**
1. Ryd browser cache
2. Verificer at brugeren har bekræftet e-mail
3. Tjek at `[rfm_login]` shortcode er korrekt

### Problem: Dashboard viser fejl
**Løsning:**
1. Verificer at siden har shortcode: `[rfm_user_dashboard]`
2. Tjek at du er logget ind
3. Ryd cache

---

## 📋 KOMPLET SHORTCODE REFERENCE

| Shortcode | Funktion | Side |
|-----------|----------|------|
| `[rfm_user_registration]` | Brugerregistrering | /opret-bruger |
| `[rfm_login]` | Fælles login | /login |
| `[rfm_user_dashboard]` | Bruger dashboard | /bruger-dashboard |
| `[rfm_expert_registration]` | Ekspert registrering | /opret-ekspert |
| `[rfm_expert_dashboard_tabbed]` | Ekspert dashboard | /ekspert-dashboard |
| `[rfm_contact_login_prompt]` | Login prompt | Ekspertprofiler |

---

## ✨ DU ER KLAR!

Din platform har nu:
- ✅ Komplet brugersystem
- ✅ Fælles login for alle
- ✅ GDPR-compliant funktionalitet
- ✅ Beskyttet kontaktinformation
- ✅ Admin oversigt over brugere
- ✅ Database klar til messaging system

**Næste skridt:** Test alt grundigt og gå i produktion! 🚀

---

## 📞 SUPPORT

Hvis du har spørgsmål eller problemer:
1. Tjek denne guide igen
2. Se CHANGELOG-3.1.0.md for detaljerede informationer
3. Kontakt support

**God fornøjelse med dit nye brugersystem!** 🎉
