<?php
// Konfiguration der API (Version 1). Geprüft wird beim Start (src/Config/ConfigValidator.php).
//
// Zugangsdaten stehen NICHT hier, sondern in config/.env (nicht im Git, Vorlage: .env.example).
// $env enthält die Werte aus config/.env, z. B. $env['DB_USER'].
// Mehr dazu im README, Abschnitt "Konfiguration".

/** @var array<string, string> $env */

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
        'fahrzeuge' => [
            'treiber' => 'pdo',
            // Die Teile des DSN kommen aus config/.env – fehlt ein Wert, gilt der Standard dahinter.
            'dsn'     => sprintf('mysql:host=%s;port=%s;dbname=%s;charset=%s',
                $env['DB_HOST']    ?? '127.0.0.1',
                $env['DB_PORT']    ?? '3306',
                $env['DB_NAME']    ?? 'fahrzeugverwaltung',
                $env['DB_CHARSET'] ?? 'utf8mb4'),
            'user'    => $env['DB_USER'] ?? 'root',
            'pass'    => $env['DB_PASS'] ?? '',
        ],
    ],

    // Nur diese Tabellen sind über die API erreichbar (Whitelist).
    // Schlüssel = Name in der URL, quelle = Tabelle in der Datenbank.
    // Felder, Datentypen, Pflichtfelder und Verweise liest die API selbst aus der Datenbank.
    'ressourcen' => [
        'fahrzeuge'      => ['verbindung' => 'fahrzeuge', 'quelle' => 'fahrzeuge'],
        'fahrzeugtypen'  => ['verbindung' => 'fahrzeuge', 'quelle' => 'fahrzeugtypen'],
        'fahrzeugarten'  => ['verbindung' => 'fahrzeuge', 'quelle' => 'fahrzeugarten'],
        'treibstoffe'    => ['verbindung' => 'fahrzeuge', 'quelle' => 'treibstoffe'],
        'fahrzeughalter' => ['verbindung' => 'fahrzeuge', 'quelle' => 'fahrzeughalter'],
    ],
];
