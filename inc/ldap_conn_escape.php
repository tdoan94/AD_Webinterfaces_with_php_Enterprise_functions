<?php

/**
 * Connect to LDAP (with basic TLS options if configured).
 * Throws Exception on failure.
 *
 * @return resource LDAP connection
 * @throws Exception
 */
function connect_ldap()
{
    global $ldap_server, $ldap_user, $ldap_pass, $cert_file;

    if (!function_exists('ldap_connect')) {
        throw new \RuntimeException('LDAP PHP extension not available');
    }

    $ldap_conn = @ldap_connect($ldap_server);
    if ($ldap_conn === false) {
        throw new \RuntimeException('ldap_connect failed for ' . (string)$ldap_server);
    }

    // Recommended options
    @ldap_set_option($ldap_conn, LDAP_OPT_PROTOCOL_VERSION, 3);
    @ldap_set_option($ldap_conn, LDAP_OPT_REFERRALS, 0);

    // If cert file provided, set CA file; otherwise tolerant mode (but production should validate certs)
    if (!empty($cert_file) && file_exists($cert_file)) {
        @ldap_set_option($ldap_conn, LDAP_OPT_X_TLS_CACERTFILE, $cert_file);
    }
    // Default: be permissive for now (change for production)
    if (defined('LDAP_OPT_X_TLS_REQUIRE_CERT')) {
        @ldap_set_option($ldap_conn, LDAP_OPT_X_TLS_REQUIRE_CERT, LDAP_OPT_X_TLS_NEVER);
    }

    $bindOk = @ldap_bind($ldap_conn, $ldap_user, $ldap_pass);
    if ($bindOk === false) {
        $err = ldap_error($ldap_conn) ?: 'unknown';
        throw new \RuntimeException('LDAP bind failed: ' . $err);
    }

    return $ldap_conn;
}

/**
 * ldap_escape fallback (supports FILTER and DN flags loosely).
 * If PHP has ldap_escape builtin, use that.
 *
 * @param string $str
 * @param string $ignore
 * @param int $flags
 * @return string
 */
if (!function_exists('safe_ldap_escape')) {
    function safe_ldap_escape(string $str, string $ignore = '', int $flags = 0): string
    {
        // If native exists, use it
        if (function_exists('ldap_escape')) {
            return ldap_escape($str, $ignore, $flags);
        }

        // Minimal fallback — escape common filter chars and DN special chars
        $replacements = [
            '\\' => '\\5c',
            '*'  => '\\2a',
            '('  => '\\28',
            ')'  => '\\29',
            "\x00" => '\\00',
            ','  => '\\2c',
            '='  => '\\3d',
            '+'  => '\\2b',
            '<'  => '\\3c',
            '>'  => '\\3e',
            ';'  => '\\3b',
            '"'  => '\\22'
        ];

        $escaped = strtr($str, $replacements);

        // remove ignored chars from escaping if requested
        if (!empty($ignore)) {
            $escaped = str_replace(str_split($ignore), str_split($ignore), $escaped);
        }

        return $escaped;
    }
}


?>
