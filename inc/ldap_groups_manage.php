<?php
declare(strict_types=1);

if (!file_exists(__DIR__ . '/ldaps_config.php') && file_exists(__DIR__ . '/inc/ldaps_config.php')) {
    // falls config in inc liegt
    require_once __DIR__ . '/inc/ldaps_config.php';
} else {
    require_once __DIR__ . '/ldaps_config.php';
}

if (!file_exists(__DIR__ . '/ldap_conn_escape.php') && file_exists(__DIR__ . '/inc/ldap_conn_escape.php')) {
    // falls config in inc liegt
    require_once __DIR__ . '/inc/ldap_conn_escape.php';
} else {
    require_once __DIR__ . '/ldap_conn_escape.php';
}

if (!file_exists(__DIR__ . '/ldap_get_groups.php') && file_exists(__DIR__ . 'inc/ldap_get_groups.php')) {
    // falls config in inc liegt
    require_once __DIR__ . '/inc/ldap_get_groups.php';
} else {
    require_once __DIR__ . '/ldap_get_groups.php';
}
if (!file_exists(__DIR__ . '/rename_remove_group.php') && file_exists(__DIR__ . 'inc/rename_remove_group.php')) {
    // falls config in inc liegt
    require_once __DIR__ . '/inc/rename_remove_group.php';
} else {
    require_once __DIR__ . '/rename_remove_group.php';
}


/**
 * Get all users (map by sAMAccountName => ['dn'=>..., 'cn'=>...])
 *
 * @param resource $ldap_conn
 * @param string $base_dn
 * @return array
 */
function getAllUsers($ldap_conn, string $base_dn): array
{
    $all_users = [];
    $filter = '(&(objectClass=user)(sAMAccountName=*))';
    $attrs = ['cn', 'sAMAccountName', 'distinguishedName'];
    $search = @ldap_search($ldap_conn, $base_dn, $filter, $attrs);
    if ($search === false) {
        return $all_users;
    }

    $entries = ldap_get_entries($ldap_conn, $search);
    $count = (int)($entries['count'] ?? 0);
    for ($i = 0; $i < $count; $i++) {
        $sam = $entries[$i]['samaccountname'][0] ?? null;
        $dn = $entries[$i]['distinguishedname'][0] ?? null;
        $cn = $entries[$i]['cn'][0] ?? null;
        if ($sam && $dn && $cn) {
            $all_users[$sam] = ['dn' => $dn, 'cn' => $cn];
        }
    }
    return $all_users;
}

/**
 * Add members (list of sAMAccountName) to a group (accepts DN or CN).
 * Returns ['added'=>[], 'errors'=>[]]
 *
 * @param resource $ldap_conn
 * @param string $groupIdentifier DN or CN
 * @param array $user_sams
 * @return array
 */
function addMembersToGroup($ldap_conn, string $groupIdentifier, array $user_sams): array
{
    $added = [];
    $errors = [];

    $base_dn = $GLOBALS['base_dn'] ?? '';
    $group_dn = getGroupDNByCN($ldap_conn, $base_dn, $groupIdentifier);
    if ($group_dn === null) {
        $errors[] = 'Group not found';
        return ['added' => $added, 'errors' => $errors];
    }

    foreach ($user_sams as $user_sam) {
        $user_sam = trim((string)$user_sam);
        if ($user_sam === '') {
            continue;
        }
        $escaped = safe_ldap_escape($user_sam, '', 0);
        $filter = sprintf('(&(objectClass=user)(sAMAccountName=%s))', $escaped);
        $search = @ldap_search($ldap_conn, $base_dn, $filter, ['distinguishedName']);
        if ($search === false) {
            $errors[] = $user_sam . ' (search failed)';
            continue;
        }
        $entries = ldap_get_entries($ldap_conn, $search);
        if (empty($entries) || !isset($entries[0]['distinguishedname'][0])) {
            $errors[] = $user_sam . ' (not found)';
            continue;
        }
        $user_dn = $entries[0]['distinguishedname'][0];
        $r = @ldap_mod_add($ldap_conn, $group_dn, ['member' => $user_dn]);
        if ($r === false) {
            $err = ldap_error($ldap_conn);
            error_log("LDAP mod_add failed: $err (user $user_dn -> group $group_dn)");
            $errors[] = $user_sam . ' (error: ' . $err . ')';
        } else {
            $added[] = $user_sam;
        }
    }

    return ['added' => $added, 'errors' => $errors];
}

/**
 * Remove members (list of sAMAccountName) from a group (accepts DN or CN).
 * Returns ['removed'=>[], 'errors'=>[]]
 *
 * @param resource $ldap_conn
 * @param string $groupIdentifier
 * @param array $user_sams
 * @return array
 */
function removeMembersFromGroup($ldap_conn, string $groupIdentifier, array $user_sams): array
{
    $removed = [];
    $errors = [];

    $base_dn = $GLOBALS['base_dn'] ?? '';
    $group_dn = getGroupDNByCN($ldap_conn, $base_dn, $groupIdentifier);
    if ($group_dn === null) {
        $errors[] = 'Group not found';
        return ['removed' => $removed, 'errors' => $errors];
    }

    foreach ($user_sams as $user_sam) {
        $user_sam = trim((string)$user_sam);
        if ($user_sam === '') {
            continue;
        }
        $escaped = safe_ldap_escape($user_sam, '', 0);
        $filter = sprintf('(&(objectClass=user)(sAMAccountName=%s))', $escaped);
        $search = @ldap_search($ldap_conn, $base_dn, $filter, ['distinguishedName']);
        if ($search === false) {
            $errors[] = $user_sam . ' (search failed)';
            continue;
        }
        $entries = ldap_get_entries($ldap_conn, $search);
        if (empty($entries) || !isset($entries[0]['distinguishedname'][0])) {
            $errors[] = $user_sam . ' (not found)';
            continue;
        }
        $user_dn = $entries[0]['distinguishedname'][0];
        $r = @ldap_mod_del($ldap_conn, $group_dn, ['member' => $user_dn]);
        if ($r === false) {
            $err = ldap_error($ldap_conn);
            error_log("LDAP mod_del failed: $err (user $user_dn -> group $group_dn)");
            $errors[] = $user_sam . ' (error: ' . $err . ')';
        } else {
            $removed[] = $user_sam;
        }
    }

    return ['removed' => $removed, 'errors' => $errors];
}





