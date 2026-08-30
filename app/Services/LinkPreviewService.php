<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

class LinkPreviewService
{
    /**
     * Fetch a public Open Graph snapshot for a URL.
     *
     * @return array{url: string, title: string|null, description: string|null, image: string|null}
     */
    public function fetch(string $url): array
    {
        $url = $this->normalizeUrl($url);

        $empty = [
            'url' => $url,
            'title' => null,
            'description' => null,
            'image' => null,
        ];

        if (! $this->isSafePublicUrl($url)) {
            return $empty;
        }

        try {
            $response = Http::timeout(6)
                ->connectTimeout(4)
                ->withHeaders([
                    'User-Agent' => 'EddySocialBot/1.0',
                    'Accept' => 'text/html,application/xhtml+xml',
                ])
                ->withOptions(['allow_redirects' => ['max' => 3]])
                ->get($url);
        } catch (Throwable) {
            return $empty;
        }

        if (! $response->successful()) {
            return $empty;
        }

        $html = Str::limit($response->body(), 500_000, '');

        return [
            'url' => $url,
            'title' => $this->meta($html, ['og:title', 'twitter:title']) ?? $this->titleTag($html),
            'description' => $this->meta($html, ['og:description', 'twitter:description', 'description']),
            'image' => $this->absoluteUrl($url, $this->meta($html, ['og:image', 'twitter:image'])),
        ];
    }

    public function normalizeUrl(string $url): string
    {
        $url = trim($url);

        if (! preg_match('/^https?:\/\//i', $url)) {
            $url = 'https://'.$url;
        }

        return $url;
    }

    public function isSafePublicUrl(string $url): bool
    {
        $parts = parse_url($url);

        if (! is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
            return false;
        }

        if (! in_array(strtolower($parts['scheme']), ['http', 'https'], true)) {
            return false;
        }

        $host = strtolower($parts['host']);

        if (in_array($host, ['localhost', '127.0.0.1', '::1', '0.0.0.0'], true)) {
            return false;
        }

        if (str_ends_with($host, '.local') || str_ends_with($host, '.internal') || str_ends_with($host, '.localhost')) {
            return false;
        }

        $ips = filter_var($host, FILTER_VALIDATE_IP)
            ? [$host]
            : (gethostbynamel($host) ?: []);

        foreach ($ips as $ip) {
            if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return false;
            }
        }

        return $ips !== [];
    }

    /**
     * @param  list<string>  $keys
     */
    private function meta(string $html, array $keys): ?string
    {
        foreach ($keys as $key) {
            $pattern = '/<meta[^>]+(?:property|name)=["\']'.preg_quote($key, '/').'["\'][^>]+content=["\']([^"\']+)["\']/i';

            if (preg_match($pattern, $html, $matches) === 1) {
                return html_entity_decode(trim($matches[1]), ENT_QUOTES | ENT_HTML5);
            }

            $pattern = '/<meta[^>]+content=["\']([^"\']+)["\'][^>]+(?:property|name)=["\']'.preg_quote($key, '/').'["\']/i';

            if (preg_match($pattern, $html, $matches) === 1) {
                return html_entity_decode(trim($matches[1]), ENT_QUOTES | ENT_HTML5);
            }
        }

        return null;
    }

    private function titleTag(string $html): ?string
    {
        if (preg_match('/<title[^>]*>(.*?)<\/title>/is', $html, $matches) !== 1) {
            return null;
        }

        $title = trim(html_entity_decode(strip_tags($matches[1]), ENT_QUOTES | ENT_HTML5));

        return $title !== '' ? $title : null;
    }

    private function absoluteUrl(string $base, ?string $image): ?string
    {
        if ($image === null || $image === '') {
            return null;
        }

        if (preg_match('/^https?:\/\//i', $image) === 1) {
            return $this->isSafePublicUrl($image) ? $image : null;
        }

        $parts = parse_url($base);

        if (! is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
            return null;
        }

        $origin = $parts['scheme'].'://'.$parts['host'];

        if (str_starts_with($image, '//')) {
            $absolute = $parts['scheme'].':'.$image;
        } elseif (str_starts_with($image, '/')) {
            $absolute = $origin.$image;
        } else {
            $absolute = $origin.'/'.$image;
        }

        return $this->isSafePublicUrl($absolute) ? $absolute : null;
    }
}
