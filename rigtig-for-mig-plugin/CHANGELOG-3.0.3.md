# Version 3.0.3 Changelog

## Dato: 3. december 2024

### UI/UX Forbedringer

#### 🎨 Uddannelser Layout Forbedret
- **Ændret**: Certificeringsbilleder er nu 50% mindre (150px i stedet for 300px)
  - Bedre visuel balance på siden
  - Mindre dominerende i layoutet
  - Stadig klikbar for fuld størrelse

- **Ændret**: Omorganiseret uddannelses-elementers rækkefølge
  - Institution tekst er nu **fed/bold** for bedre synlighed
  - Certificeringsbillede vises nu FØR beskrivelse teksten
  - Giver mere logisk læseflow: Titel → Institution → År → Billede → Beskrivelse

### Layout Før vs. Efter

**Før v3.0.3:**
```
Certified Executive Coach
MMT Academy | v/Rasmus Bagger         (normal tekst)
2012 - 2014

Beskrivelse tekst her...

[STORT CERTIFIKAT BILLEDE - 300px]     (efter beskrivelse)
```

**Efter v3.0.3:**
```
Certified Executive Coach
MMT Academy | v/Rasmus Bagger         (bold tekst)
2012 - 2014

[MINDRE CERTIFIKAT BILLEDE - 150px]    (før beskrivelse)

Beskrivelse tekst her...
```

### Fordele ved Ændringerne

1. **Bedre Læsbarhed**
   - Fed institution tekst springer i øjnene
   - Mindre klemt layout
   - Bedre luft omkring elementerne

2. **Bedre Visuel Hierarki**
   - Billede kommer naturligt efter fakta (titel, institution, år)
   - Beskrivelse kommer til sidst som uddybende info
   - Mindre billede distrahere ikke fra indholdet

3. **Mobil-venlig**
   - Mindre billeder betyder hurtigere load
   - Bedre proportioner på små skærme
   - Stadig 100% bredde på mobil når nødvendigt

### Tekniske Detaljer

#### Ændrede Filer
1. `includes/class-rfm-expert-profile.php`
   - Tilføjet `<strong>` tag omkring institution tekst
   - Flyttet certificeringsbillede før beskrivelse

2. `assets/css/public.css`
   - Opdateret `.rfm-certificate-img` max-width: 300px → 150px
   - Opdateret margin på `.rfm-education-certificate`
   - Fjernet redundant font-weight fra `.rfm-education-institution`

### CSS Ændringer

```css
/* Før */
.rfm-education-institution {
    margin: 5px 0;
    color: #666;
    font-weight: 500; /* Ikke bold nok */
}

.rfm-certificate-img {
    max-width: 300px; /* For stort */
}

/* Efter */
.rfm-education-institution {
    margin: 5px 0;
    color: #666;
    /* Bold kommer fra <strong> tag */
}

.rfm-certificate-img {
    max-width: 150px; /* 50% mindre */
}
```

### HTML Struktur

```html
<!-- Ny struktur -->
<div class="rfm-education-item">
    <h4>Certified Executive Coach</h4>
    <p><strong>MMT Academy | v/Rasmus Bagger</strong></p>
    <div>2012 - 2014</div>
    
    <!-- Billede FØR beskrivelse -->
    <div class="rfm-education-certificate">
        <a href="[fuld størrelse]">
            <img src="[150px billede]" />
        </a>
    </div>
    
    <!-- Beskrivelse EFTER billede -->
    <p>C-level coaching værkstøjer...</p>
</div>
```

### Ingen Breaking Changes
- ✅ Bagud-kompatibel med v3.0.0, v3.0.1, v3.0.2
- ✅ Ingen database ændringer
- ✅ Ingen nye dependencies
- ✅ Eksisterende data uberørt

### Installation
1. Deaktiver v3.0.2 (IKKE slet)
2. Upload v3.0.3
3. Aktiver
4. Verificer at uddannelser vises pænt

### Test Checklist
```
□ Institution tekst er fed/bold
□ Certificeringsbillede er mindre (ca. 150px bred)
□ Billede vises FØR beskrivelse
□ Hover-effekt fungerer stadig
□ Klik for fuld størrelse virker
□ Mobil visning OK (100% bredde)
□ Intet overlap eller klemt layout
```

### Kompatibilitet
- WordPress: 5.8+
- PHP: 7.4+
- Browsers: Alle moderne browsers
- Responsive: Ja (mobil-optimeret)

### Support
Kontakt: [email@rigtigformig.dk]
Se også: CHANGELOG-3.0.2.md og CHANGELOG-3.0.1.md
