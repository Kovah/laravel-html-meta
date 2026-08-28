<?php

namespace Kovah\HtmlMeta\Tests;

use GuzzleHttp\Exception\RequestException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Kovah\HtmlMeta\Exceptions\DisallowedIpException;
use Kovah\HtmlMeta\Exceptions\InvalidUrlException;
use Kovah\HtmlMeta\Exceptions\UnreachableUrlException;
use Kovah\HtmlMeta\HtmlMeta;
use Kovah\HtmlMeta\HtmlMetaParser;
use Kovah\HtmlMeta\HtmlMetaResult;
use PHPUnit\Framework\Attributes\DataProvider;

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
        $result = $this->app['HtmlMeta']->forUrl($url);

        self::assertTrue(is_a($result, HtmlMetaResult::class));
        self::assertArrayHasKey('title', $result->getMeta());
        self::assertEquals('Test Title', $result->getMeta()['title']);
        self::assertTrue(is_a($result->getResponse(), \Illuminate\Http\Client\Response::class));
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
        Http::fake(function () {
            throw new ConnectionException(
                'cURL error 7: Failed to connect to 192.168.0.123 port 54623: Connection refused'
            );
        });

        $this->expectException(UnreachableUrlException::class);

        $url = 'https://unreachable-website.com/';
        $this->app['HtmlMeta']->forUrl($url);
    }

    /**
     * Tests the correct addition of a user agent to the request.
     */
    public function testWithCustomUserAgent(): void
    {
        Http::fake(['*' => Http::response()]);

        config()->set('html-meta.user_agents', [
            'My Custom User-Agent',
        ]);

        $this->app['HtmlMeta']->forUrl('https://example.com');

        Http::assertSent(function (Request $request) {
            return $request->hasHeader('User-Agent', 'My Custom User-Agent');
        });
    }

    /**
     * Tests the correct addition of custom headers to a requests, if specified
     * as an array.
     */
    public function testWithCustomHeaders(): void
    {
        Http::fake(['*' => Http::response()]);

        config()->set('html-meta.custom_headers', [
            'Accept-Encoding' => 'gzip,deflate',
            'Cache-Control' => 'no-cache',
        ]);

        $this->app['HtmlMeta']->forUrl('https://example.com');

        Http::assertSent(function (Request $request) {
            return $request->hasHeader('Accept-Encoding', 'gzip,deflate') &&
                $request->hasHeader('Cache-Control', 'no-cache');
        });
    }

    /**
     * Tests the correct addition of custom headers to a requests, if specified
     * as a string.
     */
    public function testWithCustomHeadersAsString(): void
    {
        Http::fake(['*' => Http::response()]);

        // Test a correctly configured string
        config()->set('html-meta.custom_headers', 'Accept-Encoding=gzip,deflate|Cache-Control=no-cache');

        $this->app['HtmlMeta']->forUrl('https://example.com');

        Http::assertSent(function (Request $request) {
            return $request->hasHeader('Accept-Encoding', 'gzip,deflate') &&
                $request->hasHeader('Cache-Control', 'no-cache');
        });

        // Test a deformed string
        config()->set('html-meta.custom_headers', 'Accept-Encoding:gzip,deflate| ');

        $this->app['HtmlMeta']->forUrl('https://example.com');

        Http::assertSent(function (Request $request) {
            return !$request->hasHeader('Accept-Encoding', 'gzip,deflate');
        });
    }

    /**
     * Tests the correct addition of custom options to a requests, if specified
     * as an array. The query ?foo=bar must be added to the URI and the redirect
     * must not be followed.
     */
    public function testWithCustomOptions(): void
    {
        Http::fake([
            'https://example.com' => Http::response(status: 404),
            'https://example.com?foo=bar' => Http::response(status: 302, headers: ['location' => 'https://example.com/about']),
            'https://example.com/about' => Http::response('Hi'),
        ]);

        config()->set('html-meta.custom_options', [
            'allow_redirects' => false,
            'query' => ['foo' => 'bar'],
        ]);

        $response = $this->app['HtmlMeta']->forUrl('https://example.com')->getResponse();

        $this->assertEquals(302, $response->status());
    }

    public function testPrivateIpProtectionIsDisabledByDefault(): void
    {
        Http::fake(['*' => Http::response('<title>Test</title>')]);

        $result = $this->app['HtmlMeta']->forUrl('http://192.168.0.10');

        self::assertTrue(is_a($result, HtmlMetaResult::class));
    }

    public function testPrivateIpv4UrlIsBlockedWhenProtectionIsEnabled(): void
    {
        Http::fake(['*' => Http::response('<title>Test</title>')]);

        config()->set('html-meta.block_private_ips', true);

        $this->expectException(DisallowedIpException::class);

        try {
            $this->app['HtmlMeta']->forUrl('http://192.168.0.10');
        } finally {
            Http::assertNothingSent();
        }
    }

    public function testReservedIpv6UrlIsBlockedWhenProtectionIsEnabled(): void
    {
        Http::fake(['*' => Http::response('<title>Test</title>')]);

        config()->set('html-meta.block_private_ips', true);

        $this->expectException(DisallowedIpException::class);

        try {
            $this->app['HtmlMeta']->forUrl('http://[::1]');
        } finally {
            Http::assertNothingSent();
        }
    }

    public function testPublicIpUrlIsAllowedWhenProtectionIsEnabled(): void
    {
        Http::fake(['*' => Http::response('<title>Test</title>')]);

        config()->set('html-meta.block_private_ips', true);

        $result = $this->app['HtmlMeta']->forUrl('http://8.8.8.8');

        self::assertTrue(is_a($result, HtmlMetaResult::class));
    }

    public function testHostnameResolvingToPrivateIpIsBlockedWhenProtectionIsEnabled(): void
    {
        Http::fake(['*' => Http::response('<title>Test</title>')]);

        config()->set('html-meta.block_private_ips', true);

        $meta = new class(new HtmlMetaParser()) extends HtmlMeta {
            protected function resolveHostIps(string $host): array
            {
                return $host === 'example.com' ? ['192.168.1.10'] : [];
            }
        };

        $this->expectException(DisallowedIpException::class);

        try {
            $meta->forUrl('https://example.com');
        } finally {
            Http::assertNothingSent();
        }
    }

    public function testHostnameResolvingToPublicIpsIsAllowedWhenProtectionIsEnabled(): void
    {
        Http::fake(['*' => Http::response('<title>Test</title>')]);

        config()->set('html-meta.block_private_ips', true);

        $meta = new class(new HtmlMetaParser()) extends HtmlMeta {
            protected function resolveHostIps(string $host): array
            {
                return $host === 'example.com' ? ['8.8.8.8', '2001:4860:4860::8888'] : [];
            }
        };

        $result = $meta->forUrl('https://example.com');

        self::assertTrue(is_a($result, HtmlMetaResult::class));
    }

    /**
     * Regression test for GHSA-x8w7-mhjm-xvj2. Previously, if resolveHostIps()
     * returned an empty array (e.g. because both dns_get_record() and
     * gethostbynamel() failed to resolve the host), the validating loop in
     * assertHostUsesPublicIps() had nothing to iterate over and silently let
     * the request through. An unresolvable host must now be treated as
     * disallowed instead.
     */
    public function testHostnameIsBlockedWhenDnsResolutionReturnsNoRecords(): void
    {
        Http::fake(['*' => Http::response('<title>Test</title>')]);

        config()->set('html-meta.block_private_ips', true);

        $meta = new class(new HtmlMetaParser()) extends HtmlMeta {
            protected function resolveHostIps(string $host): array
            {
                return [];
            }
        };

        $this->expectException(DisallowedIpException::class);

        try {
            $meta->forUrl('https://unresolvable-internal.example');
        } finally {
            Http::assertNothingSent();
        }
    }

    /**
     * The private-IP protection is also exposed publicly via
     * applyPrivateIpProtection() so consuming applications can attach it to
     * a request they build and send themselves, instead of reimplementing
     * the validation logic (see GHSA-x8w7-mhjm-xvj2).
     */
    public function testApplyPrivateIpProtectionBlocksAnArbitraryRequestToAPrivateHost(): void
    {
        config()->set('html-meta.block_private_ips', true);

        $request = $this->app['HtmlMeta']->applyPrivateIpProtection(
            Http::timeout(5)
        );

        Http::fake(['*' => Http::response()]);

        $this->expectException(DisallowedIpException::class);

        try {
            $request->get('http://192.168.0.10');
        } finally {
            Http::assertNothingSent();
        }
    }

    /**
     * Validating the hostname alone is a TOCTOU gap: cURL re-resolves the
     * host itself at connect time, independently of the lookup used for
     * validation. An attacker with authoritative, short-TTL DNS for the host
     * can answer with a public IP for our lookup and a private/loopback one
     * moments later for cURL's own lookup (DNS rebinding). The fix pins the
     * connection to the IP(s) that were actually validated via
     * CURLOPT_RESOLVE, so this asserts the outgoing transfer options carry
     * that pin rather than just checking the request URI's host is unchanged
     * (which would be true both before and after the fix, and therefore
     * would not catch a regression).
     */
    public function testApplyPrivateIpProtectionPinsTheValidatedIpViaCurlResolve(): void
    {
        config()->set('html-meta.block_private_ips', true);

        $meta = new class(new HtmlMetaParser()) extends HtmlMeta {
            protected function resolveHostIps(string $host): array
            {
                return $host === 'example.com' ? ['8.8.8.8'] : [];
            }
        };

        $request = $meta->applyPrivateIpProtection(Http::timeout(5));

        $middlewareProperty = new \ReflectionProperty($request, 'middleware');
        $middlewareProperty->setAccessible(true);
        $middleware = $middlewareProperty->getValue($request)->last();

        $capturedOptions = null;
        $handler = $middleware(function ($request, array $options) use (&$capturedOptions) {
            $capturedOptions = $options;

            return new \GuzzleHttp\Promise\FulfilledPromise(
                new \GuzzleHttp\Psr7\Response(200)
            );
        });

        $handler(new \GuzzleHttp\Psr7\Request('GET', 'https://example.com/'), []);

        self::assertSame(
            ['example.com:443:8.8.8.8'],
            $capturedOptions['curl'][CURLOPT_RESOLVE] ?? null
        );
    }

    /**
     * Multiple validated IPs (e.g. IPv4 + IPv6) must all be pinned, with
     * IPv6 addresses bracketed as libcurl requires for CURLOPT_RESOLVE.
     */
    public function testApplyPrivateIpProtectionPinsAllValidatedIpsIncludingIpv6(): void
    {
        config()->set('html-meta.block_private_ips', true);

        $meta = new class(new HtmlMetaParser()) extends HtmlMeta {
            protected function resolveHostIps(string $host): array
            {
                return $host === 'example.com' ? ['8.8.8.8', '2001:4860:4860::8888'] : [];
            }
        };

        $request = $meta->applyPrivateIpProtection(Http::timeout(5));

        $middlewareProperty = new \ReflectionProperty($request, 'middleware');
        $middlewareProperty->setAccessible(true);
        $middleware = $middlewareProperty->getValue($request)->last();

        $capturedOptions = null;
        $handler = $middleware(function ($request, array $options) use (&$capturedOptions) {
            $capturedOptions = $options;

            return new \GuzzleHttp\Promise\FulfilledPromise(
                new \GuzzleHttp\Psr7\Response(200)
            );
        });

        $handler(new \GuzzleHttp\Psr7\Request('GET', 'http://example.com/'), []);

        self::assertSame(
            ['example.com:80:8.8.8.8', 'example.com:80:[2001:4860:4860::8888]'],
            $capturedOptions['curl'][CURLOPT_RESOLVE] ?? null
        );
    }

    /**
     * CURLOPT_RESOLVE only has an effect when Guzzle actually dispatches the
     * request through its cURL handler. A request-level "stream" => true
     * option forces Guzzle's StreamHandler instead, which silently ignores
     * curl options - reopening the DNS-rebinding gap with no error. This
     * must fail closed rather than send an unpinned request.
     */
    public function testApplyPrivateIpProtectionFailsClosedWhenStreamTransportIsForced(): void
    {
        config()->set('html-meta.block_private_ips', true);

        $meta = new class(new HtmlMetaParser()) extends HtmlMeta {
            protected function resolveHostIps(string $host): array
            {
                return $host === 'example.com' ? ['8.8.8.8'] : [];
            }
        };

        $request = $meta->applyPrivateIpProtection(Http::timeout(5));

        $middlewareProperty = new \ReflectionProperty($request, 'middleware');
        $middlewareProperty->setAccessible(true);
        $middleware = $middlewareProperty->getValue($request)->last();

        $handler = $middleware(function ($request, array $options) {
            return new \GuzzleHttp\Promise\FulfilledPromise(
                new \GuzzleHttp\Psr7\Response(200)
            );
        });

        $this->expectException(DisallowedIpException::class);

        $handler(new \GuzzleHttp\Psr7\Request('GET', 'https://example.com/'), ['stream' => true]);
    }

    /**
     * Regression test for GHSA-h73m-vm5m-f6h3. IPv6 transition mechanisms
     * (NAT64, 6to4, Teredo) carry an IPv4 address embedded inside an
     * otherwise "public-looking" IPv6 address. filter_var()'s range flags
     * only inspect the IPv6 address itself, so a private/link-local IPv4
     * (here, the 169.254.169.254 cloud metadata address) embedded this way
     * used to slip through isPublicIp() and reach the network.
     *
     */
    #[DataProvider('transitionAddressesEmbeddingPrivateIpv4')]
    public function testIpv6TransitionAddressEmbeddingPrivateIpv4IsBlockedWhenProtectionIsEnabled(string $ip): void
    {
        Http::fake(['*' => Http::response('<title>Test</title>')]);

        config()->set('html-meta.block_private_ips', true);

        $this->expectException(DisallowedIpException::class);

        try {
            $this->app['HtmlMeta']->forUrl("http://[$ip]");
        } finally {
            Http::assertNothingSent();
        }
    }

    public static function transitionAddressesEmbeddingPrivateIpv4(): array
    {
        return [
            'NAT64 embedding cloud metadata IP' => ['64:ff9b::a9fe:a9fe'],
            '6to4 embedding a private IP' => ['2002:c0a8:0001::1'],
            'Teredo embedding a private IP' => ['2001::3f57:fefe'],
        ];
    }

    /**
     * Genuinely public IPv6 addresses using the same transition mechanisms
     * must still be allowed through, to guard against over-blocking.
     *
     */
    #[DataProvider('transitionAddressesEmbeddingPublicIpv4')]
    public function testIpv6TransitionAddressEmbeddingPublicIpv4IsAllowedWhenProtectionIsEnabled(string $ip): void
    {
        Http::fake(['*' => Http::response('<title>Test</title>')]);

        config()->set('html-meta.block_private_ips', true);

        $result = $this->app['HtmlMeta']->forUrl("http://[$ip]");

        self::assertTrue(is_a($result, HtmlMetaResult::class));
    }

    public static function transitionAddressesEmbeddingPublicIpv4(): array
    {
        return [
            'NAT64 embedding a public IP' => ['64:ff9b::808:808'],
            '6to4 embedding a public IP' => ['2002:0808:0808::1'],
        ];
    }

    /**
     * Regression test for a BC break risk: assertHostUsesPublicIps() is
     * protected, so consuming applications may already have subclassed
     * HtmlMeta and overridden it with its original `void` return type. If
     * the base method's signature changed to `array`, PHP would raise a
     * fatal "declaration must be compatible" error as soon as such a
     * subclass is defined. Defining and exercising one here, using the
     * original void signature, proves the base signature is unchanged and
     * that the override is still consulted.
     */
    public function testSubclassOverridingAssertHostUsesPublicIpsWithOriginalVoidSignatureStillWorks(): void
    {
        config()->set('html-meta.block_private_ips', true);

        $meta = new class(new HtmlMetaParser()) extends HtmlMeta {
            public bool $overrideWasCalled = false;

            protected function assertHostUsesPublicIps(string $host, string $url): void
            {
                $this->overrideWasCalled = true;

                parent::assertHostUsesPublicIps($host, $url);
            }
        };

        $this->expectException(DisallowedIpException::class);

        try {
            $meta->forUrl('http://192.168.0.10');
        } finally {
            self::assertTrue($meta->overrideWasCalled);
        }
    }
}
