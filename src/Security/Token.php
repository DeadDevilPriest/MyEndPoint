<?php
declare(strict_types=1);

namespace Gateway\Security;

/**
 * Token für die Anmeldung: "mep1.<Inhalt>.<Signatur>"
 *
 *   Inhalt   = Base64url von JSON, z. B. {"k":1,"n":"admin","exp":1764000000}
 *   Signatur = HMAC-SHA256 über "mep1.<Inhalt>" mit TOKEN_SECRET
 *
 * Der Inhalt ist nur kodiert, nicht verschlüsselt – deshalb steht darin nichts Geheimes,
 * nur Konto-ID, Name und Ablaufzeit. Fälschen kann ihn ohne das Secret niemand:
 * jede Änderung macht die Signatur ungültig.
 */
final class Token
{
    public const PRAEFIX = 'mep1.';

    /** Erstellt einen Token. Ergebnis: ['token' => 'mep1.…', 'gueltigBis' => Unix-Zeit] */
    public static function create(array $inhalt, string $secret, int $gueltigkeit, ?int $jetzt = null): array
    {
        $inhalt['exp'] = ($jetzt ?? time()) + $gueltigkeit;
        $daten = self::base64url(json_encode($inhalt, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
        return [
            'token'      => self::PRAEFIX . $daten . '.' . self::signatur($daten, $secret),
            'gueltigBis' => $inhalt['exp'],
        ];
    }

    /** Prüft Signatur und Ablaufzeit. Ergebnis: der Inhalt oder null (falsch, verändert, abgelaufen). */
    public static function verify(string $token, string $secret, ?int $jetzt = null): ?array
    {
        if (!str_starts_with($token, self::PRAEFIX) || substr_count($token, '.') !== 2) {
            return null;
        }
        [$daten, $signatur] = explode('.', substr($token, strlen(self::PRAEFIX)), 2);

        // hash_equals vergleicht in gleicher Zeit – die Laufzeit verrät nichts über die richtige Signatur
        if (!hash_equals(self::signatur($daten, $secret), $signatur)) {
            return null;
        }
        $json   = base64_decode(strtr($daten, '-_', '+/'), true);
        $inhalt = $json === false ? null : json_decode($json, true);
        if (!is_array($inhalt) || !is_int($inhalt['exp'] ?? null) || $inhalt['exp'] < ($jetzt ?? time())) {
            return null;
        }
        return $inhalt;
    }

    private static function signatur(string $daten, string $secret): string
    {
        return self::base64url(hash_hmac('sha256', self::PRAEFIX . $daten, $secret, true));
    }

    // Base64 ohne +, / und = – passt so in Header und URLs
    private static function base64url(string $bytes): string
    {
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }
}
