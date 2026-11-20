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
docker compose exec app php artisan app:reset-admin-password admin@localhost
```

Voeg `--second-factor` toe als ook de tweede stap van het inloggen kwijt is. Het commando toont het nieuwe wachtwoord en beëindigt alle sessies van die gebruiker.

## Back-up en terugzetten

```
make backup
make restore FOLDER=<map>
```

`make backup` kopieert de database, de bestanden en de sleutel van de toepassing naar een map met datum en tijd en toont die map. Bewaar de map veilig: de sleutel zit erin, en zonder die sleutel zijn de gegevens van de tweede stap niet terug te lezen. Maak elke week een back-up en altijd vóór een import. `make restore` zet precies de gegevens van die map terug. Wat na de laatste back-up is gedaan, gaat verloren.

## Importeren

Op het Overzicht kiest u Importeren uit JSON voor een canvas of voor alles. Kostenposten, structuur en verdelingen kunnen met een CSV-bestand van hoogstens 5 MB; een JSON-bestand mag tot 50 MB zijn. U ziet eerst een controle per regel. Importeren kan pas als er geen fouten meer zijn, en gebeurt dan helemaal of niet.

## Als de browser niet wil inloggen

De toepassing zet veilige cookies. Weigert uw browser die op http://localhost:8080, dan komt de inlogpagina steeds terug. Zet in het bestand `.env` de regel `SESSION_SECURE_COOKIE=false` en start opnieuw met `docker compose up -d`. Chrome en Edge zijn getest met Chromium; voor Firefox en Safari is dit niet vastgesteld.

## Veelgestelde vragen

**Kan een collega meekijken?** Ja, via een SSH-tunnel naar uw machine: `ssh -L 8080:127.0.0.1:8080 gebruiker@machine`, daarna http://localhost:8080 op de eigen computer. De toepassing luistert alleen op uw eigen machine.

**Waar staan mijn gegevens?** In één Docker-volume op uw machine en in uw back-ups.

**Hoe verwijder ik de voorbeeldgegevens?** In het beheerscherm, onder voorbeeldgegevens.

**Wat als een momentopname beschadigd is?** U krijgt een melding bij de systeemmeldingen en die momentopname is dan niet te openen. De rest werkt gewoon.
