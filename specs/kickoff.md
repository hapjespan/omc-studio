# Handoff orchestrator: OMC Studio (lokaal canvas met financiele laag)

Versie 1.1, 18 november 2026 (1.1: volledige CRUD toegevoegd, zie hoofdstuk 8A). Opdrachtgever en product owner: Martien (Indicia). Project: canvas-model.
Werktitel applicatie: **OMC Studio**. De naam is een instelling, geen vaste tekst in de code.

## 0. Opdracht in het kort

Bouw een lokaal draaiende webapplicatie waarmee de product owner zelf:

1. een Operating Model Canvas (OMC) aanmaakt en vult, zonder externe database;
2. domeinen toekent aan vaste area's, met binnen elk domein activiteiten, al dan niet geclusterd;
3. kostenposten met percentages toewijst aan domein, cluster en/of activiteit, altijd tot precies 100%;
4. de financiele laag als overlay op het canvas bekijkt.

Het moet werken en snel op te zetten zijn. Eenvoud gaat boven volledigheid.

## 1. Context

1. Referentie is een bestaande canvasapplicatie die haar inhoud uit een extern platform haalt dat met de hand wordt gevuld. Die koppeling vervalt volledig. Een breakdown van de referentie is op te vragen bij de product owner; dit handoff is zelfstandig leesbaar en gaat voor bij verschillen. Vanwege H8 staat de naam van de referentie hier bewust niet.
2. Eerste toepassing: de eerste klant (kinderopvang en primair onderwijs, regio Tilburg, circa 35 locaties, circa 30 applicaties in het landschap).
3. De product owner is geen developer. Schermteksten in gewoon Nederlands.

## 2. Harde regels

| Nr | Regel |
|---|---|
| H1 | Geen enkele externe koppeling of externe database. Geen CDN, geen externe fonts, geen telemetrie. Alle data blijft op de machine waar de applicatie draait. |
| H2 | De area's liggen vast in de code (seed). Gebruikers kunnen ze niet toevoegen, verwijderen of hernoemen. |
| H3 | Binnen een area bestaat altijd de structuur domein > activiteit. Een activiteit hangt direct onder een domein of onder een cluster binnen dat domein. Geen activiteit zonder domein. |
| H4 | Een cluster is optioneel, hoort bij precies een domein en bevat een of meer activiteiten. Clusters zijn niet nestbaar. |
| H5 | Een kostenpost is per jaar of niet toegewezen (0 regels) of volledig toegewezen (som precies 100,00%). Een tussenstand kan niet worden opgeslagen. |
| H6 | Toewijzen kan aan een domein, een cluster en/of een activiteit, ook gemengd binnen een kostenpost. |
| H7 | Totalen sluiten altijd: som kostenposten = som toegewezen + som niet toegewezen, op de cent. |
| H8 | Nergens in schermen, exports, bestandsnamen, code of documentatie de naam van de referentie-applicatie, het externe platform of het adviesbureau achter de methodiek. Gebruik neutrale termen. |
| H9 | Opstarten op een schone machine met Docker in maximaal twee commando's, met werkende voorbeelddata. |
| H10 | Alles wat de gebruiker invoert is via de schermen aan te maken, in te zien, te wijzigen en te verwijderen (CRUD), volgens de matrix in 8A. Niets vereist een databasetool, seed of import om te beheren. |

## 3. Genomen keuzes (niet opnieuw ter discussie)

| Nr | Keuze | Reden |
|---|---|---|
| K1 | Canvas met automatische opmaak in HTML en CSS (grid en flex), geen vrije tekeneditor | De vrije editor is het duurste deel van de referentie. Automatische opmaak maakt "zelf aanmaken en toekennen" direct bruikbaar |
| K2 | Kosten gaan rechtstreeks van kostenpost naar domein, cluster of activiteit. Geen tussenlagen | Sluit aan op de wens: percentage op domein, cluster en/of activiteit. Applicatie is wel een optioneel kenmerk van de kostenpost (K3) |
| K3 | Kostenpost heeft een optioneel veld "applicatie" uit een eigen lijst | De eerste klant heeft een applicatielandschap; zo is later te filteren zonder extra rekenlaag |
| K4 | Ook de area's Richten en Beheersen krijgen een domeinlaag | Volgt uit H3. Wijkt af van de referentie, waar activiteiten daar direct onder de area hangen |
| K5 | Actoren, Kanalen en Waardeproposities gebruiken dezelfde boom, met andere labels (groep > item) | Een model, een scherm, minder code |
| K6 | Stack: Laravel, Inertia, React, TypeScript, Tailwind, SQLite, Docker Compose | Gelijk aan de werkwijze van Indicia; SQLite is een bestand zonder beheer. Schrijf portable SQL zodat PostgreSQL via configuratie kan |
| K7 | Modaliteiten: Common, Adaptive, Specialized, Distinct | Indeling die in dit project als analysekader wordt gebruikt |
| K8 | Percentages als gehele basispunten (10000 = 100%), bedragen als gehele centen | Geen afrondingsverschillen |
| K9 | Meerdere organisaties en meerdere canvassen per organisatie | Kleine meerprijs, direct herbruikbaar voor andere klanten |

## 4. Scope

**In versie 1**

1. Inloggen, organisaties, canvassen.
2. Canvas aanmaken (leeg, vanaf sjabloon, kopie, import).
3. Structuur beheren: domeinen, clusters, activiteiten, groepen en items; toekennen en verplaatsen met slepen.
4. Koersblok: missie, visie, strategische doelen.
5. Modaliteit met onderbouwing per domein, cluster en activiteit.
6. Kostenposten per jaar, toewijzing met 100%-regel, overnemen van vorig jaar.
7. Overlays: Geen, Modaliteit, Financieel.
8. Export: PNG, PDF (afdrukken), JSON (alles), CSV (kostenposten en toewijzingen).
9. Versies: een canvas met cijfers vastleggen als onveranderlijke momentopname.

**Niet in versie 1**

1. Vrij positioneren, eigen afmetingen, lagenpaneel, hulplijnen.
2. Tussenlagen in de kostenstroom (technologie, oplossing).
3. Koppelingen met bronsystemen. Alleen bestandsimport.
4. Gelijktijdig bewerken door meerdere gebruikers in hetzelfde scherm.
5. Fijnmazige rechten per canvas.

## 5. Begrippen

| Term | Betekenis |
|---|---|
| Area | Vast vlak op het canvas. Acht stuks, zie 6.1 |
| Domein | Hoofdgroep binnen een area. Wordt door de gebruiker aangemaakt en aan een area toegekend |
| Cluster | Optionele bundel van activiteiten binnen een domein |
| Activiteit | Wat de organisatie doet, met resultaat, zonder het hoe |
| Groep, item | Naam van domein en activiteit in de area's Actoren, Kanalen en Waardeproposities |
| Modaliteit | Classificatie van een activiteit, cluster of domein, met onderbouwing |
| Kostenpost | Een budgetregel: contract, personeel, project of overig |
| Toewijzing | Percentage van een kostenpost in een jaar naar een doel op het canvas |
| Doel | Domein, cluster of activiteit in een area van soort "activiteiten" |
| Momentopname | Vastgelegde versie van canvas plus cijfers |

## 6. Canvasmodel

### 6.1 Vaste area's (seed, niet wijzigbaar)

| Volgorde | Sleutel | Schermnaam | Soort | Label niveau 1 | Label niveau 2 | Kosten toewijsbaar |
|---|---|---|---|---|---|---|
| 1 | koers | Koers | tekst | n.v.t. | n.v.t. | nee |
| 2 | richten | Richten | activiteiten | Domein | Activiteit | ja |
| 3 | beheersen | Beheersen | activiteiten | Domein | Activiteit | ja |
| 4 | actoren | Actoren | lijst | Groep | Actor | nee |
| 5 | kanalen | Kanalen | lijst | Groep | Kanaal | nee |
| 6 | waardeproposities | Waardeproposities | lijst | Groep | Propositie | nee |
| 7 | primair | Primaire activiteiten | activiteiten | Domein | Activiteit | ja |
| 8 | ondersteunend | Ondersteunende activiteiten | activiteiten | Domein | Activiteit | ja |

1. Area "koers" bevat geen boom maar drie tekstblokken: missie, visie en een lijst strategische doelen (titel plus toelichting).
2. Clusters bestaan alleen in area's van soort "activiteiten".

### 6.2 Structuurregels

| Soort element | Toegestane ouder | Verplicht |
|---|---|---|
| Domein of groep | Area (niet koers) | ja |
| Cluster | Domein in dezelfde area, alleen soort "activiteiten" | nee |
| Activiteit of item | Domein, of cluster binnen dat domein | ja |

1. Volgorde per ouder via een sorteerveld; slepen past dit aan.
2. Verplaatsen mag: activiteit naar ander domein of cluster; cluster naar ander domein (activiteiten gaan mee); domein naar andere area van dezelfde soort.
3. Een domein van soort "activiteiten" mag niet naar een lijst-area als er toewijzingen op of onder hangen.
4. Toewijzingen hangen aan het element-id en verhuizen dus mee.
5. Een cluster zonder activiteiten mag bestaan maar krijgt een waarschuwing "leeg cluster".
6. Namen zijn uniek binnen dezelfde ouder.

### 6.3 Velden per element

naam (verplicht), omschrijving, modaliteit (optioneel, alleen soort "activiteiten"), onderbouwing modaliteit, pictogram (optioneel, alleen lijst-area's), status (actief of gearchiveerd).

### 6.4 Verwijderen en archiveren

1. Element zonder toewijzingen en zonder kinderen: direct verwijderen.
2. Element met kinderen: bevestigen, kinderen gaan mee, mits geen van alle toewijzingen heeft.
3. Element met toewijzingen (zelf of via kinderen): niet verwijderen. Bied dialoog "Herverdelen" met twee keuzes per jaar:
   1. alle betrokken percentages overzetten naar een gekozen ander doel;
   2. de betrokken kostenposten terugzetten naar "niet toegewezen" (hele set weg, nooit een rest).
4. Na herverdelen wordt het element gearchiveerd, niet hard verwijderd, zodat momentopnamen leesbaar blijven.

### 6.5 Opmaak van het canvas (K1)

```
+--------------------------------------------------------------+
| Logo | Missie | Visie | Strategische doelen          (koers)  |
+-----------------------------+--------------------------------+
| Richten                     | Beheersen                      |
+--------+--------+-----------+--------------------------------+
| Actoren| Kanalen| Waarde-   | Primaire activiteiten          |
|        |        | proposities| [domein][domein][domein] ...  |
|        |        |           +--------------------------------+
|        |        |           | Ondersteunende activiteiten    |
+--------+--------+-----------+--------------------------------+
```

1. Domeinen lopen als kaarten naast elkaar binnen hun area en breken af naar een volgende rij.
2. Binnen een domeinkaart: eerst losse activiteiten, daarna clusters als ingesprongen blok met eigen titel.
3. Per domein instelbaar: breedte in kolommen (1 tot 4). Verder geen vrije positie.
4. Kleuren per area en per niveau instelbaar in een eenvoudig themascherm, opgeslagen als JSON per canvas met versienummer. Standaardthema meeleveren.
5. Het canvas past op een liggend A3 bij afdrukken en schaalt op scherm met zoom en "passend maken".

## 7. Financieel model

### 7.1 Kostenpost

| Veld | Type | Opmerking |
|---|---|---|
| code | tekst | optioneel, uniek per organisatie als gevuld |
| naam | tekst | verplicht |
| soort | keuze | Leverancierscontract, Personeel, Project, Overig |
| leverancier | tekst | optioneel; optioneel te koppelen aan een item in Actoren |
| eigenaar | keuze | organisatieonderdeel uit eigen lijst |
| kostenplaats | tekst | optioneel |
| applicatie | keuze | optioneel, uit eigen lijst (K3) |
| omschrijving | tekst | optioneel |

Per kostenpost per jaar:

| Veld | Type | Opmerking |
|---|---|---|
| budget | bedrag in centen | verplicht om te kunnen toewijzen |
| laatste prognose | bedrag in centen | optioneel |
| run, change | basispunten | optioneel, samen maximaal 10000; de rest heet "niet bepaald" |
| fte | getal, 2 decimalen | optioneel, vooral bij Personeel |
| peildatum, opmerking | datum, tekst | optioneel |

### 7.2 Toewijzing en de 100%-regel

1. Een toewijzingsset hoort bij een kostenpost en een jaar en bestaat uit regels (doel, percentage).
2. Opslaan gebeurt altijd als hele set in een transactie. De server weigert elke set waarvan de som niet precies 10000 basispunten is, of met een regel van 0 of minder, of met een dubbel doel.
3. Een lege set betekent "niet toegewezen". Dat is de enige toegestane tussenstand.
4. Doel is precies een element van soort domein, cluster of activiteit in een area waar kosten toewijsbaar zijn, en is actief.
5. Het scherm toont tijdens invoer een teller "nog te verdelen" en een knop "rest naar deze regel". Opslaan is uit zolang de teller niet op 0 staat.
6. Snelle acties: gelijk verdelen over de gekozen doelen; verdelen over alle activiteiten van een gekozen domein.
7. Een jaar kan "definitief" worden gezet. Dat lukt alleen als geen enkele kostenpost met budget in dat jaar op "niet toegewezen" staat. Een definitief jaar is alleen-lezen tot het wordt heropend.

### 7.3 Rekenregels

1. Bedrag per regel = budget x basispunten / 10000, in centen. Verdeel afrondingsresten met de methode van de grootste rest, zodat de regels samen exact het budget zijn. Doe hetzelfde voor de laatste prognose.
2. Run en change per regel = regelbedrag x run of change van de kostenpost.
3. Per element twee bedragen: **direct** (toewijzingen op het element zelf) en **totaal** (direct plus alles eronder).
4. Oprollen: activiteit naar cluster naar domein naar area naar canvas.
5. "Niet toegewezen" is een aparte post op canvasniveau: som van budgetten van kostenposten zonder set.
6. Controle na elke berekening (H7). Bij afwijking een fout tonen en loggen, geen cijfers tonen.
7. Aandeel van een element = totaal van het element / totaal van het canvas inclusief niet toegewezen.
8. Per modaliteit: som van direct-bedragen van elementen met die modaliteit. Elementen zonder modaliteit vallen onder "Geen modaliteit". Niet dubbel tellen via ouders.
9. De berekening staat in een pure module zonder database-aanroepen, zodat zij los te testen is.

### 7.4 Overnemen van vorig jaar

1. Kopieert per kostenpost de jaarwaarden (optioneel aan te vinken) en de toewijzingsset naar het doeljaar, alleen als het doeljaar voor die kostenpost nog leeg is.
2. Bevat een set een gearchiveerd doel, dan wordt die kostenpost niet overgenomen en komt hij in een rapportlijst "handmatig toewijzen".
3. Resultaatscherm: aantal overgenomen, overgeslagen, handmatig.

## 8. Schermen

| Nr | Scherm | Inhoud en acties |
|---|---|---|
| S1 | Inloggen | E-mail en wachtwoord, lokaal account |
| S2 | Overzicht | Organisaties en hun canvassen als kaarten met miniatuur; nieuw, kopie, importeren, archiveren |
| S3 | Nieuw canvas | Naam, organisatie, start: leeg, sjabloon of kopie. De acht area's staan er direct in |
| S4 | Canvas bewerken | Canvas in het midden. Per area "+ domein"; per domein "+ activiteit" en "+ cluster". Inline naam bewerken. Slepen om te sorteren en te verplaatsen. Rechter paneel met velden van het geselecteerde element. Lijst "Waarschuwingen" (lege clusters, domeinen zonder activiteiten) |
| S5 | Snel invoeren | Tekstvak: een regel per element, inspringen met tab of twee spaties bepaalt domein > cluster > activiteit. Voorbeeld van het resultaat tonen voor opslaan |
| S6 | Canvas bekijken | Alleen-lezen, overlaykeuze, zoom, detailpaneel, versiekeuze, exporteren |
| S7 | Kostenposten | Tabel per jaar met zoeken en filters (soort, eigenaar, applicatie, status toewijzing). Kolommen: naam, budget, prognose, toegewezen ja of nee. Toevoegen, bewerken, importeren uit CSV |
| S8 | Toewijzen | Links kostenposten met statusbolletje, midden de set van de gekozen kostenpost met teller, rechts de boom van doelen met bedragen. Doel toevoegen door klikken of slepen |
| S9 | Financiele overlay | Op S6: per domein bedrag, aandeel en balk prognose tegen budget; kleurintensiteit naar aandeel; bij aanwijzen de uitsplitsing per kostenpost. Zijpaneel: canvastotalen, niet toegewezen, per soort, per modaliteit, run en change. Filter: jaar, soort, eigenaar, applicatie |
| S10 | Modaliteit-overlay | Gekleurd merkteken per element, legenda, tellingen per domein |
| S11 | Versies | Momentopname maken (naam, jaar, opmerking), lijst, bekijken, archiveren |
| S12 | Beheer | Gebruikers en rol, organisaties, lijsten (eigenaren, applicaties), thema, volledige export en import |

Rollen: **Beheerder** (alles) en **Lezer** (S2, S6, S9, S10, S11 bekijken, exporteren).

## 8A. CRUD per gegevenssoort

Legenda: A = aanmaken, L = lezen, W = wijzigen, V = verwijderen. Alle schrijfacties alleen voor rol Beheerder; Lezer heeft alleen L.

| Gegevenssoort | A | L | W | V | Scherm | Regel bij verwijderen |
|---|---|---|---|---|---|---|
| Organisatie | ja | ja | ja | ja | S12 | Alleen als er geen canvassen en kostenposten meer onder hangen; anders archiveren |
| Canvas | ja | ja | ja | ja | S2 | Bevestigen met naam intypen; verwijdert elementen, toewijzingen en momentopnamen van dat canvas. Archiveren is de standaardknop |
| Area | nee | ja | nee | nee | n.v.t. | Vast (H2) |
| Koersblok: missie, visie, doel | ja | ja | ja | ja | S4 | Vrij |
| Domein of groep | ja | ja | ja | ja | S4, S5 | Zie 6.4 |
| Cluster | ja | ja | ja | ja | S4, S5 | Zie 6.4. Keuze: activiteiten mee verwijderen of terugzetten onder het domein |
| Activiteit of item | ja | ja | ja | ja | S4, S5 | Zie 6.4 |
| Modaliteit op element | ja | ja | ja | ja | S4 | Leegmaken is toegestaan |
| Kostenpost | ja | ja | ja | ja | S7 | Verwijdert ook de jaarwaarden en volledige toewijzingssets van die kostenpost. Geblokkeerd als de kostenpost in een definitief jaar voorkomt; dan eerst heropenen of archiveren |
| Jaarwaarden van kostenpost | ja | ja | ja | ja | S7 | Verwijdert ook de toewijzingsset van dat jaar. Niet in een definitief jaar |
| Toewijzingsset | ja | ja | ja | ja | S8 | Altijd de hele set (H5). Losse regel weghalen kan alleen in het scherm, opslaan pas weer bij 100% |
| Boekjaar | ja | ja | ja (definitief, heropenen) | ja | S7 | Alleen als er geen jaarwaarden in staan |
| Eigenaar (lijst) | ja | ja | ja | ja | S12 | Geblokkeerd als in gebruik; bied "vervangen door" aan |
| Applicatie (lijst) | ja | ja | ja | ja | S12 | Geblokkeerd als in gebruik; bied "vervangen door" aan |
| Thema | nee (een per canvas) | ja | ja | herstel naar standaard | S12 | n.v.t. |
| Momentopname | ja | ja | alleen naam, opmerking, archiefstatus | ja | S11 | Bevestigen; inhoud zelf is nooit te wijzigen |
| Gebruiker | ja | ja | ja | ja | S12 | Niet de eigen gebruiker en niet de laatste Beheerder |
| Logboek | automatisch | ja | nee | nee | S12 | Alleen-lezen |

Eisen aan elke CRUD-lijst (een herbruikbaar onderdeel, niet per soort opnieuw bouwen):

1. Tabel met zoeken, sorteren per kolom en filters; aantal resultaten zichtbaar.
2. Knop "Nieuw" opent een formulier in een zijpaneel; opslaan zonder het scherm te verlaten.
3. Wijzigen in hetzelfde zijpaneel; verplichte velden en foutmeldingen per veld, in gewoon Nederlands.
4. Verwijderen altijd met bevestiging die het gevolg benoemt ("verwijdert ook 3 toewijzingssets").
5. Meerdere rijen selecteren voor verwijderen of archiveren, met dezelfde regels per rij en een resultaatmelding (gelukt, overgeslagen met reden).
6. Gearchiveerde rijen zijn standaard verborgen, met een schakelaar "toon gearchiveerd" en een knop "terugzetten".
7. Na verwijderen 10 seconden een knop "ongedaan maken", behalve bij canvas, organisatie en momentopname.
8. Elke A, W en V komt in het logboek met oude en nieuwe waarde.
9. Geen enkele verwijdering mag H5 of H7 breken. De server controleert dit, niet alleen het scherm.

## 9. Datamodel

| Tabel | Kernkolommen |
|---|---|
| users | naam, e-mail, wachtwoord-hash, rol |
| organisations | naam, slug, logo |
| canvases | organisation_id, naam, status, theme_json, theme_version, miniatuur |
| areas | sleutel, schermnaam, soort, volgorde, labels, kosten_toewijsbaar (seed, alleen-lezen) |
| statements | canvas_id, soort (missie, visie, doel), titel, tekst, volgorde |
| elements | id (UUID), canvas_id, area_id, parent_id, soort (domein, cluster, activiteit), naam, omschrijving, modality_id, onderbouwing, pictogram, kolombreedte, sort_order, gearchiveerd_op |
| modalities | sleutel, naam, kleur, volgorde (seed) |
| owners | organisation_id, naam |
| applications | organisation_id, naam, leverancier |
| cost_records | id (UUID), organisation_id, code, naam, soort, leverancier, actor_element_id, owner_id, application_id, kostenplaats, omschrijving, gearchiveerd_op |
| cost_record_years | cost_record_id, jaar, budget_cents, prognose_cents, run_bp, change_bp, fte, peildatum, opmerking |
| allocations | id, canvas_id, cost_record_id, jaar, element_id, basispunten |
| fiscal_years | canvas_id, jaar, definitief_op |
| snapshots | canvas_id, naam, jaar, opmerking, payload_json, schema_version, checksum, gearchiveerd |
| audit_log | user_id, actie, onderwerp, voor, na, tijdstip |

Afdwingen in database en applicatie:

1. `elements`: controle bij opslaan op de regels uit 6.2; index op (canvas_id, parent_id, sort_order).
2. `allocations`: uniek op (canvas_id, cost_record_id, jaar, element_id); basispunten tussen 1 en 10000.
3. Som per (canvas_id, cost_record_id, jaar) is 10000: afgedwongen in een service die de hele set vervangt binnen een transactie. Er bestaat geen route om een losse regel te wijzigen.
4. Kostenposten horen bij de organisatie, toewijzingen bij een canvas. Een kostenpost kan dus per canvas anders verdeeld zijn.
5. Momentopnamen zijn onveranderlijk: geen update-route, checksum controleren bij lezen.

## 10. Techniek en opzet

1. Laravel (actuele stabiele versie) met Inertia en React in TypeScript, Tailwind voor opmaak, Vite voor de build.
2. SQLite-bestand in een Docker-volume. Geen aparte databaseserver nodig.
3. Slepen en sorteren met een lokaal gebundelde bibliotheek (bijvoorbeeld dnd-kit).
4. PNG-export met een lokaal gebundelde bibliotheek die de canvas-DOM omzet; PDF via een afdrukstijlblad (A3 liggend).
5. Alle fonts en pictogrammen lokaal in de repository (H1).
6. Opstarten:
   1. `cp .env.example .env`
   2. `docker compose up -d`
   De container voert migraties en seed uit bij de eerste start en meldt de inlog van de beheerder in de log. Applicatie op `http://localhost:8080`.
7. `make backup` kopieert het databasebestand en de uploads naar een map met datum. `make restore` zet terug. Beide in de README.
8. README in het Nederlands: opstarten, inloggen, backup, import, veelgestelde vragen. Maximaal twee pagina's.

## 11. Routes (richtlijn)

| Methode en pad | Doel |
|---|---|
| GET /overzicht | S2 |
| POST /canvassen | nieuw, kopie of sjabloon |
| GET /canvassen/{id}/bewerken, /bekijken | S4, S6 |
| POST, PATCH, DELETE /canvassen/{id}/elementen | structuur beheren |
| POST /canvassen/{id}/elementen/verplaatsen | ouder en volgorde |
| POST /canvassen/{id}/elementen/bulk | S5 |
| POST /canvassen/{id}/elementen/{el}/herverdelen | 6.4 |
| GET, POST, PATCH, DELETE /kostenposten, /kostenposten/{kp}/jaren/{jaar} | S7 |
| GET, POST, PATCH, DELETE /organisaties, /canvassen, /gebruikers, /eigenaren, /applicaties, /canvassen/{id}/koers | 8A |
| POST /{soort}/{id}/archiveren, /terugzetten, /ongedaan-maken | 8A |
| PATCH, DELETE /canvassen/{id}/momentopnamen/{m} | alleen naam, opmerking, archiefstatus |
| PUT /canvassen/{id}/jaren/{jaar}/kostenposten/{kp}/toewijzing | hele set vervangen (H5) |
| POST /canvassen/{id}/jaren/{jaar}/overnemen | 7.4 |
| POST /canvassen/{id}/jaren/{jaar}/definitief, /heropenen | 7.2 punt 7 |
| GET /canvassen/{id}/jaren/{jaar}/cijfers | uitkomst rekenmodule |
| POST /canvassen/{id}/momentopnamen | S11 |
| GET /export/..., POST /import/... | hoofdstuk 12 |

## 12. Import en export

1. **JSON volledig per canvas**: canvas, thema, koersblok, elementen, kostenposten met jaren, toewijzingen. Dient als backup, kopie en overdracht. Versienummer in het bestand.
2. **Import JSON**: eerst proefrun met rapport (structuurregels, verwijzingen, 100%-regel), daarna alles of niets in een transactie.
3. **CSV kostenposten**: kolommen `code;naam;soort;leverancier;eigenaar;kostenplaats;applicatie;jaar;budget;prognose;run_pct;change_pct;fte`. Puntkomma als scheiding, komma als decimaalteken, UTF-8. Bestaande code wordt bijgewerkt, nieuwe code toegevoegd. Proefrun met rapport per regel.
4. **CSV toewijzingen**: `kostenpost_code;jaar;doel_pad;percentage`, waarbij `doel_pad` de vorm `Area > Domein > Cluster > Activiteit` heeft. Sets die niet op 100% uitkomen worden geweigerd met melding per kostenpost.
5. **CSV structuur**: `area;domein;cluster;activiteit;omschrijving;modaliteit` voor snel vullen vanuit een spreadsheet.
6. **Beeld**: PNG en afdrukken naar PDF, met of zonder actieve overlay.

## 13. Seed

1. Acht area's en vier modaliteiten (Common groen, Adaptive blauw, Specialized oranje, Distinct paars).
2. Een beheerder met eenmalig wachtwoord.
3. Sjabloon "Leeg canvas".
4. Voorbeeldorganisatie "Voorbeeld BV" met een klein gevuld canvas en tien kostenposten, zodat alle schermen direct iets tonen. Duidelijk als voorbeeld gemarkeerd en met een knop te verwijderen.
5. Geen gegevens van de eerste klant in de repository. Die voert de product owner zelf in of importeert hij.

## 14. Werkpakketten

Modeladvies volgt de vaste routering: Fable 5 voor ontwerp, specificatie en review; Sonnet 5 voor implementatie; Haiku voor bulkwerk.

| WP | Inhoud | Omvang | Model | Effort | Hangt af van |
|---|---|---|---|---|---|
| WP0 | Specificatie aanscherpen tot fasedocumenten, datamodel en routes vastleggen, testplan | S | Fable 5 | high | geen |
| WP1 | Projectopzet: Docker, Laravel, Inertia, React, SQLite, inloggen, rollen, seed area's en modaliteiten, CI met tests | S | Sonnet 5 | medium | WP0 |
| WP2 | Herbruikbaar CRUD-onderdeel (lijst, zijpaneel, bevestigen, ongedaan maken, archiveren) en daarmee organisaties, canvassen, gebruikers, lijsten; overzicht, nieuw canvas, kopie | M | Sonnet 5 | medium | WP1 |
| WP3 | Elementenboom met alle regels uit 6.2 en 6.4 (zonder herverdelen), API en tests | M | Sonnet 5 | high | WP2 |
| WP4 | Canvasweergave met automatische opmaak, bewerken inline, slepen, rechter paneel, snel invoeren, koersblok, thema | L | Sonnet 5 | high | WP3 |
| WP5 | Kostenposten, jaarwaarden, lijsten, CSV-import met proefrun | M | Sonnet 5 | medium | WP2 |
| WP6 | Rekenmodule (7.3) als pure module met volledige tests | M | Fable 5 ontwerp en review, Sonnet 5 bouw | high | WP3, WP5 |
| WP7 | Toewijsscherm, 100%-regel op server en scherm, herverdelen, overnemen, definitief | M | Sonnet 5 | high | WP6 |
| WP8 | Overlays Modaliteit en Financieel, bekijkmodus, detailpaneel, filters | M | Sonnet 5 | medium | WP4, WP6 |
| WP9 | Export en import JSON, CSV, PNG, PDF; momentopnamen | M | Sonnet 5 | medium | WP7, WP8 |
| WP10 | Voorbeelddata, README, backup en restore, schermteksten nalopen | S | Haiku | low | WP9 |
| WP11 | Eindreview tegen dit document, H-regels en testgevallen; herstelronde | S | Fable 5 | high | alles |

Eerste bruikbare oplevering: WP0 t/m WP4 (canvas zelf aanmaken en toekennen). Tweede: WP5 t/m WP8 (financiele laag zichtbaar). Derde: WP9 t/m WP11.

## 15. Acceptatiecriteria per oplevering

**Oplevering 1**

1. Na twee commando's draait de applicatie en kan de beheerder inloggen.
2. Een nieuw canvas toont direct de acht area's in de vaste opmaak.
3. Domein toevoegen in een area, activiteiten toevoegen, cluster maken en activiteiten erin slepen lukt zonder de pagina te verlaten.
4. Een activiteit kan niet buiten een domein bestaan; de API weigert dit met een leesbare melding.
5. Via "snel invoeren" staan 3 domeinen met 15 activiteiten binnen een minuut op het canvas.
6. Area's zijn nergens te wijzigen of te verwijderen.

6a. Elke gegevenssoort uit 8A die in deze oplevering zit, is via het scherm aan te maken, te wijzigen en te verwijderen, met bevestiging en logboekregel.
6b. De laatste Beheerder en een organisatie met canvassen zijn niet te verwijderen; de melding legt uit waarom.

**Oplevering 2**

6c. Een kostenpost verwijderen haalt zijn toewijzingssets mee weg; de totalen sluiten daarna nog steeds (H7). In een definitief jaar wordt het geweigerd.
6d. Een eigenaar of applicatie die in gebruik is, is pas te verwijderen na "vervangen door".

7. Een kostenpost met budget 230,00 verdeeld over drie doelen op 33,33 / 33,33 / 33,34 geeft bedragen die samen exact 230,00 zijn.
8. Een set van 99,99% of 100,01% wordt door de server geweigerd, ook bij een rechtstreekse API-aanroep.
9. De overlay toont per domein het totaal inclusief clusters en activiteiten, en het canvastotaal is gelijk aan de som van alle kostenposten.
10. Niet toegewezen kostenposten staan als aparte post in het zijpaneel en tellen mee in het totaal.
11. Een jaar met een niet toegewezen kostenpost kan niet definitief worden.
12. Een activiteit met toewijzingen verwijderen opent "Herverdelen"; daarna zijn alle betrokken kostenposten nog steeds 0% of 100%.

**Oplevering 3**

13. Export naar JSON, alles wissen en weer importeren geeft identieke cijfers.
14. Een momentopname toont na latere wijzigingen nog de oude structuur en bedragen.
15. PNG en PDF bevatten het hele canvas, leesbaar op A3.

## 16. Tests

1. Eenheidstests rekenmodule: grootste-rest-afronding; oprollen met gemengde niveaus (domein en activiteit in hetzelfde domein); niet toegewezen; run en change; modaliteit zonder dubbeltelling; lege invoer; een kostenpost met 100% op een doel.
2. Eigenschapstest: voor willekeurige budgetten en geldige sets geldt altijd H7.
3. API-tests op elke weigering: activiteit zonder domein, genest cluster, cluster in lijst-area, doel in niet-toewijsbare area, som ongelijk aan 100%, dubbel doel, gearchiveerd doel, wijzigen in definitief jaar, Lezer die schrijft.
3a. CRUD-tests per gegevenssoort uit 8A: aanmaken, wijzigen, verwijderen, elke blokkade, ongedaan maken, archiveren en terugzetten, en dat Lezer op elke schrijfroute wordt geweigerd.
4. Schermtest van begin tot eind (Playwright): canvas maken, structuur vullen, kostenpost invoeren, toewijzen, overlay controleren, exporteren.
5. Importtests met foute bestanden: rapport klopt en er is niets weggeschreven.
6. Controle op H1: test die faalt als de gebouwde assets een externe URL bevatten.
7. Controle op H8: test die de repository doorzoekt op een lijst verboden namen. De product owner levert die lijst als lokaal bestand buiten versiebeheer aan.

## 17. Definition of done

1. Alle acceptatiecriteria van de oplevering aantoonbaar gehaald, met schermafbeelding of testuitvoer.
2. Alle tests groen in CI.
3. Geen openstaande fout van het type "cijfers kloppen niet" of "regel te omzeilen".
4. README bijgewerkt.
5. Eindreview door Fable 5 tegen dit document, met lijst afwijkingen en besluit per afwijking.
6. Niet opleveren wat niet af en niet getoetst is.

## 18. Overige eisen

1. Beveiliging: wachtwoorden gehasht, sessies met veilige cookies, CSRF aan, alle schrijfacties achter rol Beheerder, uploads beperkt tot afbeeldingen en CSV of JSON.
2. Privacy: de applicatie bevat organisatie- en kostengegevens, geen gegevens van kinderen of medewerkers. Leverancier en eigenaar zijn organisatienamen, geen personen. Toon bij het veld eigenaar de hint "afdeling of rol, geen persoonsnaam".
3. Prestaties: tot 500 elementen en 1000 kostenposten per canvas reageert elk scherm binnen een seconde op een gewone laptop.
4. Toegankelijkheid: alles met toetsenbord te bedienen; kleur nooit de enige drager van betekenis (modaliteit ook als letter C, A, S, D).
5. Taal: schermteksten Nederlands, bedragen als `€ 1.234,56`, percentages met komma.
6. Logboek: elke wijziging aan structuur, kostenposten en toewijzingen in `audit_log`.

## 19. Aannames met standaardkeuze

Niet blokkerend. Bouw volgens de standaard; de product owner past later aan.

| Nr | Aanname | Standaard |
|---|---|---|
| A1 | Waar draait het | Op de laptop of ontwikkelserver van de product owner, via Docker. Niet op internet bereikbaar |
| A2 | Wie gebruikt het | Alleen de product owner en enkele collega's; Lezer-accounts voor meekijkers |
| A3 | Valuta | Alleen euro |
| A4 | Boekjaar | Kalenderjaar |
| A5 | Schermnamen van de area's | Zoals in 6.1; de namen staan in de seed en zijn bij een latere release aan te passen |
| A6 | FTE in de overlay | Alleen tonen als som per domein, zonder aparte rekenregels |

## 20. Werkwijze voor de orchestrator

1. Volg de vaste fasering met fasepoorten. Geen implementatie voor WP0 is goedgekeurd door de reviewer.
2. Per werkpakket: uitvoeren, zelf testen, fouten herstellen, herhalen tot het klopt, daarna pas terugmelden.
3. Terugmelding per werkpakket in maximaal tien regels: wat is gebouwd, welke criteria zijn gehaald, welke keuzes zijn gemaakt.
4. Los onduidelijkheden zelf op binnen de H-regels en K-keuzes en meld de keuze in een regel. Alleen stoppen en vragen bij een conflict met een H-regel.
5. Vrije tekeneditor, tussenlagen en koppelingen zijn uitdrukkelijk buiten scope. Niet alvast voorbereiden met extra abstracties.
6. Leg elke correctie van de product owner direct vast in dit document of in de fasedocumenten.
