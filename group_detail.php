<?php
session_start();
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    header("Location: login.php");
    exit;
}



if (!isset($_GET['cn'])) {
    die("Keine Gruppe ausgewählt.");
}

$groupCN = $_GET['cn'];

// LDAP-Verbindungsdaten
$ldap_server = "ldap://192.168.120.1";
$ldap_user = "administrator@dwc.de";
$ldap_pass = "Bg2,14Abc";
$base_dn = "DC=dwc,DC=de";

$ldap_conn = ldap_connect($ldap_server);
if (!$ldap_conn) {
    die("Fehler: LDAP-Verbindung konnte nicht hergestellt werden.");
}
ldap_set_option($ldap_conn, LDAP_OPT_PROTOCOL_VERSION, 3);
ldap_set_option($ldap_conn, LDAP_OPT_REFERRALS, 0);

if (!@ldap_bind($ldap_conn, $ldap_user, $ldap_pass)) {
    die("Fehler: LDAP-Bind fehlgeschlagen. Zugangsdaten prüfen.");
}

// Gruppe suchen
$filter = "(&(objectClass=group)(cn=" . ldap_escape($groupCN, "", LDAP_ESCAPE_FILTER) . "))";
$attributes = ["cn", "description", "member"];

$search = ldap_search($ldap_conn, $base_dn, $filter, $attributes);
if (!$search) {
    die("Fehler: LDAP-Suche fehlgeschlagen.");
}
$entries = ldap_get_entries($ldap_conn, $search);

if ($entries["count"] == 0) {
    die("Gruppe nicht gefunden.");
}

$group = $entries[0];

// Mitglieder auslesen
$members = [];
if (isset($group["member"])) {
    for ($i = 0; $i < $group["member"]["count"]; $i++) {
        $members[] = $group["member"][$i];
    }
}

// Für Mitglieder jetzt noch CN aus LDAP holen (optional, schöner)
$memberDetails = [];

foreach ($members as $dn) {
    $searchMember = ldap_read($ldap_conn, $dn, "(objectClass=*)", ["cn", "samaccountname"]);
    if ($searchMember) {
        $entry = ldap_get_entries($ldap_conn, $searchMember);
        if ($entry["count"] > 0) {
            $memberDetails[] = [
                "cn" => $entry[0]["cn"][0] ?? $dn,
                "samaccountname" => $entry[0]["samaccountname"][0] ?? "-"
            ];
        }
    }
}

ldap_unbind($ldap_conn);
?>

<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8" />
    <title>Gruppendetails: <?= htmlspecialchars($group["cn"][0]) ?></title>
    <style>
        body { font-family: Arial, sans-serif; background: #f4f6f8; padding: 2rem; }
        a.back-link { display: inline-block; margin-bottom: 1rem; color: #0078d4; text-decoration: none; font-weight: bold; }
        a.back-link:hover { text-decoration: underline; }
        table { border-collapse: collapse; width: 100%; background: white; border-radius: 8px; overflow: hidden; box-shadow: 0 0 15px rgba(0,0,0,0.1); }
        th, td { border: 1px solid #ddd; padding: 0.8rem 1rem; text-align: left; }
        th { background-color: #0078d4; color: white; }
        tr:nth-child(even) { background-color: #f9fbfd; }
    </style>
</head>
<body>

<a href="group_browser.php" class="back-link">← Zurück zur Gruppenliste</a>

<h1>Gruppe: <?= htmlspecialchars($group["cn"][0]) ?></h1>
<p><?= htmlspecialchars($group["description"][0] ?? 'Keine Beschreibung') ?></p>

<h2>Mitglieder (<?= count($memberDetails) ?>)</h2>

<?php if (count($memberDetails) > 0): ?>
<table>
    <thead>
        <tr>
            <th>Name (CN)</th>
            <th>Benutzername (sAMAccountName)</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($memberDetails as $member): ?>
        <tr>
            <td><?= htmlspecialchars($member["cn"]) ?></td>
            <td><?= htmlspecialchars($member["samaccountname"]) ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<?php else: ?>
<p>Keine Mitglieder in dieser Gruppe gefunden.</p>
<?php endif; ?>

</body>
</html>
