<?php
declare(strict_types=1);

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

session_start();
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    header("Location: login.php");
    exit;
}


require_once __DIR__ . '/inc/ldaps_config.php';
require_once __DIR__ . '/inc/ldap_user_create_functions.php';

$message = '';
$messageClass = '';

// === LDAP-Verbindung aufbauen und OUs abrufen ===
$ous = [];

if ($ldap_conn) {


    if (@ldap_bind($ldap_conn, $ldap_user, $ldap_pass)) {
        $ous = getOUsFromLDAP($ldap_conn, $base_dn);
    } else {
        $message = "LDAP Bind zum Auslesen der OUs fehlgeschlagen: " . ldap_error($ldap_conn);
        $messageClass = 'error';
    }
} else {
    $message = "LDAP-Verbindung konnte nicht hergestellt werden.";
    $messageClass = 'error';
}

// === Benutzer anlegen ===
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $givenName = trim($_POST['givenName'] ?? '');
    $sn = trim($_POST['sn'] ?? '');
    $sAMAccountName = trim($_POST['sAMAccountName'] ?? '');
    $userPrincipalName = trim($_POST['userPrincipalName'] ?? '');
    $password = $_POST['password'] ?? '';
    $selected_ou = $_POST['ou'] ?? '';

    if (!$givenName || !$sn || !$sAMAccountName || !$userPrincipalName || !$password || !$selected_ou) {
        $message = "Bitte alle Felder ausfüllen, inklusive OU-Auswahl.";
        $messageClass = 'error';
    } else {
        $result = createLDAPUser(
            $ldap_conn,
            $givenName,
            $sn,
            $sAMAccountName,
            $userPrincipalName,
            $password,
            $selected_ou,
            "CN=Inet_access,OU=Departments,DC=dwc,DC=de"
        );

        $message = $result['message'];
        $messageClass = $result['success'] ? 'success' : 'error';
    }
}

if (isset($ldap_conn)) ldap_unbind($ldap_conn);
?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8" />
<title>Neuen AD-Benutzer anlegen</title>
<link rel="stylesheet" href="css/ad_user_create.css">
<style>
.message.success { background-color:#c6f6d5;color:#276749;border:2px solid #2f855a;padding:1rem;border-radius:8px;margin-bottom:1rem;font-weight:600; }
.message.error { background-color:#fed7d7;color:#9b2c2c;border:2px solid #c53030;padding:1rem;border-radius:8px;margin-bottom:1rem;font-weight:600; }
.message .user-name { font-weight:800; }
.message .ou-name { font-style:italic;color:#22543d; }
</style>
</head>
<body>
<form method="POST">
<h1>Neuen AD-Benutzer anlegen</h1>

<?php if ($message): ?>
<div class="message <?= $messageClass ?>"><?= $message ?></div>
<?php endif; ?>

<label for="givenName">Vorname</label>
<input type="text" id="givenName" name="givenName" required value="<?= htmlspecialchars($_POST['givenName'] ?? '') ?>" />

<label for="sn">Nachname</label>
<input type="text" id="sn" name="sn" required value="<?= htmlspecialchars($_POST['sn'] ?? '') ?>" />

<label for="sAMAccountName">Benutzername (sAMAccountName)</label>
<input type="text" id="sAMAccountName" name="sAMAccountName" required value="<?= htmlspecialchars($_POST['sAMAccountName'] ?? '') ?>" />

<label for="userPrincipalName">User Principal Name (E-Mail)</label>
<input type="text" id="userPrincipalName" name="userPrincipalName" required value="<?= htmlspecialchars($_POST['userPrincipalName'] ?? '') ?>" />

<label for="password">Passwort</label>
<input type="password" id="password" name="password" required />

<label for="ou">Organizational Unit (OU)</label>
<select id="ou" name="ou" required>
    <option value="">Bitte OU wählen</option>
    <?php foreach ($ous as $dn => $ouName): ?>
        <option value="<?= htmlspecialchars($dn) ?>" <?= (($_POST['ou'] ?? '') === $dn ? 'selected' : '') ?>>
            <?= htmlspecialchars($ouName) ?>
        </option>
    <?php endforeach; ?>
</select>

<button type="submit">Benutzer anlegen</button>
</form>
</body>
</html>
