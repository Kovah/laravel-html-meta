<?php

namespace Kovah\HtmlMeta;

use GuzzleHttp\Exception\RequestException as GuzzleRequestException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Kovah\HtmlMeta\Exceptions\DisallowedIpException;
use Kovah\HtmlMeta\Exceptions\InvalidUrlException;
use Kovah\HtmlMeta\Exceptions\UnreachableUrlException;
use Psr\Http\Message\RequestInterface;

class HtmlMeta
{
    private HtmlMetaParser $parser;
    private PendingRequest $request;

    public function __construct(HtmlMetaParser $parser)
    {
        $this->parser = $parser;
    }

    /**
     * Get the HTML meta for a given URL. After validating the URL, its response
     * is fetched and then passed to the specified parser. The parser takes care
     * of reading the response body, extract all meta tags including the title
     * and then return the tags as an array.
     *
     * @param string $url
     * @return HtmlMetaResult
     * @throws DisallowedIpException|InvalidUrlException|UnreachableUrlException
     */
    public function forUrl(string $url): HtmlMetaResult
    {
        $this->validateUrl($url);
        $response = $this->fetchUrl($url);
        $meta = $this->parser->parse($url, $response);

        return new HtmlMetaResult($meta, $url, $response);
    }

    /**
     * Get the HTML meta for a given URL. After validating the URL, its response
     * is fetched and then passed to the specified parser. The parser takes care
     * of reading the response body, extract all meta tags including the title
     * and then return the tags as an array.
     *
     * @param string      $html
     * @param array|null  $headers
     * @param string|null $url
     * @return HtmlMetaResult
     */
    public function fromHtml(string $html, ?array $headers = null, ?string $url = null): HtmlMetaResult
    {
        $meta = $this->parser->parseHtml($html, $headers, $url);

        return new HtmlMetaResult($meta);
    }

    /**
     * We try to fetch the response from the given URL. The timeout for this
     * operation is specified in the configuration. In case a connection
     * exception (network layer) or a request exception (application layer)
     * occurs, a UnreachableUrlException exception is thrown.
     *
     * @param string $url
     * @return Response
     * @throws DisallowedIpException|UnreachableUrlException
     */
    private function fetchUrl(string $url): Response
    {
        $this->request = Http::timeout(config('html-meta.timeout', 10))
            ->accept(config('html-meta.default_accept', 'text/html'));

        $this->prepareHeaders();
        $this->prepareOptions();
        $this->preparePrivateIpProtection();

        try {
            return $this->request->get($url)->throw();
        } catch (ConnectionException|GuzzleRequestException|RequestException $e) {
            throw new UnreachableUrlException("$url is not reachable. " . $e->getMessage());
        }
    }

    private function prepareHeaders(): void
    {
        $headers = [];

        if (config('html-meta.user_agents', false)) {
            // Add a random user agent from the configuration to the request
            $agents = config('html-meta.user_agents');

            $headers['User-Agent'] = $agents[array_rand($agents)];
        }

        if ($headerConfig = config('html-meta.custom_headers', false)) {
            if (!is_array($headerConfig)) {
                $headerConfig = $this->parseCustomHeaderString($headerConfig);

            }
            foreach ($headerConfig as $header => $value) {
                if (in_array(strtolower($header), ['user-agent', 'accept'])) {
                    continue;
                }
                $headers[$header] = $value;
            }
        }

        if (!empty($headers)) {
            $this->request = $this->request->withHeaders($headers);
        }
    }

    private function parseCustomHeaderString(string $customHeaders): array
    {
        $newHeaders = [];
        $rawHeaders = explode('|', $customHeaders);
        foreach ($rawHeaders as $rawHeader) {
            if (!str_contains($rawHeader, '=')) {
                continue;
            }
            [$key, $value] = explode('=', $rawHeader);
            $newHeaders[$key] = $value;
        }
        return $newHeaders;
    }

    private function prepareOptions(): void
    {
        $options = config('html-meta.custom_options');

        if (is_array($options)) {
            $this->request = $this->request->withOptions($options);
        }
    }

    private function preparePrivateIpProtection(): void
    {
        $this->request = $this->applyPrivateIpProtection($this->request);
    }

    /**
     * Attaches the private-IP protection to an arbitrary pending request, so
     * consuming applications can reuse the exact same validation for requests
     * they build and send themselves instead of reimplementing it (which is
     * how GHSA-x8w7-mhjm-xvj2 arose: a second, divergent copy of this logic).
     *
     * Validating the hostname is not enough on its own: cURL re-resolves the
     * host itself when it connects, independently of the lookup used here.
     * An attacker controlling DNS for the host (short TTL, authoritative
     * answers) can return a public IP for our lookup and a private/loopback
     * one moments later for cURL's own connect-time lookup (DNS rebinding).
     * We use withMiddleware() rather than withRequestMiddleware() because we
     * need access to the transfer $options, not just the PSR-7 request, to
     * pin the connection to the IP(s) we just validated via CURLOPT_RESOLVE.
     * CURLOPT_RESOLVE only has an effect when Guzzle actually hands the
     * request to its cURL handler; if cURL is unavailable, or the request
     * forces Guzzle's StreamHandler via the "stream" option, the option is
     * silently ignored and the rebinding window would reopen unnoticed. We
     * fail closed in that case rather than send an unpinned request.
     */
    public function applyPrivateIpProtection(PendingRequest $request): PendingRequest
    {
        if (!$this->shouldBlockPrivateIps()) {
            return $request;
        }

        return $request->withMiddleware(function (callable $handler) {
            return function (RequestInterface $request, array $options) use ($handler) {
                $uri = $request->getUri();
                $host = $this->normalizeHost($uri->getHost());

                if ($host !== '') {
                    $validatedIps = $this->resolveValidatedPublicIps($host, (string) $uri);

                    if ($validatedIps !== []) {
                        if (!$this->canPinResolvedIps($options)) {
                            throw new DisallowedIpException(
                                "$uri could not be requested safely: the validated IP address " .
                                'cannot be pinned for this transport (cURL is unavailable, or a ' .
                                'stream-based transport was forced), which would otherwise reopen ' .
                                'a DNS-rebinding gap between validation and connection.'
                            );
                        }

                        $options['curl'][CURLOPT_RESOLVE] = array_merge(
                            $options['curl'][CURLOPT_RESOLVE] ?? [],
                            $this->buildCurlResolveEntries($host, $uri->getScheme(), $uri->getPort(), $validatedIps)
                        );
                    }
                }

                return $handler($request, $options);
            };
        });
    }

    /**
     * @param array<string, mixed> $options
     */
    private function canPinResolvedIps(array $options): bool
    {
        return defined('CURLOPT_RESOLVE')
            && function_exists('curl_version')
            && ($options['stream'] ?? false) !== true;
    }

    /**
     * @param array<int, string> $ips
     * @return array<int, string>
     */
    private function buildCurlResolveEntries(string $host, string $scheme, ?int $port, array $ips): array
    {
        $port ??= $scheme === 'https' ? 443 : 80;

        return array_map(
            fn (string $ip) => sprintf('%s:%d:%s', $host, $port, str_contains($ip, ':') ? "[$ip]" : $ip),
            $ips
        );
    }

    /**
     * The HTML meta parser only accepts valid URLs with the HTTP or HTTP protocols.
     *
     * @param string $url
     * @throws DisallowedIpException|InvalidUrlException
     */
    private function validateUrl(string $url): void
    {
        $invalidUri = filter_var($url, FILTER_VALIDATE_URL) === false;
        $unsupportedProtocol = !in_array(parse_url($url, PHP_URL_SCHEME), ['http', 'https']);

        if ($invalidUri || $unsupportedProtocol) {
            throw new InvalidUrlException("$url is not a valid URL to parse its HTML meta.");
        }

        if ($this->shouldBlockPrivateIps()) {
            $host = parse_url($url, PHP_URL_HOST);

            if (is_string($host)) {
                $host = $this->normalizeHost($host);
            }

            if (is_string($host) && filter_var($host, FILTER_VALIDATE_IP) !== false) {
                $this->assertHostUsesPublicIps($host, $url);
            }
        }
    }

    protected function shouldBlockPrivateIps(): bool
    {
        return config('html-meta.block_private_ips', false) === true;
    }

    /**
     * Kept with its original void signature for backwards compatibility:
     * this method is protected, so consuming applications may have
     * subclassed HtmlMeta and overridden it. Changing its return type would
     * be a breaking change for any such override (PHP requires overriding
     * methods to declare a compatible/covariant return type). New code
     * needing the resolved IPs should use resolveValidatedPublicIps().
     *
     * @throws DisallowedIpException
     */
    protected function assertHostUsesPublicIps(string $host, string $url): void
    {
        $this->resolveValidatedPublicIps($host, $url);
    }

    /**
     * @throws DisallowedIpException
     * @return array<int, string> The validated public IP(s) $host resolved to,
     *         so the caller can pin the connection to them (see
     *         applyPrivateIpProtection()) instead of letting cURL re-resolve
     *         the host on its own. Empty when $host was itself a literal IP,
     *         since no DNS lookup (and thus no rebinding) is involved there.
     */
    protected function resolveValidatedPublicIps(string $host, string $url): array
    {
        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            if (!$this->isPublicIp($host)) {
                throw new DisallowedIpException("$url points to a non-public IP address.");
            }

            return [];
        }

        $ips = $this->resolveHostIps($host);

        if ($ips === []) {
            // Fail closed: an unresolvable host cannot be proven public. Both
            // dns_get_record() and gethostbynamel() coming back empty used to
            // be silently treated as "no private IP found" (GHSA-x8w7-mhjm-xvj2).
            throw new DisallowedIpException("$url could not be resolved to a public IP address.");
        }

        foreach ($ips as $ip) {
            if (!$this->isPublicIp($ip)) {
                throw new DisallowedIpException("$url resolves to a non-public IP address.");
            }
        }

        return $ips;
    }

    /**
     * @return array<int, string>
     */
    protected function resolveHostIps(string $host): array
    {
        $ips = [];
        $records = @dns_get_record($host, DNS_A + DNS_AAAA);

        if (is_array($records)) {
            foreach ($records as $record) {
                if (isset($record['ip'])) {
                    $ips[] = $record['ip'];
                }

                if (isset($record['ipv6'])) {
                    $ips[] = $record['ipv6'];
                }
            }
        }

        if (empty($ips)) {
            $ipv4Addresses = @gethostbynamel($host);

            if (is_array($ipv4Addresses)) {
                $ips = $ipv4Addresses;
            }
        }

        return array_values(array_unique($ips));
    }

    protected function isPublicIp(string $ip): bool
    {
        return filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        ) !== false;
    }

    protected function normalizeHost(string $host): string
    {
        return trim($host, '[]');
    }
}
