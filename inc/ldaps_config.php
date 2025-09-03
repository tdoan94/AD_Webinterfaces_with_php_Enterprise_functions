<?php
declare(strict_types=1);

// Fehleranzeige wie vorher
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// --- LDAP Server-Konfiguration ---
$ldap_server = "ldaps://dwc-DC-CA:636";
$ldap_user   = "administrator@dwc.de";
$base_dn     = "DC=dwc,DC=de";
$cert_file   = __DIR__ . '/../dc-dwc.cer'; // optional

// --- Verschlüsseltes Passwort ---
$enc_file = __DIR__ . '/../ldap_pass.enc';
if (!file_exists($enc_file)) {
    die("Fehler: Verschlüsselte Passwortdatei nicht gefunden!\n");
}

$enc_data = base64_decode(file_get_contents($enc_file));
if ($enc_data === false || strlen($enc_data) < 17) {
    die("Fehler: Ungültige verschlüsselte Passwortdatei!\n");
}

$iv = substr($enc_data, 0, 16);
$encrypted_pass = substr($enc_data, 16);

$encryption_key = getenv('LDAP_KEY') ?: "mein-geheimer-schluessel";
if (empty($encryption_key)) {
    die("Fehler: Verschlüsselungsschlüssel nicht gesetzt!\n");
}

$ldap_pass = openssl_decrypt($encrypted_pass, 'aes-256-cbc', $encryption_key, 0, $iv);
if ($ldap_pass === false) {
    die("Fehler: Passwort konnte nicht entschlüsselt werden!\n");
}

// --- LDAP-Verbindung aufbauen ---
$ldap_conn = ldap_connect($ldap_server);
if (!$ldap_conn) {
    die("Fehler: Konnte LDAP-Server nicht erreichen!\n");
}

ldap_set_option($ldap_conn, LDAP_OPT_PROTOCOL_VERSION, 3);
ldap_set_option($ldap_conn, LDAP_OPT_REFERRALS, 0);
ldap_set_option($ldap_conn, LDAP_OPT_X_TLS_CACERTFILE, $cert_file);
ldap_set_option($ldap_conn, LDAP_OPT_X_TLS_REQUIRE_CERT, LDAP_OPT_X_TLS_NEVER);

if (!@ldap_bind($ldap_conn, $ldap_user, $ldap_pass)) {
    $errno = ldap_errno($ldap_conn);
    $errstr = ldap_error($ldap_conn);
    die("LDAP-Bind fehlgeschlagen! [$errno] $errstr\n");
}
