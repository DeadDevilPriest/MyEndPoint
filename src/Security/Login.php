<?php
declare(strict_types=1);

namespace Gateway\Security;

use PDO;

/**
 * Anmeldung gegen die Login-Tabelle (Benutzername oder E-Mail + Passwort).
 *
 * Welche Tabelle und welche Spalten, steht in config/gateway.php unter sicherheit.login:
 *   ['tabelle' => 'benutzer', 'spalten' => ['id' => 'IID', 'name' => 'username',
 *                                           'email' => 'useremail', 'passwort' => 'password']]
 *
 * In der Spalte für das Passwort steht ein Hash von password_hash(), nie das Passwort selbst.
 * Geprüft wird nur bei der Anmeldung (POST /_login) – danach reicht der Token.
 */
final class Login
{
    // Hash eines zufälligen, unbekannten Passworts. Gibt es das Konto nicht, wird trotzdem
    // einmal password_verify() ausgeführt: So dauert "falsche E-Mail" genauso lange wie
    // "falsches Passwort", und man kann nicht ausprobieren, welche Konten es gibt.
    private const DUMMY_HASH = '$2y$10$p8/c/.Y9wRzKjuYDzb7WgOUqaqkcxMls4dghK6ywxvRsUgo.TA.zu';

    /**
     * Prüft Benutzername bzw. E-Mail und Passwort.
     * Ergebnis: ['id' => …, 'name' => …, 'email' => …] oder null.
     *
     * @throws KlartextPasswort wenn das gespeicherte Passwort kein Hash ist (Hilfe beim Einrichten)
     */
    public static function check(PDO $pdo, array $einstellung, string $login, string $passwort): ?array
    {
        $s = $einstellung['spalten'];
        // Gibt es zufällig eine E-Mail, die wie ein anderer Benutzername lautet, gewinnt die E-Mail
        $abfrage = $pdo->prepare(
            'SELECT ' . self::q($s['id']) . ' AS id, ' . self::q($s['name']) . ' AS name, '
            . self::q($s['email']) . ' AS email, ' . self::q($s['passwort']) . ' AS hash'
            . ' FROM ' . self::q($einstellung['tabelle'])
            . ' WHERE ' . self::q($s['email']) . ' = ? OR ' . self::q($s['name']) . ' = ?'
            . ' ORDER BY ' . self::q($s['email']) . ' = ? DESC LIMIT 1'
        );
        $abfrage->execute([$login, $login, $login]);
        $konto = $abfrage->fetch(PDO::FETCH_ASSOC);

        if ($konto === false) {
            password_verify($passwort, self::DUMMY_HASH);   // gleiche Laufzeit, Ergebnis egal
            return null;
        }
        if (!password_verify($passwort, (string) $konto['hash'])) {
            if (password_get_info((string) $konto['hash'])['algoName'] === 'unknown') {
                throw new KlartextPasswort('Das Passwort dieses Kontos ist nicht mit password_hash() gespeichert.');
            }
            return null;
        }
        unset($konto['hash']);   // den Hash nie weitergeben
        return $konto;
    }

    /** Konto über die ID holen – bei jeder Anfrage: Wurde das Konto inzwischen gelöscht? */
    public static function find(PDO $pdo, array $einstellung, int|string $id): ?array
    {
        $s = $einstellung['spalten'];
        $abfrage = $pdo->prepare(
            'SELECT ' . self::q($s['id']) . ' AS id, ' . self::q($s['name']) . ' AS name, '
            . self::q($s['email']) . ' AS email FROM ' . self::q($einstellung['tabelle'])
            . ' WHERE ' . self::q($s['id']) . ' = ?'
        );
        $abfrage->execute([$id]);
        $konto = $abfrage->fetch(PDO::FETCH_ASSOC);
        return $konto === false ? null : $konto;
    }

    // Tabellen- und Spaltennamen kommen aus der Config, werden aber trotzdem in Backticks gesetzt
    private static function q(string $name): string
    {
        return '`' . str_replace('`', '``', $name) . '`';
    }
}
