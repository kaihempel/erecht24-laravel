<?php

declare(strict_types=1);

namespace KaiHempel\ERecht24\Support;

/**
 * Pure helpers for reasoning about push URIs: validation, reachability,
 * normalization for comparison, and redaction for display.
 */
final class PushUri
{
    private const DEFAULT_PORTS = ['http' => 80, 'https' => 443];

    private const LOCAL_SUFFIXES = ['.test', '.local', '.localhost'];

    public static function isValid(string $uri): bool
    {
        $parts = parse_url($uri);

        if ($parts === false) {
            return false;
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? ''));

        return isset(self::DEFAULT_PORTS[$scheme]) && self::host($uri) !== null;
    }

    /**
     * True when the host cannot be reached by eRecht24 from the public internet:
     * localhost / local dev TLDs, loopback, unspecified, private and reserved ranges.
     */
    public static function isLocal(string $uri): bool
    {
        $host = self::host($uri);

        if ($host === null) {
            return false;
        }

        if ($host === 'localhost') {
            return true;
        }

        foreach (self::LOCAL_SUFFIXES as $suffix) {
            if (str_ends_with($host, $suffix)) {
                return true;
            }
        }

        $ip = self::unmapIp($host);

        if ($ip === null) {
            return false;
        }

        return str_starts_with($ip, '127.')
            || $ip === '0.0.0.0'
            || filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false;
    }

    /**
     * Lowercase scheme/host, default ports dropped, trailing slash trimmed, userinfo/fragment removed.
     */
    public static function normalize(string $uri): string
    {
        $parts = parse_url($uri);
        $host = self::host($uri);

        if ($parts === false || $host === null) {
            return trim($uri);
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $port = $parts['port'] ?? null;
        $authority = str_contains($host, ':') ? '['.$host.']' : $host;

        if ($port !== null && (self::DEFAULT_PORTS[$scheme] ?? null) !== $port) {
            $authority .= ':'.$port;
        }

        $path = rtrim((string) ($parts['path'] ?? ''), '/');
        $query = isset($parts['query']) ? '?'.$parts['query'] : '';

        return $scheme.'://'.$authority.$path.$query;
    }

    /**
     * Display-safe form: no userinfo, query string, or fragment.
     */
    public static function redact(string $uri): string
    {
        $parts = parse_url($uri);

        if ($parts === false || ! isset($parts['host'])) {
            return '[unparseable URI]';
        }

        $host = str_contains($parts['host'], ':') ? '['.trim($parts['host'], '[]').']' : $parts['host'];

        return (isset($parts['scheme']) ? $parts['scheme'].'://' : '')
            .$host
            .(isset($parts['port']) ? ':'.$parts['port'] : '')
            .($parts['path'] ?? '');
    }

    private static function host(string $uri): ?string
    {
        $host = parse_url($uri, PHP_URL_HOST);

        if (! is_string($host)) {
            return null;
        }

        $host = rtrim(strtolower(trim($host, '[]')), '.');

        return $host === '' ? null : $host;
    }

    /**
     * Returns a canonical IP string (IPv4-mapped IPv6 reduced to IPv4), or null for non-IP hosts.
     */
    private static function unmapIp(string $host): ?string
    {
        $packed = @inet_pton($host);

        if ($packed === false) {
            return null;
        }

        if (strlen($packed) === 16 && str_starts_with($packed, str_repeat("\0", 10)."\xff\xff")) {
            $packed = substr($packed, 12);
        }

        $ip = inet_ntop($packed);

        return $ip === false ? null : $ip;
    }
}
