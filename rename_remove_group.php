<?php
function deleteGroup($ldap_conn, string $groupIdentifier): bool
{
    $base_dn = $GLOBALS['base_dn'] ?? '';
    $group_dn = getGroupDNByCN($ldap_conn, $base_dn, $groupIdentifier);
    if ($group_dn === null) { error_log('deleteGroup: group not found: ' . $groupIdentifier); return false; }
    $r = @ldap_delete($ldap_conn, $group_dn);
    if ($r === false) { $err = ldap_error($ldap_conn); error_log("LDAP delete failed: $err (group $group_dn)"); }
    return $r !== false;
}

function renameGroup($ldap_conn, string $groupIdentifier, string $new_cn): bool
{
    $base_dn = $GLOBALS['base_dn'] ?? '';
    if (stripos($groupIdentifier, 'CN=') === 0) { $group_dn = $groupIdentifier; }
    else { $group_dn = getGroupDNByCN($ldap_conn, $base_dn, $groupIdentifier); if ($group_dn === null) { error_log('renameGroup: group not found: ' . $groupIdentifier); return false; } }
    $parts = explode(',', $group_dn, 2);
    if (count($parts) < 2) { error_log("renameGroup: invalid DN: $group_dn"); return false; }
    $parent_dn = $parts[1];
    $escaped_cn = ldap_escape($new_cn, '', LDAP_ESCAPE_DN);
    $new_rdn = 'CN=' . $escaped_cn;
    $r = @ldap_rename($ldap_conn, $group_dn, $new_rdn, $parent_dn, true);
    if ($r === false) { $err = ldap_error($ldap_conn); error_log("LDAP rename failed: $err (from $group_dn to $new_rdn,$parent_dn)"); return false; }
    return true;
}

function moveGroup($ldap_conn, string $groupIdentifier, string $target_ou_dn): bool
{
    $base_dn = $GLOBALS['base_dn'] ?? '';
    $group_dn = (stripos($groupIdentifier, 'CN=') === 0) ? $groupIdentifier : getGroupDNByCN($ldap_conn, $base_dn, $groupIdentifier);
    if ($group_dn === null) { error_log('moveGroup: group not found: ' . $groupIdentifier); return false; }
    $parts = explode(',', $group_dn, 2);
    if (count($parts) < 2) { error_log("moveGroup: invalid DN: $group_dn"); return false; }
    $rdn = $parts[0];
    $r = @ldap_rename($ldap_conn, $group_dn, $rdn, $target_ou_dn, true);
    if ($r === false) { $err = ldap_error($ldap_conn); error_log("LDAP move failed: $err (from $group_dn to $target_ou_dn)"); return false; }
    return true;
}
?>