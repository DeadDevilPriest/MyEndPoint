<?php
declare(strict_types=1);

// Erzeugt den Hash für ein Passwort der Login-Tabelle (Spalte password).
//
//   php bin/passwort-hash.php            fragt das Passwort ab (wird nicht angezeigt)
//   php bin/passwort-hash.php --hilfe    diese Hilfe

if (PHP_SAPI !== 'cli') {
    http_response_code(404);   // nur für die Kommandozeile, nie über das Web
    exit;
}

if (in_array($argv[1] ?? '', ['--hilfe', '--help', '-h'], true)) {
    echo <<<TEXT
    passwort-hash – Hash für ein Passwort der Login-Tabelle erzeugen

    AUFRUF
      php bin/passwort-hash.php

    Das Passwort wird abgefragt und nicht angezeigt. Ausgegeben wird der Hash
    (\$2y\$10\$…), der in die Spalte password der Login-Tabelle gehört, z. B.:

      UPDATE benutzer SET password = '<Hash>' WHERE IID = 1;

    Das Passwort selbst wird nirgends gespeichert.

    TEXT;
    exit(0);
}

// Passwort verdeckt einlesen (Linux/macOS: stty; unter Windows ist die Eingabe sichtbar).
// Die Frage geht nach STDERR – auf STDOUT steht am Ende nur der Hash.
function passwortLesen(string $frage): string
{
    fwrite(STDERR, $frage);
    $verdeckt = DIRECTORY_SEPARATOR === '/' && stream_isatty(STDIN);
    if ($verdeckt) shell_exec('stty -echo');
    $eingabe = rtrim((string) fgets(STDIN), "\r\n");
    if ($verdeckt) { shell_exec('stty echo'); fwrite(STDERR, PHP_EOL); }
    return $eingabe;
}

$passwort = passwortLesen('Passwort: ');
if ($passwort === '') {
    fwrite(STDERR, "Kein Passwort eingegeben.\n");
    exit(1);
}
if (stream_isatty(STDIN) && passwortLesen('Wiederholen: ') !== $passwort) {
    fwrite(STDERR, "Die Eingaben stimmen nicht überein.\n");
    exit(1);
}
echo password_hash($passwort, PASSWORD_DEFAULT), PHP_EOL;
