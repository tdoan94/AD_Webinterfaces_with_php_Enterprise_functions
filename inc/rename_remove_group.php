<?php
/**
 * Delete a group (accepts DN or CN). Returns bool.
 *
 * @param resource $ldap_conn
 * @param string $groupIdentifier
 * @return bool
 */
function deleteGroup($ldap_conn, string $groupIdentifier): bool
{
    $base_dn = $GLOBALS['base_dn'] ?? '';
    $group_dn = getGroupDNByCN($ldap_conn, $base_dn, $groupIdentifier);
    if ($group_dn === null) {
        error_log('deleteGroup: group not found: ' . $groupIdentifier);
        return false;
    }
    $r = @ldap_delete($ldap_conn, $group_dn);
    if ($r === false) {
        $err = ldap_error($ldap_conn);
        error_log("LDAP delete failed: $err (group $group_dn)");
    }
    return $r !== false;
}

/**
 * Rename a group: provide groupIdentifier (DN or CN) and new CN.
 * Returns bool.
 *
 * Gruppe anhand CN oder DN umbenennen
 *
 * @param resource $ldap_conn  Offene LDAP-Verbindung
 * @param string   $groupIdentifier  Kann CN oder vollständiger DN sein
 * @param string   $new_cn           Neuer Gruppenname (CN)
 *
 * @return bool Erfolg oder Fehler
 */
function renameGroup($ldap_conn, string $groupIdentifier, string $new_cn): bool
{
    $base_dn = $GLOBALS['base_dn'] ?? '';

    // Prüfen ob Identifier schon ein DN ist oder nur CN
    if (stripos($groupIdentifier, 'CN=') === 0) {
        // schon DN
        $group_dn = $groupIdentifier;
    } else {
        // CN -> DN suchen
        $group_dn = getGroupDNByCN($ldap_conn, $base_dn, $groupIdentifier);
        if ($group_dn === null) {
            error_log('renameGroup: group not found: ' . $groupIdentifier);
            return false;
        }
    }

    // Parent-DN extrahieren (alles nach dem ersten Komma)
    $parts = explode(',', $group_dn, 2);
    if (count($parts) < 2) {
        error_log("renameGroup: invalid DN: $group_dn");
        return false;
    }
    $parent_dn = $parts[1];

    // Neuen RDN bauen (korrekt escapen für DN)
    $escaped_cn = ldap_escape($new_cn, '', LDAP_ESCAPE_DN);
    $new_rdn = 'CN=' . $escaped_cn;

    // Rename durchführen
    $r = @ldap_rename($ldap_conn, $group_dn, $new_rdn, $parent_dn, true);

    if ($r === false) {
        $err = ldap_error($ldap_conn);
        error_log("LDAP rename failed: $err (from $group_dn to $new_rdn,$parent_dn)");
        return false;
    }

    return true;
}

/**
 * Move a group to a different OU (target container).
 *
 * @param resource $ldap_conn
 * @param string $groupIdentifier DN or CN of group
 * @param string $target_ou_dn DistinguishedName of target OU
 * @return bool
 */
function moveGroup($ldap_conn, string $groupIdentifier, string $target_ou_dn): bool
{
    $base_dn = $GLOBALS['base_dn'] ?? '';

    // DN bestimmen
    $group_dn = (stripos($groupIdentifier, 'CN=') === 0)
        ? $groupIdentifier
        : getGroupDNByCN($ldap_conn, $base_dn, $groupIdentifier);

    if ($group_dn === null) {
        error_log('moveGroup: group not found: ' . $groupIdentifier);
        return false;
    }

    // RDN extrahieren
    $parts = explode(',', $group_dn, 2);
    if (count($parts) < 2) {
        error_log("moveGroup: invalid DN: $group_dn");
        return false;
    }
    $rdn = $parts[0]; // CN=...

    // Gruppe verschieben
    $r = @ldap_rename($ldap_conn, $group_dn, $rdn, $target_ou_dn, true);
    if ($r === false) {
        $err = ldap_error($ldap_conn);
        error_log("LDAP move failed: $err (from $group_dn to $target_ou_dn)");
        return false;
    }
    return true;
}

?>