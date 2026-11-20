-- Konten für die Anmeldung an der API (per API-Schlüssel)
-- Eigene Datenbank, getrennt von fahrzeugverwaltung. Der Name muss zu AUTH_DSN in api.php passen.
-- phpMyAdmin: oben links auf den Server (nicht auf eine Datenbank) → Reiter "SQL" → einfügen → OK
--
-- Gespeichert wird nur die SHA-256-Prüfsumme des Schlüssels, nie der Schlüssel selbst.
-- Wer die Tabelle sieht, kennt also keinen Schlüssel.

CREATE DATABASE IF NOT EXISTS benutzerverwaltung CHARACTER SET utf8mb4;
USE benutzerverwaltung;

CREATE TABLE IF NOT EXISTS benutzer (
    BID        INT AUTO_INCREMENT PRIMARY KEY,
    Name       VARCHAR(50) NOT NULL,
    Rolle      VARCHAR(20) NOT NULL DEFAULT 'nutzer',  -- muss in ROLLEN in api.php stehen: nutzer, admin
    Schluessel CHAR(64)    NOT NULL UNIQUE             -- SHA-256 des API-Schlüssels
);

-- Konten anlegen: Schlüssel selbst ausdenken (lang und zufällig, mindestens 20 Zeichen).
-- SHA2(…, 256) macht daraus die Prüfsumme. Den Schlüssel selbst bekommt nur die Person,
-- die das Konto benutzt – sie trägt ihn im Client bzw. in docs.html unter "Authorize" ein.
-- Zum Benutzen: die beiden Zeilen unten anpassen und die -- davor entfernen.
--
-- INSERT INTO benutzer (Name, Rolle, Schluessel) VALUES
-- ('Test Nutzer', 'nutzer', SHA2('hier-einen-langen-schluessel-ausdenken', 256)),
-- ('Test Admin',  'admin',  SHA2('hier-einen-anderen-schluessel-ausdenken', 256));
--
-- Schlüssel ändern:   UPDATE benutzer SET Schluessel = SHA2('neuer-schluessel', 256) WHERE BID = 1;
-- Konto sperren:      DELETE FROM benutzer WHERE BID = 1;
--
-- Optional, aber sauberer: eigener MySQL-Benutzer für die Anmeldung, der nur lesen darf.
-- Dann in api.php AUTH_USER/AUTH_PASS auf diesen Benutzer setzen.
--
-- CREATE USER 'api_anmeldung'@'localhost' IDENTIFIED BY 'ein-passwort';
-- GRANT SELECT ON benutzerverwaltung.benutzer TO 'api_anmeldung'@'localhost';
