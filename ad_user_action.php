<?php
session_start();
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    header("Location: login.php");
    exit;
}

require_once __DIR__.'/inc/ldaps_config.php';

// Fehleranzeige aktivieren
error_reporting(E_ALL);
ini_set('display_errors', 1);

// CSRF prüfen
$csrf = $_POST['csrf_token'] ?? '';
if(!hash_equals($_SESSION['csrf_token'] ?? '', $csrf)){
    die('Ungültiges CSRF-Token.');
}

// Aktion und Benutzerliste
$action = $_POST['action'] ?? '';
$users = json_decode($_POST['users'] ?? '[]', true);
$target_ou = $_POST['target_ou'] ?? '';

if(!is_array($users) || empty($users)){
    die("Keine Benutzer ausgewählt.");
}

define('UF_ACCOUNTDISABLE', 2);

// DN eines Benutzers finden
function ldap_find_dn($ldap, $base, $sAM){
    $filter = '(&(objectCategory=person)(objectClass=user)(samaccountname=' . ldap_escape($sAM, '', LDAP_ESCAPE_FILTER) . '))';
    $res = @ldap_search($ldap, $base, $filter, ['distinguishedName']);
    $entries = $res ? ldap_get_entries($ldap, $res) : [];
    return ($entries['count'] ?? 0) ? $entries[0]['distinguishedname'][0] : false;
}

$errors = [];

foreach($users as $u){
    $dn = ldap_find_dn($ldap_conn, $base_dn, $u);
    if(!$dn){
        $errors[] = "$u: DN nicht gefunden";
        continue;
    }

    switch($action){
        case 'disable':
            $entry = ['userAccountControl' => 514];
            if(!@ldap_mod_replace($ldap_conn, $dn, $entry)) $errors[] = "$u: konnte nicht deaktiviert werden";
            break;

        case 'enable':
            $entry = ['userAccountControl' => 512];
            if(!@ldap_mod_replace($ldap_conn, $dn, $entry)) $errors[] = "$u: konnte nicht aktiviert werden";
            break;

        case 'delete':
            if(!@ldap_delete($ldap_conn, $dn)) $errors[] = "$u: konnte nicht gelöscht werden";
            break;

        case 'move':
            if(!$target_ou){
                $errors[] = "$u: Keine Ziel-OU angegeben";
                continue 2;
            }
            // CN korrekt LDAP-escaped
            $new_rdn = "CN=" . ldap_escape($u, '', LDAP_ESCAPE_DN);
            if(!@ldap_rename($ldap_conn, $dn, $new_rdn, $target_ou, true)){
                $errors[] = "$u: konnte nicht verschoben werden";
            }
            break;

        default:
            $errors[] = "$u: unbekannte Aktion $action";
            break;
    }
}

$msg = 'Aktion ausgeführt.';
if($errors) $msg .= ' Fehler: ' . implode('; ', $errors);

header('Location: ad_browser.php?msg=' . urlencode($msg));
exit;
?>
