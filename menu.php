<?php
session_start();
$isLoggedIn = $_SESSION['user'];

if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    header("Location: login.php");
    exit;
}

?>

<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>AD Webinterface – Menü</title>
    <link rel="stylesheet" href="css/menu.css">
    
</head>
<body>
<div class="container">
    <h1>🌐 Active Directory Webinterface</h1>

    <div class="grid">
        <div class="card" data-url="group_browser.php">📇 Gruppen+beschreibung</div>
        <div class="card" data-url="ad_user_create.php">Neuen Benutzer anlegen</div>
        <div class="card" data-url="ad_user_delete.php">📇 Benutzer verwalten</div>
        <div class="card" data-url="create_group.php">Neue Gruppe erstellen</div>
        <div class="card" data-url="ad_groups.php">📇 Gruppen_verwaltung</div>
        <div class="card" data-url="create_ou.php">Organisationseinheit erstellen</div>
        <div class="card" data-url="ou_verwalten.php">OU verwalten</div>
        <?php if ($isLoggedIn): ?>
            <div class="card" data-url="dashboard.php">👤 Dashboard (<?= htmlspecialchars($_SESSION['user']) ?>)</div>
            <div class="card" data-url="logout.php">🚪 Logout</div>
        <?php else: ?>
            <div class="card" data-url="login.php">👤 Login erforderlich</div>
        <?php endif; ?>
    </div>

    <div class="footer">
        &copy; <?= date("Y") ?> Dein Unternehmen · AD Webtool v1.0
    </div>
</div>

<!-- Modal -->
<div class="modal" id="modal">
    <div class="modal-content">
        <span class="modal-close" id="modalClose">&times;</span>
        <iframe src="" id="modalIframe"></iframe>
    </div>
</div>

<script>
    const modal = document.getElementById('modal');
    const modalIframe = document.getElementById('modalIframe');
    const modalClose = document.getElementById('modalClose');

    // Öffne Modal mit URL
    document.querySelectorAll('.card').forEach(card => {
        card.addEventListener('click', () => {
            const url = card.getAttribute('data-url');
            
            // Wenn logout, dann direkt weiterleiten
            if (url == "logout.php") {
                window.location.href = url;
                return;
            }
            
            modalIframe.src = url;
            modal.style.display = 'flex';
        });
    });

    // Schließen durch Button
    modalClose.addEventListener('click', () => {
        modal.style.display = 'none';
        modalIframe.src = '';
    });

    // Schließen durch Klick außerhalb
    window.addEventListener('click', e => {
        if (e.target === modal) {
            modal.style.display = 'none';
            modalIframe.src = '';
        }
    });

    // Schließen durch ESC
    window.addEventListener('keydown', e => {
        if (e.key === 'Escape') {
            modal.style.display = 'none';
            modalIframe.src = '';
        }
    });
</script>
</body>
</html>
