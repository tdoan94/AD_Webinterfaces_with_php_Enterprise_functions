<?php
declare(strict_types=1);

/**
 * Rekursive Funktion, um OUs aus LDAP auszulesen
 *
 * @param resource $ldap_conn
 * @param string $base_dn
 * @param int $level
 * @return array<string,string> Array mit DN => Anzeige-Name
 */
function getOUsFromLDAP($ldap_conn, string $base_dn, int $level = 0): array
{
    $ous = [];
    if ($level === 0) {
        $ous[$base_dn] = "🌐 Root-Domain ($base_dn)";
    }

    $filter = "(objectClass=organizationalUnit)";
    $attributes = ["distinguishedName", "ou"];
    $search = @ldap_list($ldap_conn, $base_dn, $filter, $attributes);

    if ($search !== false) {
        $entries = ldap_get_entries($ldap_conn, $search);
        for ($i = 0; $i < $entries["count"]; $i++) {
            $dn = $entries[$i]["distinguishedname"][0] ?? null;
            $ouName = $entries[$i]["ou"][0] ?? null;
            if ($dn && $ouName) {
                $label = str_repeat("— ", $level + 1) . $ouName;
                $ous[$dn] = $label;
                $ous = array_merge($ous, getOUsFromLDAP($ldap_conn, $dn, $level + 1));
            }
        }
    }

    return $ous;
}

/**
 * Benutzer in AD anlegen
 *
 * @param resource $ldap_conn
 * @param string $givenName
 * @param string $sn
 * @param string $sAMAccountName
 * @param string $userPrincipalName
 * @param string $password
 * @param string $selected_ou
 * @param string $auto_group_dn Optional: Gruppe, in die der User automatisch hinzugefügt wird
 * @return array{success: bool, message: string}
 */
function createLDAPUser(
    $ldap_conn,
    string $givenName,
    string $sn,
    string $sAMAccountName,
    string $userPrincipalName,
    string $password,
    string $selected_ou,
    string $auto_group_dn = ""
): array {
    $cn = $givenName . ' ' . $sn;
    $userDN = "CN={$cn}," . $selected_ou;

    $entry = [
        "cn" => $cn,
        "sn" => $sn,
        "givenName" => $givenName,
        "objectClass" => ["top", "person", "organizationalPerson", "user"],
        "sAMAccountName" => $sAMAccountName,
        "userPrincipalName" => $userPrincipalName,
        "displayName" => $cn,
        "userAccountControl" => 514, // deaktiviert initial
    ];

    if (!ldap_add($ldap_conn, $userDN, $entry)) {
        return ['success' => false, 'message' => "Benutzer konnte nicht angelegt werden: " . ldap_error($ldap_conn)];
    }

    // Passwort setzen
    $unicodePwd = '"' . $password . '"';
    $unicodePwd = mb_convert_encoding($unicodePwd, 'UTF-16LE');

    if (!ldap_mod_replace($ldap_conn, $userDN, ["unicodePwd" => $unicodePwd])) {
        ldap_delete($ldap_conn, $userDN);
        return ['success' => false, 'message' => "Passwort konnte nicht gesetzt werden: " . ldap_error($ldap_conn)];
    }

    // Account aktivieren
    ldap_mod_replace($ldap_conn, $userDN, ["userAccountControl" => 512]);

    // Optional in Gruppe hinzufügen
    if ($auto_group_dn) {
        @ldap_mod_add($ldap_conn, $auto_group_dn, ["member" => $userDN]);
    }

    return ['success' => true, 'message' => "Benutzer '$cn' erfolgreich erstellt."];
}

/**
 * LDAP DN Escape für spezielle Zeichen
 *
 * @param string $str
 * @return string
 */
function ldap_escape_dn(string $str): string {
    $metaChars = ['\\', ',', '+', '"', '<', '>', ';', '='];
    $escaped = '';
    for ($i = 0; $i < strlen($str); $i++) {
        $c = $str[$i];
        $escaped .= in_array($c, $metaChars) ? '\\' . $c : $c;
    }
    if (strlen($escaped) > 0) {
        if ($escaped[0] === ' ') $escaped = '\\ ' . substr($escaped, 1);
        if (substr($escaped, -1) === ' ') $escaped = substr($escaped, 0, -1) . '\\ ';
    }
    return $escaped;
}
