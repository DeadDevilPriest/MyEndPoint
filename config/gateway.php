<?php
// Konfiguration der API (Version 1). Geprüft wird beim Start (src/Config/ConfigValidator.php).
//
// Zugangsdaten stehen NICHT hier, sondern in config/.env (nicht im Git, Vorlage: .env.example).
// $env enthält die Werte aus config/.env, z. B. $env['DB_USER'].
// Mehr dazu im README, Abschnitt "Konfiguration".

/** @var array<string, string> $env */

// Wert aus der .env – fehlt er oder ist er leer, gilt der Standard
$wert = fn(string $name, string $standard) => ($env[$name] ?? '') !== '' ? $env[$name] : $standard;

// Baut eine Datenbank-Verbindung aus den .env-Werten mit dem Präfix $p ("DB_" oder "LOGIN_DB_").
$db = fn(string $p) => [
    'treiber' => 'pdo',
    'dsn'     => sprintf('mysql:host=%s;port=%s;dbname=%s;charset=%s',
        $wert($p . 'HOST', '127.0.0.1'),
        $wert($p . 'PORT', '3306'),
        $wert($p . 'NAME', 'fahrzeugverwaltung'),
        $wert($p . 'CHARSET', 'utf8mb4')),
    'user'    => $wert($p . 'USER', 'root'),
    'pass'    => $env[$p . 'PASS'] ?? '',   // leeres Passwort ist erlaubt (XAMPP-Standard)
];

return [

    // true = genaue Fehlermeldungen (z. B. der SQL-Fehler) in der Antwort.
    // Nur zum Entwickeln – eingeschaltet wird in der .env mit API_DEBUG=1.
    'debug' => ($env['API_DEBUG'] ?? '0') === '1',

    'grenzen' => [
        'limitStandard' => 100,    // so viele Datensätze liefert eine Liste ohne ?limit=
        'limitMaximum'  => 1000,   // mehr gibt es auch mit ?limit= nicht
    ],

    // Webseiten, die die API per JavaScript von einer ANDEREN Adresse aus aufrufen dürfen.
    // client/ und docs.html liegen auf derselben Adresse wie die API und brauchen keinen Eintrag.
    'cors' => [
        'erlaubteUrspruenge' => ['http://localhost', 'http://localhost:5173'],
    ],

    'verbindungen' => [
        // Daten: DB_HOST, DB_PORT, DB_NAME, DB_CHARSET, DB_USER, DB_PASS
        'fahrzeuge' => $db('DB_'),

        // Login: eine eigene Datenbank nur, wenn LOGIN_DB_NAME gesetzt ist (dann alle LOGIN_DB_*-Werte).
        // Sonst liegt die Login-Tabelle in derselben Datenbank wie die Daten.
        'login' => ($env['LOGIN_DB_NAME'] ?? '') !== '' ? $db('LOGIN_DB_') : $db('DB_'),
    ],

    // Nur diese Tabellen sind über die API erreichbar (Whitelist).
    // Schlüssel = Name in der URL, quelle = Tabelle in der Datenbank.
    // Felder, Datentypen, Pflichtfelder und Verweise liest die API selbst aus der Datenbank.
    // Die Login-Tabelle darf hier NIE stehen (prüft der ConfigValidator).
    'ressourcen' => [
        'fahrzeuge'      => ['verbindung' => 'fahrzeuge', 'quelle' => 'fahrzeuge'],
        'fahrzeugtypen'  => ['verbindung' => 'fahrzeuge', 'quelle' => 'fahrzeugtypen'],
        'fahrzeugarten'  => ['verbindung' => 'fahrzeuge', 'quelle' => 'fahrzeugarten'],
        'treibstoffe'    => ['verbindung' => 'fahrzeuge', 'quelle' => 'treibstoffe'],
        'fahrzeughalter' => ['verbindung' => 'fahrzeuge', 'quelle' => 'fahrzeughalter'],
    ],

    // SecurityCore (optional). Umgesetzt: Anmeldung über die Login-Datenbank + Token.
    // Ablauf: POST api.php/_login mit login (Benutzername oder E-Mail) und passwort → Token.
    // Danach bei jeder Anfrage den Token im Header schicken: "Authorization: Bearer mep1.…"
    'sicherheit' => [
        'aktiv' => false,                       // true = ohne Anmeldung keine Daten (401)

        'login' => [
            'verbindung' => 'login',            // aus 'verbindungen' oben
            'tabelle'    => 'benutzer',
            'spalten'    => [                   // Spaltennamen in der Login-Tabelle
                'id'       => 'IID',
                'name'     => 'username',
                'email'    => 'useremail',
                'passwort' => 'password',       // Hash von password_hash(), nie das Passwort selbst
            ],
        ],

        'header' => [                           // hier schickt der Client den Token mit
            'name'    => 'Authorization',
            'praefix' => 'Bearer ',
        ],

        'token' => [
            'secret'      => $env['TOKEN_SECRET'] ?? '',   // Pflicht bei aktiv, mindestens 32 Zeichen
            'gueltigkeit' => 8 * 3600,                     // Sekunden, danach neu anmelden
        ],
    ],
];
