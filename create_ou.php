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


$user = $_SESSION['user'];

// LDAP Config laden
require_once __DIR__ . '/inc/ldaps_config.php'; // bindet $ldap_conn, $base_dn, $cert_file usw.

// Nachricht Variablen
$message = '';
$messageClass = '';

// DN escape Funktion
function ldap_escape_custom(string $str): string {
    $metaChars = ['\\', ',', '+', '"', '<', '>', ';', '='];
    $escaped = '';
    for ($i = 0; $i < strlen($str); $i++) {
        $c = $str[$i];
        $escaped .= in_array($c, $metaChars) ? '\\' . $c : $c;
    }
    if (strlen($escaped) > 0) {
        if ($escaped[0] === ' ') $escaped = '\\ ' . substr($escaped, 1);
        if (substr($escaped, -1) === ' ') $escaped = substr($escaped, 0, -1) . '\\ ';
    }
    return $escaped;
}

// Rekursive OU-Abfrage
function getOUsRecursive($ldap, string $base_dn, int $level = 0): array {
    $ous = [];
    if ($level === 0) {
        $ous[] = ['dn' => $base_dn, 'ou' => "🌐 Root-Domain ($base_dn)", 'level' => 0];
    }

    $filter = '(objectClass=organizationalUnit)';
    $attrs = ['ou','distinguishedName'];
    $search = @ldap_list($ldap, $base_dn, $filter, $attrs);

    if ($search) {
        $entries = ldap_get_entries($ldap, $search);
        for ($i=0; $i<$entries['count']; $i++) {
            $dn = $entries[$i]['distinguishedname'][0] ?? null;
            $ouName = $entries[$i]['ou'][0] ?? '(ohne Name)';
            if ($dn) {
                $ous[] = [
                    'dn' => $dn,
                    'ou' => str_repeat('— ', $level+1) . $ouName,
                    'level' => $level+1
                ];
                $subOUs = getOUsRecursive($ldap, $dn, $level+1);
                $ous = array_merge($ous, $subOUs);
            }
        }
    }
    return $ous;
}

// OUs abrufen
$available_ous = getOUsRecursive($ldap_conn, $base_dn);

// OU erstellen
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ou_name = trim($_POST['ou_name'] ?? '');
    $parent_dn = trim($_POST['base_dn'] ?? '');

    if ($ou_name === '' || $parent_dn === '') {
        $message = 'Bitte alle Felder ausfüllen.';
        $messageClass = 'error';
    } else {
        $escaped_ou = ldap_escape_custom($ou_name);
        $new_dn = "OU=$escaped_ou,$parent_dn";
        $entry = ['objectClass'=>['top','organizationalUnit'],'ou'=>$ou_name];

        if (@ldap_add($ldap_conn, $new_dn, $entry)) {
            $message = "OU '$ou_name' erfolgreich erstellt!";
            $messageClass = 'success';
            $available_ous = getOUsRecursive($ldap_conn, $base_dn); // aktualisieren
        } else {
            $message = 'Fehler beim Anlegen der OU: ' . ldap_error($ldap_conn);
            $messageClass = 'error';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<title>Neue Organisationseinheit anlegen</title>
<style>
body { font-family: Arial, sans-serif; background: #f7fafc; margin:0; display:flex; justify-content:center; align-items:center; min-height:100vh; }
.container { background:#fff; padding:2.5rem 3rem; border-radius:12px; box-shadow:0 10px 30px rgba(0,0,0,0.1); max-width:480px; width:100%; }
h1 { font-weight:700; font-size:1.8rem; text-align:center; margin-bottom:2rem; }
label { display:block; font-weight:600; margin-bottom:0.4rem; }
input[type=text], select { width:100%; padding:0.6rem 1rem; margin-bottom:1.5rem; border:1.8px solid #cbd5e0; border-radius:8px; font-size:1rem; }
input[type=text]:focus, select:focus { border-color:#3182ce; outline:none; }
input[type=submit] { background-color:#3182ce; color:white; font-weight:700; border:none; border-radius:8px; padding:0.75rem 0; cursor:pointer; font-size:1.1rem; width:100%; }
input[type=submit]:hover { background-color:#2b6cb0; }
.message { padding:1rem 1.2rem; border-radius:8px; margin-bottom:1.5rem; font-weight:600; text-align:center; font-size:0.95rem; }
.success { background-color:#c6f6d5; color:#276749; border:1px solid #9ae6b4; }
.error { background-color:#fed7d7; color:#9b2c2c; border:1px solid #feb2b2; }
.back-link { display:block; text-align:center; margin-top:1rem; font-size:0.9rem; color:#3182ce; text-decoration:none; font-weight:600; }
.back-link:hover { text-decoration:underline; }
</style>
</head>
<body>
<main class="container">
<h1>Neue OU anlegen</h1>

<?php if ($message): ?>
<div class="message <?= htmlspecialchars($messageClass) ?>"><?= htmlspecialchars($message) ?></div>
<?php endif; ?>

<form method="post">
<label for="ou_name">Name der OU</label>
<input type="text" id="ou_name" name="ou_name" placeholder="z.B. Marketing" required value="<?= htmlspecialchars($_POST['ou_name'] ?? '') ?>">

<label for="base_dn">Übergeordnete OU</label>
<select id="base_dn" name="base_dn" required>
<?php foreach ($available_ous as $ou): ?>
    <option value="<?= htmlspecialchars($ou['dn']) ?>" <?= (($_POST['base_dn'] ?? '') === $ou['dn']) ? 'selected' : '' ?>>
        <?= htmlspecialchars($ou['ou']) ?>
    </option>
<?php endforeach; ?>
</select>

<input type="submit" value="OU erstellen">
</form>

</main>
</body>
</html>
