<?php

namespace Kovah\HtmlMeta\Tests;

use GuzzleHttp\Exception\RequestException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Kovah\HtmlMeta\Exceptions\InvalidUrlException;
use Kovah\HtmlMeta\Exceptions\UnreachableUrlException;

class HtmlMetaTest extends TestCase
{
    /**
     * Tests the correct parsing of meta tags from regular, UTF-8-compatible
     * strings.
     */
    public function testMetaFromValidUrl(): void
    {
        $testHtml = '<!DOCTYPE html><head>' .
            '<title>Test Title</title>' .
            '<meta name="foo" content="bar">' .
            '<meta name="description" content="This an example description">' .
            '</head></html>';

        Http::fake([
            '*' => Http::response($testHtml),
        ]);

        $url = 'https://test.com/';
        $meta = $this->app['HtmlMeta']->forUrl($url);

        self::assertArrayHasKey('title', $meta);
        self::assertEquals('Test Title', $meta['title']);
    }

    /**
     * Test a URL that is not valid, because its protocol is missing.
     */
    public function testUrlWithoutProtocol(): void
    {
        Http::fake([
            '*' => Http::response(null, 404),
        ]);

        $url = 'duckduckgo.com/about-us';

        $this->expectException(InvalidUrlException::class);
        $this->app['HtmlMeta']->forUrl($url);
    }

    /**
     * Test a URL that is not valid, because its protocol is not supported.
     */
    public function testTitleFromUrlWithInvalidProtocol(): void
    {
        Http::fake([
            '*' => Http::response(null, 404),
        ]);

        $url = 's3://example-bucket/test.jpg';

        $this->expectException(InvalidUrlException::class);
        $this->app['HtmlMeta']->forUrl($url);
    }

    /**
     * Test a URL that returns a server or client error, in this case a 404.
     * The page cannot be parsed, so the helper throws an error.
     */
    public function testUnreachableUrlWithClientError(): void
    {
        Http::fake([
            '*' => Http::response(null, 404),
        ]);

        $this->expectException(UnreachableUrlException::class);

        $url = 'https://duckduckgogo.comcom/';
        $this->app['HtmlMeta']->forUrl($url);
    }

    /**
     * Test a URL that cannot be reached because a request error occurred.
     */
    public function testRequestError(): void
    {
        Http::fake(function (Request $request) {
            throw new RequestException(
                'cURL error 60: SSL certificate problem: self signed certificate',
                new \GuzzleHttp\Psr7\Request('get', $request->url())
            );
        });

        $this->expectException(UnreachableUrlException::class);

        $url = 'https://self-signed.badssl.com/';
        $this->app['HtmlMeta']->forUrl($url);
    }

    /**
     * Test a URL that cannot be reached because a connection exception occurred.
     */
    public function testConnectionError(): void
    {
        Http::fake(function (Request $request) {
            throw new ConnectionException(
                'cURL error 7: Failed to connect to 192.168.0.123 port 54623: Connection refused'
            );
        });

        $this->expectException(UnreachableUrlException::class);

        $url = 'https://unreachable-website.com/';
        $this->app['HtmlMeta']->forUrl($url);
    }
}
