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

$group_dn = $_GET['group_dn'] ?? '';

try {
    $ldap = connect_ldap();

    // Gruppenmitglieder
    $members = $group_dn ? getGroupMembers($ldap, $base_dn, $group_dn) : [];

    // Alle Benutzer
    $allUsersArr = getAllUsers($ldap, $base_dn);
    $allUsers = [];
    foreach ($allUsersArr as $sam => $u) {
        $allUsers[] = ['sAMAccountName' => $sam, 'cn' => $u['cn']];
    }

    echo json_encode([
        'members' => $members,
        'allUsers' => $allUsers
    ]);

} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
