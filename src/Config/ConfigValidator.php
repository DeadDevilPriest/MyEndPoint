<?php
declare(strict_types=1);

namespace Gateway\Config;

/**
 * Prüft die Konfiguration, bevor der erste Request bearbeitet wird.
 * Ergebnis: Liste von Fehlern, leer = alles in Ordnung.
 * Ist etwas falsch, startet die API nicht (siehe README, "Prüfung beim Start").
 */
final class ConfigValidator
{
    // Treiber, die es in Version 1 gibt. Weitere kommen mit dem ConnectionManager (Phase 2).
    private const TREIBER = ['pdo'];

    public static function validate(array $config): array
    {
        $fehler = [];

        if (!is_bool($config['debug'] ?? false)) {
            $fehler[] = 'debug muss true oder false sein.';
        }

        $standard = $config['grenzen']['limitStandard'] ?? null;
        $maximum  = $config['grenzen']['limitMaximum'] ?? null;
        if (!is_int($standard) || !is_int($maximum) || $standard < 1 || $standard > $maximum) {
            $fehler[] = 'grenzen: limitStandard und limitMaximum müssen ganze Zahlen sein, mit 1 ≤ limitStandard ≤ limitMaximum.';
        }

        $urspruenge = $config['cors']['erlaubteUrspruenge'] ?? [];
        if (!is_array($urspruenge)) {
            $fehler[] = 'cors.erlaubteUrspruenge muss eine Liste sein.';
        } else {
            foreach ($urspruenge as $adresse) {
                if (!is_string($adresse) || !preg_match('#^https?://[^/\s]+$#', $adresse)) {
                    $fehler[] = 'cors.erlaubteUrspruenge: "' . self::text($adresse)
                              . '" ist keine Adresse wie http://localhost:5173 (ohne / am Ende).';
                }
            }
        }

        $verbindungen = $config['verbindungen'] ?? null;
        if (!is_array($verbindungen) || $verbindungen === []) {
            $fehler[] = 'verbindungen: mindestens eine Verbindung eintragen.';
            $verbindungen = [];
        }
        foreach ($verbindungen as $name => $verbindung) {
            $treiber = $verbindung['treiber'] ?? null;
            if (!in_array($treiber, self::TREIBER, true)) {
                $fehler[] = "verbindungen.$name: Treiber \"" . self::text($treiber) . '" gibt es in Version 1 nicht (nur: '
                          . implode(', ', self::TREIBER) . ').';
            }
            $dsn = $verbindung['dsn'] ?? null;
            if (!is_string($dsn) || $dsn === '') {
                $fehler[] = "verbindungen.$name: dsn fehlt.";
            } elseif (str_starts_with($dsn, 'mysql:') && !preg_match('/(^mysql:|;)\s*(host|unix_socket)=/', $dsn)) {
                // "mysql:127.0.0.1;dbname=…" – ohne host= erkennt PDO den Server nicht
                $fehler[] = "verbindungen.$name: dsn braucht host=…, z. B. mysql:host=127.0.0.1;dbname=…";
            }
        }

        $ressourcen = $config['ressourcen'] ?? null;
        if (!is_array($ressourcen) || $ressourcen === []) {
            $fehler[] = 'ressourcen: mindestens eine Ressource eintragen.';
            $ressourcen = [];
        }
        $genutzt = [];
        foreach ($ressourcen as $name => $ressource) {
            $name = (string) $name;
            // Name erscheint in der URL; "_" am Anfang ist für _hilfe, _openapi … reserviert
            if (!preg_match('/^[a-z0-9][a-z0-9_-]*$/', $name)) {
                $fehler[] = "ressourcen.$name: Name nur aus Kleinbuchstaben, Ziffern, - und _, nicht mit _ beginnend.";
            }
            $verbindung = $ressource['verbindung'] ?? null;
            if (!is_string($verbindung) || !isset($verbindungen[$verbindung])) {
                $fehler[] = "ressourcen.$name: Verbindung \"" . self::text($verbindung) . '" gibt es nicht.';
            } else {
                $genutzt[$verbindung] = true;
            }
            if (!is_string($ressource['quelle'] ?? null) || $ressource['quelle'] === '') {
                $fehler[] = "ressourcen.$name: quelle (Name der Tabelle) fehlt.";
            }
        }
        if (count($genutzt) > 1) {
            $fehler[] = 'In Version 1 müssen alle Ressourcen dieselbe Verbindung nutzen (genutzt: '
                      . implode(', ', array_keys($genutzt)) . '). Mehrere Verbindungen kommen mit dem ConnectionManager (Phase 2).';
        }
        return $fehler;
    }

    private static function text(mixed $wert): string
    {
        return is_scalar($wert) ? (string) $wert : gettype($wert);
    }
}
