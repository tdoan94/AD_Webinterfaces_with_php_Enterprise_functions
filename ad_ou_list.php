<?php
declare(strict_types=1);
require_once __DIR__ . '/inc/ldaps_config.php';

header('Content-Type: application/json');

// Root Name
preg_match_all('/DC=([^,]+)/i', $base_dn, $matches);
$root_name = implode('.', $matches[1]);

// Alle echten OUs rekursiv abrufen
function getOUs($ldap_conn, $dn, $level = 0) {
    $ous = [];
    $filter = '(objectClass=organizationalUnit)'; // nur OUs
    $attrs = ['ou','distinguishedName'];
    $search = ldap_list($ldap_conn, $dn, $filter, $attrs);
    if ($search) {
        $entries = ldap_get_entries($ldap_conn, $search);
        for ($i=0; $i < $entries['count']; $i++) {
            $ou_dn = $entries[$i]['distinguishedname'][0] ?? '';
            $ou_name = $entries[$i]['ou'][0] ?? 'unbekannt';
            $ous[] = ['dn' => $ou_dn, 'name' => str_repeat('→ ', $level) . $ou_name];
            // Rekursion für Unter-OUs
            $ous = array_merge($ous, getOUs($ldap_conn, $ou_dn, $level + 1));
        }
    }
    return $ous;
}

// Root OU zuerst
$ous = [
    ['dn' => $base_dn, 'name' => $root_name]
];

// Alle Unter-OUs
$ous = array_merge($ous, getOUs($ldap_conn, $base_dn));

echo json_encode(['ous' => $ous]);
