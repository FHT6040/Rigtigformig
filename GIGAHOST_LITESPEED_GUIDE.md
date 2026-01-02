# Guide: Deaktiver LiteSpeed Cache i Gigahost

## Problem
LiteSpeed Cache minificerer JavaScript-filer til cached versioner med hash-navne (f.eks. `5672036b9.min.js`), hvilket forhindrer vores cache-busting strategi i at virke.

## Løsning: Deaktiver i Gigahost Kontrolpanel

### Trin 1: Log ind på Gigahost
1. Gå til https://gigahost.dk
2. Log ind på din konto
3. Find dit hostingpanel (sandsynligvis DirectAdmin eller cPanel)

### Trin 2: Find LiteSpeed Cache Indstillinger

**Hvis du har DirectAdmin:**
1. Find "Extra Features" eller "Ekstra Funktioner"
2. Klik på "LiteSpeed Cache Manager" eller "LSCache"
3. Vælg dit domæne (rigtigformig.dk)

**Hvis du har cPanel:**
1. Find "Software" sektionen
2. Klik på "LiteSpeed Web Cache Manager"
3. Vælg dit domæne (rigtigformig.dk)

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

## Alternativ: Hvis du ikke kan finde LiteSpeed indstillinger

Kontakt Gigahost support og bed dem om at:

> "Hej Gigahost,
>
> Jeg har problemer med at LiteSpeed Cache minificerer mine JavaScript-filer og cacher dem med forkerte versioner.
>
> Kan I venligst deaktivere JavaScript minification og combination for mit domæne rigtigformig.dk?
>
> Alternativt, kan I guide mig til hvor jeg selv kan gøre det i kontrolpanelet?
>
> Tak!"

**Gigahost Support:**
- Email: support@gigahost.dk
- Telefon: +45 89 88 14 80
- Support portal: https://support.gigahost.dk

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
