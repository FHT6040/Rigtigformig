# Installations Guide - Rigtig for Mig v3.1.5

**Version:** 3.1.5
**Dato:** 5. december 2024
**Type:** Kritisk Bug Fix Opdatering

---

## 🎯 HVAD ER NYT I v3.1.5?

Denne opdatering retter **TO KRITISKE FEJL**:

### ✅ 1. Ekspert Login Fejl Rettet
**Problem:** Verificerede eksperter kunne ikke logge ind
**Løsning:** Login tjekker nu korrekt verificeringsstatus for eksperter

### ✅ 2. Cache-Rensning Implementeret
**Problem:** Cache viste stadig logged-in indhold efter logout
**Løsning:** Automatisk cache-rensning ved logout og profil opdateringer

---

## 📦 INSTALLATION

### Trin 1: Download Plugin

Du har allerede filen: `rigtig-for-mig-v3.1.5.zip`

### Trin 2: Upload til WordPress

1. Log ind i **WordPress Admin**
2. Gå til **Plugins → Tilføj ny**
3. Klik **Upload Plugin** øverst
4. Vælg filen `rigtig-for-mig-v3.1.5.zip`
5. Klik **Installer nu**
6. Når installation er færdig, klik **Aktiver plugin**

> **Note:** Plugin'et erstatter automatisk version 3.1.4

### Trin 3: RYD CACHE! ⚠️

**MEGET VIGTIGT** - Funktionaliteten vil ikke virke uden cache-rensning!

#### Browser Cache
1. Tryk `CTRL + SHIFT + DELETE`
2. Vælg:
   - ☑ Cached images and files
   - ☑ Cookies and site data
3. Tidsperiode: "All time"
4. Klik "Clear data"

#### LiteSpeed Cache (hvis du bruger det)
1. Gå til **WordPress Admin → LiteSpeed Cache**
2. Klik på **Toolbox** fanen
3. Find "Purge" sektionen
4. Klik **Purge All**
5. Bekræft

#### Hard Refresh
Efter cache-rensning, gør også dette:
1. Gå til login-siden
2. Tryk `CTRL + F5` (hard refresh)
3. Nu skulle alt virke!

**Alternativ: Test i privat vindue**
- Tryk `CTRL + SHIFT + N` (Chrome) eller `CTRL + SHIFT + P` (Firefox)
- Private vinduer har INGEN cache!

---

## ✅ TEST EFTER INSTALLATION

### Test 1: Ekspert Login
1. Gå til login-siden
2. Log ind med en **verificeret ekspert**
3. ✅ Login skal fungere uden fejl
4. ✅ Du skal redirect til ekspert-dashboard

**Hvis det fejler:**
- Ryd cache igen
- Test i privat vindue
- Kontakt support

### Test 2: Logout
1. Klik på "Log ud" knappen
2. ✅ Du skal redirect til login-siden
3. ✅ Du må IKKE kunne se dashboard efter logout
4. ✅ Prøv at gå til `/ekspert-dashboard` direkte - skal redirect til login

**Hvis dashboard stadig vises:**
- Ryd browser cache (CTRL+SHIFT+DELETE)
- Ryd LiteSpeed cache
- Test i privat vindue

### Test 3: Profil Opdatering
1. Log ind som bruger eller ekspert
2. Gå til dashboard
3. Ret dit telefonnummer eller navn
4. Gem ændringer
5. ✅ Ændringer skal vises ØJEBLIKKELIGT
6. ✅ INGEN behov for manuel cache-rensning

---

## 🔍 TEKNISKE DETALJER

### Ændringer i denne Version

#### 1. Login Funktion (`class-rfm-user-registration.php`)
```php
// Nu tjekker verificering korrekt for både eksperter og brugere
if (in_array('rfm_expert_user', $user->roles)) {
    // Tjek post meta for eksperter
    $verified = get_post_meta($expert_posts[0]->ID, '_rfm_email_verified', true);
} else {
    // Tjek user meta for brugere
    $verified = get_user_meta($user->ID, 'rfm_email_verified', true);
}
```

#### 2. Logout med Cache-Rensning (`class-rfm-user-registration.php`)
```php
// Rens WordPress cache
wp_cache_flush();

// Rens LiteSpeed cache
if (function_exists('litespeed_purge_all')) {
    litespeed_purge_all();
}

// Rens andre cache plugins
do_action('litespeed_purge_all');
do_action('w3tc_flush_all');
do_action('wp_cache_clear_cache');

// Send no-cache headers
header('Cache-Control: no-cache, no-store, must-revalidate');
```

#### 3. Profil Opdatering med Cache (`class-rfm-user-dashboard.php`)
```php
// Rens bruger-specifik cache
wp_cache_delete($user_id, 'users');
wp_cache_delete($user_id, 'user_meta');

// Rens plugin caches
do_action('litespeed_purge_all');
```

### Filer Ændret
- `rigtig-for-mig.php` - Version bump til 3.1.5
- `includes/class-rfm-user-registration.php` - Login fix + logout cache
- `includes/class-rfm-user-dashboard.php` - Profil opdatering cache

---

## ⚠️ FEJLFINDING

### Problem: "Login virker stadig ikke for eksperter"

**Løsning 1: Verificer at ekspert ER verificeret**
1. Gå til WordPress Admin
2. Gå til **Eksperter** (custom post type)
3. Find eksperten
4. Tjek at "Email Verified" er sat til "Ja"

**Løsning 2: Ryd cache grundigt**
1. Browser cache (CTRL+SHIFT+DELETE)
2. LiteSpeed cache (Admin → LiteSpeed → Purge All)
3. Genstart browser
4. Test i privat vindue

**Løsning 3: Tjek fejlmeddelelse**
1. Tryk F12 i browseren
2. Gå til Console tab
3. Prøv at logge ind
4. Se efter røde fejl
5. Send screenshot af fejl til support

### Problem: "Logout viser stadig dashboard"

**Løsning:**
1. Ryd ALLE caches:
   - Browser cache
   - LiteSpeed cache
   - Server cache
2. Hard refresh med CTRL+F5
3. Test i privat vindue
4. Hvis det stadig fejler, kontakt support

### Problem: "Profil opdateringer vises ikke"

**Løsning:**
1. Tjek at opdateringen faktisk blev gemt:
   - Reload siden
   - Er ændringen der?
2. Hvis ja - det er cache:
   - Ryd browser cache
   - Hard refresh
3. Hvis nej - der er en fejl:
   - Tjek console (F12)
   - Kontakt support

---

## 📞 SUPPORT

Hvis du oplever problemer efter installation:

### Før du kontakter support:
1. ✅ Ryd browser cache
2. ✅ Ryd LiteSpeed cache (hvis aktiv)
3. ✅ Test i privat vindue
4. ✅ Tjek console for fejl (F12)

### Kontakt support med:
- WordPress version (find under Dashboard → Opdateringer)
- PHP version (find under Værktøjer → Site Health)
- Cache plugin (hvis du bruger et)
- Fejlmeddelelse eller screenshot
- Browser type og version

---

## 📚 RELATEREDE DOKUMENTER

- **CHANGELOG-3.1.5.md** - Detaljeret liste over ændringer
- **FEJLRAPPORT-v3.1.4.md** - Analyse af de fejl der blev rettet
- **README.md** - Generel plugin dokumentation

---

## ✨ FORDELE VED DENNE OPDATERING

### For Eksperter
- ✅ Kan logge ind uden problemer
- ✅ Logout virker konsekvent
- ✅ Profil opdateringer vises med det samme

### For Brugere
- ✅ Login og logout virker perfekt
- ✅ Ingen sikkerhedsrisiko med cached data

### For Admin
- ✅ Færre support henvendelser
- ✅ Automatisk cache-håndtering
- ✅ Ingen manuelle indgreb

---

## 🎯 NÆSTE SKRIDT

Efter installation:

1. ✅ Test login med ekspert
2. ✅ Test logout
3. ✅ Test profil opdatering
4. ✅ Informer dine eksperter om rettelsen

---

**Installation udført?** Send gerne feedback! 📧

**Problemer?** Se fejlfindingssektionen ovenfor eller kontakt support.

**Tak fordi du bruger Rigtig for mig!** 🎉

---

*Guide oprettet: 5. december 2024*
*Version: 3.1.5*
*Claude Code*
