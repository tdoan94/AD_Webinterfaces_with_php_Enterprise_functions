<?php
declare(strict_types=1);

require_once __DIR__ . '/inc/ldap_groups_manage.php';
require_once __DIR__ . '/inc/ldaps_config.php';


session_start();
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    header("Location: login.php");
    exit;
}


header('Content-Type: application/json');

$action = $_POST['action'] ?? '';
$response = ['message' => 'Unbekannte Aktion'];

try {
    $ldap = connect_ldap();

    switch ($action) {
        case 'delete':
            $dn = $_POST['group_dn'] ?? '';
            if ($dn && deleteGroup($ldap, $dn)) {
                $response['message'] = "Gruppe erfolgreich gelöscht.";
            } else {
                $response['message'] = "Fehler beim Löschen.";
            }
            break;

        case 'rename':
            $dn = $_POST['group_dn'] ?? '';
            $newName = $_POST['new_name'] ?? '';
            if ($dn && $newName && renameGroup($ldap, $dn, $newName)) {
                $response['message'] = "Gruppe erfolgreich umbenannt.";
            } else {
                $response['message'] = "Fehler beim Umbenennen.";
            }
            break;

        case 'addMember':
            $dn = $_POST['group_dn'] ?? '';
            $user = $_POST['user'] ?? [];
            if (!is_array($user)) $user = [$user];
            if ($dn && !empty($user)) {
                $result = addMembersToGroup($ldap, $dn, $user);
                $addedUsers = !empty($result['added']) ? implode(", ", $result['added']) : 'Keine';
                $errors = !empty($result['errors']) ? implode(", ", $result['errors']) : '';
                $response['message'] = "Hinzugefügt: $addedUsers" . ($errors ? " | Fehler: $errors" : " | Erfolgreich");
            }
            break;

        case 'removeMember':
            $dn = $_POST['group_dn'] ?? '';
            $user = $_POST['user'] ?? [];
            if (!is_array($user)) $user = [$user];
            if ($dn && !empty($user)) {
                $result = removeMembersFromGroup($ldap, $dn, $user);
                $removedUsers = !empty($result['removed']) ? implode(", ", $result['removed']) : 'Keine';
                $errors = !empty($result['errors']) ? implode(", ", $result['errors']) : '';
                $response['message'] = "Entfernt: $removedUsers" . ($errors ? " | Fehler: $errors" : " | Erfolgreich");
            }
            break;
        
        case 'move':
            $dn = $_POST['group_dn'] ?? '';
            $target_ou = $_POST['target_ou'] ?? '';
            if ($dn && $target_ou && moveGroup($ldap, $dn, $target_ou)) {
                $response['message'] = "Gruppe erfolgreich verschoben.";
            } else {
                $response['message'] = "Fehler beim Verschieben.";
            }
            break;
        }

} catch (Exception $e) {
    $response['message'] = "Exception: " . $e->getMessage();
}

echo json_encode($response);
