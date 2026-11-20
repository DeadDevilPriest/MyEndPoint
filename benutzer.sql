-- Konten für die Anmeldung an der API (E-Mail oder Benutzername + Passwort)
-- Eigene Datenbank, getrennt von fahrzeugverwaltung.
-- Datenbank- und Tabellenname müssen zu AUTH_DSN und TBL_BENUTZER in api.php passen.
-- phpMyAdmin: oben links auf den Server (nicht auf eine Datenbank) → Reiter "SQL" → einfügen → OK

CREATE DATABASE IF NOT EXISTS benutzerverwaltung CHARACTER SET utf8mb4;
USE benutzerverwaltung;

CREATE TABLE IF NOT EXISTS benutzer (
    IID       INT AUTO_INCREMENT PRIMARY KEY,
    username  VARCHAR(50)  NOT NULL UNIQUE,
    useremail VARCHAR(255) NOT NULL UNIQUE,
    password  VARCHAR(255) NOT NULL,                  -- Hash von password_hash(), NIE das Passwort selbst
    rolle     VARCHAR(20)  NOT NULL DEFAULT 'nutzer'  -- muss in ROLLEN in api.php stehen: nutzer, admin
);

-- Tabelle gibt es schon (z. B. mit Klartext-Passwörtern)? Dann stattdessen:
--
-- ALTER TABLE benutzer
--     MODIFY password VARCHAR(255) NOT NULL,   -- Hashes sind 60 Zeichen lang
--     ADD UNIQUE (useremail),                  -- Anmeldung per E-Mail → muss eindeutig sein
--     ADD UNIQUE (username);

-- Passwörter speichern: NUR als Hash. Den Hash erzeugt PHP, z. B. in der XAMPP-Shell:
--
--     php -r "echo password_hash('das-passwort', PASSWORD_DEFAULT);"
--
-- Die Ausgabe ($2y$10$…) kommt in die Spalte password:
--
-- INSERT INTO benutzer (username, useremail, password, rolle) VALUES
-- ('admin', 'admin@test.com', 'HIER-DEN-HASH-EINFUEGEN', 'admin');
--
-- UPDATE benutzer SET password = 'HIER-DEN-HASH-EINFUEGEN' WHERE IID = 1;
--
-- Warum nicht SHA2()? Das ist für Passwörter zu schnell zu knacken. password_hash()
-- ist absichtlich langsam und fügt jedem Passwort einen eigenen Zufallswert (Salt) hinzu.
--
-- Optional, aber sauberer: eigener MySQL-Benutzer für die Anmeldung, der nur lesen darf.
-- Dann in api.php AUTH_USER/AUTH_PASS auf diesen Benutzer setzen.
--
-- CREATE USER 'api_anmeldung'@'localhost' IDENTIFIED BY 'ein-passwort';
-- GRANT SELECT ON benutzerverwaltung.benutzer TO 'api_anmeldung'@'localhost';
