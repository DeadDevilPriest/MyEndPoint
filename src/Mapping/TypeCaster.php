<?php
declare(strict_types=1);

namespace Gateway\Mapping;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Typerkennung: Die Datenbank ist das Template.
 *
 * fromSql() macht aus einem Spaltentyp der Datenbank eine einheitliche Beschreibung, z. B.
 *     "int(10) unsigned"  → ['datentyp' => 'int', 'min' => 0, 'max' => 4294967295]
 *     "decimal(6,2)"      → ['datentyp' => 'decimal', 'stellen' => 6, 'nachkomma' => 2]
 *     "varchar(50)"       → ['datentyp' => 'string', 'laenge' => 50]
 *     "enum('PKW','LKW')" → ['datentyp' => 'string', 'werte' => ['PKW', 'LKW']]
 *     "date"              → ['datentyp' => 'datetime', 'format' => 'Y-m-d']
 *
 * cast() prüft einen Eingabewert gegen diese Beschreibung und wandelt ihn in das Format um,
 * das die Datenbank erwartet. Passt der Wert nicht, kommt eine InvalidArgumentException mit
 * einer deutschen Meldung – noch bevor die Datenbank den Wert zu sehen bekommt.
 *
 * Datentypen (wie im README, Abschnitt "Typen"): int, float, decimal, string, bool, datetime.
 */
final class TypeCaster
{
    // Ganzzahl-Typen: [kleinster Wert, größter Wert, größter Wert ohne Vorzeichen (unsigned)]
    private const GANZZAHLEN = [
        'tinyint'   => [-128, 127, 255],
        'smallint'  => [-32768, 32767, 65535],
        'mediumint' => [-8388608, 8388607, 16777215],
        'int'       => [-2147483648, 2147483647, 4294967295],
        'integer'   => [-2147483648, 2147483647, 4294967295],
        'bigint'    => [PHP_INT_MIN, PHP_INT_MAX, PHP_INT_MAX],   // unsigned geht in PHP nur bis PHP_INT_MAX
    ];

    // TEXT- und BLOB-Typen: Höchstlänge in BYTES (bei VARCHAR zählt MySQL dagegen Zeichen)
    private const TEXTE = [
        'tinytext' => 255, 'text' => 65535, 'mediumtext' => 16777215, 'longtext' => null,
        'tinyblob' => 255, 'blob' => 65535, 'mediumblob' => 16777215, 'longblob' => null,
    ];

    /** Beschreibung eines Spaltentyps aus der Datenbank (z. B. "int(10) unsigned"). */
    public static function fromSql(string $sqlTyp): array
    {
        $klein    = strtolower(trim($sqlTyp));
        $unsigned = str_contains($klein, 'unsigned');
        preg_match('/^([a-z ]+?)\s*(?:\((.*)\))?(?:\s|$)/', $klein, $teile);
        $basis     = trim($teile[1] ?? $klein);
        $argumente = $teile[2] ?? '';
        $zahl      = ctype_digit($argumente) ? (int) $argumente : null;

        // TINYINT(1) bzw. BOOLEAN: in MySQL der übliche Ja/Nein-Typ
        if (($basis === 'tinyint' && $argumente === '1') || $basis === 'bool' || $basis === 'boolean') {
            return ['datentyp' => 'bool'];
        }
        if (isset(self::GANZZAHLEN[$basis])) {
            [$min, $max, $maxOhneVorzeichen] = self::GANZZAHLEN[$basis];
            return $unsigned
                ? ['datentyp' => 'int', 'min' => 0, 'max' => $maxOhneVorzeichen]
                : ['datentyp' => 'int', 'min' => $min, 'max' => $max];
        }
        if ($basis === 'year') {
            return ['datentyp' => 'int', 'min' => 1901, 'max' => 2155];
        }
        if ($basis === 'bit') {
            $bits = $zahl ?? 1;
            return ['datentyp' => 'int', 'min' => 0, 'max' => $bits >= 63 ? PHP_INT_MAX : (1 << $bits) - 1];
        }
        if (in_array($basis, ['decimal', 'numeric', 'dec', 'fixed'], true)) {
            [$stellen, $nachkomma] = array_map('intval', explode(',', $argumente . ',0')) + [10, 0];
            $typ = ['datentyp' => 'decimal', 'stellen' => $stellen ?: 10, 'nachkomma' => $nachkomma];
            return $unsigned ? $typ + ['min' => 0] : $typ;
        }
        if (in_array($basis, ['float', 'double', 'real', 'double precision'], true)) {
            return $unsigned ? ['datentyp' => 'float', 'min' => 0] : ['datentyp' => 'float'];
        }
        if ($basis === 'char' || $basis === 'varchar') {
            return ['datentyp' => 'string', 'laenge' => $zahl ?? 1];
        }
        if ($basis === 'binary' || $basis === 'varbinary') {
            return ['datentyp' => 'string', 'laenge' => $zahl ?? 1, 'bytes' => true];
        }
        if (array_key_exists($basis, self::TEXTE)) {
            return self::TEXTE[$basis] === null
                ? ['datentyp' => 'string']
                : ['datentyp' => 'string', 'laenge' => self::TEXTE[$basis], 'bytes' => true];
        }
        if ($basis === 'enum' || $basis === 'set') {
            // Werte in Originalschreibweise aus dem unveränderten Typ lesen: enum('PKW','LKW')
            preg_match_all("/'((?:[^']|'')*)'/", $sqlTyp, $treffer);
            $werte = array_map(fn(string $w) => str_replace("''", "'", $w), $treffer[1]);
            return $basis === 'set'
                ? ['datentyp' => 'string', 'werte' => $werte, 'mehrfach' => true]
                : ['datentyp' => 'string', 'werte' => $werte];
        }
        if ($basis === 'date') {
            return ['datentyp' => 'datetime', 'format' => 'Y-m-d'];
        }
        if ($basis === 'datetime' || $basis === 'timestamp') {
            return ['datentyp' => 'datetime', 'format' => 'Y-m-d H:i:s'];
        }
        if ($basis === 'time') {
            return ['datentyp' => 'string', 'format' => 'H:i:s'];
        }
        if ($basis === 'json') {
            return ['datentyp' => 'string', 'format' => 'json'];
        }
        return ['datentyp' => 'string'];   // unbekannter Typ: annehmen, die Datenbank prüft selbst
    }

    /**
     * Prüft einen Eingabewert und wandelt ihn passend zum Typ um.
     * null bleibt null (ob ein Feld leer sein darf, prüft der Aufrufer).
     *
     * @throws InvalidArgumentException mit einer Meldung wie "ganze Zahl von 0 bis 255 erwartet"
     */
    public static function cast(mixed $wert, array $typ): mixed
    {
        if ($wert === null) {
            return null;
        }
        if (is_array($wert) || is_object($wert)) {
            throw new InvalidArgumentException('einzelner Wert erwartet, keine Liste');
        }
        return match ($typ['datentyp'] ?? 'string') {
            'int'      => self::castInt($wert, $typ),
            'bool'     => self::castBool($wert),
            'float'    => self::castFloat($wert, $typ),
            'decimal'  => self::castDecimal($wert, $typ),
            'datetime' => self::castDateTime($wert, $typ),
            default    => self::castString($wert, $typ),
        };
    }

    // ── Die einzelnen Typen ──────────────────────────────────────────

    private static function castInt(mixed $wert, array $typ): int
    {
        if (is_bool($wert)) {
            $zahl = (int) $wert;                          // true/false → 1/0
        } elseif (is_int($wert)) {
            $zahl = $wert;
        } elseif (is_float($wert) && floor($wert) === $wert && abs($wert) < 9.2e18) {
            $zahl = (int) $wert;                          // 5.0 aus JSON ist auch eine ganze Zahl
        } elseif (is_string($wert) && preg_match('/^([+-]?)0*(\d+)$/', trim($wert), $t)) {
            $text = ($t[1] === '-' && $t[2] !== '0' ? '-' : '') . $t[2];
            $zahl = (int) $text;
            if ((string) $zahl !== $text) {               // zu groß für PHP → Überlauf
                throw new InvalidArgumentException(self::bereich($typ));
            }
        } else {
            throw new InvalidArgumentException('ganze Zahl erwartet');
        }
        if ((isset($typ['min']) && $zahl < $typ['min']) || (isset($typ['max']) && $zahl > $typ['max'])) {
            throw new InvalidArgumentException(self::bereich($typ));
        }
        return $zahl;
    }

    private static function castBool(mixed $wert): int
    {
        $text = is_string($wert) ? strtolower(trim($wert)) : $wert;
        if (in_array($text, [true, 1, '1', 'true', 'ja'], true)) {
            return 1;
        }
        if (in_array($text, [false, 0, '0', 'false', 'nein'], true)) {
            return 0;
        }
        throw new InvalidArgumentException('Ja/Nein erwartet (true/false, 1/0 oder ja/nein)');
    }

    private static function castFloat(mixed $wert, array $typ): float
    {
        if (is_int($wert) || is_float($wert)) {
            $zahl = (float) $wert;
        } elseif (is_string($wert) && is_numeric($text = self::dezimalkomma(trim($wert)))) {
            $zahl = (float) $text;
        } else {
            throw new InvalidArgumentException('Zahl erwartet');
        }
        if (!is_finite($zahl)) {
            throw new InvalidArgumentException('Zahl erwartet');
        }
        if (isset($typ['min']) && $zahl < $typ['min']) {
            throw new InvalidArgumentException('Zahl ab ' . $typ['min'] . ' erwartet');
        }
        return $zahl;
    }

    // DECIMAL bleibt ein Text, damit nichts gerundet wird: "12.50" statt 12.4999999…
    private static function castDecimal(mixed $wert, array $typ): string
    {
        $text = match (true) {
            is_int($wert)    => (string) $wert,
            is_float($wert)  => (string) $wert,
            is_string($wert) => self::dezimalkomma(trim($wert)),
            default          => '',
        };
        if (!preg_match('/^([+-]?)(\d*)(?:\.(\d*))?$/', $text, $t) || ($t[2] === '' && ($t[3] ?? '') === '')) {
            throw new InvalidArgumentException('Dezimalzahl erwartet, z. B. 12.50 oder 12,50');
        }
        $nachkomma    = $typ['nachkomma'] ?? 0;
        $vorkomma     = ($typ['stellen'] ?? 10) - $nachkomma;
        $ganzzahlteil = ltrim($t[2], '0');
        $bruchteil    = rtrim($t[3] ?? '', '0');          // 12.50 hat nur 1 echte Nachkommastelle

        if (strlen($bruchteil) > $nachkomma) {
            throw new InvalidArgumentException("höchstens $nachkomma Nachkommastellen erlaubt");
        }
        if (strlen($ganzzahlteil) > $vorkomma) {
            throw new InvalidArgumentException("Zahl zu groß: höchstens $vorkomma Stellen vor dem Komma");
        }
        $istNull = $ganzzahlteil === '' && $bruchteil === '';
        if (isset($typ['min']) && $t[1] === '-' && !$istNull) {
            throw new InvalidArgumentException('Zahl ab 0 erwartet');
        }
        return ($t[1] === '-' && !$istNull ? '-' : '') . ($ganzzahlteil === '' ? '0' : $ganzzahlteil)
             . ($bruchteil === '' ? '' : '.' . $bruchteil);
    }

    private static function castDateTime(mixed $wert, array $typ): string
    {
        $format   = $typ['format'] ?? 'Y-m-d H:i:s';
        $nurDatum = $format === 'Y-m-d';
        $muster   = $nurDatum
            ? ['Y-m-d', 'd.m.Y']
            : ['Y-m-d H:i:s', 'Y-m-d H:i', 'Y-m-d\TH:i:s', 'Y-m-d\TH:i', 'd.m.Y H:i:s', 'd.m.Y H:i', 'Y-m-d', 'd.m.Y'];

        if (is_string($wert)) {
            $text = trim($wert);
            foreach ($muster as $m) {
                // "!" setzt nicht angegebene Teile auf 0 (Uhrzeit 00:00:00)
                $datum   = DateTimeImmutable::createFromFormat('!' . $m, $text);
                $meldung = DateTimeImmutable::getLastErrors();   // PHP 8.1: Array, ab 8.2: false ohne Fehler
                $sauber  = $meldung === false || ($meldung['warning_count'] === 0 && $meldung['error_count'] === 0);
                if ($datum !== false && $sauber) {               // 31.02. ergibt eine Warnung → abgelehnt
                    return $datum->format($format);
                }
            }
        }
        throw new InvalidArgumentException($nurDatum
            ? 'Datum erwartet (JJJJ-MM-TT oder TT.MM.JJJJ)'
            : 'Datum mit Uhrzeit erwartet (JJJJ-MM-TT hh:mm[:ss])');
    }

    private static function castString(mixed $wert, array $typ): string
    {
        $text = is_bool($wert) ? ($wert ? '1' : '0') : (string) $wert;

        if (($typ['format'] ?? '') === 'H:i:s') {                     // TIME
            if (!preg_match('/^(-?\d{1,3}):([0-5]\d)(?::([0-5]\d))?$/', trim($text), $t)) {
                throw new InvalidArgumentException('Uhrzeit erwartet (hh:mm oder hh:mm:ss)');
            }
            return sprintf('%s:%s:%s', $t[1], $t[2], $t[3] ?? '00');
        }
        if (($typ['format'] ?? '') === 'json') {
            json_decode($text);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new InvalidArgumentException('gültiges JSON erwartet');
            }
        }
        if (isset($typ['werte'])) {                                   // ENUM / SET
            $teile = !empty($typ['mehrfach']) ? array_map('trim', explode(',', $text)) : [$text];
            foreach ($teile as $i => $teil) {
                $treffer = self::wertFinden($teil, $typ['werte']);
                if ($treffer === null) {
                    throw new InvalidArgumentException('erlaubt sind nur: ' . implode(', ', $typ['werte']));
                }
                $teile[$i] = $treffer;
            }
            return implode(',', $teile);
        }
        if (isset($typ['laenge'])) {
            $laenge = !empty($typ['bytes']) ? strlen($text) : mb_strlen($text);
            if ($laenge > $typ['laenge']) {
                throw new InvalidArgumentException('höchstens ' . $typ['laenge']
                                                   . (!empty($typ['bytes']) ? ' Bytes' : ' Zeichen') . ' erlaubt');
            }
        }
        return $text;
    }

    // ── Hilfsfunktionen ──────────────────────────────────────────────

    // "4,2" → "4.2" (deutsches Dezimalkomma, aber nur wenn es eindeutig ist)
    private static function dezimalkomma(string $text): string
    {
        return substr_count($text, ',') === 1 && !str_contains($text, '.') ? str_replace(',', '.', $text) : $text;
    }

    // ENUM-Werte vergleicht MySQL ohne Groß-/Kleinschreibung → Originalschreibweise zurückgeben
    private static function wertFinden(string $text, array $werte): ?string
    {
        foreach ($werte as $wert) {
            if (mb_strtolower($wert) === mb_strtolower($text)) {
                return $wert;
            }
        }
        return null;
    }

    private static function bereich(array $typ): string
    {
        return isset($typ['min'], $typ['max'])
            ? 'ganze Zahl von ' . $typ['min'] . ' bis ' . $typ['max'] . ' erwartet'
            : 'ganze Zahl erwartet';
    }
}
