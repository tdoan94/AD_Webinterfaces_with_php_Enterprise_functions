<?php
declare(strict_types=1);

// Passwort zum Verschlüsseln
$plain_password = "Bg2,14Abc";

// Verschlüsselungsschlüssel (dieser wird gebraucht, um später zu entschlüsseln)
$encryption_key = "mein-geheimer-schluessel";

// Initialisierungsvektor (IV) für AES-256-CBC
$iv = random_bytes(16);

// Passwort verschlüsseln
$encrypted_pass = openssl_encrypt($plain_password, 'aes-256-cbc', $encryption_key, 0, $iv);

// IV + verschlüsseltes Passwort zusammen in Base64 speichern
file_put_contents('/var/www/html/ldap_pass.enc', base64_encode($iv . $encrypted_pass));

echo "Passwort verschlüsselt und gespeichert!\n";
