# Verschillen tussen de bronnen

Datum: 18 november 2026. Vergeleken: kickoff v1.2 van de PO (`specs/kickoff-v1.2-po.md`), de aanvulling A1 (`specs/kickoff-addendum-edit-mode.md`), het design (`specs/design/`, vijf borden) en de spec (`spec.md`, gebouwd op repo-versie 1.3 van de kickoff).

## Spelregels van de PO

1. De poortantwoorden uit repo-versie 1.3 (Onbekende allocatie, incident, omvang, H8) gaan voor op alle drie de bronnen.
2. Voor schermen en namen leiden v1.2 en het design. De drie modi heten Invoeren, Tekenen en Bekijken.
3. Voor de techniek leidt de aanvulling. Dat betekent geheel Vue 3, opmaak als eigen gegevens per element en geen tekendocument als waarheid. OpenPencil zit achter de adapter met vastgepinde versies, eerst komt de technische proef met go of no-go, en H11 t/m H15 gelden.
4. In de aanvulling lees je "Bewerk" als Invoeren en "Edit" als Tekenen.
5. Waar deze regels een punt niet eenduidig beslissen, staat het op **open**. Open punten staan in de spec als `[GATE-Cn]`, met hetzelfde nummer als hieronder.

Per punt staat hieronder wat elke bron zegt, wat er uit de keuze volgt, een advies en de status.

## Beslist

**1. Namen en aantal modi.** v1.2 en het design hebben Invoeren, Tekenen en Bekijken. De aanvulling heeft Bekijk, Bewerk en Edit.
- Gevolg: in code heten de modi Entry, Drawing en View. Het woord "edit" komt voor geen enkele modus meer voor.
- Status: **beslist door PO**: Invoeren, Tekenen, Bekijken, in die volgorde in de schakelaar.

**2. Frontend.** v1.2 zegt React met een Vue-eiland en een terugval naar Vue (A7). De aanvulling zegt geheel Vue 3 (K6 gewijzigd). Het design is een React-prototype.
- Gevolg: ADR 0004 wordt vervangen door ADR 0012. Het design is alleen een visuele referentie, geen code om over te nemen.
- Status: **beslist door PO**: geheel Vue 3.

**3. Wat Bekijken tekent.** v1.2 S6 zegt een alleen-lezen weergave van de tekening. De aanvulling K10 zegt een eigen HTML-weergave.
- Gevolg: OpenPencil wordt alleen in Tekenen geladen. Bekijken blijft snel, en overlays zijn gewone HTML.
- Status: **beslist door PO** (a): Bekijken tekent een eigen HTML-weergave op basis van de opgeslagen opmaak, gelijk aan de tekening (H11).

**4. Opslag van opmaak.** v1.2 heeft per canvas een tekendocument `layout_doc`. De aanvulling heeft opmaak per element als eigen JSON (H13, K11).
- Gevolg: de server controleert elke opmaakwaarde, en er is geen tweede bestandsformaat om te beheren.
- Status: **beslist door PO**: eigen gegevens per element, geen tekendocument als waarheid.

**5. Thema.** v1.2 en het design laten het themascherm vervallen. De aanvulling houdt het thema als standaard. De spec heeft User Story 15 "Thema".
- Gevolg: het themascherm verdwijnt uit de spec. Standaardkleuren worden meegeleverd, en daarboven komt de stijl per element uit Tekenen.
- Status: **beslist door PO** (c): geen apart themascherm. Kleuren stelt de gebruiker in Tekenen in, en de standaardkleuren liggen daaronder.

**6. Invoermodus.** v1.2 en het design hebben een boom met pijlen, zonder tekenvlak en zonder slepen. De aanvulling heeft een HTML-canvas met bewerkfuncties en slepen.
- Gevolg: User Story 11 wordt herschreven. Verplaatsen gaat via het veld "Hangt onder", de volgorde via pijlen.
- Status: **beslist door PO**: boom met pijlen.

**7. Planning.** In v1.2 zit Tekenen in oplevering 1, na een spike van twee dagen. In de aanvulling is Tekenen een eigen oplevering na oplevering 1, met proef WP-E0.
- Gevolg: oplevering 1 is Invoeren en Bekijken op automatische opmaak.
- Status: **beslist door PO** (b): Tekenen is een eigen oplevering na oplevering 1. WP-E0 loopt direct na de projectopzet.

**8. Export naar PNG en PDF.** v1.2 laat dit via de tekenbibliotheek lopen. De aanvulling laat dit via HTML lopen.
- Gevolg: export werkt ook zonder Tekenen en ook bij een no-go.
- Status: **beslist door PO** (techniek, plus punt 3): uit de HTML-weergave.

**9. Nummers K10, A7 en A8.** Die nummers betekenen in v1.2 iets anders dan in de aanvulling.
- Gevolg: de spec noemt altijd de bron erbij ("v1.2 K10", "aanvulling K10").
- Status: **beslist door PO** (geen inhoudelijke keuze).

**10. Bibliotheek lokaal en afgeschermd.** v1.2 K10 (vastgepind, telemetrie, AI en WebRTC uit, geen cloud) en de aanvulling (H14, H15) zeggen hetzelfde.
- Status: **beslist door PO**: beide gelden.

**11. Nieuw element in de tekening.** v1.2 zegt: een vrije plek binnen de ouder, gemarkeerd "nieuw". De aanvulling 6.2 zegt: automatisch, of onder het laagste kind, waarbij de ouder groeit.
- Status: **beslist door PO**: plaatsing volgens de aanvulling (techniek), markering "nieuw" volgens v1.2 en het design (scherm).

**12. Tekenen verandert geen structuur.** Alle bronnen zijn het eens (v1.2 6.5.7, aanvulling H12).
- Status: **beslist**: geen verschil.

**13. Lagenpaneel in Tekenen.** v1.2 en het design hebben links "Lagen", één laag per element. De aanvulling noemt het niet, en de kickoff 1.3 sloot een lagenpaneel uit.
- Gevolg: het paneel is ook de manier om met het toetsenbord te selecteren.
- Status: **beslist door PO** (scherm): paneel "Lagen" komt er.

**14. Kolombreedte per domein.** v1.2 schrapt de kolom, en het design toont geen veld. De spec had "1 tot 4 kolommen".
- Gevolg: bij automatische opmaak hebben alle domeinkaarten dezelfde breedte. Maat regel je in Tekenen.
- Status: **beslist door PO** (scherm en v1.2): vervalt.

**15. Correcties uit fase 1 en de poort.** v1.2 mist ze, bijvoorbeeld boekjaren per canvas en de tekst "niet toegewezen" in H7.
- Status: **beslist door PO**: de ADR's en poortantwoorden van repo-versie 1.3 gaan voor.

**16. Tests.** v1.2 16.8 en aanvulling 11 vullen elkaar aan.
- Status: **beslist**: beide gelden.

**17. Acceptatiecriteria 3a en 3b van v1.2 (Tekenen in oplevering 1).**
- Status: **beslist door PO** via punt 7: ze gaan naar de oplevering Tekenen.

**18. Spike van twee dagen tegenover WP-E0.**
- Status: **beslist door PO** via punt 7: WP-E0 met go of no-go, direct na de projectopzet.

**19. "Niet in versie 1".** De lijst van v1.2 (geen vrije vormen, losse teksten, afbeeldingen, AI, samenwerken, cloud of .fig en .pen) en aanvulling H15 vullen elkaar aan.
- Status: **beslist**: beide gelden.

**20. Schil.** Het design heeft tabbladen bovenin (Canvas, Kostenposten, Toewijzen, Versies, Beheer), een paneel links per scherm en een detailpaneel rechts. De spec had een zijbalk met menu.
- Gevolg: de constitutie spreekt van "top menu, sidebar, detail window". Het linkerpaneel per scherm vult de rol van de zijbalk.
- Advies: de formulering van de constitutie bij de poort aanpassen.
- Status: **beslist door PO** (scherm).

**21. Boekjaarkeuze.** Het design zet één keuze in de kop: "Boekjaar 2026 open". In de spec kiest elk scherm zelf.
- Status: **beslist door PO** (scherm): één keuze in de kop, met de status van dat jaar voor het open canvas.

**22. Kostenposten en Toewijzen.** In het design zijn dit tabbladen binnen het open canvas, en de toewijzingsstatus geldt voor dat canvas. De spec had een keuzelijst "Toewijzingsstatus voor canvas".
- Status: **beslist door PO** (scherm). Zie punt 43 voor een organisatie zonder canvas.

**23. Teksten in Toewijzen.** Het design heeft "Set leegmaken", "Wijzigingen weggooien", "Snel: Gelijk verdelen over 3 doelen" en de status "Concept 80%" voor niet-opgeslagen werk.
- Gevolg: een concept wordt nooit opgeslagen (H5).
- Status: **beslist door PO** (namen).

**24. Uitsplitsing per kostenpost.** Het design toont het toegewezen percentage van de kostenpost (60%, € 126.000). De spec toonde het aandeel in het domeintotaal.
- Status: **beslist door PO** (scherm): het toegewezen percentage.

**25. "Archiveren" bij een element in Invoeren.** Het design heeft die knop. In de spec archiveer je een element alleen via Herverdelen.
- Gevolg: archiveren mag alleen zonder toewijzingsregels in de deelboom. Met toewijzingsregels opent Herverdelen.
- Status: **beslist door PO** (scherm).

**26. Onbekende allocatie in het design.** Het design toont geen domeinkaart "Onbekende allocatie". Het totaal van Ondersteunende activiteiten (€ 333.400) laat € 81.000 weg, en het label is "Niet toegewezen".
- Gevolg: de kaart komt erbij, het totaal van Ondersteunende activiteiten wordt € 414.400, en het label wordt "Onbekende allocatie".
- Status: **beslist door poortantwoord** (gaat voor).

**27. Knop "Jaar definitief maken" op Kostenposten,** met "Nog 2 te gaan".
- Gevolg: de regel zelf (pas definitief als Onbekende allocatie leeg is) blijft de aanname `[GATE-U1]`.
- Status: **beslist door PO** (scherm).

**28. Standaardkleuren.** Het design laat per area kleuren zien: lila voor Richten en Beheersen, zand voor Primaire en lichtblauw voor Ondersteunende activiteiten.
- Status: **beslist door PO** (c plus scherm): dit zijn de meegeleverde standaardkleuren.

**29. Label "Tekenvlak: OpenPencil, lokaal meegeleverd" in Tekenen.** Geen H8-naam.
- Status: **beslist door PO** (scherm).

**30. Schermen die niet in het design staan.** Dat zijn het overzicht, inloggen, Mijn account, de beheerschermen, Logboek, Systeemmeldingen, Versies en de dialogen (snel invoeren, herverdelen, overnemen, importeren).
- Status: **geen botsing**: de spec houdt ze, in de stijl van het ontwerpcontract.

**31. Knop "Importeren uit CSV" in Invoeren.**
- Status: **beslist door PO** (plek): de knop staat in Invoeren. Hij verschijnt pas in oplevering 3, waar de structuurimport zit, dus zonder stub in oplevering 1.

## Open

**32. Opslaan in Tekenen.** Het design toont "Opmaak opgeslagen", wat op automatisch opslaan lijkt. De aanvulling heeft een knop Opslaan, de tekst "niet opgeslagen" en elke 30 seconden een lokale tussenstand.
- Gevolg: automatisch opslaan betekent veel kleine serverchecks en geen bewuste stap.
- Advies: knop en tussenstand uit de aanvulling, met de statusteksten uit het design.
- Status: **open**.

**33. Wat in Tekenen instelbaar is.** Het design toont plek, grootte, vulling, rand, tekstkleur en hoeken. De aanvulling (hoofdstuk 5) heeft ook font, randdikte en randstijl, tekstuitlijning, volgorde, stijl kopiëren, gelijke maat en een raster.
- Advies: de lijst van de aanvulling; het design is een schets.
- Status: **open**.

**34. Area's in Tekenen.** In de aanvulling (K13, A9) zijn ze vergrendeld. In v1.2 6.5.3 mag de gebruiker "alles vrij verplaatsen en vergroten".
- Advies: vergrendeld, want area's liggen vast (H2).
- Status: **open**.

**35. Koersblok en logo in Tekenen.** In v1.2 6.5.8 zijn ze verplaatsbaar, en het design toont een laag "Koers". In de aanvulling 7.2 zijn ze een vergrendelde achtergrond.
- Advies: vergrendeld.
- Status: **open**.

**36. Tekenvlak.** De aanvulling (A10) heeft een vast vlak van 1680 bij 1188, liggend A3. Het design toont een hoog, staand canvas.
- Gevolg: bij een vast vlak passen 500 elementen niet.
- Advies: een vaste breedte van 1680 waarbij de hoogte meegroeit met de inhoud.
- Status: **open**.

**37. Fontlijst.** De aanvulling (A8) noemt Inter, Arimo en Gelasio. Het design gebruikt Inter en Space Grotesk, die laatste voor koppen, bedragen en titels op het canvas.
- Advies: Inter en Space Grotesk, beide lokaal meegeleverd.
- Status: **open**.

**38. Koersblok verschilt tussen modi.** In Tekenen staan de doelen onder missie en visie; in Bekijken staan ze in vier kolommen. Dat botst met H11.
- Advies: één indeling, die van Bekijken.
- Status: **open**.

**39. Financiële overlay.** Het design toont in Bekijken andere kaartmaten dan in Tekenen, en geen activiteiten. Dat botst met H11.
- Advies: dezelfde plek en maat houden en de overlay binnen de kaart tekenen.
- Status: **open**.

**40. Bedragen en percentages.** Het design toont hele euro's (€ 84.000) en een aandeel met één decimaal (2,8%). De spec heeft overal € 1.234,56 en 12,40%.
- Gevolg: afgeronde getallen kunnen optellen tot een ander totaal dan getoond.
- Advies: overal centen en twee decimalen.
- Status: **open**.

**41. Uitsplitsing per modaliteit.** Het design laat Onbekende allocatie weg ("telt hier niet mee"). De spec (SD-18) telt haar mee, zodat de uitsplitsing optelt tot het totaal.
- Advies: meetellen.
- Status: **open**.

**42. Velden van een kostenpost.** Het formulier in het design mist leverancier, kostenplaats, omschrijving en opmerking. v1.2 7.1 en de spec hebben ze.
- Advies: de velden blijven.
- Status: **open**.

**43. Kostenposten zonder canvas.** Het design toont Kostenposten alleen binnen een canvas.
- Gevolg: een organisatie zonder canvas heeft dan geen plek voor haar kostenposten.
- Advies: ook bereikbaar vanuit het overzicht, per organisatie.
- Status: **open**.

**44. Invoerregel "Activiteit of cluster toevoegen".** Het design laat niet zien hoe je voor een cluster kiest.
- Advies: een keuze "Activiteit | Cluster" naast de regel, met Activiteit als standaard.
- Status: **open**.

**45. Contrast in het design.** Gemeten op de borden, tegen de spec WB3 (minstens 3:1):
- randen van invoervelden #AEB4BC: 2,1:1;
- randen van knoppen #DCDFE2: 1,3:1;
- het gele deel van de prognosebalk #C9A227: 2,4:1;
- de rand van de knop Verwijderen #D9B4AF: 1,9:1.
- Advies: donkerder maken, bijvoorbeeld #8A919B (3,2:1).
- Status: **open**.

**46. Focus en klikvlak.** Het design heeft één blauwe focusrand, waar WB2 een donkere en een lichte rand vraagt. De selectievakjes zijn 16 px, waar WB4 24 px of ruimte eromheen vraagt.
- Advies: WB2 en WB4 houden.
- Status: **open**.

**47. Terug naar automatisch.** Het design heeft "Terug naar automatische plek" en "Alles automatisch opmaken". De aanvulling heeft dit per element, per domein en voor het hele canvas, en zet ook de stijl terug.
- Advies: plek, maat en stijl terugzetten, en "per domein" toevoegen.
- Status: **open**.

**48. Lange namen.** Het design laat namen in Tekenen doorlopen op een volgende regel. De aanvulling (6.2, risico's) kapt ze af met drie puntjes, zodat beide weergaven gelijk blijven (H11).
- Gevolg: bij doorlopen moet een element meegroeien, en dat kan niet bij een vaste handmatige maat.
- Advies: doorlopen bij automatische opmaak, en afkappen met een waarschuwing als een handmatige maat te klein is.
- Status: **open**.

## Nieuwe vragen die volgen uit de besluiten

**49. Kleuren in oplevering 1.** Zonder themascherm, en met Tekenen pas later, zijn kleuren in oplevering 1 niet aan te passen.
- Advies: accepteren.
- Status: **open**.

**50. Kleuren bij een no-go op WP-E0.** Dan komt Tekenen er niet, en zijn kleuren in versie 1 nooit aan te passen.
- Advies: dan alsnog een eenvoudig kleurenscherm, als apart besluit.
- Status: **open**.
