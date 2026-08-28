<?php

declare(strict_types=1);

namespace App\Services;

use App\Data\FetchedImage;
use App\Exceptions\ImageFetchException;
use Illuminate\Support\Facades\Http;
use Psr\Http\Message\ResponseInterface;
use Throwable;

/**
 * Downloads an image from a URL supplied by the model.
 *
 * The URL is untrusted input even though only two people can reach this app, so
 * this deliberately does the work Laravel's HTTP client will not: it requires
 * https, resolves the hostname itself and refuses private and link-local
 * addresses, pins the connection to the address it checked so DNS cannot be
 * re-pointed underneath it, follows at most three redirects, and re-runs the
 * whole check on every hop.
 */
final class SafeImageFetcher
{
    private const MAX_BYTES = 5 * 1024 * 1024;

    private const MAX_REDIRECTS = 3;

    private const CONNECT_TIMEOUT_SECONDS = 5;

    private const TOTAL_TIMEOUT_SECONDS = 15;

    /**
     * Some CDNs reject anything that does not look like a browser.
     */
    private const USER_AGENT = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 '
        .'(KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36';

    /**
     * @throws ImageFetchException
     */
    public function fetch(string $url): FetchedImage
    {
        $current = $url;

        for ($hop = 0; $hop <= self::MAX_REDIRECTS; $hop++) {
            [$host, $port, $ip] = $this->assertReachableSafely($current);

            try {
                $response = Http::withHeaders([
                    'User-Agent' => self::USER_AGENT,
                    'Accept' => 'image/avif,image/webp,image/apng,image/*,*/*;q=0.8',
                ])
                    ->connectTimeout(self::CONNECT_TIMEOUT_SECONDS)
                    ->timeout(self::TOTAL_TIMEOUT_SECONDS)
                    ->withOptions($this->requestOptions($host, $port, $ip))
                    ->get($current);
            } catch (Throwable $exception) {
                throw ImageFetchException::because('the download failed: '.$exception->getMessage());
            }

            $status = $response->status();

            if ($status >= 300 && $status < 400) {
                $location = $response->header('Location');

                if ($location === '') {
                    throw ImageFetchException::because("the server redirected without saying where (HTTP {$status})");
                }

                if ($hop === self::MAX_REDIRECTS) {
                    throw ImageFetchException::because('it redirected more than '.self::MAX_REDIRECTS.' times');
                }

                $current = $this->resolveLocation($current, $location);

                continue;
            }

            if (! $response->successful()) {
                throw ImageFetchException::because("the server returned HTTP {$status}");
            }

            return $this->readImage($response->header('Content-Type'), $response->body());
        }

        throw ImageFetchException::because('it redirected more than '.self::MAX_REDIRECTS.' times');
    }

    /**
     * Guzzle options for one hop.
     *
     * Deliberately does NOT set `stream`. Guzzle routes streaming requests to
     * its stream handler, which refuses cURL options outright — so asking for
     * both a streamed body and a pinned address silently breaks the download.
     * The size cap is enforced from the headers and the body instead.
     *
     * @return array<string, mixed>
     */
    public function requestOptions(string $host, int $port, string $ip): array
    {
        return [
            'allow_redirects' => false,
            // Pin the hostname to the address we just vetted, so the name
            // cannot resolve somewhere else between the check and the request.
            'curl' => [CURLOPT_RESOLVE => ["{$host}:{$port}:{$ip}"]],
            // Refuse an oversized body before it is downloaded, when the server
            // is honest enough to declare a length.
            'on_headers' => function (ResponseInterface $response): void {
                $declared = (int) $response->getHeaderLine('Content-Length');

                if ($declared > self::MAX_BYTES) {
                    throw ImageFetchException::because('the image is larger than 5 MB');
                }
            },
        ];
    }

    /**
     * Check the scheme, resolve the host and reject any address we should not be
     * talking to.
     *
     * @return array{0: string, 1: int, 2: string} host, port, vetted IP
     *
     * @throws ImageFetchException
     */
    public function assertReachableSafely(string $url): array
    {
        $parts = parse_url($url);

        if ($parts === false || ! isset($parts['host'])) {
            throw ImageFetchException::because('it is not a valid URL');
        }

        if (($parts['scheme'] ?? '') !== 'https') {
            throw ImageFetchException::because('only https URLs are accepted');
        }

        $host = $parts['host'];
        $port = (int) ($parts['port'] ?? 443);

        $addresses = $this->resolve($host);

        if ($addresses === []) {
            throw ImageFetchException::because("the hostname {$host} does not resolve");
        }

        // Every address it answers to must be safe, not just the first: a host
        // that resolves to both a public and a private address is still a way in.
        foreach ($addresses as $ip) {
            if (! $this->isPubliclyRoutable($ip)) {
                throw ImageFetchException::because("it resolves to a private or link-local address ({$ip})");
            }
        }

        return [$host, $port, $addresses[0]];
    }

    /**
     * Every address the hostname resolves to, v4 and v6. A literal IP in the URL
     * is returned as-is so it still gets range-checked.
     *
     * @return array<int, string>
     */
    private function resolve(string $host): array
    {
        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return [$host];
        }

        $host = trim($host, '[]');

        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return [$host];
        }

        $addresses = gethostbynamel($host) ?: [];

        $records = @dns_get_record($host, DNS_AAAA);

        if (is_array($records)) {
            foreach ($records as $record) {
                if (isset($record['ipv6']) && is_string($record['ipv6'])) {
                    $addresses[] = $record['ipv6'];
                }
            }
        }

        return array_values(array_unique($addresses));
    }

    /**
     * Rejects 10/8, 172.16/12, 192.168/16, 127/8, 169.254/16, ::1, fc00::/7 and
     * the rest of the reserved space.
     */
    private function isPubliclyRoutable(string $ip): bool
    {
        return filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE,
        ) !== false;
    }

    private function resolveLocation(string $from, string $location): string
    {
        if (str_starts_with($location, 'http://') || str_starts_with($location, 'https://')) {
            return $location;
        }

        $parts = parse_url($from);
        $base = ($parts['scheme'] ?? 'https').'://'.($parts['host'] ?? '')
            .(isset($parts['port']) ? ':'.$parts['port'] : '');

        if (str_starts_with($location, '/')) {
            return $base.$location;
        }

        $path = $parts['path'] ?? '/';

        return $base.rtrim(dirname($path), '/').'/'.$location;
    }

    /**
     * Read the body under the size cap and confirm the bytes really are an
     * image. Neither the extension nor the Content-Type header is trusted on
     * its own.
     *
     * @throws ImageFetchException
     */
    private function readImage(string $contentType, string $bytes): FetchedImage
    {
        if (! str_starts_with(strtolower($contentType), 'image/')) {
            $described = $contentType === '' ? 'nothing' : $contentType;

            throw ImageFetchException::because("the server sent {$described} rather than an image");
        }

        if (strlen($bytes) > self::MAX_BYTES) {
            throw ImageFetchException::because('the image is larger than 5 MB');
        }

        $info = @getimagesizefromstring($bytes);

        if ($info === false) {
            throw ImageFetchException::because('the downloaded bytes are not a readable image');
        }

        return new FetchedImage(
            bytes: $bytes,
            mimeType: $info['mime'],
            extension: $this->extensionFor($info['mime']),
        );
    }

    private function extensionFor(string $mimeType): string
    {
        return match (strtolower($mimeType)) {
            'image/jpeg', 'image/jpg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            'image/avif' => 'avif',
            default => 'img',
        };
    }
}
