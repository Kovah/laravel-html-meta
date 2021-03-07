<?php

namespace Kovah\HtmlMeta;

use GuzzleHttp\Exception\RequestException as GuzzleRequestException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Kovah\HtmlMeta\Exceptions\InvalidUrlException;
use Kovah\HtmlMeta\Exceptions\UnreachableUrlException;

class HtmlMeta
{
    /** @var HtmlMetaParser * */
    private $parser;

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
     * @return array
     * @throws InvalidUrlException|UnreachableUrlException
     */
    public function forUrl(string $url): array
    {
        $this->validateUrl($url);

        $response = $this->fetchUrl($url);

        return $this->parser->parse($url, $response);
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
        try {
            return Http::timeout(config('html-meta.timeout', 10))->get($url)->throw();
        } catch (ConnectionException | GuzzleRequestException | RequestException $e) {
            throw new UnreachableUrlException("$url is not reachable. " . $e->getMessage());
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
