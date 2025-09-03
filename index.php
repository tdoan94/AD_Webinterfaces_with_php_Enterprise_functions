<?php


// Wenn schon eingeloggt, weiterleiten zum Dashboard
session_start();
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    header("Location: login.php");
    exit;
}
else
    header("Location: menu.php");
?>

<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Willkommen</title>
    <style>
        body { font-family: Arial; background: #eef1f5; display: flex; justify-content: center; align-items: center; height: 100vh; }
        .box { background: white; padding: 2rem; border-radius: 12px; box-shadow: 0 0 10px rgba(0,0,0,0.2); text-align: center; }
        .btn { margin-top: 1rem; padding: 0.6rem 1.2rem; background-color: #0078d4; color: white; text-decoration: none; border-radius: 8px; display: inline-block; }
        .btn:hover { background-color: #005a9e; }
    </style>
</head>
<body>
<div class="box">
    <h1>Willkommen im AD Webinterface</h1>
    <p>Bitte melden Sie sich an, um fortzufahren.</p>
    <a href="login.php" class="btn">Anmelden</a>
</div>
</body>
</html>
