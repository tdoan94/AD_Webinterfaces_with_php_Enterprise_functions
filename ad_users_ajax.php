<?php
session_start();
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    header("Location: login.php");
    exit;
}

require_once __DIR__.'/inc/ldaps_config.php';
header('Content-Type: application/json; charset=utf-8');

define('UF_ACCOUNTDISABLE', 2);

// DataTables Parameter
$draw = intval($_POST['draw'] ?? 0);
$start = intval($_POST['start'] ?? 0);
$length = intval($_POST['length'] ?? 50);
$searchValue = trim($_POST['search']['value'] ?? '');

// Spalten-Mapping für Sortierung
$orderColIndex = intval($_POST['order'][0]['column'] ?? 1);
$orderDir = ($_POST['order'][0]['dir'] ?? 'asc') === 'desc' ? 'desc' : 'asc';
$columnsMap = ['checkbox','cn','samaccountname','mail','department','status','actions'];
$orderCol = $columnsMap[$orderColIndex] ?? 'cn';

// LDAP Filter
$filter = '(&(objectCategory=person)(objectClass=user))';
if ($searchValue !== '') {
    $s = ldap_escape($searchValue, '', LDAP_ESCAPE_FILTER);
    $filter = "(&{$filter}(|(cn=*{$s}*)(samaccountname=*{$s}*)(mail=*{$s}*)(department=*{$s}*)))";
}

// LDAP Attribute
$attributes = ['cn','samaccountname','mail','department','userAccountControl','distinguishedName'];

// LDAP Suche
$search = @ldap_search($ldap_conn, $base_dn, $filter, $attributes);
if (!$search) {
    echo json_encode([
        'draw' => $draw,
        'recordsTotal' => 0,
        'recordsFiltered' => 0,
        'data' => [],
        'error' => 'LDAP search failed'
    ]);
    exit;
}

$all_entries = ldap_get_entries($ldap_conn, $search);
$totalRecords = count($all_entries);

// Paging per PHP
$entries = array_slice($all_entries, $start, $length);

// Daten für DataTables vorbereiten
$data = [];
foreach ($entries as $e) {
    if (!isset($e['samaccountname'][0])) continue;

    $sam = $e['samaccountname'][0];
    $cn = $e['cn'][0] ?? '';
    $mail = $e['mail'][0] ?? '';
    $dept = $e['department'][0] ?? '';
    $uac = intval($e['useraccountcontrol'][0] ?? 0);

$statusClass = ($uac & UF_ACCOUNTDISABLE) ? 'disabled' : 'active';
$statusText  = ($uac & UF_ACCOUNTDISABLE) ? 'Deaktiviert' : 'Aktiv';

$data[] = [
    'checkbox' => '<input type="checkbox" class="row-select" value="'.htmlspecialchars($sam).'">',
    'cn' => htmlspecialchars($cn),
    'samaccountname' => htmlspecialchars($sam),
    'mail' => htmlspecialchars($mail),
    'department' => htmlspecialchars($dept),
    'status' => '<a href="#" class="status-toggle '.$statusClass.'" data-user="'.htmlspecialchars($sam).'">'.$statusText.'</a>',
    'actions' => '<div class="actions">'.
                    '<a href="#" class="edit btn-edit" data-user="'.htmlspecialchars($sam).'">✏️ Bearbeiten</a>'.
                    '<a href="ad_user_password.php?user='.urlencode($sam).'" class="password">🔑 Passwort</a>'.
                 '</div>'
];
}

// JSON-Ausgabe
echo json_encode([
    'draw' => $draw,
    'recordsTotal' => $totalRecords,
    'recordsFiltered' => $totalRecords,
    'data' => $data
], JSON_UNESCAPED_UNICODE);
?>
