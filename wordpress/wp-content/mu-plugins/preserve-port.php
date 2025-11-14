<?php
/**
 * MU plugin: Preserve non-standard port in WordPress URLs and redirects.
 *
 * In some dev/proxy setups (e.g., http://localhost:8015), WordPress or Apache
 * environment variables may omit the external port, causing redirects or
 * generated links to drop the port. This plugin normalizes the detected host,
 * scheme, and port using server and common proxy headers, then ensures
 * home_url/site_url and wp_redirect preserve a non-standard port.
 */

if (!defined('ABSPATH')) {
    exit;
}

// Only run for non-CLI requests
if (php_sapi_name() === 'cli' || defined('WP_CLI') && WP_CLI) {
    return;
}

/**
 * Detect external scheme, host, and port considering proxy headers.
 */
function nrfc_detect_request_origin(): array
{
    $server = $_SERVER;

    // Scheme
    $scheme = (!empty($server['HTTP_X_FORWARDED_PROTO']))
        ? strtolower(trim(explode(',', $server['HTTP_X_FORWARDED_PROTO'])[0]))
        : ((isset($server['HTTPS']) && $server['HTTPS'] !== 'off') ? 'https' : 'http');

    // Host (may include port)
    $host = !empty($server['HTTP_X_FORWARDED_HOST'])
        ? trim(explode(',', $server['HTTP_X_FORWARDED_HOST'])[0])
        : (!empty($server['HTTP_HOST']) ? $server['HTTP_HOST'] : ($server['SERVER_NAME'] ?? 'localhost'));

    // If host includes port, split it out
    $host_only = $host;
    $port_from_host = null;
    if (strpos($host, ':') !== false) {
        [$host_only, $port_part] = explode(':', $host, 2);
        if (ctype_digit($port_part)) {
            $port_from_host = (int) $port_part;
        }
    }

    // Port
    if (!empty($server['HTTP_X_FORWARDED_PORT']) && ctype_digit((string)$server['HTTP_X_FORWARDED_PORT'])) {
        $port = (int) $server['HTTP_X_FORWARDED_PORT'];
    } elseif ($port_from_host) {
        $port = $port_from_host;
    } elseif (!empty($server['SERVER_PORT'])) {
        $port = (int) $server['SERVER_PORT'];
    } else {
        $port = ($scheme === 'https') ? 443 : 80;
    }

    return [
        'scheme' => $scheme,
        'host' => $host_only,
        'port' => $port,
    ];
}

/**
 * Determine if a port is non-standard for a scheme.
 */
function nrfc_is_non_standard_port(string $scheme, int $port): bool
{
    return !($scheme === 'http' && $port === 80) && !($scheme === 'https' && $port === 443);
}

/**
 * Append port to a URL if it targets the current host and lacks the non-standard port.
 */
function nrfc_ensure_url_port(string $url): string
{
    [$scheme, $host, $port] = (function () {
        $o = nrfc_detect_request_origin();
        return [$o['scheme'], $o['host'], $o['port']];
    })();

    if (!nrfc_is_non_standard_port($scheme, $port)) {
        return $url; // standard ports need no change
    }

    $parts = wp_parse_url($url);
    if (!$parts || empty($parts['host'])) {
        return $url; // relative URL or unparsable
    }

    // Only adjust if URL points to the same host and scheme
    $url_host = strtolower($parts['host']);
    $url_scheme = isset($parts['scheme']) ? strtolower($parts['scheme']) : $scheme;
    $same_host = ($url_host === strtolower($host));
    $same_scheme = ($url_scheme === $scheme);

    if ($same_host && $same_scheme && empty($parts['port'])) {
        // Rebuild URL with port
        $parts['port'] = $port;
        $rebuilt = $parts['scheme'] . '://' . $parts['host'] . ':' . $parts['port'];
        if (!empty($parts['path'])) $rebuilt .= $parts['path'];
        if (!empty($parts['query'])) $rebuilt .= '?' . $parts['query'];
        if (!empty($parts['fragment'])) $rebuilt .= '#' . $parts['fragment'];
        return $rebuilt;
    }
    return $url;
}

// Make is_ssl() aware of forwarded proto early (helps WordPress canonical redirects)
add_filter('determine_current_user', function ($user) {
    $o = nrfc_detect_request_origin();
    if ($o['scheme'] === 'https' && (!isset($_SERVER['HTTPS']) || $_SERVER['HTTPS'] === 'off')) {
        $_SERVER['HTTPS'] = 'on';
        $_SERVER['SERVER_PORT'] = (string) $o['port'];
    }
    return $user;
}, 1);

// Ensure home_url and site_url include non-standard port
foreach ([
    'home_url',
    'site_url',
] as $filter) {
    add_filter($filter, function ($url) {
        return nrfc_ensure_url_port($url);
    }, 20);
}

// Preserve port in redirects
add_filter('wp_redirect', function ($location) {
    return nrfc_ensure_url_port($location);
}, 20);
