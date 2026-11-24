# MyEndPoint – universelle REST-API

Eine REST-API in PHP, die jede freigegebene Datenbanktabelle per HTTP lesbar, anlegbar, änderbar und löschbar macht. Felder, Pflichtfelder und Verweise liest die API **selbst aus der Datenbank**. Eine neue Spalte braucht keine Codeänderung.

Der Branch `api-2.0` baut diese API zu einem **universellen Gateway** aus: mehrere Ein- und Ausgabeformate (JSON, Formular, CSV, XML …), mehrere Speicher-Backends (MariaDB, MongoDB, Dateien), eine zentrale Konfiguration und **eingebaute Hilfe-Befehle**.

> **Stand:** `api.php` ist lauffähig (Version 1, nur MariaDB und JSON, noch mit Anmeldung). Alles, was mit 🔜 markiert ist, ist geplant und noch nicht umgesetzt. Den Fortschritt zeigt die [Roadmap](#roadmap).
>
> **Für Anwender:** Die Bedienung der API mit allen Hilfe-Befehlen erklärt das [Benutzerhandbuch](HANDBUCH.md).

---

## Inhalt

- [Festgelegte Entscheidungen](#festgelegte-entscheidungen)
- [Schnellstart (XAMPP)](#schnellstart-xampp)
- [Projektinhalt](#projektinhalt)
- [Überblick: Ist-Stand und Ziel](#überblick-ist-stand-und-ziel)
- [Grundidee von 2.0](#grundidee-von-20)
- [API-Referenz](#api-referenz)
- [Hilfe-Befehle](#hilfe-befehle)
- [Konfiguration](#konfiguration)
- [Formate (Codecs)](#formate-codecs)
- [Datenmodell und Verweise](#datenmodell-und-verweise)
- [Speicher-Backends (Treiber)](#speicher-backends-treiber)
- [API-Dokumentation (Swagger)](#api-dokumentation-swagger)
- [Architektur und Umbau](#architektur-und-umbau)
- [Serverauslastung und Anforderungen](#serverauslastung-und-anforderungen)
- [Sicherheit](#sicherheit)
- [Bewusst nicht enthalten](#bewusst-nicht-enthalten)
- [Roadmap](#roadmap)

---

## Festgelegte Entscheidungen

| Thema | Entscheidung | Begründung |
|---|---|---|
| Anmeldung | **keine** | nicht benötigt, die API läuft im geschützten Netz (siehe [Sicherheit](#sicherheit)) |
| Rollen | **keine** | ohne Anmeldung gibt es niemanden, dem eine Rolle zugeordnet wäre |
| Request-Limit (Anfragen pro Zeit) | **vorerst keins** | kann später ergänzt werden |
| VMs / Container | **nicht Teil des Projekts** | die API läuft z. B. in XAMPP, wie sie bereitgestellt wird, ist offen |
| Hilfe | **eingebaute Hilfe-Befehle** (`_hilfe`) + [Benutzerhandbuch](HANDBUCH.md) | die API erklärt sich selbst, immer passend zur Datenbank |
| Datenverarbeitung | **alles in Arrays**, kein Streaming | einfache Schnittstellen, leicht zu testen |
| Backend-Wahl | **nur über die Konfiguration** | ein Client kann nie bestimmen, wo Daten landen |
| Datenstruktur | **nur flache Datensätze**, Verweise als IDs | jedes Format kann jede Zeile abbilden, auch CSV |
| Felder | **automatisch aus der Datenbank**, Config nur für Ausnahmen | so wie `api.php` es heute schon macht |
| Abwärtskompatibilität | `api.php/<ressource>/<id>`, Formulare und `client/index.php` funktionieren weiter | die Aufgabe baut darauf auf |
| Sprache | **Deutsch** für Config, JSON-Schlüssel, Fehlermeldungen und Hilfe, **Englisch** für Klassen und Namespaces (PHP-Konvention) | Clients sehen nur Deutsch, der Code folgt PSR-4 |

---

## Schnellstart (XAMPP)

**Voraussetzung:** PHP 8.1 oder neuer, MariaDB/MySQL, Apache mit `mod_rewrite` (bei XAMPP aktiv).

1. Den Ordner nach `htdocs/MyEndPoint` kopieren.
2. Die Datenbank `fahrzeugverwaltung` mit den Tabellen `fahrzeuge`, `fahrzeugtypen`, `fahrzeugarten`, `treibstoffe` und `fahrzeughalter` anlegen.
   > Ein SQL-Dump dieser Datenbank liegt noch nicht im Repo (siehe [Roadmap](#roadmap)).
3. Ausprobieren:

   ```bash
   curl http://localhost/MyEndPoint/api.php
   curl http://localhost/MyEndPoint/api.php/fahrzeuge
   curl "http://localhost/MyEndPoint/api.php/_hilfe?format=text"      # 🔜
   ```

4. Im Browser:
   - `http://localhost/MyEndPoint/client/`: Formular-Client
   - `http://localhost/MyEndPoint/docs.html`: Swagger-Doku zum Ausprobieren

> **Übergang:** Bis die Anmeldung aus `api.php` entfernt ist (Roadmap Phase 1), braucht die heutige Version noch die Konten-Datenbank aus `benutzer.sql` und bei `curl` zusätzlich `-u email:passwort`.

---

## Projektinhalt

| Datei / Ordner | Inhalt | Status |
|---|---|---|
| `api.php` | Die API (Version 1), eine Datei, prozedural | ✅ läuft (Anmeldung wird entfernt) |
| `.htaccess` | URLs ohne `api.php` | ✅ |
| `client/index.php` | HTML/JS-Client mit `fetch()` | ✅ (Anmeldung wird entfernt) |
| `openapi.yaml` | OpenAPI-3-Beschreibung, Pfade von Hand gepflegt | ✅ (wird ersetzt, siehe [Swagger](#api-dokumentation-swagger)) |
| `docs.html` | Swagger UI, lädt `openapi.yaml` | ✅ |
| `HANDBUCH.md` | Benutzerhandbuch mit allen Hilfe-Befehlen | ✅ |
| `Aufgabe.md` | Aufgabenstellung | – |
| `benutzer.sql` | Konten-Datenbank für die Anmeldung | ❌ entfällt mit der Anmeldung |
| `api_outerlayer/fahrzeug.php` | erster Ansatz für Objekte | ⚠️ ungültiges PHP (`class fahrzeug()`), wird ersetzt |

---

## Überblick: Ist-Stand und Ziel

| Funktion | Version 1 (`api.php`) | Ziel 2.0 |
|---|---|---|
| CRUD über HTTP (GET, POST, PUT, PATCH, DELETE) | ✅ | ✅ |
| Felder automatisch aus der Datenbank | ✅ bei jedem Request | ✅ gecacht 🔜 |
| Fremdschlüssel + Namensregel erkennen | ✅ | ✅ auch über Backends hinweg 🔜 |
| Verweise beim Speichern / Löschen prüfen | ✅ | ✅ |
| Filter `?spalte=wert`, Platzhalter `*` | ✅ | ✅ |
| Filter mit Operatoren (`?Leistung[gt]=100`) | ❌ | 🔜 |
| Sortierung, `limit`, `offset` | ✅ | ✅ |
| HTML-Formulare (`_method`, `operation`, ID als Feld) | ✅ | ✅ |
| Übersicht `GET api.php` | ✅ | ✅ |
| **Hilfe-Befehle** (`_hilfe`, `?_hilfe`, `--hilfe`) | ❌ | 🔜 |
| Anmeldung (Basic Auth) | ✅ | **entfällt** |
| Rollen | ✅ (pro HTTP-Methode) | **entfällt** |
| Aktionen pro Ressource einschränken (z. B. nur lesen) | ❌ | 🔜 optional |
| Eingabe JSON / Formular | ✅ | ✅ |
| Eingabe CSV, XML, YAML | ❌ | 🔜 |
| Ausgabe | nur JSON | JSON + CSV, XML, YAML, SQL, Text (Hilfe) 🔜 |
| MariaDB / MySQL | ✅ | ✅ |
| MongoDB, Dateien als Speicher | ❌ | 🔜 |
| Konfiguration | Konstanten in `api.php` | `config/gateway.php` + `.env` 🔜 |
| Swagger-Doku | statische `openapi.yaml` | automatisch erzeugt (`/_openapi`) 🔜 |
| Aufbau | eine Datei | Klassen unter `src/` 🔜 |

---

## Grundidee von 2.0

### Hub-and-Spoke: alles läuft über den neutralen Datensatz

```
 Eingabe (HTTP)                                           Ausgabe (HTTP)
 JSON ───────┐                                         ┌─→ JSON
 Formular ───┤                                         ├─→ CSV
 CSV ────────┼─→ Codec::decode ─→ [ neutrale Zeilen ] ─┼─→ XML
 XML ────────┤                         │     ▲         ├─→ YAML
 YAML ───────┘                         ▼     │         └─→ SQL (INSERT)
                                    FieldMapper
                                       │     ▲
                                       ▼     │
                         Treiber (MariaDB | MongoDB | Datei)
```

Ein neues Format braucht genau **einen** Codec, ein neues Backend genau **einen** Treiber. Formate und Backends lassen sich frei kombinieren: Daten aus MongoDB können z. B. als CSV ausgegeben werden, ohne dafür eigenen Code zu schreiben.

### Drei getrennte Bausteine

| Baustein | Aufgabe | Weiß nichts über |
|---|---|---|
| **Codec** | Format ↔ neutrale Zeilen | Datenbanken |
| **Treiber** | neutrale Zeilen ↔ Speicher, liefert das Schema | Formate |
| **FieldMapper** | Namen angleichen, Ausnahmen aus der Config, Prüfung, Verweise | Formate, Speicherart |

### Die Datenbank ist das Template

Wie in `api.php` heute: Die API fragt die Datenbank, welche Spalten es gibt. Jeder Treiber bekommt dafür eine Methode `schema()`:

| Treiber | Woher kommt das Schema? |
|---|---|
| PdoDriver (MariaDB …) | aus der Datenbank (`SHOW COLUMNS`, `INFORMATION_SCHEMA`), wie heute `spalten()` |
| FileDriver (CSV …) | Feldnamen aus der Kopfzeile, Typen aus der Config (sonst `string`) |
| MongoDriver | MongoDB ist schemalos, **die Felder stehen vollständig in der Config** |

Die Konfiguration muss deshalb nur Ausnahmen enthalten: versteckte Felder, Umbenennungen, Beschreibungen für die Hilfe. Dasselbe Schema ist auch die Grundlage für die **Hilfe-Befehle** und die **Swagger-Doku**. Beide sind damit automatisch aktuell.

### Ablauf eines Requests

```
 1. Request annehmen          Größe prüfen                         → 413
 2. Router                    api.php/<ressource>/<id?>             → 404
 3. Hilfe?                    _hilfe im Pfad oder ?_hilfe           → Hilfe ausgeben, fertig
 4. Methode bestimmen         echte Methode oder _method/Override   → 400
 5. Aktion erlaubt?           optionale Einschränkung pro Ressource → 405
 6. Codec (Content-Type)      Body → neutrale Zeilen                → 400 / 415
 7. FieldMapper               Namen angleichen, Pflichtfelder,
                              Typen, Verweise prüfen                → 400
 8. Treiber                   lesen / schreiben                     → 404 / 409
 9. FieldMapper (zurück)      versteckte Felder entfernen
10. Codec (Accept)            neutrale Zeilen → Ausgabeformat       → 406
11. Antwort
```

---

## API-Referenz

> Ausführliche Erklärungen mit Beispielen für Anwender: [HANDBUCH.md](HANDBUCH.md).

### Adressen

Alle drei Schreibweisen sind gleichwertig (die beiden kurzen brauchen die `.htaccess`):

```
http://localhost/MyEndPoint/api.php/fahrzeuge/5
http://localhost/MyEndPoint/api/fahrzeuge/5
http://localhost/MyEndPoint/fahrzeuge/5
```

### Endpunkte

| Methode | Pfad | Wirkung |
|---|---|---|
| `GET` | `api.php` | Übersicht: Ressourcen und ihre Felder |
| `GET` | `api.php/<ressource>` | Liste mit Filter, Sortierung, Seiten |
| `GET` | `api.php/<ressource>/<id>` | ein Datensatz |
| `POST` | `api.php/<ressource>` | anlegen → `201` + `Location`-Header |
| `PUT` / `PATCH` | `api.php/<ressource>/<id>` | ändern (nur mitgeschickte Felder) |
| `DELETE` | `api.php/<ressource>/<id>` | löschen |
| `OPTIONS` | beliebig | CORS-Vorabanfrage → `204` |
| `GET` | `api.php/_hilfe[/…]` | Hilfe-Befehle 🔜 (siehe [Hilfe-Befehle](#hilfe-befehle)) |
| `GET` | `api.php/_openapi` | OpenAPI-Beschreibung, automatisch erzeugt 🔜 |
| `POST` | `api.php/_convert` | Format umwandeln, nichts speichern 🔜 |

Freigegebene Ressourcen: `fahrzeuge`, `fahrzeugtypen`, `fahrzeugarten`, `treibstoffe`, `fahrzeughalter`.

Namen, die mit `_` beginnen, sind für Systemfunktionen reserviert und können keine Ressource sein.

### HTML-Formulare

Formulare kennen nur `GET` und `POST`. Deshalb wird die gewünschte Methode mitgeschickt:

| Weg | Beispiel |
|---|---|
| verstecktes Feld `_method` | `<input type="hidden" name="_method" value="DELETE">` |
| altes Feld `operation` | `GET`, `POST`, `UPDATE` (= `PUT`), `DELETE` |
| Header | `X-HTTP-Method-Override: PATCH` |

Steht keine ID in der URL, wird sie aus dem Feld gelesen, das wie der Primärschlüssel heißt (z. B. `FID`). Felder, die mit `_` beginnen, werden nie gespeichert.

### Listen: Filter, Sortierung, Seiten

| Parameter | Beispiel | Wirkung | Status |
|---|---|---|---|
| `<spalte>=<wert>` | `?FTID=3` | gleich, mehrere Filter mit UND | ✅ |
| `*` als Platzhalter | `?Marke=VW*` | `LIKE 'VW%'` | ✅ |
| `sort` | `?sort=Marke,-Leistung` | sortieren, `-` = absteigend | ✅ |
| `limit` | `?limit=20` | Seitengröße (Standard 100, max. 1000) | ✅ |
| `offset` | `?offset=40` | Startposition | ✅ |
| `<spalte>[op]=<wert>` | `?Leistung[gt]=100` | mit Operator: `eq`, `ne`, `lt`, `lte`, `gt`, `gte`, `in`, `null` | 🔜 |
| `fields` | `?fields=FID,Marke` | nur diese Felder ausgeben | 🔜 |
| `format` | `?format=csv` | Ausgabeformat statt `Accept`-Header | 🔜 |
| `_hilfe` | `?_hilfe` | Hilfe zu dieser Adresse statt Ausführung | 🔜 |

Groß-/Kleinschreibung der Spaltennamen ist egal (`fid` = `FID`). Leere Filterwerte werden ignoriert. Unbekannte Spalten führen zu `400` mit der Liste der erlaubten Felder.

### Antworten

**Liste**: Bei JSON mit Hülle (so liest es `client/index.php`):

```json
{
  "daten":  [ { "FID": 1, "Marke": "VW", "FTID": 3 } ],
  "gesamt": 42,
  "limit":  100,
  "offset": 0
}
```

🔜 Zusätzlich stehen die Seitenangaben **immer** in den Headern (`X-Total-Count`, `X-Limit`, `X-Offset`), damit sie auch bei CSV und XML verfügbar sind. Diese Formate enthalten nur die Zeilen.

**Einzelner Datensatz, Anlegen, Ändern**: der Datensatz so, wie er jetzt in der Datenbank steht. Beim Anlegen zusätzlich `201 Created` und `Location: …/api.php/fahrzeuge/43`.

**Löschen**:

```json
{ "meldung": "Datensatz \"43\" wurde gelöscht.", "geloescht": { "FID": 43, "Marke": "VW" } }
```

**Übersicht** (`GET api.php`). Der Block `angemeldet` entfällt mit der Anmeldung:

```json
{
  "hilfe": "http://localhost/MyEndPoint/api.php/_hilfe",
  "ressourcen": {
    "fahrzeuge": {
      "url": "http://localhost/MyEndPoint/api.php/fahrzeuge",
      "felder": {
        "FID":  { "typ": "int(11)", "pk": true,  "auto": true,  "null": false, "pflicht": false, "verweis": null },
        "FTID": { "typ": "int(11)", "pk": false, "auto": false, "null": false, "pflicht": true,
                  "verweis": { "ressource": "fahrzeugtypen", "spalte": "FTID" } }
      }
    }
  }
}
```

### Fehler

Fehler kommen immer als JSON, auch wenn ein anderes Ausgabeformat angefragt wurde. 🔜 Jeder Fehler enthält einen Link zur passenden Hilfe:

```json
{
  "fehler": "Ungültige Eingaben.",
  "details": ["Pflichtfeld \"Marke\" fehlt."],
  "hilfe": "http://localhost/MyEndPoint/api.php/_hilfe/fahrzeuge"
}
```

| Status | Wann |
|---|---|
| `400` | ungültige Eingabe oder Parameter, Pflichtfeld fehlt, Verweis zeigt ins Leere |
| `404` | Ressource oder Datensatz unbekannt |
| `405` | Methode passt nicht zum Pfad, oder Aktion für diese Ressource abgeschaltet |
| `406` | Ausgabeformat nicht verfügbar 🔜 |
| `409` | doppelter Wert, oder Datensatz wird noch verwendet |
| `413` | Anfrage zu groß 🔜 |
| `415` | Eingabeformat nicht verfügbar 🔜 |
| `500` | interner Fehler |

`401` und `403` gibt es nicht mehr, sobald die Anmeldung entfernt ist.

Datenbankfehler, die der Client verursacht hat, werden übersetzt (heute `MYSQL_FEHLER`, künftig im jeweiligen Treiber):

| MySQL-Fehler | Status | Meldung |
|---|---|---|
| 1048 | 400 | Ein Pflichtfeld ist leer. |
| 1062 | 409 | Ein Datensatz mit diesem Wert existiert bereits. |
| 1264 | 400 | Eine Zahl liegt außerhalb des erlaubten Bereichs. |
| 1265, 1366 | 400 | Ein Wert passt nicht zum Spaltentyp. |
| 1292 | 400 | Ein Datum oder eine Uhrzeit ist ungültig. |
| 1406 | 400 | Ein Text ist zu lang. |
| 1451 | 409 | Der Datensatz wird noch von einer anderen Tabelle verwendet. |
| 1452 | 400 | Ein Verweis zeigt auf einen Datensatz, den es nicht gibt. |

`details` enthält technische Meldungen (z. B. den SQL-Fehler) **nur im Debug-Modus**.

---

## Hilfe-Befehle

Die API erklärt sich selbst. Die Hilfe wird aus **Schema und Config** erzeugt und stimmt deshalb immer mit der Datenbank überein. Die Bedienung für Anwender beschreibt das [Benutzerhandbuch, Abschnitt 2](HANDBUCH.md#2-hilfe-befehle).

### HTTP-Hilfe

| Befehl | Inhalt |
|---|---|
| `GET api.php/_hilfe` | Gesamthilfe: alle Ressourcen, Aktionen, Parameter, Formate |
| `GET api.php/_hilfe/<ressource>` | Felder mit Typ, Pflicht, Verweis, dazu Beispiele mit den echten Feldnamen |
| `GET api.php/_hilfe/filter` | alle Filter-, Sortier- und Seitenparameter |
| `GET api.php/_hilfe/formate` | alle Ein- und Ausgabeformate, die auf diesem Server verfügbar sind |
| `GET api.php/_hilfe/fehler` | alle Statuscodes und Fehlermeldungen mit Erklärung |
| `<beliebige Adresse>?_hilfe` | Hilfe zu genau dieser Adresse. **Es wird nichts ausgeführt**, auch nicht bei `POST`/`DELETE`. |

- `_help` ist ein Alias für `_hilfe`.
- **Ausgabe:** JSON (Standard, für Programme) oder lesbarer Text mit `?format=text` bzw. `Accept: text/plain`. Browser bekommen automatisch Text.
- **Fehlermeldungen** verweisen im Feld `hilfe` auf die passende Hilfeseite.
- `?_hilfe` passt zur bestehenden Regel, dass Parameter mit `_` Steuerfelder sind.

Beispiel (Text):

```text
$ curl "http://localhost/MyEndPoint/api.php/_hilfe/fahrzeuge?format=text"

FAHRZEUGE
  Alle Fahrzeuge mit Typ, Art, Treibstoff und Halter.
  http://localhost/MyEndPoint/api.php/fahrzeuge

AKTIONEN
  GET     /fahrzeuge              Liste (Filter, Sortierung, Seiten)
  GET     /fahrzeuge/{FID}        ein Fahrzeug
  POST    /fahrzeuge              anlegen
  PATCH   /fahrzeuge/{FID}        ändern (nur mitgeschickte Felder)
  DELETE  /fahrzeuge/{FID}        löschen

FELDER
  FID        int           automatisch vergeben (Primärschlüssel)
  Marke      varchar(50)   Pflicht
  FTID       int           Pflicht   → Verweis auf fahrzeugtypen

BEISPIELE
  GET  /fahrzeuge?Marke=VW*&sort=-FID
  POST /fahrzeuge   {"Marke": "…", "FTID": 1}
```

### Beschreibungstexte

Die Hilfe kommt ohne Config aus. Optional lassen sich Ressourcen und Felder beschreiben:

```php
'fahrzeuge' => [
    'verbindung'   => 'fahrzeuge',
    'quelle'       => 'fahrzeuge',
    'beschreibung' => 'Alle Fahrzeuge mit Typ, Art, Treibstoff und Halter.',
    'felder' => [
        'Leistung' => ['beschreibung' => 'Leistung in kW'],
    ],
],
```

Alternativ werden Kommentare aus der Datenbank übernommen (`COMMENT` an Tabelle und Spalte, auslesbar über `INFORMATION_SCHEMA`). Dann steht die Beschreibung direkt in der Datenbank und gilt für jedes Werkzeug.

### Kommandozeilen-Hilfe

Jedes Werkzeug in `bin/` zeigt mit `--hilfe` (oder `--help`, `-h`) Aufruf, Optionen und ein Beispiel. `php bin/hilfe.php` listet alle Werkzeuge auf. Siehe [Benutzerhandbuch, Abschnitt 11](HANDBUCH.md#11-kommandozeilen-werkzeuge).

---

## Konfiguration

### Heute: Konstanten in `api.php`

| Konstante | Bedeutung | in 2.0 |
|---|---|---|
| `DB2_DSN`, `DB2_USER`, `DB2_PASS` | Verbindung zur Fahrzeug-Datenbank | → `verbindungen` + `.env` |
| `RESSOURCEN` | Whitelist: Name in der URL → Tabelle | → `ressourcen` |
| `LIMIT_STANDARD`, `LIMIT_MAXIMUM` | Seitengröße 100 / höchstens 1000 | → `grenzen` |
| `API_DEBUG` | genaue Fehlermeldungen in der Antwort | → `debug` (Standard: aus) |
| `MYSQL_FEHLER` | MySQL-Fehlernummer → Status + Meldung | → in den PdoDriver |
| `AUTH_DSN`, `AUTH_USER`, `AUTH_PASS`, `TBL_BENUTZER` | Konten-Datenbank | **entfällt** |
| `ROLLEN` | Rolle → erlaubte HTTP-Methoden | **entfällt** |

### 🔜 Künftig: `config/gateway.php` + `.env`

Die Konfiguration wird ein PHP-Array. OPcache hält es im Speicher, es muss also nicht bei jedem Request geparst werden. Zugangsdaten zur Datenbank stehen nicht darin, sondern in `config/.env`.

```php
<?php
// config/gateway.php
return [

    'debug' => getenv('API_DEBUG') === '1',      // Standard: aus

    'grenzen' => [
        'maxAnfrageBytes' => 5 * 1024 * 1024,     // größer → 413 (schützt den Arbeitsspeicher)
        'maxAnfrageBytesProFormat' => ['xlsx' => 1 * 1024 * 1024],
        'limitStandard'   => 100,
        'limitMaximum'    => 1000,
    ],

    'cors' => [
        'erlaubteUrspruenge' => ['http://localhost', 'http://localhost:5173'],
    ],

    'verbindungen' => [
        'fahrzeuge' => [
            'treiber' => 'pdo',
            'dsn'     => 'mysql:host=127.0.0.1;dbname=fahrzeugverwaltung;charset=utf8mb4',
            'user'    => getenv('DB_USER'),
            'pass'    => getenv('DB_PASS'),
        ],
        'archiv' => [
            'treiber'   => 'mongo',
            'uri'       => getenv('MONGO_URI'),
            'datenbank' => 'fahrzeugarchiv',
        ],
    ],

    'ressourcen' => [
        // Felder kommen automatisch aus der Datenbank
        'fahrzeuge' => [
            'verbindung'   => 'fahrzeuge',
            'quelle'       => 'fahrzeuge',
            'beschreibung' => 'Alle Fahrzeuge mit Typ, Art, Treibstoff und Halter.',
        ],
        'fahrzeugtypen' => ['verbindung' => 'fahrzeuge', 'quelle' => 'fahrzeugtypen'],
        'fahrzeugarten' => ['verbindung' => 'fahrzeuge', 'quelle' => 'fahrzeugarten'],

        // Stammdaten nur lesbar machen (optional)
        'treibstoffe' => [
            'verbindung' => 'fahrzeuge',
            'quelle'     => 'treibstoffe',
            'aktionen'   => 'r',
        ],

        // Personendaten: einzelne Felder verbergen (optional)
        'fahrzeughalter' => [
            'verbindung' => 'fahrzeuge',
            'quelle'     => 'fahrzeughalter',
            'felder'     => ['Strasse' => ['versteckt' => true]],
        ],

        // MongoDB hat kein Schema → Felder vollständig angeben
        'wartungen' => [
            'verbindung' => 'archiv',
            'quelle'     => 'wartungen',
            'felder' => [
                'id'    => ['spalte' => '_id', 'typ' => 'string', 'nurLesen' => true, 'pk' => true],
                'FID'   => ['typ' => 'int', 'pflicht' => true],
                'datum' => ['typ' => 'datetime', 'pflicht' => true],
                'notiz' => ['typ' => 'string'],
            ],
            'verweise' => ['FID' => 'fahrzeuge'],   // Verweis über Backend-Grenzen
        ],
    ],
];
```

```dotenv
# config/.env – NICHT ins Git
DB_USER=api
DB_PASS=...
MONGO_URI=mongodb://127.0.0.1:27017
API_DEBUG=0
```

> Die Spalte `Strasse`, die Einschränkung bei `treibstoffe` und die Ressource `wartungen` zeigen, was die Config kann. Sie sind nicht Teil der heutigen Datenbank bzw. Konfiguration.

### Ressourcen-Optionen

| Option | Pflicht | Bedeutung |
|---|---|---|
| `verbindung` | ja | Name aus `verbindungen` |
| `quelle` | ja | Tabelle, Collection oder Dateiname |
| `felder` | bei MongoDB | Ausnahmen bzw. vollständige Feldliste (siehe unten) |
| `aktionen` | nein | erlaubte Aktionen als Buchstaben: `c` anlegen, `r` lesen, `u` ändern, `d` löschen. Standard: `crud` (alles). Gilt für **alle** Aufrufer, es ist keine Rolle. Nicht erlaubt → `405`. |
| `beschreibung` | nein | Text für die Hilfe-Befehle und Swagger |
| `verweise` | nein | `Feld => Ressource`, für Verweise ohne Fremdschlüssel |
| `filterbar` | nein | nur diese Felder als Filter erlauben (Standard: alle sichtbaren) |
| `sortierbar` | nein | nur diese Felder zum Sortieren erlauben (Standard: alle sichtbaren) |

### Feld-Optionen

Bei SQL-Tabellen werden alle Felder automatisch erkannt. Hier wird nur eingetragen, was davon **abweicht**:

| Option | Bedeutung |
|---|---|
| `versteckt` | Feld wird weder ausgegeben noch angenommen, erscheint auch nicht in der Hilfe |
| `nurLesen` | wird ausgegeben, kann aber nicht geschrieben werden |
| `nurSchreiben` | kann geschrieben werden, wird **nie** ausgegeben |
| `name` | anderer Name in der API (`'Fahrzeug_Name' => ['name' => 'name']`) |
| `aliase` | weitere erlaubte Eingabenamen, z. B. für CSV-Kopfzeilen aus Excel |
| `spalte` | echter Spaltenname, wenn er vom API-Namen abweicht (MongoDB: `_id`) |
| `typ` | `int`, `float`, `decimal`, `string`, `bool`, `datetime` |
| `beschreibung` | Text für die Hilfe-Befehle und Swagger |
| `pflicht`, `pk`, `min`, `max` | Prüfregeln, bei SQL automatisch aus der Datenbank |

### Prüfung beim Start

Die Konfiguration wird geprüft, bevor der erste Request bearbeitet wird:

- Jede Ressource verweist auf eine existierende Verbindung, deren Treiber installiert ist.
- `aktionen` besteht nur aus `c`, `r`, `u`, `d`.
- Verweise zeigen auf existierende Ressourcen.
- Kein Ressourcenname beginnt mit `_` (reserviert für `_hilfe`, `_openapi` …).

Ist etwas falsch, antwortet die API mit `500` und schreibt den Grund ins Log. Manuell prüfen: `php bin/config-pruefen.php` 🔜.

---

## Formate (Codecs)

Das Eingabeformat bestimmt der `Content-Type`, das Ausgabeformat der `Accept`-Header (oder `?format=`). Ohne Angabe wird JSON ausgegeben.

| Format | MIME-Type | Eingabe | Ausgabe | Abhängigkeit |
|---|---|---|---|---|
| JSON | `application/json` | ✅ | ✅ | – |
| Formular | `application/x-www-form-urlencoded`, `multipart/form-data` | ✅ | – | – |
| CSV | `text/csv` | 🔜 | 🔜 | – |
| TSV | `text/tab-separated-values` | 🔜 | 🔜 | – |
| XML | `application/xml` | 🔜 | 🔜 | – |
| YAML | `application/x-yaml` | 🔜 | 🔜 | `symfony/yaml` |
| XLSX | `application/vnd.openxmlformats-officedocument.spreadsheetml.sheet` | 🔜 | 🔜 | `phpoffice/phpspreadsheet` |
| SQL | `application/sql` | ❌ nie | 🔜 | – |
| Text | `text/plain` | – | 🔜 nur für Hilfe-Befehle | – |

Jeder Codec setzt dieselbe Schnittstelle um:

```php
interface Codec
{
    public function mimeTypes(): array;
    public function decode(string $body): array;   // → Liste von Zeilen
    public function encode(array $rows): string;
}
```

Hinweise zu den Formaten:

- **JSON**: heute ein einzelnes Objekt. 🔜 Künftig auch eine Liste von Objekten, die in einer Transaktion angelegt wird (alle oder keiner).
- **Formular**: wie heute. Steuerfelder (`_method`, `_hilfe` …) werden entfernt, bevor gespeichert wird.
- **CSV**: Kopfzeile = Feldnamen. Bei der Eingabe wird das Trennzeichen erkannt (`;`, `,`, Tab) und ein BOM entfernt. Die Ausgabe nutzt `;` mit BOM, damit deutsches Excel die Datei direkt öffnet. **Eine leere Zelle bedeutet `NULL`.**
- **XML**: `<datensaetze><datensatz><Feld>Wert</Feld></datensatz></datensaetze>`, nur Elemente, keine Attribute. Ein leeres Element bedeutet `NULL`, externe Entities sind abgeschaltet.
- **SQL**: nur als Ausgabe (`INSERT`-Statements für Export oder Testdaten). SQL wird **nie** als Eingabe angenommen.

### Objekte („Obj“)

Statt einer handgeschriebenen Klasse pro Tabelle (wie `api_outerlayer/fahrzeug.php`) gibt es zwei Wege:

- **Generisch**: eine Klasse `Record` für jede Ressource: `$fahrzeug->get('Marke')`.
- **Generiert** 🔜: `php bin/klassen-erzeugen.php` erzeugt aus dem Schema typisierte Klassen (`Fahrzeug`, `Fahrzeugtyp` …) mit Autovervollständigung in der IDE. Ändert sich eine Tabelle, wird einfach neu erzeugt.

---

## Datenmodell und Verweise

### Nur flache Datensätze

Ein Datensatz ist ein einfaches Schlüssel-Wert-Array. Erlaubt sind nur `int`, `float`, `string`, `bool` und `null`. Listen oder Objekte als Feldwert werden abgelehnt:

```json
{ "fehler": "Ungültige Eingaben.", "details": ["Feld \"Ausstattung\" muss ein einzelner Wert sein, keine Liste."] }
```

### Typen

| Typ | MariaDB | MongoDB | JSON-Ausgabe | CSV / XML |
|---|---|---|---|---|
| `int` | INT, BIGINT | int32/int64 | `42` | `42` |
| `float` | DOUBLE, FLOAT | double | `3.5` | `3.5` |
| `decimal` | DECIMAL | Decimal128 | `"12.50"` (String, keine Rundungsfehler) | `12.50` |
| `string` | VARCHAR, TEXT | string | `"Golf"` | `Golf` |
| `bool` | TINYINT(1) | bool | `1` / `0` | `1` / `0` |
| `datetime` | DATE, DATETIME | Date | `"2026-11-23 10:00:00"` | wie JSON |

Bei der Eingabe wird `true`/`false` zu `1`/`0`, ein leeres Feld zu `NULL` (wenn die Spalte das erlaubt), und Texte werden getrimmt. 🔜 Aus CSV und Formularen wird außerdem das deutsche Dezimalkomma akzeptiert (`"4,2"` → `4.2`).

### Verweise

Beziehungen werden nur als **ID** abgebildet: Ein Fahrzeug hat `"FTID": 3`, nicht das ganze Fahrzeugtyp-Objekt. Wer den Typ braucht, fragt `GET api.php/fahrzeugtypen/3`.

Verweise werden auf drei Wegen erkannt:

1. **Echte Fremdschlüssel** in der Datenbank (`INFORMATION_SCHEMA`).
2. **Namensregel**: Eine Spalte, die so heißt wie der Primärschlüssel einer anderen Tabelle, verweist auf diese (`fahrzeuge.FTID → fahrzeugtypen.FTID`). Heißen zwei Primärschlüssel gleich (z. B. beide `id`), gilt die Regel für sie nicht.
3. 🔜 **Config** (`'verweise' => ['FID' => 'fahrzeuge']`): für Backends ohne Fremdschlüssel und für Verweise **über Backend-Grenzen hinweg**, z. B. MongoDB → MariaDB.

Die API prüft dabei selbst:

- **Beim Speichern**, ob es den Eintrag gibt, auf den verwiesen wird. Sonst `400`.
- **Beim Löschen**, ob der Datensatz noch verwendet wird. Sonst `409` mit Ressource, Spalte und Anzahl.

🔜 Die Prüfung läuft künftig über den Treiber der Zielressource statt direkt per SQL. Dadurch funktioniert sie für jedes Backend.

---

## Speicher-Backends (Treiber)

Jeder Treiber setzt dieselbe Schnittstelle um:

```php
interface Driver
{
    public function schema(): array;                         // Felder, Typen, PK, Verweise, Kommentare
    public function find(Query $query): array;               // ['daten' => [...], 'gesamt' => n]
    public function get(string $id): ?array;
    public function create(array $row): array;               // gespeicherter Datensatz inkl. ID
    public function update(string $id, array $row): array;
    public function delete(string $id): bool;
    public function count(array $filter): int;               // für die Verweis-Prüfung
}
```

### PdoDriver: MariaDB, MySQL (✅ heute in `api.php`), PostgreSQL, SQLite (🔜)

- Werte immer über Prepared Statements, `ATTR_EMULATE_PREPARES` ist aus.
- Tabellen- und Spaltennamen kommen **nur** aus der Config bzw. dem Schema und werden zusätzlich in Backticks gesetzt.
- Bei Zahlen-Primärschlüsseln werden nur Ziffern als ID angenommen (MySQL würde `5abc` sonst als `5` lesen).
- Ohne `sort` wird nach dem Primärschlüssel sortiert, damit `limit`/`offset` stabil bleiben.
- Datenbankfehler werden übersetzt (siehe [Fehler](#fehler)).
- 🔜 **Das Schema wird gecacht.** Heute laufen bei jedem Request `SHOW COLUMNS` und die `INFORMATION_SCHEMA`-Abfragen, beim Löschen sogar für alle Tabellen. Künftig wird das Schema einmal in `cache/schema.php` geschrieben und von OPcache gehalten. Neu erzeugt wird über `php bin/schema-aktualisieren.php` oder automatisch nach einer einstellbaren Zeit.

### 🔜 MongoDriver

- Filter werden übersetzt: `gt` → `$gt`, `*` → `$regex` (mit maskierten Sonderzeichen), `in` → `$in`.
- `_id` wird über `'spalte' => '_id'` auf ein API-Feld abgebildet, ObjectIds werden als String ausgegeben.
- Nur Felder der obersten Ebene. Verschachtelte Felder in vorhandenen Dokumenten werden ignoriert.
- Fehler 11000 (doppelter Schlüssel) → `409`.
- Benötigt die PHP-Extension `mongodb` und das Paket `mongodb/mongodb`.

### 🔜 FileDriver (CSV, XML, JSON als Speicher)

- Eine Datei pro Ressource, gelesen und geschrieben über dieselben Codecs wie die HTTP-Schicht.
- **Atomares Schreiben**: Datei mit `flock()` sperren, in eine Temp-Datei schreiben, per `rename()` ersetzen.
- Gut für kleine Datenmengen (bis einige tausend Zeilen), nicht für viele gleichzeitige Schreibzugriffe.

### Backend wechseln

1. Neue Verbindung in `verbindungen` eintragen.
2. Bei der Ressource `verbindung` ändern (bei MongoDB zusätzlich `felder`).
3. Daten umziehen (🔜 `php bin/umziehen.php fahrzeuge --nach=archiv`).

Für Clients ändert sich nichts: gleiche URLs, gleiche Feldnamen, gleiche Formate.

---

## API-Dokumentation (Swagger)

**Heute** beschreibt `openapi.yaml` jeden Pfad von Hand (`/fahrzeuge/{FID}`, `/fahrzeugtypen/{FTID}` …) und enthält noch das Anmeldeschema `BasicAuth`. `docs.html` zeigt sie mit Swagger UI an. Kommt eine Spalte oder Tabelle dazu, muss die Datei mitgepflegt werden, sonst stimmt die Doku nicht mehr.

**🔜 Künftig** erzeugt die API die Beschreibung selbst, aus demselben Schema wie die Hilfe-Befehle:

```
GET api.php/_openapi   → OpenAPI 3 als JSON
```

- `docs.html` lädt diese URL statt der statischen Datei.
- Die erklärenden Texte aus der heutigen `openapi.yaml` (URL-Aufbau, Formulare) bleiben als Vorlage erhalten. Nur Pfade, Felder und Typen werden generiert.
- `beschreibung` aus der Config bzw. Datenbank-Kommentare erscheinen auch hier.
- Ohne Anmeldung entfällt der „Authorize“-Schritt.

---

## Architektur und Umbau

Die bestehende `api.php` wird **nicht neu geschrieben**, sondern Schritt für Schritt in Klassen zerlegt, während sie weiterläuft. `client/index.php` und Swagger dienen dabei als Test, ob noch alles funktioniert. Am Ende ist `api.php` nur noch ein Einstiegspunkt, der den Kernel startet.

### Von der Funktion zur Klasse

| Heute in `api.php` | Wird zu |
|---|---|
| `antwort()`, `fehler()`, `exceptionBehandeln()` | `Http\Response`, `Http\ErrorHandler` |
| Routing (Abschnitt 6b) | `Http\Router` |
| Methode und Override (6c), `eingabeLesen()` | `Http\Request` |
| `zugangsdaten()`, `anmelden()`, `authDb()`, Prüfung in 6d | **entfällt** |
| `ROLLEN` | **entfällt** (optional `aktionen` pro Ressource → `Http\ActionGuard`) |
| `eingabeLesen()` (JSON/Formular) | `Codec\JsonCodec`, `Codec\FormCodec` |
| `db()`, `spalten()`, `primaerschluessel()`, `primaerschluesselNamen()` | `Driver\PdoDriver::schema()` + `Schema\SchemaCache` |
| `holen()`, `liste()`, `anlegen()`, `aendern()`, `loeschen()` | `Driver\PdoDriver::get/find/create/update/delete` |
| `namenAngleichen()`, `spalteFinden()`, `felderPruefen()`, `ohneSteuerfelder()` | `Mapping\FieldMapper` |
| Verweis-Prüfung, `verwendungPruefen()` | `Mapping\ReferenceChecker` |
| `MYSQL_FEHLER` | Fehlerübersetzung im `PdoDriver` |
| Übersicht (6e) | `Http\Controller\OverviewController` |
| – (neu) | `Help\HelpController`, `Help\HelpBuilder`, `Help\TextRenderer` |

### Ziel-Ordnerstruktur

```
MyEndPoint/
├─ api.php                    ← Einstiegspunkt (bleibt, für alte URLs)
├─ .htaccess
├─ README.md
├─ HANDBUCH.md                ← Benutzerhandbuch
├─ config/
│   ├─ gateway.php
│   ├─ .env                   ← nicht im Git
│   └─ .env.example
├─ bin/                       ← Kommandozeilen-Werkzeuge, alle mit --hilfe
│   ├─ hilfe.php
│   ├─ config-pruefen.php
│   ├─ schema-aktualisieren.php
│   ├─ klassen-erzeugen.php
│   └─ umziehen.php
├─ cache/                     ← erzeugtes Schema, nicht im Git
├─ src/
│   ├─ Http/                  ← Kernel, Router, Request, Response, ErrorHandler, ActionGuard, Controller/
│   ├─ Help/                  ← HelpController, HelpBuilder, TextRenderer
│   ├─ Config/                ← ConfigLoader, ConfigValidator
│   ├─ Codec/                 ← Codec, CodecRegistry, JsonCodec, FormCodec, CsvCodec, XmlCodec, …
│   ├─ Driver/                ← Driver, DriverFactory, PdoDriver, MongoDriver, FileDriver
│   ├─ Schema/                ← SchemaCache
│   ├─ Mapping/               ← FieldMapper, TypeCaster, ReferenceChecker, Record
│   ├─ Query/                 ← Query, QueryParser
│   └─ Cli/                   ← gemeinsame Hilfe-Ausgabe (--hilfe) für bin/
├─ client/index.php
├─ docs.html
├─ tests/
└─ composer.json              ← Autoloading (PSR-4) + optionale Pakete
```

---

## Serverauslastung und Anforderungen

> **Alle Zahlen sind Schätzungen** aus dem Aufbau des Codes und typischen Werten von PHP und MariaDB. Messwerte ersetzen sie, sobald ein Lasttest gelaufen ist (siehe [Messen](#messen-statt-schätzen)).

### Was ein Request kostet

Mit dem Wegfall der Anmeldung entfällt auch die bcrypt-Prüfung (`password_verify()`, grob 50–100 ms pro Request), bisher der mit Abstand größte Kostenpunkt. Übrig bleiben:

| Arbeitsschritt | Kosten (geschätzt) | Bemerkung |
|---|---|---|
| Routing, Prüfung, Mapping | < 1 ms | vernachlässigbar |
| Schema auslesen (heute bei jedem Request) | einige bis mehrere zehn ms | beim `DELETE` für alle Tabellen. **Entfällt mit dem Schema-Cache 🔜.** |
| Datenbankabfrage | 1–10 ms | mit passenden Indizes |
| JSON erzeugen | < 1 ms bis wenige ms | je nach Seitengröße |
| XML / YAML / XLSX erzeugen 🔜 | deutlich mehr als JSON | XLSX am teuersten |
| Hilfe-Befehle 🔜 | wie eine Übersicht | nutzen den Schema-Cache |

| Ausbaustufe | Durchsatz auf 2 vCPU (grob) |
|---|---|
| heute ohne Anmeldung, ohne Schema-Cache | 30–100 Requests/s |
| mit Schema-Cache | 100–300 Requests/s, dann begrenzt durch die Datenbank |

### Speicherverbrauch pro Request

Weil alles in Arrays verarbeitet wird, liegen die Daten zeitweise mehrfach im Speicher (Roh-Body → Array → geprüftes Array → Ausgabe). Faustregel: **Daten im PHP-Array brauchen etwa das 5- bis 10-Fache ihrer Dateigröße.**

| Request | Geschätzter Peak-RAM pro PHP-Prozess |
|---|---|
| `GET` einzelner Datensatz | 2–5 MB |
| `GET` Liste mit 100 Zeilen (Standard) | 3–8 MB |
| `GET` Liste mit 1000 Zeilen (Maximum) | 10–25 MB |
| 🔜 `POST` mit 5 MB CSV/JSON | 40–80 MB |
| 🔜 `POST` mit 1 MB XLSX | 50–150 MB (PhpSpreadsheet: grob 1 KB pro Zelle) |

Deshalb bleibt die **Größenbegrenzung** für Anfragen (`maxAnfrageBytes`) erhalten. Sie schützt den Arbeitsspeicher und ist kein Request-Limit im Sinne von „Anfragen pro Zeit“.

### Empfohlene Servergrößen

| Einsatz | Nutzer gleichzeitig | CPU | RAM | Speicher |
|---|---|---|---|---|
| Entwicklung / Ausbildung (XAMPP) | < 10 | 1–2 Kerne | 2 GB | 10 GB |
| Interner Betrieb, nur MariaDB | 50–200 | 2 vCPU | 2–4 GB | 20 GB SSD |
| mit MongoDB auf demselben Server | wie oben | +1 vCPU | +1–2 GB | +20 GB |

**MongoDB begrenzen:** Standardmäßig nimmt sich MongoDB als Cache die Hälfte von (RAM − 1 GB). Auf einem gemeinsamen Server in `mongod.conf` begrenzen:

```yaml
storage:
  wiredTiger:
    engineConfig:
      cacheSizeGB: 1
```

### PHP-Einstellungen

```ini
memory_limit        = 256M
post_max_size       = 8M      ; etwas über maxAnfrageBytes
upload_max_filesize = 8M
max_execution_time  = 30
display_errors      = Off     ; außerhalb der Entwicklung

opcache.enable              = 1
opcache.memory_consumption  = 128
```

Bei PHP-FPM gilt: `pm.max_children ≈ RAM für PHP ÷ durchschnittlicher Speicher pro Prozess` (grob 40–60 MB). Bei 4 GB RAM mit MariaDB auf demselben Server sind etwa 10–12 Prozesse sinnvoll.

### Typische Engpässe

1. **Schema bei jedem Request:** Schema-Cache einbauen (Roadmap Phase 2).
2. **Fehlende Indizes:** Jede Spalte, nach der oft gefiltert oder sortiert wird, braucht einen Index. Fremdschlüssel-Spalten (`FTID` …) sowieso, weil die Lösch-Prüfung danach sucht.
3. **Große Imports:** mehrere 5-MB-Uploads gleichzeitig verdoppeln kurzzeitig den RAM-Bedarf.
4. **`limit=1000` in XML/XLSX:** kostet ein Vielfaches von 100 Zeilen JSON.

### Messen statt schätzen

🔜 Mit `API_DEBUG=1` hängt die API an jede Antwort echte Messwerte:

```http
X-Debug-Memory: 4.1 MB
X-Debug-Time: 12.3 ms
```

Lasttest, z. B. mit Apache Bench (bei XAMPP unter `apache/bin/ab`):

```bash
ab -n 1000 -c 20 "http://localhost/MyEndPoint/api.php/fahrzeuge?limit=50"
```

| Messwert | Zielwert (interner Betrieb) |
|---|---|
| Antwortzeit, 95. Perzentil | < 200 ms für normale Listen |
| RAM der PHP-Prozesse | stabil, kein stetiges Wachstum |
| langsame SQL-Abfragen (`slow_query_log`) | keine über 100 ms |

---

## Sicherheit

### Ohne Anmeldung: Schutz über das Netz

Ohne Anmeldung und Rollen kann **jeder, der die Adresse erreicht, alle Daten lesen, ändern und löschen**. Das ist eine bewusste Entscheidung. Der Schutz muss deshalb außerhalb der API liegen:

- [ ] **Nur im lokalen bzw. internen Netz betreiben**, nie direkt aus dem Internet erreichbar.
- [ ] Zugriff auf bestimmte Rechner beschränken, z. B. in der `.htaccess`:

  ```apache
  # nur dieser Rechner und das interne Netz
  Require ip 127.0.0.1 ::1 192.168.0.0/16
  ```

- [ ] **Stammdaten nur lesbar machen**, wo niemand etwas ändern soll: `'aktionen' => 'r'` 🔜.
- [ ] **Personendaten prüfen** (`fahrzeughalter`): Was nicht gebraucht wird, mit `'versteckt' => true` ausblenden 🔜.
- [ ] **Regelmäßige Backups** der Datenbank. Jeder Aufrufer kann löschen, der Lösch-Response enthält zwar den gelöschten Datensatz, ersetzt aber kein Backup.
- [ ] `/_hilfe` und `/_openapi` legen die Struktur aller Tabellen offen. Im geschützten Netz ist das gewollt, öffentlich wäre es ein Problem.

### Was `api.php` schon richtig macht

| Risiko | Schutz |
|---|---|
| SQL-Injection über Werte | Prepared Statements, `ATTR_EMULATE_PREPARES = false` |
| SQL-Injection über Tabellen-/Spaltennamen | nur aus `RESSOURCEN` bzw. dem Schema, zusätzlich in Backticks |
| Zugriff auf beliebige Tabellen | Whitelist `RESSOURCEN` |
| Datensätze mit kaputten Verweisen | Prüfung beim Speichern und Löschen |
| HTML-Fehlerseiten | jede Exception wird zu einer JSON-Antwort |

### Was vor dem Einsatz außerhalb von localhost geändert werden muss

- [ ] **`API_DEBUG` steht auf `true`.** Damit gehen SQL-Fehlermeldungen an den Client. Standard muss `false` sein, einschalten nur über `.env`.
- [ ] **Datenbank-Benutzer `root` ohne Passwort.** Eigenen Benutzer anlegen, der in `fahrzeugverwaltung` nur `SELECT, INSERT, UPDATE, DELETE` darf.
- [ ] **Zugangsdaten stehen im Code.** Sie gehören in `config/.env` und damit nicht ins Repo.
- [ ] **`Access-Control-Allow-Origin: *`.** Erlaubte Adressen in der Config festlegen. Ohne Anmeldung kann sonst jede Webseite, die ein Nutzer im internen Netz öffnet, per JavaScript Daten der API lesen und ändern.
- [ ] **`.idea/` ist eingecheckt.** In die `.gitignore` aufnehmen.

### Geplante Schutzmaßnahmen der 2.0

| Risiko | Schutz |
|---|---|
| Speicher-Erschöpfung | `maxAnfrageBytes`, `limitMaximum`, eigenes XLSX-Limit |
| XML External Entities (XXE) | Entity-Loading deaktiviert |
| CSV-Injection (Formeln in Excel) | Zellen mit `=`, `+`, `-`, `@` am Anfang werden bei der Ausgabe maskiert |
| NoSQL-Injection | nur skalare Werte, Operatoren aus fester Liste, Regex maskiert |
| Ungewollte Änderungen an Stammdaten | `aktionen` pro Ressource |
| Personendaten sichtbar | `versteckt` / `nurSchreiben` pro Feld |
| Fremde Webseiten greifen zu | `cors.erlaubteUrspruenge` statt `*` |

---

## Bewusst nicht enthalten

| Nicht enthalten | Grund | Stattdessen |
|---|---|---|
| Anmeldung / Login | nicht benötigt | Schutz über das Netz (siehe [Sicherheit](#sicherheit)) |
| Rollen und Rechte pro Person | ohne Anmeldung nicht möglich | `aktionen` pro Ressource für alle gleich |
| Request-Limit (Anfragen pro Zeit) | vorerst nicht benötigt | kann später ergänzt werden |
| VMs / Container starten oder verwalten | nicht Teil des Projekts | Bereitstellung außerhalb der API |
| Streaming großer Datenmengen | Einfachheit | Seiten mit `limit`/`offset` |
| Backend-Wahl per Request | Sicherheit | Backend in der Config |
| Verschachtelte Daten, eingebettete Verweise | jedes Format soll jede Zeile abbilden | IDs + zweiter Request |
| SQL als Eingabe | Sicherheit | JSON, Formular, CSV, XML |
| Joins über mehrere Ressourcen | folgt aus „nur flach“ | Datenbank-View als `quelle` |

---

## Roadmap

### Version 1 (`api.php`)

- [x] CRUD für alle Tabellen aus `RESSOURCEN`
- [x] Felder, Pflichtfelder, Primärschlüssel automatisch aus der Datenbank
- [x] Verweise über Fremdschlüssel und Namensregel, Prüfung beim Speichern und Löschen
- [x] Filter mit `*`, Sortierung, `limit`/`offset`
- [x] HTML-Formulare (`_method`, `operation`, ID als Feld)
- [x] Übersetzung von MySQL-Fehlern in passende Statuscodes
- [x] Übersicht `GET api.php`, Client, Swagger-Doku

### Version 2.0

**Phase 1: Aufräumen und Grundlage**
- [ ] **Anmeldung und Rollen entfernen:** `zugangsdaten()`, `anmelden()`, `authDb()`, `AUTH_*`, `TBL_BENUTZER`, `ROLLEN`, Prüfung in 6d, Block `angemeldet` in der Übersicht
- [ ] `client/index.php` ohne `Authorization`-Header, `openapi.yaml` ohne `BasicAuth`
- [ ] `benutzer.sql` entfernen
- [ ] SQL-Dump der Datenbank `fahrzeugverwaltung` ins Repo
- [ ] `.gitignore` (`.idea/`, `config/.env`, `cache/`)
- [ ] `API_DEBUG` standardmäßig aus, Zugangsdaten in `.env`
- [ ] `composer.json` mit PSR-4-Autoloading
- [ ] Konstanten → `config/gateway.php` + Prüfung beim Start

**Phase 2: Zerlegen in Klassen (Verhalten bleibt gleich)**
- [ ] `Response`, `ErrorHandler`, `Router`, `Request`
- [ ] `PdoDriver` mit `schema()` + Schema-Cache
- [ ] `FieldMapper`, `ReferenceChecker`
- [ ] `JsonCodec`, `FormCodec`
- [ ] `api.php` startet nur noch den Kernel. Client und Swagger funktionieren unverändert.

**Phase 3: Hilfe und neue Funktionen**
- [ ] **Hilfe-Befehle:** `/_hilfe`, `/_hilfe/<ressource>`, `/_hilfe/filter|formate|fehler`, `?_hilfe`, Text- und JSON-Ausgabe
- [ ] `hilfe`-Link in jeder Fehlermeldung
- [ ] `beschreibung` aus Config und Datenbank-Kommentaren
- [ ] `aktionen` pro Ressource, Felder verstecken / nur lesen / nur schreiben
- [ ] Filter-Operatoren, `fields`, Seitenangaben als Header
- [ ] `CsvCodec`, `XmlCodec` (Ein- und Ausgabe), `SqlCodec` (nur Ausgabe)
- [ ] `/_openapi`, `docs.html` umstellen, `openapi.yaml` entfernen

**Phase 4: Weitere Backends und Werkzeuge**
- [ ] `bin/hilfe.php` + `--hilfe` für alle Werkzeuge
- [ ] `FileDriver`
- [ ] `MongoDriver`, Verweise über Backend-Grenzen
- [ ] `YamlCodec`, `XlsxCodec` (mit eigenem Größenlimit)
- [ ] `/_convert`
- [ ] `bin/klassen-erzeugen.php` (ersetzt `api_outerlayer/`)
- [ ] `bin/umziehen.php`
- [ ] Debug-Header, Lasttest, Schätzwerte durch Messwerte ersetzen

---

## Lizenz

Noch festzulegen.
