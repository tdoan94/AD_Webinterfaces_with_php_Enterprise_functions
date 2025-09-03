<?php
session_start();
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    header("Location: login.php");
    exit;
}


if (!isset($_GET['user'])) {
    die("Fehler: Kein Benutzer angegeben.");
}
$username = $_GET['user'];

function generateRandomPassword($length = 12) {
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()-_=+?';
    $password = '';
    for ($i = 0; $i < $length; $i++) {
        $password .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $password;
}
require_once __DIR__. '/inc/ldaps_config.php'; 
// Benutzer-DN ermitteln
$search = ldap_search($ldap_conn, $base_dn, "(samaccountname=$username)");
$entries = ldap_get_entries($ldap_conn, $search);

if ($entries["count"] != 1) {
    die("Fehler: Benutzer nicht eindeutig gefunden.");
}
$user_dn = $entries[0]["dn"];

$error = "";
$success = "";
$newPassword = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $newPassword = $_POST['password'] ?? "";

    if (empty($newPassword)) {
        $error = "Bitte ein Passwort eingeben.";
    } else {
        $password_quoted = '"' . $newPassword . '"';
        $password_utf16 = mb_convert_encoding($password_quoted, 'UTF-16LE');

        $mods = [];
        $mods['unicodePwd'] = [$password_utf16];
        $mods['pwdLastSet'] = ['0']; // zwingt zur Passwortänderung

        if (@ldap_modify($ldap_conn, $user_dn, $mods)) {
            $success = "Passwort erfolgreich zurückgesetzt. Benutzer muss es beim nächsten Login ändern.";
        } else {
            $error = "Fehler beim Zurücksetzen: " . ldap_error($ldap_conn);
        }
    }
} else {
    $newPassword = generateRandomPassword(12);
}

ldap_unbind($ldap_conn);
?>

<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8" />
<title>Passwort zurücksetzen – <?= htmlspecialchars($username) ?></title>
<style>
    body { font-family: Arial, sans-serif; background: #f4f6f8; padding: 2rem; }
    form { background: white; padding: 2rem; border-radius: 10px; max-width: 400px; margin: auto; box-shadow: 0 0 15px rgba(0,0,0,0.1); }
    label { display: block; margin-top: 1rem; font-weight: bold; }
    input[type="text"] {
        width: 100%; padding: 0.8rem; border-radius: 6px; border: 1px solid #ccc; margin-top: 0.3rem;
        font-family: monospace;
    }
    button {
        margin-top: 1.5rem; background: #0078d4; color: white; border: none;
        padding: 0.8rem 1.5rem; font-size: 1rem; border-radius: 6px; cursor: pointer;
    }
    .back { margin-bottom: 1rem; display: inline-block; }
    .error { background: #f8d7da; color: #721c24; padding: 1rem; margin-top: 1rem; border: 1px solid #f5c6cb; border-radius: 6px; }
    .success { background: #d4edda; color: #155724; padding: 1rem; margin-top: 1rem; border: 1px solid #c3e6cb; border-radius: 6px; }
</style>
</head>
<body>

<a href="ad_browser.php" class="back">← Zurück zur Benutzerliste</a>

<h2>Passwort zurücksetzen für: <?= htmlspecialchars($username) ?></h2>

<?php if ($error): ?>
    <div class="error"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>
<?php if ($success): ?>
    <div class="success"><?= htmlspecialchars($success) ?></div>
<?php endif; ?>

<form method="POST">
    <label for="password">Neues Passwort:</label>
    <input type="text" name="password" id="password" value="<?= htmlspecialchars($newPassword) ?>" required autocomplete="off" />

    <button type="submit">Passwort zurücksetzen</button>
</form>

</body>
</html>
