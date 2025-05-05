<?php

namespace Kovah\HtmlMeta;

use GuzzleHttp\Exception\RequestException as GuzzleRequestException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Kovah\HtmlMeta\Exceptions\InvalidUrlException;
use Kovah\HtmlMeta\Exceptions\UnreachableUrlException;

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
     * @throws InvalidUrlException|UnreachableUrlException
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
     * @throws UnreachableUrlException
     */
    private function fetchUrl(string $url): Response
    {
        $this->request = Http::timeout(config('html-meta.timeout', 10))
            ->accept(config('html-meta.default_accept', 'text/html'));

        $this->prepareHeaders();
        $this->prepareOptions();

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

    /**
     * The HTML meta parser only accepts valid URLs with the HTTP or HTTP protocols.
     *
     * @param string $url
     * @throws InvalidUrlException
     */
    private function validateUrl(string $url): void
    {
        $invalidUri = filter_var($url, FILTER_VALIDATE_URL) === false;
        $unsupportedProtocol = !in_array(parse_url($url, PHP_URL_SCHEME), ['http', 'https']);

        if ($invalidUri || $unsupportedProtocol) {
            throw new InvalidUrlException("$url is not a valid URL to parse its HTML meta.");
        }
    }
}
