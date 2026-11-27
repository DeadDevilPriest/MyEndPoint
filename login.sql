-- Login-Tabelle für die optionale Anmeldung (sicherheit.aktiv in config/gateway.php)
--
-- Wohin? Entweder in die Daten-Datenbank (Standard, LOGIN_DB_NAME in config/.env leer lassen)
-- oder in eine eigene Datenbank (LOGIN_DB_NAME usw. in config/.env setzen).
-- phpMyAdmin: die gewünschte Datenbank wählen → Reiter "SQL" → einfügen → OK
--
-- Tabellen- und Spaltennamen müssen zu sicherheit.login in config/gateway.php passen.
-- Die Login-Tabelle darf NIE unter 'ressourcen' stehen (das prüft die API beim Start).

CREATE TABLE IF NOT EXISTS benutzer (
    IID       INT AUTO_INCREMENT PRIMARY KEY,
    username  VARCHAR(50)  NOT NULL UNIQUE,
    useremail VARCHAR(255) NOT NULL UNIQUE,
    password  VARCHAR(255) NOT NULL                 -- Hash von password_hash(), NIE das Passwort selbst
);

-- Tabelle gibt es schon (z. B. mit Klartext-Passwörtern)? Dann stattdessen:
--
-- ALTER TABLE benutzer
--     MODIFY password VARCHAR(255) NOT NULL,       -- Hashes sind 60 Zeichen lang
--     ADD UNIQUE (useremail),
--     ADD UNIQUE (username);

-- Konto anlegen: zuerst den Hash erzeugen (Passwort wird nicht angezeigt):
--
--     php bin/passwort-hash.php
--
-- Die Ausgabe ($2y$10$…) kommt in die Spalte password:
--
-- INSERT INTO benutzer (username, useremail, password)
-- VALUES ('admin', 'admin@test.com', 'HIER-DEN-HASH-EINFUEGEN');
--
-- Passwort ändern:  UPDATE benutzer SET password = 'NEUER-HASH' WHERE IID = 1;
-- Konto sperren:    DELETE FROM benutzer WHERE IID = 1;   -- wirkt sofort, auch für ausgegebene Tokens
--
-- Eigene Login-Datenbank? Dann sauberer mit einem MySQL-Benutzer, der dort nur lesen darf,
-- und diesen in config/.env als LOGIN_DB_USER / LOGIN_DB_PASS eintragen:
--
-- CREATE USER 'api_login'@'localhost' IDENTIFIED BY 'ein-passwort';
-- GRANT SELECT ON login_datenbank.benutzer TO 'api_login'@'localhost';
