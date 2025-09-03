# SECURITY

**Scope:** Best Practices für dieses Projekt (Demo/Portfolio) und Empfehlung für Produktivbetrieb.

## Keine Secrets im Repo
- `ldap_pass.enc`, `ldap_key.php`, `.env`, Zertifikate/Keys (`*.cer/*.crt/*.pem/*.key/*.pfx`) sind **ausgeschlossen** (.gitignore).
- Secrets außerhalb des Webroots (Linux: `/etc/ad-webif`, Windows: `C:\ProgramData\ad-webif`), restriktive Rechte (640/600).

## Key & Cipher
- Entschlüsselungs‑Key via **Environment** `LDAP_KEY` (nicht im Code).  
- Passwort verschlüsselt in `ldap_pass.enc` (AES; für Prod: **AES‑GCM** empfohlen).

## LDAPS & Zertifikate
- LDAPv3, Referrals off.  
- **Zertifikatsvalidierung = REQUIRED/DEMAND** in Produktion, feste CA‑Kette hinterlegt.

## Zugriffskontrolle (Login)
- Nur Mitglieder **definierter AD‑Gruppen**; optional **Nested Groups** prüfen.  
- Session‑Regeneration nach Login, Zugriffsschutz auf allen Admin‑Seiten.

## Least Privilege
- Service‑Account nur mit minimalen Rechten auf die **benötigten OUs/Attribute** (Delegation).  
- Keine Domain‑Admin Rechte.

## Auditing & Monitoring (Empfehlung)
- Änderungs‑Audit (wer/was/wann) als Append‑only Log oder DB.  
- Secret Scanning (gitleaks/trufflehog), GitHub **Dependabot/Secret Scanning** aktivieren.

## Rotation
- Regelmäßige Rotation von Service‑Account‑Passwort **und** optional `LDAP_KEY`.  
- Nach Rotation `ldap_pass.enc` neu erzeugen und Dienst neu starten.
