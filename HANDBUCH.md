# MyEndPoint – Benutzerhandbuch

Dieses Handbuch erklärt, wie man mit der API arbeitet: Daten ansehen, anlegen, ändern und löschen und wie man sich jederzeit **Hilfe von der API selbst** holt.

Für Aufbau, Konfiguration und Entwicklung siehe [README.md](README.md).

> **Legende:** ✅ = funktioniert heute · 🔜 = geplant für Version 2.0
>
> **Ohne Anmeldung:** Die API verlangt standardmäßig keine Anmeldung. Ob du lesen und ändern darfst, regelt der Betreiber über das Netz bzw. optional über die Anmeldung.
>
> **Optionale Anmeldung ✅:** Hat der Betreiber die Anmeldung eingeschaltet, meldest du dich einmal mit Benutzername oder E-Mail und Passwort an und schickst danach bei jeder Anfrage deinen Token mit. Wie das geht, steht in [Abschnitt 3](#3-anmeldung-und-zugang). Alle anderen Abschnitte gelten unverändert. 🔜 Später kommen Kundenschlüssel und Rollen dazu, dann siehst du nur die Befehle, die deine Rolle benutzen darf.

---

## Inhalt

1. [Das Wichtigste in 1 Minute](#1-das-wichtigste-in-1-minute)
2. [Hilfe-Befehle](#2-hilfe-befehle)
3. [Anmeldung und Zugang](#3-anmeldung-und-zugang)
4. [Adressen](#4-adressen)
5. [Daten lesen](#5-daten-lesen)
6. [Daten anlegen](#6-daten-anlegen)
7. [Daten ändern](#7-daten-ändern)
8. [Daten löschen](#8-daten-löschen)
9. [Arbeiten mit HTML-Formularen](#9-arbeiten-mit-html-formularen)
10. [Formate wählen](#10-formate-wählen)
11. [Fehlermeldungen verstehen](#11-fehlermeldungen-verstehen)
12. [Kommandozeilen-Werkzeuge](#12-kommandozeilen-werkzeuge)
13. [Häufige Probleme](#13-häufige-probleme)
14. [Kurzreferenz](#14-kurzreferenz)

---

## 1. Das Wichtigste in 1 Minute

```bash
# Welche Daten gibt es?
curl http://localhost/MyEndPoint/api.php

# Hilfe zu allem
curl http://localhost/MyEndPoint/api.php/_hilfe

# Hilfe zu einer Ressource (Felder, Beispiele)
curl http://localhost/MyEndPoint/api.php/_hilfe/fahrzeuge

# Alle Fahrzeuge
curl http://localhost/MyEndPoint/api.php/fahrzeuge

# Ein Fahrzeug
curl http://localhost/MyEndPoint/api.php/fahrzeuge/5
```

| Ich möchte … | Methode | Adresse |
|---|---|---|
| sehen, was es gibt | `GET` | `api.php` |
| Hilfe | `GET` | `api.php/_hilfe` |
| eine Liste | `GET` | `api.php/fahrzeuge` |
| einen Datensatz | `GET` | `api.php/fahrzeuge/5` |
| etwas anlegen | `POST` | `api.php/fahrzeuge` |
| etwas ändern | `PATCH` | `api.php/fahrzeuge/5` |
| etwas löschen | `DELETE` | `api.php/fahrzeuge/5` |

> Die Feldnamen in diesem Handbuch (`Marke`, `Leistung`, `FTID` …) sind Beispiele. Die echten Namen zeigt immer `api.php/_hilfe/<ressource>`.

---

## 2. Hilfe-Befehle

Die API erklärt sich selbst. Die Hilfe wird **aus der Datenbank erzeugt** und ist deshalb immer aktuell, auch wenn eine neue Spalte oder Tabelle dazukommt.

### Übersicht der Hilfe-Befehle

| Befehl | Zeigt | Status |
|---|---|---|
| `GET api.php` | alle Ressourcen mit ihren Feldern (kurz) | ✅ |
| `GET api.php/_hilfe` | Gesamthilfe: Ressourcen, Aktionen, Parameter, Formate | 🔜 |
| `GET api.php/_hilfe/<ressource>` | Hilfe zu einer Ressource: Felder, Pflichtfelder, Verweise, Beispiele | 🔜 |
| `GET api.php/_hilfe/filter` | alle Filter- und Sortiermöglichkeiten | 🔜 |
| `GET api.php/_hilfe/formate` | alle Ein- und Ausgabeformate | 🔜 |
| `GET api.php/_hilfe/fehler` | alle Fehlercodes mit Erklärung | 🔜 |
| `?_hilfe` an jeder Adresse | Hilfe genau zu dieser Adresse | 🔜 |

`_help` funktioniert überall als Alias für `_hilfe`.

### Als Text oder als JSON

Die Hilfe gibt es in zwei Formen:

| Für | Aufruf | Ergebnis |
|---|---|---|
| Menschen (Terminal, Browser) | `?format=text` oder `Accept: text/plain` | lesbarer Text, wie eine `--help`-Ausgabe |
| Programme (Clients) | Standard bzw. `Accept: application/json` | JSON zum Weiterverarbeiten |

Im Browser wird automatisch die Textform angezeigt.

### Beispiel: Hilfe zu einer Ressource (Text)

```bash
curl "http://localhost/MyEndPoint/api.php/_hilfe/fahrzeuge?format=text"
```

```text
FAHRZEUGE
  Alle Fahrzeuge mit Typ, Art, Treibstoff und Halter.
  http://localhost/MyEndPoint/api.php/fahrzeuge

AKTIONEN
  GET     /fahrzeuge              Liste (Filter, Sortierung, Seiten)
  GET     /fahrzeuge/{FID}        ein Fahrzeug
  POST    /fahrzeuge              anlegen
  PATCH   /fahrzeuge/{FID}        ändern (nur mitgeschickte Felder)
  PUT     /fahrzeuge/{FID}        ändern (wie PATCH)
  DELETE  /fahrzeuge/{FID}        löschen

FELDER
  FID        int           automatisch vergeben (Primärschlüssel)
  Marke      varchar(50)   Pflicht
  Modell     varchar(50)   Pflicht
  Leistung   int           optional
  FTID       int           Pflicht   → Verweis auf fahrzeugtypen
  TID        int           optional  → Verweis auf treibstoffe
  FHID       int           optional  → Verweis auf fahrzeughalter

BEISPIELE
  Liste, gefiltert:   GET  /fahrzeuge?Marke=VW*&sort=-Leistung
  Anlegen (JSON):     POST /fahrzeuge
                      {"Marke": "…", "Modell": "…", "FTID": 1}
  Auswahl für FTID:   GET  /fahrzeugtypen

MEHR HILFE
  /_hilfe/filter    /_hilfe/formate    /_hilfe/fehler
```

### Beispiel: Hilfe zu einer Ressource (JSON)

```bash
curl http://localhost/MyEndPoint/api.php/_hilfe/fahrzeuge
```

```json
{
  "ressource": "fahrzeuge",
  "beschreibung": "Alle Fahrzeuge mit Typ, Art, Treibstoff und Halter.",
  "url": "http://localhost/MyEndPoint/api.php/fahrzeuge",
  "aktionen": {
    "auflisten": "GET /fahrzeuge",
    "holen":     "GET /fahrzeuge/{FID}",
    "anlegen":   "POST /fahrzeuge",
    "aendern":   "PATCH /fahrzeuge/{FID}",
    "loeschen":  "DELETE /fahrzeuge/{FID}"
  },
  "felder": [
    { "name": "FID",   "typ": "int(11)",     "pflicht": false, "automatisch": true,  "verweis": null },
    { "name": "Marke", "typ": "varchar(50)", "pflicht": true,  "automatisch": false, "verweis": null },
    { "name": "FTID",  "typ": "int(11)",     "pflicht": true,  "automatisch": false, "verweis": "fahrzeugtypen" }
  ],
  "beispiele": {
    "liste":   "GET /fahrzeuge?Marke=VW*&sort=-Leistung",
    "anlegen": { "Marke": "…", "Modell": "…", "FTID": 1 }
  }
}
```

### Hilfe direkt an der Adresse: `?_hilfe`

Wer gerade an einer Adresse arbeitet, hängt einfach `?_hilfe` an:

```bash
curl "http://localhost/MyEndPoint/api.php/fahrzeuge?_hilfe"       # = /_hilfe/fahrzeuge
curl "http://localhost/MyEndPoint/api.php/fahrzeuge/5?_hilfe"     # Hilfe zu Einzelzugriffen
```

Dabei wird **nichts ausgeführt**, auch nicht bei `POST` oder `DELETE`. Die API antwortet nur mit der Hilfe.

### Hilfe in Fehlermeldungen

Jede Fehlermeldung enthält einen Verweis auf die passende Hilfe:

```json
{
  "fehler": "Ungültige Eingaben.",
  "details": ["Pflichtfeld \"Marke\" fehlt."],
  "hilfe": "http://localhost/MyEndPoint/api.php/_hilfe/fahrzeuge"
}
```

### Hilfe mit SecurityCore 🔜

Ist der SecurityCore aktiv, zeigt die Hilfe **nur die Befehle, die deine Rolle benutzen darf**. Ressourcen ohne Recht fehlen ganz, nicht erlaubte Aktionen stehen nicht in der Liste. Die Hilfe selbst braucht dann ebenfalls deinen Kundenschlüssel (siehe Abschnitt 3).

---

## 3. Anmeldung und Zugang

> Dieser Abschnitt gilt **nur**, wenn der Betreiber die Anmeldung eingeschaltet hat (`sicherheit.aktiv`). Standardmäßig ist sie aus. Ob sie an ist, merkst du daran, dass `GET api.php` mit `401` antwortet. Sonst kannst du diesen Abschnitt überspringen.

### Anmelden mit Benutzername oder E-Mail ✅

Vom Betreiber bekommst du ein Konto (Benutzername, E-Mail und Passwort). Du meldest dich **einmal** an und bekommst einen Token:

```bash
curl -X POST -H "Content-Type: application/json" \
     -d '{"login":"admin@test.com","passwort":"dein-passwort"}' \
     http://localhost/MyEndPoint/api.php/_login
```

`login` darf der Benutzername **oder** die E-Mail sein. Ein HTML-Formular geht auch (Felder `login` und `passwort`, `method="post"`).

```json
{
    "token": "mep1.eyJrIjoxLCJuIjoiYWRtaW4iLCJleHAiOjE3OTU…",
    "gueltigBis": "2026-11-27T23:00:00+01:00",
    "benutzer": { "name": "admin", "email": "admin@test.com" }
}
```

Danach schickst du bei **jeder** Anfrage den Token im Header `Authorization` mit, mit `Bearer ` davor:

```bash
curl -H "Authorization: Bearer mep1.eyJrIjox…" http://localhost/MyEndPoint/api.php/fahrzeuge
```

In JavaScript:

```js
fetch('http://localhost/MyEndPoint/api.php/fahrzeuge', {
    headers: { Authorization: 'Bearer ' + token }
});
```

- **Wie lange gilt der Token?** Bis `gueltigBis` (Standard: 8 Stunden). Danach antwortet die API mit `401`, dann meldest du dich einfach neu an.
- **Abmelden:** Den Token wegwerfen. Er läuft von selbst ab.
- **Wer bin ich?** `GET api.php` zeigt unter `angemeldet` deinen Namen und deine E-Mail.
- **Client:** `client/index.php` zeigt das Anmeldeformular von selbst, wenn die API eine Anmeldung verlangt. Der Token gilt nur in diesem Browser-Tab.
- **Swagger-Doku (`docs.html`):** Zuerst `POST /_login` ausprobieren, den `token` kopieren, oben auf **Authorize** klicken und dort einfügen (ohne `Bearer `).
- **Den Token nie in die Adresse schreiben** (`?token=…`) und nicht weitergeben: Wer ihn hat, ist bis zum Ablauf als du angemeldet.

### Zugang mit Kundenschlüssel 🔜

> 🔜 Geplant. Dieser Teil gilt erst, wenn der Betreiber zusätzlich Kundenschlüssel einschaltet.

#### Was du vom Betreiber bekommst

| Angabe | Beispiel |
|---|---|
| deinen **Kundenschlüssel** (geheim halten!) | `7f3c9a…` (64 Zeichen) |
| den **Namen des Headers**, in dem er mitgeschickt wird | `X-MyEndPoint-Key` |
| ggf. ein **Präfix** vor dem Schlüssel | meist keins, manchmal `Bearer ` |

Der Header-Name wird vom Betreiber festgelegt und kann bei jeder Installation anders heißen. Fehlt dir die Angabe, liefert `GET api.php/_hilfe` ohne Schlüssel eine `401`-Antwort, die den richtigen Header-Namen nennt.

**Deine Datenbank:** Hat der Betreiber dir eine eigene Datenbank zugeordnet, landen alle deine Anfragen automatisch dort. Dein Schlüssel bzw. Token bestimmt das Ziel. Server, Datenbankname oder Datenbank-Passwort schickst du **nie** mit, die API würde sie auch nicht annehmen. Für eine andere Datenbank wendest du dich an den Betreiber.

#### Schlüssel mitschicken

```bash
curl -H "X-MyEndPoint-Key: 7f3c9a…" http://localhost/MyEndPoint/api.php/fahrzeuge
curl -H "X-MyEndPoint-Key: 7f3c9a…" "http://localhost/MyEndPoint/api.php/_hilfe?format=text"
```

In JavaScript (wie in `client/index.php`):

```js
fetch('http://localhost/MyEndPoint/api.php/fahrzeuge', {
    headers: { 'X-MyEndPoint-Key': schluessel }
});
```

**Nie in die Adresse schreiben** (`?key=…`): Adressen landen in Logs und im Browser-Verlauf.

#### Schlüssel gegen ein Token tauschen

Wie bei der Anmeldung mit Konto kannst du deinen Schlüssel einmal gegen ein Token mit Ablaufzeit tauschen:

```bash
curl -X POST -H "X-MyEndPoint-Key: 7f3c9a…" http://localhost/MyEndPoint/api.php/_login
```

```json
{ "token": "mep1.eyJr…", "gueltigBis": "2026-11-24T22:00:00+01:00" }
```

Danach schickst du im **selben Header** das Token statt des Schlüssels:

```bash
curl -H "X-MyEndPoint-Key: mep1.eyJr…" http://localhost/MyEndPoint/api.php/fahrzeuge
```

Ist das Token abgelaufen, antwortet die API mit `401`. Dann einfach erneut `POST /_login` aufrufen.

#### Was du siehst (mit Rollen 🔜)

- Nur die Ressourcen und Befehle, die deine Rolle benutzen darf: in der Hilfe, in der Übersicht `GET api.php` und in der Swagger-Doku.
- Eine Ressource, auf die du kein Recht hast, antwortet mit `404`, als gäbe es sie nicht.
- Eine Aktion, die du auf einer sichtbaren Ressource nicht ausführen darf (z. B. `DELETE`), antwortet mit `403`.

### Typische Antworten

| Code | Bedeutung | Was tun? |
|---|---|---|
| `400` | bei `POST /_login`: `login` oder `passwort` fehlt | beide Felder mitschicken |
| `401` | bei `POST /_login`: Benutzername/E-Mail oder Passwort falsch. Sonst: Token fehlt, ist falsch, abgelaufen oder das Konto gibt es nicht mehr | Eingaben prüfen (Groß-/Kleinschreibung beim Passwort!), neu anmelden, `Authorization: Bearer <token>` prüfen |
| `404` | bei `POST /_login`: Die Anmeldung ist nicht eingeschaltet | Kein Token nötig, einfach ohne arbeiten |
| `405` | `_login` mit `GET` aufgerufen | `POST` benutzen |
| `403` 🔜 | Aktion für deine Rolle nicht erlaubt, oder Zugriff von deinem Rechner bzw. ohne HTTPS gesperrt | in `_hilfe` nachsehen, was du darfst, sonst Betreiber fragen |
| `404` 🔜 | Ressource existiert nicht **oder** ist für deine Rolle nicht sichtbar | `_hilfe` zeigt, welche Ressourcen du benutzen kannst |
| `429` 🔜 | zu viele Anfragen in kurzer Zeit (nur wenn ein Limit aktiv ist) | warten, so lange der Header `Retry-After` angibt |

---

## 4. Adressen

Alle drei Schreibweisen funktionieren gleich:

```
http://localhost/MyEndPoint/api.php/fahrzeuge/5
http://localhost/MyEndPoint/api/fahrzeuge/5        (braucht .htaccess)
http://localhost/MyEndPoint/fahrzeuge/5            (braucht .htaccess)
```

Aufbau:

```
api.php / <ressource> / <id>
          fahrzeuge     5
```

Verfügbare Ressourcen: `fahrzeuge`, `fahrzeugtypen`, `fahrzeugarten`, `treibstoffe`, `fahrzeughalter`. Die aktuelle Liste zeigt `GET api.php`.

---

## 5. Daten lesen

### Liste

```bash
curl http://localhost/MyEndPoint/api.php/fahrzeuge
```

```json
{
  "daten":  [ { "FID": 1, "Marke": "VW", "Modell": "Golf", "FTID": 3 } ],
  "gesamt": 42,
  "limit":  100,
  "offset": 0
}
```

- `daten`: die Datensätze dieser Seite
- `gesamt`: Anzahl aller Treffer, ohne Seitenbegrenzung
- `limit` / `offset`: Größe und Start dieser Seite

### Einzelner Datensatz

```bash
curl http://localhost/MyEndPoint/api.php/fahrzeuge/5
```

Gibt es die ID nicht, antwortet die API mit `404`.

### Filtern

| Ich suche … | Anhängen | Status |
|---|---|---|
| genau einen Wert | `?FTID=3` | ✅ |
| mit Platzhalter `*` | `?Marke=VW*` (beginnt mit „VW“) | ✅ |
| Text irgendwo enthalten | `?Modell=*olf*` | ✅ |
| mehrere Bedingungen (UND) | `?FTID=3&Marke=VW*` | ✅ |
| größer / kleiner | `?Leistung[gt]=100`, `?Leistung[lte]=150` | 🔜 |
| ungleich | `?FTID[ne]=2` | 🔜 |
| einer von mehreren | `?FTID[in]=1,2,3` | 🔜 |
| leer / nicht leer | `?FHID[null]=true` | 🔜 |

Groß-/Kleinschreibung der Feldnamen ist egal (`marke` = `Marke`). Leere Filterwerte werden ignoriert.

### Sortieren

```
?sort=Marke                 aufsteigend
?sort=-Leistung             absteigend (Minus davor)
?sort=Marke,-Leistung       erst nach Marke, dann nach Leistung absteigend
```

Ohne `sort` wird nach dem Primärschlüssel sortiert.

### Seitenweise abrufen

```
?limit=20               20 Datensätze pro Seite (Standard 100, höchstens 1000)
?limit=20&offset=40     Seite 3 (Datensätze 41–60)
```

Formel: `offset = (Seite − 1) × limit`

### Nur bestimmte Felder 🔜

```
?fields=FID,Marke,Modell
```

---

## 6. Daten anlegen

```bash
curl -X POST \
     -H "Content-Type: application/json" \
     -d '{"Marke": "VW", "Modell": "Golf", "FTID": 3}' \
     http://localhost/MyEndPoint/api.php/fahrzeuge
```

Antwort: `201 Created`, der neue Datensatz inklusive vergebener ID. Im Header `Location` steht seine Adresse.

**Worauf achten:**
- **Pflichtfelder** müssen dabei sein. Welche das sind, zeigt `_hilfe/<ressource>`.
- Den **Primärschlüssel** (z. B. `FID`) weglassen, wenn die Datenbank ihn selbst vergibt.
- **Verweise** (z. B. `FTID`) müssen auf einen existierenden Eintrag zeigen. Mögliche Werte liefert `GET api.php/fahrzeugtypen`.
- Unbekannte Felder werden abgelehnt. Die Fehlermeldung nennt die erlaubten Felder.

🔜 **Mehrere auf einmal:** eine JSON-Liste oder eine CSV-Datei schicken. Entweder werden alle Datensätze gespeichert oder keiner.

```bash
curl -X POST -H "Content-Type: text/csv" \
     --data-binary @neue_fahrzeuge.csv \
     http://localhost/MyEndPoint/api.php/fahrzeuge
```

---

## 7. Daten ändern

```bash
curl -X PATCH \
     -H "Content-Type: application/json" \
     -d '{"Leistung": 110}' \
     http://localhost/MyEndPoint/api.php/fahrzeuge/5
```

- Es werden **nur die mitgeschickten Felder** geändert. `PUT` verhält sich genauso wie `PATCH`.
- Der Primärschlüssel kann nicht geändert werden. Schickt man das ganze Objekt samt passender ID zurück, ist das aber in Ordnung.
- Ein Pflichtfeld darf nicht geleert werden.

Antwort: der Datensatz mit dem neuen Stand.

---

## 8. Daten löschen

```bash
curl -X DELETE http://localhost/MyEndPoint/api.php/fahrzeuge/5
```

```json
{ "meldung": "Datensatz \"5\" wurde gelöscht.", "geloescht": { "FID": 5, "Marke": "VW" } }
```

Die Antwort enthält den gelöschten Datensatz. So lässt er sich bei Bedarf neu anlegen.

**Wird der Datensatz noch verwendet**, z. B. ein Fahrzeugtyp, den noch Fahrzeuge haben, verweigert die API das Löschen mit `409` und nennt, wo er verwendet wird:

```json
{ "fehler": "Der Datensatz wird noch in \"fahrzeuge\" verwendet (Spalte FTID, Anzahl: 12)." }
```

Erst die abhängigen Datensätze ändern oder löschen, dann erneut versuchen.

> **Achtung:** Ist der SecurityCore aus (Standard), kann jeder, der die API erreicht, Daten löschen. Siehe README, Abschnitt *Sicherheit*.

---

## 9. Arbeiten mit HTML-Formularen

HTML-Formulare kennen nur `GET` und `POST`. Die gewünschte Aktion steht deshalb in einem versteckten Feld:

```html
<!-- Ändern -->
<form method="post" action="/MyEndPoint/api.php/fahrzeuge">
    <input type="hidden" name="_method" value="PATCH">
    <input type="hidden" name="FID" value="5">
    <input name="Leistung" value="110">
    <button>Speichern</button>
</form>

<!-- Löschen -->
<form method="post" action="/MyEndPoint/api.php/fahrzeuge">
    <input type="hidden" name="_method" value="DELETE">
    <input type="hidden" name="FID" value="5">
    <button>Löschen</button>
</form>
```

| Weg | Werte |
|---|---|
| Feld `_method` | `GET`, `POST`, `PUT`, `PATCH`, `DELETE` |
| altes Feld `operation` | `GET`, `POST`, `UPDATE` (= `PUT`), `DELETE` |
| Header `X-HTTP-Method-Override` | wie `_method` |

- Steht keine ID in der Adresse, wird sie aus dem Feld gelesen, das wie der Primärschlüssel heißt (hier `FID`).
- Felder, die mit `_` beginnen, werden nie gespeichert.
- Ein leeres Formularfeld wird als „leer“ (`NULL`) gespeichert, wenn das Feld leer sein darf.

Ein fertiges Beispiel ist der Client unter `client/index.php`.

---

## 10. Formate wählen

| Richtung | Wie | Standard |
|---|---|---|
| Was ich schicke | Header `Content-Type` | JSON oder Formular |
| Was ich bekomme | Header `Accept` oder `?format=` | JSON |

| Format | `Content-Type` / `Accept` | `?format=` | Schicken | Bekommen |
|---|---|---|---|---|
| JSON | `application/json` | `json` | ✅ | ✅ |
| Formular | `application/x-www-form-urlencoded` | – | ✅ | – |
| CSV | `text/csv` | `csv` | 🔜 | 🔜 |
| XML | `application/xml` | `xml` | 🔜 | 🔜 |
| YAML | `application/x-yaml` | `yaml` | 🔜 | 🔜 |
| Excel | `application/vnd.openxmlformats-officedocument.spreadsheetml.sheet` | `xlsx` | 🔜 | 🔜 |
| SQL | `application/sql` | `sql` | – | 🔜 |
| Text (nur Hilfe) | `text/plain` | `text` | – | 🔜 |

```bash
# 🔜 Liste als CSV für Excel speichern
curl "http://localhost/MyEndPoint/api.php/fahrzeuge?format=csv" -o fahrzeuge.csv

# 🔜 Datei umwandeln, ohne etwas zu speichern (CSV rein, XML raus)
curl -X POST -H "Content-Type: text/csv" -H "Accept: application/xml" \
     --data-binary @fahrzeuge.csv http://localhost/MyEndPoint/api.php/_convert
```

**CSV-Hinweise:** Die erste Zeile enthält die Feldnamen. Trennzeichen `;` oder `,` wird erkannt. Eine leere Zelle bedeutet „leer“ (`NULL`). Ausgegebene CSV-Dateien öffnen sich in deutschem Excel direkt richtig.

Die vollständige Liste liefert `GET api.php/_hilfe/formate`.

---

## 11. Fehlermeldungen verstehen

Jeder Fehler hat einen Statuscode und eine Meldung auf Deutsch:

```json
{ "fehler": "Was ist passiert", "details": ["Genauere Angaben"], "hilfe": "Link zur passenden Hilfe" }
```

| Code | Bedeutung | Was tun? |
|---|---|---|
| `400` | Eingabe oder Parameter ungültig | `details` lesen: Pflichtfeld fehlt, unbekanntes Feld, Wert passt nicht zum Datentyp, Verweis ins Leere |
| `401` | nur mit Anmeldung: Anmeldedaten falsch, Token fehlt oder abgelaufen | siehe [Abschnitt 3](#3-anmeldung-und-zugang) |
| `403` | nur mit SecurityCore 🔜: Aktion für deine Rolle nicht erlaubt | `_hilfe` zeigt, was du darfst |
| `404` | Ressource oder ID gibt es nicht | Schreibweise prüfen, `GET api.php` zeigt alle Ressourcen |
| `405` | Aktion passt nicht zur Adresse | z. B. `DELETE` ohne ID, oder `POST` mit ID |
| `406` | Ausgabeformat nicht verfügbar 🔜 | `_hilfe/formate` |
| `409` | Konflikt | Wert existiert schon (doppelt), oder Datensatz wird noch verwendet |
| `413` | Anfrage zu groß 🔜 | Datei aufteilen |
| `415` | Eingabeformat nicht verfügbar 🔜 | `Content-Type` prüfen |
| `500` | Fehler im Server | Administrator fragen (Details stehen im Server-Log) |
| `503` | Datenbank nicht erreichbar 🔜 | später erneut versuchen, läuft MariaDB bzw. MongoDB? (XAMPP: MySQL im Control Panel starten) |

Die vollständige Liste liefert `GET api.php/_hilfe/fehler` 🔜.

### Falscher Datentyp ✅

Die API weiß aus der Datenbank, welchen Typ und welche Grenzen jedes Feld hat (zu sehen in `GET api.php` unter `datentyp`, `min`, `max`, `laenge`, `werte` …). Passt ein Wert nicht, sagt sie genau, welches Feld und warum:

```json
{
  "fehler": "Ungültige Eingaben.",
  "details": [
    "Feld \"Leistung\": ganze Zahl von -2147483648 bis 2147483647 erwartet.",
    "Feld \"Fahrzeug_Name\": höchstens 50 Zeichen erlaubt."
  ]
}
```

| Meldung | Bedeutung |
|---|---|
| `ganze Zahl erwartet` bzw. `ganze Zahl von … bis … erwartet` | Text oder Kommazahl in einem Zahlenfeld, oder Zahl außerhalb des Bereichs |
| `Zahl erwartet`, `Dezimalzahl erwartet` | kein gültiger Zahlenwert. Das deutsche Komma ist erlaubt: `4,2` |
| `höchstens 2 Nachkommastellen erlaubt`, `Zahl zu groß` | passt nicht in `DECIMAL(6,2)` & Co. |
| `höchstens 50 Zeichen erlaubt` | Text zu lang |
| `erlaubt sind nur: PKW, LKW` | Auswahlfeld (ENUM): nur diese Werte |
| `Ja/Nein erwartet` | erlaubt: `true`/`false`, `1`/`0`, `ja`/`nein` |
| `Datum erwartet (JJJJ-MM-TT oder TT.MM.JJJJ)` | kein gültiges Datum, z. B. `31.02.2026` |

Dasselbe gilt für Filter: `?Leistung=abc` ergibt `Filter "Leistung": ganze Zahl … erwartet.`

---

## 12. Kommandozeilen-Werkzeuge

Für Administratoren gibt es Werkzeuge im Ordner `bin/`. Jedes zeigt mit `--hilfe` (oder `--help`, `-h`) seine Anleitung.

✅ Heute schon da: `php bin/passwort-hash.php` erzeugt den Hash für ein Passwort der Login-Tabelle. Das Passwort wird verdeckt abgefragt, ausgegeben wird nur der Hash (`$2y$10$…`) für die Spalte `password`. Alle anderen Werkzeuge sind 🔜 geplant.

```bash
php bin/hilfe.php
```

```text
MyEndPoint – Werkzeuge

  php bin/config-pruefen.php          Konfiguration prüfen
  php bin/schema-aktualisieren.php    Datenbank-Schema neu einlesen (nach Tabellenänderungen)
  php bin/bausteine-aktualisieren.php Bausteine (Connectoren, Codecs, Wächter) neu suchen
  php bin/passwort-hash.php           Passwort-Hash für die Login-Tabelle erzeugen  ✅
  php bin/schluessel-erzeugen.php     Kundenschlüssel für den SecurityCore erzeugen
  php bin/klassen-erzeugen.php        PHP-Klassen aus den Tabellen erzeugen
  php bin/umziehen.php                Daten einer Ressource in ein anderes Backend kopieren

Hilfe zu einem Werkzeug:   php bin/<werkzeug>.php --hilfe
```

Beispiel:

```bash
php bin/umziehen.php --hilfe
```

```text
umziehen – Daten einer Ressource in eine andere Verbindung kopieren

AUFRUF
  php bin/umziehen.php <ressource> --nach=<verbindung> [optionen]

OPTIONEN
  --nach=<verbindung>   Ziel aus config/gateway.php (Pflicht)
  --probelauf           nur anzeigen, was kopiert würde
  --hilfe, -h           diese Hilfe

BEISPIEL
  php bin/umziehen.php fahrzeuge --nach=archiv --probelauf
```

| Werkzeug | Wann benutzen? |
|---|---|
| `config-pruefen.php` | nach jeder Änderung an `config/gateway.php` |
| `schema-aktualisieren.php` | nach neuen Tabellen oder Spalten in der Datenbank |
| `bausteine-aktualisieren.php` | nach einem neuen oder entfernten Connector, Codec oder Wächter (in Produktion Pflicht, in der Entwicklung automatisch) |
| `passwort-hash.php` ✅ | ein Konto in der Login-Tabelle anlegen oder ein Passwort ändern (Hash in die Spalte `password`) |
| `schluessel-erzeugen.php` | einen neuen Kunden für den SecurityCore anlegen. Zeigt den Schlüssel **einmal** an und gibt die Zeile mit dem Hash für die `.env` aus. |
| `klassen-erzeugen.php` | wenn PHP-Code mit typisierten Objekten arbeiten soll |
| `umziehen.php` | beim Wechsel einer Ressource auf ein anderes Backend |

---

## 13. Häufige Probleme

| Problem | Ursache | Lösung |
|---|---|---|
| `404` bei `/api/fahrzeuge`, aber `api.php/fahrzeuge` geht | `.htaccess` wird nicht beachtet | in Apache `mod_rewrite` aktivieren und `AllowOverride All` setzen, oder `api.php/…` benutzen |
| `500` „Konfiguration fehlerhaft“ mit „config/.env fehlt“ | Zugangsdaten-Datei fehlt | `config/.env.example` nach `config/.env` kopieren und `DB_USER`/`DB_PASS` eintragen |
| `500` „Konfiguration fehlerhaft“ ohne Details | Fehler in `config/gateway.php` | `API_DEBUG=1` in `config/.env` setzen, dann steht der Grund in der Antwort (sonst im Server-Log) |
| `500` mit „Unknown database“ bzw. „Table … doesn't exist“ | Datenbank oder Tabelle gibt es nicht | `DB_NAME` (und `DB_HOST`) in `config/.env` sowie `quelle` unter `ressourcen` in `config/gateway.php` prüfen |
| Neue Spalte taucht nicht auf | Schema-Cache ist veraltet 🔜 | `php bin/schema-aktualisieren.php` |
| Neues Format bzw. neue Datenbank wird nicht erkannt 🔜 | Baustein-Cache veraltet, Abhängigkeit fehlt oder Datei falsch benannt | `php bin/bausteine-aktualisieren.php`, danach `php bin/config-pruefen.php` (nennt den Grund) |
| `503` „Datenbank nicht erreichbar“ 🔜 | Datenbankserver läuft nicht oder Zugangsdaten falsch | MySQL in XAMPP starten, Zugangsdaten in `config/.env` prüfen |
| `400` „Unbekanntes Feld“ | Tippfehler oder Feld gibt es nicht | erlaubte Felder stehen in der Meldung bzw. in `_hilfe/<ressource>` |
| `400` „Eintrag … gibt es nicht“ | Verweis zeigt ins Leere | gültige IDs der Zielressource abrufen (z. B. `GET api.php/fahrzeugtypen`) |
| `409` beim Löschen | Datensatz wird noch verwendet | abhängige Datensätze zuerst ändern oder löschen |
| Umlaute kaputt in Excel | CSV ohne BOM geöffnet | 🔜 CSV aus der API hat ein BOM, oder in Excel über „Daten → Aus Text/CSV“ öffnen |
| JavaScript-Fehler „CORS“ | Aufruf von einer anderen Adresse | Adresse in `config/gateway.php` unter `cors.erlaubteUrspruenge` eintragen (Betreiber) |
| Antwort ist HTML statt JSON | PHP-Fehler vor der API | Server-Log prüfen (XAMPP: `apache/logs/error.log`) |
| `401` „Nicht angemeldet oder Anmeldung abgelaufen“ | Anmeldung ist an, Token fehlt oder ist abgelaufen | `POST api.php/_login`, dann `Authorization: Bearer <token>` mitschicken |
| `401` bei der Anmeldung, obwohl das Passwort stimmt | In der Login-Tabelle steht das Passwort im Klartext statt als Hash (mit `API_DEBUG=1` steht das in der Antwort) | `php bin/passwort-hash.php`, den Hash in die Spalte `password` eintragen (Betreiber) |
| `401` trotz Token im Browser, mit `curl` geht es | Apache gibt den Header `Authorization` nicht an PHP weiter (FastCGI) | `.htaccess` aus dem Repo verwenden (Zeile mit `E=HTTP_AUTHORIZATION`) und `AllowOverride All` |
| `500` „Konfiguration fehlerhaft“ nach `sicherheit.aktiv = true` | `TOKEN_SECRET` fehlt oder ist zu kurz, oder die Login-Tabelle steht unter `ressourcen` | `TOKEN_SECRET` mit `php -r "echo bin2hex(random_bytes(32));"` erzeugen und in `config/.env` eintragen; mit `API_DEBUG=1` steht der genaue Grund in der Antwort |
| `500` „Die Login-Datenbank passt nicht zur Konfiguration“ | Login-Tabelle oder -Spalte gibt es nicht, oder `LOGIN_DB_NAME` zeigt auf die falsche Datenbank | `login.sql` ausführen, `sicherheit.login` in `config/gateway.php` und `LOGIN_DB_*` in `config/.env` prüfen |
| `401` trotz Schlüssel 🔜 | falscher Header-Name, Präfix fehlt, Token abgelaufen oder Kunde gesperrt | Header-Name aus der `401`-Antwort übernehmen, neues Token holen, Betreiber fragen |
| Im Browser kommt der Schlüssel-Header nicht an 🔜 | CORS: der Header ist für diese Seite nicht freigegeben | Seite in `cors.erlaubteUrspruenge` eintragen (Betreiber) |
| API startet nach dem Einschalten des SecurityCores nicht 🔜 | Einstellung unvollständig (absichtlich, fail closed) | `php bin/config-pruefen.php` nennt den fehlenden Wert |

---

## 14. Kurzreferenz

```text
HILFE
  GET  api.php                         Übersicht
  GET  api.php/_hilfe                  Gesamthilfe                    🔜
  GET  api.php/_hilfe/<ressource>      Felder + Beispiele             🔜
  GET  api.php/_hilfe/filter|formate|fehler                           🔜
  ?_hilfe                              Hilfe zur aktuellen Adresse    🔜
  ?format=text                         Hilfe als lesbarer Text        🔜

LESEN
  GET  api.php/<ressource>             Liste
  GET  api.php/<ressource>/<id>        ein Datensatz
       ?Feld=Wert   ?Feld=Anf*   ?sort=Feld,-Feld   ?limit=20&offset=40
       ?Feld[gt]=100   ?Feld[in]=1,2,3   ?fields=A,B                  🔜

SCHREIBEN
  POST    api.php/<ressource>          anlegen     → 201 + Location
  PATCH   api.php/<ressource>/<id>     ändern
  PUT     api.php/<ressource>/<id>     ändern (wie PATCH)
  DELETE  api.php/<ressource>/<id>     löschen     → 409, wenn noch verwendet

FORMULARE
  method="post" + <input type="hidden" name="_method" value="PATCH|DELETE">

FORMATE
  Content-Type: application/json | application/x-www-form-urlencoded | text/csv 🔜
  Accept:       application/json | text/csv 🔜 | application/xml 🔜
  ?format=json|csv|xml|yaml|xlsx|sql|text                             🔜

ANMELDUNG (nur wenn sie eingeschaltet ist)
  POST api.php/_login                  {"login":"name oder e-mail","passwort":"…"} → Token
  Authorization: Bearer mep1.…         bei jeder weiteren Anfrage
  401 = Anmeldedaten falsch, Token fehlt oder abgelaufen

ZUGANG MIT KUNDENSCHLÜSSEL                                            🔜
  <Header-Name>: <Kundenschlüssel>     bei jeder Anfrage, Name vom Betreiber
  POST api.php/_login                  Schlüssel → Token
  403 = nicht erlaubt   404 = nicht sichtbar

WERKZEUGE
  php bin/passwort-hash.php                   Passwort-Hash für die Login-Tabelle  ✅
  php bin/hilfe.php                                                   🔜
  php bin/<werkzeug>.php --hilfe
  php bin/schluessel-erzeugen.php <kunde>     (Betreiber, SecurityCore)
```
