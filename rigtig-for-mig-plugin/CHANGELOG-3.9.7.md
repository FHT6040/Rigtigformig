# Changelog v3.9.7

**Release Date:** 28. januar 2026

## 🎯 KRITISK FIX - Søgeresultater vises ikke

### Problemet identificeret gennem debug log analyse

**Root Cause:**
Debug log'en afslørede at søgeformularen submitter til WordPress' standard search results page, som IKKE bruger `[rfm_expert_list]` shortcode'n. Derfor virkede alle tidligere shortcode-fixes (v3.9.6) ikke.

**URL fra søgning:**
```
https://rigtigformig.dk/?post_type=rfm_expert&s=&rfm_category=&rfm_location=Odense&rfm_radius=
```

**Debug Log viste:**
```
[28-Jan-2026 09:29:43 UTC] RFM: is_search: YES
[28-Jan-2026 09:29:43 UTC] RFM: post_type: rfm_expert
[28-Jan-2026 09:29:43 UTC] RFM: search term:  [EMPTY!]
```

**Konklusion:**
- WordPress behandler `?s=` (selv hvis tom) som en search query
- WordPress viser tema'ets `search.php` eller `archive.php` template
- Den template har IKKE `[rfm_expert_list]` shortcode'n
- Resultat: "Nothing found" selvom eksperter findes i databasen

---

## ✅ Løsning: Custom Archive Template

### 1. Oprettet `templates/archive-rfm_expert.php`

Custom template der:
- Bruges automatisk for rfm_expert post type archives og søgninger
- Inkluderer `[rfm_expert_search]` søgeformular
- Inkluderer `[rfm_expert_list]` shortcode med URL parameter support
- Viser alle resultater når der søges/filtreres
- Viser max 12 eksperter når der ikke søges

**Template kode:**
```php
<?php
get_header();
?>

<div class="rfm-expert-archive">
    <div class="rfm-container">

        <?php
        // Show search form
        echo do_shortcode('[rfm_expert_search]');
        ?>

        <div class="rfm-expert-results">
            <?php
            // Check if this is a search or filter request
            $is_search = (isset($_GET['s']) && !empty($_GET['s'])) ||
                         (isset($_GET['rfm_category']) && !empty($_GET['rfm_category'])) ||
                         (isset($_GET['rfm_location']) && !empty($_GET['rfm_location']));

            if ($is_search) {
                // Show filtered results using shortcode
                echo do_shortcode('[rfm_expert_list columns="3"]');
            } else {
                // Show default expert list (limited to 12)
                echo do_shortcode('[rfm_expert_list limit="12" columns="3"]');
            }
            ?>
        </div>

    </div>
</div>

<?php
get_footer();
```

### 2. Tilføjet Template Loader i `RFM_Public`

**Modificeret:** `public/class-rfm-public.php`

**Constructor (linje 23-29):**
```php
private function __construct() {
    add_filter('pre_get_posts', array($this, 'modify_expert_query'));
    add_filter('posts_search', array($this, 'extend_expert_search'), 10, 2);
    add_action('template_redirect', array($this, 'handle_expert_actions'));
    add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
    add_filter('template_include', array($this, 'load_expert_archive_template')); // NY LINJE
}
```

**Ny metode (linje 321-340):**
```php
/**
 * Load custom archive template for rfm_expert post type
 *
 * This ensures that search results and archives for rfm_expert
 * use our custom template with the shortcode
 *
 * @param string $template The path to the template to include
 * @return string Modified template path
 */
public function load_expert_archive_template($template) {
    // Only for rfm_expert post type archives and searches
    if (is_post_type_archive('rfm_expert') || (is_search() && get_query_var('post_type') === 'rfm_expert')) {
        $custom_template = RFM_PLUGIN_DIR . 'templates/archive-rfm_expert.php';

        if (file_exists($custom_template)) {
            return $custom_template;
        }
    }

    return $template;
}
```

---

## 🔧 Hvorfor virker det nu?

### Før (v3.9.6):
1. Bruger søger "Odense" → URL: `/?post_type=rfm_expert&s=&rfm_location=Odense`
2. WordPress ser `?s=` → behandler som search query
3. WordPress viser tema'ets search template (uden shortcode)
4. Resultat: "Nothing found" ❌

### Efter (v3.9.7):
1. Bruger søger "Odense" → URL: `/?post_type=rfm_expert&s=&rfm_location=Odense`
2. WordPress ser `?s=` + `post_type=rfm_expert`
3. **RFM_Public loader custom template:** `archive-rfm_expert.php`
4. Template bruger `[rfm_expert_list]` shortcode med v3.9.6 fixes
5. Shortcode læser URL parametre og viser filtrerede resultater
6. Resultat: Alle eksperter i Odense vises! ✅

---

## 📊 Testscenarier

### Scenarie 1: Søg efter navn "Frank"
**URL:** `/?post_type=rfm_expert&s=Frank&rfm_category=&rfm_location=&rfm_radius=`

**Forventet:**
- ✅ Viser begge: "Frank Hansen" og "Frank Hansen Tessin"

**Debug log:**
```
RFM: is_search: YES
RFM: post_type: rfm_expert
RFM: search term: Frank
RFM: Set posts_per_page to -1 for search
```

### Scenarie 2: Søg efter lokation "Odense"
**URL:** `/?post_type=rfm_expert&s=&rfm_category=&rfm_location=Odense&rfm_radius=`

**Forventet:**
- ✅ Finder alle eksperter med "Odense" i by-felt eller postnummer 5240

**Shortcode logik:**
1. Detekterer `rfm_location=Odense`
2. Finder koordinater for Odense (5240): lat 55.4219, lng 10.4208
3. Hvis radius er angivet: filtrerer efter afstand
4. Hvis ingen radius: søger i `_rfm_city` meta field med LIKE

### Scenarie 3: Kombineret søgning
**URL:** `/?post_type=rfm_expert&s=yoga&rfm_category=krop-bevaegelse&rfm_location=København&rfm_radius=25`

**Forventet:**
- ✅ Viser yoga-eksperter i kategori "Krop & Bevægelse" inden for 25km fra København

---

## 🔍 Afhængigheder fra tidligere versioner

v3.9.7 **afhænger af** fixes fra tidligere versioner:

### v3.9.4
- ✅ `get_coordinates_by_city()` metode til bynavn-søgning
- ✅ Extended search i taxonomier og meta felter

### v3.9.5
- ✅ `posts_per_page = -1` for search queries i `modify_expert_query()`

### v3.9.6
- ✅ Shortcode læser URL parametre (`s`, `rfm_category`, `rfm_location`, `rfm_radius`)
- ✅ Shortcode fjerner pagination limit når der søges
- ✅ Location radius filtering efter query execution

**v3.9.7 tilføjer:**
- ✅ Custom template der **faktisk bruger** shortcode'n når man søger
- ✅ Template loader filter i `RFM_Public`

---

## 📝 Tekniske Detaljer

### Template Hierarchy

**Standard WordPress:**
```
/?post_type=rfm_expert&s=Frank
    ↓
search.php (tema) → Ingen shortcode → Nothing found ❌
```

**Med v3.9.7:**
```
/?post_type=rfm_expert&s=Frank
    ↓
template_include filter
    ↓
archive-rfm_expert.php (plugin) → [rfm_expert_list] → Resultater vises ✅
```

### URL Parameter Flow

1. **Search form submits** → `/?post_type=rfm_expert&rfm_location=Odense`
2. **WordPress routing** → Ser post_type + search params
3. **Template loader** → `load_expert_archive_template()` aktiveres
4. **Custom template loads** → `archive-rfm_expert.php`
5. **Shortcode renders** → `[rfm_expert_list]`
6. **Shortcode detects search** → `$is_search = true`
7. **Shortcode reads params** → `$_GET['rfm_location'] = 'Odense'`
8. **Location search** → `get_coordinates_by_city('Odense')`
9. **Results rendered** → Expert cards displayed

---

## 🎨 Styling

Template bruger:
- `.rfm-expert-archive` - Container class
- `.rfm-container` - Inner container
- `.rfm-expert-results` - Results wrapper

Template inkluderer automatisk:
- `get_header()` - Tema header
- `get_footer()` - Tema footer
- Alle plugin CSS/JS via `wp_enqueue_scripts`

---

## 🔐 Sikkerhed

- ✅ Bruger `do_shortcode()` (safe output)
- ✅ URL parameters saniteres af shortcode (v3.9.6)
- ✅ Template fil er i plugin mappe (sikker placering)
- ✅ Ingen direkte database queries i template

---

## 📚 Upgrade Noter

**Efter opdatering til v3.9.7:**

1. Upload og aktiver plugin
2. Test søgefunktionen:
   - Søg "Frank" → skal vise 2 resultater
   - Søg "Odense" → skal finde eksperter i Odense
   - Søg med radius → skal filtrere efter afstand
3. Verificer at custom template loader:
   - Besøg `/?post_type=rfm_expert`
   - Skal vise archive-rfm_expert.php template
   - Skal inkludere tema header/footer

**Ingen database-ændringer krævet.**

---

## 🐛 Debug Tips

Hvis søgningen stadig ikke virker efter v3.9.7:

1. **Tjek template loader:**
   ```php
   // Tilføj i wp-config.php
   define('WP_DEBUG', true);
   define('WP_DEBUG_LOG', true);

   // Log output vil vise:
   // RFM: is_search: YES
   // RFM: post_type: rfm_expert
   ```

2. **Tjek om custom template loader:**
   - View page source
   - Se om der er `rfm-expert-archive` class i HTML
   - Hvis ikke: Template loader virker ikke

3. **Tjek shortcode:**
   - Tilføj `debug="true"` til shortcode i template
   - `[rfm_expert_list columns="3" debug="true"]`
   - Viser query args og post count

---

## 🎯 Konklusion

v3.9.7 løser **det fundamentale problem** at søgeresultater vistes i en template **uden** `[rfm_expert_list]` shortcode'n.

**Tidligere versioner (v3.9.4-v3.9.6) var korrekte**, men de kunne ikke virke fordi shortcode'n aldrig blev kaldt på search results siden.

Nu virker **hele søgesystemet som forventet**! 🚀

**Flow:**
- v3.9.4: Tilføjede city name search ✅
- v3.9.5: Fjernede pagination limit ✅
- v3.9.6: Shortcode læser URL parametre ✅
- **v3.9.7: Template bruger shortcode'n** ✅ ← **DET MANGLENDE STYKKE!**
