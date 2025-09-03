<?php
declare(strict_types=1);
session_start();
if (isset($_SESSION['is_admin']) || $_SESSION['is_admin'] == true) {
    header("Location: menu.php");
    exit;
}


// Fehlermeldung-Variable
$error = "";

// LDAPS-Konfiguration laden
require_once __DIR__ . '/inc/ldaps_config.php'; 
// $ldap_conn, $base_dn, $ldap_server, $cert_file stehen bereit

$required_admin_group_dn = "CN=Administratoren,CN=Builtin,$base_dn"; // Admin-Gruppe

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    if ($username && $password) {
        $user_conn = ldap_connect($ldap_server);
        ldap_set_option($user_conn, LDAP_OPT_PROTOCOL_VERSION, 3);
        ldap_set_option($user_conn, LDAP_OPT_REFERRALS, 0);
        ldap_set_option($user_conn, LDAP_OPT_X_TLS_CACERTFILE, $cert_file);
        ldap_set_option($user_conn, LDAP_OPT_X_TLS_REQUIRE_CERT, LDAP_OPT_X_TLS_NEVER);

        $user_dn = "$username@dwc.de";

        if (@ldap_bind($user_conn, $user_dn, $password)) {
            $filter = "(sAMAccountName=$username)";
            $attributes = ['memberOf'];
            $search = ldap_search($user_conn, $base_dn, $filter, $attributes);
            $entries = ldap_get_entries($user_conn, $search);

            if ($entries['count'] > 0) {
                $memberOf = $entries[0]['memberof'] ?? [];
                $isAdmin = false;

                
                if (is_array($memberOf) && isset($memberOf['count'])) {
                  for ($i = 0; $i < $memberOf['count']; $i++) {
                    if (strcasecmp($memberOf[$i], $required_admin_group_dn) === 0) {
                        $isAdmin = true;
                        break;
                    }
                }
                }

                if ($isAdmin) {
                    session_regenerate_id(true);
                    $_SESSION['user'] = $username;
                    $_SESSION['is_admin'] = true;

                    ldap_unbind($user_conn);
                    header("Location: menu.php");
                    exit;
                } else {
                    $error = "Sie sind nicht berechtigt, sich anzumelden.";
                }
            } else {
                $error = "Benutzer nicht gefunden.";
            }

            ldap_unbind($user_conn);

        } else {
            $error = "Benutzername oder Passwort falsch.";
        }

    } else {
        $error = "Bitte Benutzername und Passwort eingeben.";
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<title>Login</title>
<style>
body { font-family:"Segoe UI", Roboto, Arial, sans-serif; background: linear-gradient(135deg,#0078d4,#00b4d8); display:flex; justify-content:center; align-items:center; height:100vh; margin:0; }
.box { background:#fff; padding:2.5rem; border-radius:20px; box-shadow:0 10px 30px rgba(0,0,0,0.15); width:350px; animation: fadeIn 0.6s ease; }
h2 { text-align:center; margin-bottom:1.5rem; color:#333; }
input { width:100%; padding:0.8rem; margin:0.5rem 0; border-radius:10px; border:1px solid #ddd; font-size:1rem; transition: all 0.2s ease; }
input:focus { border-color:#0078d4; outline:none; box-shadow:0 0 5px rgba(0,120,212,0.4); }
button { width:100%; padding:0.9rem; margin-top:1rem; border-radius:10px; border:none; background: linear-gradient(135deg,#0078d4,#005a9e); color:white; font-size:1rem; font-weight:bold; cursor:pointer; transition: transform 0.15s ease, box-shadow 0.15s ease; }
button:hover { transform:translateY(-2px); box-shadow:0 5px 15px rgba(0,0,0,0.2); }
.error { color:#d62828; margin-bottom:1rem; text-align:center; font-weight:bold; }
.link { margin-top:1.5rem; text-align:center; }
.link a { color:#0078d4; text-decoration:none; font-size:0.9rem; transition:color 0.2s ease; }
.link a:hover { color:#005a9e; text-decoration:underline; }
@keyframes fadeIn { from { opacity:0; transform:translateY(10px); } to { opacity:1; transform:translateY(0); } }
</style>
</head>
<body>
<div class="box">
<h2>AD Login</h2>
<?php if (!empty($error)): ?>
  <div class="error"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>
<form method="post">
  <input type="text" name="username" placeholder="Benutzername" required />
  <input type="password" name="password" placeholder="Passwort" required />
  <button type="submit">Anmelden</button>
</form>
<div class="link">
  <a href="index.php">Zurück zur Startseite</a>
</div>
</div>
</body>
</html>
