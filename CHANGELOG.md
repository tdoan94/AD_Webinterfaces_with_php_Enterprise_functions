# Changelog

## [2.1] - 2025-09-03
### Added
- **Benutzer deaktivieren**: Neue Aktion zum schnellen Deaktivieren von AD‑Benutzern.
- **UX**: Modaldialoge schließen per **ESC** oder Klick **außerhalb** des Fensters.

### Changed
- **Security**: Passwort wird **verschlüsselt** gespeichert – kein Klartext mehr.
- **Code-Struktur**: Kürzer & wartbarer durch `include`/`require_once`; **CSS** in **separate Dateien** ausgelagert.
- **Performance**: Abfragen für **bis zu 50.000 Benutzer** optimiert; Suchfilter ergänzt.
- **Sessions**: Harter Session‑Check, damit **keine PHP‑Skripte** ohne Adminrechte ausgeführt werden können.

### Security
- Secrets aus dem Repo entfernt; `.gitignore` ergänzt.
- Installationsanweisungen für **ENV‑Key** + verschlüsseltes Passwort dokumentiert.

## [2.0] - 2025-08-01
- Initiale „Enterprise“-Funktionen: Benutzer/Gruppen/OU-Verwaltung, LDAPS, CSRF, AJAX/DataTables.
