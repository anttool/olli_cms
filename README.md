# Olli CMS ◈ Moderni Tietokannaton Sisällönhallintajärjestelmä

**Olli CMS** on nopea, moderni ja täysin tietokannaton (*Flat-File*) PHP-sisällönhallintajärjestelmä. Se yhdistää **Moodle-henkisen monipalstaisen alue- ja lohkoarkkitehtuurin** sekä raikkaan **Bento Grid -muotoilun**, joka erottuu edukseen perinteisistä WordPress-sivustoista.

---

## ✨ Tärkeimmät ominaisuudet

1. **Ei tietokantaa (100 % Flat-File):**
   * Kaikki sivut, artikkelit, palstoitukset, ulkoasuasetukset ja yhteydenottoviestit tallennetaan suoraan `content/`-kansion JSON-tiedostoihin.
   * **Helppo siirto:** Kopioi kansio tietokoneelta webhotelliin (FTP/SFTP/Git) – mitään tietokanta-asennusta tai konfigurointia ei tarvita.
2. **Valittavat sivupalstoitukset (Page Layouts):**
   * `1col-full` — 1 palsta: Täysleveä (100 %)
   * `2col-left` — 2 palstaa: Vasen sivupalsta (30 % / 70 %)
   * `2col-right` — 2 palstaa: Oikea sivupalsta (70 % / 30 %)
   * `2col-equal` — 2 palstaa: Tasajako (50 % / 50 %)
   * `3col-moodle` — 3 palstaa: Moodle / Portaali (25 % / 50 % / 25 %)
   * `3col-bento` — 3 palstaa: Bento-tasajako (33 % / 33 % / 33 %)
3. **12 valmista sisältöelementtiä (Lohkoa / Widgettiä):**
   * 🚀 **Hero-nosto / Banneri**
   * 📝 **Teksti & Artikkelisisältö (Rich Text)**
   * 💡 **Huomiolaatikko / Info (Moodle-tyyli)**
   * 📂 **Haitari / Kurssimoduulit / UKK (Accordion)**
   * 📰 **Artikkelilistaus (Uutissyöte kategoriasuodatuksella)**
   * 🔗 **Pikavalikko / Tapahtumalista**
   * 📎 **Tiedostot & Ladattavat materiaalit**
   * 🖼️ **Kuvagalleria suurennoksella (Lightbox)**
   * 📊 **Mittarit & Avainluvut (Bento Stats)**
   * ✉️ **Yhteydenottolomake (tallentaa viestit suoraan hallintapaneeliin)**
   * 🎬 **Video / Kartta / Upote (YouTube & iframe)**
   * 💻 **Koodi- / Komentolaatikko kopiointipainikkeella**
4. **Moodle-tyylinen suora muokkaustila + täysi Hallintapaneeli:**
   * Kytke yläpalkista **Muokkaustila päälle**, jolloin voit vaihtaa sivun palstoitusta, raahata lohkoja palstasta toiseen (Drag & Drop) ja lisätä uusia elementtejä suoraan julkisella sivulla.
   * Tai käytä kattavaa **Hallintapaneelia (`admin/`)**, josta hallitset sivuja, artikkeleita, teemapaketteja, värejä, fontteja, mediapankkia ja ZIP-varmuuskopioita.

---

## 🚀 Käyttöönotto

### A. Webhotellissa
1. Siirrä kaikki kansion tiedostot webhotellin julkiseen kansioon (esim. `public_html/`).
2. Avaa sivuston osoite selaimessa – järjestelmä luo automaattisesti `content/`-kansion ja esimerkkisisällöt ensimmäisellä latauskerralla!

### B. Omalla tietokoneella
1. Tuplaklikkaa `kaynnista_palvelin.bat` tai aja komentorivillä projektikansiossa:
   ```bash
   php -S localhost:8000
   ```
2. Avaa selaimessa:
   * **Julkinen sivusto:** `http://localhost:8000`
   * **Hallintapaneeli:** `http://localhost:8000/admin/`

---

## 🔐 Oletuskirjautumistunnukset

* **Käyttäjätunnus:** `admin`
* **Salasana:** `admin123`
*(Voit vaihtaa salasanan heti Hallintapaneelin **Valikot & Asetukset** -välilehdeltä).*
