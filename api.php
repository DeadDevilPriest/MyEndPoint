<?php
declare(strict_types=1);

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?:'/';
$basePath = rtrim(str_replace('\\','/',dirname($_SERVER['SCRIPT_NAME'])), '/');
$route = trim(substr($path,strlen($basePath)),'/');
$route = preg_replace('#^index\.php/?#','',$route);

$method = $_SERVER['REQUEST_METHOD'];
$segments = explode('/', $route);
$resource = $segments[0];

// ============================================================
// Fahrzeug-API (nur PHP)
// Speichert jedes Fahrzeug als eigene JSON-Datei in MyEndPoint/data/
// ============================================================

// Die Antwort ist reiner Text, kein HTML
header('Content-Type: text/plain; charset=UTF-8');

// ------------------------------------------------------------
// 1) Nur POST zulassen (ein HTML-Formular kann nur GET/POST senden)
// ------------------------------------------------------------
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405); // 405 = Methode nicht erlaubt
    echo 'Keine Daten empfangen. Bitte das Formular benutzen.';
    exit;
}

// ------------------------------------------------------------
// 2) Werte erfassen und den Variablen zuweisen
//    Schlüssel in $_POST = name-Attribut im Formular
// ------------------------------------------------------------
$erlaubteTypen       = ['auto', 'motorrad'];
$erlaubteOperationen = ['GET', 'POST', 'UPDATE', 'DELETE'];

// Liest einen Text aus $_POST.
// Fehlt der Wert oder ist er ein Array (z. B. name="operation[]"), kommt '' zurück.
function postText(string $schluessel): string
{
    $wert = $_POST[$schluessel] ?? '';
    return is_string($wert) ? $wert : '';
}

$fahrzeugName     = trim(postText('fahrzeug-name'));
$fahrzeugType     = postText('fahrzeug-type');
$fahrzeugLeistung = filter_var(postText('fahrzeug-leistung'), FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 0],
]);                                      // int oder false
$operation        = postText('operation');

// GET und DELETE brauchen nur den Namen, POST und UPDATE auch Typ und Leistung
$brauchtDaten = in_array($operation, ['post', 'update'], true);

// Dateiname aus dem Fahrzeugnamen: klein, ohne Umlaute, Leerzeichen -> _
// Beispiel: "VW Käfer" -> "vw_kaefer"
$um       = ['ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'ß' => 'ss', ' ' => '_'];
$fileName = strtr(mb_strtolower($fahrzeugName), $um);

// ------------------------------------------------------------
// 3) Werte prüfen
// ------------------------------------------------------------
$fehler = [];

if (!in_array($operation, $erlaubteOperationen, true)) {
    $fehler[] = 'Bitte eine gültige Operation wählen.';
}
if ($fahrzeugName === '') {
    $fehler[] = 'Bitte einen Namen eingeben.';
} elseif (!preg_match('/^[a-z0-9_-]+$/', $fileName)) {
    // Schutz: verhindert z. B. "../../" im Dateinamen
    $fehler[] = 'Der Name darf nur Buchstaben, Ziffern, Leerzeichen, - und _ enthalten.';
}
if ($brauchtDaten && !in_array($fahrzeugType, $erlaubteTypen, true)) {
    $fehler[] = 'Bitte einen gültigen Typ wählen.';
}
if ($brauchtDaten && $fahrzeugLeistung === false) {
    $fehler[] = 'Leistung muss eine ganze Zahl ≥ 0 sein.';
}

if ($fehler !== []) {
    http_response_code(400); // 400 = fehlerhafte Anfrage
    echo "Fehler:\n- " . implode("\n- ", $fehler);
    exit;
}

// ------------------------------------------------------------
// 4) Speicherort vorbereiten
// ------------------------------------------------------------
$fileDir = __DIR__ . '/data';

if (!is_dir($fileDir) && !mkdir($fileDir)) {
    http_response_code(500); // 500 = Fehler auf dem Server
    echo 'Der Ordner "data" konnte nicht angelegt werden.';
    exit;
}

// Vollständiger Pfad, z. B. C:\xampp\htdocs\MyEndPoint/data/kaefer.json
$filePath = $fileDir . '/' . $fileName . '.json';

// Schreibt die Fahrzeugdaten als JSON in die Datei.
// Gibt true zurück, wenn es geklappt hat.
function speichern(string $filePath, array $daten): bool
{
    $jsonString = json_encode($daten, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    return $jsonString !== false && file_put_contents($filePath, $jsonString) !== false;
}

// ------------------------------------------------------------
// 5) Anfrage-Zeile bauen, z. B.
//    HTTP POST http://localhost/MyEndPoint/api.php?Golf,auto,150
// ------------------------------------------------------------
$httpMethoden = ['GET'=>'GET', 'POST'=>'POST', 'UPDATE' => 'PUT', 'DELETE'=>'DELETE'];

$parameter = $brauchtDaten
    ? [$fahrzeugName, $fahrzeugType, $fahrzeugLeistung]
    : [$fahrzeugName];

$url = 'http://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . $_SERVER['SCRIPT_NAME']
     . '?' . implode(',', $parameter);

$anfrage = "HTTP {$httpMethoden[$operation]} {$url}";

// ------------------------------------------------------------
// 6) Operation ausführen
//    Zuerst nur Statuscode und Meldung festlegen, ausgegeben wird am Ende.
// ------------------------------------------------------------
$status  = 200;
$meldung = '';

// Das kommt in die Datei – mit dem Originalnamen, also mit Umlauten
$daten = [
    'name'     => $fahrzeugName,
    'typ'      => $fahrzeugType,
    'leistung' => $fahrzeugLeistung,
];

switch ($operation) {
    case 'GET':
        if (!file_exists($filePath)) {
            $status  = 404; // 404 = nicht gefunden
            $meldung = "Fahrzeug \"$fahrzeugName\" nicht gefunden.";
        } else {
            $meldung = (string) file_get_contents($filePath);
        }
        break;

    case 'POST':
        if (file_exists($filePath)) {
            $status  = 409; // 409 = Konflikt, gibt es schon
            $meldung = "Fahrzeug \"$fahrzeugName\" existiert bereits.";
        } elseif (!speichern($filePath, $daten)) {
            $status  = 500;
            $meldung = 'Fehler beim Speichern.';
        } else {
            $status  = 201; // 201 = angelegt
            $meldung = "Fahrzeug \"$fahrzeugName\" wurde angelegt.";
        }
        break;

    case 'UPDATE':
        if (!file_exists($filePath)) {
            $status  = 404;
            $meldung = "Fahrzeug \"$fahrzeugName\" nicht gefunden.";
        } elseif (!speichern($filePath, $daten)) {
            $status  = 500;
            $meldung = 'Fehler beim Speichern.';
        } else {
            $meldung = "Fahrzeug \"$fahrzeugName\" wurde geändert.";
        }
        break;

    case 'DELETE':
        if (!file_exists($filePath)) {
            $status  = 404;
            $meldung = "Fahrzeug \"$fahrzeugName\" nicht gefunden.";
        } elseif (!unlink($filePath)) {
            $status  = 500;
            $meldung = 'Fehler beim Löschen.';
        } else {
            $meldung = "Fahrzeug \"$fahrzeugName\" wurde gelöscht.";
        }
        break;

    default:
        $status  = 400;
        $meldung = 'Unbekannte Operation.';
}

// ------------------------------------------------------------
// 7) Antwort ausgeben
// ------------------------------------------------------------
http_response_code($status);
echo /*$anfrage . "\n\n" .*/ $meldung;