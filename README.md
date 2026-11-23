# MyEndPoint – universelle REST-API

Eine REST-API in PHP, die jede freigegebene Datenbanktabelle per HTTP lesbar, anlegbar, änderbar und löschbar macht. Felder, Pflichtfelder und Verweise liest die API **selbst aus der Datenbank**. Eine neue Spalte braucht keine Codeänderung.

Der Branch `api-2.0` baut diese API zu einem **universellen Gateway** aus: mehrere Ein- und Ausgabeformate (JSON, Formular, CSV, XML …), mehrere Speicher-Backends (MariaDB, MongoDB, Dateien) und Rechte pro Ressource, gesteuert über eine zentrale Konfiguration.

> **Stand:** `api.php` ist lauffähig (Version 1, nur MariaDB und JSON). Alles, was in diesem README mit 🔜 markiert ist, ist geplant und noch nicht umgesetzt. Den Fortschritt zeigt die [Roadmap](#roadmap).

---

## Inhalt

- [Festgelegte Entscheidungen](#festgelegte-entscheidungen)
- [Schnellstart (XAMPP)](#schnellstart-xampp)
- [Projektinhalt](#projektinhalt)
- [Überblick: Ist-Stand und Ziel](#überblick-ist-stand-und-ziel)
- [Grundidee von 2.0](#grundidee-von-20)
- [API-Referenz](#api-referenz)
- [Anmeldung und Rollen](#anmeldung-und-rollen)
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
| Datenverarbeitung | **Alles in Arrays**, kein Streaming | einfache Schnittstellen, leicht zu testen |
| Backend-Wahl | **nur über die Konfiguration** | ein Client kann nie bestimmen, wo Daten landen |
| Datenstruktur | **nur flache Datensätze**, Verweise als IDs | jedes Format kann jede Zeile abbilden, auch CSV |
| Felder | **automatisch aus der Datenbank**, Config nur für Ausnahmen | so wie `api.php` es heute schon macht |
| Abwärtskompatibilität | `api.php/<ressource>/<id>`, Formulare und `client/index.php` funktionieren weiter | die Aufgabe baut darauf auf |
| Sprache | **Deutsch** für Config, JSON-Schlüssel und Fehlermeldungen (wie heute), **Englisch** für Klassen und Namespaces (PHP-Konvention) | Clients sehen nur Deutsch, der Code folgt PSR-4 |
| Anmeldung | **Basic Auth bleibt**, Token-Login kommt dazu 🔜 | Basic ist einfach, aber teuer (siehe [Serverauslastung](#serverauslastung-und-anforderungen)) |

---

## Schnellstart (XAMPP)

**Voraussetzung:** PHP 8.1 oder neuer, MariaDB/MySQL, Apache mit `mod_rewrite` (bei XAMPP aktiv).

1. Den Ordner nach `htdocs/MyEndPoint` kopieren.
2. Die Datenbank `fahrzeugverwaltung` mit den Tabellen `fahrzeuge`, `fahrzeugtypen`, `fahrzeugarten`, `treibstoffe` und `fahrzeughalter` anlegen.
   > Ein SQL-Dump dieser Datenbank liegt noch nicht im Repo (siehe [Roadmap](#roadmap)).
3. `benutzer.sql` in phpMyAdmin importieren: oben links auf den Server klicken → Reiter „SQL“ → Inhalt einfügen → OK.
4. Einen Passwort-Hash erzeugen und ein Konto anlegen:

   ```bash
   php -r "echo password_hash('geheim', PASSWORD_DEFAULT);"
   ```

   ```sql
   INSERT INTO benutzerverwaltung.benutzer (username, useremail, password, rolle)
   VALUES ('admin', 'admin@test.com', '<HASH>', 'admin');
   ```

5. Ausprobieren:

   ```bash
   curl -u admin@test.com:geheim http://localhost/MyEndPoint/api.php
   curl -u admin@test.com:geheim http://localhost/MyEndPoint/api.php/fahrzeuge
   ```

6. Im Browser:
   - `http://localhost/MyEndPoint/client/`: Formular-Client
   - `http://localhost/MyEndPoint/docs.html`: Swagger-Doku zum Ausprobieren

---

## Projektinhalt

| Datei / Ordner | Inhalt | Status |
|---|---|---|
| `api.php` | Die API (Version 1), eine Datei, prozedural | ✅ läuft |
| `.htaccess` | URLs ohne `api.php`, reicht den `Authorization`-Header an PHP weiter | ✅ |
| `benutzer.sql` | Konten-Datenbank `benutzerverwaltung`, getrennt von den Fahrzeugdaten | ✅ |
| `client/index.php` | HTML/JS-Client mit `fetch()` und Basic Auth | ✅ |
| `openapi.yaml` | OpenAPI-3-Beschreibung, Pfade von Hand gepflegt | ✅ (wird ersetzt, siehe [Swagger](#api-dokumentation-swagger)) |
| `docs.html` | Swagger UI, lädt `openapi.yaml` | ✅ |
| `Aufgabe.md` | Aufgabenstellung | – |
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
| Filter mit Operatoren (`?leistung[gt]=100`) | ❌ | 🔜 |
| Sortierung, `limit`, `offset` | ✅ | ✅ |
| HTML-Formulare (`_method`, `operation`, ID als Feld) | ✅ | ✅ |
| Basic Auth gegen eigene Konten-DB | ✅ | ✅ |
| Token-Login (`POST /_login`) | ❌ | 🔜 |
| Begrenzung von Fehlversuchen | ❌ | 🔜 |
| Rechte pro HTTP-Methode (global) | ✅ | ✅ als Standard |
| Rechte pro Ressource, Felder verstecken | ❌ | 🔜 |
| Eingabe JSON / Formular | ✅ | ✅ |
| Eingabe CSV, XML, YAML | ❌ | 🔜 |
| Ausgabe nur JSON | ✅ | JSON + CSV, XML, YAML, SQL 🔜 |
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
| **Codec** | Format ↔ neutrale Zeilen | Datenbanken, Rechte |
| **Treiber** | neutrale Zeilen ↔ Speicher, liefert das Schema | Formate, Rechte |
| **FieldMapper** | Namen angleichen, Ausnahmen aus der Config, Prüfung, Verweise | Formate, Speicherart |

Die Rechte prüft die **AccessPolicy**, bevor einer dieser Bausteine aufgerufen wird.

### Die Datenbank ist das Template

Wie in `api.php` heute: Die API fragt die Datenbank, welche Spalten es gibt. Jeder Treiber bekommt dafür eine Methode `schema()`:

| Treiber | Woher kommt das Schema? |
|---|---|
| PdoDriver (MariaDB …) | aus der Datenbank (`SHOW COLUMNS`, `INFORMATION_SCHEMA`), wie heute `spalten()` |
| FileDriver (CSV …) | Feldnamen aus der Kopfzeile, Typen aus der Config (sonst `string`) |
| MongoDriver | MongoDB ist schemalos, **die Felder stehen vollständig in der Config** |

Die Konfiguration muss deshalb nur Ausnahmen enthalten: versteckte Felder, Umbenennungen, eigene Rechte.

### Ablauf eines Requests

```
 1. Request annehmen          Größe prüfen                         → 413
 2. Router                    api.php/<ressource>/<id?>             → 404
 3. Methode bestimmen         echte Methode oder _method/Override   → 400
 4. Anmeldung                 Basic Auth oder Token                 → 401
 5. AccessPolicy              darf die Rolle diese Aktion?          → 403
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

### Adressen

Alle drei Schreibweisen sind gleichwertig (die beiden kurzen brauchen die `.htaccess`):

```
http://localhost/MyEndPoint/api.php/fahrzeuge/5
http://localhost/MyEndPoint/api/fahrzeuge/5
http://localhost/MyEndPoint/fahrzeuge/5
```

### Endpunkte

| Methode | Pfad | Wirkung | Recht |
|---|---|---|---|
| `GET` | `api.php` | Übersicht: Ressourcen, Felder, eigene Rechte | angemeldet |
| `GET` | `api.php/<ressource>` | Liste mit Filter, Sortierung, Seiten | `r` |
| `GET` | `api.php/<ressource>/<id>` | ein Datensatz | `r` |
| `POST` | `api.php/<ressource>` | anlegen → `201` + `Location`-Header | `c` |
| `PUT` / `PATCH` | `api.php/<ressource>/<id>` | ändern (nur mitgeschickte Felder) | `u` |
| `DELETE` | `api.php/<ressource>/<id>` | löschen | `d` |
| `OPTIONS` | beliebig | CORS-Vorabanfrage → `204` | – |
| `POST` | `api.php/_login` | Token holen 🔜 | – |
| `GET` | `api.php/_openapi` | OpenAPI-Beschreibung, automatisch erzeugt 🔜 | angemeldet |
| `POST` | `api.php/_convert` | Format umwandeln, nichts speichern 🔜 | laut Config |

Freigegebene Ressourcen: `fahrzeuge`, `fahrzeugtypen`, `fahrzeugarten`, `treibstoffe`, `fahrzeughalter`.

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
| `<spalte>[op]=<wert>` | `?Leistung[gt]=100` | mit Operator | 🔜 |
| `fields` | `?fields=FID,Marke` | nur diese Felder ausgeben | 🔜 |
| `format` | `?format=csv` | Ausgabeformat statt `Accept`-Header | 🔜 |

Groß-/Kleinschreibung der Spaltennamen ist egal (`fid` = `FID`). Leere Filterwerte werden ignoriert. Unbekannte Spalten führen zu `400` mit der Liste der erlaubten Felder.

**Geplante Operatoren** (PHP macht aus `?x[gt]=1` automatisch ein Array):

| Operator | Bedeutung | Beispiel |
|---|---|---|
| `eq` | gleich (Standard) | `?FID[eq]=5` |
| `ne` | ungleich | `?FTID[ne]=2` |
| `lt`, `lte` | kleiner (gleich) | `?Leistung[lt]=100` |
| `gt`, `gte` | größer (gleich) | `?Baujahr[gte]=2015` |
| `in` | in Liste | `?FTID[in]=1,2,3` |
| `null` | ist (nicht) leer | `?FHID[null]=true` |

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

🔜 Zusätzlich stehen die Seitenangaben **immer** in den Headern, damit sie auch bei CSV und XML verfügbar sind. Diese Formate enthalten nur die Zeilen:

```http
X-Total-Count: 42
X-Limit: 100
X-Offset: 0
```

**Einzelner Datensatz, Anlegen, Ändern**: der Datensatz so, wie er jetzt in der Datenbank steht. Beim Anlegen zusätzlich `201 Created` und `Location: …/api.php/fahrzeuge/43`.

**Löschen**:

```json
{ "meldung": "Datensatz \"43\" wurde gelöscht.", "geloescht": { "FID": 43, "Marke": "VW" } }
```

**Übersicht** (`GET api.php`):

```json
{
  "angemeldet": { "name": "admin", "email": "admin@test.com", "rolle": "admin",
                  "darf": ["GET", "POST", "PUT", "PATCH", "DELETE"] },
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

Ein Client kann daraus Formulare und Auswahllisten bauen. `client/index.php` macht genau das.

### Fehler

Fehler kommen immer als JSON, auch wenn ein anderes Ausgabeformat angefragt wurde:

```json
{ "fehler": "Ungültige Eingaben.", "details": ["Pflichtfeld \"Marke\" fehlt."] }
```

| Status | Wann |
|---|---|
| `400` | ungültige Eingabe oder Parameter, Pflichtfeld fehlt, Verweis zeigt ins Leere |
| `401` | nicht angemeldet, E-Mail/Passwort falsch |
| `403` | Rolle darf diese Aktion nicht |
| `404` | Ressource oder Datensatz unbekannt |
| `405` | Methode passt nicht zum Pfad |
| `406` | Ausgabeformat nicht verfügbar 🔜 |
| `409` | doppelter Wert, oder Datensatz wird noch verwendet |
| `413` | Anfrage zu groß 🔜 |
| `415` | Eingabeformat nicht verfügbar 🔜 |
| `429` | zu viele Fehlversuche bei der Anmeldung 🔜 |
| `500` | interner Fehler |

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

### Beispiele

```bash
# Liste, gefiltert und sortiert
curl -u admin@test.com:geheim \
     "http://localhost/MyEndPoint/api.php/fahrzeuge?Marke=VW*&sort=-Leistung&limit=10"

# Anlegen (JSON)
curl -u admin@test.com:geheim -X POST \
     -H "Content-Type: application/json" \
     -d '{"Marke": "VW", "Modell": "Golf", "FTID": 3}' \
     http://localhost/MyEndPoint/api.php/fahrzeuge

# Ändern (nur ein Feld)
curl -u admin@test.com:geheim -X PATCH \
     -H "Content-Type: application/json" \
     -d '{"Leistung": 110}' \
     http://localhost/MyEndPoint/api.php/fahrzeuge/43

# Löschen per Formular-Daten
curl -u admin@test.com:geheim -X POST \
     -d "_method=DELETE&FID=43" \
     http://localhost/MyEndPoint/api.php/fahrzeuge

# 🔜 Liste als CSV
curl -u admin@test.com:geheim -H "Accept: text/csv" \
     http://localhost/MyEndPoint/api.php/fahrzeuge

# 🔜 Mehrere Datensätze per CSV anlegen
curl -u admin@test.com:geheim -X POST \
     -H "Content-Type: text/csv" --data-binary @fahrzeuge.csv \
     http://localhost/MyEndPoint/api.php/fahrzeuge
```

> Die Spaltennamen in den Beispielen sind Platzhalter. Die echten Namen liefert `GET api.php`.

---

## Anmeldung und Rollen

### Heute: HTTP Basic Auth

Jede Anfrage schickt E-Mail (oder Benutzername) und Passwort mit:

```http
Authorization: Basic <Base64 von "email:passwort">
```

- HTML-Formulare können keine Header setzen und nutzen ersatzweise die Felder `_email` und `_passwort`.
- Die Konten liegen in der eigenen Datenbank `benutzerverwaltung` (siehe `benutzer.sql`), die Passwörter nur als Hash von `password_hash()`.
- **Die Rolle schickt der Client nie mit.** Sie steht beim Konto in der Spalte `rolle`.
- Absichtlich ohne `WWW-Authenticate`-Header, damit der Browser kein eigenes Login-Fenster öffnet.

### 🔜 Zusätzlich: Token-Login

Basic Auth prüft bei **jedem** Request das Passwort mit bcrypt. Das ist absichtlich langsam (grob 50–100 ms) und begrenzt den Durchsatz stark (siehe [Serverauslastung](#serverauslastung-und-anforderungen)). Deshalb kommt ein Login dazu, der das Passwort nur einmal prüft:

```bash
curl -X POST -u admin@test.com:geheim http://localhost/MyEndPoint/api.php/_login
```

```json
{ "token": "eyJhbGciOiJIUzI1NiJ9...", "gueltigBis": "2026-11-23T22:00:00+01:00", "rolle": "admin" }
```

Alle weiteren Requests schicken nur noch das Token. Dessen Prüfung (HMAC) dauert Mikrosekunden:

```http
Authorization: Bearer eyJhbGciOiJIUzI1NiJ9...
```

- Basic Auth bleibt für einfache Clients, Swagger und die Aufgabe erhalten.
- `client/index.php` wird auf Token umgestellt.
- **Fehlversuche werden begrenzt**: nach 5 Fehlversuchen pro IP innerhalb einer Minute folgt `429`.

### Rollen und Rechte

**Heute** gelten die Rechte global pro HTTP-Methode:

```php
const ROLLEN = [
    'nutzer' => ['GET'],
    'admin'  => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'],
];
```

**🔜 Künftig** in CRUD-Buchstaben, als globaler Standard plus Override pro Ressource:

| Buchstabe | Aktion | HTTP-Methode |
|---|---|---|
| `c` | anlegen | `POST` |
| `r` | lesen | `GET` |
| `u` | ändern | `PUT`, `PATCH` |
| `d` | löschen | `DELETE` |

```php
'rollen' => [                     // Standard für alle Ressourcen
    'nutzer' => 'r',
    'admin'  => 'crud',
],
'ressourcen' => [
    'fahrzeughalter' => [
        'rechte' => ['nutzer' => '', 'admin' => 'crud'],   // Override: Personendaten
    ],
],
```

Das Recht einer Rolle auf eine Ressource ergibt sich so:

```
Recht = ressourcen[x].rechte[rolle]  ??  rollen[rolle]  ??  ''   (nichts)
```

Unbekannte Rollen dürfen nichts. Rolle stimmt nicht → `403`.

---

## Konfiguration

### Heute: Konstanten in `api.php`

| Konstante | Bedeutung |
|---|---|
| `DB2_DSN`, `DB2_USER`, `DB2_PASS` | Verbindung zur Fahrzeug-Datenbank |
| `AUTH_DSN`, `AUTH_USER`, `AUTH_PASS`, `TBL_BENUTZER` | Verbindung zur Konten-Datenbank |
| `RESSOURCEN` | Whitelist: Name in der URL → Tabelle |
| `ROLLEN` | Rolle → erlaubte HTTP-Methoden |
| `LIMIT_STANDARD`, `LIMIT_MAXIMUM` | Seitengröße 100 / höchstens 1000 |
| `API_DEBUG` | genaue Fehlermeldungen in der Antwort |
| `MYSQL_FEHLER` | MySQL-Fehlernummer → Status + Meldung |

### 🔜 Künftig: `config/gateway.php` + `.env`

Die Konfiguration wird ein PHP-Array. OPcache hält es im Speicher, es muss also nicht bei jedem Request geparst werden. Passwörter stehen nicht darin, sondern in `config/.env`.

```php
<?php
// config/gateway.php
return [

    'debug' => getenv('API_DEBUG') === '1',      // Standard: aus

    'grenzen' => [
        'maxAnfrageBytes' => 5 * 1024 * 1024,     // größer → 413
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
        'konten' => [
            'treiber' => 'pdo',
            'dsn'     => 'mysql:host=127.0.0.1;dbname=benutzerverwaltung;charset=utf8mb4',
            'user'    => getenv('AUTH_USER'),
            'pass'    => getenv('AUTH_PASS'),
        ],
        'archiv' => [
            'treiber'   => 'mongo',
            'uri'       => getenv('MONGO_URI'),
            'datenbank' => 'fahrzeugarchiv',
        ],
    ],

    'anmeldung' => [
        'verbindung'     => 'konten',
        'tabelle'        => 'benutzer',
        'loginSpalten'   => ['useremail', 'username'],   // anmelden mit E-Mail oder Name
        'passwortSpalte' => 'password',
        'rollenSpalte'   => 'rolle',
        'verfahren'      => ['basic', 'token'],
        'tokenSecret'    => getenv('TOKEN_SECRET'),      // Pflicht bei 'token', min. 32 Zeichen
        'tokenGueltigkeit' => 8 * 3600,                  // Sekunden
        'maxFehlversuche'  => 5,                         // pro IP und Minute
    ],

    'rollen' => [
        'nutzer' => 'r',
        'admin'  => 'crud',
    ],

    'ressourcen' => [
        // Felder kommen automatisch aus der Datenbank
        'fahrzeuge'     => ['verbindung' => 'fahrzeuge', 'quelle' => 'fahrzeuge'],
        'fahrzeugtypen' => ['verbindung' => 'fahrzeuge', 'quelle' => 'fahrzeugtypen'],
        'fahrzeugarten' => ['verbindung' => 'fahrzeuge', 'quelle' => 'fahrzeugarten'],
        'treibstoffe'   => ['verbindung' => 'fahrzeuge', 'quelle' => 'treibstoffe'],

        // Ausnahmen nur, wo nötig
        'fahrzeughalter' => [
            'verbindung' => 'fahrzeuge',
            'quelle'     => 'fahrzeughalter',
            'felder'     => ['Strasse' => ['versteckt' => true]],
            'rechte'     => ['nutzer' => '', 'admin' => 'crud'],
        ],

        // MongoDB hat kein Schema → Felder vollständig angeben
        'wartungen' => [
            'verbindung' => 'archiv',
            'quelle'     => 'wartungen',
            'felder' => [
                'id'      => ['spalte' => '_id', 'typ' => 'string', 'nurLesen' => true, 'pk' => true],
                'FID'     => ['typ' => 'int', 'pflicht' => true],
                'datum'   => ['typ' => 'datetime', 'pflicht' => true],
                'notiz'   => ['typ' => 'string'],
            ],
            'verweise' => ['FID' => 'fahrzeuge'],   // Verweis über Backend-Grenzen
            'rechte'   => ['nutzer' => 'r', 'admin' => 'crud'],
        ],
    ],
];
```

```dotenv
# config/.env – NICHT ins Git
DB_USER=api
DB_PASS=...
AUTH_USER=api_anmeldung
AUTH_PASS=...
TOKEN_SECRET=ein-langer-zufaelliger-wert-mindestens-32-zeichen
MONGO_URI=mongodb://127.0.0.1:27017
API_DEBUG=0
```

> Die Spalten `Strasse` und die Ressource `wartungen` sind Beispiele für die Möglichkeiten der Config, nicht Teil der heutigen Datenbank.

### Ressourcen-Optionen

| Option | Pflicht | Bedeutung |
|---|---|---|
| `verbindung` | ja | Name aus `verbindungen` |
| `quelle` | ja | Tabelle, Collection oder Dateiname |
| `felder` | bei MongoDB | Ausnahmen bzw. vollständige Feldliste (siehe unten) |
| `rechte` | nein | Override der globalen `rollen` |
| `verweise` | nein | `Feld => Ressource`, für Verweise ohne Fremdschlüssel |
| `filterbar` | nein | nur diese Felder als Filter erlauben (Standard: alle sichtbaren) |
| `sortierbar` | nein | nur diese Felder zum Sortieren erlauben (Standard: alle sichtbaren) |

### Feld-Optionen

Bei SQL-Tabellen werden alle Felder automatisch erkannt. Hier wird nur eingetragen, was davon **abweicht**:

| Option | Bedeutung |
|---|---|
| `versteckt` | Feld wird weder ausgegeben noch angenommen |
| `nurLesen` | wird ausgegeben, kann aber nicht geschrieben werden |
| `nurSchreiben` | kann geschrieben werden, wird **nie** ausgegeben (z. B. Passwörter) |
| `name` | anderer Name in der API (`'Fahrzeug_Name' => ['name' => 'name']`) |
| `aliase` | weitere erlaubte Eingabenamen, z. B. für CSV-Kopfzeilen aus Excel |
| `spalte` | echter Spaltenname, wenn er vom API-Namen abweicht (MongoDB: `_id`) |
| `typ` | `int`, `float`, `decimal`, `string`, `bool`, `datetime`, `passwort` |
| `pflicht`, `pk`, `min`, `max` | Prüfregeln, bei SQL automatisch aus der Datenbank |

### Prüfung beim Start

Die Konfiguration wird geprüft, bevor der erste Request bearbeitet wird:

- Jede Ressource verweist auf eine existierende Verbindung, deren Treiber installiert ist.
- Jede Rechte-Angabe besteht nur aus `c`, `r`, `u`, `d`.
- Verweise zeigen auf existierende Ressourcen.
- Ist `token` als Verfahren aktiv, muss `tokenSecret` gesetzt und mindestens 32 Zeichen lang sein. **Es gibt keinen Standardwert.**

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
- **Formular**: wie heute. Steuerfelder (`_method`, `_email` …) werden entfernt, bevor gespeichert wird.
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
    public function schema(): array;                         // Felder, Typen, PK, Verweise
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

**Heute** beschreibt `openapi.yaml` jeden Pfad von Hand (`/fahrzeuge/{FID}`, `/fahrzeugtypen/{FTID}` …). `docs.html` zeigt sie mit Swagger UI an. Kommt eine Spalte oder Tabelle dazu, muss die Datei mitgepflegt werden, sonst stimmt die Doku nicht mehr.

**🔜 Künftig** erzeugt die API die Beschreibung selbst aus Schema und Config:

```
GET api.php/_openapi   → OpenAPI 3 als JSON
```

- `docs.html` lädt diese URL statt der statischen Datei.
- Die erklärenden Texte aus der heutigen `openapi.yaml` (Anmeldung, Rollen, URL-Aufbau) bleiben als Vorlage erhalten. Nur Pfade, Felder und Typen werden generiert.
- Jeder sieht nur die Ressourcen und Methoden, die seine Rolle darf.

---

## Architektur und Umbau

Die bestehende `api.php` wird **nicht neu geschrieben**, sondern Schritt für Schritt in Klassen zerlegt, während sie weiterläuft. `client/index.php` und Swagger dienen dabei als Test, ob noch alles funktioniert. Am Ende ist `api.php` nur noch ein Einstiegspunkt, der den Kernel startet.

### Von der Funktion zur Klasse

| Heute in `api.php` | Wird zu |
|---|---|
| `antwort()`, `fehler()`, `exceptionBehandeln()` | `Http\Response`, `Http\ErrorHandler` |
| Routing (Abschnitt 6b) | `Http\Router` |
| Methode und Override (6c), `eingabeLesen()` | `Http\Request` |
| `zugangsdaten()`, `anmelden()`, `authDb()` | `Auth\BasicAuthenticator` (+ `Auth\TokenAuthenticator` 🔜) |
| `ROLLEN`, Prüfung in 6d | `Policy\AccessPolicy` |
| `eingabeLesen()` (JSON/Formular) | `Codec\JsonCodec`, `Codec\FormCodec` |
| `db()`, `spalten()`, `primaerschluessel()`, `primaerschluesselNamen()` | `Driver\PdoDriver::schema()` + `Schema\SchemaCache` |
| `holen()`, `liste()`, `anlegen()`, `aendern()`, `loeschen()` | `Driver\PdoDriver::get/find/create/update/delete` |
| `namenAngleichen()`, `spalteFinden()`, `felderPruefen()`, `ohneSteuerfelder()` | `Mapping\FieldMapper` |
| Verweis-Prüfung, `verwendungPruefen()` | `Mapping\ReferenceChecker` |
| `MYSQL_FEHLER` | Fehlerübersetzung im `PdoDriver` |
| Übersicht (6e) | `Http\Controller\OverviewController` |

### Ziel-Ordnerstruktur

```
MyEndPoint/
├─ api.php                    ← Einstiegspunkt (bleibt, für alte URLs)
├─ .htaccess
├─ config/
│   ├─ gateway.php
│   ├─ .env                   ← nicht im Git
│   └─ .env.example
├─ bin/                       ← Kommandozeilen-Werkzeuge
│   ├─ config-pruefen.php
│   ├─ schema-aktualisieren.php
│   ├─ klassen-erzeugen.php
│   └─ umziehen.php
├─ cache/                     ← erzeugtes Schema, nicht im Git
├─ src/
│   ├─ Http/                  ← Kernel, Router, Request, Response, ErrorHandler, Controller/
│   ├─ Config/                ← ConfigLoader, ConfigValidator
│   ├─ Auth/                  ← BasicAuthenticator, TokenAuthenticator, LoginThrottle, Principal
│   ├─ Policy/                ← AccessPolicy
│   ├─ Codec/                 ← Codec, CodecRegistry, JsonCodec, FormCodec, CsvCodec, XmlCodec, …
│   ├─ Driver/                ← Driver, DriverFactory, PdoDriver, MongoDriver, FileDriver
│   ├─ Schema/                ← SchemaCache
│   ├─ Mapping/               ← FieldMapper, TypeCaster, ReferenceChecker, Record
│   └─ Query/                 ← Query, QueryParser
├─ client/index.php
├─ docs.html
├─ benutzer.sql
├─ tests/
└─ composer.json              ← Autoloading (PSR-4) + optionale Pakete
```

---

## Serverauslastung und Anforderungen

> **Alle Zahlen sind Schätzungen** aus dem Aufbau des Codes und typischen Werten von PHP und MariaDB. Messwerte ersetzen sie, sobald ein Lasttest gelaufen ist (siehe [Messen](#messen-statt-schätzen)).

### Die zwei größten Kostenpunkte heute

**1. Basic Auth: bcrypt bei jedem Request.** `password_verify()` ist absichtlich langsam, je nach CPU grob **50–100 ms** pro Prüfung. Das ist mehr als der ganze Rest des Requests zusammen.

| Anmeldung | Kosten pro Request | Durchsatz pro CPU-Kern (grob) |
|---|---|---|
| Basic Auth (heute) | 50–100 ms CPU | **10–20 Requests/s** |
| Token (🔜) | < 0,1 ms | 100–300 Requests/s, dann begrenzt durch die Datenbank |

**2. Schema bei jedem Request auslesen.** `SHOW COLUMNS` und die Abfragen auf `INFORMATION_SCHEMA` kosten je nach Server einige bis mehrere zehn Millisekunden. Beim `DELETE` werden alle freigegebenen Tabellen untersucht. Mit dem Schema-Cache (🔜) fällt das weg.

### Speicherverbrauch pro Request

Weil alles in Arrays verarbeitet wird, liegen die Daten zeitweise mehrfach im Speicher (Roh-Body → Array → geprüftes Array → Ausgabe). Faustregel: **Daten im PHP-Array brauchen etwa das 5- bis 10-Fache ihrer Dateigröße.**

| Request | Geschätzter Peak-RAM pro PHP-Prozess |
|---|---|
| `GET` einzelner Datensatz | 2–5 MB |
| `GET` Liste mit 100 Zeilen (Standard) | 3–8 MB |
| `GET` Liste mit 1000 Zeilen (Maximum) | 10–25 MB |
| 🔜 `POST` mit 5 MB CSV/JSON | 40–80 MB |
| 🔜 `POST` mit 1 MB XLSX | 50–150 MB (PhpSpreadsheet: grob 1 KB pro Zelle) |

### Empfohlene Servergrößen

| Einsatz | Nutzer gleichzeitig | CPU | RAM | Speicher |
|---|---|---|---|---|
| Entwicklung / Ausbildung (XAMPP) | < 10 | 1–2 Kerne | 2 GB | 10 GB |
| Interner Betrieb, Basic Auth | bis ~20 aktive | 2 vCPU | 2–4 GB | 20 GB SSD |
| Interner Betrieb, Token-Login | 50–200 | 2 vCPU | 4 GB | 40 GB SSD |
| mit MongoDB auf demselben Server | wie oben | +1 vCPU | +1–2 GB | +20 GB |

**MongoDB im Container begrenzen.** Standardmäßig nimmt sich MongoDB als Cache die Hälfte von (RAM − 1 GB):

```yaml
mongo:
  image: mongo:8
  command: ["--wiredTigerCacheSizeGB", "1"]
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

PHP-FPM: `pm.max_children ≈ RAM für PHP ÷ durchschnittlicher Speicher pro Prozess` (grob 40–60 MB). Bei 4 GB RAM mit MariaDB auf demselben Server sind etwa 10–12 Prozesse sinnvoll.

### Typische Engpässe

1. **Basic Auth bei vielen Requests:** Token-Login nutzen.
2. **Fehlende Indizes:** Jede Spalte, nach der oft gefiltert oder sortiert wird, braucht einen Index. Fremdschlüssel-Spalten (`FTID` …) sowieso, weil die Lösch-Prüfung danach sucht.
3. **Große Imports:** mehrere 5-MB-Uploads gleichzeitig verdoppeln kurzzeitig den RAM-Bedarf.
4. **`limit=1000` in XML/XLSX:** kostet ein Vielfaches von 100 Zeilen JSON.

### Messen statt schätzen

🔜 Mit `API_DEBUG=1` hängt die API an jede Antwort echte Messwerte:

```http
X-Debug-Memory: 4.1 MB
X-Debug-Time: 73.2 ms
```

Lasttest, z. B. mit Apache Bench (bei XAMPP unter `apache/bin/ab`):

```bash
ab -n 500 -c 10 -A admin@test.com:geheim \
   "http://localhost/MyEndPoint/api.php/fahrzeuge?limit=50"
```

| Messwert | Zielwert (interner Betrieb) |
|---|---|
| Antwortzeit, 95. Perzentil | < 200 ms für normale Listen |
| RAM der PHP-Prozesse | stabil, kein stetiges Wachstum |
| langsame SQL-Abfragen (`slow_query_log`) | keine über 100 ms |

---

## Sicherheit

### Was `api.php` schon richtig macht

| Risiko | Schutz |
|---|---|
| SQL-Injection über Werte | Prepared Statements, `ATTR_EMULATE_PREPARES = false` |
| SQL-Injection über Tabellen-/Spaltennamen | nur aus `RESSOURCEN` bzw. dem Schema, zusätzlich in Backticks |
| Zugriff auf beliebige Tabellen | Whitelist `RESSOURCEN` |
| Konten über die Fahrzeug-API auslesen | getrennte Datenbank und Verbindung |
| Rolle fälschen | Rolle kommt aus der Datenbank, nie vom Client |
| Passwörter im Klartext | `password_hash()` / `password_verify()`, Hash wird nie ausgegeben |
| Datensätze mit kaputten Verweisen | Prüfung beim Speichern und Löschen |
| HTML-Fehlerseiten | jede Exception wird zu einer JSON-Antwort |

### Was vor dem Einsatz außerhalb von localhost geändert werden muss

- [ ] **`API_DEBUG` steht auf `true`.** Damit gehen SQL-Fehlermeldungen an den Client, und die Anmeldung verrät, dass ein Konto existiert und ein Klartext-Passwort hat. Standard muss `false` sein, einschalten nur über `.env`.
- [ ] **Datenbank-Benutzer `root` ohne Passwort.** Eigene Benutzer anlegen: für die Fahrzeug-DB nur `SELECT, INSERT, UPDATE, DELETE`, für die Konten-DB nur `SELECT` (siehe `benutzer.sql`).
- [ ] **Zugangsdaten stehen im Code.** Sie gehören in `config/.env` und damit nicht ins Repo.
- [ ] **`Access-Control-Allow-Origin: *`.** Erlaubte Adressen in der Config festlegen.
- [ ] **Kein Schutz gegen Durchprobieren von Passwörtern.** Mit Basic Auth ist jeder Request ein Versuch. Fehlversuche begrenzen (`429`).
- [ ] **Unterschiedliche Antwortzeit** bei unbekanntem Konto (keine bcrypt-Prüfung) und falschem Passwort (bcrypt-Prüfung). Damit lässt sich herausfinden, welche Konten existieren. Abhilfe: auch bei unbekanntem Konto einen Dummy-Hash prüfen.
- [ ] **HTTPS erzwingen.** Bei Basic Auth steht das Passwort in jedem Request (nur Base64, nicht verschlüsselt).
- [ ] **`.idea/` ist eingecheckt.** In die `.gitignore` aufnehmen.

### Geplante Schutzmaßnahmen der 2.0

| Risiko | Schutz |
|---|---|
| Speicher-Erschöpfung | `maxAnfrageBytes`, `limitMaximum`, eigenes XLSX-Limit |
| XML External Entities (XXE) | Entity-Loading deaktiviert |
| CSV-Injection (Formeln in Excel) | Zellen mit `=`, `+`, `-`, `@` am Anfang werden bei der Ausgabe maskiert |
| NoSQL-Injection | nur skalare Werte, Operatoren aus fester Liste, Regex maskiert |
| Personendaten sichtbar | `versteckt` / `nurSchreiben` pro Feld, `rechte` pro Ressource |
| Token-Fälschung | kein Standard-Secret, mindestens 32 Zeichen, sonst startet die API nicht |

---

## Bewusst nicht enthalten

| Nicht enthalten | Grund | Stattdessen |
|---|---|---|
| Streaming großer Datenmengen | Einfachheit | Seiten mit `limit`/`offset` |
| Backend-Wahl per Request | Sicherheit | Backend in der Config |
| Verschachtelte Daten, eingebettete Verweise | jedes Format soll jede Zeile abbilden | IDs + zweiter Request |
| SQL als Eingabe | Sicherheit | JSON, Formular, CSV, XML |
| Joins über mehrere Ressourcen | folgt aus „nur flach“ | Datenbank-View als `quelle` |
| Benutzerverwaltung über die API | Konten-DB ist absichtlich getrennt | phpMyAdmin / eigenes Admin-Tool |

---

## Roadmap

### Version 1 (`api.php`)

- [x] CRUD für alle Tabellen aus `RESSOURCEN`
- [x] Felder, Pflichtfelder, Primärschlüssel automatisch aus der Datenbank
- [x] Verweise über Fremdschlüssel und Namensregel, Prüfung beim Speichern und Löschen
- [x] Filter mit `*`, Sortierung, `limit`/`offset`
- [x] HTML-Formulare (`_method`, `operation`, ID als Feld)
- [x] Basic Auth gegen getrennte Konten-Datenbank, Rollen pro Methode
- [x] Übersetzung von MySQL-Fehlern in passende Statuscodes
- [x] Übersicht `GET api.php`, Client, Swagger-Doku

### Version 2.0

**Phase 1: Aufräumen und Grundlage**
- [ ] SQL-Dump der Datenbank `fahrzeugverwaltung` ins Repo
- [ ] `.gitignore` (`.idea/`, `config/.env`, `cache/`)
- [ ] `API_DEBUG` standardmäßig aus, Zugangsdaten in `.env`
- [ ] `composer.json` mit PSR-4-Autoloading
- [ ] Konstanten → `config/gateway.php` + Prüfung beim Start

**Phase 2: Zerlegen in Klassen (Verhalten bleibt gleich)**
- [ ] `Response`, `ErrorHandler`, `Router`, `Request`
- [ ] `PdoDriver` mit `schema()` + Schema-Cache
- [ ] `FieldMapper`, `ReferenceChecker`
- [ ] `BasicAuthenticator`, `AccessPolicy` (globale Rollen)
- [ ] `JsonCodec`, `FormCodec`
- [ ] `api.php` startet nur noch den Kernel. Client und Swagger funktionieren unverändert.

**Phase 3: Neue Funktionen**
- [ ] Rechte pro Ressource, Felder verstecken / nur lesen / nur schreiben
- [ ] `POST /_login` mit Token, Fehlversuche begrenzen
- [ ] `client/index.php` auf Token umstellen
- [ ] Filter-Operatoren, `fields`, Seitenangaben als Header
- [ ] `CsvCodec`, `XmlCodec` (Ein- und Ausgabe), `SqlCodec` (nur Ausgabe)
- [ ] `/_openapi`, `docs.html` umstellen, `openapi.yaml` entfernen

**Phase 4: Weitere Backends und Werkzeuge**
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
