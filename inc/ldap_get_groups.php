<?php
/**
 * Helper: get the DN of a group when given its CN.
 * If $identifier already looks like a DN (contains '='), returns it unchanged.
 *
 * @param resource $ldap_conn
 * @param string $base_dn
 * @param string $groupIdentifier CN or DN
 * @return string|null DN or null if not found
 */
function getGroupDNByCN($ldap_conn, string $base_dn, string $groupIdentifier): ?string
{
    // If it already has an '=' sign assume it's a DN
    if (strpos($groupIdentifier, '=') !== false && strpos($groupIdentifier, ',') !== false) {
        return $groupIdentifier;
    }

    $escaped = safe_ldap_escape($groupIdentifier, '', 0);
    $filter = sprintf('(&(objectClass=group)(cn=%s))', $escaped);
    $search = @ldap_search($ldap_conn, $base_dn, $filter, ['distinguishedName']);
    if (!$search) {
        return null;
    }
    $entries = ldap_get_entries($ldap_conn, $search);
    if (!isset($entries[0]['distinguishedname'][0])) {
        return null;
    }
    return $entries[0]['distinguishedname'][0];
}

/**
 * Get all groups. Returns array of:
 * [
 *   ['cn' => 'GroupName', 'dn' => 'CN=GroupName,OU=...,DC=...', 'members' => [ 'DN1', 'DN2', ... ] ],
 *   ...
 * ]
 *
 * @param resource $ldap_conn
 * @param string $base_dn
 * @return array
 */
function getGroups($ldap_conn, string $base_dn): array
{
    $groups = [];
    $filter = '(objectClass=group)';
    $attrs = ['cn', 'member', 'distinguishedName'];
    $search = @ldap_search($ldap_conn, $base_dn, $filter, $attrs);
    if ($search === false) {
        // return empty array on search failure; caller can decide
        return [];
    }
    $entries = ldap_get_entries($ldap_conn, $search);
    $count = (int)($entries['count'] ?? 0);
    for ($i = 0; $i < $count; $i++) {
        $cn = $entries[$i]['cn'][0] ?? '';
        $dn = $entries[$i]['distinguishedname'][0] ?? '';
        $members = $entries[$i]['member'] ?? [];
        $groups[] = [
            'cn' => $cn,
            'dn' => $dn,
            'members' => $members
        ];
    }
    return $groups;
}

/**
 * Get members of a group.
 * $groupIdentifier can be CN or DN. Returns array of members with cn and sAMAccountName.
 *
 * @param resource $ldap_conn
 * @param string $base_dn
 * @param string $groupIdentifier
 * @return array
 */
function getGroupMembers($ldap_conn, string $base_dn, string $groupIdentifier): array
{
    $members = [];

    $group_dn = getGroupDNByCN($ldap_conn, $base_dn, $groupIdentifier);
    if ($group_dn === null) {
        return $members;
    }

    $search = @ldap_read($ldap_conn, $group_dn, '(objectClass=group)', ['member']);
    if ($search === false) {
        return $members;
    }

    $entries = ldap_get_entries($ldap_conn, $search);
    if (empty($entries) || !isset($entries[0]['member'])) {
        return $members;
    }

    $member_dns = $entries[0]['member'];
    $mcount = (int)($member_dns['count'] ?? 0);
    for ($i = 0; $i < $mcount; $i++) {
        $user_dn = $member_dns[$i];
        // read user attributes
        $u_search = @ldap_read($ldap_conn, $user_dn, '(objectClass=person)', ['cn', 'sAMAccountName']);
        if ($u_search === false) {
            // skip if cannot read user entry
            continue;
        }
        $u_entries = ldap_get_entries($ldap_conn, $u_search);
        if (isset($u_entries[0])) {
            $members[] = [
                'dn' => $user_dn,
                'cn' => $u_entries[0]['cn'][0] ?? $user_dn,
                'sAMAccountName' => $u_entries[0]['samaccountname'][0] ?? ''
            ];
        }
    }
    return $members;
}
?>