# Guide: Deaktiver LiteSpeed Cache i Gigahost

## Problem
LiteSpeed Cache minificerer JavaScript-filer til cached versioner med hash-navne (f.eks. `5672036b9.min.js`), hvilket forhindrer vores cache-busting strategi i at virke.

---

## ⚡ HURTIG LØSNING: Kontakt Gigahost Support

**ANBEFALET:** Da Gigahost har deres eget kontrolpanel, er den hurtigste løsning at kontakte deres support direkte:

**Gigahost Support:**
- 📧 Email: support@gigahost.dk
- 📞 Telefon: +45 89 88 14 80
- 🌐 Support portal: https://support.gigahost.dk
- 💬 Live chat: Tilgængelig på gigahost.dk

**Kopi-klar besked til Gigahost:**

```
Emne: Deaktiver LiteSpeed JavaScript Minification for rigtigformig.dk

Hej Gigahost Support,

Jeg har problemer med at LiteSpeed Cache minificerer mine WordPress plugin JavaScript-filer og cacher dem med forkerte versioner, hvilket bryder min AJAX funktionalitet.

Kan I venligst hjælpe med at deaktivere følgende for mit domæne rigtigformig.dk:
- JavaScript Minification
- JavaScript Combination
- CSS Minification (valgfrit)

Alternativt, hvis det er nemmere, kan hele LiteSpeed Cache deaktiveres midlertidigt mens jeg tester.

Kan I også guide mig til hvor jeg selv kan styre disse indstillinger i jeres kontrolpanel fremadrettet?

Mit domæne: rigtigformig.dk
Min kundeID: [DIN KUNDE-ID]

Tak for hjælpen!
```

---

## 🔧 Eller Gør Det Selv: Find Indstillingerne i Gigahost

### Trin 1: Log ind på Gigahost Kontrolpanel
1. Gå til https://gigahost.dk/login eller https://my.gigahost.dk
2. Log ind med dine Gigahost kundeoplysninger
3. Find dit hosting-produkt for rigtigformig.dk
4. Klik ind på kontrolpanelet

### Trin 2: Find LiteSpeed Cache Indstillinger

Gigahost har deres **eget kontrolpanel** (ikke cPanel eller DirectAdmin).

**Søg efter disse menupunkter:**
- "Cache" eller "Caching"
- "Performance" eller "Ydeevne"
- "LiteSpeed" eller "LiteSpeed Cache"
- "Optimization" eller "Optimering"
- "Website Settings" eller "Hjemmeside Indstillinger"

**Eller brug søgefunktionen:**
- Søg efter "cache", "litespeed", eller "optimization"

### Trin 3: Deaktiver JavaScript Optimization

Find følgende indstillinger og DEAKTIVER dem:

- ❌ **JavaScript Minify** (JavaScript Minificering)
- ❌ **JavaScript Combine** (JavaScript Kombination)
- ❌ **Combine External JavaScript** (Kombiner ekstern JavaScript)
- ❌ **CSS Minify** (CSS Minificering) - valgfrit
- ❌ **CSS Combine** (CSS Kombination) - valgfrit

**ELLER alternativt:**

- ❌ **Helt deaktiver LiteSpeed Cache** midlertidigt for at teste

### Trin 4: Ryd Cache
Efter du har ændret indstillingerne:

1. Find "Purge All" eller "Ryd Alt Cache" knappen
2. Klik på den for at rydde hele cachen
3. Vent 30 sekunder

### Trin 5: Test Hjemmesiden

1. Åbn din hjemmeside i en **ny inkognito/privat vindue**
2. Tryk Ctrl+Shift+R (eller Cmd+Shift+R på Mac) for hård refresh
3. Test om User Dashboard virker nu

---

## Efter LiteSpeed er deaktiveret

Når du har deaktiveret LiteSpeed Cache:

1. Slet `wp-content/cache/` mappen via FTP
2. Slet `wp-content/boost-cache/` mappen via FTP (hvis den findes)
3. Åbn hjemmesiden i inkognito vindue
4. Test User Dashboard - det skulle virke perfekt nu! ✅

---

## Bekræftelse på at det virker

Du vil se i browser konsollen:
```
RFM User Dashboard v3.7.1 initialized
AJAX URL: https://rigtigformig.dk/wp-admin/admin-ajax.php
Nonce available: Yes
```

Og når du gemmer profilen:
```
RFM User Dashboard: AJAX Success Response: Object { success: true, data: {...} }
```

**INGEN 302 redirect!** ✅
