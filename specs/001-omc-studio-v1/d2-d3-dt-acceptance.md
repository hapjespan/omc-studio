# Acceptatie oplevering 2, oplevering 3 en Tekenen

Dit document hoort bij de financiële laag (oplevering 2), export, import en versies (oplevering 3) en Tekenen. Het werkt zoals `d1-acceptance.md`: per scherm **Doe dit** en **Je hoort te zien**, met de Nederlandse knoppen tussen aanhalingstekens. Alle stappen zijn in de voorproef op `http://localhost:8080`, ingelogd als beheerder, met "Voorbeelddata" aan (Beheer, "Voorbeelddata") zodat er kosten en een structuur zijn. Het bewijs per punt staat in `dt-results.md` en in `specs/design/compare/README.md`.

## Besluiten van 2026-11-20

1. **Referrer: optie A.** Opslaan op een pagina die je via een link bereikte laat je op die pagina (ADR 0046).
2. **Bekijken** opent passend op ongeveer 62% (de breedte van de kolom). De zoomknoppen gaan tot 100% en "Passend maken" past het hele canvas.
3. **Het label "Doelen"** op de canvaskaart in "Overzicht" blijft.
4. **De schermen van oplevering 3 zijn akkoord** in de huidige vormgeving.

## Oplevering 2: kosten

**Kostenposten.** Open een canvas, kies de tab "Kostenposten". *Doe dit:* kies "Nieuwe kostenpost", vul de velden in en kies "Opslaan". Probeer ook "Importeren uit CSV" met een bestand met één foutieve regel. *Je hoort te zien:* de kostenpost staat in de lijst met bedragen per "Boekjaar". Het CSV-bestand met een fout wijzigt niets en toont de regel met de fout.

**Eigenaren en Applicaties** (Beheer). *Doe dit:* voeg twee eigenaren toe, gebruik er één bij een kostenpost en verwijder hem met "Vervangen door". *Je hoort te zien:* de kostenpost hoort daarna bij de vervanger.

**Toewijzen.** Kies de tab "Toewijzen", kies een kostenpost en verdeel hem over activiteiten. *Doe dit:* vul percentages in die samen 100% zijn en kies "Opslaan". Probeer eerst 99,99%. *Je hoort te zien:* "Nog te verdelen" telt mee terwijl je typt. Opslaan kan pas bij 0,00% over; daarna "Verdeling opgeslagen" en de status "Toegewezen".

**Jaar herverdelen, overnemen, definitief maken** (Kostenposten). *Doe dit:* verwijder een activiteit met kosten en kies wat er met de kosten gebeurt; neem een jaar over met "Jaar overnemen"; kies "Jaar definitief maken". *Je hoort te zien:* definitief maken weigert zolang er kosten in "Onbekende allocatie" staan. Daarna kun je niets meer wijzigen aan dat jaar, tot je het heropent.

**Bekijken met overlay.** Kies "Bekijken" en zet "Overlay" op "Financieel", daarna op "Modaliteit". *Je hoort te zien:* bedragen en aandelen op de blokken, een legenda, en bij een blok de uitsplitsing per kostenpost. De totalen komen overeen met de som van de kostenposten.

**Systeemmeldingen** (Beheer). *Je hoort te zien:* "Geen systeemmeldingen gevonden", of een melding met "Gezien" als de cijfers eens één cent niet sluiten.

## Oplevering 3: export, import en versies

**Versies.** Kies de tab "Versies". *Doe dit:* "Momentopname maken", geef een naam, open hem met "Openen", maak hem weer weg. *Je hoort te zien:* de momentopname toont de cijfers van dat moment, ook als je daarna het canvas wijzigt. Verwijderen vraagt eerst bevestiging.

**Exporteren en importeren van één canvas.** Menu "Exporteren" in Bekijken. *Doe dit:* "Exporteren als JSON", daarna "Importeren uit JSON" met dat bestand; probeer ook "Kostenposten exporteren", "Toewijzingen exporteren", "Exporteren als PNG" en "Afdrukken". *Je hoort te zien:* een kopie met dezelfde structuur, kosten en opmaak. De PNG en de afdruk tonen de tekening zoals in Bekijken.

**Volledige export en import** (Beheer, "Volledige export en import"). *Doe dit:* "Alles exporteren". Importeren kan alleen in een lege applicatie. *Je hoort te zien:* één bestand met alle organisaties, canvassen en sjablonen, zonder gebruikers en logboek.

**Voorbeelddata** (Beheer). *Doe dit:* zet de voorbeelddata aan en weer uit met "Voorbeelddata verwijderen". *Je hoort te zien:* alleen de voorbeeldgegevens verdwijnen.

**Back-up en terugzetten.** Zie de README (`make backup`, `make restore FOLDER=<map>`); niet geprobeerd in de voorproef.

## Tekenen

Kies in een canvas "Tekenen". Links "Lagen", rechts "Eigenschappen".

**Verplaatsen en grootte.** *Doe dit:* klik een domein in de tekening of in "Lagen", wijzig X, Y, Breedte of Hoogte, of sleep het blok. Gebruik "Ongedaan maken". *Je hoort te zien:* het blok volgt, "Opmaak opgeslagen" verschijnt vanzelf en na herladen staat alles op dezelfde plek. In "Bekijken" staat het blok binnen 1 pixel op dezelfde plek.

**Kleur en tekst.** *Doe dit:* zet een "Vulling" op `#FFFFFF`, een donkere vulling en een tekstkleur `#FFFFFE`. Gebruik "Stijl naar alle domeinen hier". *Je hoort te zien:* een tekstkleur onder 4,5:1 wordt geweigerd met de verhouding als tekst, het veld blijft ongeldig met de focus erop. Bij een donkere vulling springt donkere tekst automatisch naar wit, met een melding. De stijl naar alle domeinen laat plaatsen en groottes ongemoeid.

**Verplaatsen naar een andere plek in de structuur.** *Doe dit:* houd Shift ingedrukt bij het slepen, of gebruik "Verplaatsen naar" in de eigenschappen en bevestig. *Je hoort te zien:* het blok hangt onder het nieuwe onderdeel; kan dat niet, dan staat de reden erbij.

**Alles automatisch opmaken.** *Doe dit:* kies "Alles automatisch opmaken" en bevestig met "Terugzetten", of gebruik bij één blok "Terug naar automatische plek". *Je hoort te zien:* plek, grootte en stijl staan terug op de opmaak van een nieuw canvas ("Opmaak teruggezet naar automatisch"). Wijzigt een tweede beheerder tegelijk de opmaak, dan krijg je "Iemand anders heeft de opmaak gewijzigd. De tekening is opnieuw geladen" en gaat er niets stilletjes verloren.

## Bewijs voor de eerder open punten

- **Slepen en kleur (SC-025).** `tests/e2e/drawing-colour-drag.spec.ts`: een domein met de muis verslepen en kleuren; na herladen staan plek en vulling vast, en Bekijken wijkt hooguit 1 pixel af. Groen op Chromium, Firefox en WebKit.
- **Nieuw element in één reis (SC-026).** `tests/e2e/dt-new-element-journey.spec.ts`: een activiteit toevoegen onder een handmatig geplaatst domein in Invoeren, naar Tekenen (rij met "nieuw", geplaatst onder het laagste handmatige kind, zonder overlap), naar Bekijken (zelfde plek binnen 1 pixel). Groen op drie engines.
- **Eerste gebruik van Tekenen met 500 elementen (SC-029).** `tests/e2e/drawing-performance.spec.ts`, grens 3 seconden voor de traagste 5% van 20 metingen. Rustige run: Chromium 1690 ms, Firefox 1806 ms, WebKit 1130 ms. Tijdens een andere zware testrun gaf dezelfde spec 4448 ms (Chromium) en 3052 ms (Firefox) door uitschieters; de grens is niet aangepast.

## Wat nog openstaat

- Een nieuw domein naast een handmatig geplaatst domein in dezelfde Area komt onder het laagste handmatige domein van die Area te staan (FR-106a, FR-108, commit ef13083); dit wordt bewezen in `tests/e2e/dt-new-element-journey.spec.ts`. Dit punt is dus opgelost.
- SC-001 (schone start binnen 3 minuten) is niet gedraaid: Docker ontbreekt in de ontwikkelcontainer. De twee commando's moeten nog op de laptop van de PO draaien (zie `d1-acceptance.md`).
- De metingen zijn gedaan in een container; een gewone laptop is niet beschikbaar (K-6).
