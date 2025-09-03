<?php
session_start();
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    header("Location: login.php");
    exit;
}
require_once __DIR__. '/inc/ldaps_config.php';


$message = "";
$errors = [];

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $usersToDelete = $_POST["users"] ?? [];

    if (empty($usersToDelete) || !is_array($usersToDelete)) {
        $message = "Keine Benutzer zum Löschen ausgewählt.";
    } else {
        $ldap_conn = ldap_connect($ldap_server);
        if (!$ldap_conn) {
            $message = "LDAP-Verbindung konnte nicht hergestellt werden.";
        } else {

            if (!@ldap_bind($ldap_conn, $ldap_user, $ldap_pass)) {
                $message = "LDAP Bind fehlgeschlagen: " . ldap_error($ldap_conn);
            } else {
                $deletedUsers = [];
                $failedUsers = [];

                foreach ($usersToDelete as $sAMAccountName) {
                    $sAMAccountName = trim($sAMAccountName);
                    if (!$sAMAccountName) continue;

                    $filter = "(sAMAccountName=" . ldap_escape($sAMAccountName, "", LDAP_ESCAPE_FILTER) . ")";
                    $search = ldap_search($ldap_conn, $base_dn, $filter);
                    $entries = ldap_get_entries($ldap_conn, $search);

                    if ($entries["count"] === 0) {
                        $failedUsers[] = $sAMAccountName . " (nicht gefunden)";
                        continue;
                    }

                    $userDN = $entries[0]["dn"];

                    if (@ldap_delete($ldap_conn, $userDN)) {
                        $deletedUsers[] = $sAMAccountName;
                    } else {
                        $failedUsers[] = $sAMAccountName . " (" . ldap_error($ldap_conn) . ")";
                    }
                }
                ldap_unbind($ldap_conn);

                // Ergebnis-Nachricht
                $message = "";
                if ($deletedUsers) {
                    $message .= "Benutzer erfolgreich gelöscht: " . implode(", ", $deletedUsers) . ". ";
                }
                if ($failedUsers) {
                    $message .= "Fehler bei folgenden Benutzern: " . implode(", ", $failedUsers) . ".";
                }

                // Nach erfolgreichem Löschen zurück zur Liste mit Message (URL-encode)
                header("Location: ad_browser.php?msg=" . urlencode($message));
                exit;
            }
        }
    }
} else {
    // Kein POST -> keine Aktion, zurück zur Liste
    header("Location: ad_browser.php");
    exit;
}
?>
