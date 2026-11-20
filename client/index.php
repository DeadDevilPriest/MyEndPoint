<?php
// Operationen wie bisher. UPDATE wird an die API als PATCH geschickt:
// geändert werden nur die Felder, die man im Formular angefasst hat.
$erlaubteOperationen = ['GET', 'POST', 'UPDATE', 'DELETE'];

// Adresse der API, von dieser Seite aus gesehen: client/ liegt im API-Ordner.
// Mit der .htaccess geht auch '../api'.
$apiUrl = '../api.php';
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Fahrzeugverwaltung</title>
    <style>
        body { font-family: system-ui, sans-serif; max-width: 900px; margin: 2rem auto; padding: 0 1rem; }
        form { max-width: 520px; }
        fieldset { border: 1px solid #ccc; border-radius: 6px; padding: 1rem; }
        label { display: block; margin-top: .75rem; font-weight: 600; }
        .feld input, .feld select, #ressource { width: 100%; padding: .4rem; box-sizing: border-box; }
        .radios label { display: inline-block; font-weight: normal; margin-right: 1rem; }
        button { margin-top: 1rem; padding: .5rem 1.2rem; }
        .hinweis { color: #555; font-size: .9rem; margin: .5rem 0 0; }

        #status { padding: .6rem .8rem; border-radius: 6px; font-weight: 600; }
        #status.ok     { background: #e6f4ea; color: #1e6b34; }
        #status.fehler { background: #fdecea; color: #a12622; }
        .tabelle { overflow-x: auto; }
        table { border-collapse: collapse; width: 100%; margin-top: .75rem; }
        th, td { border-bottom: 1px solid #ddd; padding: .35rem .5rem; text-align: left; white-space: nowrap; }
        tbody tr { cursor: pointer; }
        tbody tr:hover { background: #f1f5fb; }
        details { margin-top: 1rem; }
        pre { background: #f6f6f6; padding: .75rem; overflow-x: auto; font-size: .85rem; }
        #angemeldet { display: flex; gap: 1rem; align-items: center; max-width: 520px; margin: 0 0 1rem; }
        #angemeldet[hidden] { display: none; }
        #angemeldet button { margin: 0 0 0 auto; padding: .3rem .8rem; }
        #schluessel { width: 100%; padding: .4rem; box-sizing: border-box; }
    </style>
</head>
<body>

<h1>Fahrzeugverwaltung</h1>

<!-- Anmeldung: Den API-Schlüssel bekommt man vom Admin (Tabelle benutzer, siehe benutzer.sql) -->
<form id="anmeldung">
    <fieldset>
        <label for="schluessel">API-Schlüssel</label>
        <input type="password" id="schluessel" autocomplete="current-password" required>
        <button type="submit">Anmelden</button>
    </fieldset>
</form>

<p id="angemeldet" hidden>
    <span>Angemeldet als <strong id="wer"></strong></span>
    <button type="button" id="abmelden">Abmelden</button>
</p>

<form id="formular" hidden>
    <fieldset>
        <label for="ressource">Tabelle</label>
        <select id="ressource"></select>

        <!-- Die Eingabefelder baut das Skript unten aus den Spalten, die die API meldet -->
        <div id="felder"><p class="hinweis">Lade Felder …</p></div>

        <label>Operation</label>
        <div class="radios">
            <?php foreach ($erlaubteOperationen as $operation): ?>
                <label for="op-<?= $operation ?>">
                    <input type="radio" id="op-<?= $operation ?>" name="operation"
                           value="<?= $operation ?>" <?= $operation === 'GET' ? 'checked' : '' ?>>
                    <?= $operation ?>
                </label>
            <?php endforeach; ?>
        </div>
        <p class="hinweis" id="hinweis"></p>

        <button type="submit">Absenden</button>
        <button type="button" id="leeren">Leeren</button>
    </fieldset>
</form>

<noscript><p>Diese Seite braucht JavaScript.</p></noscript>

<section id="ergebnis" hidden>
    <h2>Ergebnis</h2>
    <p id="status"></p>
    <ul id="details"></ul>
    <div class="tabelle" id="tabelle"></div>
    <details>
        <summary>Anfrage und Antwort im Detail</summary>
        <pre id="roh"></pre>
    </details>
</section>

<script>
// ── 1) Einstellungen ───────────────────────────────────────────
const API = <?= json_encode($apiUrl, JSON_HEX_TAG | JSON_UNESCAPED_SLASHES) ?>;

// Operation im Formular → HTTP-Methode für die API
const METHODEN = { GET: 'GET', POST: 'POST', UPDATE: 'PATCH', DELETE: 'DELETE' };

const HINWEISE = {
    GET:    'Mit ID: ein Datensatz. Ohne ID: Liste – ausgefüllte Felder filtern, * ist Platzhalter (z. B. VW*).',
    POST:   'Legt einen neuen Datensatz an. Eine automatische ID vergibt die Datenbank. * = Pflichtfeld',
    UPDATE: 'Ändert den Datensatz mit dieser ID. Gesendet werden nur die Felder, die du geändert hast.',
    DELETE: 'Löscht den Datensatz mit dieser ID.',
};

const MELDUNGEN = { GET: 'Gefunden', POST: 'Angelegt', PATCH: 'Geändert', DELETE: 'Gelöscht' };

// Eigene Beschriftungen für Spalten (Name genau wie in der Datenbank).
// Primärschlüssel heißen immer "ID". Alle anderen Spalten werden aus dem Namen gebildet.
const BESCHRIFTUNGEN = {
    Fahrzeug_Name: 'Name',
    Leistung:      'Leistung (PS)',
    FAID:          'Fahrzeugart',     // Verweis auf fahrzeugarten
    FTID:          'Fahrzeugtyp',     // Verweis auf fahrzeugtypen
    TID:           'Treibstoff',      // Verweis auf treibstoffe
    FHID:          'Halter',          // Verweis auf fahrzeughalter
    FahrzeugTyp:   'Fahrzeugtyp',
    Fahrzeugarten: 'Fahrzeugart',
    Tel:           'Telefon',
};


// ── 2) Zustand ─────────────────────────────────────────────────
let ressourcen = {};          // Antwort von GET api.php: alle Tabellen mit ihren Feldern
let felder     = {};          // Felder der gewählten Tabelle
let pk         = null;        // Name des Primärschlüssels, meist "id"
let auswahlen  = {};          // Fremdschlüssel: Spalte → Map(Wert → lesbarer Text)
const geaendert = new Set();  // Felder, die seit dem letzten Laden geändert wurden
let apiSchluessel = '';       // wird bei jeder Anfrage im Header X-API-Key mitgeschickt
let rechte     = [];          // HTTP-Methoden, die die eigene Rolle darf (meldet die API)

const el = id => document.getElementById(id);
const aktuelleOperation = () => document.querySelector('input[name=operation]:checked').value;
const istGanzzahl = typ => /^(tiny|small|medium|big)?int\b/.test(typ);
const istZahl     = typ => istGanzzahl(typ) || /^(decimal|numeric|float|double|real)/.test(typ);


// ── 3) Verbindung zur API ──────────────────────────────────────
// Schickt eine Anfrage und liefert Status, JSON und alles für die Detail-Anzeige
async function anfrage(methode, pfad, daten = null) {
    // Der Schlüssel weist uns aus. Was wir dürfen, entscheidet die API anhand des Kontos.
    const optionen = { method: methode, headers: { 'X-API-Key': apiSchluessel } };
    if (daten !== null) {
        optionen.headers['Content-Type'] = 'application/json';
        optionen.body = JSON.stringify(daten);
    }
    const url     = new URL(API + pfad, location.href).href;
    const antwort = await fetch(url, optionen);
    const text    = await antwort.text();
    let json = null;
    try { json = text ? JSON.parse(text) : null; } catch { /* keine JSON-Antwort */ }
    return { methode, url, daten, status: antwort.status, ok: antwort.ok, json, text };
}

function verbindungsfehler(fehler) {
    const url = new URL(API, location.href).href;
    ergebnisZeigen({ methode: '', url, daten: null, status: 0, ok: false, text: '',
                     json: { fehler: 'Keine Verbindung zur API.', details: [fehler.message, 'Adresse: ' + url] } });
}


// ── 4) Anmelden und Start: Übersicht laden, Tabellen-Auswahl füllen ──
async function start() {
    let r;
    try {
        r = await anfrage('GET', '');
    } catch (fehler) {
        return verbindungsfehler(fehler);
    }
    if (r.status === 401) {            // Schlüssel falsch → zurück zur Anmeldung
        abmelden();
        return ergebnisZeigen(r);
    }
    if (!r.ok || !r.json?.ressourcen) {
        return ergebnisZeigen({ ...r, ok: false,
            json: { fehler: 'Unter dieser Adresse antwortet keine passende API.', details: [r.url, ...(r.json?.details ?? [])] } });
    }
    ressourcen = r.json.ressourcen;
    rechte     = r.json.angemeldet?.darf ?? [];

    // Anmeldung ausblenden, Formular zeigen
    const konto = r.json.angemeldet ?? {};
    el('wer').textContent  = `${konto.name} (Rolle: ${konto.rolle})`;
    el('anmeldung').hidden  = true;
    el('angemeldet').hidden = false;
    el('formular').hidden   = false;

    el('ressource').replaceChildren();
    for (const name of Object.keys(ressourcen)) {
        el('ressource').add(new Option(beschriftungFuer(name), name));
    }
    el('ressource').value = 'fahrzeuge' in ressourcen ? 'fahrzeuge' : Object.keys(ressourcen)[0];
    operationenFreigeben();
    await ressourceWechseln();
}

// Operationen, die die eigene Rolle nicht darf, ausgrauen (z. B. darf "nutzer" nur GET).
// Das ist nur Bequemlichkeit – verboten wird es von der API (403), nicht hier.
function operationenFreigeben() {
    for (const knopf of document.querySelectorAll('input[name=operation]')) {
        knopf.disabled = !rechte.includes(METHODEN[knopf.value]);
        knopf.closest('label').title = knopf.disabled ? 'Deine Rolle darf das nicht' : '';
    }
    if (document.querySelector('input[name=operation]:checked')?.disabled) {
        el('op-GET').checked = true;
    }
}

function abmelden() {
    apiSchluessel = '';
    schluesselMerken('');
    el('schluessel').value  = '';
    el('anmeldung').hidden  = false;
    el('angemeldet').hidden = true;
    el('formular').hidden   = true;
}

// Schlüssel nur für diesen Browser-Tab merken (sessionStorage), damit Neuladen nicht abmeldet.
// Beim Schließen des Tabs ist er weg.
function schluesselLaden() {
    try { return sessionStorage.getItem('apiSchluessel') ?? ''; } catch { return ''; }
}
function schluesselMerken(wert) {
    try {
        if (wert) sessionStorage.setItem('apiSchluessel', wert);
        else      sessionStorage.removeItem('apiSchluessel');
    } catch { /* ohne Speicher geht es auch, dann nur ohne Merken */ }
}

// Andere Tabelle gewählt: Auswahllisten laden, Felder neu bauen
async function ressourceWechseln() {
    el('ergebnis').hidden = true;
    felder    = ressourcen[el('ressource').value].felder;
    pk        = Object.keys(felder).find(name => felder[name].pk) ?? null;
    auswahlen = {};
    try {
        // Für Fremdschlüssel die Einträge der anderen Tabelle holen, z. B. typId → alle Fahrzeugtypen
        for (const [name, feld] of Object.entries(felder)) {
            if (feld.verweis) auswahlen[name] = await auswahlLaden(feld.verweis);
        }
    } catch (fehler) {
        verbindungsfehler(fehler);
    }
    formularBauen();
    operationWechseln();
}

async function auswahlLaden(verweis) {
    const r = await anfrage('GET', `/${verweis.ressource}?limit=1000`);
    const liste = new Map();
    for (const zeile of r.json?.daten ?? []) {
        liste.set(String(zeile[verweis.spalte]), lesbar(zeile, verweis.spalte));
    }
    return liste;
}

// Lesbarer Text für einen Datensatz: die ersten zwei Textfelder, z. B. "Max Mustermann"
function lesbar(zeile, schluessel) {
    const texte = Object.entries(zeile)
        .filter(([name, wert]) => name !== schluessel && typeof wert === 'string')
        .slice(0, 2)
        .map(([, wert]) => wert);
    return texte.length ? texte.join(' ') : String(zeile[schluessel]);
}

// Beschriftung für eine Spalte (oder Tabelle):
// Primärschlüssel → "ID", dann BESCHRIFTUNGEN, bei Verweisen der Name der anderen Tabelle,
// sonst aus dem Namen gebildet: "Erst_Zulassung" → "Erst Zulassung", "FahrzeugTyp" → "Fahrzeug Typ"
function beschriftungFuer(spalte) {
    const feld = felder[spalte];
    if (feld?.pk) return 'ID';
    if (BESCHRIFTUNGEN[spalte]) return BESCHRIFTUNGEN[spalte];
    if (feld?.verweis) return beschriftungFuer(feld.verweis.ressource);
    const text = spalte.replace(/_/g, ' ').replace(/([a-zäöüß])([A-ZÄÖÜ])/g, '$1 $2');
    return text.charAt(0).toUpperCase() + text.slice(1);
}


// ── 5) Formular aus den Spalten bauen ──────────────────────────
function formularBauen() {
    const bereich = el('felder');
    bereich.replaceChildren();

    for (const [name, feld] of Object.entries(felder)) {
        const eingabe = eingabeFuer(name, feld);
        eingabe.id   = 'feld-' + name;
        eingabe.name = name;

        const label = document.createElement('label');
        label.htmlFor     = eingabe.id;
        label.textContent = beschriftungFuer(name) + (feld.pflicht ? ' *' : '');

        const zeile = document.createElement('div');
        zeile.className = 'feld';
        zeile.append(label, eingabe);
        bereich.append(zeile);
    }
    geaendert.clear();
}

// Passendes Eingabeelement zum Spaltentyp der Datenbank
function eingabeFuer(name, feld) {
    const typ = feld.typ.toLowerCase();

    // Auswahlliste: Fremdschlüssel, enum('auto','motorrad') oder Ja/Nein bei tinyint(1)
    let optionen = null;
    if (auswahlen[name]) {
        optionen = [...auswahlen[name]];
    } else if (typ.startsWith('enum(')) {
        optionen = [...feld.typ.matchAll(/'((?:[^']|'')*)'/g)]
            .map(treffer => treffer[1].replaceAll("''", "'"))
            .map(wert => [wert, wert]);
    } else if (typ.startsWith('tinyint(1)')) {
        optionen = [['1', 'Ja'], ['0', 'Nein']];
    }
    if (optionen) {
        const auswahl = document.createElement('select');
        auswahl.add(new Option('-- bitte wählen --', ''));
        for (const [wert, text] of optionen) auswahl.add(new Option(text, wert));
        return auswahl;
    }

    const eingabe = document.createElement('input');
    eingabe.type = 'text';
    if (istGanzzahl(typ)) {
        eingabe.type = 'number';
        eingabe.step = '1';
        if (typ.includes('unsigned')) eingabe.min = '0';
    } else if (istZahl(typ)) {
        eingabe.type = 'number';
        eingabe.step = 'any';
    } else if (typ === 'date') {
        eingabe.type = 'date';
    } else if (/^(datetime|timestamp)/.test(typ)) {
        eingabe.type = 'datetime-local';
        eingabe.step = '1';
    }
    const laenge = typ.match(/char\((\d+)\)/);   // varchar(30) → höchstens 30 Zeichen
    if (laenge) eingabe.maxLength = Number(laenge[1]);
    return eingabe;
}

// Je nach Operation Felder ein-/ausblenden und Pflichtfelder setzen
function operationWechseln() {
    const operation = aktuelleOperation();
    el('hinweis').textContent = HINWEISE[operation];

    for (const [name, feld] of Object.entries(felder)) {
        const eingabe = el('feld-' + name);
        const istId   = name === pk;
        // Beim Anlegen keine automatische ID zeigen – die vergibt die Datenbank
        eingabe.closest('.feld').hidden = operation === 'POST' && feld.auto;
        // Pflicht: beim Anlegen die Pflichtfelder, beim Ändern und Löschen die ID
        eingabe.required = (operation === 'POST' && feld.pflicht)
                        || (istId && (operation === 'UPDATE' || operation === 'DELETE'));
        // Löschen braucht nur die ID
        eingabe.disabled = operation === 'DELETE' && !istId;
    }
}

// Werte aller aktiven Felder als { spalte: wert }. Zahlen werden als Zahl geschickt.
function formularWerte() {
    const werte = {};
    for (const [name, feld] of Object.entries(felder)) {
        const eingabe = el('feld-' + name);
        if (eingabe.disabled || eingabe.closest('.feld').hidden) continue;

        let wert = eingabe.value.trim();
        if (eingabe.type === 'datetime-local') wert = wert.replace('T', ' ');
        werte[name] = wert !== '' && istZahl(feld.typ.toLowerCase()) ? Number(wert) : wert;
    }
    return werte;
}

// Datensatz ins Formular übernehmen (nach GET/POST/UPDATE oder Klick auf eine Tabellenzeile)
function formularFuellen(zeile) {
    for (const [name, wert] of Object.entries(zeile ?? {})) {
        const eingabe = el('feld-' + name);
        if (!eingabe) continue;
        let text = wert === null ? '' : String(wert);
        if (eingabe.type === 'datetime-local') text = text.replace(' ', 'T');
        eingabe.value = text;
    }
    geaendert.clear();
}

function formularLeeren() {
    for (const name of Object.keys(felder)) el('feld-' + name).value = '';
    geaendert.clear();
}


// ── 6) Absenden: Operation → HTTP-Anfrage an die API ───────────
el('formular').addEventListener('submit', async (ereignis) => {
    ereignis.preventDefault();   // Seite nicht verlassen – wir schicken selbst per fetch()

    const operation = aktuelleOperation();
    const methode   = METHODEN[operation];
    const pfad      = '/' + el('ressource').value;                 // z. B. /fahrzeuge
    const werte     = formularWerte();
    const id        = pk && werte[pk] !== undefined && werte[pk] !== '' ? String(werte[pk]) : null;
    const pfadMitId = id === null ? pfad : `${pfad}/${encodeURIComponent(id)}`;   // z. B. /fahrzeuge/5

    let r;
    try {
        if (operation === 'GET') {
            // Mit ID → ein Datensatz. Ohne ID → Liste, ausgefüllte Felder sind Filter.
            const filter = new URLSearchParams(Object.entries(werte).filter(([, w]) => w !== '')).toString();
            r = await anfrage(methode, id !== null ? pfadMitId : pfad + (filter ? '?' + filter : ''));

        } else if (operation === 'POST') {
            // Leere Felder weglassen → die Datenbank nimmt ihren Standardwert
            const daten = Object.fromEntries(Object.entries(werte).filter(([, w]) => w !== ''));
            r = await anfrage(methode, pfad, daten);

        } else if (operation === 'UPDATE') {
            // Nur geänderte Felder schicken. Die ID steht in der URL.
            const daten = Object.fromEntries(Object.entries(werte).filter(([name]) => geaendert.has(name) && name !== pk));
            r = await anfrage(methode, pfadMitId, daten);

        } else {
            if (!confirm(`Datensatz "${id}" wirklich löschen?`)) return;
            r = await anfrage(methode, pfadMitId);
        }
    } catch (fehler) {
        return verbindungsfehler(fehler);
    }

    ergebnisZeigen(r);
    if (r.status === 401) abmelden();   // z. B. Konto inzwischen gelöscht
    if (!r.ok) return;

    // Formular auf den Stand der Datenbank bringen
    if (operation === 'DELETE') {
        formularLeeren();
    } else if (!Array.isArray(r.json?.daten)) {
        formularFuellen(r.json);   // ein einzelner Datensatz (GET mit ID, POST, UPDATE)
    }
});


// ── 7) Ergebnis anzeigen ───────────────────────────────────────
function ergebnisZeigen(r) {
    el('ergebnis').hidden = false;

    let meldung;
    if (!r.ok) {
        meldung = r.json?.fehler ?? 'Unerwartete Antwort vom Server.';
    } else if (Array.isArray(r.json?.daten)) {
        const { gesamt, daten } = r.json;
        meldung = `${gesamt} Treffer` + (gesamt > daten.length ? ` – die ersten ${daten.length} werden angezeigt` : '');
    } else {
        meldung = r.json?.meldung ?? MELDUNGEN[r.methode];
    }
    const status = el('status');
    status.className   = r.ok ? 'ok' : 'fehler';
    status.textContent = (r.status ? r.status + ' – ' : '') + meldung;

    // Fehlerdetails als Liste
    el('details').replaceChildren(...(r.json?.details ?? []).map(text => {
        const punkt = document.createElement('li');
        punkt.textContent = text;
        return punkt;
    }));

    // Datensätze als Tabelle: Liste (daten), gelöschter Datensatz (geloescht) oder ein einzelner
    let zeilen = [];
    if (r.ok && r.json) {
        zeilen = Array.isArray(r.json.daten) ? r.json.daten : [r.json.geloescht ?? r.json];
    }
    tabelleZeigen(zeilen);

    // Was genau gesendet und empfangen wurde – zum Nachvollziehen
    el('roh').textContent =
        `${r.methode} ${r.url}\n` +
        (r.daten ? JSON.stringify(r.daten, null, 2) + '\n' : '') +
        `\n← ${r.status}\n${r.text}`;
}

function tabelleZeigen(zeilen) {
    const bereich = el('tabelle');
    bereich.replaceChildren();
    if (zeilen.length === 0) return;

    const tabelle = document.createElement('table');
    const spalten = Object.keys(zeilen[0]);

    const kopf = tabelle.createTHead().insertRow();
    for (const spalte of spalten) {
        const th = document.createElement('th');
        th.textContent = beschriftungFuer(spalte);
        kopf.append(th);
    }

    const koerper = tabelle.createTBody();
    for (const zeile of zeilen) {
        const tr = koerper.insertRow();
        tr.title = 'Anklicken, um den Datensatz ins Formular zu übernehmen';
        tr.addEventListener('click', () => formularFuellen(zeile));
        for (const spalte of spalten) {
            // textContent statt innerHTML: Daten aus der Datenbank werden nie als HTML ausgeführt
            tr.insertCell().textContent = anzeigeText(spalte, zeile[spalte]);
        }
    }
    bereich.append(tabelle);
}

// Bei Auswahllisten den sichtbaren Text zeigen, z. B. typId 1 → "Auto", aktiv 1 → "Ja"
function anzeigeText(spalte, wert) {
    if (wert === null) return '–';
    const eingabe = el('feld-' + spalte);
    if (eingabe?.tagName === 'SELECT') {
        const option = [...eingabe.options].find(o => o.value === String(wert));
        if (option) return option.text;
    }
    return String(wert);
}


// ── 8) Ereignisse verbinden und starten ────────────────────────
el('ressource').addEventListener('change', ressourceWechseln);
document.querySelectorAll('input[name=operation]')
        .forEach(knopf => knopf.addEventListener('change', operationWechseln));
el('felder').addEventListener('input',  e => geaendert.add(e.target.name));
el('felder').addEventListener('change', e => geaendert.add(e.target.name));
el('leeren').addEventListener('click', () => { formularLeeren(); el('ergebnis').hidden = true; });
el('anmeldung').addEventListener('submit', (ereignis) => {
    ereignis.preventDefault();
    apiSchluessel = el('schluessel').value.trim();
    schluesselMerken(apiSchluessel);
    start();
});
el('abmelden').addEventListener('click', () => { abmelden(); el('ergebnis').hidden = true; });

// Seite neu geladen und schon angemeldet? Dann direkt starten, sonst auf die Anmeldung warten.
apiSchluessel = schluesselLaden();
if (apiSchluessel) start();
</script>
</body>
</html>
