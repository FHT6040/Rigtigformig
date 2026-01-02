# Version 3.0.2 Changelog

## Dato: 3. december 2024

### Rettelser (Bug Fixes)

#### 🖼️ Certificerings/Diplom Billeder
- **Rettet**: Certificeringsbilleder vises nu på ekspert-profiler
  - Problem: Billeder blev hentet men ikke vist i HTML
  - Løsning: Rettet feltnavn fra `certificate_images` til `image_id` og tilføjet visning i profil
  - Tilføjet: Pæn CSS styling med hover-effekt og responsivt design
  - Billeder kan klikkes for at se i fuld størrelse

#### 🔒 Banner Billede Restriktion
- **Tilføjet**: Banner billede er nu kun tilgængeligt for Standard og Premium planer
  - Gratis plan kan IKKE uploade eller vise banner billeder
  - Standard og Premium kan uploade og vise banner billeder
  - Implementeret både i frontend profil-visning og ekspert dashboard
  - Gratis brugere ser en "upgrade notice" hvor upload feltet ville være

### CSS Forbedringer

#### Nye Styles
- **Certificeringsbilleder**:
  - Responsive billeder (max 300px på desktop, 100% på mobil)
  - Border og box-shadow for professionelt udseende
  - Hover-effekt med lift animation
  - Klikbare links til fuld størrelse

- **Erfaring Sektion**:
  - Grøn badge styling med hvid tekst
  - Klar og tydelig visning af erfaring i år

- **Specialiseringer**:
  - Opdateret hover-effekt (skifter til grøn ved hover)
  - Border for bedre definition
  - Smooth transitions

### Tekniske Detaljer

#### Ændrede Filer
1. `includes/class-rfm-expert-profile.php`
   - Rettet certificeringsbillede visning (linje 377-399)
   - Tilføjet banner billede plan-tjek (linje 63-68)

2. `includes/class-rfm-frontend-registration.php`
   - Tilføjet banner billede upload restriktion (linje 774-807)
   - Gratis brugere ser locked feature notice

3. `assets/css/public.css`
   - Tilføjet `.rfm-education-certificate` styling
   - Tilføjet `.rfm-certificate-link` og `.rfm-certificate-img` styling
   - Opdateret `.rfm-experience-years` styling
   - Forbedret `.rfm-specialization-tag` styling med hover

#### Database
Ingen ændringer - bruger eksisterende struktur

### Før og Efter

#### Certificeringsbilleder

**Før v3.0.2:**
```
Uddannelser
- Titel: Certified Executive Coach
- Institution: MHT Academy
- År: 2012-2014
(Intet billede selvom det er uploaded)
```

**Efter v3.0.2:**
```
Uddannelser
- Titel: Certified Executive Coach
- Institution: MHT Academy
- År: 2012-2014
- [BILLEDE AF CERTIFIKAT] (klikbart)
```

#### Banner Billede Restriktion

**Gratis Plan:**
```
❌ Kan ikke uploade banner billede
❌ Eksisterende banner billeder vises ikke på profil
✅ Ser "upgrade notice" i dashboard
```

**Standard/Premium Plan:**
```
✅ Kan uploade banner billede
✅ Banner billede vises på profil
✅ Kan fjerne banner billede
```

### Upgrade Påvirkning

#### For Gratis Brugere:
- Hvis du havde et banner billede før, vises det IKKE længere
- Du skal opgradere til Standard eller Premium for at få det tilbage
- Banner billedet er stadig gemt i databasen - intet går tabt

#### For Standard/Premium Brugere:
- Ingen ændringer - alt fungerer som før
- Banner billeder vises stadig normalt

#### For Alle Brugere:
- Certificeringsbilleder vises nu pænt på profiler
- Bedre visuelt udtryk for uddannelser

### Installation
1. Deaktiver den gamle version (IKKE slet)
2. Upload version 3.0.2
3. Aktiver pluginet
4. Tjek at profiler viser certificeringsbilleder korrekt
5. Verificer at banner billede restriktioner virker

### Kompatibilitet
- WordPress: 5.8+
- PHP: 7.4+
- Elementor: 3.0+ (valgfrit)
- MySQL: 5.6+
- Kompatibel med v3.0.0 og v3.0.1 data

### Test Checklist

#### Certificeringsbilleder:
```
□ Upload uddannelse med certifikat billede i dashboard
□ Gem uddannelse
□ Gå til din offentlige profil
□ Verificer at billede vises under uddannelsen
□ Klik på billede og verificer at det åbner i fuld størrelse
```

#### Banner Billede Restriktion:
```
□ Log ind som gratis bruger
□ Gå til dashboard
□ Verificer at banner upload er låst med upgrade notice
□ Gå til din offentlige profil
□ Verificer at banner IKKE vises
□ Opgrader til Standard
□ Verificer at banner upload nu er tilgængelig
□ Upload banner billede
□ Verificer at det vises på profil
```

### Kendte Begrænsninger
- Kun ét certificeringsbillede per uddannelse (som designet)
- Banner billeder forsvinder fra profiler for gratis brugere (feature, ikke bug)

### Support
Kontakt: [email@rigtigformig.dk]
Dokumentation: Se CHANGELOG-3.0.1.md for tidligere ændringer
