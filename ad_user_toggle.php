<?php
session_start();
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    header("Location: login.php");
    exit;
}


if (!isset($_GET['user'])) {
    die("Kein Benutzer angegeben.");
}

$username = $_GET['user'];

require_once __DIR__. '/inc/ldaps_config.php';

// Benutzer suchen
$filter = "(samaccountname=$username)";
$search = ldap_search($ldap_conn, $base_dn, $filter, ["userAccountControl"]);
$entries = ldap_get_entries($ldap_conn, $search);

if ($entries["count"] == 0) {
    ldap_unbind($ldap_conn);
    die("Benutzer nicht gefunden.");
}

$user_dn = $entries[0]["dn"];
$current_uac = $entries[0]["useraccountcontrol"][0];

// Prüfen ob aktiviert oder deaktiviert
define('UF_ACCOUNTDISABLE', 2);

if ($current_uac & UF_ACCOUNTDISABLE) {
    // Benutzer aktivieren
    $new_uac = $current_uac & ~UF_ACCOUNTDISABLE;
    $action = "aktiviert";
} else {
    // Benutzer deaktivieren
    $new_uac = $current_uac | UF_ACCOUNTDISABLE;
    $action = "deaktiviert";
}

// Ändern
$entry = ["userAccountControl" => $new_uac];
if (ldap_mod_replace($ldap_conn, $user_dn, $entry)) {
    $msg = "Benutzer '$username' wurde erfolgreich $action.";
} else {
    $msg = "Fehler beim Ändern des Benutzerstatus.";
}

ldap_unbind($ldap_conn);

// Zurück zur Benutzerliste
header("Location: ad_browser.php?msg=" . urlencode($msg));
exit;
