<?php
session_start();
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    header("Location: login.php");
    exit;
}

require_once __DIR__.'/inc/ldaps_config.php';

// CSRF-Token
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
}
$csrf = $_SESSION['csrf_token'];

// Funktion: alle OUs aus LDAP holen und hierarchisch vorbereiten
function getHierarchicalOUs($ldap_conn, $base_dn, $prefix = '', $level = 0, &$seen = []) {
    $ous = [];

    if (isset($seen[$base_dn])) {
        return []; // schon gesehen, überspringen
    }
    $seen[$base_dn] = true;

    $ous[] = ['dn' => $base_dn, 'name' => str_replace(['DC='], '', $base_dn), 'level' => $level];

    $filter = "(objectClass=organizationalUnit)";
    $attributes = ["distinguishedName","ou"];
    $search = @ldap_search($ldap_conn, $base_dn, $filter, $attributes);

    if ($search) {
        $entries = ldap_get_entries($ldap_conn, $search);
        for ($i = 0; $i < $entries['count']; $i++) {
            $dn = $entries[$i]['distinguishedname'][0] ?? '';
            $name = $entries[$i]['ou'][0] ?? '';
            if ($dn && !isset($seen[$dn])) {
                $ous[] = ['dn' => $dn, 'name' => $name, 'level' => $level + 1];
                $ous = array_merge($ous, getHierarchicalOUs($ldap_conn, $dn, $prefix . '-- ', $level + 1, $seen));
            }
        }
    }

    return $ous;
}


$all_ous = [];
if ($ldap_conn && @ldap_bind($ldap_conn, $ldap_user, $ldap_pass)) {
    $all_ous_raw = getHierarchicalOUs($ldap_conn, $base_dn);

    // Duplikate anhand des DN entfernen
    $seen_dns = [];
    foreach ($all_ous_raw as $ou) {
        if (!in_array($ou['dn'], $seen_dns)) {
            $all_ous[] = $ou;
            $seen_dns[] = $ou['dn'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="utf-8">
<title>AD Benutzerverwaltung</title>
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
<style>
body {
    font-family: 'Segoe UI', Arial, sans-serif;
    background: linear-gradient(135deg, #f0f2f5, #d9e2ec);
    padding: 1rem;
    color: #2d3748;
}

h1 {
    margin-bottom: 1.5rem;
    font-size: 2rem;
    color: #1f2937;
    text-shadow: 0 1px 2px rgba(0,0,0,0.1);
}

.topbar {
    display: flex;
    flex-wrap: wrap;
    gap: 1rem;
    margin-bottom: 1rem;
    align-items: center;
}

button.action {
    padding: 0.6rem 1rem;
    border-radius: 8px;
    border: none;
    font-weight: bold;
    cursor: pointer;
    box-shadow: 0 3px 8px rgba(0,0,0,0.1);
    transition: transform 0.15s, box-shadow 0.15s, background 0.2s;
}

button.action:hover {
    transform: translateY(-1px);
    box-shadow: 0 5px 15px rgba(0,0,0,0.2);
}

button.primary {
    background-color: #0078d4;
    color: #fff;
}

button.primary:hover {
    background-color: #005a9e;
}

button.danger {
    background-color: #d9534f;
    color: #fff;
}

button.danger:hover {
    background-color: #b12e2b;
}

#target-ou {
    padding: 0.5rem;
    border-radius: 6px;
    border: 1px solid #ccc;
    min-width: 250px;
    background: #fff;
    transition: border 0.2s;
}

#target-ou:focus {
    border-color: #0078d4;
    outline: none;
}

#target-ou option.level-0 { font-weight:bold; color:#1f2937; }
#target-ou option.level-1 { padding-left:1rem; color:#2563eb; }
#target-ou option.level-2 { padding-left:2rem; color:#16a34a; }
#target-ou option.level-3 { padding-left:3rem; color:#d97706; }
#target-ou option.level-4 { padding-left:4rem; color:#4b5563; }

table.dataTable {
    border-collapse: separate !important;
    border-spacing: 0 0.5rem;
    background: #fff;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 4px 20px rgba(0,0,0,0.05);
}

table.dataTable thead th {
    background: #0078d4;
    color: #fff;
    font-weight: bold;
    border-bottom: none;
    padding: 0.8rem;
    text-align: left;
}

table.dataTable tbody tr {
    background: #f9fafb;
    border-radius: 6px;
}

table.dataTable tbody tr:hover {
    background: #e0f2ff;
}

.row-select {
    width: 18px;
    height: 18px;
}

.status-active {
    color: #16a34a;
    font-weight: bold;
}

.status-disabled {
    color: #d9534f;
    font-weight: bold;
}

.dataTables_wrapper .dataTables_paginate .paginate_button {
    background: #0078d4;
    color: #fff !important;
    border-radius: 6px;
    margin: 0 2px;
    padding: 0.3rem 0.6rem;
}

.dataTables_wrapper .dataTables_paginate .paginate_button.current {
    background: #005a9e;
}

.msg {
    margin-top: 1rem;
    padding: 0.8rem 1rem;
    border-radius: 8px;
    font-size: 0.95rem;
    font-weight: bold;
    color: #fff;
    animation: fadeIn 0.3s ease-in-out;
}

.msg.success { background-color: #16a34a; }
.msg.error { background-color: #d9534f; }

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(-5px); }
    to { opacity: 1; transform: translateY(0); }
}
/* Aktionen in der Tabelle */
.actions a {
    display: inline-block;
    margin-right: 0.4rem;
    padding: 0.35rem 0.6rem;
    border-radius: 6px;
    text-decoration: none;
    font-size: 0.85rem;
    font-weight: bold;
    color: #fff;
    transition: background 0.2s, transform 0.15s;
}

.actions a.edit {
    background-color: #2563eb;
}
.actions a.edit:hover {
    background-color: #1e40af;
    transform: translateY(-1px);
}

.actions a.password {
    background-color: #f59e0b;
}
.actions a.password:hover {
    background-color: #b45309;
    transform: translateY(-1px);
}

.actions a.status-toggle {
    background-color: #16a34a; /* aktiv */
}
.actions a.status-toggle.disabled {
    background-color: #d9534f; /* deaktiv */
}
.actions a.status-toggle:hover {
    opacity: 0.85;
}

.status-toggle {
    display: inline-block;
    padding: 0.3rem 0.6rem;
    border-radius: 6px;
    color: #fff;
    font-weight: bold;
    text-decoration: none;
    cursor: pointer;
    transition: background 0.2s, transform 0.15s;
}

.status-toggle.active {
    background-color: #16a34a; /* grün */
}

.status-toggle.disabled {
    background-color: #d9534f; /* rot */
}

.status-toggle:hover {
    opacity: 0.85;
}



</style>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
</head>
<body>

<h1>AD Benutzerverwaltung</h1>

<div class="topbar">
    <button id="btn-refresh" class="action primary">🔄 Neu laden</button>
    <button id="btn-disable" class="action danger">Deaktivieren</button>
    <button id="btn-enable" class="action primary">Aktivieren</button>
    <button id="btn-delete" class="action danger">Löschen</button>
    <button id="btn-move" class="action primary">Verschieben</button>

    <select id="target-ou">
        <?php foreach ($all_ous as $ou): ?>
            <?php $prefix = str_repeat('→ ', $ou['level']); ?>
            <option value="<?= htmlspecialchars($ou['dn']) ?>" class="level-<?= $ou['level'] ?>">
                <?= htmlspecialchars($prefix . $ou['name']) ?>
            </option>
        <?php endforeach; ?>
    </select>

    <div style="margin-left:auto;">
        Zeilen / Seite:
        <select id="pageLength">
            <option>25</option>
            <option selected>50</option>
            <option>100</option>
        </select>
    </div>
</div>

<table id="users" style="width:100%">
    <thead>
        <tr>
            <th><input id="select-all" type="checkbox"></th>
            <th>CN</th>
            <th>Benutzername</th>
            <th>E-Mail</th>
            <th>Abteilung</th>
            <th>Status</th>
            <th>Aktionen</th>
        </tr>
    </thead>
</table>

<form id="action-form" method="post" action="ad_user_action.php" style="display:none">
    <input type="hidden" name="action" value="">
    <input type="hidden" name="users" value="">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
    <input type="hidden" name="target_ou" id="form-target-ou" value="">
</form>

<script>
$(document).ready(function(){
    var table = $('#users').DataTable({
        serverSide:true,
        processing:true,
        ajax:{url:'ad_users_ajax.php', type:'POST'},
        pageLength:50,
        lengthChange:false,
        columns:[
            {data:'checkbox', orderable:false, searchable:false},
            {data:'cn'},
            {data:'samaccountname'},
            {data:'mail'},
            {data:'department'},
            {data:'status', orderable:false, searchable:false}, // Status Spalte enthält jetzt Toggle
            {data:'actions', orderable:false, searchable:false}
        ],
        order:[[1,'asc']],
        language:{url:"//cdn.datatables.net/plug-ins/1.13.6/i18n/de-DE.json"},
        drawCallback:function(){ $('#select-all').prop('checked', false); }
    });

    $('#select-all').on('click', function(){
        var rows = table.rows({page:'current'}).nodes();
        $('input.row-select', rows).prop('checked', this.checked);
    });

    $('#btn-refresh').click(function(){ table.ajax.reload(); });

    function collectSelected(){
        var sel=[];
        $('input.row-select:checked').each(function(){ sel.push($(this).val()); });
        return sel;
    }

    function submitAction(action){
        var sel = collectSelected();
        if(!sel.length){ alert('Mindestens einen Benutzer auswählen.'); return false; }
        $('#action-form input[name=action]').val(action);
        $('#action-form input[name=users]').val(JSON.stringify(sel));
        $('#action-form input[name=target_ou]').val($('#target-ou').val());
        $('#action-form').submit();
    }

    $('#btn-enable').click(function(){ if(confirm('Benutzer aktivieren?')) submitAction('enable'); });
    $('#btn-disable').click(function(){ if(confirm('Benutzer deaktivieren?')) submitAction('disable'); });
    $('#btn-delete').click(function(){ if(confirm('Löschen ist permanent. Fortfahren?')) submitAction('delete'); });
    $('#btn-move').click(function(){ 
        if(!$('#target-ou').val()){ alert('Bitte eine Ziel-OU wählen.'); return; }
        if(confirm('Benutzer in neue OU verschieben?')) submitAction('move'); 
    });

    $('#pageLength').on('change', function(){ table.page.len(parseInt(this.value)).draw(); });

    // Edit Button
    $('#users').on('click', '.btn-edit', function(e){
        e.preventDefault();
        window.location = 'ad_user_edit.php?user='+encodeURIComponent($(this).data('user'));
    });

    // Status Toggle direkt in Status-Spalte
$('#users').on('click', '.status-toggle', function(e){
    e.preventDefault();
    var $btn = $(this);
    var user = $btn.data('user');
    var action = $btn.hasClass('disabled') ? 'enable' : 'disable';
    if(!confirm('Benutzer ' + (action === 'enable' ? 'aktivieren' : 'deaktivieren') + '?')) return;

    $.post('ad_user_action.php', {
        action: action,
        users: JSON.stringify([user]),
        csrf_token: '<?= htmlspecialchars($csrf) ?>'
    }, function(resp){
        table.ajax.reload(null, false);
    });
});
});
</script>

</body>
</html>

</html>
