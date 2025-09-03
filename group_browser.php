<?php
session_start();
$isLoggedIn = $_SESSION['user'];

if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    header("Location: login.php");
    exit;
}


require_once __DIR__ . '/inc/ldaps_config.php';

// Gruppenfilter
$filter = "(objectClass=group)";
$attributes = ["cn", "description", "member"];

$search = ldap_search($ldap_conn, $base_dn, $filter, $attributes);
if (!$search) {
    die("Fehler: LDAP-Suche fehlgeschlagen.");
}
$entries = ldap_get_entries($ldap_conn, $search);
?>

<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8" />
<title>Active Directory Gruppenliste</title>
<style>
    body { font-family: Arial, sans-serif; background: #f4f6f8; padding: 2rem; }
    table { border-collapse: collapse; width: 100%; background: white; border-radius: 8px; overflow: hidden; box-shadow: 0 0 15px rgba(0,0,0,0.1); }
    th, td { border: 1px solid #ddd; padding: 0.8rem 1rem; text-align: left; }
    th { background-color: #0078d4; color: white; }
    tr:nth-child(even) { background-color: #f9fbfd; }
    a.back-link { display: inline-block; margin-bottom: 1rem; color: #0078d4; text-decoration: none; font-weight: bold; }
    a.back-link:hover { text-decoration: underline; }
</style>
</head>
<body>

<h1>Active Directory Gruppenliste</h1>

<p>Gefundene Gruppen: <?= $entries["count"] ?></p>

<table>
    <thead>
        <tr>
            <th>Gruppenname (CN)</th>
            <th>Beschreibung</th>
            <th>Mitglieder (Anzahl)</th>
        </tr>
    </thead>
    <tbody>
    <?php for ($i = 0; $i < $entries["count"]; $i++): ?>
        <tr>
            <td><?= htmlspecialchars($entries[$i]["cn"][0] ?? '-') ?></td>
            <td><?= htmlspecialchars($entries[$i]["description"][0] ?? '-') ?></td>
            <td><?= isset($entries[$i]["member"]) ? $entries[$i]["member"]["count"] : 0 ?></td>
        </tr>
    <?php endfor; ?>
    </tbody>
</table>

</body>
</html>

<?php ldap_unbind($ldap_conn); ?>
