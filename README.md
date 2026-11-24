# MyEndPoint – universelle REST-API

Eine REST-API in PHP, die jede freigegebene Datenbanktabelle per HTTP lesbar, anlegbar, änderbar und löschbar macht. Felder, Pflichtfelder und Verweise liest die API **selbst aus der Datenbank**. Eine neue Spalte braucht keine Codeänderung.

Der Branch `api-2.0` baut diese API zu einem **universellen Gateway** aus: mehrere Ein- und Ausgabeformate (JSON, Formular, CSV, XML …), mehrere Speicher-Backends (MariaDB, MongoDB, Dateien), eine zentrale Konfiguration, **eingebaute Hilfe-Befehle** und einen **optionalen SecurityCore**, der standardmäßig ausgeschaltet ist.

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
- [Verbindungen und Bausteine](#verbindungen-und-bausteine)
- [SecurityCore (optional)](#securitycore-optional)
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
| Zugriffskontrolle | **optionaler SecurityCore, standardmäßig aus** (siehe [SecurityCore](#securitycore-optional)) | ohne Konfiguration offen wie bisher geplant, bei Bedarf pro Kunde absicherbar |
| Grundschutz | **immer aktiv, nicht abschaltbar** (Prepared Statements, Whitelist, XXE aus, Größenlimit …) | kostet praktisch nichts und schützt vor kaputten oder böswilligen Eingaben, egal wer sie schickt |
| Geltungsbereich | **Variante C: global + pro Ressource zusätzlich**. Ressourcen können strenger sein, aber nie offener als global. | neue Ressourcen sind automatisch mindestens global geschützt |
| Identität | **Kundenschlüssel in einem frei benennbaren HTTP-Header**, optional gegen ein **Token** tauschbar (`POST /_login`) | kein Benutzername/Passwort nötig, die Rolle kommt immer aus der Config und nie vom Client |
| Anmeldung mit Benutzername/Passwort (`basic`) | **entfällt** | durch Kundenschlüssel und Token ersetzt |
| Rollen | **nur mit SecurityCore**, Rechte pro Ressource (`crud`) | ohne SecurityCore gibt es niemanden, dem eine Rolle zugeordnet wäre |
| Hilfe bei aktiver Sicherheit | **zeigt nur die Befehle, die die Rolle benutzen darf** | jeder Kunde sieht seine eigene, passende Doku, keine Sicherheitsdetails |
| Request-Limit (Anfragen pro Zeit) | **vorerst keins**, später als optionaler Wächter | kann ergänzt werden, ohne den Kern zu ändern |
| VMs / Container | **nicht Teil des Projekts** | die API läuft z. B. in XAMPP, wie sie bereitgestellt wird, ist offen |
| Hilfe | **eingebaute Hilfe-Befehle** (`_hilfe`) + [Benutzerhandbuch](HANDBUCH.md) | die API erklärt sich selbst, immer passend zur Datenbank |
| Datenverarbeitung | **alles in Arrays**, kein Streaming | einfache Schnittstellen, leicht zu testen |
| Backend-Wahl | **nur über die Konfiguration** | ein Client kann nie bestimmen, wo Daten landen |
| Datenstruktur | **nur flache Datensätze**, Verweise als IDs | jedes Format kann jede Zeile abbilden, auch CSV |
| Felder | **automatisch aus der Datenbank**, Config nur für Ausnahmen | so wie `api.php` es heute schon macht |
| Verbindungen | **ein `ConnectionManager`** baut alle Verbindungen auf, erst bei Bedarf, höchstens eine pro Request. Die API spricht nur mit dem Treiber. | ein Ort für Zugangsdaten und Optionen, Backends austauschbar, testbar |
| Bausteine | **werden automatisch erkannt** (`PluginFinder`): neue Datei in den Ordner legen, fertig. Die Config hat Vorrang, in Produktion wird nur auf Befehl gesucht. | neue Datenbanken und Formate ohne Änderung am Kern |
| PHP-Version | **8.2 oder neuer empfohlen** (8.1 läuft) | ab 8.2 erscheinen Passwörter nicht mehr im Stacktrace (siehe [Stolperfallen](#stolperfallen)) |
| Abwärtskompatibilität | `api.php/<ressource>/<id>`, Formulare und `client/index.php` funktionieren weiter | die Aufgabe baut darauf auf |
| Sprache | **Deutsch** für Config, JSON-Schlüssel, Fehlermeldungen und Hilfe, **Englisch** für Klassen und Namespaces (PHP-Konvention) | Clients sehen nur Deutsch, der Code folgt PSR-4 |

---

## Schnellstart (XAMPP)

**Voraussetzung:** PHP 8.1 oder neuer (8.2+ empfohlen), MariaDB/MySQL, Apache mit `mod_rewrite` (bei XAMPP aktiv).

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
| Anmeldung (Basic Auth, Pflicht) | ✅ | **entfällt** |
| SecurityCore (optional, Standard: aus) | ❌ | 🔜 Kundenschlüssel im Header, Token, IP-Freigabe … |
| Rollen | ✅ (pro HTTP-Methode, Pflicht) | 🔜 nur mit SecurityCore, pro Kunde und Ressource |
| Hilfe gefiltert nach Rolle | ❌ | 🔜 nur mit SecurityCore |
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

### Getrennte Bausteine

| Baustein | Aufgabe | Weiß nichts über |
|---|---|---|
| **Codec** | Format ↔ neutrale Zeilen | Datenbanken |
| **Treiber** | neutrale Zeilen ↔ Speicher, liefert das Schema | Formate |
| **FieldMapper** | Namen angleichen, Ausnahmen aus der Config, Prüfung, Verweise | Formate, Speicherart |
| **ConnectionManager** | Verbindungen aufbauen, erst bei Bedarf, eine pro Request | Abfragen, Formate, HTTP |
| **PluginFinder** | neue Codecs, Connectoren und Wächter automatisch finden | was die Bausteine tun |
| **SecurityCore** 🔜 | optional: wer anfragt (Kunde, Rolle) und ob er darf | Formate, Speicherart |

Details: [Verbindungen und Bausteine](#verbindungen-und-bausteine), [SecurityCore](#securitycore-optional).

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
 3. Methode bestimmen         echte Methode oder _method/Override   → 400
 4. SecurityCore              nur wenn global oder für die Ressource
                              aktiv: Kunde erkennen, Rolle prüfen   → 401 / 403 / 404
 5. Hilfe?                    _hilfe im Pfad oder ?_hilfe           → Hilfe ausgeben
                              (bei aktiver Sicherheit nach Rolle gefiltert), fertig
 6. Aktion erlaubt?           optionale Einschränkung pro Ressource → 405
 7. Codec (Content-Type)      Body → neutrale Zeilen                → 400 / 415
 8. FieldMapper               Namen angleichen, Pflichtfelder,
                              Typen, Verweise prüfen                → 400
 9. Treiber                   lesen / schreiben                     → 404 / 409
10. FieldMapper (zurück)      versteckte Felder entfernen
11. Codec (Accept)            neutrale Zeilen → Ausgabeformat       → 406
12. Antwort
```

Ist der SecurityCore aus, wird Schritt 4 komplett übersprungen. Es wird dann keine einzige Sicherheitsklasse geladen.

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
| `OPTIONS` | beliebig | CORS-Vorabanfrage → `204` (immer offen) |
| `GET` | `api.php/_hilfe[/…]` | Hilfe-Befehle 🔜 (siehe [Hilfe-Befehle](#hilfe-befehle)) |
| `POST` | `api.php/_login` | Kundenschlüssel gegen ein Token tauschen 🔜, nur wenn der Wächter `token` aktiv ist (siehe [SecurityCore](#securitycore-optional)) |
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
| `401` | nur mit SecurityCore 🔜: Kundenschlüssel bzw. Token fehlt, ist falsch oder abgelaufen |
| `403` | nur mit SecurityCore 🔜: Ressource ist für die Rolle sichtbar, die Aktion aber nicht erlaubt, oder ein Wächter wie `ip` / `https` lehnt ab |
| `404` | Ressource oder Datensatz unbekannt. Mit SecurityCore auch: Ressource, auf die die Rolle **gar kein** Recht hat. |
| `405` | Methode passt nicht zum Pfad, oder Aktion für diese Ressource abgeschaltet |
| `406` | Ausgabeformat nicht verfügbar 🔜 |
| `409` | doppelter Wert, oder Datensatz wird noch verwendet |
| `413` | Anfrage zu groß 🔜 |
| `415` | Eingabeformat nicht verfügbar 🔜 |
| `429` | nur mit Wächter `requestLimit` 🔜: zu viele Anfragen, mit `Retry-After`-Header |
| `500` | interner Fehler, oder Konfiguration ungültig |
| `503` | Datenbank bzw. Backend nicht erreichbar 🔜 (heute `500`) |

Ohne SecurityCore kommen `401`, `403` und `429` nicht vor. Die heutige `api.php` nutzt `401`/`403` noch für ihre Pflicht-Anmeldung, die entfällt.

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
- Die Hilfe zeigt **Befehle, keine Sicherheitsdetails**. Ob und welche Wächter aktiv sind, steht nie in der Antwort.

### Hilfe und SecurityCore

| SecurityCore | Was `_hilfe`, die Übersicht `GET api.php` und `/_openapi` zeigen |
|---|---|
| **aus** (Standard) | alle Befehle für alle Ressourcen, ohne Header |
| **an** (global oder für eine Ressource) | die Hilfe ist genauso geschützt wie der Rest. Gezeigt werden **nur die Befehle, die die Rolle des Kunden benutzen darf**. Ressourcen ohne Recht tauchen gar nicht auf, nicht erlaubte Aktionen fehlen in der Liste. |
| **an, aber kein / falscher Schlüssel** | `401` mit einem kurzen Hinweis, wie man sich ausweist (Name des konfigurierten Headers), aber ohne Ressourcen oder Felder |

Beispiel: Für einen Kunden mit der Rolle `leser` steht bei `fahrzeuge` nur `GET`, und `fahrzeughalter` fehlt ganz. Für die Rolle `werkstatt` stehen bei `fahrzeuge` zusätzlich `POST` und `PATCH`.

Beispiel (Text, SecurityCore aus):

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

Jedes Werkzeug in `bin/` zeigt mit `--hilfe` (oder `--help`, `-h`) Aufruf, Optionen und ein Beispiel. `php bin/hilfe.php` listet alle Werkzeuge auf. Siehe [Benutzerhandbuch, Abschnitt 12](HANDBUCH.md#12-kommandozeilen-werkzeuge).

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
| `AUTH_DSN`, `AUTH_USER`, `AUTH_PASS`, `TBL_BENUTZER` | Konten-Datenbank | **entfällt** (Kunden stehen in `sicherheit.kunden`) |
| `ROLLEN` | Rolle → erlaubte HTTP-Methoden | → optional `sicherheit.rollen`, pro Ressource |

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

    // Optionaler SecurityCore – siehe „SecurityCore (optional)“
    'sicherheit' => [
        'aktiv'    => false,                // Standard: aus, kein Wächter wird geladen
        'waechter' => [],                   // z. B. ['ip', 'kundenschluessel', 'rollen']
        // … Einstellungen der Wächter, siehe Abschnitt SecurityCore
    ],

    // Bausteine (Connectoren, Codecs, Wächter) – siehe „Verbindungen und Bausteine“
    'bausteine' => [
        'automatischSuchen' => getenv('API_DEBUG') === '1',   // Entwicklung: ja, Produktion: nein
        'abschalten' => ['xlsx'],                              // gefunden, aber nicht anbieten
        'ersetzen'   => [],                                    // z. B. ['pdo' => App\MeinPdoConnector::class]
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

        // Personendaten: einzelne Felder verbergen und zusätzlich absichern (optional)
        'fahrzeughalter' => [
            'verbindung' => 'fahrzeuge',
            'quelle'     => 'fahrzeughalter',
            'felder'     => ['Strasse' => ['versteckt' => true]],
            'sicherheit' => ['kundenschluessel', 'rollen'],   // gilt auch, wenn global aus
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
| `aktionen` | nein | erlaubte Aktionen als Buchstaben: `c` anlegen, `r` lesen, `u` ändern, `d` löschen. Standard: `crud` (alles). Gilt für **alle** Aufrufer, es ist keine Rolle. Nicht erlaubt → `405`. Mit SecurityCore die Obergrenze: Auch eine Rolle mit `crud` darf nicht mehr, als `aktionen` erlaubt. |
| `sicherheit` | nein | zusätzliche Wächter nur für diese Ressource, z. B. `['kundenschluessel', 'rollen']`. Werden zu den globalen **hinzugefügt**, können sie aber nie entfernen (Variante C). Gilt auch, wenn der SecurityCore global aus ist. |
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

- Jede Ressource verweist auf eine existierende Verbindung.
- Für jeden `treiber` in `verbindungen` wurde ein Connector-Baustein gefunden **und** ist verfügbar. Sonst kommt eine klare Meldung, z. B. „Treiber `mongo` ist konfiguriert, aber nicht verfügbar: PHP-Extension `mongodb` fehlt“.
- Klassen in `bausteine.ersetzen` existieren und setzen das passende Interface um.
- Ist der SecurityCore global oder für eine Ressource aktiv: Alle genannten Wächter wurden gefunden, ihre Abhängigkeiten sind erfüllt (z. B. `rollen` braucht eine Identität), und ihre Einstellungen sind vollständig (Header-Name, Schlüssel-Hashes, Token-Secret ≥ 32 Zeichen). **Sonst startet die API nicht** (fail closed).
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

Codecs werden wie Connectoren **automatisch erkannt**: Eine neue Datei `src/Codec/NdjsonCodec.php` reicht, und das Format steht zur Verfügung (siehe [Bausteine automatisch erkennen](#bausteine-automatisch-erkennen-pluginfinder)). Jeder Codec setzt dieselbe Schnittstelle um:

```php
interface Codec
{
    public static function typ(): string;          // Kurzname, z. B. 'csv' – auch für ?format=
    public static function verfuegbar(): bool;     // z. B. ist symfony/yaml installiert?
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

Ein Treiber stellt die Abfragen. Die Verbindung baut er **nicht selbst** auf, sondern holt sie sich beim `ConnectionManager` (siehe [Verbindungen und Bausteine](#verbindungen-und-bausteine)). Jeder Treiber setzt dieselbe Schnittstelle um:

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

## Verbindungen und Bausteine

### Drei Schichten

```
API (HTTP)  →  Treiber (Abfragen)  →  ConnectionManager (nur Verbindungsaufbau)
```

| Schicht | Aufgabe | Kennt |
|---|---|---|
| **API** (Router, Controller) | Request verstehen, Antwort bauen | nur den Treiber |
| **Treiber** (`PdoDriver`, `MongoDriver`, `FileDriver`) | SQL bzw. Mongo-Abfragen, Schema, Fehlerübersetzung | seine Verbindung |
| **ConnectionManager** | Verbindung aufbauen, erst bei Bedarf, höchstens eine pro Request und Verbindungsname | die Config und die Connectoren |

Die API sagt also nicht „baue die Verbindung auf“, sondern „gib mir den Treiber für `fahrzeuge`“. Der Treiber holt sich die Verbindung, und die wird erst in diesem Moment aufgebaut.

**Heute** übernimmt das in `api.php` die Funktion `db()`: ein statischer Singleton mit fest eingetragenen Konstanten. Der `ConnectionManager` ersetzt ihn.

### ConnectionManager

**Was er bringt:**

- **Ein Ort für alles rund um die Verbindung**: Zugangsdaten, `charset`, `ERRMODE_EXCEPTION`, `EMULATE_PREPARES = false`.
- **Lazy**: `OPTIONS`, `?_hilfe` mit Schema-Cache oder eine Anfrage mit falschem Pfad bauen gar keine Datenbankverbindung auf. Eine Anfrage an MariaDB öffnet keine Verbindung zu MongoDB.
- **Backends austauschbar**: MariaDB, MongoDB oder Datei unterscheiden sich nur im Connector. API und Treiber merken davon nichts.
- **Testbar**: Für Tests lässt sich eine SQLite-Verbindung im Speicher übergeben, ganz ohne XAMPP.
- **Saubere Fehler**: Ist die Datenbank nicht erreichbar, wird daraus `503 Datenbank nicht erreichbar`. Die Details landen nur im Log.

**Regeln:**

| Regel | Warum |
|---|---|
| **PDO umhüllen, nicht davon erben** (kein `extends PDO`) | MongoDB oder Dateien passen nicht in eine PDO-Unterklasse. Über das `Connector`-Interface passt jedes Backend. |
| **Eine Verbindung pro Request**, nicht pro Abfrage öffnen und schließen | Jeder Aufbau kostet einige Millisekunden. PHP schließt die Verbindung am Ende des Requests von selbst. |
| **Keine persistenten Verbindungen** (`PDO::ATTR_PERSISTENT`) | bringen hier keinen Vorteil, aber Probleme mit offenen Transaktionen und Sperren aus vorherigen Requests |
| **Kein globaler Singleton** | Der Kernel erzeugt **einen** `ConnectionManager` und reicht ihn an die Treiber weiter. Man sieht dann jedem Treiber an, dass er eine Verbindung braucht. |
| **Klein halten** | Er baut nur auf und hält fest. Abfragen, Schema und Fehlerübersetzung (`MYSQL_FEHLER`) gehören in den Treiber. |
| **Passwort nie ins Log** | Verbindungsfehler selbst abfangen und nur die Meldung loggen, ohne Stacktrace (siehe [Stolperfallen](#stolperfallen)). |

**Skizze:**

```php
interface Connector
{
    public static function typ(): string;          // 'pdo', 'mongo', 'datei' – so heißt er in der Config
    public static function verfuegbar(): bool;     // ist die nötige PHP-Extension / Bibliothek da?
    public function verbinden(array $config): object;
}
```

```php
final class PdoConnector implements Connector
{
    public static function typ(): string      { return 'pdo'; }
    public static function verfuegbar(): bool { return extension_loaded('pdo'); }

    public function verbinden(array $c): object
    {
        return new PDO($c['dsn'], $c['user'] ?? null, $c['pass'] ?? null, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    }
}
```

```php
final class ConnectionManager
{
    private array $offen = [];          // bereits aufgebaute Verbindungen dieses Requests
    private ?array $connectoren = null; // Typ → Klasse, vom PluginFinder

    public function __construct(
        private array $verbindungen,    // aus config/gateway.php
        private PluginFinder $finder,
        private array $ersetzen = [],   // bausteine.ersetzen
    ) {}

    public function get(string $name): object
    {
        return $this->offen[$name] ??= $this->aufbauen($name);   // erst bei Bedarf, dann wiederverwenden
    }

    private function aufbauen(string $name): object
    {
        $c = $this->verbindungen[$name]
            ?? throw new ConfigException("Verbindung \"$name\" ist nicht konfiguriert.");

        $klasse = $this->ersetzen[$c['treiber']]
            ?? $this->connectoren()[$c['treiber']]
            ?? throw new ConfigException("Treiber \"{$c['treiber']}\" ist nicht verfügbar.");

        try {
            return (new $klasse())->verbinden($c);
        } catch (Throwable $e) {
            error_log("Verbindung \"$name\" fehlgeschlagen: " . $e->getMessage());   // ohne Stacktrace
            throw new VerbindungFehlgeschlagen($name);                               // → 503
        }
    }

    private function connectoren(): array
    {
        return $this->connectoren ??= $this->finder->finden(__DIR__ . '/../Connector', Connector::class);
    }
}
```

```php
// Kernel: einmal pro Request verdrahten
$finder       = new PluginFinder(__DIR__ . '/../cache', $config['bausteine']['automatischSuchen']);
$verbindungen = new ConnectionManager($config['verbindungen'], $finder, $config['bausteine']['ersetzen']);
$treiber      = new DriverFactory($verbindungen, $config['ressourcen']);

// API: kennt nur noch den Treiber
$fahrzeug = $treiber->fuer('fahrzeuge')->get('5');
```

### Bausteine automatisch erkennen (PluginFinder)

**Idee:** Ein neuer Baustein wird eingebaut, indem man **eine Datei in einen Ordner legt**. Der Kern muss nicht angepasst werden (Open/Closed-Prinzip). Java bietet dafür den `ServiceLoader`, Spring das Classpath-Scanning. PHP hat nichts Eingebautes, deshalb übernimmt das der `PluginFinder`.

| Ordner | Interface | Beispiel | Ergebnis |
|---|---|---|---|
| `src/Connector/` | `Connector` | `MongoConnector.php` | `'treiber' => 'mongo'` ist in der Config nutzbar |
| `src/Codec/` | `Codec` | `NdjsonCodec.php` | `Content-Type: application/x-ndjson` wird verstanden |
| `src/Security/` | `Waechter` | `IpWaechter.php` | `'ip'` ist im SecurityCore nutzbar (siehe [SecurityCore](#securitycore-optional)) |

**Konvention:** Dateiname = Klassenname, Endung `…Connector.php` bzw. `…Codec.php`, Namespace passend zum Ordner (PSR-4).

```php
final class MongoConnector implements Connector
{
    public static function typ(): string        { return 'mongo'; }
    public static function verfuegbar(): bool   { return extension_loaded('mongodb') && class_exists(\MongoDB\Client::class); }
    public function verbinden(array $c): object { return new \MongoDB\Client($c['uri']); }
}
```

**So sucht der PluginFinder:**

```php
final class PluginFinder
{
    public function __construct(private string $cacheOrdner, private bool $automatischSuchen) {}

    /** @return array<string, class-string>  Typ → Klasse */
    public function finden(string $ordner, string $interface): array
    {
        $cache = $this->cacheOrdner . '/bausteine-' . md5($interface) . '.php';

        // Produktion: nur den Cache lesen. Entwicklung: neu suchen, wenn sich der Ordner geändert hat.
        if (is_file($cache) && (!$this->automatischSuchen || filemtime($cache) >= filemtime($ordner))) {
            return require $cache;
        }
        return $this->suchenUndSpeichern($ordner, $interface, $cache);
    }

    private function suchenUndSpeichern(string $ordner, string $interface, string $cache): array
    {
        $gefunden = [];
        $suffix   = substr(strrchr($interface, '\\') ?: "\\$interface", 1);        // 'Connector' bzw. 'Codec'
        $ns       = substr($interface, 0, strrpos($interface, '\\') ?: 0) . '\\';

        foreach (glob("$ordner/*$suffix.php") as $datei) {
            $klasse = $ns . basename($datei, '.php');
            if (!class_exists($klasse)) continue;                                    // lädt über den Autoloader
            $r = new ReflectionClass($klasse);
            if (!$r->isInstantiable() || !$r->implementsInterface($interface)) continue;
            if (!$klasse::verfuegbar()) continue;                                    // Abhängigkeit fehlt
            $gefunden[$klasse::typ()] = $klasse;
        }

        // atomar schreiben: erst Temp-Datei, dann umbenennen
        $tmp = $cache . '.' . bin2hex(random_bytes(4)) . '.tmp';
        file_put_contents($tmp, '<?php return ' . var_export($gefunden, true) . ';');
        rename($tmp, $cache);

        return $gefunden;
    }
}
```

> Die Skizze geht davon aus, dass Interface und Bausteine im selben Namespace liegen, z. B. `Gateway\Connector\Connector` und `Gateway\Connector\MongoConnector`.

**Die Config hat Vorrang** vor der Automatik:

| Einstellung | Wirkung |
|---|---|
| `bausteine.abschalten` | gefundene Bausteine nicht anbieten, z. B. `['xlsx']` |
| `bausteine.ersetzen` | eigene Klasse statt der gefundenen, z. B. `['pdo' => App\MeinPdoConnector::class]` |
| `bausteine.automatischSuchen` | `true`: Ordner bei Änderungen neu durchsuchen, `false`: nur den Cache nutzen |

Welche Bausteine gefunden wurden und welche fehlen, zeigen `GET api.php/_hilfe/formate` und `php bin/config-pruefen.php`.

### Wann sucht der PluginFinder?

**PHP startet bei jedem Request neu.** Anders als bei Java gibt es bei XAMPP (`mod_php`) und PHP-FPM keine dauerhaft laufende Anwendung:

```
Request kommt → Skript startet → Config laden → … → Antwort → alles wird verworfen
```

Objekte und Variablen überleben den Request nicht. **Für PHP ist jeder Request ein Runtime-Start.** Deshalb gibt es den Cache.

| | Java (z. B. Spring, Tomcat) | PHP (XAMPP, PHP-FPM) |
|---|---|---|
| Anwendung läuft | dauerhaft, startet einmal | pro Request neu |
| Suche nach Bausteinen | einmal beim Start | bei jedem Request, wenn man nichts dagegen tut |
| Neuer Baustein | Neustart nötig | nächster Request sieht ihn, ohne Neustart |
| Zustand zwischen Requests | im Arbeitsspeicher | nur über Dateien, Datenbank, APCu, OPcache |

**Ablauf über mehrere Requests** (Entwicklung, `automatischSuchen = true`):

```
Request 1   Cache fehlt              → Ordner durchsuchen, Cache schreiben    (einige ms)
Request 2   Cache aktuell            → Cache-Datei lesen                      (Mikrosekunden)
Request 3   Cache aktuell            → Cache-Datei lesen
            ── MongoConnector.php wird in src/Connector/ gelegt ──
Request 4   Ordner neuer als Cache   → neu durchsuchen, Cache schreiben       (einige ms)
Request 5   Cache aktuell            → Cache-Datei lesen
```

**Innerhalb eines Requests** wird höchstens einmal gesucht, und zwar erst, wenn ein Baustein gebraucht wird (`$this->connectoren ??= …`). Bei `OPTIONS` oder `?_hilfe` mit Schema-Cache passiert es gar nicht.

**Kosten:** Die Prüfung, ob sich der Ordner geändert hat, ist ein einziger `filemtime()`-Aufruf (Mikrosekunden). Die Cache-Datei ist ein PHP-Array, das OPcache kompiliert im Speicher hält.

### Betriebsarten

| | Entwicklung | Produktion |
|---|---|---|
| `bausteine.automatischSuchen` | `true` | `false` |
| Ordner auf Änderungen prüfen | bei jedem Request (`filemtime`) | nie |
| Neu suchen | automatisch, sobald eine Datei dazukommt oder entfernt wird | nur über `php bin/bausteine-aktualisieren.php` |
| Vorteil | Datei ablegen, sofort aktiv | kein Aufwand zur Laufzeit, nichts wird unbemerkt aktiv |

In Produktion werden neue Bausteine damit nur **bewusst** aktiviert und nicht schon dadurch, dass irgendwo eine Datei auftaucht. Fehlt der Cache dort ganz (z. B. nach dem ersten Kopieren), wird einmal gesucht und eine Warnung ins Log geschrieben.

### Stolperfallen

| Stolperfalle | Was passiert | Lösung |
|---|---|---|
| **Autoloading** | PHP kennt nur bereits geladene Klassen. `get_declared_classes()` findet einen neuen Baustein nicht, weil er noch nie benutzt wurde. | festen Ordner durchsuchen und Klassen gezielt mit `class_exists()` laden |
| **`filemtime()` eines Ordners** | ändert sich nur, wenn Dateien **hinzukommen, gelöscht oder umbenannt** werden, nicht wenn eine Datei bearbeitet wird | für „welche Bausteine gibt es“ ist das richtig. Geänderter Code in einer Klasse wird normal geladen. Wer den `typ()` eines Bausteins ändert: `bausteine-aktualisieren.php` ausführen. |
| **OPcache in Produktion** (`opcache.validate_timestamps = 0`) | geänderte PHP-Dateien, auch die Cache-Datei, werden erst nach einem Neustart von Apache bzw. PHP-FPM gesehen | `bausteine-aktualisieren.php` ruft `opcache_reset()` auf (wirkt nur, wenn es über den Webserver ausgelöst wird) bzw. weist auf den Neustart hin |
| **Zwei Requests gleichzeitig** mit veraltetem Cache | beide suchen und schreiben die Cache-Datei. Ein dritter könnte eine halb geschriebene Datei lesen. | in eine Temp-Datei schreiben und per `rename()` ersetzen (atomar) |
| **Beschreibbarer Baustein-Ordner** | jede Datei darin wird ausgeführt, also auch eingeschleuster Code | `src/Connector/` und `src/Codec/` dürfen für den Webserver **nicht** beschreibbar sein. In Produktion `automatischSuchen = false`. |
| **Fehlende Abhängigkeit** (z. B. `ext-mongodb`) | ohne Prüfung würde schon das Laden der Klasse oder der erste Aufruf abstürzen | `verfuegbar()` prüft vorher. Der Baustein wird nicht angeboten, `config-pruefen.php` nennt den Grund. |
| **Dateiname ≠ Klassenname** oder falscher Namespace | Autoloader findet die Klasse nicht, der Baustein wird stillschweigend übersprungen | Konvention einhalten. `config-pruefen.php` meldet Dateien im Ordner, die keinen gültigen Baustein enthalten. |
| **Zwei Bausteine mit demselben `typ()`** | der zuletzt gefundene gewinnt, abhängig von der Reihenfolge im Dateisystem | doppelte Typen als Fehler melden. Bewusst ersetzen nur über `bausteine.ersetzen`. |
| **Abstrakte Klassen, Interfaces, Hilfsklassen** im Ordner | würden fälschlich als Baustein gelten | `isInstantiable()` + `implementsInterface()` prüfen, Hilfsklassen ohne Endung `…Connector`/`…Codec` benennen |
| **Passwort im Stacktrace** (PHP 8.1) | schlägt `new PDO(...)` fehl, kann das Passwort als Argument im Stacktrace stehen. `api.php` schreibt heute `error_log((string) $e)` mit Stacktrace. | Verbindungsfehler selbst abfangen und nur `getMessage()` loggen. Ab PHP 8.2 wird das Passwort-Argument automatisch ausgeblendet. |
| **`cache/` nicht beschreibbar** | der Cache kann nicht geschrieben werden, bei jedem Request wird neu gesucht | Warnung ins Log. Mit `automatischSuchen = false` muss nur das CLI-Werkzeug schreiben dürfen. |

### Sonderfälle

| Fall | Verhalten |
|---|---|
| **PHP mit dauerhaftem Prozess** (FrankenPHP im Worker-Modus, RoadRunner, Swoole) | wie bei Java: Die Anwendung bleibt im Speicher, der PluginFinder sucht **einmal beim Start** des Workers. Ein neuer Baustein braucht einen Neustart des Workers. Der Cache schadet nicht, der Code funktioniert unverändert. Achtung: Dort bleiben auch Verbindungen über Requests hinweg offen. Der `ConnectionManager` muss sie dann am Ende jedes Requests verwerfen oder vor der Nutzung prüfen. |
| **Kommandozeile** (`bin/…`) | jedes Werkzeug ist ein eigener Start, wie ein Request. `bin/bausteine-aktualisieren.php` sucht immer neu, unabhängig von `automatischSuchen`. |
| **Tests** | dem `ConnectionManager` eine feste Liste übergeben statt des PluginFinders. Dann hängen Tests nicht vom Dateisystem ab. |
| **Baustein in Config genutzt, aber nicht gefunden** | Prüfung beim Start schlägt fehl, `500` mit Grund im Log. Die API startet nicht mit halber Funktion. |
| **Baustein gefunden, aber in keiner Verbindung genutzt** | kein Problem. Connectoren werden nur geladen, wenn eine Verbindung sie braucht. |

### Abwägung: Automatik oder Liste in der Config?

Auch mit Automatik braucht ein neues Backend die Klasse selbst und einen Eintrag in `verbindungen`. Gespart wird nur die Zeile, die den Typ registriert. Das gleiche Ziel (Kern bleibt unverändert) erreicht man auch mit einer festen Liste in der Config:

```php
'bausteine' => [
    'connectoren' => ['pdo' => PdoConnector::class, 'mongo' => MongoConnector::class],
],
```

| | Liste im Code (`match`) | Liste in der Config | Automatik (PluginFinder) |
|---|---|---|---|
| Kern ändern bei neuem Baustein | ja | nein | nein |
| Config ändern | nein | eine Zeile | nein |
| Man sieht sofort, was aktiv ist | ja | ja | über `_hilfe/formate` bzw. `config-pruefen.php` |
| Aufwand | gering | gering | mittel |
| Fehlerquellen | wenige | wenige | Ordner, Namensschema, Cache |

**Entscheidung:** Automatik, weil derselbe Mechanismus auch für **Codecs** gilt. Dort kommen deutlich öfter neue Bausteine dazu als bei Datenbanken. Die Config hat aber immer Vorrang (`abschalten`, `ersetzen`), und in Produktion wird nur auf Befehl gesucht.

---

## SecurityCore (optional)

> 🔜 Geplant. **Standardmäßig ausgeschaltet.** Ohne Konfiguration verhält sich die API so, als gäbe es den SecurityCore nicht: kein Header wird ausgewertet, keine Sicherheitsklasse wird geladen.

### Zwei Ebenen

| Ebene | Was | Abschaltbar? |
|---|---|---|
| **Grundschutz** | Prepared Statements, Whitelist der Ressourcen, Spaltennamen nur aus dem Schema, XXE aus, CSV-Injection maskieren, Größenlimit, Debug standardmäßig aus, keine Passwörter im Log | **nein**. Kostet praktisch nichts und schützt die API vor kaputten oder böswilligen Eingaben, egal wer sie schickt. |
| **SecurityCore** | Zugriffskontrolle: IP-Freigabe, Kundenschlüssel, Token, Rollen, Request-Limit, Audit-Log … | **ja, standardmäßig aus** |

### Wächter als Bausteine

Jede Sicherheitsfunktion ist ein **Wächter**: eine kleine Klasse in `src/Security/`, die der [PluginFinder](#bausteine-automatisch-erkennen-pluginfinder) wie Connectoren und Codecs automatisch findet.

```php
interface Waechter
{
    public static function typ(): string;            // 'ip', 'kundenschluessel', 'token', 'rollen' …
    public static function verfuegbar(): bool;
    public static function braucht(): array;         // Abhängigkeiten, z. B. ['identitaet'] bei 'rollen'
    public function pruefen(Request $request, Kontext $kontext): void;   // wirft bei Ablehnung
}
```

Der `Kontext` trägt den **`Principal`**: wer anfragt. Ohne SecurityCore ist das immer „anonym“. Mit Kundenschlüssel oder Token ist es der Kundenname, und daraus ergibt sich die Rolle.

| Wächter | Schützt | braucht | Kosten pro Request |
|---|---|---|---|
| `ip` | nur bestimmte Rechner oder Netze | – | Mikrosekunden |
| `https` | lehnt unverschlüsselte Requests ab | – | Mikrosekunden |
| `nurLesen` | schaltet alle Schreibzugriffe ab, z. B. für eine Demo | – | Mikrosekunden |
| `kundenschluessel` | nur Kunden mit gültigem Schlüssel im konfigurierten Header | – (liefert die Identität) | Mikrosekunden (SHA-256) |
| `token` | Schlüssel einmal gegen ein Token tauschen, danach nur noch Token | `kundenschluessel` | Mikrosekunden (HMAC) |
| `rollen` | Rechte pro Kunde und Ressource (`crud`), filtert auch die Hilfe | eine Identität | Mikrosekunden |
| `requestLimit` | Anfragen pro Zeit (später, vorerst nicht benötigt) | – (optional Identität) | Mikrosekunden (APCu) |
| `audit` | protokolliert Schreibzugriffe mit Kunde, Zeit, Ressource, ID | sinnvoll mit Identität | ~1 ms |

### Geltungsbereich: Variante C (global + pro Ressource zusätzlich)

```php
'sicherheit' => [
    'aktiv'    => true,
    'waechter' => ['ip'],                                    // gilt für alle Ressourcen
],
'ressourcen' => [
    'fahrzeughalter' => [/* … */, 'sicherheit' => ['kundenschluessel', 'rollen']],   // zusätzlich
],
```

Die Wächter für einen Request ergeben sich so:

```
wirksame Wächter = (global aktiv ? globale Wächter : keine)  ∪  Wächter der Ressource
```

| Regel | Bedeutung |
|---|---|
| **Nur hinzufügen, nie entfernen** | Eine Ressource kann strenger sein als global, aber nie offener. Eine Ressource zu „vergessen“ öffnet sie nicht. |
| **Neue Ressourcen** | bekommen automatisch den globalen Schutz |
| **Global aus, Ressource mit Wächtern** | nur diese Ressource ist geschützt, alle anderen bleiben offen |
| **Global aus, keine Ressource mit Wächtern** | SecurityCore komplett aus (Standard) |

| Variante | Vorteile | Nachteile |
|---|---|---|
| A: nur global | ein Schalter, nichts vergessen | keine Ausnahmen, alles gleich streng |
| B: nur pro Ressource | sehr flexibel | neue Ressourcen sind offen, bis man sie einträgt |
| **C: kombiniert** (gewählt) | so sicher wie A, fast so flexibel wie B | man muss beide Ebenen kennen |

### Kunden und Kundenschlüssel

Jeder Kunde, der den SecurityCore nutzt, bekommt einen eigenen **geheimen Schlüssel**. Den schickt er in einem **HTTP-Header, dessen Name frei wählbar** ist.

```php
'sicherheit' => [
    'aktiv'    => true,
    'waechter' => ['kundenschluessel', 'rollen'],

    'header' => [
        'name'    => 'X-MyEndPoint-Key',     // frei wählbar, z. B. 'X-Kunde'
        'praefix' => '',                     // optional, z. B. 'Bearer ' (dann 'name' => 'Authorization')
    ],

    'kunden' => [                            // Schlüssel nur als SHA-256-Hash, in der .env
        'autohaus-mueller' => ['schluessel' => getenv('KEY_MUELLER_HASH'), 'rolle' => 'leser'],
        'werkstatt-nord'   => ['schluessel' => getenv('KEY_NORD_HASH'),    'rolle' => 'werkstatt'],
        'verwaltung'       => ['schluessel' => getenv('KEY_ADMIN_HASH'),   'rolle' => 'admin'],
        'alter-kunde'      => ['schluessel' => getenv('KEY_ALT_HASH'),     'rolle' => 'leser', 'aktiv' => false],
    ],
],
```

```bash
curl -H "X-MyEndPoint-Key: 7f3c9a…" http://localhost/MyEndPoint/api.php/fahrzeuge
```

- **Im Header steht der Schlüssel, nie die Rolle.** Die Rolle steht in der Config. Der Header beweist nur, welcher Kunde anfragt. Könnte der Client seine Rolle selbst schicken, würde jeder „admin“ hineinschreiben.
- **Gespeichert wird nur der Hash.** Weil die Schlüssel lang und zufällig sind (32 Byte), reicht SHA-256. Das langsame bcrypt ist nur für von Menschen gewählte Passwörter nötig. Verglichen wird mit `hash_equals()`, damit die Laufzeit nichts verrät.
- **Schlüssel erzeugen**: `php bin/schluessel-erzeugen.php <kunde>` erzeugt einen zufälligen Schlüssel, zeigt ihn **einmal** an und gibt die Zeile für die `.env` mit dem Hash aus.
- **Kunden sperren**: `'aktiv' => false` oder Eintrag löschen. Das wirkt sofort, auch für bereits ausgegebene Tokens.
- **Ist der SecurityCore aus, wird der Header ignoriert.**

### Token (`POST /_login`)

Optionaler Wächter `token`: Der Kunde tauscht seinen Schlüssel **einmal** gegen ein Token mit Ablaufzeit und schickt danach nur noch das Token.

```bash
curl -X POST -H "X-MyEndPoint-Key: 7f3c9a…" http://localhost/MyEndPoint/api.php/_login
```

```json
{ "token": "mep1.eyJrIjoid2Vya3N0YXR0LW5vcmQiLCJleHAiOjE3NjQwMDAwMDB9.Qk3…", "gueltigBis": "2026-11-24T22:00:00+01:00" }
```

```bash
curl -H "X-MyEndPoint-Key: mep1.eyJr…" http://localhost/MyEndPoint/api.php/fahrzeuge
```

```php
'token' => [
    'secret'      => getenv('TOKEN_SECRET'),   // Pflicht, mindestens 32 Zeichen, kein Standardwert
    'gueltigkeit' => 8 * 3600,                 // Sekunden
    'nurToken'    => true,                     // Schlüssel nur noch bei /_login annehmen
],
```

| Punkt | Umsetzung |
|---|---|
| Wo wird es geschickt? | im selben konfigurierten Header wie der Schlüssel. Tokens erkennt die API am Präfix `mep1.`. |
| Was steht drin? | nur Kundenname und Ablaufzeit, signiert mit HMAC-SHA256. **Keine Rolle.** |
| Rolle | wird bei jedem Request aus der Config gelesen. Eine geänderte Rolle oder ein gesperrter Kunde wirkt sofort. |
| Warum überhaupt Token? | Der dauerhafte Schlüssel wird nur selten übertragen. Ein abgefangenes Token ist nach Ablauf wertlos. |
| Alle Tokens ungültig machen | `TOKEN_SECRET` ändern |
| `POST /_login` | ist immer erreichbar, sonst könnte sich niemand ein Token holen. Ohne gültigen Schlüssel antwortet er mit `401`. |

### Rollen

```php
'sicherheit' => [
    'rollen' => [                                    // '*' = alle übrigen Ressourcen
        'leser'     => ['*' => 'r', 'fahrzeughalter' => ''],
        'werkstatt' => ['fahrzeuge' => 'cru', '*' => 'r'],
        'admin'     => ['*' => 'crud'],
    ],
],
```

```
Recht = (rollen[rolle][ressource] ?? rollen[rolle]['*'] ?? '')  ∩  ressourcen[ressource].aktionen
```

| Ergebnis | Wirkung |
|---|---|
| Recht leer (`''`) | Ressource ist für diese Rolle **unsichtbar**: fehlt in Hilfe, Übersicht und `/_openapi`, Zugriff → `404` |
| Recht vorhanden, Aktion nicht enthalten | Ressource sichtbar, die Aktion fehlt in der Hilfe, Zugriff → `403` |
| Aktion enthalten | erlaubt |

`aktionen` an der Ressource bleibt die Obergrenze für alle: Ist `treibstoffe` auf `'r'` gesetzt, kann auch `admin` dort nichts ändern.

### Hilfe bei aktiver Sicherheit

- Die Hilfe zeigt **die Befehle, die der Kunde benutzen kann**, und nichts über Wächter oder Einstellungen.
- `_hilfe`, die Übersicht `GET api.php` und `/_openapi` sind genauso geschützt wie die Daten.
- Gesamthilfe (`_hilfe`): geschützt durch die globalen Wächter. Ressourcen mit eigenen Wächtern erscheinen nur, wenn der Aufrufer auch diese besteht.
- Ressourcenhilfe (`_hilfe/<ressource>`, `?_hilfe`): geschützt durch die wirksamen Wächter dieser Ressource.
- Ohne bzw. mit falschem Schlüssel: `401` mit dem Hinweis, welchen Header man mitschicken muss, ohne Ressourcen oder Felder.

### Immer offen

| Was | Warum |
|---|---|
| `OPTIONS` (CORS-Vorabanfrage) | Browser schicken dabei technisch keine eigenen Header mit |
| `POST /_login` | sonst könnte sich niemand ein Token holen. Prüft selbst den Schlüssel. |

### Weitere Wächter

```php
'ip' => [
    'erlaubt'               => ['127.0.0.1', '::1', '192.168.0.0/16'],
    'vertrauenswuerdigeProxys' => [],        // nur dann wird X-Forwarded-For beachtet
],
'requestLimit' => ['proMinute' => 120, 'je' => 'kunde'],   // 'kunde' oder 'ip'
'audit'        => ['ziel' => 'datei', 'pfad' => __DIR__ . '/../logs/audit.log'],
```

### Regeln

1. **Fail closed, nicht fail open.** Ist ein Wächter eingeschaltet, aber falsch konfiguriert (Header-Name fehlt, Schlüssel-Hash fehlt, Secret zu kurz, Wächter nicht gefunden, Abhängigkeit fehlt), dann startet die API **nicht**. Optional heißt: Man muss es nicht einschalten. Eingeschaltet muss es aber greifen.
2. **Keine Sicherheitsdetails in Antworten.** Weder Hilfe noch Fehlermeldungen verraten, welche Wächter aktiv sind.
3. **Geheimnisse nur als Hash bzw. in der `.env`.** Schlüssel und Tokens erscheinen nie im Log. Geloggt wird der Kundenname.
4. **Schlüssel nie in der URL** (`?key=…`). URLs landen in Server-Logs, im Browser-Verlauf und in Proxys.
5. **„Aus“ bleibt nicht unbemerkt.** `config-pruefen.php` und das Server-Log warnen, wenn der SecurityCore aus ist und die API nicht nur auf localhost läuft.

### Stolperfallen

| Stolperfalle | Lösung |
|---|---|
| **CORS und eigener Header**: Browser schicken einen selbst benannten Header nur, wenn die API ihn in `Access-Control-Allow-Headers` erlaubt. | Den konfigurierten Header-Namen automatisch in die CORS-Antwort aufnehmen. |
| **Caching**: Antworten und Hilfe unterscheiden sich je nach Kunde. Ein Proxy könnte die Hilfe von Kunde A an Kunde B ausliefern. | Bei aktiver Sicherheit `Vary: <Header-Name>` und `Cache-Control: private` senden. |
| **HTML-Formulare** können keine eigenen Header setzen. | `client/index.php` nutzt `fetch()` und kann den Header setzen. Für reine Formulare optional ein Feld (`'formularFeld' => '_schluessel'`, standardmäßig aus), nur im POST-Body, nie in der URL. |
| **`Authorization` als Header-Name** wird von Apache nicht immer an PHP weitergereicht. | Die vorhandene Regel in `.htaccess` reicht ihn weiter. Eigene `X-…`-Header kommen ohne Zusatz an. |
| **`X-Forwarded-For` ist fälschbar.** | Nur beachten, wenn die Anfrage von einem eingetragenen `vertrauenswuerdigeProxys` kommt. |
| **Verweis-Prüfung** über eine geschützte Ressource kann verraten, ob eine ID existiert (`400` „Eintrag gibt es nicht“ oder kein Fehler). | Bewusst in Kauf nehmen oder die Prüfung für Aufrufer ohne Leserecht auf der Zielressource verallgemeinern. |
| **Lösch-Prüfung** nennt die Ressource, in der ein Datensatz noch verwendet wird. | Für Aufrufer, die diese Ressource nicht sehen dürfen, nur allgemein melden („wird noch von einer anderen Ressource verwendet“). |
| **Uhrzeit des Servers** falsch → Tokens laufen zu früh oder zu spät ab. | Serverzeit per NTP synchron halten. |

### Planung (S1–S6)

```
S1  Fundament
 ├─ S2  ip · https · nurLesen                (ohne Identität)
 ├─ S3  kundenschluessel                      (liefert die Identität)
 │   ├─ S4  rollen + Hilfe-Filter             (braucht S3)
 │   └─ S5  token                             (braucht S3)
 └─ S6  requestLimit · audit                  (später, sinnvoll nach S3)
```

| Phase | Inhalt | Aufwand |
|---|---|---|
| **S1** | Interface `Waechter`, `Kontext` + `Principal`, Einhängen im Kernel (aus = nichts laden), Variante-C-Logik, Config-Prüfung (fail closed), Statuscodes `401`/`403`/`429` | mittel |
| **S2** | `ip` (inkl. Proxy-Regel), `https`, `nurLesen` | klein |
| **S3** | `kundenschluessel`: konfigurierbarer Header + Präfix, Kunden mit Hash, `aktiv`-Schalter, CORS-Header, `Vary`, `bin/schluessel-erzeugen.php` | klein–mittel |
| **S4** | `rollen`: Rechte pro Ressource, Schnittmenge mit `aktionen`, `404`/`403`-Regel, **Hilfe, Übersicht und `/_openapi` nach Rolle filtern** | mittel |
| **S5** | `token`: `POST /_login`, HMAC-Token mit Ablauf, `nurToken`, Sperre über Config | mittel |
| **S6** | `requestLimit` (wenn benötigt), `audit` | mittel |

Die Anmeldung mit Benutzername und Passwort aus der heutigen `api.php` (`zugangsdaten()`, `anmelden()`, `benutzer.sql`) wird **nicht** übernommen. Kundenschlüssel und Token ersetzen sie.

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
- Ist der SecurityCore aus, gibt es keinen „Authorize“-Schritt. Ist er an, beschreibt `/_openapi` den konfigurierten Header als Sicherheitsschema und enthält **nur die Pfade und Methoden, die die Rolle des Kunden benutzen darf**.

---

## Architektur und Umbau

Die bestehende `api.php` wird **nicht neu geschrieben**, sondern Schritt für Schritt in Klassen zerlegt, während sie weiterläuft. `client/index.php` und Swagger dienen dabei als Test, ob noch alles funktioniert. Am Ende ist `api.php` nur noch ein Einstiegspunkt, der den Kernel startet.

### Von der Funktion zur Klasse

| Heute in `api.php` | Wird zu |
|---|---|
| `antwort()`, `fehler()`, `exceptionBehandeln()` | `Http\Response`, `Http\ErrorHandler` |
| Routing (Abschnitt 6b) | `Http\Router` |
| Methode und Override (6c), `eingabeLesen()` | `Http\Request` |
| `zugangsdaten()`, `anmelden()`, `authDb()`, Prüfung in 6d | **entfällt** (ersetzt durch den optionalen Wächter `kundenschluessel`) |
| `ROLLEN` | optional `Security\RollenWaechter`; ohne SecurityCore nur `aktionen` pro Ressource → `Http\ActionGuard` |
| – (neu) | `Security\SecurityCore`, `Security\Kontext`, `Security\Principal`, Wächter (`IpWaechter`, `KundenschluesselWaechter`, `TokenWaechter` …) |
| `eingabeLesen()` (JSON/Formular) | `Codec\JsonCodec`, `Codec\FormCodec` |
| `db()` (Verbindungsaufbau, Singleton) | `Connection\ConnectionManager` + `Connector\PdoConnector` |
| `spalten()`, `primaerschluessel()`, `primaerschluesselNamen()` | `Driver\PdoDriver::schema()` + `Schema\SchemaCache` |
| `holen()`, `liste()`, `anlegen()`, `aendern()`, `loeschen()` | `Driver\PdoDriver::get/find/create/update/delete` |
| `namenAngleichen()`, `spalteFinden()`, `felderPruefen()`, `ohneSteuerfelder()` | `Mapping\FieldMapper` |
| Verweis-Prüfung, `verwendungPruefen()` | `Mapping\ReferenceChecker` |
| `MYSQL_FEHLER` | Fehlerübersetzung im `PdoDriver` |
| Übersicht (6e) | `Http\Controller\OverviewController` |
| – (neu) | `Help\HelpController`, `Help\HelpBuilder`, `Help\TextRenderer` |
| – (neu) | `Plugin\PluginFinder` (findet Connectoren und Codecs) |

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
│   ├─ bausteine-aktualisieren.php
│   ├─ schluessel-erzeugen.php ← Kundenschlüssel + Hash für die .env (SecurityCore)
│   ├─ klassen-erzeugen.php
│   └─ umziehen.php
├─ cache/                     ← erzeugt (schema.php, bausteine-*.php), nicht im Git
├─ src/
│   ├─ Http/                  ← Kernel, Router, Request, Response, ErrorHandler, ActionGuard, Controller/
│   ├─ Help/                  ← HelpController, HelpBuilder, TextRenderer
│   ├─ Config/                ← ConfigLoader, ConfigValidator
│   ├─ Plugin/                ← PluginFinder
│   ├─ Connection/            ← ConnectionManager, VerbindungFehlgeschlagen
│   ├─ Connector/             ← Connector + Bausteine (automatisch erkannt): PdoConnector, MongoConnector, FileConnector
│   ├─ Codec/                 ← Codec, CodecRegistry + Bausteine (automatisch erkannt): JsonCodec, FormCodec, CsvCodec, …
│   ├─ Driver/                ← Driver, DriverFactory, PdoDriver, MongoDriver, FileDriver
│   ├─ Security/              ← SecurityCore, Kontext, Principal, Waechter + Bausteine (automatisch erkannt):
│   │                            IpWaechter, HttpsWaechter, NurLesenWaechter, KundenschluesselWaechter,
│   │                            TokenWaechter, RollenWaechter, RequestLimitWaechter, AuditWaechter
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
| Verbindung aufbauen | 1–5 ms | einmal pro Request, nur wenn die Datenbank gebraucht wird |
| PluginFinder: Cache lesen 🔜 | Mikrosekunden | ein `filemtime()` + OPcache. In Produktion ohne `filemtime()`. |
| PluginFinder: neu suchen 🔜 | einige ms | nur wenn ein Baustein hinzukommt oder entfernt wird |
| SecurityCore aus (Standard) 🔜 | 0 | keine Sicherheitsklasse wird geladen |
| SecurityCore an 🔜 | Mikrosekunden | Kundenschlüssel (SHA-256), Token (HMAC), Rollen, IP. Kein bcrypt, deshalb kein spürbarer Einfluss auf den Durchsatz. `audit` ~1 ms pro Schreibzugriff. |

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

### Zwei Ebenen

- **Grundschutz**: immer aktiv, nicht abschaltbar (siehe Tabellen unten).
- **Zugriffskontrolle**: optional über den [SecurityCore](#securitycore-optional), standardmäßig aus.

### Standard (SecurityCore aus): Schutz über das Netz

Ist der SecurityCore aus, kann **jeder, der die Adresse erreicht, alle Daten lesen, ändern und löschen**. Das ist eine bewusste Entscheidung. Der Schutz muss dann außerhalb der API liegen:

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

### Mit SecurityCore

Sobald die API außerhalb eines geschützten Netzes erreichbar ist oder mehrere Kunden unterschiedliche Rechte brauchen:

- [ ] `sicherheit.aktiv = true` mit mindestens `kundenschluessel` und `rollen`, oder diese Wächter für die betroffenen Ressourcen eintragen (Variante C).
- [ ] `https` als Wächter aktivieren. Kundenschlüssel und Tokens dürfen nicht unverschlüsselt übertragen werden.
- [ ] Schlüssel nur mit `bin/schluessel-erzeugen.php` erzeugen und nur als Hash in die `.env` schreiben.
- [ ] Für jeden Kunden die kleinste passende Rolle vergeben.
- [ ] Optional `token` mit `nurToken = true`, damit der dauerhafte Schlüssel nur bei `/_login` übertragen wird.
- [ ] `php bin/config-pruefen.php` ausführen. Bei Fehlern startet die API absichtlich nicht (fail closed).

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
- [ ] **Stacktraces im Log** (`error_log((string) $e)`): Unter PHP 8.1 kann bei einem fehlgeschlagenen Verbindungsaufbau das Datenbank-Passwort darin stehen. PHP 8.2+ verwenden und Verbindungsfehler im `ConnectionManager` abfangen.
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
| Code einschleusen über den Baustein-Ordner | `src/Connector/`, `src/Codec/` für den Webserver nicht beschreibbar, in Produktion `automatischSuchen = false` |
| Passwort im Log | Verbindungsfehler abfangen, nur die Meldung loggen, PHP 8.2+ |
| Datenbank nicht erreichbar | `503` statt `500`, Details nur im Log |
| Unbefugter Zugriff (optional) | SecurityCore: `ip`, `kundenschluessel`, `token`, `rollen` |
| Rolle fälschen | Rolle steht nur in der Config, der Header beweist nur, welcher Kunde anfragt |
| Schlüssel ausspähen | nur Hashes gespeichert, `hash_equals()`, nie in URL oder Log, Token mit Ablaufzeit |
| SecurityCore halb konfiguriert | fail closed: die API startet nicht |
| Hilfe verrät fremde Ressourcen | Hilfe, Übersicht und `/_openapi` nach Rolle gefiltert, unsichtbare Ressourcen → `404` |

---

## Bewusst nicht enthalten

| Nicht enthalten | Grund | Stattdessen |
|---|---|---|
| Pflicht-Anmeldung | nicht benötigt | Standard offen, optional [SecurityCore](#securitycore-optional) |
| Anmeldung mit Benutzername und Passwort | durch Kundenschlüssel und Token ersetzt | Wächter `kundenschluessel` + `token` |
| Benutzerverwaltung (Konten-Datenbank) | Kunden stehen in der Config | `sicherheit.kunden` + `bin/schluessel-erzeugen.php` |
| Rollen ohne SecurityCore | ohne Identität keine Rolle | `aktionen` pro Ressource für alle gleich |
| VMs / Container starten oder verwalten | nicht Teil des Projekts | Bereitstellung außerhalb der API |
| Streaming großer Datenmengen | Einfachheit | Seiten mit `limit`/`offset` |
| Backend-Wahl per Request | Sicherheit | Backend in der Config |
| Verschachtelte Daten, eingebettete Verweise | jedes Format soll jede Zeile abbilden | IDs + zweiter Request |
| SQL als Eingabe | Sicherheit | JSON, Formular, CSV, XML |
| Joins über mehrere Ressourcen | folgt aus „nur flach“ | Datenbank-View als `quelle` |
| Persistente Datenbankverbindungen | kein Vorteil, aber Risiko durch offene Transaktionen | eine Verbindung pro Request über den `ConnectionManager` |

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
- [ ] **Pflicht-Anmeldung und Rollen entfernen:** `zugangsdaten()`, `anmelden()`, `authDb()`, `AUTH_*`, `TBL_BENUTZER`, `ROLLEN`, Prüfung in 6d, Block `angemeldet` in der Übersicht. Die Rollen-Idee kommt später optional im SecurityCore zurück (S4).
- [ ] `client/index.php` ohne `Authorization`-Header, `openapi.yaml` ohne `BasicAuth`
- [ ] `benutzer.sql` entfernen
- [ ] SQL-Dump der Datenbank `fahrzeugverwaltung` ins Repo
- [ ] `.gitignore` (`.idea/`, `config/.env`, `cache/`)
- [ ] `API_DEBUG` standardmäßig aus, Zugangsdaten in `.env`
- [ ] `composer.json` mit PSR-4-Autoloading
- [ ] Konstanten → `config/gateway.php` + Prüfung beim Start
- [ ] Mindestversion PHP 8.2 festlegen (oder Stacktraces bei Verbindungsfehlern nicht loggen)

**Phase 2: Zerlegen in Klassen (Verhalten bleibt gleich)**
- [ ] **`ConnectionManager` + `PdoConnector` ersetzen `db()`** (erster Schritt, der Rest von `api.php` läuft unverändert weiter). Verbindungsfehler → `503`.
- [ ] `Response`, `ErrorHandler`, `Router`, `Request`
- [ ] `PdoDriver` mit `schema()` + Schema-Cache, holt die Verbindung beim `ConnectionManager`
- [ ] `FieldMapper`, `ReferenceChecker`
- [ ] `PluginFinder` mit Cache, atomarem Schreiben und den Betriebsarten Entwicklung/Produktion
- [ ] `Connector`- und `Codec`-Bausteine über den `PluginFinder` laden, `bausteine.abschalten` / `ersetzen`
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
- [ ] `bin/bausteine-aktualisieren.php` (sucht immer neu, setzt OPcache zurück bzw. weist auf Neustart hin)
- [ ] `config-pruefen.php` meldet doppelte `typ()`, ungültige Dateien im Baustein-Ordner und fehlende Abhängigkeiten
- [ ] `FileDriver` + `FileConnector`
- [ ] `MongoDriver` + `MongoConnector`, Verweise über Backend-Grenzen
- [ ] `YamlCodec`, `XlsxCodec` (mit eigenem Größenlimit)
- [ ] `/_convert`
- [ ] `bin/klassen-erzeugen.php` (ersetzt `api_outerlayer/`)
- [ ] `bin/umziehen.php`
- [ ] Debug-Header, Lasttest, Schätzwerte durch Messwerte ersetzen

**SecurityCore (optional, unabhängig von Phase 3 und 4, setzt Phase 2 voraus)**
- [ ] **S1** Fundament: `Waechter`-Interface, `Kontext`/`Principal`, Einhängen im Kernel (aus = nichts laden), Variante C, Config-Prüfung (fail closed), `401`/`403`/`429`
- [ ] **S2** `ip` (mit Proxy-Regel), `https`, `nurLesen`
- [ ] **S3** `kundenschluessel`: konfigurierbarer Header + Präfix, Kunden mit Hash, `aktiv`-Schalter, CORS-Header, `Vary`, `bin/schluessel-erzeugen.php`
- [ ] **S4** `rollen`: Rechte pro Ressource, Schnittmenge mit `aktionen`, `404`/`403`, Hilfe, Übersicht und `/_openapi` nach Rolle filtern
- [ ] **S5** `token`: `POST /_login`, HMAC-Token mit Ablauf, `nurToken`
- [ ] **S6** `requestLimit` (wenn benötigt), `audit`

---

## Lizenz

Noch festzulegen.
