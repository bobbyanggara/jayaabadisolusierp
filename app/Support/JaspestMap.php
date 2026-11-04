<?php

namespace App\Support;

/**
 * Helpers for turning an editable Google Maps share URL into a safe map embed.
 * A normal Google Maps URL is resolved from coordinates or its place/query text;
 * an official Google Maps Embed URL is preserved as supplied.
 */
final class JaspestMap
{
    private const ALLOWED_HOSTS = [
        'google.com', 'google.co.id', 'maps.google.com', 'maps.google.co.id',
        'maps.app.goo.gl', 'goo.gl',
    ];

    public static function isGoogleMapsUrl(?string $value): bool
    {
        $value = trim((string) $value);
        if ($value === '' || filter_var($value, FILTER_VALIDATE_URL) === false) {
            return false;
        }

        $parts = parse_url($value);
        if (! is_array($parts) || strtolower((string) ($parts['scheme'] ?? '')) !== 'https') {
            return false;
        }

        $host = strtolower((string) ($parts['host'] ?? ''));
        foreach (self::ALLOWED_HOSTS as $allowed) {
            if ($host === $allowed || str_ends_with($host, '.'.$allowed)) {
                return true;
            }
        }

        return false;
    }

    public static function embedUrl(?string $link, string $address): string
    {
        $link = trim((string) $link);
        if (self::isGoogleMapsUrl($link)) {
            $parts = parse_url($link) ?: [];
            $path = (string) ($parts['path'] ?? '');

            // Google Embed URLs contain an opaque, provider-generated pb payload.
            if (str_contains($path, '/maps/embed')) {
                return $link;
            }

            $coordinates = self::coordinatesFromUrl($link);
            if ($coordinates !== null) {
                return self::searchEmbed($coordinates[0].','.$coordinates[1]);
            }

            $place = self::placeQueryFromUrl($link);
            if ($place !== null) {
                return self::searchEmbed($place);
            }
        }

        return self::searchEmbed($address);
    }

    public static function externalUrl(?string $link, string $address): string
    {
        $link = trim((string) $link);

        return self::isGoogleMapsUrl($link)
            ? $link
            : 'https://www.google.com/maps/search/?api=1&query='.rawurlencode($address);
    }

    private static function searchEmbed(string $query): string
    {
        return 'https://maps.google.com/maps?q='.rawurlencode($query).'&z=16&output=embed';
    }

    /** @return array{0: float, 1: float}|null */
    private static function coordinatesFromUrl(string $url): ?array
    {
        $patterns = [
            '/@(-?\d{1,2}(?:\.\d+)?),\s*(-?\d{1,3}(?:\.\d+)?)/',
            '/!3d(-?\d{1,2}(?:\.\d+)?)!4d(-?\d{1,3}(?:\.\d+)?)/',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $url, $matches) === 1) {
                $lat = (float) $matches[1];
                $lng = (float) $matches[2];
                if (abs($lat) <= 90 && abs($lng) <= 180) {
                    return [$lat, $lng];
                }
            }
        }

        $parts = parse_url($url);
        parse_str((string) ($parts['query'] ?? ''), $query);
        foreach (['q', 'query', 'll', 'center'] as $key) {
            if (! isset($query[$key]) || ! is_string($query[$key])) {
                continue;
            }
            if (preg_match('/^\s*(-?\d{1,2}(?:\.\d+)?)\s*,\s*(-?\d{1,3}(?:\.\d+)?)\s*$/', $query[$key], $matches) === 1) {
                $lat = (float) $matches[1];
                $lng = (float) $matches[2];
                if (abs($lat) <= 90 && abs($lng) <= 180) {
                    return [$lat, $lng];
                }
            }
        }

        return null;
    }

    private static function placeQueryFromUrl(string $url): ?string
    {
        $parts = parse_url($url);
        if (! is_array($parts)) {
            return null;
        }

        $path = (string) ($parts['path'] ?? '');
        if (preg_match('~/(?:place|search)/([^/]+)~', $path, $matches) === 1) {
            $place = trim(str_replace('+', ' ', rawurldecode($matches[1])));
            if ($place !== '') {
                return $place;
            }
        }

        parse_str((string) ($parts['query'] ?? ''), $query);
        foreach (['q', 'query', 'destination'] as $key) {
            if (isset($query[$key]) && is_string($query[$key]) && trim($query[$key]) !== '') {
                return trim($query[$key]);
            }
        }

        return null;
    }
}
