# Acceptatie oplevering 1

## 1. Kort

Dit document hoort bij oplevering 1 van OMC Studio: Invoeren en Bekijken van een canvas, de beheerschermen (gebruikers, organisaties, logboek), sjablonen, de Koers van een canvas (missie, visie, strategische doelen) en de modaliteit per activiteit. Tekenen, de financiele laag en export zitten er niet in.

Je leest het per acceptatiecriterium uit `specs/kickoff.md`, sectie 15. Bij elk criterium staan drie delen:

- **Doe dit**: de stappen in het scherm, met de Nederlandse knoppen en velden tussen aanhalingstekens.
- **Je hoort te zien**: wat er na die stappen moet gebeuren.
- **Bewijs**: het testbestand en de testnaam die dit automatisch controleren, soms met een schermafbeelding.

Waar het gebouwde anders is dan het criterium letterlijk zegt, staat dat er in gewone woorden bij, met de beslissing in de specificatie.

Alle runs in dit document zijn gedaan op 2026-11-20 in de ontwikkelcontainer (PHP-server op loopback, SQLite, Chromium). Dat is niet de uitgeleverde Docker-opzet op jouw laptop; zie hoofdstuk 6.

**De acceptatie zelf is niet afgevinkt.** Jij beoordeelt dit document en de applicatie op de laptop (K-6). Taak T172 in `tasks.md` betekent alleen dat dit bewijs is opgeschreven.

## 2. Per acceptatiecriterium

### Criterium 1: "Na twee commando's draait de applicatie en kan de beheerder inloggen."

**Doe dit**

1. Op een machine met alleen Docker: `cp env.example .env`
2. Daarna: `docker compose up -d`
3. Open `http://localhost:8080`. Je ziet "Inloggen".
4. Lees het e-mailadres en het eenmalige wachtwoord van de beheerder met `docker compose logs`.
5. Log in ("E-mailadres", "Wachtwoord", "Inloggen"), kies een nieuw wachtwoord, log uit en weer in.

**Je hoort te zien**

Binnen 3 minuten na het tweede commando de pagina "Inloggen" (nadat het image eenmalig is gebouwd, wat internet nodig heeft). Het eenmalige wachtwoord staat precies een keer in de log. Na een herstart komt er geen nieuw wachtwoord.

**Bewijs**

- `tests/e2e/auth/first-start.spec.ts`, "clean start: sign in with the logged password, choose a new one, sign out, sign in again": groen op 2026-11-20 (Chromium).
- `tests/Feature/Setup/CleanStartEvidenceTest.php` en `tests/Feature/Setup/DeliveredStackFilesTest.php`: de bestanden en het bewijsscript van de uitgeleverde opzet; onderdeel van `composer test` (739 groen op 2026-11-20).
- `specs/001-omc-studio-v1/wp1-evidence.md`, "Clean start proof (2026-11-19)": eerste antwoord van `/` na 2 seconden, bouwtijd 7 seconden met een warme cache.

**Niet geverifieerd in deze run**: de twee commando's zelf (`cp env.example .env` en `docker compose up -d`) zijn op 2026-11-20 niet uitgevoerd, want in de ontwikkelcontainer is geen Docker beschikbaar. De 7 seconden zijn niet de bouwtijd van een schone machine. De limiet van 3 minuten op jouw laptop moet jij nog meten.

### Criterium 2: "Een nieuw canvas toont direct de acht area's in de vaste opmaak."

**Doe dit**

1. In "Overzicht": "Nieuw canvas". Vul "Naam", kies de "Organisatie", laat "Start vanuit" op "Sjabloon: Leeg canvas" staan en kies "Aanmaken".
2. Je komt in Invoeren. Kies "Bekijken".

**Je hoort te zien**

De acht area's: "Koers", "Richten", "Beheersen", "Actoren", "Kanalen", "Waardeproposities", "Primaire activiteiten" en "Ondersteunende activiteiten", op het A3-vlak, met Koers als band bovenaan en "Onbekende allocatie" in "Ondersteunende activiteiten".

**Bewijs**

- `tests/e2e/d1/full-flow.spec.ts`, "an Administrator creates an Organisation and a Canvas, fills it through Invoeren and Snel invoeren, writes the Koers and views the result in Bekijken": controleert in Bekijken alle acht area-namen. Groen op 2026-11-20.
- `tests/Feature/Structure/AreasImmutableTest.php`, `test_the_eight_areas_are_the_reference_set`.
- `tests/Feature/Canvas/CreateFromTemplateTest.php`, `test_the_canvas_holds_the_unknown_allocation_domain_once`.
- `tests/e2e/view/draw.spec.ts`, "Bekijken draws the Areas and Elements on the Artboard with the Direction band on top".
- Beeld: [bekijken-after.png](../design/compare/bekijken-after.png).

### Criterium 3: "Domein toevoegen in een area, activiteiten toevoegen, cluster maken en activiteiten erin slepen lukt zonder de pagina te verlaten."

**Doe dit**

1. Open het canvas in Invoeren en kies de area "Primaire activiteiten".
2. Kies "Domein", typ een naam in "+ Domein" en druk op Enter.
3. Typ onder het domein in de regel "Activiteit of cluster toevoegen aan ..." een naam en druk op Enter. Voeg zo meerdere activiteiten toe.
4. Kies in de regel de keuze "Cluster" (naast "Activiteit"), typ een naam en druk op Enter.
5. Kies een activiteit, open het veld "Hangt onder" in het paneel rechts, kies het cluster (bijvoorbeeld "Primaire activiteiten > Onderwijs > Lessen") en kies "Opslaan".
6. Wijzig de volgorde met de pijlknoppen bij elke rij.

**Je hoort te zien**

Elke actie verschijnt direct in de lijst zonder dat de pagina opnieuw laadt. Na stap 5 staat boven in het paneel "Activiteit · Primaire activiteiten › Onderwijs › Lessen".

**Afwijking van de tekst van het criterium**: er wordt niets gesleept. Het criterium zegt "erin slepen"; gebouwd is de keuze "Hangt onder" (en daarna "Opslaan"), die met muis en toetsenbord werkt. Het slepen van een activiteit in een cluster is er in oplevering 1 niet. De spec noemt dit expliciet: SC-003 en gebruikersverhaal US11 spreken van "Hangt onder", en slepen hoort bij Tekenen (ADR 0036, Shift-verplaatsen). Dat is een bewuste keuze, geen gat; jij beslist of dat acceptabel is.

**Bewijs**

- `tests/e2e/d1/full-flow.spec.ts` (zie boven): domein, activiteit, cluster en "Hangt onder" in een doorlopende run.
- `tests/e2e/entry/add-domain.spec.ts`, "Enter in "+ Domein" adds a Domain row at the end and moves focus to its add line".
- `tests/e2e/entry/add-line.spec.ts`, "Enter adds an Activity at the end, empties the line and keeps focus" en "with the choice "Cluster" the line adds a Cluster".
- `tests/e2e/entry/parent-select.spec.ts`, ""Hangt onder" moves an Activity to the end of a Cluster and never offers the Unknown allocation Domain".
- `tests/e2e/entry/reorder.spec.ts`, "the arrows swap neighbours of the same kind and keep focus sensible".
- `tests/e2e/entry/keyboard-only.spec.ts`, "the independent test of Invoeren runs with the keyboard only".
- Beeld: [invoeren-after.png](../design/compare/invoeren-after.png).

**Let op: bekende fout `[GAP referrer back()]`.** Zie hoofdstuk 6. Op een net aangemaakt canvas springt het scherm na het eerste "Domein" terug naar "Overzicht" tot je de pagina herlaadt.

### Criterium 4: "Een activiteit kan niet buiten een domein bestaan; de API weigert dit met een leesbare melding."

**Doe dit**

Op het scherm kan dit niet: de add-regel van een activiteit staat alleen onder een domein of cluster. Een ontwikkelaar kan het verzoek wel rechtstreeks naar de server sturen; dat doen de tests hieronder.

**Je hoort te zien**

De melding "Een activiteit hoort onder een domein of een cluster" en er wordt niets opgeslagen.

**Afwijking van de tekst van het criterium**: er is geen aparte publieke API. De applicatie heeft alleen haar eigen schermroutes (ADR 0030, FR-005, ASVS V4); "de API weigert" betekent hier: de server weigert het rechtstreekse verzoek, ook zonder de schermen.

**Bewijs**

- `tests/Feature/Structure/CreateElementTest.php`, `test_an_activity_without_a_parent_is_refused`, `test_an_activity_under_a_missing_parent_is_refused`, `test_an_activity_under_another_activity_is_refused`.
- `tests/Feature/Structure/RefusalMatrixTest.php`, `test_the_server_refuses_with_the_message_and_changes_nothing`.
- `specs/001-omc-studio-v1/d1-refusals.md`: alle weigeringen van oplevering 1 met hun test. `tests/Feature/Security/D1RefusalCoverageTest.php` faalt als een weigeringsmelding geen test heeft. Op 2026-11-20 is dat bewezen: een verwijzing naar `rule.undo_parent_gone` tijdelijk weggehaald, de test werd rood met precies die sleutel, daarna hersteld en groen.

### Criterium 5: "Via "snel invoeren" staan 3 domeinen met 15 activiteiten binnen een minuut op het canvas."

**Doe dit**

1. In Invoeren, area "Primaire activiteiten": kies "Snel invoeren".
2. Typ in "Regels" per regel een element; twee spaties inspringen is een niveau lager. Drie domeinen met elk vijf activiteiten eronder.
3. Controleer onder "Voorbeeld" dat elke regel "nieuw" of "bestaat al" toont.
4. Kies "Opslaan".

**Je hoort te zien**

Alle 18 regels staan in de lijst, de area-kaart toont "3 domeinen · 15 activiteiten" en onderaan staat "Opgeslagen: 18 nieuwe elementen". Een regelfout, bijvoorbeeld "Regel 3: inspringing past niet bij de regel erboven", blokkeert "Opslaan" tot hij hersteld is.

**Bewijs**

- `tests/e2e/entry/quick-entry-timing.spec.ts`, "3 Domains with 5 Activities each are on the Canvas within one minute": groen op 2026-11-20 (de hele test duurde 2,7 seconden). Dit meet een geautomatiseerde invoer; een geoefend mens op de laptop is niet gemeten.
- `tests/e2e/entry/quick-entry.spec.ts` en `tests/Feature/Structure/QuickEntryTest.php`.
- Schermafbeeldingen van deze run: [voorbeeld voor het opslaan](d1-evidence/snel-invoeren-voorbeeld.png) en [resultaat na het opslaan](d1-evidence/snel-invoeren-resultaat.png).

### Criterium 6: "Area's zijn nergens te wijzigen of te verwijderen."

**Doe dit**

Zoek in Invoeren en Bekijken naar een manier om een area toe te voegen, te hernoemen, te verplaatsen of te verwijderen.

**Je hoort te zien**

Die is er niet. Aan de area-kaarten zit alleen selecteren; de tekst bij de lijst zegt: "De acht area's liggen vast." Er bestaat ook geen route die het kan.

**Bewijs**

- `tests/Feature/Structure/AreasImmutableTest.php`, `test_no_route_creates_renames_reorders_or_deletes_an_area`.
- `tests/Feature/Security/RouteEnumerationTest.php`: elke schrijfroute is bekend en vereist de rol Beheerder.

### Criterium 6a: "Elke gegevenssoort uit 8A die in deze oplevering zit, is via het scherm aan te maken, te wijzigen en te verwijderen, met bevestiging en logboekregel."

In oplevering 1: gebruikers, organisaties (met logo), canvassen, sjablonen, elementen (domein, cluster, activiteit en de items van de lijstarea's) en de Koers (missie, visie, strategische doelen).

**Doe dit, per soort**

- Gebruikers: "Beheer", "Gebruikers", "Nieuw"; wijzig; "Wachtwoord resetten"; "Verwijderen".
- Organisaties: "Beheer", "Organisaties", "Nieuw"; wijzig naam en logo; "Archiveren"; "Terugzetten"; "Verwijderen" (typ de naam ter bevestiging).
- Canvassen: in "Overzicht" de knop "Acties voor <naam>": "Naam wijzigen", "Kopiëren", "Archiveren", "Terugzetten", "Verwijderen" (typ de naam).
- Sjablonen: in Invoeren "Opslaan als sjabloon"; in "Overzicht" staat het onder "Sjablonen"; hernoemen en verwijderen via het menu van de kaart.
- Elementen: in Invoeren toevoegen, wijzigen in het paneel, "Archiveren", "Terugzetten", "Verwijderen".
- Koers: area "Koers": "Missie" en "Visie" opslaan; "+ strategisch doel" toevoegen, met de pijlen herordenen, verwijderen.

**Je hoort te zien**

Elke verwijdering vraagt om bevestiging en noemt wat er mee verdwijnt. Na een verwijdering in een lijst of in Invoeren is "Ongedaan maken" kort beschikbaar. Onder "Beheer", "Logboek" staat elke actie met oude en nieuwe waarde.

**Bewijs**

- `tests/e2e/admin/users.spec.ts`, `organisations.spec.ts`; `tests/e2e/overview/lifecycle.spec.ts`, `save-template.spec.ts`, `templates.spec.ts`; `tests/e2e/entry/delete-archive.spec.ts`, `direction.spec.ts`, `modality.spec.ts`: groen op 2026-11-20.
- `tests/e2e/admin/audit-log.spec.ts`, "a renamed Element shows in Logboek with old and new name and filters by Canvas and user".
- `tests/Feature/Audit/AuditedTest.php`: een aanmaak, wijziging en verwijdering schrijft elk een logboekregel; `tests/Feature/Audit/AuditLogPageTest.php`.

### Criterium 6b: "De laatste Beheerder en een organisatie met canvassen zijn niet te verwijderen; de melding legt uit waarom."

**Doe dit**

1. Probeer als enige beheerder jezelf te verwijderen of je rol te wijzigen.
2. Probeer in "Beheer", "Organisaties" een organisatie met een canvas te verwijderen: "Verwijderen", typ de naam, "Verwijderen".

**Je hoort te zien**

"Er moet minstens één beheerder blijven" en "Deze organisatie heeft nog canvassen of kostenposten. Archiveer haar, of verwijder die eerst".

**Bewijs**

- `tests/Feature/Concurrency/LastAdministratorTest.php`: `test_two_administrators_demoting_each_other_end_with_one_success_and_one_refusal` (twee processen tegelijk), `test_deleting_the_last_administrator_is_refused`, `test_demoting_the_last_administrator_is_refused`.
- `tests/Feature/Organisations/OrganisationDeletionTest.php`, `test_deletion_is_refused_while_the_organisation_still_holds_a_canvas`.
- `tests/e2e/admin/organisations.spec.ts`, "deleting is refused while a Canvas exists, then archive, restore and a typed-name deletion work".
- Beeld: [organisatie-verwijderen-geweigerd.png](d1-evidence/organisatie-verwijderen-geweigerd.png).

## 3. Succescriteria

Alleen wat op 2026-11-20 is gedraaid staat als resultaat; de rest staat als "niet gedraaid".

| Criterium | Resultaat | Bewijs |
|---|---|---|
| SC-001 schone start binnen 3 minuten | niet gedraaid: Docker ontbreekt in de ontwikkelcontainer; alleen de start-e2e is gedraaid (groen) en het eerdere hostbewijs van 2026-11-19 (2 s tot eerste antwoord, warme cache) | `auth/first-start.spec.ts`, `wp1-evidence.md` |
| SC-002 acht area's direct | geslaagd | `npx playwright test tests/e2e/d1 --project=chromium`: 1 groen; `AreasImmutableTest` in `composer test` |
| SC-003 domein, activiteiten, cluster, "Hangt onder", muis en toetsenbord, zonder de pagina te verlaten | geslaagd, met de afwijking van criterium 3 (geen slepen) en de fout `[GAP referrer back()]` op het eerste domein van een nieuw canvas | `d1/full-flow.spec.ts`, `entry/parent-select.spec.ts`, `entry/keyboard-only.spec.ts` (Chromium, 99 specs in een run, 98 groen en 1 groen bij herhaling, zie hoofdstuk 6) |
| SC-004 activiteit buiten een domein altijd geweigerd, via de schermen en rechtstreeks | geslaagd | `CreateElementTest`, `RefusalMatrixTest` in `composer test` |
| SC-005 3 domeinen met 15 activiteiten binnen een minuut | geslaagd in de geautomatiseerde run; een geoefend mens niet gemeten | `entry/quick-entry-timing.spec.ts` |
| SC-006 geen area te wijzigen | geslaagd | `AreasImmutableTest` in `composer test` |
| SC-007 elke soort aan te maken, te wijzigen, te verwijderen met bevestiging en logboekregel | geslaagd voor de soorten van oplevering 1 | criterium 6a |
| SC-008 laatste beheerder en organisatie met canvassen beschermd, ook bij gelijktijdig handelen | geslaagd | `LastAdministratorTest`, `OrganisationDeletionTest` in `composer test` |
| SC-020 500 elementen, elk scherm binnen 1 seconde (p95) | geslaagd op Chromium in deze run (Invoeren openen, een element toevoegen en Bekijken met 500 elementen, 3 specs); de kostenposten-helft hoort bij oplevering 2; zie hoofdstuk 6 voor de omstandigheden | `entry/performance.spec.ts`, `view/performance.spec.ts` |
| SC-021 WCAG 2.2 AA-scan en toetsenbord | geslaagd op Chromium: de axe-scan staat in elke stap van de doorlopende test en in de specs van Invoeren, Bekijken en beheer; toetsenbord in `entry/keyboard-only.spec.ts` en `view/keyboard.spec.ts` | `support/guards.ts`, `d1/full-flow.spec.ts` |
| SC-022 geen verzoek naar buiten tijdens een volledige run | geslaagd op Chromium: de bewaker voor verzoeken buiten de eigen oorsprong en voor CSP-overtredingen staat in elke spec, ook in de doorlopende test | `support/guards.ts`, `d1/full-flow.spec.ts` |

Runs van 2026-11-20 op deze tak:

| Commando | Uitkomst |
|---|---|
| `composer verify` | Pint geslaagd, PHPStan 0 fouten |
| `composer test` | 739 van 739 geslaagd (3969 asserties) |
| `npm run verify` | 55 bestanden en 380 tests geslaagd (ESLint en typecontrole groen) |
| `npm run check` | assets en imports geslaagd; de lijst met verboden namen ontbreekt en wordt zichtbaar overgeslagen |
| `npx playwright test tests/e2e/d1 tests/e2e/entry tests/e2e/view tests/e2e/admin tests/e2e/overview tests/e2e/auth --project=chromium --workers=1` | 98 van 99 geslaagd; de ene mislukking was het zaaien van testdata via `php artisan tinker` in `entry/reorder.spec.ts` en was bij herhaling alleen groen |

## 4. ASVS 5.0 niveau 2, voor zover oplevering 1 het raakt

| Hoofdstuk | Test |
|---|---|
| V2 validatie en bedrijfslogica (regels op de server, laatste beheerder) | `D1RefusalCoverageTest`, `D1RefusalMessagesTest`, `RefusalMatrixTest`, `LastAdministratorTest` |
| V3 frontend (CSP, nosniff, no-referrer, framing) | `tests/Feature/Security/HeadersTest.php`; de CSP-bewaker in elke e2e-spec |
| V4 alleen POST, PUT, PATCH en DELETE met token voor wijzigingen | `tests/Feature/Security/RouteEnumerationTest.php` |
| V5 bestanden (logo: type, grootte, hercoderen) | `tests/Feature/Organisations/LogoUploadTest.php` |
| V6 authenticatie (wachtwoordregels, blokkade, tweede factor) | `tests/Feature/Accounts/SignInServiceTest.php`, `PasswordChangeTest.php`, `SecondFactorEnableTest.php`, `TotpReplayTest.php` |
| V7 sessies (nieuw id, 120 minuten inactief, 12 uur absoluut, beeindiging) | `SessionLifetimeTest.php`, `SessionTerminatorTest.php`, `tests/Feature/Security/SessionHandlerTest.php`, `tests/e2e/auth/session-timer.spec.ts` |
| V8 autorisatie (Lezer geweigerd op elke schrijfroute) | `RouteEnumerationTest.php`, `tests/Feature/Security/WritableGuardTest.php` |
| V11 cryptografie (TOTP-geheim versleuteld, willekeurige eenmalige wachtwoorden) | `OneTimePasswordTest.php`, `SecondFactorAccountTest.php` |
| V13 configuratie (debug uit, geen standaardwachtwoord, `.env` buiten git) | `tests/Feature/Setup/DeliveredStackFilesTest.php` |
| V14 gegevensbescherming (geen cache van ingelogde antwoorden, geen geheimen in het logboek) | `HeadersTest.php`, `tests/Feature/Audit/AuditSecretsTest.php`, `tests/Feature/Security/HistoryEncryptionTest.php` |
| V16 logging (logboek, weigeringenlog) | `tests/Feature/Audit/`, `tests/Feature/Security/ForbiddenLogTest.php` |
| V12 beveiligde verbinding | vrijgesteld door ADR 0024 (loopback en SSH-tunnel) |

Alle genoemde PHP-tests zijn onderdeel van de run `composer test` (739 geslaagd). De uitzondering is `session-timer.spec.ts`: die draait alleen in de e2e-run en is op 2026-11-20 meegelopen in de auth-map (geslaagd).

## 5. Ontwerp

Het ontwerp van de producteigenaar staat als borden in `specs/design/`. Na taak D1.20 zijn de gebouwde schermen ertegen vergeleken; de vergelijking met alle 47 bevindingen staat in [specs/design/compare/README.md](../design/compare/README.md): 30 opgelost, 15 bewust afwijkend, 2 niet van toepassing, 0 open.

| Scherm | Gebouwd | Bord |
|---|---|---|
| Inloggen | [sign-in-after.png](../design/compare/sign-in-after.png) | geen |
| Overzicht | [overview-after.png](../design/compare/overview-after.png) | geen |
| Invoeren | [invoeren-after.png](../design/compare/invoeren-after.png) | [bord 1](../design/board-1-invoeren.png) |
| Bekijken | [bekijken-after.png](../design/compare/bekijken-after.png), [passend maken](../design/compare/bekijken-passend-after.png) | [bord 2](../design/board-2-bekijken-met-financiele-overlay.png) |
| Bekijken, detailpaneel | [bekijken-detail-after.png](../design/compare/bekijken-detail-after.png) | geen |
| Gebruikerslijst met paneel | [gebruikers-after.png](../design/compare/gebruikers-after.png) | [bord 4](../design/board-4-kostenposten.png) (dichtstbijzijnde) |

Bord 2 laat de financiele overlay zien; voor oplevering 1 telt alleen de indeling van het canvas.

**Bewust afwijkend** (met reden):

1. Bovenbalk: de tabs Kostenposten, Toewijzen en Versies en de keuze voor het boekjaar horen bij oplevering 2; het label "Voorbeelddata" komt pas in oplevering 3 omdat er nog geen voorbeeldcanvas is.
2. Keuzeschakelaar Invoeren/Bekijken: "Tekenen" ontbreekt, want die modus wordt na oplevering 1 gebouwd.
3. "Alles opgeslagen" met vinkje verschijnt alleen na een opslag in deze sessie, niet permanent, zodat het nooit iets claimt dat niet gebeurd is. "Importeren uit CSV" is uitgeschakeld tot oplevering 3.
4. Areakaarten in Invoeren: witte kaarten op de grijze pagina zonder omringend wit paneel, zoals het bord zelf toont (de taaktekst sprak van "wit paneel").
5. "+ Domein": de knop "Domein" opent een tekstveld (Enter voegt toe, Escape sluit) omdat het bord geen veld toont; "Toon gearchiveerd" staat onder de kop; het zoekveld heeft een label voor schermlezers en een voorbeeldtekst, geen zichtbaar label.
6. Waarschuwingen: onderaan het detailpaneel zoals op het bord; zolang niets geselecteerd is staat er geen paneel, dus staat de lijst dan in de hoofdkolom.
7. Voeg-regel: gestippeld vak met de keuze "Activiteit | Cluster", maar de zin "Activiteit of cluster toevoegen aan ..." is een blijvend zichtbaar label boven het veld in plaats van voorbeeldtekst, omdat de spec een zichtbaar label eist (US11, WB5, 3.3.2); het vak is daardoor hoger dan op het bord.
8. Detailpaneel: de knop "Sluiten" blijft, omdat het teruggeven van de focus en de tests daarop een expliciete sluitknop nodig hebben.
9. Knoppen in het detailpaneel: de gevolgtekst ("Geen kosten toegewezen in 2026. Verwijderen kan direct.") wacht op de kosten van oplevering 2; de zin over Tekenen is gewone tekst, geen link, omdat Tekenen er nog niet is (en dat botst met US11 scenario 7, "from DT").
10. Overzicht, canvaskaart: geen statusregel, want oplevering 1 heeft geen statusgegevens.
11. Bekijken: de zoomknoppen ("Uitzoomen", "Inzoomen", "Passend maken") zijn een toevoeging die het bord niet toont; het bord toont daar de overlaybediening van latere opleveringen.
12. Gebruikerslijst: zoeken met pictogram, aantal resultaten en "Nieuw"; geen filterchips en geen rijselectie (de spec kent geen bulkacties); de kolom E-mailadres is er weer, met sorteren (besluit van 2026-11-20: een bestaande functie verdwijnt niet door een ontwerpronde); het zoekveld heeft geen zichtbaar label.
13. Zijpaneel van de beheerlijsten: gedokt van 360 pixels, zonder donkere sluier, maar het paneel vangt nog steeds de focus en blokkeert de muis erachter (WB8).
14. Paneelinhoud: de eerste veldwaarde is bij openen geselecteerd; de gevolgtekst staat niet bij je eigen rij, waar "Verwijderen" uit staat; het organisatiepaneel heeft geen gevolgtekst en geen "Verwijderen" (dat gaat via de rij-actie en de dialoog met de getypte naam).
15. Bekijken: "STRATEGISCHE DOELEN" als label op elke plek (band, hovertekst, detailpaneel) en de doelen als genummerde lijst met vette titel.

**Vragen over het ontwerp en hun besluiten** (uit de vergelijking, besluiten van 2026-11-20):

| Nr | Vraag | Besluit |
|---|---|---|
| 1 | Voeg-regel: de zin als voorbeeldtekst in de gestippelde regel (bord) of een zichtbaar label (spec)? | Bevestigd voor nu door de producteigenaar: zoals gebouwd, een zichtbaar label in het gestippelde kader. |
| 2 | Zoekveld: is een label voor schermlezers met voorbeeldtekst genoeg (WB5)? | Akkoord (technisch besluit van de orchestrator): zoekveld met pictogram, voorbeeldtekst en een toegankelijke naam. |
| 3 | "+ Domein": een knop die een veld toont, een dialoog of een lege rij? | Bevestigd voor nu door de producteigenaar: zoals gebouwd, de knop toont het veld. |
| 4 | "Alles opgeslagen": altijd tonen of alleen na een opslag? | Bevestigd voor nu door de producteigenaar: zoals gebouwd, alleen na een opslag in deze sessie. |
| 5 | De zin over Tekenen in het detailpaneel? | Technisch besluit: gewone tekst tot Tekenen bestaat, daarna een link. |
| 6 | De gevolgtekst over kosten komt in oplevering 2: is de afwezigheid nu akkoord? | Akkoord (technisch besluit): de tekst komt met oplevering 2. |
| 7 | Zijpaneel van de beheerlijsten: echt niet-modaal maken? | Bevestigd voor nu door de producteigenaar: zoals gebouwd, het paneel houdt de focusval (WB8). |
| 8 | Gebruikerslijst: verlies van sorteren op e-mailadres? | Opgelost (technisch besluit): de kolom E-mailadres is terug en sorteerbaar; bewijs: de test "the user list sorts on the E-mailadres column". |
| 9 | Zoomknoppen in Bekijken: laten, verplaatsen of weghalen? | Bevestigd voor nu door de producteigenaar: zoals gebouwd, de zoomknoppen blijven. |
| 10 | "STRATEGISCHE DOELEN" als label op elke plek. | Aanname, blijft open voor de producteigenaar: het label blijft staan tot hij anders zegt (tasks.md, [ASSUMPTION D1.20-2]). |
| 11 | "Toon gearchiveerd" en het selectievakje in de toevoegregel op het gedeelde 24-pixeldoel? | Opgelost (technisch besluit): "Toon gearchiveerd" heeft het gedeelde doel (test: "Toon gearchiveerd" is a 16 pixel box inside a 24 pixel target); de toevoegregel heeft geen selectievakje meer maar een keuzegroep "Activiteit | Cluster" met een groot doel. |
| 12 | Areakaarten: witte kaarten zonder omringend paneel, zoals het bord? | Technisch besluit: zo laten. |

## 6. Wat niet is geverifieerd

- **De twee commando's op een schone machine.** Docker ontbreekt in de ontwikkelcontainer. De start-e2e en de bestandstests lopen wel, maar `docker compose up -d` is op 2026-11-20 niet uitgevoerd. De drie minuten op jouw laptop zijn niet gemeten.
- **GitHub Actions was geblokkeerd** vanaf 2026-11-19 (zie `docs/ci-blocked-merges.md`). Alle pull requests sindsdien zijn op lokaal bewijs gemerged. Er is dus geen CI-run op de punt van `release`; die moet eerst draaien zodra Actions weer werkt.
- **PostgreSQL.** Alle runs in dit document zijn op SQLite. De PostgreSQL-helft van de PHP-suite is eerder alleen lokaal in een tijdelijk schema gedraaid (beschreven in `docs/ci-blocked-merges.md`), niet op de CI-job `tests-postgresql`, en niet in deze run.
- **Firefox en WebKit.** In deze run alleen Chromium met een werker. De drie engines draaien bij het samenvoegen door de integrator. De WebKit-run gebruikt `SESSION_SECURE_COOKIE=false` (ADR 0024, `evidence-d1-3.md`).
- **Prestaties.** SC-020 is gemeten op een machine met 11 kernen en SQLite, terwijl de CI-runner 2 vCPU heeft. De prestatiespecs zijn in deze run niet alleen gedraaid maar tussen andere zware runs van de container, terwijl de spec ze apart wil (ADR 0044). De krapste waarde van eerdere runs was Invoeren openen op Firefox en WebKit (p95 996 ms, 1063 ms onder belasting; `docs/ci-blocked-merges.md`). Dit is niet opnieuw gemeten.
- **Geoefend mens binnen een minuut** (SC-005): gemeten met een geautomatiseerde invoer, niet door een persoon.
- **Eenmalige mislukking.** In de brede Chromium-run mislukte `tests/e2e/entry/reorder.spec.ts` bij het zaaien met `php artisan tinker` (het commando stopte met een foutcode); bij herhaling groen. De oorzaak is niet onderzocht.
- **Bekende fout `[GAP referrer back()]`** (zie `tasks.md`, "Assumptions awaiting the product owner"). Dit is op de vrijgegeven code opnieuw vastgesteld, met precies deze stappen:
  1. Log in als beheerder en laad "Overzicht" volledig (adresbalk, Enter).
  2. Kies "Nieuw canvas", vul een naam, kies een organisatie en kies "Aanmaken". Je komt op de pagina Invoeren van het nieuwe canvas.
  3. Kies de area "Primaire activiteiten", kies "Domein", typ een naam en druk op Enter.
  4. Verwacht: het domein staat als rij in de lijst en je blijft op Invoeren.
  5. Gekregen: de pagina staat op "Overzicht". Herlaad je Invoeren (opnieuw openen van het canvas) dan blijft het werken. De oorzaak is `Referrer-Policy: no-referrer` (FR-090): de server stuurt na het opslaan terug naar de vorige pagina, en dat is de laatste volledig geladen pagina, hier "Overzicht".
  De doorlopende test herlaadt daarom de pagina na het aanmaken van het canvas, in een stap met de naam `workaroundGapReferrerBack`. Jij beslist: `Referrer-Policy: same-origin` (geen lek naar buiten, nog steeds geen uitgaand verzoek) of een expliciet terugkeerpad per formulier.
- **Controle door een tweede partij.** De taken T170, T171 en T172 zijn door een model gebouwd en moeten onafhankelijk worden beoordeeld.
- **Latere opleveringen.** Tekenen, de financiele laag en export zijn niet getest en niet gestubd.
