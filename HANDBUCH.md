# MyEndPoint – Benutzerhandbuch

Dieses Handbuch erklärt, wie man mit der API arbeitet: Daten ansehen, anlegen, ändern und löschen und wie man sich jederzeit **Hilfe von der API selbst** holt.

Für Aufbau, Konfiguration und Entwicklung siehe [README.md](README.md).

> **Legende:** ✅ = funktioniert heute · 🔜 = geplant für Version 2.0
>
> **Hinweis zum Übergang:** Die heutige `api.php` verlangt noch eine Anmeldung per Basic Auth. In Version 2.0 entfällt sie (siehe README, Roadmap Phase 1). Dieses Handbuch beschreibt den Stand **ohne Anmeldung**. Bis zur Umstellung bei `curl` zusätzlich `-u email:passwort` angeben.

---

## Inhalt

1. [Das Wichtigste in 1 Minute](#1-das-wichtigste-in-1-minute)
2. [Hilfe-Befehle](#2-hilfe-befehle)
3. [Adressen](#3-adressen)
4. [Daten lesen](#4-daten-lesen)
5. [Daten anlegen](#5-daten-anlegen)
6. [Daten ändern](#6-daten-ändern)
7. [Daten löschen](#7-daten-löschen)
8. [Arbeiten mit HTML-Formularen](#8-arbeiten-mit-html-formularen)
9. [Formate wählen](#9-formate-wählen)
10. [Fehlermeldungen verstehen](#10-fehlermeldungen-verstehen)
11. [Kommandozeilen-Werkzeuge](#11-kommandozeilen-werkzeuge)
12. [Häufige Probleme](#12-häufige-probleme)
13. [Kurzreferenz](#13-kurzreferenz)

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

---

## 3. Adressen

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

## 4. Daten lesen

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

## 5. Daten anlegen

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

## 6. Daten ändern

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

## 7. Daten löschen

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

> **Achtung:** Ohne Anmeldung kann jeder, der die API erreicht, Daten löschen. Siehe README, Abschnitt *Sicherheit*.

---

## 8. Arbeiten mit HTML-Formularen

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

## 9. Formate wählen

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

## 10. Fehlermeldungen verstehen

Jeder Fehler hat einen Statuscode und eine Meldung auf Deutsch:

```json
{ "fehler": "Was ist passiert", "details": ["Genauere Angaben"], "hilfe": "Link zur passenden Hilfe" }
```

| Code | Bedeutung | Was tun? |
|---|---|---|
| `400` | Eingabe oder Parameter ungültig | `details` lesen: Pflichtfeld fehlt, unbekanntes Feld, falscher Wert, Verweis ins Leere |
| `404` | Ressource oder ID gibt es nicht | Schreibweise prüfen, `GET api.php` zeigt alle Ressourcen |
| `405` | Aktion passt nicht zur Adresse | z. B. `DELETE` ohne ID, oder `POST` mit ID |
| `406` | Ausgabeformat nicht verfügbar 🔜 | `_hilfe/formate` |
| `409` | Konflikt | Wert existiert schon (doppelt), oder Datensatz wird noch verwendet |
| `413` | Anfrage zu groß 🔜 | Datei aufteilen |
| `415` | Eingabeformat nicht verfügbar 🔜 | `Content-Type` prüfen |
| `500` | Fehler im Server | Administrator fragen (Details stehen im Server-Log) |
| `503` | Datenbank nicht erreichbar 🔜 | später erneut versuchen, läuft MariaDB bzw. MongoDB? (XAMPP: MySQL im Control Panel starten) |

Die vollständige Liste liefert `GET api.php/_hilfe/fehler`.

---

## 11. Kommandozeilen-Werkzeuge

🔜 Für Administratoren gibt es Werkzeuge im Ordner `bin/`. Jedes zeigt mit `--hilfe` (oder `--help`, `-h`) seine Anleitung.

```bash
php bin/hilfe.php
```

```text
MyEndPoint – Werkzeuge

  php bin/config-pruefen.php          Konfiguration prüfen
  php bin/schema-aktualisieren.php    Datenbank-Schema neu einlesen (nach Tabellenänderungen)
  php bin/bausteine-aktualisieren.php Bausteine (Connectoren, Codecs) neu suchen
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
| `bausteine-aktualisieren.php` | nach einem neuen oder entfernten Connector bzw. Codec (in Produktion Pflicht, in der Entwicklung automatisch) |
| `klassen-erzeugen.php` | wenn PHP-Code mit typisierten Objekten arbeiten soll |
| `umziehen.php` | beim Wechsel einer Ressource auf ein anderes Backend |

---

## 12. Häufige Probleme

| Problem | Ursache | Lösung |
|---|---|---|
| `404` bei `/api/fahrzeuge`, aber `api.php/fahrzeuge` geht | `.htaccess` wird nicht beachtet | in Apache `mod_rewrite` aktivieren und `AllowOverride All` setzen, oder `api.php/…` benutzen |
| `500` mit „Datenbank … fehlt“ | Datenbank nicht importiert | SQL-Datei in phpMyAdmin importieren |
| Neue Spalte taucht nicht auf | Schema-Cache ist veraltet 🔜 | `php bin/schema-aktualisieren.php` |
| Neues Format bzw. neue Datenbank wird nicht erkannt 🔜 | Baustein-Cache veraltet, Abhängigkeit fehlt oder Datei falsch benannt | `php bin/bausteine-aktualisieren.php`, danach `php bin/config-pruefen.php` (nennt den Grund) |
| `503` „Datenbank nicht erreichbar“ 🔜 | Datenbankserver läuft nicht oder Zugangsdaten falsch | MySQL in XAMPP starten, Zugangsdaten in `config/.env` prüfen |
| `400` „Unbekanntes Feld“ | Tippfehler oder Feld gibt es nicht | erlaubte Felder stehen in der Meldung bzw. in `_hilfe/<ressource>` |
| `400` „Eintrag … gibt es nicht“ | Verweis zeigt ins Leere | gültige IDs der Zielressource abrufen (z. B. `GET api.php/fahrzeugtypen`) |
| `409` beim Löschen | Datensatz wird noch verwendet | abhängige Datensätze zuerst ändern oder löschen |
| Umlaute kaputt in Excel | CSV ohne BOM geöffnet | 🔜 CSV aus der API hat ein BOM, oder in Excel über „Daten → Aus Text/CSV“ öffnen |
| JavaScript-Fehler „CORS“ | Aufruf von einer anderen Adresse | Adresse in `cors.erlaubteUrspruenge` eintragen 🔜 |
| Antwort ist HTML statt JSON | PHP-Fehler vor der API | Server-Log prüfen (XAMPP: `apache/logs/error.log`) |

---

## 13. Kurzreferenz

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

WERKZEUGE                                                             🔜
  php bin/hilfe.php
  php bin/<werkzeug>.php --hilfe
```
