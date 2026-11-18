# Aanvulling op handoff OMC Studio: drie modi en Edit met OpenPencil

Aanvulling A1, 18 november 2026. Hoort bij `specs/kickoff.md` (het hoofddocument, daar inmiddels versie 1.3).
Bij verschil gaat deze aanvulling voor op het hoofddocument. Alle H-regels blijven gelden.

## 0. Wat verandert

1. Het canvas krijgt drie modi: **Bekijk**, **Bewerk** en **Edit**.
2. **Edit** is een visuele opmaakmodus op basis van OpenPencil (https://github.com/open-pencil/open-pencil): domeinen, clusters en activiteiten slepen, verplaatsen, vergroten en verkleinen, plus fonts, kleuren en uitlijning.
3. De eerdere keuze "geen vrije tekeneditor" (K1) vervalt voor de Edit-modus. Automatische opmaak blijft bestaan als startpositie en als terugvaloptie.
4. Het frontend wordt Vue 3 in plaats van React (wijziging op K6), omdat de OpenPencil-SDK alleen voor Vue bestaat.

## 1. Feiten over OpenPencil (gecontroleerd op 18 november 2026)

Bron: README van de repository, de releasepagina en de SDK-documentatie op openpencil.dev. De map `packages/` en `package.json` konden niet worden gelezen; wat daarvan afhangt staat in hoofdstuk 8 als te toetsen.

| Onderwerp | Bevinding |
|---|---|
| Wat het is | Open-source ontwerpeditor met een "headless Vue SDK" om eigen editors mee te bouwen |
| Licentie | MIT (alleen het onderdeel Cloud is Apache 2.0; dat gebruiken wij niet) |
| Status | Laatste release v0.16.0. Auteurs: actief in ontwikkeling, bruikbaar, met ruwe randen. Recente releases bevatten brekende wijzigingen |
| Rendering | Skia via CanvasKit (WASM), dus op een canvas-element, niet in HTML of SVG |
| Layout | Yoga (WASM), flex en grid |
| UI-laag | Vue 3 (vereist Vue 3.5.41 of hoger), Reka UI, Tailwind 4 |
| Pakketten | `@open-pencil/core`, `@open-pencil/scene-graph`, `@open-pencil/vue`, `@open-pencil/canvaskit` |
| Inbedden | Gedocumenteerd voor Vue: `createEditor`, `SceneGraph`, `provideEditor`, `useCanvas`, `useSelectionState`, `useEditorCommands`, `usePosition`. Voor React niet gedocumenteerd |
| Assets | CanvasKit en meegeleverde fonts worden bij het starten geladen; met Vite via de plugin `openPencil()` uit `@open-pencil/core/node/vite`, van de eigen server |
| Export | PNG, JPG, WEBP, SVG, PDF en meer |
| Aanwezig in de editor | Slepen, verplaatsen, formaat wijzigen, auto layout, fills, strokes, fontkeuze, undo en redo, lagen |
| Niet aangetroffen in de documentatie | Een wijzigingsgebeurtenis voor de hostapplicatie; het vergrendelen van knopen of eigenschappen vanuit de host; uitlijn- en verdeelknoppen; snappen op objecten |
| Netwerk | Telemetrie zit in de officiele app-builds, AI alleen met eigen sleutel, samenwerking via WebRTC, iconen standaard via Iconify. Of de SDK zelf iets naar buiten stuurt is niet beschreven |
| Browsers | Chrome 111, Edge 111, Firefox 128, Safari 16.4 of nieuwer |

## 2. Aanvullende harde regels

| Nr | Regel |
|---|---|
| H11 | De drie modi tonen hetzelfde canvas: een element staat in Bekijk en Bewerk op dezelfde plek en met dezelfde maat als in Edit (afwijking maximaal 1 pixel bij 100% zoom). |
| H12 | Edit wijzigt alleen opmaak: positie, maat, kleur, rand, font en uitlijning. Edit maakt, verwijdert, hernoemt en verplaatst geen elementen in de structuur. Dat gebeurt uitsluitend in Bewerk. |
| H13 | Onze database is de enige bron. Opmaak staat als eigen JSON in de database. Er wordt geen OpenPencil-bestand als waarheid bewaard. |
| H14 | OpenPencil zit achter een eigen adapter in een map. Buiten die map komt geen import uit `@open-pencil/*` voor. Versies zijn exact vastgepind. |
| H15 | H1 geldt ook hier: de Edit-modus doet geen enkel verzoek naar buiten. AI, samenwerking, telemetrie en externe iconen zijn niet meegebouwd of staan uit. |

## 3. Aangepaste en nieuwe keuzes

| Nr | Keuze | Reden |
|---|---|---|
| K6 (gewijzigd) | Laravel, Inertia en **Vue 3** met TypeScript, Tailwind, SQLite, Docker | De SDK is Vue. Een Vue-eiland binnen React geeft twee frameworks en meer risico. Inertia werkt gelijk met Vue |
| K1 (gewijzigd) | Automatische opmaak blijft de startpositie van elk element. Zodra een element in Edit is aangepast, geldt de handmatige opmaak | Zo werkt een nieuw canvas direct, zonder dat alles eerst gesleept moet worden |
| K10 | Bekijk en Bewerk tekenen het canvas met een eigen lichte HTML-weergave op basis van de opgeslagen opmaak. Alleen Edit laadt OpenPencil | Overlays, klikken en inline bewerken zijn in HTML eenvoudig; de afhankelijkheid blijft tot een modus beperkt (H14) |
| K11 | De scene in Edit wordt bij het openen opgebouwd uit model plus opmaak, en bij opslaan teruggelezen naar onze JSON | Volgt uit H13. Geen tweede bestandsformaat te beheren |
| K12 | Het eigenschappenpaneel in Edit is eigen bouw op de headless SDK en toont alleen de eigenschappen uit hoofdstuk 5 | Wat niet in onze JSON past, kan dan ook niet worden ingesteld en dus niet verloren gaan |
| K13 | Area's zijn in Edit vergrendeld (vaste plek en maat uit het sjabloon). Domeinen blijven binnen hun area, clusters en activiteiten binnen hun domein | Area's liggen vast (H2); voorkomt dat structuur en beeld uit elkaar lopen |
| K14 | Vaste, meegeleverde fontlijst; dezelfde fontbestanden voor de HTML-weergave en voor OpenPencil | Nodig voor H11 en H15 |
| K15 | Eerst een technische proef (WP-E0) met een go of no-go, voor er verder wordt gebouwd | De documentatie laat vier punten open, zie hoofdstuk 8 |

## 4. De drie modi

| Modus | Voor wie | Wat kan | Wat kan niet | Techniek |
|---|---|---|---|---|
| Bekijk | Lezer en Beheerder | Canvas zien, zoomen, overlays, detailpaneel, versies, exporteren | Niets wijzigen | HTML-weergave |
| Bewerk | Beheerder | Inhoud en structuur: CRUD op domeinen, clusters, activiteiten, koersblok; namen, omschrijving, modaliteit; toekennen en volgorde; snel invoeren | Vrij positioneren, stijl per element | HTML-weergave met bewerkfuncties |
| Edit | Beheerder | Opmaak: slepen, verplaatsen, vergroten, verkleinen; fonts, kleuren, randen, uitlijning; undo en redo | Structuur of teksten wijzigen | OpenPencil via de adapter |

1. Een schakelaar met drie standen in de topbalk: Bekijk, Bewerk, Edit. De gekozen modus staat in de URL.
2. Wisselen met niet-opgeslagen wijzigingen vraagt: opslaan, weggooien of blijven.
3. Edit heeft een eigen knop Opslaan en toont "niet opgeslagen" zolang er wijzigingen zijn. Daarnaast elke 30 seconden een lokale tussenstand in de browser als vangnet bij een crash.
4. Een Lezer ziet alleen Bekijk.
5. Een definitief boekjaar blokkeert Edit niet: opmaak raakt de cijfers niet.

## 5. Wat in Edit instelbaar is

| Groep | Eigenschap | Geldt voor | Opslag in opmaak-JSON |
|---|---|---|---|
| Plaats | x, y (ten opzichte van de ouder) | domein, cluster, activiteit | `x`, `y` |
| Maat | breedte, hoogte, met minimum 40 bij 24 | domein, cluster, activiteit | `w`, `h` |
| Vulling | kleur met dekking | alle | `fill` |
| Rand | kleur, dikte, stijl (doorgetrokken, gestreept), hoekradius | alle | `stroke`, `strokeWidth`, `strokeStyle`, `radius` |
| Font | familie (vaste lijst), grootte, gewicht, cursief, kleur | titel van domein en cluster, tekst van activiteit | `font` |
| Tekstuitlijning | links, midden, rechts; boven, midden, onder | alle | `textAlign`, `textValign` |
| Objecten uitlijnen | links, midden, rechts, boven, midden, onder ten opzichte van selectie of ouder | selectie van 1 of meer | resultaat in `x`, `y` |
| Verdelen | horizontaal en verticaal gelijk verdelen | selectie van 3 of meer | resultaat in `x`, `y` |
| Gelijke maat | zelfde breedte of hoogte als eerste selectie | selectie van 2 of meer | resultaat in `w`, `h` |
| Volgorde | naar voren, naar achteren binnen de ouder | alle | `z` |

1. Meervoudige selectie met shift-klik en met een selectiekader.
2. Pijltjestoetsen verplaatsen 1 pixel, met shift 8 pixels.
3. Snappen op een raster van 8 pixels, aan en uit te zetten. Snappen op andere objecten alleen als de SDK het levert.
4. Stijl kopieren en plakken tussen elementen van dezelfde soort.
5. "Toepassen op alle activiteiten in dit domein" en "op alle domeinen in deze area" voor stijl, niet voor plaats en maat.
6. "Terug naar automatisch" per element, per domein en voor het hele canvas, met bevestiging.
7. De tekst zelf is in Edit niet te wijzigen (H12). Dubbelklik toont een hint "tekst wijzig je in Bewerk".

## 6. Opmaak: opslag en regels

### 6.1 Datamodel (aanvulling op hoofdstuk 9)

| Tabel | Kernkolommen |
|---|---|
| element_layouts | element_id (uniek), canvas_id, mode (auto of manual), x, y, w, h, z, style_json, bijgewerkt_op |
| canvases (extra) | artboard_w, artboard_h, layout_version |
| fonts | sleutel, schermnaam, bestanden per gewicht (seed, alleen-lezen) |

1. Coordinaten zijn hele pixels op een vast tekenvlak (standaard 1680 bij 1188, verhouding A3 liggend), ten opzichte van de ouder.
2. `style_json` bevat alleen de sleutels uit hoofdstuk 5. De server weigert onbekende sleutels.
3. `layout_version` is een geheel getal met kleine upgradefuncties bij laden.
4. Het thema uit het hoofddocument blijft de standaard per area en niveau. `style_json` is de afwijking per element. Weergave = thema plus afwijking.
5. Opmaak gaat mee in momentopnamen, in de JSON-export en bij het kopieren van een canvas.

### 6.2 Samenspel met Bewerk

| Gebeurtenis in Bewerk | Gevolg voor opmaak |
|---|---|
| Nieuw element in een ouder met automatische opmaak | Automatisch geplaatst |
| Nieuw element in een ouder met handmatige opmaak | Geplaatst onder het laagste kind, standaardmaat. Past het niet, dan groeit de ouder in hoogte en verschijnt een melding "opmaak aangepast" |
| Element verplaatst naar andere ouder | Opmaak van het element vervalt naar automatisch binnen de nieuwe ouder |
| Element verwijderd of gearchiveerd | Opmaakregel vervalt mee |
| Naam langer dan de maat toelaat | Tekst wordt afgekapt met drie puntjes en volledig getoond bij aanwijzen; lijst Waarschuwingen noemt het element |

### 6.3 Regels bij opslaan vanuit Edit

1. Opslaan stuurt de hele opmaak van het canvas in een verzoek; de server verwerkt het in een transactie.
2. De server controleert: elk id bestaat en is actief; geen onbekende ids; ouder in de scene gelijk aan ouder in het model; kind binnen de grenzen van de ouder; maat boven het minimum; alleen toegestane stijlsleutels.
3. Kinderen die buiten de ouder steken worden door de adapter voor het opslaan teruggeschoven en de gebruiker ziet welke.
4. Een knoop die in de scene ontbreekt of extra is, betekent dat H12 is omzeild: opslaan wordt geweigerd en de scene wordt opnieuw opgebouwd.
5. Bij een gelijktijdige wijziging van de structuur door iemand anders (model nieuwer dan bij openen) krijgt de gebruiker de keuze: opmaak toepassen op wat nog bestaat, of opnieuw laden.

## 7. Adapter (H14)

Map: `resources/js/edit-engine/`. Publieke interface, verder niets:

| Functie | Doel |
|---|---|
| `mount(el, document, options)` | Editor starten op een DOM-element met model, thema en opmaak |
| `getLayout()` | Huidige opmaak als onze JSON |
| `onChange(cb)` | Melding bij elke wijziging (voor "niet opgeslagen" en tussenstand) |
| `select(ids)`, `getSelection()` | Selectie sturen en lezen |
| `apply(command, args)` | Uitlijnen, verdelen, stijl zetten, terug naar automatisch |
| `undo()`, `redo()`, `zoomToFit()` | Bediening |
| `exportImage(type)` | PNG of SVG van het tekenvlak |
| `destroy()` | Opruimen bij verlaten van de modus |

1. Elk element wordt een knoop met het element-id in de knoopnaam of in de bijbehorende metadata. Domein en cluster zijn frames, activiteit is een frame met tekst.
2. Area's en het koersblok worden als vergrendelde achtergrond opgebouwd.
3. Gereedschappen voor nieuwe vormen, tekst, pen, componenten, variabelen, AI en samenwerking worden niet getoond en hun sneltoetsen zijn uitgeschakeld. Delete en Backspace doen niets.
4. Levert de SDK geen wijzigingsgebeurtenis, dan vergelijkt de adapter na elke afgeronde handeling (loslaten muis, toets, commando) de scene met de vorige stand.
5. Levert de SDK geen uitlijnen en verdelen, dan rekent de adapter dit zelf uit en zet de posities via de editorcommando's, zodat undo blijft werken.
6. CanvasKit en fonts komen van de eigen server via de Vite-plugin. Geen externe iconenbron.
7. De Edit-modus wordt apart geladen (eigen bundel), zodat Bekijk en Bewerk snel blijven.

## 8. Technische proef WP-E0 (go of no-go)

Doel: de open punten uit de documentatie beantwoorden met een werkend minimaal voorbeeld in de projectopzet (Laravel, Inertia, Vue, Vite), voor er verder wordt gebouwd.

| Nr | Te bewijzen | Go als |
|---|---|---|
| P1 | Installeren en bouwen met de gekozen pakketbeheerder, binnen Docker | Schone build zonder handwerk; versies vastgepind |
| P2 | Scene opbouwen uit JSON: 3 domeinen, 1 cluster, 10 activiteiten met tekst, kleur en font | Beeld klopt met de invoer |
| P3 | Positie, maat en stijl teruglezen per knoop, met behoud van het element-id | Heen en terug zonder verschil |
| P4 | Wijzigingen opmerken vanuit de host | Via een gebeurtenis of via vergelijken, binnen 100 ms na de handeling |
| P5 | Beperken: geen knopen maken, verwijderen of van ouder wisselen; area's vergrendeld | Lukt via de SDK of via de adapter, aantoonbaar met een test |
| P6 | Geen netwerkverkeer naar buiten bij laden en gebruik | Netwerklog toont alleen de eigen server |
| P7 | Gelijkheid met de HTML-weergave | Positie en maat binnen 1 pixel; tekstregels gelijk bij de vaste fontlijst |
| P8 | Undo en redo over slepen, maat, stijl en uitlijnen | Werkt per handeling |
| P9 | Laadtijd en geheugen van de Edit-bundel | Eerste keer bruikbaar binnen 3 seconden op een gewone laptop |

1. Uitkomst is een kort verslag per punt met bewijs (schermafbeelding, test of log).
2. Go: alle punten gehaald, of P4, P5 en de uitlijnfuncties opgelost in de adapter.
3. No-go: P2, P3, P6 of P7 niet haalbaar. Dan stopt het werk aan Edit en volgt een melding aan de product owner met het verslag. Bekijk en Bewerk gaan door op automatische opmaak; er wordt niet op eigen initiatief een andere editor gekozen.

## 9. Werkpakketten (aanvulling op hoofdstuk 14)

| WP | Inhoud | Omvang | Model | Effort | Hangt af van |
|---|---|---|---|---|---|
| WP1 (gewijzigd) | Projectopzet met Vue 3 in plaats van React | S | Sonnet 5 | medium | WP0 |
| WP-E0 | Technische proef uit hoofdstuk 8 | S | Fable 5 | high | WP1 |
| WP-E1 | Opmaakmodel: tabel, JSON-schema, servercontroles, automatische startopmaak, HTML-weergave op basis van opgeslagen opmaak, samenspel met Bewerk (6.2) | M | Sonnet 5 | high | WP4 |
| WP-E2 | Adapter: scene opbouwen, teruglezen, wijzigingen, beperkingen, undo en redo | L | Sonnet 5, review Fable 5 | high | WP-E0, WP-E1 |
| WP-E3 | Edit-scherm: modusschakelaar, eigenschappenpaneel, uitlijnen, verdelen, gelijke maat, stijl kopieren, terug naar automatisch, opslaan en tussenstand | M | Sonnet 5 | high | WP-E2 |
| WP-E4 | Gelijkheid van de drie modi, export met handmatige opmaak, momentopnamen met opmaak, netwerktest, prestaties | M | Sonnet 5, review Fable 5 | medium | WP-E3, WP9 |

1. WP4 uit het hoofddocument (Bekijk en Bewerk op automatische opmaak) blijft de eerste oplevering en wacht niet op Edit.
2. Edit vormt een eigen oplevering na oplevering 1 en kan parallel lopen aan de financiele laag.
3. Alle onderdelen in het hoofddocument waar React staat, lezen als Vue 3. Slepen in Bewerk met een Vue-geschikte bibliotheek.

## 10. Acceptatiecriteria Edit

1. De schakelaar toont drie modi; een Lezer ziet er een.
2. Een domein slepen en vergroten in Edit, opslaan en naar Bekijk gaan toont het domein op dezelfde plek en maat (H11).
3. Een cluster met vijf activiteiten verplaatsen neemt de activiteiten mee.
4. Font, grootte, gewicht en kleur van een activiteit wijzigen is na opslaan zichtbaar in alle drie de modi en in de PNG-export.
5. Drie activiteiten selecteren en "links uitlijnen" en "verticaal verdelen" geeft gelijke x en gelijke tussenruimte; een keer undo herstelt de vorige stand.
6. Een activiteit is niet buiten haar domein te laten staan na opslaan; de gebruiker ziet welke zijn teruggeschoven.
7. In Edit kan niets worden aangemaakt, verwijderd of hernoemd, ook niet met sneltoetsen.
8. Een rechtstreeks API-verzoek met een onbekend id, een andere ouder of een onbekende stijlsleutel wordt geweigerd.
9. Een nieuw domein in Bewerk verschijnt direct zichtbaar, ook op een canvas met handmatige opmaak.
10. "Terug naar automatisch" voor het hele canvas geeft exact de opmaak van een nieuw canvas met dezelfde inhoud.
11. Tijdens een volledige Edit-sessie gaat geen enkel verzoek naar een andere host dan de eigen server.
12. Browser sluiten met niet-opgeslagen wijzigingen en opnieuw openen biedt de tussenstand aan.

## 11. Tests (aanvulling op hoofdstuk 16)

1. Eenheidstests op het JSON-schema en de servercontroles uit 6.3.
2. Heen-en-terugtest van de adapter: opmaak in, scene, opmaak uit, zonder verschil.
3. Beeldvergelijking tussen HTML-weergave en Edit voor drie referentiecanvassen (klein, middel, vol), tolerantie 1 pixel op posities.
4. Playwright: de criteria 2 tot en met 7 en 12 uit hoofdstuk 10.
5. Netwerktest: Playwright faalt bij elk verzoek buiten de eigen host.
6. Statische controle op H14: geen import uit `@open-pencil/*` buiten de adaptermap.
7. Upgradecheck: een script dat de adaptertests draait tegen een nieuwere versie van de pakketten, zodat een upgrade een bewuste stap is.

## 12. Risico's

| Risico | Gevolg | Maatregel |
|---|---|---|
| Jonge bibliotheek met brekende wijzigingen per release | Upgrade kost tijd | Exact vastpinnen, adapter (H14), upgradecheck |
| Geen gedocumenteerde wijzigingsgebeurtenis en vergrendeling | Meer werk in de adapter | WP-E0 punten P4 en P5 |
| Twee weergaven (HTML en canvas) lopen uiteen, vooral bij tekst | H11 niet gehaald | Vaste fontlijst, zelfde regelhoogte, tekst afkappen in plaats van laten groeien, beeldvergelijking |
| Zware bundel (WASM) | Trage eerste keer laden | Apart laden van Edit, lokale cache |
| Verborgen netwerkverkeer | Breekt H1 en H15 | Netwerktest in CI |
| Licentie | Laag | MIT; licentietekst opnemen in de repository |

## 13. Aannames met standaardkeuze

| Nr | Aanname | Standaard |
|---|---|---|
| A7 | Namen van de modi in het scherm | Bekijk, Bewerk, Edit |
| A8 | Fontlijst | Inter, Arial-vervanger (Arimo), Georgia-vervanger (Gelasio), elk normaal, vet en cursief, lokaal meegeleverd |
| A9 | Area's verplaatsen of van maat wijzigen | Niet in Edit; vaste indeling uit het sjabloon |
| A10 | Tekenvlak | 1680 bij 1188 pixels, een maat per canvas |
| A11 | Lijst-area's (Actoren, Kanalen, Waardeproposities) | Zelfde Edit-mogelijkheden als activiteiten |
