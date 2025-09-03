<?php
session_start();
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    header("Location: login.php");
    exit;
}

// CSRF-Token erzeugen
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

require_once __DIR__. '/inc/ldaps_config.php';
// Benutzername aus GET prüfen
if (!isset($_GET['user'])) {
    die("Fehler: Kein Benutzer angegeben.");
}
$username = ldap_escape($_GET['user'], "", LDAP_ESCAPE_FILTER);

// Benutzer suchen
$search = ldap_search($ldap_conn, $base_dn, "(samaccountname=$username)");
$entries = ldap_get_entries($ldap_conn, $search);

if ($entries["count"] != 1) {
    die("Fehler: Benutzer nicht eindeutig gefunden.");
}

$user_dn = $entries[0]["dn"];
$cn = $entries[0]["cn"][0] ?? "";
$mail = $entries[0]["mail"][0] ?? "";
$dept = $entries[0]["department"][0] ?? "";
$old_sam = $entries[0]["samaccountname"][0] ?? "";
$given = $entries[0]["givenname"][0] ?? "";
$sn = $entries[0]["sn"][0] ?? "";

// POST-Request bearbeiten
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    // CSRF-Check
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("Ungültiger CSRF-Token!");
    }

    $new_cn = trim($_POST["cn"] ?? "");
    $new_mail = trim($_POST["mail"] ?? "");
    $new_dept = trim($_POST["department"] ?? "");
    $new_sam = trim($_POST["samaccountname"] ?? "");
    $new_given = trim($_POST["givenname"] ?? "");
    $new_sn = trim($_POST["sn"] ?? "");

    $ldap_mods = [];
    $änderung_vorhanden = false;

    // Attribute prüfen
    if (strcasecmp($mail, $new_mail) !== 0) { $ldap_mods["mail"] = $new_mail; $änderung_vorhanden = true; }
    if (strcasecmp($dept, $new_dept) !== 0) { $ldap_mods["department"] = $new_dept; $änderung_vorhanden = true; }
    if (strcasecmp($old_sam, $new_sam) !== 0) { $ldap_mods["samaccountname"] = $new_sam; $änderung_vorhanden = true; }
    if (strcasecmp($given, $new_given) !== 0) { $ldap_mods["givenName"] = $new_given; $änderung_vorhanden = true; }
    if (strcasecmp($sn, $new_sn) !== 0) { $ldap_mods["sn"] = $new_sn; $änderung_vorhanden = true; }

    // CN ändern → RDN ändern, nur wenn wirklich anders
    if (strcasecmp($cn, $new_cn) !== 0 && $new_cn !== "") {
        $new_rdn = "CN=" . ldap_escape($new_cn, "", LDAP_ESCAPE_DN);
        if (@ldap_rename($ldap_conn, $user_dn, $new_rdn, null, true)) {
            $user_dn = $new_rdn . "," . preg_replace('/^[^,]+,/', '', $user_dn);
            $cn = $new_cn;
            $änderung_vorhanden = true; // CN-Änderung berücksichtigen
        } else {
            $error = "Fehler beim Ändern des CN: " . ldap_error($ldap_conn);
        }
    }

    // Änderungen durchführen, wenn vorhanden
    if ($änderung_vorhanden) {
        if (!empty($ldap_mods)) {
            if (!@ldap_modify($ldap_conn, $user_dn, $ldap_mods)) {
                $error = "Fehler beim Aktualisieren: " . ldap_error($ldap_conn);
            }
        }

        // Logfile nur wenn etwas geändert wurde
        $logfile = __DIR__ . "/ad_changes.log";
        $log_entry = date("Y-m-d H:i:s") . " - Benutzer $username geändert: " . json_encode($ldap_mods) . " | CN: $cn\n";
        file_put_contents($logfile, $log_entry, FILE_APPEND | LOCK_EX);

        header("Location: ad_browser.php?msg=" . urlencode("Benutzer erfolgreich aktualisiert."));
        exit;
    } else {
        $error = "Keine Änderungen vorgenommen.";
    }
}

ldap_unbind($ldap_conn);
?>


<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Benutzer bearbeiten – <?= htmlspecialchars($username) ?></title>
    <style>
        body { font-family: Arial, sans-serif; background: #f4f6f8; padding: 2rem; }
        form { background: white; padding: 2rem; border-radius: 10px; box-shadow: 0 0 15px rgba(0,0,0,0.1); max-width: 600px; margin: auto; }
        label { display: block; margin-top: 1rem; font-weight: bold; }
        input[type="text"], input[type="email"] {
            width: 100%; padding: 0.8rem; border-radius: 6px; border: 1px solid #ccc; margin-top: 0.3rem;
        }
        button {
            margin-top: 1.5rem; background: #0078d4; color: white; border: none;
            padding: 0.8rem 1.5rem; font-size: 1rem; border-radius: 6px; cursor: pointer;
        }
        .back { margin-bottom: 1rem; display: inline-block; }
        .error { background: #f8d7da; color: #721c24; padding: 1rem; margin-top: 1rem; border: 1px solid #f5c6cb; border-radius: 6px; }
    </style>
</head>
<body>

<a href="ad_browser.php" class="back">← Zurück zur Benutzerliste</a>

<h2>Benutzer bearbeiten: <?= htmlspecialchars($username) ?></h2>

<?php if (!empty($error)): ?>
    <div class="error"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<form method="POST">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <label for="cn">Vollständiger Name (CN):</label>
    <input type="text" name="cn" id="cn" value="<?= htmlspecialchars($cn) ?>" />

    <label for="givenname">Vorname:</label>
    <input type="text" name="givenname" id="givenname" value="<?= htmlspecialchars($given) ?>" />

    <label for="sn">Nachname:</label>
    <input type="text" name="sn" id="sn" value="<?= htmlspecialchars($sn) ?>" />

    <label for="samaccountname">Anmeldename (sAMAccountName):</label>
    <input type="text" name="samaccountname" id="samaccountname" value="<?= htmlspecialchars($old_sam) ?>" />

    <label for="mail">E-Mail-Adresse:</label>
    <input type="email" name="mail" id="mail" value="<?= htmlspecialchars($mail) ?>" />

    <label for="department">Abteilung:</label>
    <input type="text" name="department" id="department" value="<?= htmlspecialchars($dept) ?>" />

    <button type="submit">Speichern</button>
</form>

</body>
</html>
