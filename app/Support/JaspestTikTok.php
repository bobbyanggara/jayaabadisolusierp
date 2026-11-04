<?php

namespace App\Support;

use Illuminate\Support\Facades\Http;

/**
 * Validasi dan normalisasi link video TikTok untuk pemutar di halaman utama.
 *
 * Bentuk baku yang disimpan: https://www.tiktok.com/@akun/video/1234567890123456789
 */
final class JaspestTikTok
{
    /** Host yang memuat link video lengkap. */
    private const VIDEO_HOSTS = ['www.tiktok.com', 'tiktok.com', 'm.tiktok.com'];

    /** Host link pendek yang hanya mengalihkan ke link video. */
    private const SHORT_HOSTS = ['vm.tiktok.com', 'vt.tiktok.com'];

    /**
     * Mengubah link video TikTok menjadi bentuk baku. Tanpa akses jaringan.
     * Mengembalikan null bila bukan link video TikTok yang dikenali.
     */
    public static function normalize(?string $url): ?string
    {
        $url = self::withScheme($url);

        if ($url === null) {
            return null;
        }

        $parts = parse_url($url);

        if (! is_array($parts) || empty($parts['host'])) {
            return null;
        }

        if (! in_array(strtolower($parts['host']), self::VIDEO_HOSTS, true)) {
            return null;
        }

        if (preg_match('#^/(@[A-Za-z0-9._]+)/video/(\d{15,22})(?:/|$)#', $parts['path'] ?? '', $match) !== 1) {
            return null;
        }

        return 'https://www.tiktok.com/'.$match[1].'/video/'.$match[2];
    }

    /**
     * Seperti normalize(), tetapi juga mengikuti link pendek (vm.tiktok.com, tiktok.com/t/...)
     * dengan membaca header pengalihan. Hanya host TikTok yang pernah diakses.
     */
    public static function resolve(?string $url): ?string
    {
        $direct = self::normalize($url);

        if ($direct !== null) {
            return $direct;
        }

        $current = self::withScheme($url);

        for ($hop = 0; $hop < 4 && $current !== null; $hop++) {
            $host = self::hostOf($current);

            if ($host === null || ! self::isAllowedHost($host)) {
                return null;
            }

            try {
                $response = Http::withOptions(['allow_redirects' => false])
                    ->withHeaders(['User-Agent' => 'Mozilla/5.0 (compatible; JASPEST/1.0)'])
                    ->timeout(8)
                    ->get($current);
            } catch (\Throwable) {
                return null;
            }

            $location = trim((string) $response->header('Location'));

            if ($location === '') {
                return null;
            }

            if (str_starts_with($location, '/')) {
                $location = 'https://'.$host.$location;
            }

            $found = self::normalize($location);

            if ($found !== null) {
                return $found;
            }

            $current = $location;
        }

        return null;
    }

    private static function withScheme(?string $url): ?string
    {
        $url = trim((string) $url);

        if ($url === '') {
            return null;
        }

        return preg_match('#^https?://#i', $url) === 1 ? $url : 'https://'.$url;
    }

    private static function hostOf(string $url): ?string
    {
        $host = parse_url($url, PHP_URL_HOST);

        return is_string($host) && $host !== '' ? strtolower($host) : null;
    }

    private static function isAllowedHost(string $host): bool
    {
        return in_array($host, self::VIDEO_HOSTS, true) || in_array($host, self::SHORT_HOSTS, true);
    }
}
