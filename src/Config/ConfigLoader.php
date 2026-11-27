<?php
declare(strict_types=1);

namespace Gateway\Config;

use RuntimeException;

/**
 * Lädt die Konfiguration aus dem Ordner config/:
 *   1. config/.env         Zugangsdaten und Schalter (NAME=Wert), nicht im Git
 *   2. config/gateway.php  gibt ein Array zurück und bekommt die .env-Werte als $env
 *
 * Die .env-Werte werden NICHT mit putenv() gesetzt: So wirkt eine Änderung an der .env
 * sofort (ohne Apache-Neustart), und das Passwort steht nicht in der Prozessumgebung.
 */
final class ConfigLoader
{
    public static function load(string $ordner): array
    {
        $envDatei = $ordner . '/.env';
        if (!is_file($envDatei)) {
            throw new RuntimeException(
                'config/.env fehlt. Bitte config/.env.example nach config/.env kopieren und die Zugangsdaten eintragen.'
            );
        }
        $configDatei = $ordner . '/gateway.php';
        if (!is_file($configDatei)) {
            throw new RuntimeException('config/gateway.php fehlt.');
        }
        if (file_get_contents($configDatei, false, null, 0, 3) === "\xEF\xBB\xBF") {
            // Ein BOM würde vor dem ersten header() ausgegeben und die Antwort kaputt machen
            throw new RuntimeException('config/gateway.php beginnt mit einem UTF-8-BOM. Im Editor "ohne BOM" speichern.');
        }

        $env    = self::readEnv($envDatei);
        $config = (static fn(array $env) => require $configDatei)($env);   // gateway.php sieht nur $env

        if (!is_array($config)) {
            throw new RuntimeException('config/gateway.php muss ein Array zurückgeben (return [ … ];).');
        }
        return $config;
    }

    /**
     * Liest NAME=Wert-Zeilen. Leerzeilen und Kommentare (#) werden übersprungen,
     * Anführungszeichen um den Wert entfernt. Ergebnis: ['DB_USER' => 'root', …]
     */
    public static function readEnv(string $datei): array
    {
        $werte = [];
        foreach (file($datei, FILE_IGNORE_NEW_LINES) ?: [] as $nr => $zeile) {
            if ($nr === 0) {
                $zeile = preg_replace('/^\xEF\xBB\xBF/', '', $zeile);   // BOM vom Editor überspringen
            }
            $zeile = trim($zeile);
            if ($zeile === '' || str_starts_with($zeile, '#')) {
                continue;
            }
            if (!preg_match('/^(?:export\s+)?([A-Za-z_][A-Za-z0-9_]*)\s*=\s*(.*)$/', $zeile, $t)) {
                throw new RuntimeException('config/.env, Zeile ' . ($nr + 1) . ': erwartet wird NAME=Wert.');
            }
            $wert = $t[2];
            if (preg_match('/^"(.*)"$/', $wert, $q) || preg_match("/^'(.*)'$/", $wert, $q)) {
                $wert = $q[1];                                         // "mit Leerzeichen # und Raute"
            } else {
                $wert = trim((string) preg_replace('/\s+#.*$/', '', $wert));   // Kommentar am Zeilenende
            }
            $werte[$t[1]] = $wert;
        }
        return $werte;
    }
}
