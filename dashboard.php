<?php
session_start();
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    header("Location: login.php");
    exit;
}
$user = $_SESSION['user'];
?>

<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Dashboard</title>
    <style>
        body { font-family: Arial; background: #ffffff; padding: 2rem; }
        .container { max-width: 600px; margin: auto; text-align: center; }
        .btn { display: inline-block; margin-top: 1rem; padding: 0.6rem 1.2rem; background-color: #e81123; color: white; text-decoration: none; border-radius: 8px; }
        .btn:hover { background-color: #c50f1f; }
    </style>
</head>
<body>
<div class="container">
    <h1>Willkommen, <?= htmlspecialchars($user) ?>!</h1>
    <p>Du bist erfolgreich über Active Directory eingeloggt.</p>
    <a href="logout.php" class="btn">Abmelden</a>
    <a href="menu.php" style="display:inline-block; margin-bottom:1rem; color:#0078d4; text-decoration:none;">
</a>
</div>
</body>
</html>
