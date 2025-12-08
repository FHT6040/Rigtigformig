# CHANGELOG - Version 3.4.1

**Release Date:** 7. december 2024
**Type:** Feature Enhancement
**Prioritet:** MEDIUM - Uddannelses-visning opdatering

---

## Hvad er nyt i v3.4.1

### Uddannelses-visning Format Opdatering

**Nyt format:** Uddannelser vises nu i formatet "Institution | v/Instruktør"

**Eksempel:**
```
MMT Academy | v/Rasmus Bagger

C-level coaching værktøjer, strategier og inner circle insights
De universielle love om ledelse.
De 7 Master Steps – next level leadership.
21st Century Leadership Skill Setting Workshop.
```

Med certifikat-billede til højre.

---

## Tekniske Detaljer

### Ændrede Filer:

#### 1. `includes/class-rfm-expert-profile.php`
- Ny header-logik der bygger "Institution | v/Instructor" format
- Understøtter nyt `instructor` felt
- Bagudkompatibel: Bruger `title` som instruktør-navn hvis `institution` er udfyldt
- Certifikat-billede vises først (floater til højre)
- Beskrivelse vises under header

#### 2. `assets/css/public.css`
- Ny `.rfm-education-header` klasse
- Styling til "Institution | v/Instructor" header
- Font-weight og farve optimering

#### 3. `rigtig-for-mig.php`
- Version opdateret til 3.4.1

---

## Visnings-logik

**Header bygges således:**

1. Hvis `institution` findes → tilføj til header
2. Hvis `instructor` findes → tilføj "v/{instructor}"
3. Ellers hvis `title` OG `institution` findes → tilføj "v/{title}"
4. Hvis kun `title` findes (ingen institution) → vis `title` alene

**Eksempler:**
- Institution: "MMT Academy", Title: "Rasmus Bagger" → "MMT Academy | v/Rasmus Bagger"
- Institution: "MMT Academy", Instructor: "John Doe" → "MMT Academy | v/John Doe"
- Title: "Coach Uddannelse" (ingen institution) → "Coach Uddannelse"

---

## Installation

1. Download `rigtig-for-mig-v3.4.1.zip`
2. WordPress Admin → Plugins → Tilføj ny → Upload
3. Upload og aktiver
4. **RYD CACHE:** CTRL+SHIFT+DELETE (browser) + LiteSpeed purge

---

## Upgrade fra v3.4.0

Ingen breaking changes. Direkte opgradering mulig.

**Bemærk:** Eksisterende uddannelser vil automatisk vises i det nye format baseret på de udfyldte felter.

---

*Release completed: 7. december 2024*
