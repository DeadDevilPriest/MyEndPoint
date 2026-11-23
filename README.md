# Data Gateway

Ein universelles HTTP-REST-Gateway in PHP. Es nimmt Anfragen in verschiedenen Formaten entgegen (JSON, CSV, XML, YAML …), übersetzt sie über ein zentrales Mapping in der Konfiguration und speichert bzw. liest die Daten aus verschiedenen Backends (MariaDB, MongoDB, Dateien …). Zugriffe werden über Rollen mit getrennten Rechten für **Lesen, Anlegen, Ändern und Löschen** gesteuert.

> **Status: Konzeptphase.** Dieses README beschreibt die geplante Architektur und das Zielverhalten. Der Umsetzungsstand ist in der [Roadmap](#roadmap) festgehalten.

---

## Inhalt

- [Features](#features)
- [Grundidee](#grundidee)
- [Designentscheidungen](#designentscheidungen)
- [Anforderungen](#anforderungen)
- [Installation](#installation)
- [Ordnerstruktur](#ordnerstruktur)
- [Konfiguration](#konfiguration)
- [Authentifizierung und Rollen](#authentifizierung-und-rollen)
- [API-Referenz](#api-referenz)
- [Formate (Codecs)](#formate-codecs)
- [Datenmodell](#datenmodell)
- [Speicher-Backends (Treiber)](#speicher-backends-treiber)
- [Fehlerbehandlung](#fehlerbehandlung)
- [Grenzen und Performance](#grenzen-und-performance)
- [Serverauslastung und Anforderungen](#serverauslastung-und-anforderungen)
- [Sicherheit](#sicherheit)
- [Erweitern](#erweitern)
- [Bewusst nicht enthalten](#bewusst-nicht-enthalten)
- [Roadmap](#roadmap)

---

## Features

- **Ein Einstiegspunkt für alle Daten**: `GET/POST/PUT/PATCH/DELETE /api/{ressource}/{id?}`
- **Format-unabhängig**: Eingabe über `Content-Type`, Ausgabe über `Accept`. CSV rein und XML raus funktioniert ohne Extra-Code.
- **Backend-unabhängig**: Ob eine Ressource in MariaDB, MongoDB oder einer CSV-Datei liegt, steht nur in der Konfiguration.
- **Mapping statt Code**: Feldnamen, Aliase, Typen, Pflichtfelder und Sichtbarkeit werden in `config/gateway.php` definiert.
- **Rollenbasierte Rechte** pro Ressource in kompakter CRUD-Schreibweise (`'admin' => 'crud'`), inklusive „nur eigene Datensätze“ (`:own`).
- **Fail fast**: Eine fehlerhafte Konfiguration verhindert den Start, statt zur Laufzeit Lücken zu öffnen.
- **Reiner Übersetzer**: `POST /api/_convert` wandelt Formate um, ohne etwas zu speichern.
- **Selbstbeschreibend**: `GET /api/_meta` listet Ressourcen, Formate und die eigenen Rechte.

---

## Grundidee

### Hub-and-Spoke statt „jedes Format mit jedem“

Alle Formate und Backends sprechen mit einem **neutralen Datensatz** in der Mitte. Ein neues Format braucht dann genau einen Codec und nicht einen Konverter pro anderem Format.

```
 Eingabe (HTTP)                                         Ausgabe (HTTP)
 JSON ─┐                                             ┌─→ JSON
 CSV ──┤                                             ├─→ CSV
 XML ──┼─→ Codec::decode ─→ [ neutrale Zeilen ] ─────┼─→ XML
 YAML ─┘                         │     ▲             ├─→ YAML
                                 ▼     │             └─→ SQL (INSERT)
                              FieldMapper
                                 │     ▲
                                 ▼     │
                       Driver (MariaDB | MongoDB | Datei)
```

### Drei streng getrennte Bausteine

| Baustein | Aufgabe | Weiß nichts über |
|---|---|---|
| **Codec** | Format ↔ neutrale Zeilen | Datenbanken, Rechte |
| **Driver** | neutrale Zeilen ↔ Speicher | Formate, Rechte |
| **Mapper** | API-Felder ↔ Speicher-Spalten, Typen, Validierung | Formate, Speicherart |

Rechte prüft die **AccessPolicy**, bevor einer dieser Bausteine überhaupt aufgerufen wird.

### Codecs werden doppelt genutzt

Ein Format kann Übertragungsformat (HTTP-Body) **und** Speicherformat sein. Der `FileDriver` verwendet deshalb dieselben Codecs wie die HTTP-Schicht:

```
FileDriver(CsvCodec)   → CSV-Datei als Speicher
FileDriver(XmlCodec)   → XML-Datei als Speicher
FileDriver(JsonCodec)  → JSON-Datei als Speicher
```

### Ablauf eines Requests

```
1. Request annehmen         → Größenlimit prüfen (413)
2. Router                   → Methode + /api/{ressource}/{id?}
3. Authentifizierung        → Token prüfen (401)
4. AccessPolicy             → Rolle darf Aktion auf Ressource? (403)
5. Codec(Content-Type)      → Body in neutrale Zeilen (415 / 400)
6. FieldMapper              → Aliase, Typen, Pflichtfelder (422)
7. Driver                   → Lesen / Schreiben im Backend (404)
8. FieldMapper (zurück)     → Spalten → API-Felder, writeonly-Felder entfernen
9. Codec(Accept)            → neutrale Zeilen ins Zielformat (406)
10. Response
```

---

## Designentscheidungen

Diese drei Entscheidungen halten das Gateway bewusst einfach.

### 1. Alles in einem Array, kein Streaming

Codecs und Treiber arbeiten mit vollständigen PHP-Arrays.

- **Vorteil**: einfache Interfaces, leicht zu testen, `Content-Length` immer bekannt.
- **Konsequenz**: Speicherverbrauch wächst mit der Datenmenge. Deshalb gibt es feste Grenzen für Body-Größe und Seitengröße (siehe [Grenzen](#grenzen-und-performance)).

### 2. Backend-Wahl nur über die Konfiguration

Welche Ressource in welchem Backend liegt, steht ausschließlich in `config/gateway.php`. Es gibt **keinen** Request-Parameter wie `?target=mongo`.

- **Vorteil**: Ein Client kann nie beeinflussen, wohin Daten geschrieben oder woher sie gelesen werden.
- **Konsequenz**: Ein Backend-Wechsel bedeutet: Config ändern und Daten umziehen.

> Das Ausgabeformat (`Accept` bzw. `?format=`) darf der Client dagegen frei wählen. Es betrifft nur die Darstellung, nicht den Speicherort.

### 3. Nur flache Datensätze

Eine Zeile ist ein einfaches Schlüssel-Wert-Array mit skalaren Werten. Keine verschachtelten Objekte, keine Listen in Feldern, keine eingebetteten Relationen.

- **Vorteil**: Jedes Format kann jede Zeile verlustfrei abbilden, auch CSV.
- **Konsequenz**: Beziehungen werden als IDs abgebildet (`besitzer_id: 5`). Verschachtelte Eingaben werden mit `422` abgelehnt.

---

## Anforderungen

**Pflicht**

| Komponente | Version / Hinweis |
|---|---|
| PHP | 8.2 oder neuer |
| `ext-json` | Standard |
| `ext-pdo` + `ext-pdo_mysql` | für MariaDB / MySQL |
| `ext-simplexml`, `ext-xmlwriter` | für XML |
| Composer | für Autoloading |

**Optional**: Ein Codec oder Treiber wird nur aktiviert, wenn seine Abhängigkeit installiert ist.

| Paket | Wofür |
|---|---|
| `ext-mongodb` + `mongodb/mongodb` | MongoDB-Treiber |
| `symfony/yaml` | YAML-Codec |
| `phpoffice/phpspreadsheet` | XLSX/ODS-Codec |
| `ext-pdo_pgsql`, `ext-pdo_sqlite` | weitere SQL-Datenbanken über den PDO-Treiber |

Ist eine optionale Abhängigkeit nicht installiert, taucht das Format bzw. der Treiber nicht in `/api/_meta` auf. Eine Ressource, die einen fehlenden Treiber benutzt, verhindert den Start (siehe [Validierung](#validierung-beim-start)).

---

## Installation

```bash
# 1. Abhängigkeiten installieren
composer install

# 2. Optionale Pakete nach Bedarf
composer require symfony/yaml
composer require mongodb/mongodb

# 3. Umgebungsvariablen anlegen
cp config/.env.example config/.env
#    → Secrets eintragen (siehe unten)

# 4. Konfiguration anpassen
#    config/gateway.php

# 5. Konfiguration prüfen
php bin/check-config.php

# 6. Lokal starten (nur Entwicklung)
php -S localhost:8080 -t public
```

Im Produktivbetrieb zeigt der Webserver (Apache/nginx) mit seinem Document Root auf `public/`. **Nur** `public/index.php` ist von außen erreichbar, `config/` und `src/` liegen außerhalb.

### Beispiel `config/.env`

```dotenv
GATEWAY_TOKEN_SECRET=ein-langer-zufälliger-wert-mindestens-32-zeichen
GATEWAY_DEBUG=0
DB_MAIN_USER=gateway
DB_MAIN_PASS=geheim
MONGO_URI=mongodb://mongo:27017
```

> `config/.env` gehört **nicht** ins Git-Repository. Im Repo liegt nur `config/.env.example` ohne echte Werte.

---

## Ordnerstruktur

```
gateway/
├─ public/
│   └─ index.php              ← einziger Einstiegspunkt
├─ config/
│   ├─ gateway.php            ← Verbindungen, Ressourcen, Rollen, Limits
│   ├─ .env                   ← Secrets (nicht im Git)
│   └─ .env.example
├─ bin/
│   └─ check-config.php       ← Konfiguration validieren
├─ src/
│   ├─ Http/                  ← Kernel, Router, Request, Response, ErrorResponse
│   ├─ Config/                ← ConfigLoader, ConfigValidator
│   ├─ Auth/                  ← TokenVerifier, Principal
│   ├─ Policy/                ← AccessPolicy, AccessRule
│   ├─ Codec/                 ← Codec, CodecRegistry, JsonCodec, CsvCodec, XmlCodec,
│   │                            YamlCodec, XlsxCodec, SqlCodec
│   ├─ Driver/                ← Driver, DriverFactory, PdoDriver, MongoDriver, FileDriver
│   ├─ Mapping/               ← ResourceMap, FieldMap, FieldMapper, TypeCaster, Hydrator
│   └─ Query/                 ← Query, Filter, QueryParser
├─ tests/
├─ composer.json
└─ README.md
```

Eine Klasse pro Datei, ein Namespace pro Ordner, Autoloading über Composer (PSR-4).

---

## Konfiguration

Die gesamte Konfiguration ist **ein PHP-Array** in `config/gateway.php`. PHP-Arrays werden von OPcache zwischengespeichert und müssen nicht bei jedem Request geparst werden.

### Vollständiges Beispiel

```php
<?php
// config/gateway.php
return [

    // ── Grenzen ─────────────────────────────────────────────────────────
    'limits' => [
        'maxBodyBytes' => 5 * 1024 * 1024,   // größere Requests → 413
        'maxBodyBytesByFormat' => [
            'xlsx' => 1 * 1024 * 1024,       // XLSX braucht sehr viel RAM (siehe Serverauslastung)
        ],
        'defaultLimit' => 50,                // Seitengröße ohne ?limit
        'maxLimit'     => 500,               // Obergrenze für ?limit
    ],

    // ── Debug (nur Entwicklung!) ────────────────────────────────────────
    // Fügt jeder Antwort X-Debug-Memory und X-Debug-Time hinzu.
    'debug' => (bool) getenv('GATEWAY_DEBUG'),

    // ── Authentifizierung ───────────────────────────────────────────────
    'auth' => [
        'secret'     => getenv('GATEWAY_TOKEN_SECRET'),
        'header'     => 'Authorization',     // "Bearer <token>"
        'cookie'     => 'auth_token',        // alternativ für Browser-Frontends
        'claims'     => [
            'user'  => 'sub',                // Benutzer-ID
            'role'  => 'role',               // Rollenname
            'level' => 'level',              // Berechtigungsstufe (optional)
        ],
    ],

    // ── Verbindungen ────────────────────────────────────────────────────
    'connections' => [
        'main' => [
            'driver' => 'pdo',
            'dsn'    => 'mysql:host=mysql;dbname=app;charset=utf8mb4',
            'user'   => getenv('DB_MAIN_USER'),
            'pass'   => getenv('DB_MAIN_PASS'),
        ],
        'logs' => [
            'driver'   => 'mongo',
            'uri'      => getenv('MONGO_URI'),
            'database' => 'logs',
        ],
        'files' => [
            'driver'    => 'file',
            'path'      => __DIR__ . '/../data',
            'format'    => 'csv',                // Codec für die Dateien
            'delimiter' => ';',
        ],
    ],

    // ── Ressourcen ──────────────────────────────────────────────────────
    'resources' => [

        'users' => [
            'connection' => 'main',
            'source'     => 'user_data',          // Tabelle / Collection / Dateiname
            'primaryKey' => 'id',                 // API-Feldname des Schlüssels
            'fields' => [
                'id'       => ['column' => 'UserID',   'type' => 'int',    'readonly' => true],
                'username' => ['column' => 'Username', 'type' => 'string', 'required' => true, 'max' => 50],
                'vorname'  => ['column' => 'Vorname',  'type' => 'string', 'aliases' => ['firstname', 'first_name']],
                'nachname' => ['column' => 'Nachname', 'type' => 'string', 'aliases' => ['lastname', 'last_name']],
                'passwort' => ['column' => 'Userpw',   'type' => 'password', 'writeonly' => true],
            ],
            'filterable' => ['username', 'vorname', 'nachname'],
            'sortable'   => ['id', 'username', 'nachname'],
            'owner'      => 'id',                 // Feld, das mit dem user-Claim verglichen wird (:own)
            'access' => [
                'admin'      => 'crud',
                'ausbilder'  => 'r',
                'teilnehmer' => 'ru:own',
            ],
            'minLevel' => ['create' => 3, 'delete' => 3],
        ],

        'schluessel' => [
            'connection' => 'main',
            'source'     => 'schluessel',
            'primaryKey' => 'id',
            'fields' => [
                'id'          => ['column' => 'Schluessel_ID', 'type' => 'int', 'readonly' => true],
                'nummer'      => ['column' => 'SchluesselNr',  'type' => 'string', 'required' => true,
                                  'aliases' => ['schluesselnr', 'schlüsselnr']],
                'schrank'     => ['column' => 'SchrankNr',     'type' => 'string', 'aliases' => ['schranknr']],
                'besitzer_id' => ['column' => 'Nutzer_ID',     'type' => 'int',    'nullable' => true],
                'ausgegeben'  => ['column' => 'Ausgabedatum',  'type' => 'datetime', 'nullable' => true],
            ],
            'filterable' => ['nummer', 'schrank', 'besitzer_id'],
            'sortable'   => ['nummer', 'schrank'],
            'access' => [
                'admin'     => 'crud',
                'ausbilder' => 'cru',
            ],
        ],

        'zugriffslog' => [
            'connection' => 'logs',
            'source'     => 'access_log',
            'primaryKey' => 'id',
            'fields' => [
                'id'        => ['column' => '_id',       'type' => 'string', 'readonly' => true],
                'benutzer'  => ['column' => 'user',      'type' => 'string'],
                'aktion'    => ['column' => 'action',    'type' => 'string'],
                'zeitpunkt' => ['column' => 'timestamp', 'type' => 'datetime'],
            ],
            'access' => ['admin' => 'r'],
        ],

        // Reine Formatumwandlung ohne Speicher
        '_convert' => [
            'access' => ['admin' => 'r', 'ausbilder' => 'r'],
        ],
    ],
];
```

### Ressourcen-Optionen

| Option | Pflicht | Beschreibung |
|---|---|---|
| `connection` | ja | Name einer Verbindung aus `connections` |
| `source` | ja | Tabelle, Collection oder Dateiname im Backend |
| `primaryKey` | ja | API-Feldname des eindeutigen Schlüssels |
| `fields` | ja | Feld-Mapping (siehe unten) |
| `access` | ja | Rechte pro Rolle. **Ohne `access` ist die Ressource gesperrt.** |
| `filterable` | nein | Felder, nach denen gefiltert werden darf. Standard: keine. |
| `sortable` | nein | Felder, nach denen sortiert werden darf. Standard: nur `primaryKey`. |
| `owner` | bei `:own` | Feld, das den Besitzer eines Datensatzes enthält |
| `minLevel` | nein | Mindest-Berechtigungsstufe pro Aktion |

### Feld-Optionen

| Option | Typ | Beschreibung |
|---|---|---|
| `column` | string | Name der Spalte bzw. des Dokumentfelds im Backend |
| `type` | string | `int`, `float`, `decimal`, `string`, `bool`, `datetime`, `password` |
| `required` | bool | Muss beim Anlegen (`POST`) vorhanden sein |
| `nullable` | bool | Darf `null` sein (Standard: `false`) |
| `readonly` | bool | Wird ausgegeben, kann aber nicht geschrieben werden (z. B. Auto-ID) |
| `writeonly` | bool | Kann geschrieben werden, wird aber **nie** ausgegeben |
| `max` | int | Maximale Länge (Strings) bzw. Maximalwert (Zahlen) |
| `min` | int | Minimale Länge bzw. Minimalwert |
| `aliases` | string[] | Alternative Feldnamen bei der Eingabe |

**Aliase** werden normalisiert verglichen: Groß-/Kleinschreibung, Leerzeichen und Umlaute spielen keine Rolle (`"Schlüssel Nr"` → `schluesselnr`). Das ist besonders für CSV-Importe aus Excel praktisch, deren Spaltenüberschriften oft unterschiedlich geschrieben sind.

**`type: password`** wird beim Schreiben automatisch mit `password_hash()` gehasht und ist implizit `writeonly`.

### Validierung beim Start

Beim Laden der Konfiguration wird geprüft:

- Jede Ressource verweist auf eine existierende Verbindung.
- Der Treiber jeder Verbindung ist verfügbar (Abhängigkeit installiert).
- Jede Ressource hat `primaryKey`, `fields` und `access`.
- `primaryKey`, `filterable`, `sortable` und `owner` verweisen auf existierende Felder.
- Jede `access`-Regel ist gültig (nur `c`, `r`, `u`, `d`, optional `:own`).
- Ressourcen mit `:own` haben ein `owner`-Feld.
- `auth.secret` ist gesetzt und mindestens 32 Zeichen lang.

Schlägt eine Prüfung fehl, antwortet das Gateway auf jeden Request mit `500` und schreibt den genauen Grund ins Log. Manuell prüfen lässt sich das mit:

```bash
php bin/check-config.php
```

---

## Authentifizierung und Rollen

### Token

Jeder Request (außer bei explizit öffentlichen Ressourcen) braucht ein signiertes Token (JWT, HS256):

```http
Authorization: Bearer eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...
```

Alternativ wird das Cookie aus `auth.cookie` gelesen, damit ein Browser-Frontend mit HttpOnly-Cookie arbeiten kann. Das Gateway ist **zustandslos**: keine PHP-Session, alle Informationen stehen im Token.

Erwartete Claims (Namen über `auth.claims` anpassbar):

```json
{
  "sub": "42",
  "role": "ausbilder",
  "level": 2,
  "exp": 1764000000
}
```

### Rechte-Schreibweise

Pro Ressource wird jeder Rolle eine Zeichenkette aus CRUD-Buchstaben zugewiesen:

| Buchstabe | Aktion | HTTP-Methode |
|---|---|---|
| `c` | create (anlegen) | `POST` |
| `r` | read (lesen) | `GET` |
| `u` | update (ändern) | `PUT`, `PATCH` |
| `d` | delete (löschen) | `DELETE` |

```php
'access' => [
    'admin'      => 'crud',     // alles
    'ausbilder'  => 'cru',      // alles außer löschen
    'teilnehmer' => 'r:own',    // nur eigene Datensätze lesen
    '*'          => 'r',        // jede angemeldete Rolle darf lesen
],
```

- **`:own`** beschränkt die Rechte auf Datensätze, bei denen das `owner`-Feld dem `user`-Claim aus dem Token entspricht. Das Gateway hängt diesen Filter automatisch an jede Abfrage. Ein Teilnehmer kann ihn nicht umgehen, auch nicht über `?filter`.
- **`*`** gilt für jede angemeldete Rolle, die nicht explizit aufgeführt ist.
- **Nicht aufgeführte Rollen** haben keine Rechte (deny by default).

### Berechtigungsstufen

Zusätzlich zur Rolle kann pro Aktion eine Mindeststufe verlangt werden:

```php
'minLevel' => ['create' => 3, 'delete' => 3],
```

Ein Zugriff ist nur erlaubt, wenn **die Rolle die Aktion erlaubt UND** die Stufe aus dem Token mindestens `minLevel` erreicht.

### Ablauf der Prüfung

```
Kein / ungültiges / abgelaufenes Token   → 401 Unauthorized
Rolle hat Aktion nicht                   → 403 Forbidden
Stufe zu niedrig                         → 403 Forbidden
:own und fremder Datensatz (Einzelzugriff) → 404 Not Found
```

Bei `:own` antwortet ein Einzelzugriff auf einen fremden Datensatz mit `404` statt `403`. So lässt sich nicht herausfinden, welche IDs existieren.

---

## API-Referenz

### Endpunkte

| Methode | Pfad | Aktion | Recht |
|---|---|---|---|
| `GET` | `/api/{ressource}` | Liste (gefiltert, sortiert, seitenweise) | `r` |
| `GET` | `/api/{ressource}/{id}` | Einzelner Datensatz | `r` |
| `POST` | `/api/{ressource}` | Einen oder mehrere Datensätze anlegen | `c` |
| `PUT` | `/api/{ressource}/{id}` | Datensatz vollständig ersetzen | `u` |
| `PATCH` | `/api/{ressource}/{id}` | Einzelne Felder ändern | `u` |
| `DELETE` | `/api/{ressource}/{id}` | Datensatz löschen | `d` |
| `GET` | `/api/_meta` | Ressourcen, Formate, eigene Rechte | angemeldet |
| `POST` | `/api/_convert` | Format umwandeln, ohne zu speichern | laut Config |

### Query-Parameter für Listen

| Parameter | Beispiel | Beschreibung |
|---|---|---|
| `filter[feld]` | `filter[schrank]=A12` | Gleichheit |
| `filter[feld][op]` | `filter[nachname][like]=Mül%` | Mit Operator |
| `sort` | `sort=-nachname,vorname` | Sortierung, `-` = absteigend |
| `limit` | `limit=100` | Seitengröße (max. `limits.maxLimit`) |
| `offset` | `offset=200` | Startposition |
| `fields` | `fields=id,username` | Nur diese Felder ausgeben |
| `format` | `format=csv` | Ausgabeformat, Alternative zum `Accept`-Header |

**Filter-Operatoren**

| Operator | Bedeutung | Beispiel |
|---|---|---|
| `eq` | gleich (Standard) | `filter[id][eq]=5` |
| `ne` | ungleich | `filter[schrank][ne]=A12` |
| `lt` / `gt` | kleiner / größer | `filter[id][gt]=100` |
| `lte` / `gte` | kleiner gleich / größer gleich | `filter[ausgegeben][gte]=2026-01-01` |
| `like` | Muster mit `%` | `filter[nachname][like]=Mül%` |
| `in` | in Liste | `filter[id][in]=1,2,3` |
| `null` | ist (nicht) leer | `filter[besitzer_id][null]=true` |

Gefiltert und sortiert werden darf **nur** nach Feldern aus `filterable` bzw. `sortable`. Unbekannte Felder führen zu `400`.

### Pagination

Weil manche Formate (z. B. CSV) keine Metadaten im Body tragen können, steht die Seiteninformation in den Response-Headern:

```http
X-Total-Count: 1342
Link: </api/users?limit=50&offset=50>; rel="next", </api/users?limit=50&offset=1300>; rel="last"
```

### Beispiele

**Liste als JSON**

```bash
curl -H "Authorization: Bearer $TOKEN" \
     "https://example.org/api/users?filter[nachname][like]=M%25&sort=nachname&limit=2"
```

```json
[
  { "id": 7,  "username": "mmueller", "vorname": "Max",  "nachname": "Müller" },
  { "id": 12, "username": "lmeier",   "vorname": "Lena", "nachname": "Meier" }
]
```

**Dieselbe Liste als CSV**

```bash
curl -H "Authorization: Bearer $TOKEN" \
     -H "Accept: text/csv" \
     "https://example.org/api/users?filter[nachname][like]=M%25&sort=nachname&limit=2"
```

```csv
id;username;vorname;nachname
7;mmueller;Max;Müller
12;lmeier;Lena;Meier
```

**Mehrere Datensätze per CSV anlegen, Antwort als XML**

```bash
curl -X POST \
     -H "Authorization: Bearer $TOKEN" \
     -H "Content-Type: text/csv" \
     -H "Accept: application/xml" \
     --data-binary @neue_schluessel.csv \
     "https://example.org/api/schluessel"
```

```xml
<?xml version="1.0" encoding="UTF-8"?>
<records>
  <record><id>301</id><nummer>K-0815</nummer><schrank>A12</schrank><besitzer_id/><ausgegeben/></record>
  <record><id>302</id><nummer>K-0816</nummer><schrank>A12</schrank><besitzer_id/><ausgegeben/></record>
</records>
```

**Einzelnes Feld ändern**

```bash
curl -X PATCH \
     -H "Authorization: Bearer $TOKEN" \
     -H "Content-Type: application/json" \
     -d '{"besitzer_id": 7, "ausgegeben": "2026-11-23T10:00:00+01:00"}' \
     "https://example.org/api/schluessel/301"
```

**Format umwandeln ohne Speichern**

```bash
curl -X POST \
     -H "Authorization: Bearer $TOKEN" \
     -H "Content-Type: text/csv" \
     -H "Accept: application/x-yaml" \
     --data-binary @export.csv \
     "https://example.org/api/_convert"
```

**Selbstauskunft**

```bash
curl -H "Authorization: Bearer $TOKEN" "https://example.org/api/_meta"
```

```json
{
  "formats": {
    "input":  ["application/json", "text/csv", "text/tab-separated-values", "application/xml", "application/x-yaml"],
    "output": ["application/json", "text/csv", "text/tab-separated-values", "application/xml", "application/x-yaml", "application/sql"]
  },
  "resources": {
    "users":      { "access": "r",   "fields": ["id", "username", "vorname", "nachname"] },
    "schluessel": { "access": "cru", "fields": ["id", "nummer", "schrank", "besitzer_id", "ausgegeben"] }
  }
}
```

`_meta` zeigt nur Ressourcen, auf die die eigene Rolle mindestens ein Recht hat, und nur Felder, die ausgegeben werden. `writeonly`-Felder erscheinen nicht.

---

## Formate (Codecs)

| Format | MIME-Type | Kurz (`?format=`) | Eingabe | Ausgabe | Abhängigkeit |
|---|---|---|---|---|---|
| JSON | `application/json` | `json` | ✅ | ✅ | – |
| CSV | `text/csv` | `csv` | ✅ | ✅ | – |
| TSV | `text/tab-separated-values` | `tsv` | ✅ | ✅ | – |
| XML | `application/xml` | `xml` | ✅ | ✅ | – |
| YAML | `application/x-yaml` | `yaml` | ✅ | ✅ | `symfony/yaml` |
| XLSX | `application/vnd.openxmlformats-officedocument.spreadsheetml.sheet` | `xlsx` | ✅ | ✅ | `phpoffice/phpspreadsheet` |
| SQL | `application/sql` | `sql` | ❌ | ✅ | – |

Ohne `Accept`-Header (oder bei `*/*`) wird **JSON** ausgegeben.

### JSON

- Eingabe: ein einzelnes Objekt oder ein Array von Objekten.
- Ausgabe: Liste → Array, Einzelzugriff → Objekt.
- Kodierung: UTF-8, Umlaute unmaskiert (`JSON_UNESCAPED_UNICODE`).

### CSV / TSV

- Erste Zeile ist immer die Kopfzeile mit Feldnamen. Aliase werden aufgelöst.
- **Eingabe**: Trennzeichen wird automatisch erkannt (`;`, `,`, Tab). Ein UTF-8-BOM am Anfang wird entfernt.
- **Ausgabe**: Trennzeichen `;` und UTF-8-BOM, damit deutsches Excel die Datei ohne Import-Assistent korrekt öffnet. Mit `?delimiter=,` lässt sich das Trennzeichen ändern.
- **Leere Zelle = `null`**. Ein leerer String ist in CSV deshalb nicht von `null` unterscheidbar.

### XML

Feste, einfache Struktur, nur Elemente, keine Attribute:

```xml
<records>
  <record>
    <feldname>wert</feldname>
    <leeres_feld/>
  </record>
</records>
```

- Ein leeres Element (`<feld/>`) bedeutet `null`.
- Bei Einzelzugriffen ist das Wurzelelement `<record>`.
- Feldnamen müssen gültige XML-Elementnamen sein. Das prüft der Validator beim Start.
- Externe Entities sind deaktiviert (Schutz vor XXE-Angriffen).

### YAML

Wie JSON: eine Liste von Mappings bzw. ein einzelnes Mapping. Anker, Aliase und Tags werden nicht unterstützt.

### SQL (nur Ausgabe)

Erzeugt `INSERT`-Statements für die abgefragten Zeilen, z. B. für Exporte oder Testdaten:

```sql
INSERT INTO `users` (`id`, `username`, `vorname`, `nachname`) VALUES (7, 'mmueller', 'Max', 'Müller');
```

SQL wird **niemals als Eingabe** angenommen. Vom Client gelieferte SQL-Befehle auszuführen wäre eine offene Tür für Angriffe.

### Obj (PHP-Objekte)

Kein Übertragungsformat, sondern ein `Hydrator` für eigenen PHP-Code, der das Gateway intern nutzt:

```php
$users = $gateway->resource('users')->find($query);           // neutrale Zeilen
$objs  = Hydrator::toObjects($users, App\Model\User::class);  // → User[]
```

Die Zuordnung Ressource ↔ Klasse kann optional in der Ressource stehen: `'class' => App\Model\User::class`.

---

## Datenmodell

### Der neutrale Datensatz

Alle Bausteine tauschen Daten in genau dieser Form aus:

```php
// Liste von Zeilen
[
    ['id' => 7,  'username' => 'mmueller', 'aktiv' => true,  'besitzer_id' => null],
    ['id' => 12, 'username' => 'lmeier',    'aktiv' => false, 'besitzer_id' => 3],
]
```

Erlaubt sind nur die Werttypen `int`, `float`, `string`, `bool` und `null`. **Keine Arrays oder Objekte als Feldwert.**

### Typsystem

| Typ | PHP | MariaDB | MongoDB | Ausgabe JSON | Ausgabe CSV/XML |
|---|---|---|---|---|---|
| `int` | `int` | INT, BIGINT | int32/int64 | `42` | `42` |
| `float` | `float` | DOUBLE | double | `3.14` | `3.14` |
| `decimal` | `string` | DECIMAL | Decimal128 | `"12.50"` | `12.50` |
| `string` | `string` | VARCHAR, TEXT | string | `"Text"` | `Text` |
| `bool` | `bool` | TINYINT(1) | bool | `true` | `1` / `0` |
| `datetime` | `string` (ISO 8601) | DATETIME | Date | `"2026-11-23T10:00:00+01:00"` | wie JSON |
| `password` | – | VARCHAR(255) | string | nie ausgegeben | nie ausgegeben |

`decimal` wird bewusst als String geführt. Als `float` entstehen bei Geldbeträgen Rundungsfehler.

### Typumwandlung bei der Eingabe

Aus textbasierten Formaten (CSV, XML) kommen alle Werte als String. Der `TypeCaster` wandelt sie anhand des Mappings um:

| Eingabe | Typ | Ergebnis |
|---|---|---|
| `"42"` | `int` | `42` |
| `"4,2"` | `float` | `4.2` (deutsches Dezimalkomma wird akzeptiert) |
| `"abc"` | `int` | **Fehler** → Status `422` |
| `"1"`, `"true"`, `"ja"` | `bool` | `true` |
| `"0"`, `"false"`, `"nein"` | `bool` | `false` |
| `"23.11.2026"` | `datetime` | `"2026-11-23T00:00:00+01:00"` |
| `""` | jeder | `null` (wenn `nullable`, sonst Fehler) |

Nicht umwandelbare Werte führen zu `422` mit Angabe von Zeile und Feld. Es wird nicht stillschweigend geraten.

### Beziehungen

Beziehungen werden **nur als ID** abgebildet:

```json
{ "id": 301, "nummer": "K-0815", "besitzer_id": 7 }
```

Den Besitzer liefert ein zweiter Request: `GET /api/users/7`. Joins, eingebettete Objekte oder `?include=` gibt es nicht.

### Verschachtelte Eingaben

Enthält ein Feld ein Objekt oder eine Liste, wird der gesamte Request abgelehnt:

```json
{
  "error": "validation_failed",
  "message": "Eingabe enthält ungültige Werte.",
  "details": [
    { "row": 1, "field": "adresse", "problem": "Verschachtelte Werte werden nicht unterstützt." }
  ]
}
```

---

## Speicher-Backends (Treiber)

Alle Treiber implementieren dasselbe Interface:

```php
interface Driver
{
    /** @return array{rows: list<array>, total: int} */
    public function find(Query $query): array;

    public function get(string|int $id): ?array;

    /** @return array Gespeicherte Zeile inkl. erzeugter ID */
    public function create(array $row): array;

    public function update(string|int $id, array $row): ?array;

    public function delete(string|int $id): bool;
}
```

Der Treiber bekommt bereits gemappte Daten (Spaltennamen des Backends) und eine geprüfte `Query`. Er kennt weder Formate noch Rechte.

### PdoDriver (MariaDB, MySQL, PostgreSQL, SQLite)

```php
'main' => [
    'driver' => 'pdo',
    'dsn'    => 'mysql:host=mysql;dbname=app;charset=utf8mb4',
    'user'   => getenv('DB_MAIN_USER'),
    'pass'   => getenv('DB_MAIN_PASS'),
],
```

- Alle **Werte** über Prepared Statements.
- Alle **Tabellen- und Spaltennamen** stammen ausschließlich aus der Konfiguration und werden zusätzlich gequotet. Spaltennamen aus dem Request werden nie direkt ins SQL übernommen.
- `POST` mit mehreren Zeilen läuft in einer Transaktion: entweder werden alle Zeilen gespeichert oder keine.
- Empfohlen: eigener Datenbank-Benutzer für das Gateway mit nur `SELECT`, `INSERT`, `UPDATE`, `DELETE` auf die benötigten Tabellen, **nicht** `root`.

### MongoDriver

```php
'logs' => [
    'driver'   => 'mongo',
    'uri'      => getenv('MONGO_URI'),
    'database' => 'logs',
],
```

- Die `Query` wird in Mongo-Filter übersetzt (`gt` → `$gt`, `like` → `$regex` mit maskierten Sonderzeichen, `in` → `$in`).
- `_id` wird über das Mapping auf ein API-Feld (meist `id`) abgebildet. ObjectIds werden als String ausgegeben.
- Nur Felder der obersten Ebene. Verschachtelte Felder in bestehenden Dokumenten werden beim Lesen ignoriert.
- Benötigt `ext-mongodb` und `mongodb/mongodb`.

### FileDriver (CSV, XML, JSON, YAML als Speicher)

```php
'files' => [
    'driver'    => 'file',
    'path'      => __DIR__ . '/../data',
    'format'    => 'csv',
    'delimiter' => ';',
],
```

- Jede Ressource ist eine Datei: `{path}/{source}.{format}`.
- Lesen und Schreiben über denselben Codec wie die HTTP-Schicht.
- Filtern, Sortieren und Paginieren passiert in PHP.
- **Schreiben ist atomar**: Datei mit `flock()` sperren, Inhalt in eine Temp-Datei schreiben, per `rename()` ersetzen. Stürzt der Prozess mittendrin ab, bleibt die alte Datei vollständig erhalten.
- IDs werden als fortlaufende Zahl vergeben (höchste vorhandene ID + 1).
- Geeignet für kleine Datenmengen und Konfigurationsdaten, **nicht** für viele gleichzeitige Schreibzugriffe.

### Backend wechseln

1. Neue Verbindung in `connections` anlegen.
2. Bei der Ressource `connection` und ggf. `column`-Namen anpassen.
3. Daten umziehen (geplant: `php bin/migrate.php users --from=main --to=logs`).
4. `php bin/check-config.php` ausführen.

Für Clients ändert sich dabei nichts: Endpunkte, Feldnamen und Formate bleiben gleich.

---

## Fehlerbehandlung

Fehler kommen immer im angefragten Ausgabeformat, bei JSON z. B.:

```json
{
  "error": "forbidden",
  "message": "Keine Berechtigung für diese Aktion.",
  "requestId": "a3f9c2e1"
}
```

Bei CSV-Ausgabe werden Fehler als JSON geliefert, weil CSV keine sinnvolle Fehlerstruktur abbilden kann.

| Status | `error` | Wann |
|---|---|---|
| `400` | `bad_request` | Ungültige Query-Parameter, unbekanntes Filterfeld, kaputter Body |
| `401` | `unauthorized` | Kein, ungültiges oder abgelaufenes Token |
| `403` | `forbidden` | Rolle oder Stufe reicht nicht |
| `404` | `not_found` | Ressource oder Datensatz existiert nicht (oder ist bei `:own` fremd) |
| `405` | `method_not_allowed` | z. B. `DELETE` auf `/api/users` ohne ID |
| `406` | `not_acceptable` | Gewünschtes Ausgabeformat nicht verfügbar |
| `409` | `conflict` | Eindeutiger Schlüssel verletzt (z. B. Username doppelt) |
| `413` | `payload_too_large` | Body größer als `limits.maxBodyBytes` |
| `415` | `unsupported_media_type` | Eingabeformat nicht verfügbar |
| `422` | `validation_failed` | Pflichtfeld fehlt, falscher Typ, verschachtelter Wert |
| `500` | `internal_error` | Unerwarteter Fehler oder ungültige Konfiguration |

**Interne Details werden nie an den Client geschickt.** Exception-Meldungen, SQL-Fehler und Stacktraces landen nur im Server-Log, verknüpft über die `requestId`.

---

## Grenzen und Performance

Weil alle Daten als Array im Speicher liegen, sind die Grenzen Teil des Designs:

| Grenze | Standard | Wirkung |
|---|---|---|
| `maxBodyBytes` | 5 MB | Größere Requests → `413`, bevor geparst wird |
| `maxBodyBytesByFormat.xlsx` | 1 MB | Eigenes, kleineres Limit für XLSX-Uploads |
| `defaultLimit` | 50 | Seitengröße ohne `?limit` |
| `maxLimit` | 500 | `?limit` wird darauf gekappt |
| `memory_limit` (php.ini) | ≥ 256 MB empfohlen | Puffer für Decoding + Mapping + Encoding |

Weitere Punkte:

- Die Konfiguration ist ein PHP-Array und wird von **OPcache** gecacht.
- Datenbankverbindungen werden **lazy** aufgebaut: Ein Request an `/api/users` öffnet keine MongoDB-Verbindung.
- Codecs mit externen Bibliotheken (YAML, XLSX) werden erst geladen, wenn sie gebraucht werden.
- Filter, Sortierung und Limit werden bei PDO und MongoDB **an die Datenbank übergeben** und nicht erst in PHP ausgeführt.
- Zwischenergebnisse werden **sofort freigegeben**, sobald der nächste Schritt fertig ist (z. B. `unset($body)` nach dem Dekodieren). So liegen die Daten nicht gleichzeitig drei- oder viermal im Speicher.

---

## Serverauslastung und Anforderungen

> **Alle Zahlen in diesem Abschnitt sind Schätzungen**, abgeleitet aus dem Design (alles in Arrays) und typischen Werten von PHP, MariaDB und MongoDB. Sie ersetzen keinen Lasttest. Verlässliche Werte liefert erst eine Messung, sobald die erste Ressource vollständig läuft (siehe [Messen statt schätzen](#messen-statt-schätzen)).

### Wo die Last entsteht

| Arbeitsschritt | CPU | RAM | Bewertung |
|---|---|---|---|
| Token prüfen (HMAC-SHA256) | sehr gering | – | vernachlässigbar |
| Routing, Rechteprüfung, Mapping | gering | gering | vernachlässigbar |
| JSON lesen/schreiben | gering | mittel | schnell, in C implementiert |
| CSV / TSV | mittel | mittel | unkritisch |
| XML | mittel | mittel | unkritisch |
| YAML (`symfony/yaml`) | hoch | mittel | reines PHP, spürbar langsamer |
| **XLSX (`phpoffice/phpspreadsheet`)** | **hoch** | **sehr hoch** | teuerster Codec, eigenes Limit |
| Datenbankabfrage | – | – | hängt von Indizes und DB-Server ab, nicht vom Gateway |
| FileDriver | mittel | hoch | liest bei jedem Request die komplette Datei |

**Der Engpass ist der Arbeitsspeicher, nicht die CPU.** Das ist eine direkte Folge der Entscheidung „alles in einem Array“.

### Speicherverbrauch pro Request

Während eines Requests existieren die Daten zeitweise mehrfach:

```
Roh-Body (String) → dekodiertes Array → gemapptes Array → kodierte Ausgabe (String)
```

Zusätzlich sind PHP-Arrays speicherintensiv: Jeder Wert und jeder Feldname hat Verwaltungsaufwand. Faustregel: **Eine CSV-Datei braucht als PHP-Array etwa das 5- bis 10-Fache ihrer Dateigröße.**

| Request | Geschätzter Peak-RAM pro PHP-Prozess |
|---|---|
| `GET` einzelner Datensatz | 2–5 MB |
| `GET` Liste mit 50 Zeilen (Standard) | 3–6 MB |
| `GET` Liste mit 500 Zeilen (`maxLimit`) | 5–15 MB |
| `POST` / `_convert` mit 5 MB CSV/JSON (`maxBodyBytes`) | 40–80 MB |
| `POST` mit 1 MB XLSX (`maxBodyBytesByFormat.xlsx`) | 50–150 MB |
| `POST` mit 5 MB XLSX (ohne eigenes Limit) | **mehrere hundert MB** |

PhpSpreadsheet benötigt grob **1 KB RAM pro Zelle**. Eine Tabelle mit 10.000 Zeilen × 20 Spalten liegt damit bereits bei rund 200 MB. Deshalb hat XLSX ein eigenes, kleineres Limit. Alternativ lässt sich das Format über die Rechte nur für Admins freigeben.

### Durchsatz und Antwortzeiten

| Request-Art | Zeit im Gateway (geschätzt) | Bemerkung |
|---|---|---|
| Einzelner Datensatz lesen/ändern | 2–10 ms | plus Zeit der Datenbank |
| Liste mit 50 Zeilen als JSON | 5–15 ms | plus Zeit der Datenbank |
| Liste mit 500 Zeilen als XML | 20–60 ms | XML-Erzeugung ist teurer als JSON |
| Import 5 MB CSV (mehrere tausend Zeilen) | 0,5–3 s | dominiert von Validierung und DB-Transaktion |
| Import 1 MB XLSX | 1–5 s | dominiert vom Einlesen der Tabelle |

Auf einem Server mit **2 vCPU** sind damit für typische, kleine Requests (Liste laden, Datensatz ändern) **grob 100–300 Requests pro Sekunde** realistisch, sofern die Datenbank mithält und die Filterfelder indiziert sind. Für ein internes Verwaltungstool ist das weit mehr als nötig.

### PHP-FPM dimensionieren

Wie viele Requests gleichzeitig bearbeitet werden, legt `pm.max_children` fest:

```
pm.max_children = RAM für PHP ÷ durchschnittlicher Speicher pro PHP-Prozess
```

- Mit dem **Durchschnitt** rechnen, nicht mit dem schlimmsten Fall (XLSX-Upload).
- Ein PHP-FPM-Prozess braucht im Leerlauf etwa 20–40 MB, unter typischer Last etwa 40–60 MB.
- `memory_limit = 256M` verhindert, dass ein einzelner Ausreißer den ganzen Server aufbraucht. Der Request bricht dann mit `500` ab, die anderen laufen weiter.
- Reicht der RAM nicht für genug Prozesse, warten neue Requests in der Warteschlange. Sie werden langsamer, aber nicht abgelehnt.

Beispielkonfiguration (`www.conf`) für einen Server mit 4 GB RAM:

```ini
pm                   = dynamic
pm.max_children      = 12
pm.start_servers     = 3
pm.min_spare_servers = 2
pm.max_spare_servers = 5
pm.max_requests      = 500   ; Prozess nach 500 Requests neu starten (gegen Speicherlecks)
```

### RAM-Aufteilung: Beispiel mit 4 GB (alles auf einem Server bzw. in Docker Compose)

| Dienst | RAM |
|---|---|
| Betriebssystem + Docker | ~500 MB |
| nginx | ~30 MB |
| PHP-FPM: 12 Prozesse × ~50 MB | ~600 MB |
| OPcache | 128 MB |
| MariaDB (`innodb_buffer_pool_size`) | 1 GB |
| MongoDB (`wiredTigerCacheSizeGB`) | 1 GB |
| Reserve (Upload-Spitzen, Dateisystem-Cache) | ~700 MB |

> **Achtung bei MongoDB:** Standardmäßig belegt MongoDB als Cache die Hälfte von (RAM − 1 GB). Ohne Begrenzung nimmt sie MariaDB und PHP den Speicher weg. Im Container deshalb immer begrenzen:
>
> ```yaml
> mongo:
>   image: mongo:8
>   command: ["--wiredTigerCacheSizeGB", "1"]
> ```
>
> Wird MongoDB nicht genutzt, kann der Server mit 2 GB RAM auskommen.

### Empfohlene Servergrößen

| Einsatz | Gleichzeitige Nutzer | CPU | RAM | Speicher |
|---|---|---|---|---|
| Entwicklung / Schulung, nur MariaDB | < 10 | 1–2 vCPU | 2 GB | 20 GB SSD |
| Entwicklung mit MariaDB + MongoDB | < 10 | 2 vCPU | 4 GB | 20 GB SSD |
| Interner Betrieb (eine Einrichtung) | 50–200 Nutzer, ~10–30 Requests/s Spitze | 2 vCPU | 4 GB | 40 GB SSD |
| Größerer Betrieb / mehrere Standorte | mehrere hundert Nutzer | 4 vCPU | 8 GB, Datenbank auf eigenem Server | 80 GB+ SSD |

Der Speicherplatz wird vor allem von Datenbanken, Logs und Backups belegt. Das Gateway selbst inklusive Composer-Abhängigkeiten braucht unter 100 MB.

### Software-Anforderungen für den Server

| Komponente | Empfehlung |
|---|---|
| Betriebssystem | Linux (Debian/Ubuntu LTS) oder Docker / Docker Compose |
| Webserver | nginx + PHP-FPM (alternativ Apache mit `mod_proxy_fcgi`) |
| PHP | 8.2+ mit `pdo_mysql`, `json`, `simplexml`, `xmlwriter`, `mbstring`, `opcache` |
| Datenbank | MariaDB 10.11 LTS oder 11.x |
| Optional | MongoDB 7/8 + PHP-Extension `mongodb` |
| HTTPS | Zertifikat (z. B. Let's Encrypt oder internes CA-Zertifikat) |

**Empfohlene `php.ini`-Werte**

```ini
memory_limit        = 256M
post_max_size       = 8M     ; etwas über maxBodyBytes (5 MB)
upload_max_filesize = 8M
max_execution_time  = 30
display_errors      = Off    ; in Produktion

opcache.enable              = 1
opcache.memory_consumption  = 128
opcache.validate_timestamps = 0   ; Produktion: nach Code-Änderung PHP-FPM neu starten
```

### Typische Engpässe

1. **Fehlende Datenbank-Indizes.** Jedes Feld in `filterable` und `sortable` sollte einen Index haben, sonst wird bei jedem Filter die ganze Tabelle durchsucht. Das ist der häufigste Grund für eine langsame API.
2. **Mehrere große Imports gleichzeitig.** Einige parallele 5-MB-Uploads können den RAM-Bedarf von PHP kurzzeitig verdoppeln. Hilfe: kleineres `maxBodyBytes` oder Importe nur für bestimmte Rollen.
3. **XLSX.** Eigenes Limit (`maxBodyBytesByFormat.xlsx`) oder nur für Admins freigeben.
4. **FileDriver.** Gut bis etwa ein paar tausend Zeilen pro Datei. Bei vielen gleichzeitigen Schreibzugriffen warten Requests auf die Dateisperre.
5. **Zu hohes `maxLimit`.** 5.000 Zeilen als XML auszugeben kostet ein Vielfaches von 500.

### Messen statt schätzen

**Debug-Header:** Mit `GATEWAY_DEBUG=1` hängt das Gateway an jede Antwort echte Messwerte an:

```http
X-Debug-Memory: 6.2 MB      ; memory_get_peak_usage(true)
X-Debug-Time: 8.4 ms        ; Laufzeit im Gateway
```

> Nur in der Entwicklung aktivieren. In Produktion verraten diese Header unnötig Details über den Server.

**Lasttest:** Sobald die erste Ressource vollständig läuft, z. B. mit `ab` (Apache Bench), `wrk` oder `k6`:

```bash
# 1000 Requests, davon 20 gleichzeitig
ab -n 1000 -c 20 \
   -H "Authorization: Bearer $TOKEN" \
   "https://gateway.local/api/users?limit=50"
```

Darauf achten:

| Messwert | Woher | Zielwert (interner Betrieb) |
|---|---|---|
| Requests pro Sekunde | Ausgabe von `ab` / `wrk` / `k6` | deutlich über der erwarteten Spitzenlast |
| Antwortzeit (95. Perzentil) | Ausgabe von `ab` / `wrk` / `k6` | < 200 ms für normale Listen |
| RAM der PHP-FPM-Prozesse | `docker stats`, `htop` | stabil, kein stetiges Wachstum |
| Langsame Datenbankabfragen | MariaDB `slow_query_log` | keine Abfrage > 100 ms |

Die gemessenen Werte gehören anschließend in diesen Abschnitt und ersetzen die Schätzungen.

---

## Sicherheit

Checkliste für den Betrieb:

- [ ] `GATEWAY_TOKEN_SECRET` ist gesetzt, zufällig und mindestens 32 Zeichen lang. **Es gibt keinen Fallback-Wert**, ohne Secret startet das Gateway nicht.
- [ ] Document Root zeigt auf `public/`. `config/`, `src/` und `data/` sind von außen nicht erreichbar.
- [ ] `config/.env` ist nicht im Git-Repository.
- [ ] Jede Verbindung nutzt einen eigenen Datenbank-Benutzer mit minimalen Rechten.
- [ ] HTTPS ist erzwungen. Tokens werden nie über unverschlüsseltes HTTP übertragen.
- [ ] Jede Ressource hat eine `access`-Regel. Ohne Regel ist sie gesperrt.
- [ ] Felder wie Passwörter sind `writeonly` bzw. `type: password`.
- [ ] `display_errors` ist in Produktion aus.
- [ ] `GATEWAY_DEBUG` ist in Produktion `0` (keine Debug-Header).

Eingebaute Schutzmaßnahmen:

| Risiko | Schutz |
|---|---|
| SQL-Injection über Werte | Prepared Statements |
| SQL-Injection über Feld-/Sortiernamen | Nur Namen aus dem Mapping, Whitelist über `filterable`/`sortable` |
| NoSQL-Injection (MongoDB) | Werte nur skalar, Operatoren nur aus fester Liste, Regex-Sonderzeichen maskiert |
| XXE (XML External Entities) | Entity-Loading deaktiviert |
| CSV-Injection (Formeln in Excel) | Zellen, die mit `=`, `+`, `-` oder `@` beginnen, werden in der Ausgabe mit `'` maskiert |
| Ausspähen fremder IDs | `:own` liefert `404` statt `403` |
| Passwort-Leaks | `writeonly`-Felder werden nie ausgegeben, auch nicht in `_meta` |
| Speicher-Erschöpfung | `maxBodyBytes`, `maxLimit` |
| Informationsleck bei Fehlern | Nur generische Meldung + `requestId`, Details im Log |

**Empfohlen**: ein Audit-Log für alle schreibenden Zugriffe (`POST`, `PUT`, `PATCH`, `DELETE`) mit Zeitpunkt, Benutzer, Ressource und ID. Bei personenbezogenen Daten ist das auch aus Datenschutzsicht sinnvoll.

---

## Erweitern

### Eigenen Codec hinzufügen

```php
namespace Gateway\Codec;

final class NdjsonCodec implements Codec
{
    public function mimeTypes(): array
    {
        return ['application/x-ndjson'];
    }

    public function shortName(): string
    {
        return 'ndjson';
    }

    public function decode(string $body): array
    {
        $rows = [];
        foreach (preg_split('/\R/', trim($body)) as $i => $line) {
            if ($line === '') {
                continue;
            }
            $row = json_decode($line, true);
            if (!is_array($row)) {
                throw new DecodeException("Zeile " . ($i + 1) . " ist kein gültiges JSON-Objekt.");
            }
            $rows[] = $row;
        }
        return $rows;
    }

    public function encode(array $rows): string
    {
        return implode("\n", array_map(
            fn(array $r) => json_encode($r, JSON_UNESCAPED_UNICODE),
            $rows
        )) . "\n";
    }
}
```

Registrieren in `config/gateway.php`:

```php
'codecs' => [
    Gateway\Codec\NdjsonCodec::class,
],
```

Damit ist das Format sofort für alle Ressourcen als Ein- und Ausgabe verfügbar und taucht in `/api/_meta` auf.

### Eigenen Treiber hinzufügen

Interface `Driver` implementieren und in der Konfiguration registrieren:

```php
'drivers' => [
    'redis' => Gateway\Driver\RedisDriver::class,
],
```

Danach kann eine Verbindung `'driver' => 'redis'` verwenden.

### Eigener Handler für komplexe Ressourcen

Manche Ressourcen passen nicht in ein 1:1-Mapping, z. B. wenn ein Datensatz auf mehrere Tabellen verteilt gespeichert wird. Dafür kann eine Ressource statt `connection` einen eigenen Handler angeben, der das `Driver`-Interface implementiert:

```php
'schluessel_komplett' => [
    'handler' => App\Handler\SchluesselHandler::class,
    'fields'  => [ /* API-Felder */ ],
    'access'  => ['admin' => 'crud'],
],
```

Auth, Rechte, Codecs und Validierung funktionieren dabei genauso wie bei allen anderen Ressourcen. Nur das Speichern übernimmt der eigene Code.

---

## Bewusst nicht enthalten

Damit das Gateway überschaubar bleibt, gibt es folgende Dinge **nicht**:

| Nicht enthalten | Grund | Alternative |
|---|---|---|
| Streaming großer Datenmengen | Einfachheit, Arrays reichen | Pagination mit `limit`/`offset` |
| Backend-Wahl per Request | Sicherheit | Backend in der Config festlegen |
| Verschachtelte Daten / Relationen | Jedes Format soll jede Zeile abbilden können | IDs + zweiter Request |
| SQL als Eingabe | Sicherheit | JSON/CSV/XML-Eingabe |
| Joins über mehrere Ressourcen | Folgt aus „nur flach“ | Eigener Handler oder DB-View als `source` |
| Benutzerverwaltung / Login | Gateway prüft Tokens nur | Token kommt von einem eigenen Login-Dienst |

---

## Roadmap

Bis einschließlich Schritt 6 ist das Gateway funktionsfähig. Alles danach sind Erweiterungen, die nur neue Klassen hinzufügen.

- [ ] **1.** Kernel, Router, Request/Response, einheitliche Fehlerantworten
- [ ] **2.** Konfiguration laden und beim Start validieren (`bin/check-config.php`)
- [ ] **3.** Token prüfen + `AccessPolicy` (CRUD-Schreibweise, `:own`, `minLevel`)
- [ ] **4.** `JsonCodec` + `CsvCodec`
- [ ] **5.** `PdoDriver` (MariaDB) + `FieldMapper` + `TypeCaster`
- [ ] **6.** Erste Ressource läuft vollständig durch (alle CRUD-Methoden, Filter, Pagination)
- [ ] **7.** `XmlCodec`, `YamlCodec`, `SqlCodec` (nur Ausgabe)
- [ ] **8.** `FileDriver` mit atomarem Schreiben
- [ ] **9.** `MongoDriver`
- [ ] **10.** `/api/_meta` und `/api/_convert`
- [ ] **11.** `XlsxCodec` mit eigenem Größenlimit, `Hydrator` (Obj), Audit-Log, `bin/migrate.php`
- [ ] **12.** Debug-Header (`X-Debug-Memory`, `X-Debug-Time`), Lasttest, Schätzwerte durch Messwerte ersetzen

---

## Lizenz

Noch festzulegen.
