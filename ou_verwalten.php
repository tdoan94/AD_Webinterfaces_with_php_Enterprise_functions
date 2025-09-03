<?php
session_start();

if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    header("Location: login.php");
    exit;
}

require_once __DIR__ . '/inc/ldaps_config.php';

function ldap_dn_escape($string) {
    return preg_replace_callback('/([,=+<>#;"\\\\])/u', fn($m) => '\\' . $m[1], $string);
}

// Rekursiv OUs auslesen
function get_ous_recursive($conn, $base_dn, $level = 0) {
    $ous = [];
    $filter = "(objectClass=organizationalUnit)";
    $attrs = ["ou", "distinguishedName"];
    $search = ldap_list($conn, $base_dn, $filter, $attrs);
    if ($search) {
        $entries = ldap_get_entries($conn, $search);
        for ($i = 0; $i < $entries["count"]; $i++) {
            $dn = $entries[$i]["distinguishedname"][0] ?? '';
            $ouName = $entries[$i]["ou"][0] ?? '';
            if ($dn && $ouName) {
                $ous[] = ['dn'=>$dn, 'ou'=>str_repeat('— ', $level).$ouName, 'level'=>$level];
                $sub = get_ous_recursive($conn, $dn, $level+1);
                $ous = array_merge($ous, $sub);
            }
        }
    }
    return $ous;
}

// Rekursives Löschen
function delete_ou_recursive($conn, $dn) {
    $filter = "(objectClass=organizationalUnit)";
    $search = ldap_list($conn, $dn, $filter, ["distinguishedName"]);
    if ($search) {
        $entries = ldap_get_entries($conn, $search);
        for ($i = 0; $i < $entries["count"]; $i++) {
            $sub_dn = $entries[$i]["distinguishedname"][0] ?? null;
            if ($sub_dn) delete_ou_recursive($conn, $sub_dn);
        }
    }
    $search2 = ldap_list($conn, $dn, "(objectClass=person)", ["distinguishedName"]);
    if ($search2) {
        $entries2 = ldap_get_entries($conn, $search2);
        for ($i = 0; $i < $entries2["count"]; $i++) {
            $user_dn = $entries2[$i]["distinguishedname"][0] ?? null;
            if ($user_dn) @ldap_delete($conn, $user_dn);
        }
    }
    return @ldap_delete($conn, $dn);
}

$message = '';
$messageClass = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $target_dn = $_POST['dn'] ?? '';

    if ($action === 'rename') {
        $new_name = trim($_POST['new_name'] ?? '');
        if ($target_dn && $new_name) {
            $parent_dn = preg_replace('/^ou=[^,]+,(.*)$/i', '$1', $target_dn);
            if (@ldap_rename($ldap_conn, $target_dn, "ou=".ldap_dn_escape($new_name), $parent_dn, true)) {
                $message = "OU erfolgreich umbenannt zu '$new_name'.";
                $messageClass = 'success';
            } else {
                $message = "Fehler beim Umbenennen: ".ldap_error($ldap_conn);
                $messageClass = 'error';
            }
        }
    } elseif ($action === 'move') {
        $new_parent = $_POST['new_parent'] ?? '';
        if ($target_dn && $new_parent) {
            $ou_name = preg_replace('/^ou=([^,]+),.*$/i','$1',$target_dn);
            if (@ldap_rename($ldap_conn, $target_dn, "ou=$ou_name", $new_parent, true)) {
                $message = "OU erfolgreich verschoben.";
                $messageClass = 'success';
            } else {
                $message = "Fehler beim Verschieben: ".ldap_error($ldap_conn);
                $messageClass = 'error';
            }
        }
    } elseif ($action === 'delete') {
        if ($target_dn && delete_ou_recursive($ldap_conn, $target_dn)) {
            $message = "OU rekursiv gelöscht.";
            $messageClass = 'success';
        } else {
            $message = "Fehler beim Löschen: ".ldap_error($ldap_conn);
            $messageClass = 'error';
        }
    }
}

$ous = get_ous_recursive($ldap_conn, $base_dn);
ldap_unbind($ldap_conn);
?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>LDAP OU Verwaltung</title>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<style>
body { font-family: "Segoe UI", Roboto, sans-serif; background: #f0f4f8; margin:0; padding:20px; }
.container { max-width: 1100px; margin:auto; background:#fff; padding:2rem; border-radius:12px; box-shadow:0 5px 20px rgba(0,0,0,0.1); }
h1 { text-align:center; margin-bottom:2rem; font-size:1.8rem; color:#2d3748; }
table { width:100%; border-collapse:collapse; margin-top:1rem; }
th, td { border-bottom:1px solid #e2e8f0; padding:0.8rem; text-align:left; }
th { background:#edf2f7; font-size:0.9rem; text-transform:uppercase; letter-spacing:0.05em; }
tr:hover { background:#f9fafb; }
.actions button { margin-right:6px; padding:6px 10px; border:none; border-radius:8px; cursor:pointer; font-weight:600; }
.rename { background:#3182ce; color:white; }
.move { background:#805ad5; color:white; }
.delete { background:#e53e3e; color:white; }
.actions button:hover { opacity:0.9; transform:translateY(-1px); transition:0.2s; }
.footer { text-align:center; margin-top:1.5rem; font-size:0.85rem; color:#718096; }
</style>
</head>
<body>
<div class="container">
  <h1>LDAP Organisationseinheiten verwalten</h1>

  <table>
    <thead>
      <tr>
        <th>OU Name</th>
        <th>DN</th>
        <th>Aktionen</th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($ous as $ou): ?>
      <tr>
        <td><?=htmlspecialchars($ou['ou'])?></td>
        <td><?=htmlspecialchars($ou['dn'])?></td>
        <td class="actions">
          <button class="rename" onclick="renameOu('<?=htmlspecialchars($ou['dn'])?>')">✏️ Umbenennen</button>
          <button class="move" onclick="moveOu('<?=htmlspecialchars($ou['dn'])?>')">📂 Verschieben</button>
          <button class="delete" onclick="deleteOu('<?=htmlspecialchars($ou['dn'])?>','<?=htmlspecialchars($ou['ou'])?>')">🗑️ Löschen</button>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>

  <div class="footer">&copy; 2025 AD Webinterface/OU_manage</div>
</div>

<form id="actionForm" method="post" style="display:none;">
  <input type="hidden" name="action" id="actionField">
  <input type="hidden" name="dn" id="dnField">
  <input type="hidden" name="new_name" id="newNameField">
  <input type="hidden" name="new_parent" id="newParentField">
</form>

<script>
const allOus = <?= json_encode(array_map(fn($o)=>['dn'=>$o['dn'],'ou'=>$o['ou']], $ous)) ?>;

function renameOu(dn) {
  Swal.fire({
    title: 'OU umbenennen',
    input: 'text',
    inputLabel: 'Neuer Name',
    showCancelButton: true,
    confirmButtonText: 'Speichern',
  }).then((res) => {
    if (res.isConfirmed && res.value) {
      document.getElementById('actionField').value = 'rename';
      document.getElementById('dnField').value = dn;
      document.getElementById('newNameField').value = res.value;
      document.getElementById('actionForm').submit();
    }
  });
}

function moveOu(dn) {
  let options = allOus
    .filter(o => o.dn !== dn) // sich selbst nicht als Ziel anbieten
    .map(o => `<option value="${o.dn}">${o.ou} (${o.dn})</option>`)
    .join('');

  Swal.fire({
    title: 'OU verschieben',
    html: `
      <label for="ouSelect">Neue Ziel-OU:</label>
      <select id="ouSelect" class="swal2-select" style="width:100%;padding:8px;">
        ${options}
      </select>`,
    showCancelButton: true,
    confirmButtonText: 'Verschieben',
    preConfirm: () => {
      return document.getElementById('ouSelect').value;
    }
  }).then((res) => {
    if (res.isConfirmed && res.value) {
      document.getElementById('actionField').value = 'move';
      document.getElementById('dnField').value = dn;
      document.getElementById('newParentField').value = res.value;
      document.getElementById('actionForm').submit();
    }
  });
}

function deleteOu(dn, name) {
  Swal.fire({
    title: 'Bist du sicher?',
    text: `Die OU "${name}" wird rekursiv gelöscht!`,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#e53e3e',
    confirmButtonText: 'Ja, löschen',
    cancelButtonText: 'Abbrechen'
  }).then((res) => {
    if (res.isConfirmed) {
      document.getElementById('actionField').value = 'delete';
      document.getElementById('dnField').value = dn;
      document.getElementById('actionForm').submit();
    }
  });
}

<?php if ($message): ?>
Swal.fire({
  toast: true,
  position: 'top-end',
  icon: '<?= $messageClass === "success" ? "success" : "error" ?>',
  title: '<?= htmlspecialchars($message) ?>',
  showConfirmButton: false,
  timer: 3000
});
<?php endif; ?>
</script>
</body>
</html>
