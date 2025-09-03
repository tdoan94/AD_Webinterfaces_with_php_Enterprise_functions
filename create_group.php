<?php
session_start();
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    header("Location: login.php");
    exit;
}

// --- LDAP Config laden ---
require_once __DIR__ . '/inc/ldaps_config.php';

// ======= OUs aus LDAP laden (rekursiv mit Einrückung) =======
function getOUs($ldap_conn, $dn, $level = 0) {
    $ous = [];
    $filter = '(objectClass=organizationalUnit)'; // nur echte OUs
    $attrs = ['ou','distinguishedName'];
    $search = ldap_list($ldap_conn, $dn, $filter, $attrs);
    if ($search) {
        $entries = ldap_get_entries($ldap_conn, $search);
        for ($i = 0; $i < $entries['count']; $i++) {
            $ou_dn = $entries[$i]['distinguishedname'][0] ?? '';
            $ou_name = $entries[$i]['ou'][0] ?? 'unbekannt';
            $ous[$ou_dn] = str_repeat('→ ', $level) . $ou_name;
            // Rekursion für Unter-OUs
            $ous = $ous + getOUs($ldap_conn, $ou_dn, $level + 1);
        }
    }
    return $ous;
}

// Root-OU
preg_match_all('/DC=([^,]+)/i', $base_dn, $matches);
$root_name = implode('.', $matches[1]);

$ou_list = [$base_dn => $root_name]; // Root zuerst
$ou_list = $ou_list + getOUs($ldap_conn, $base_dn);

// ======= POST Verarbeitung =======
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_group = trim($_POST['groupname'] ?? '');
    $target_dn = $_POST['ou_target'] ?? '';

    if ($new_group === "" || !isset($ou_list[$target_dn])) {
        $message = "❌ Ungültige Eingabe.";
    } else {
        $cn = addcslashes($new_group, '\\,#+<>;"=');
        $dn = "CN=$cn,$target_dn";

        $entry = [
            "cn"             => $new_group,
            "sAMAccountName" => $new_group,
            "objectClass"    => ["top", "group"],
            "groupType"      => 0x80000002,
        ];

        if (@ldap_add($ldap_conn, $dn, $entry)) {
            $message = "✅ Gruppe <strong>$new_group</strong> in <strong>{$ou_list[$target_dn]}</strong> erstellt.";
        } else {
            $message = "❌ Fehler beim Erstellen: " . ldap_error($ldap_conn);
        }
    }
}

// --- Unbind am Ende ---
ldap_unbind($ldap_conn);
?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<title>Gruppe erstellen (LDAP)</title>
<link rel="stylesheet" href="css/group_create.css">
</head>
<body>
<form method="post">
    <h3>Neue Gruppe anlegen</h3>
    <input type="text" name="groupname" placeholder="Gruppenname" required />
    <select name="ou_target" required>
        <option value="">-- Ziel-OU auswählen --</option>
        <?php foreach ($ou_list as $dn => $label): ?>
            <option value="<?= htmlspecialchars($dn) ?>"><?= htmlspecialchars($label) ?></option>
        <?php endforeach; ?>
    </select>
    <button type="submit">Erstellen</button>
    <?php if ($message): ?>
        <div class="msg"><?= $message ?></div>
    <?php endif; ?>
</form>
</body>
</html>
