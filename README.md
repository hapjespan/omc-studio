# OMC Studio

OMC Studio is een lokale webtoepassing om een Operating Model Canvas op te bouwen, er kosten aan te koppelen, het te tekenen en er momentopnamen van te bewaren. Alles draait op uw eigen machine en maakt nooit verbinding met internet.

## Starten

Zet Docker klaar en voer in deze map twee commando's uit:

```
cp env.example .env
docker compose up -d
```

De eerste keer heeft de machine eenmalig internet nodig om de installatie te bouwen. De bouw duurde 7 seconden met een warme Docker-cache; op een schone machine duurt hij langer, afhankelijk van uw verbinding. Daarna werkt alles zonder internet. Open http://localhost:8080. Stoppen kan met `docker compose down`; uw gegevens blijven bewaard.

## Eerste keer inloggen

Het eerste wachtwoord staat eenmalig in het logboek van de toepassing. Lees het met `docker compose logs app`. Log in als `admin@localhost` en kies meteen een nieuw wachtwoord van minstens 12 tekens. Het eerste wachtwoord vervalt na 24 uur.

## Wachtwoord kwijt

Een nieuw eenmalig wachtwoord maakt u zo:

```
docker compose exec -u www-data app php artisan app:reset-admin-password admin@localhost
```

Voeg `--second-factor` toe als ook de tweede stap van het inloggen kwijt is. Het commando toont het nieuwe wachtwoord en beëindigt alle sessies van die gebruiker.

## Back-up en terugzetten

```
make backup
make restore FOLDER=<map>
```

`make backup` maakt in het opslagvolume van de toepassing een map `storage/backups/<datum_tijd>` met de database, de geüploade bestanden en de sleutel van de toepassing, en toont die map. Die map staat dus nog in dezelfde container: kopieer hem naar een andere plek met `docker compose cp app:/var/www/html/storage/backups/<datum_tijd> .`. Bewaar de kopie veilig: de sleutel zit erin, en zonder die sleutel zijn de gegevens van de tweede stap niet terug te lezen. Maak elke week een back-up en altijd vóór een import. Verwijder back-ups die u niet meer nodig hebt; ze bevatten ook de gebruikersgegevens.

De beveiligingslogboeken (`storage/logs/security-*.log`, 90 dagen) staan niet in de back-up: kopieer ze mee met `docker compose cp app:/var/www/html/storage/logs .` als je ze langer wilt bewaren dan die 90 dagen of bij een incident nodig hebt.

Terugzetten gaat met een map die in het volume staat. Kopieer een bewaarde map eerst terug met `docker compose cp <map> app:/var/www/html/storage/backups/`. Zet de map over naar de gebruiker van de toepassing met `docker compose exec -u root app chown -R www-data:www-data storage/backups` en geef dan `make restore FOLDER=storage/backups/<datum_tijd>`. `make restore` maakt eerst een veiligheidskopie van de huidige gegevens en zet daarna precies de gegevens van die map terug. Wat na de laatste back-up is gedaan, gaat verloren.

Als ook het automatisch terugzetten van de vorige installatie mislukt, blijft de toepassing gestopt. Bewaar het volume en de gemelde herstelmap. De melding noemt ook de volledige veiligheidskopie. Zet die kopie terug op een schoon opslagvolume volgens de stappen hierboven en controleer de login, gegevens en logo’s voordat u het oorspronkelijke volume opruimt.

## Importeren

Op het Overzicht kiest u Importeren uit JSON voor een canvas. Een volledige import van alles staat op de beheerpagina Volledige export en import en werkt alleen in een lege applicatie; de voorbeelddata (Voorbeelddata) moet dan eerst weg. Kostenposten, structuur en verdelingen kunnen met een CSV-bestand van hoogstens 5 MB; een JSON-bestand mag tot 50 MB zijn. U ziet eerst een controle per regel. Importeren kan pas als er geen fouten meer zijn, en gebeurt dan helemaal of niet.

## Als de browser niet wil inloggen

De toepassing zet veilige cookies. Weigert uw browser die op http://localhost:8080, dan komt de inlogpagina steeds terug. Zet in het bestand `.env` de regel `SESSION_SECURE_COOKIE=false` en start opnieuw met `docker compose up -d`. Chrome en Edge zijn getest met Chromium; voor Firefox en Safari is dit niet vastgesteld.

## Veelgestelde vragen

**Kan een collega meekijken?** Ja, via een SSH-tunnel naar uw machine: `ssh -L 8080:127.0.0.1:8080 gebruiker@machine`, daarna http://localhost:8080 op de eigen computer. De toepassing luistert alleen op uw eigen machine.

**Waar staan mijn gegevens?** In één Docker-volume op uw machine en in uw back-ups.

**Hoe verwijder ik de voorbeeldgegevens?** In het beheerscherm, onder Voorbeelddata.

**Wat als een momentopname beschadigd is?** U krijgt een melding bij de systeemmeldingen en die momentopname is dan niet te openen. De rest werkt gewoon.
