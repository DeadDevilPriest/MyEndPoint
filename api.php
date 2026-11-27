<?php
declare(strict_types=1);

/* ════════════════════════════════════════════════════════════════
 *  Fahrzeugverwaltung – universelle REST-API
 * ════════════════════════════════════════════════════════════════
 *  Jede Tabelle aus RESSOURCEN kann per HTTP gelesen, angelegt,
 *  geändert und gelöscht werden. Die Spalten liest die API selbst
 *  aus der Datenbank – eine neue Spalte braucht keine Codeänderung.
 *
 *  Aufrufe (Beispiel: XAMPP, Ordner htdocs/MyEndPoint):
 *
 *    GET    api.php                  Übersicht: alle Ressourcen mit ihren Feldern
 *                                     (Typ, Pflichtfeld, Primärschlüssel, Fremdschlüssel)
 *    GET    api.php/fahrzeuge        Liste
 *    GET    api.php/fahrzeuge/5      ein Datensatz
 *    POST   api.php/fahrzeuge        neu anlegen
 *    PUT    api.php/fahrzeuge/5      ändern (nur die mitgeschickten Felder, PATCH genauso)
 *    DELETE api.php/fahrzeuge/5      löschen
 *
 *  Listen:  ?spalte=wert          Filter, mehrere werden mit UND verknüpft
 *           ?name=VW*             * als Platzhalter
 *           ?sort=name,-leistung  sortieren, - = absteigend
 *           ?limit=20&offset=40   seitenweise abrufen
 *
 *  Daten senden als JSON (Content-Type: application/json)
 *  oder als Formular (application/x-www-form-urlencoded / multipart).
 *
 *  HTML-Formulare kennen nur GET und POST. Darum: method="post" und ein
 *  verstecktes Feld _method mit PUT, PATCH oder DELETE (alternativ der
 *  Header X-HTTP-Method-Override). Das alte Feld "operation" mit
 *  GET/POST/UPDATE/DELETE wird weiterhin verstanden. Steht keine ID in
 *  der URL, wird sie aus dem Feld gelesen, das wie der Primärschlüssel
 *  heißt. Felder, die mit _ beginnen, werden ignoriert.
 *
 *  Spaltennamen: Groß-/Kleinschreibung ist egal (fid = FID), wie in MySQL.
 *
 *  Verweise zwischen Tabellen erkennt die API an echten Fremdschlüsseln –
 *  oder an der Namensregel: Eine Spalte, die so heißt wie der Primärschlüssel
 *  einer anderen Tabelle, verweist auf diese (fahrzeuge.FTID → fahrzeugtypen.FTID).
 *  Beim Speichern wird geprüft, ob es den Eintrag gibt; beim Löschen, ob er
 *  noch verwendet wird.
 *
 *  Datentypen: Auch Typ und Grenzen jedes Felds kommen aus der Datenbank
 *  (src/Mapping/TypeCaster.php). Jede Eingabe wird VOR der Datenbank geprüft
 *  und umgewandelt, z. B. "4,2" → 4.2, "ja" → 1, "24.12.2026" → 2026-12-24.
 *  Passt ein Wert nicht, kommt 400 mit dem Feldnamen.
 *
 *  Konfiguration: config/gateway.php (Ressourcen, Verbindung, Grenzen, CORS),
 *  Zugangsdaten in config/.env (Vorlage: config/.env.example, nicht im Git).
 *  Anmeldung (optional, Standard: aus – sicherheit.aktiv in config/gateway.php):
 *    POST api.php/_login  mit login (Benutzername oder E-Mail) und passwort → Token
 *    danach jede Anfrage mit dem Header  Authorization: Bearer <token>
 *  Die Konten stehen in der Login-Datenbank (LOGIN_DB_* in config/.env; ohne
 *  LOGIN_DB_NAME dieselbe Datenbank wie die Daten). Klassen: src/Security/.
 *
 *  Antwort immer JSON. Bei Fehlern: {"fehler": "...", "details": [...]}
 *  Voraussetzung: PHP 8.1 oder neuer (8.2 empfohlen).
 * ════════════════════════════════════════════════════════════════ */


/* ── 1) KONFIGURATION ─────────────────────────────────────────────
 *  Die Werte kommen aus config/gateway.php (geladen in Abschnitt 6a)
 *  und stehen danach als Konstanten bereit:
 *    API_DEBUG       genaue Fehlermeldungen in der Antwort (Standard: aus)
 *    RESSOURCEN      Name in der URL → Tabelle in der Datenbank (Whitelist)
 *    DB_VERBINDUNG   dsn, user, pass der Datenbank
 *    LIMIT_STANDARD  so viele Datensätze liefert eine Liste ohne ?limit=
 *    LIMIT_MAXIMUM   mehr gibt es auch mit ?limit= nicht
 *    CORS_URSPRUENGE Webseiten, die die API per JavaScript aufrufen dürfen
 *    SICHERHEIT      Einstellungen der Anmeldung (aktiv, login, header, token)
 *    LOGIN_VERBINDUNG dsn, user, pass der Login-Datenbank (nur bei aktiver Anmeldung)
 */

// Klassen aus src/ laden (PSR-4: Gateway\Mapping\TypeCaster → src/Mapping/TypeCaster.php).
// Mit Composer übernimmt vendor/autoload.php das, ohne Composer der kleine Lader hier.
if (is_file(__DIR__ . '/vendor/autoload.php')) {
    require __DIR__ . '/vendor/autoload.php';
} else {
    spl_autoload_register(function (string $klasse): void {
        if (str_starts_with($klasse, 'Gateway\\')) {
            $datei = __DIR__ . '/src/' . str_replace('\\', '/', substr($klasse, strlen('Gateway\\'))) . '.php';
            if (is_file($datei)) {
                require $datei;
            }
        }
    });
}

use Gateway\Config\ConfigLoader;
use Gateway\Config\ConfigValidator;
use Gateway\Mapping\TypeCaster;
use Gateway\Security\KlartextPasswort;
use Gateway\Security\Login;
use Gateway\Security\Token;

// MySQL-Fehler, die der Client verursacht hat → passender Statuscode statt 500.
// Schlüssel = MySQL-Fehlernummer
const MYSQL_FEHLER = [
    1048 => [400, 'Ein Pflichtfeld ist leer.'],
    1062 => [409, 'Ein Datensatz mit diesem Wert existiert bereits.'],
    1264 => [400, 'Eine Zahl liegt außerhalb des erlaubten Bereichs.'],
    1265 => [400, 'Ein Wert passt nicht zum Spaltentyp.'],
    1292 => [400, 'Ein Datum oder eine Uhrzeit ist ungültig.'],
    1366 => [400, 'Ein Wert passt nicht zum Spaltentyp.'],
    1406 => [400, 'Ein Text ist zu lang.'],
    1451 => [409, 'Der Datensatz wird noch von einer anderen Tabelle verwendet.'],
    1452 => [400, 'Ein Verweis zeigt auf einen Datensatz, den es nicht gibt.'],
];


/* ── 2) ANTWORTEN UND FEHLER ──────────────────────────────────────
 *  Jede Antwort läuft über antwort(): JSON ausgeben, Skript beenden.
 *  Rückgabetyp never = die Funktion kehrt nie zurück.
 */
function antwort(int $status, mixed $daten = null): never
{
    http_response_code($status);
    if ($daten !== null) {
        echo json_encode($daten, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
                               | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    }
    exit;
}

function fehler(int $status, string $meldung, array $details = []): never
{
    $daten = ['fehler' => $meldung];
    if ($details !== []) {
        $daten['details'] = $details;
    }
    antwort($status, $daten);
}

// Jede Exception, die nirgends gefangen wird, landet hier und wird zu
// einer JSON-Antwort – statt einer HTML-Fehlerseite, mit der kein Client etwas anfangen kann.
function exceptionBehandeln(Throwable $e): never
{
    $debug   = defined('API_DEBUG') && API_DEBUG;
    $details = $debug ? [$e->getMessage()] : [];
    $nummer  = $e instanceof PDOException ? (int) ($e->errorInfo[1] ?? 0) : 0;

    if (isset(MYSQL_FEHLER[$nummer])) {
        [$status, $meldung] = MYSQL_FEHLER[$nummer];
        fehler($status, $meldung, $details);
    }
    // Ins Log (XAMPP: apache/logs/error.log). Bei Datenbankfehlern NUR die Meldung:
    // Im Stacktrace von new PDO(…) kann unter PHP 8.1 das Passwort als Argument stehen.
    error_log($e instanceof PDOException
        ? 'PDOException: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine()
        : (string) $e);
    fehler(500, 'Interner Serverfehler.', $details);
}


/* ── 3) DATENBANK ─────────────────────────────────────────────── */

// PDO-Verbindung (Singleton): beim ersten Aufruf aufbauen, danach wiederverwenden
function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) return $pdo;

    // Zugangsdaten aus config/gateway.php bzw. config/.env
    $pdo = new PDO(DB_VERBINDUNG['dsn'], DB_VERBINDUNG['user'], DB_VERBINDUNG['pass'], [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
    return $pdo;
}

// Verbindung zur Login-Datenbank. Ist keine eigene konfiguriert (LOGIN_DB_NAME leer),
// ist es dieselbe Datenbank wie die Daten – dann wird die Verbindung von db() mitbenutzt.
function loginDb(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) return $pdo;
    if (LOGIN_VERBINDUNG === DB_VERBINDUNG) return $pdo = db();

    $pdo = new PDO(LOGIN_VERBINDUNG['dsn'], LOGIN_VERBINDUNG['user'], LOGIN_VERBINDUNG['pass'], [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
    return $pdo;
}

// Tabellen- und Spaltennamen lassen sich nicht als ?-Parameter binden.
// Darum kommen sie nur aus RESSOURCEN oder spalten() – nie direkt vom Client –
// und werden zusätzlich in Backticks gesetzt: name → `name`
function bezeichner(string $name): string
{
    return '`' . str_replace('`', '``', $name) . '`';
}

// Liest die Spalten einer Tabelle aus der Datenbank (pro Aufruf nur einmal).
// Ergebnis z. B. ['FTID' => ['typ' => 'int(11)', 'datentyp' => 'int', 'min' => -2147483648,
//                            'max' => 2147483647, 'pk' => false, 'auto' => false,
//                            'null' => false, 'pflicht' => true,
//                            'verweis' => ['ressource' => 'fahrzeugtypen', 'spalte' => 'FTID']], ...]
function spalten(string $tabelle): array
{
    static $cache = [];
    if (isset($cache[$tabelle])) return $cache[$tabelle];

    $spalten = [];
    foreach (db()->query('SHOW COLUMNS FROM ' . bezeichner($tabelle)) as $s) {
        $auto = str_contains($s['Extra'], 'auto_increment');
        $spalten[$s['Field']] = [
            'typ'     => $s['Type'],            // so, wie die Datenbank ihn meldet, z. B. int(11)
        ] + TypeCaster::fromSql($s['Type']) + [ // einheitlich: datentyp, min, max, laenge, werte …
            'pk'      => $s['Key'] === 'PRI',   // Primärschlüssel
            'auto'    => $auto,                 // AUTO_INCREMENT: vergibt MySQL selbst
            'null'    => $s['Null'] === 'YES',  // darf leer (NULL) sein
            // Pflicht = darf nicht NULL sein, hat keinen Standardwert und wird nicht automatisch vergeben
            'pflicht' => $s['Null'] === 'NO' && $s['Default'] === null && !$auto,
            'verweis' => null,                  // Fremdschlüssel → siehe unten
        ];
    }

    // Fremdschlüssel: Welche Spalte verweist auf welche Tabelle? Ein Client kann daraus
    // z. B. eine Auswahlliste bauen (typId → alle Einträge aus fahrzeugtypen).
    // Nur Verweise auf Tabellen aus RESSOURCEN werden gemeldet.
    $verweise = db()->prepare(
        'SELECT COLUMN_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME
           FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND REFERENCED_TABLE_NAME IS NOT NULL'
    );
    $verweise->execute([$tabelle]);
    $tabellenKlein = array_map('strtolower', RESSOURCEN);   // Windows speichert Tabellennamen klein

    foreach ($verweise as $v) {
        $ressource = array_search(strtolower($v['REFERENCED_TABLE_NAME']), $tabellenKlein, true);
        if ($ressource !== false && isset($spalten[$v['COLUMN_NAME']])) {
            $spalten[$v['COLUMN_NAME']]['verweis'] = [
                'ressource' => $ressource,
                'spalte'    => $v['REFERENCED_COLUMN_NAME'],
            ];
        }
    }

    // Ohne echten Fremdschlüssel gilt die Namensregel: Eine Spalte, die so heißt wie der
    // Primärschlüssel einer anderen Tabelle, verweist auf diese. Beispiel: fahrzeuge.FTID → fahrzeugtypen
    foreach ($spalten as $name => $s) {
        $ziel = primaerschluesselNamen()[strtolower((string) $name)] ?? null;
        if ($ziel !== null && !$s['pk'] && $s['verweis'] === null) {
            $spalten[$name]['verweis'] = $ziel;
        }
    }
    return $cache[$tabelle] = $spalten;
}

// Primärschlüssel aller Tabellen aus RESSOURCEN, für die Namensregel oben.
// Ergebnis z. B. ['ftid' => ['ressource' => 'fahrzeugtypen', 'spalte' => 'FTID'], ...]
function primaerschluesselNamen(): array
{
    static $namen = null;
    if ($namen !== null) return $namen;

    $proTabelle = [];
    $abfrage = db()->query(
        "SELECT TABLE_NAME, COLUMN_NAME FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
          WHERE TABLE_SCHEMA = DATABASE() AND CONSTRAINT_NAME = 'PRIMARY'"
    );
    foreach ($abfrage as $z) {
        $proTabelle[strtolower($z['TABLE_NAME'])][] = $z['COLUMN_NAME'];
    }

    $namen   = [];
    $doppelt = [];
    foreach (RESSOURCEN as $ressource => $tabelle) {
        $pk = $proTabelle[strtolower($tabelle)] ?? [];
        if (count($pk) !== 1) continue;
        $schluessel = strtolower($pk[0]);
        if (isset($namen[$schluessel])) $doppelt[] = $schluessel;
        $namen[$schluessel] = ['ressource' => $ressource, 'spalte' => $pk[0]];
    }
    foreach ($doppelt as $schluessel) {
        unset($namen[$schluessel]);   // heißen zwei Schlüssel gleich (z. B. "id"), ist die Regel nicht eindeutig
    }
    return $namen;
}

// Echter Spaltenname, Groß-/Kleinschreibung egal: "fid" → "FID". null = gibt es nicht.
function spalteFinden(string $tabelle, string $name): ?string
{
    foreach (array_keys(spalten($tabelle)) as $spalte) {
        if (strcasecmp((string) $spalte, $name) === 0) return (string) $spalte;
    }
    return null;
}

// Feldnamen der Eingabe auf die echten Spaltennamen bringen: {"fahrzeug_name": …} → {"Fahrzeug_Name": …}
// Unbekannte Namen bleiben, wie sie sind – die meldet später felderPruefen().
function namenAngleichen(string $tabelle, array $daten): array
{
    $ergebnis = [];
    foreach ($daten as $name => $wert) {
        $ergebnis[spalteFinden($tabelle, (string) $name) ?? $name] = $wert;
    }
    return $ergebnis;
}

// Name der Primärschlüssel-Spalte. Zugriffe per ID brauchen genau eine.
function primaerschluessel(string $tabelle): string
{
    $pk = array_keys(array_filter(spalten($tabelle), fn(array $s) => $s['pk']));
    if (count($pk) !== 1) {
        fehler(500, "Tabelle \"$tabelle\" braucht genau einen Primärschlüssel für Zugriffe per ID.");
    }
    return (string) $pk[0];
}


/* ── 3b) ANMELDUNG (nur wenn sicherheit.aktiv) ────────────────────
 *  Passwort wird nur bei POST /_login geprüft (langsam, absichtlich).
 *  Danach weist der Token aus (schnell: HMAC + ein SELECT pro Anfrage).
 */

// Liest einen Request-Header – egal, wie der Webserver ihn an PHP weiterreicht
function headerLesen(string $name): string
{
    $schluessel = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
    $wert = $_SERVER[$schluessel] ?? $_SERVER['REDIRECT_' . $schluessel] ?? null;
    if ($wert === null && function_exists('getallheaders')) {
        foreach (getallheaders() as $kopf => $inhalt) {
            if (strcasecmp((string) $kopf, $name) === 0) {
                $wert = $inhalt;
                break;
            }
        }
    }
    return trim((string) $wert);
}

// Login-Tabelle oder -Datenbank fehlt? Dann eine verständliche Meldung statt nur "Serverfehler".
function loginFehler(PDOException $e): never
{
    $nummer = (int) ($e->errorInfo[1] ?? $e->getCode());
    if (in_array($nummer, [1049, 1054, 1146], true)) {   // Datenbank, Spalte bzw. Tabelle fehlt
        error_log('Login: ' . $e->getMessage());
        fehler(500, 'Die Login-Datenbank passt nicht zur Konfiguration: sicherheit.login in config/gateway.php '
                    . 'und LOGIN_DB_* in config/.env prüfen.', API_DEBUG ? [$e->getMessage()] : []);
    }
    throw $e;
}

// POST api.php/_login – Benutzername oder E-Mail + Passwort prüfen, Token ausgeben
function anmelden(array $daten): never
{
    $login    = is_string($daten['login'] ?? null) ? trim($daten['login']) : '';
    $passwort = is_string($daten['passwort'] ?? null) ? $daten['passwort'] : '';
    if ($login === '' || $passwort === '') {
        fehler(400, 'Zum Anmelden login (Benutzername oder E-Mail) und passwort mitschicken.');
    }

    $hinweis = [];
    try {
        $konto = Login::check(loginDb(), SICHERHEIT['login'], $login, $passwort);
    } catch (KlartextPasswort $e) {
        // Hilfe beim Einrichten, nur mit API_DEBUG: Passwort steht im Klartext in der Tabelle
        $konto   = null;
        $hinweis = API_DEBUG ? [$e->getMessage() . ' Hash erzeugen: php bin/passwort-hash.php'] : [];
    } catch (PDOException $e) {
        loginFehler($e);
    }
    if ($konto === null) {
        fehler(401, 'Anmeldung fehlgeschlagen: Benutzername/E-Mail oder Passwort falsch.', $hinweis);
    }

    $token = Token::create(['k' => $konto['id'], 'n' => $konto['name']],
                           SICHERHEIT['token']['secret'], SICHERHEIT['token']['gueltigkeit']);
    header('Cache-Control: no-store');   // Token nie zwischenspeichern
    antwort(200, [
        'token'      => $token['token'],
        'gueltigBis' => date(DATE_ATOM, $token['gueltigBis']),
        'benutzer'   => ['name' => $konto['name'], 'email' => $konto['email']],
    ]);
}

// Jede andere Anfrage: gültiger Token im Header – und das Konto gibt es noch (gelöscht = sofort gesperrt)
function angemeldetesKonto(): array
{
    $kopf    = headerLesen(SICHERHEIT['header']['name']);
    $praefix = SICHERHEIT['header']['praefix'] ?? '';
    $token   = $praefix !== '' && stripos($kopf, $praefix) === 0 ? substr($kopf, strlen($praefix)) : $kopf;
    $inhalt  = $token !== '' ? Token::verify(trim($token), SICHERHEIT['token']['secret']) : null;

    $konto = null;
    if ($inhalt !== null && isset($inhalt['k'])) {
        try {
            $konto = Login::find(loginDb(), SICHERHEIT['login'], $inhalt['k']);
        } catch (PDOException $e) {
            loginFehler($e);
        }
    }
    if ($konto === null) {
        fehler(401, 'Nicht angemeldet oder Anmeldung abgelaufen. Zuerst POST api.php/_login mit login und passwort, '
                    . 'dann den Token mitschicken: ' . SICHERHEIT['header']['name'] . ': ' . $praefix . '<token>');
    }
    return $konto;
}


/* ── 4) EINGABEN LESEN UND PRÜFEN ─────────────────────────────── */

// Liest die gesendeten Daten – egal ob JSON oder Formular, egal mit welcher Methode
function eingabeLesen(): array
{
    $roh = (string) file_get_contents('php://input');
    $typ = strtolower($_SERVER['CONTENT_TYPE'] ?? '');

    // JSON: am Content-Type zu erkennen – oder daran, dass der Text mit { beginnt
    if (str_contains($typ, 'json') || str_starts_with(ltrim($roh), '{')) {
        if (trim($roh) === '') {
            return [];
        }
        $daten = json_decode($roh, true);
        if (!is_array($daten) || ($daten !== [] && array_is_list($daten))) {
            fehler(400, 'Ungültiges JSON. Erwartet wird ein Objekt, z. B. {"name": "Golf"}.');
        }
        return $daten;
    }

    // Formular per POST: PHP hat $_POST schon gefüllt
    if ($_POST !== []) {
        return $_POST;
    }

    // Formulardaten bei PUT/PATCH/DELETE: dafür füllt PHP $_POST nicht → selbst zerlegen
    parse_str($roh, $daten);
    return $daten;
}

// Entfernt Steuerfelder (_method, operation und alles, was mit _ beginnt)
function ohneSteuerfelder(array $daten): array
{
    return array_filter(
        $daten,
        fn($schluessel) => !str_starts_with((string) $schluessel, '_') && $schluessel !== 'operation',
        ARRAY_FILTER_USE_KEY
    );
}

// Prüft die gesendeten Felder gegen die Spalten der Tabelle.
// $neu = true beim Anlegen (Pflichtfelder müssen da sein), false beim Ändern.
// Gibt nur Felder zurück, die in die Tabelle geschrieben werden dürfen.
function felderPruefen(string $tabelle, array $daten, bool $neu): array
{
    $spalten   = spalten($tabelle);
    $felder    = [];
    $fehler    = [];
    $unbekannt = false;
    $gemeldet  = [];   // Felder mit eigener Fehlermeldung – nicht zusätzlich als "fehlt" melden

    foreach ($daten as $name => $wert) {
        $name = (string) $name;
        if (!isset($spalten[$name])) {
            $fehler[]  = "Unbekanntes Feld \"$name\".";
            $unbekannt = true;
            continue;
        }
        if (!$neu && $spalten[$name]['pk']) {
            $fehler[] = "Der Primärschlüssel \"$name\" kann nicht geändert werden.";
            continue;
        }
        if (is_array($wert)) {
            $fehler[] = "Feld \"$name\" muss ein einzelner Wert sein, keine Liste.";
            $gemeldet[$name] = true;
            continue;
        }
        if (is_string($wert)) $wert = trim($wert);                  // Texte werden getrimmt
        if ($wert === '' && $spalten[$name]['null']) $wert = null;  // leeres Formularfeld → NULL

        // Typ prüfen und umwandeln, z. B. "4,2" → 4.2, "ja" → 1, "24.12.2026" → "2026-12-24".
        // Ein leeres Pflichtfeld meldet weiter unten die Pflichtfeld-Prüfung.
        if ($wert !== null && !($wert === '' && $spalten[$name]['pflicht'])) {
            try {
                $wert = TypeCaster::cast($wert, $spalten[$name]);
            } catch (InvalidArgumentException $e) {
                $fehler[] = "Feld \"$name\": " . $e->getMessage() . '.';
                $gemeldet[$name] = true;
                continue;
            }
        }
        $felder[$name] = $wert;
    }

    // Pflichtfelder: beim Anlegen müssen sie da sein, beim Ändern dürfen sie nicht geleert werden
    foreach ($spalten as $name => $s) {
        if (!$s['pflicht'] || isset($gemeldet[$name])) continue;
        $vorhanden = array_key_exists($name, $felder);
        $leer      = $vorhanden && ($felder[$name] === null || $felder[$name] === '');
        if (($neu && !$vorhanden) || $leer) {
            $fehler[] = "Pflichtfeld \"$name\" fehlt.";
        }
    }

    // Verweise prüfen: Gibt es den Eintrag in der anderen Tabelle? (z. B. FTID 3 in fahrzeugtypen)
    // Echte Fremdschlüssel würden das auch tun – ohne sie macht es sonst niemand.
    foreach ($felder as $name => $wert) {
        $verweis = $spalten[$name]['verweis'];
        if ($verweis === null || $wert === null || $wert === '') continue;

        $abfrage = db()->prepare('SELECT COUNT(*) FROM ' . bezeichner(RESSOURCEN[$verweis['ressource']])
                                 . ' WHERE ' . bezeichner($verweis['spalte']) . ' = ?');
        $abfrage->execute([$wert]);
        if ((int) $abfrage->fetchColumn() === 0) {
            $fehler[] = "Feld \"$name\": Eintrag \"$wert\" gibt es in \"{$verweis['ressource']}\" nicht.";
        }
    }

    if ($unbekannt) {
        $fehler[] = 'Erlaubte Felder: ' . implode(', ', array_keys($spalten));
    }
    if ($fehler !== []) {
        fehler(400, 'Ungültige Eingaben.', $fehler);
    }
    return $felder;
}


/* ── 5) DIE VIER OPERATIONEN (CRUD) ───────────────────────────── */

// Einen Datensatz über seinen Primärschlüssel holen – oder 404
function holen(string $tabelle, string $id): array
{
    $pk = primaerschluessel($tabelle);

    // ID passend zum Typ des Primärschlüssels prüfen – MySQL würde "5abc" sonst als 5 lesen
    try {
        $schluesselwert = TypeCaster::cast($id, spalten($tabelle)[$pk]);
    } catch (InvalidArgumentException) {
        fehler(404, "Datensatz \"$id\" nicht gefunden.");
    }

    $abfrage = db()->prepare('SELECT * FROM ' . bezeichner($tabelle) . ' WHERE ' . bezeichner($pk) . ' = ?');
    $abfrage->execute([$schluesselwert]);
    $zeile = $abfrage->fetch();

    if ($zeile === false) {
        fehler(404, "Datensatz \"$id\" nicht gefunden.");
    }
    return $zeile;
}

// Liste mit Filter, Sortierung und Seiten (limit/offset)
function liste(string $tabelle, array $parameter): array
{
    $spalten = spalten($tabelle);
    $fehler  = [];

    // Steuerparameter herausnehmen – alles andere sind Filter
    $limit  = filter_var($parameter['limit'] ?? LIMIT_STANDARD, FILTER_VALIDATE_INT,
                         ['options' => ['min_range' => 1, 'max_range' => LIMIT_MAXIMUM]]);
    $offset = filter_var($parameter['offset'] ?? 0, FILTER_VALIDATE_INT,
                         ['options' => ['min_range' => 0]]);
    $sort   = $parameter['sort'] ?? '';
    unset($parameter['limit'], $parameter['offset'], $parameter['sort']);

    if ($limit === false)   $fehler[] = 'limit muss eine ganze Zahl von 1 bis ' . LIMIT_MAXIMUM . ' sein.';
    if ($offset === false)  $fehler[] = 'offset muss eine ganze Zahl ab 0 sein.';
    if (!is_string($sort))  $fehler[] = 'sort muss ein Text sein, z. B. sort=name,-leistung';

    // WHERE:  ?leistung=150&name=VW*  →  `leistung` = ? AND `name` LIKE ?
    $bedingungen = [];
    $werte       = [];
    foreach ($parameter as $spalte => $wert) {
        $spalte = (string) $spalte;
        if (!isset($spalten[$spalte])) {
            $fehler[] = "Unbekannter Filter \"$spalte\". Erlaubt: " . implode(', ', array_keys($spalten));
            continue;
        }
        if (!is_scalar($wert) && $wert !== null) {
            $fehler[] = "Filter \"$spalte\" muss ein einzelner Wert sein.";
            continue;
        }
        $wert = is_bool($wert) ? (string) (int) $wert : trim((string) $wert);
        if ($wert === '') continue;   // leeres Formularfeld = kein Filter

        if (str_contains($wert, '*')) {
            // * ist der Platzhalter. % und _ sind bei LIKE Sonderzeichen → maskieren
            $bedingungen[] = bezeichner($spalte) . ' LIKE ?';
            $werte[]       = str_replace(['\\', '%', '_', '*'], ['\\\\', '\\%', '\\_', '%'], $wert);
        } else {
            // Ohne Platzhalter: Wert passend zum Typ umwandeln – "?Leistung=abc" ist ein Fehler,
            // kein stiller Vergleich mit 0
            try {
                $werte[] = TypeCaster::cast($wert, $spalten[$spalte]);
            } catch (InvalidArgumentException $e) {
                $fehler[] = "Filter \"$spalte\": " . $e->getMessage() . '.';
                continue;
            }
            $bedingungen[] = bezeichner($spalte) . ' = ?';
        }
    }

    // ORDER BY:  ?sort=typ,-leistung  →  `typ` ASC, `leistung` DESC
    $sortierung = [];
    foreach (explode(',', is_string($sort) ? $sort : '') as $teil) {
        $teil = trim($teil);
        $name = ltrim($teil, '-');
        if ($name === '') continue;
        $spalte = spalteFinden($tabelle, $name);
        if ($spalte === null) {
            $fehler[] = "Unbekannte Sortierspalte \"$name\".";
            continue;
        }
        $sortierung[] = bezeichner($spalte) . (str_starts_with($teil, '-') ? ' DESC' : ' ASC');
    }
    // Ohne Angabe nach Primärschlüssel sortieren, damit die Seiten (limit/offset) stabil bleiben
    if ($sortierung === []) {
        foreach ($spalten as $name => $s) {
            if ($s['pk']) $sortierung[] = bezeichner((string) $name) . ' ASC';
        }
    }

    if ($fehler !== []) {
        fehler(400, 'Ungültige Parameter.', $fehler);
    }

    $von   = ' FROM ' . bezeichner($tabelle) . ($bedingungen ? ' WHERE ' . implode(' AND ', $bedingungen) : '');
    $order = $sortierung ? ' ORDER BY ' . implode(', ', $sortierung) : '';

    $anzahl = db()->prepare('SELECT COUNT(*)' . $von);
    $anzahl->execute($werte);

    // limit und offset sind oben als ganze Zahlen geprüft → dürfen direkt ins SQL
    $abfrage = db()->prepare('SELECT *' . $von . $order . " LIMIT $limit OFFSET $offset");
    $abfrage->execute($werte);

    return [
        'daten'  => $abfrage->fetchAll(),
        'gesamt' => (int) $anzahl->fetchColumn(),   // Treffer insgesamt, ohne limit
        'limit'  => $limit,
        'offset' => $offset,
    ];
}

// Neuen Datensatz anlegen und ihn so zurückgeben, wie er jetzt in der Datenbank steht
function anlegen(string $tabelle, array $daten): array
{
    $pk     = primaerschluessel($tabelle);
    $felder = felderPruefen($tabelle, $daten, true);

    $spaltenListe = implode(', ', array_map(fn($s) => bezeichner((string) $s), array_keys($felder)));
    $platzhalter  = implode(', ', array_fill(0, count($felder), '?'));

    db()->prepare('INSERT INTO ' . bezeichner($tabelle) . " ($spaltenListe) VALUES ($platzhalter)")
        ->execute(array_values($felder));

    // ID: selbst mitgeschickt – oder von MySQL vergeben (AUTO_INCREMENT)
    $neueId = isset($felder[$pk]) ? (string) $felder[$pk] : (string) db()->lastInsertId();
    return holen($tabelle, $neueId);
}

// Nur die mitgeschickten Felder ändern und den neuen Stand zurückgeben
function aendern(string $tabelle, string $id, array $daten): array
{
    $pk = primaerschluessel($tabelle);
    holen($tabelle, $id);   // 404, falls es den Datensatz nicht gibt

    // Viele Clients schicken das ganze Objekt zurück – samt ID. Passt sie, ist das in Ordnung.
    if (array_key_exists($pk, $daten) && (string) $daten[$pk] === $id) {
        unset($daten[$pk]);
    }

    $felder = felderPruefen($tabelle, $daten, false);
    if ($felder === []) {
        fehler(400, 'Keine Felder zum Ändern übergeben.');
    }

    $setzen = implode(', ', array_map(fn($s) => bezeichner((string) $s) . ' = ?', array_keys($felder)));
    db()->prepare('UPDATE ' . bezeichner($tabelle) . " SET $setzen WHERE " . bezeichner($pk) . ' = ?')
        ->execute([...array_values($felder), $id]);

    return holen($tabelle, $id);
}

// Löschen und den gelöschten Datensatz zurückgeben
function loeschen(string $tabelle, string $id): array
{
    $pk    = primaerschluessel($tabelle);
    $zeile = holen($tabelle, $id);   // 404, falls es den Datensatz nicht gibt
    verwendungPruefen($tabelle, $zeile);

    db()->prepare('DELETE FROM ' . bezeichner($tabelle) . ' WHERE ' . bezeichner($pk) . ' = ?')
        ->execute([$id]);

    return $zeile;
}

// Wird der Datensatz noch verwendet? Z. B. ein Fahrzeugtyp, den noch Fahrzeuge haben → 409
function verwendungPruefen(string $tabelle, array $zeile): void
{
    $ressource = array_search($tabelle, RESSOURCEN, true);

    foreach (RESSOURCEN as $andere => $andereTabelle) {
        foreach (spalten($andereTabelle) as $spalte => $s) {
            if (($s['verweis']['ressource'] ?? null) !== $ressource) continue;

            $abfrage = db()->prepare('SELECT COUNT(*) FROM ' . bezeichner($andereTabelle)
                                     . ' WHERE ' . bezeichner((string) $spalte) . ' = ?');
            $abfrage->execute([$zeile[$s['verweis']['spalte']]]);
            $anzahl = (int) $abfrage->fetchColumn();
            if ($anzahl > 0) {
                fehler(409, "Der Datensatz wird noch in \"$andere\" verwendet (Spalte $spalte, Anzahl: $anzahl).");
            }
        }
    }
}


/* ── 6) ABLAUF ────────────────────────────────────────────────── */

// 6a) Fehler abfangen, Konfiguration laden, Kopfzeilen setzen
set_exception_handler('exceptionBehandeln');
set_error_handler(function (int $nr, string $text, string $datei, int $zeile): bool {
    if (!(error_reporting() & $nr)) return false;
    throw new ErrorException($text, 0, $nr, $datei, $zeile);   // Warnung → Exception → JSON
});

header('Content-Type: application/json; charset=UTF-8');

// Konfiguration laden (config/.env + config/gateway.php) und prüfen.
// Ist sie fehlerhaft, startet die API nicht: 500, der Grund steht im Log.
try {
    $config = ConfigLoader::load(__DIR__ . '/config');
} catch (Throwable $e) {
    define('API_DEBUG', false);
    $meldung = $e instanceof RuntimeException
        ? $e->getMessage()                                  // z. B. "config/.env fehlt …"
        : 'config/gateway.php enthält einen Fehler: ' . $e->getMessage() . ' (Zeile ' . $e->getLine() . ')';
    error_log('Konfiguration: ' . $meldung);
    fehler(500, 'Konfiguration fehlerhaft.', [$meldung]);   // Meldung enthält keine Zugangsdaten
}

$konfigFehler = ConfigValidator::validate($config);
define('API_DEBUG', ($config['debug'] ?? false) === true);
if ($konfigFehler !== []) {
    error_log('Konfiguration: ' . implode(' | ', $konfigFehler));
    fehler(500, 'Konfiguration fehlerhaft. Details im Server-Log' . (API_DEBUG ? '.' : ' oder mit API_DEBUG=1 in der Antwort.'),
           API_DEBUG ? $konfigFehler : []);
}

// Werte aus der Konfiguration für den Rest der Datei bereitstellen (siehe Abschnitt 1)
$verbindung = $config['verbindungen'][reset($config['ressourcen'])['verbindung']];
define('DB_VERBINDUNG', [
    'dsn'  => $verbindung['dsn'],
    'user' => (string) ($verbindung['user'] ?? ''),
    'pass' => (string) ($verbindung['pass'] ?? ''),
]);
define('RESSOURCEN', array_map(fn(array $r) => $r['quelle'], $config['ressourcen']));
define('LIMIT_STANDARD', $config['grenzen']['limitStandard']);
define('LIMIT_MAXIMUM', $config['grenzen']['limitMaximum']);
define('CORS_URSPRUENGE', array_values($config['cors']['erlaubteUrspruenge'] ?? []));

// Anmeldung: Einstellungen und Login-Verbindung (die Konstanten enthalten Geheimnisse – nie ausgeben)
define('SICHERHEIT', ($config['sicherheit'] ?? []) + ['aktiv' => false]);
if (SICHERHEIT['aktiv']) {
    $login = $config['verbindungen'][SICHERHEIT['login']['verbindung']];
    define('LOGIN_VERBINDUNG', [
        'dsn'  => $login['dsn'],
        'user' => (string) ($login['user'] ?? ''),
        'pass' => (string) ($login['pass'] ?? ''),
    ]);
}
unset($config, $verbindung, $login);

// CORS: Nur Webseiten aus cors.erlaubteUrspruenge dürfen die API per JavaScript von einer
// ANDEREN Adresse aus aufrufen. "*" wäre ohne Anmeldung riskant: Jede Webseite, die jemand
// im internen Netz öffnet, könnte sonst per JavaScript Daten lesen und ändern.
// Bei aktiver Anmeldung muss der Token-Header ausdrücklich erlaubt werden.
$ursprung = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array($ursprung, CORS_URSPRUENGE, true)) {
    header("Access-Control-Allow-Origin: $ursprung");
    header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, X-HTTP-Method-Override'
           . (SICHERHEIT['aktiv'] ? ', ' . SICHERHEIT['header']['name'] : ''));
    header('Access-Control-Expose-Headers: Location');
}
// Antwort hängt vom Origin (und bei Anmeldung vom Token) ab → Caches dürfen sie nicht vermischen
if (SICHERHEIT['aktiv']) {
    header('Vary: Origin, ' . SICHERHEIT['header']['name']);
    header('Cache-Control: private');
} else {
    header('Vary: Origin');
}

// 6b) Route aus der URL lesen
//     /MyEndPoint/api.php/fahrzeuge/5  → ['fahrzeuge', '5']
//     /MyEndPoint/api/fahrzeuge/5      → dasselbe (mit der .htaccess)
//     /MyEndPoint/fahrzeuge/5          → dasselbe (mit der .htaccess)
$pfad   = rawurldecode(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');
$skript = $_SERVER['SCRIPT_NAME'];                                    // /MyEndPoint/api.php
$basis  = rtrim(str_replace('\\', '/', dirname($skript)), '/');       // /MyEndPoint

// Den längsten passenden Anfang abschneiden: mit Endung, ohne Endung, nur der Ordner
$route = '';
foreach ([$skript, preg_replace('/\.php$/', '', $skript), $basis] as $anfang) {
    if ($pfad === $anfang || str_starts_with($pfad, $anfang . '/')) {
        $route = substr($pfad, strlen($anfang));
        break;
    }
}

$segmente = array_values(array_filter(explode('/', $route), fn($s) => $s !== ''));
if (count($segmente) > 2) {
    fehler(404, 'Unbekannter Pfad. Aufbau: api.php/<ressource> oder api.php/<ressource>/<id>');
}
$ressource = strtolower($segmente[0] ?? '');
$id        = $segmente[1] ?? null;

$https  = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
$apiUrl = ($https ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . $skript;

// 6c) Methode bestimmen
$methode = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
if ($methode === 'OPTIONS') antwort(204);    // CORS-Vorabanfrage des Browsers
if ($methode === 'HEAD')    $methode = 'GET'; // wie GET, den Inhalt verwirft der Webserver

$daten = eingabeLesen();

// Formulare können nur GET/POST → gewünschte Methode aus _method, operation oder Header
if ($methode === 'POST') {
    $wunsch   = $daten['_method'] ?? $daten['operation'] ?? $_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'] ?? 'POST';
    $erlaubt  = ['GET' => 'GET', 'POST' => 'POST', 'PUT' => 'PUT', 'UPDATE' => 'PUT',
                 'PATCH' => 'PATCH', 'DELETE' => 'DELETE'];
    $methode  = is_string($wunsch) ? ($erlaubt[strtoupper(trim($wunsch))] ?? '') : '';
    if ($methode === '') {
        fehler(400, 'Unbekannte Operation. Erlaubt: GET, POST, PUT (oder UPDATE), PATCH, DELETE.');
    }
}

$daten = ohneSteuerfelder($daten);

// 6d) Anmeldung (nur wenn sicherheit.aktiv). POST /_login ist immer erreichbar –
//     sonst könnte sich niemand einen Token holen. Alles andere braucht einen gültigen Token.
if ($ressource === '_login') {
    if (!SICHERHEIT['aktiv']) {
        fehler(404, 'Die Anmeldung ist nicht eingeschaltet (sicherheit.aktiv in config/gateway.php).');
    }
    if ($methode !== 'POST' || $id !== null) {
        header('Allow: POST');
        fehler(405, 'Anmelden: POST api.php/_login mit login (Benutzername oder E-Mail) und passwort.');
    }
    anmelden($daten);
}
$konto = SICHERHEIT['aktiv'] ? angemeldetesKonto() : null;   // 401 ohne gültigen Token

// 6e) Ohne Ressource: Übersicht – so kann jeder Client nachsehen, welche Ressourcen und
//     Felder es gibt, mit Datentyp, Grenzen, Pflichtfeldern und Verweisen
if ($ressource === '') {
    if ($methode !== 'GET') {
        fehler(405, 'Auf der Übersicht ist nur GET erlaubt.');
    }
    $uebersicht = [];
    foreach (RESSOURCEN as $name => $tabelle) {
        $uebersicht[$name] = ['url' => "$apiUrl/$name", 'felder' => spalten($tabelle)];
    }
    antwort(200, ($konto !== null ? ['angemeldet' => ['name' => $konto['name'], 'email' => $konto['email']]] : [])
               + ['ressourcen' => $uebersicht]);
}

if (!isset(RESSOURCEN[$ressource])) {
    fehler(404, "Unbekannte Ressource \"$ressource\".",
           ['Verfügbar: ' . implode(', ', array_keys(RESSOURCEN))]);
}
$tabelle = RESSOURCEN[$ressource];
$daten   = namenAngleichen($tabelle, $daten);   // "fid" → "FID"

// Formulare können die ID nicht in die URL schreiben → sie darf auch als Feld kommen,
// das wie der Primärschlüssel heißt (z. B. <input type="hidden" name="id" value="5">)
if ($id === null && in_array($methode, ['PUT', 'PATCH', 'DELETE'], true)) {
    $pk = primaerschluessel($tabelle);
    if (isset($daten[$pk]) && is_scalar($daten[$pk]) && trim((string) $daten[$pk]) !== '') {
        $id = trim((string) $daten[$pk]);
        unset($daten[$pk]);
    }
}

// 6f) Operation ausführen – zuerst Status und Daten festlegen, ausgegeben wird am Ende
$status = 200;

switch ($methode) {
    case 'GET':
        // Bei einer Liste zählen Query-Parameter (und Formularfelder) als Filter
        $ergebnis = $id === null
            ? liste($tabelle, namenAngleichen($tabelle, array_merge($daten, ohneSteuerfelder($_GET))))
            : holen($tabelle, $id);
        break;

    case 'POST':
        if ($id !== null) {
            header('Allow: GET, PUT, PATCH, DELETE');
            fehler(405, "Zum Anlegen POST an api.php/$ressource senden – ohne ID.");
        }
        $ergebnis = anlegen($tabelle, $daten);
        $status   = 201;   // 201 = angelegt
        header("Location: $apiUrl/$ressource/" . rawurlencode((string) $ergebnis[primaerschluessel($tabelle)]));
        break;

    case 'PUT':
    case 'PATCH':
        if ($id === null) {
            fehler(400, "ID fehlt, z. B. PUT api.php/$ressource/5");
        }
        $ergebnis = aendern($tabelle, $id, $daten);
        break;

    case 'DELETE':
        if ($id === null) {
            fehler(400, "ID fehlt, z. B. DELETE api.php/$ressource/5");
        }
        $ergebnis = [
            'meldung'   => "Datensatz \"$id\" wurde gelöscht.",
            'geloescht' => loeschen($tabelle, $id),
        ];
        break;

    default:
        header('Allow: GET, POST, PUT, PATCH, DELETE, OPTIONS');
        fehler(405, "Methode $methode wird nicht unterstützt.");
}

// 6g) Antwort ausgeben
antwort($status, $ergebnis);