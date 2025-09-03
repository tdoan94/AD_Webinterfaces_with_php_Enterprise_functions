# AD Webinterface (PHP 8 + LDAPS) — Users · Groups · OUs

**Kurz:** Web-Frontend zum Verwalten von **Active Directory** – mit Fokus auf **Delegation, Sicherheit und Performance**.  
Helpdesk/Leiter können typische AD-Aufgaben **ohne MMC** erledigen. (Siehe auch: `HOWTO_ldap_tool_v3.md` und `INSTALLATION_HOWTO_AD_Webinterface.md`.)

> **EN (short):** Web-based AD management (users/groups/OUs) with LDAPS, secure secret handling, and group-based access. See HOWTO/INSTALLATION guides for setup.

---

## 🚀 Features
- **Benutzer**: Anlegen, Bearbeiten, **Aktivieren/Deaktivieren**, Passwort setzen/resetten, **zwischen OUs verschieben**, Löschen
- **Gruppen**: Erstellen, Umbenennen, Löschen, Mitglieder **hinzufügen/entfernen**, **Gruppen zwischen OUs verschieben**
- **OUs**: OU-Baum auflisten, Erstellen, **Umbenennen**, **Verschieben**, Löschen
- **Login & Zugriff**: Nur Mitglieder **definierter AD-Gruppen** (konfigurierbar), optional **Nested Groups**-Check
- **Security**: **LDAPS**, CSRF-Schutz, `ldap_escape()` für Filter/DN, **verschlüsseltes** Service-Passwort (AES), **keine Secrets im Repo**
- **Performance**: Skalierung getestet bis **50.000 Benutzer** (AJAX/DataTables, Filter/Suche)
- **UX**: Modale Dialoge (ESC oder Klick außerhalb schließt), saubere Navigation, CSS in separaten Dateien

---

## 🔐 Sicherheits-Hinweis (keine Secrets im Repo)
> Dieses Repository enthält bewusst **keine Secrets** (z. B. `ldap_pass.enc`, `ldap_key.php`, Zertifikate/Keys).  
> Für Tests ist eine **eigene AD/LDAPS-Umgebung** erforderlich (BYO‑AD).  
> Siehe **HOWTO_ldap_tool_v3.md** und **INSTALLATION_HOWTO_AD_Webinterface.md**.

---

## ⚙️ Installation (Linux – kurz)
```bash
# Key erzeugen und in ENV ablegen
export LDAP_KEY=$(openssl rand -base64 32)
sudo install -m 0750 -d /etc/ad-webif
printf "LDAP_KEY=%s\nLDAP_HOST=ldaps://dc01.example.local:636\nLDAP_BASE_DN=DC=example,DC=local\nLDAP_BIND_DN=CN=svc_ldap,OU=Service,DC=example,DC=local\n" "$LDAP_KEY" | sudo tee /etc/ad-webif/env >/dev/null
sudo chmod 640 /etc/ad-webif/env

# Passwort verschlüsseln (siehe tools/encrypt.php)
php tools/encrypt.php "SuperGeheimesPW" | sudo tee /etc/ad-webif/ldap_pass.enc >/dev/null
sudo chmod 640 /etc/ad-webif/ldap_pass.enc

# Docker (empfohlen)
docker compose up -d
# → http://localhost:8080
```

**Windows/IIS & detaillierte Schritte:** siehe `INSTALLATION_HOWTO_AD_Webinterface.md`.

---

## 🔑 Zugriff nur für bestimmte Gruppen
- Standard in `login.php`: nur `CN=Administratoren,CN=Builtin,$base_dn` hat Zugriff.
- **Mehrere Gruppen erlauben**: Array `allowed_group_dns` nutzen (ODER-Logik).
- **Nested Groups**: optional via Matching-Rule `1.2.840.113556.1.4.1941` (Beispiel im HOWTO).

---

## 🖼️ Screenshots
Lege PNGs in `docs/images/` ab und binde sie hier ein, z. B.:
```md
![Login](docs/images/login.png)
![Benutzerliste](docs/images/users_list.png)
![OU-Baum](docs/images/ou_tree.png)
```

---

## 🧪 Demo-Modus (optional)
Ohne AD testen? Setze `DEMO_MODE=true` und liefere Demo-Daten über `docs/fixtures/users_demo.json`. Schreiboperationen sind im Demo-Modus zu deaktivieren.

---

## 🛡️ Qualität & Sicherheit (Highlights)
- Sessions & Rechteprüfung auf allen Admin-Seiten (kein Ausführen ohne Login/Adminrechte)
- CSRF-Token für kritische POST-Aktionen
- `ldap_escape()` für sichere Filter/DN-Verwendung
- Strikte TLS-Zertifikatsprüfung für Prod empfohlen
- Code-Refactor: **Includes/require_once**, **CSS ausgelagert**, **kürzer & wartbarer**

---

## 📦 Lizenz
MIT (Code). Screenshots/Docs optional anders lizenzieren (siehe README-Hinweis).
