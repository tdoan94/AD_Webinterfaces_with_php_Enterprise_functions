<?php
declare(strict_types=1);
require_once __DIR__ . '/inc/ldap_groups_manage.php';
require_once __DIR__ . '/inc/ldaps_config.php';
session_start();
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) { header("Location: login.php"); exit; }
header('Content-Type: application/json');
try {
    $ldap = connect_ldap();
    $groups = getGroups($ldap, $base_dn);
    $data = [];
    foreach ($groups as $g) {
        $members_str = '';
        $data[] = [
            "cn" => $g['cn'],
            "members" => $members_str,
            "actions" =>
                '<button class="rename-group" data-dn="' . htmlspecialchars($g['dn'] ?? '') . '">Umbenennen</button>
                 <button class="delete-group" data-dn="' . htmlspecialchars($g['dn'] ?? '') . '">Löschen</button>
                 <button class="show-members" data-dn="' . htmlspecialchars($g['dn'] ?? '') . '" data-name="' . htmlspecialchars($g['cn'] ?? '') . '">Mitglieder anzeigen</button>
                 <button class="move-group" data-dn="' . htmlspecialchars($g['dn'] ?? '') . '">Verschieben</button>'
        ];
    }
    echo json_encode(["data" => $data]);
} catch (Exception $e) { echo json_encode(["error" => $e->getMessage()]); }
?>