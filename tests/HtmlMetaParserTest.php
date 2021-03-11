<?php

namespace Kovah\HtmlMeta\Tests;

use Illuminate\Support\Facades\Http;

class HtmlMetaParserTest extends TestCase
{
    /**
     * Test if the helper correctly trims whitespace off the meta keys.
     */
    public function testMetaFormattingWithMalformedTags(): void
    {
        $testHtml = '<!DOCTYPE html><head>' .
            '<meta name=" twitter:description " content="Text Value for Twitter Description">' .
            '</head></html>';

        Http::fake([
            '*' => Http::response($testHtml, 200),
        ]);

        $url = 'https://duckduckgo.com/';
        $meta = $this->app['HtmlMeta']->forUrl($url);

        self::assertArrayHasKey('twitter:description', $meta);
        self::assertEquals('Text Value for Twitter Description', $meta['twitter:description']);
    }

    /**
     * Test if the helper is able to convert a non-UTF-8 title into UTF-8.
     * hex2bin('3c7469746c653ecfe8eae0e1f33c2f7469746c653e') translates to
     * '<title>Пикабу</title>' in this case. 'Пикабу' must be correctly parsed
     * and converted into UTF-8 as the title.
     */
    public function testMetaEncodingWithCharsetAvailable(): void
    {
        $testHtml = '<!DOCTYPE html><head>' .
            '<meta charset="windows-1251">' .
            hex2bin('3c7469746c653ecfe8eae0e1f33c2f7469746c653e') .
            '</head></html>';

        Http::fake([
            '*' => Http::response($testHtml, 200),
        ]);

        $url = 'https://duckduckgo.com/';
        $meta = $this->app['HtmlMeta']->forUrl($url);

        self::assertArrayHasKey('title', $meta);
        self::assertEquals('Пикабу', $meta['title']);
    }

    /**
     * Test if the parser correctly discards a meta tag value if
     *  a) no charset meta tag is present and
     *  b) the tag value is detected as a non-UTF-8 string.
     *
     * The tested title must be the domain of the original URL.
     */
    public function testMetaEncodingWithCharsetMissing(): void
    {
        $testHtml = '<!DOCTYPE html><head>' .
            hex2bin('3c7469746c653ecfe8eae0e1f33c2f7469746c653e') .
            '</head></html>';

        Http::fake([
            '*' => Http::response($testHtml),
        ]);

        $url = 'https://duckduckgo.com/';
        $meta = $this->app['HtmlMeta']->forUrl($url);

        self::assertArrayHasKey('title', $meta);
        self::assertEquals('duckduckgo.com', $meta['title']);
    }

    /**
     * Test if the parser correctly discards meta tags if a charset is available,
     * but the charset is incorrect. A invalid charset cannot be used to parse
     * values of meta tags.
     */
    public function testMetaEncodingWithIncorrectCharset(): void
    {
        $testHtml = '<!DOCTYPE html><head>' .
            '<meta charset="utf-8,windows-1251">' .
            hex2bin('3c7469746c653ecfe8eae0e1f33c2f7469746c653e') .
            '</head></html>';

        Http::fake([
            '*' => Http::response($testHtml),
        ]);

        $url = 'https://duckduckgo.com/';
        $meta = $this->app['HtmlMeta']->forUrl($url);

        self::assertArrayHasKey('title', $meta);
        self::assertEquals('duckduckgo.com', $meta['title']);
    }

    /**
     * Test the HTML Meta helper function with a valid URL and the charset
     * defined in the content-type header.
     * The hex2bin('3c6d6574612...') translates to '<meta name="description" content="Qualität">'
     * in this case. 'Qualität' must be correctly parsed and converted into
     * UTF-8 as the description.
     */
    public function testMetaEncodingWithContentType(): void
    {
        $testHtml = '<!DOCTYPE html><head>' .
            hex2bin('3c6d657461206e616d653d226465736372697074696f6e2220636f6e74656e743d225175616c6974e474223e') .
            '</head></html>';

        Http::fake([
            '*' => Http::response($testHtml, 200, [
                'Content-Type' => 'text/html; charset=iso-8859-1',
            ]),
        ]);

        $url = 'https://encoding-test.com/';
        $meta = $this->app['HtmlMeta']->forUrl($url);

        self::assertArrayHasKey('description', $meta);
        self::assertEquals('Qualität', $meta['description']);
    }

    /**
     * Test the HTML Meta helper function with a valid URL and the charset
     * defined in the content-type header.
     * The hex2bin('3c6d6574612...') translates to '<meta name="description" content="Qualität">'
     * in this case. 'Qualität' must be correctly parsed and converted into
     * UTF-8 as the description.
     */
    public function testMetaEncodingWithContentTypeInHtml(): void
    {
        $testHtml = '<!DOCTYPE html><head>' .
            hex2bin('3c6d657461206e616d653d226465736372697074696f6e2220636f6e74656e743d225175616c6974e474223e') .
            '<meta http-equiv="content-type" content="text/html; charset=iso-8859-1">' .
            '</head></html>';

        Http::fake([
            '*' => Http::response($testHtml),
        ]);

        $url = 'https://encoding-test.com/';
        $meta = $this->app['HtmlMeta']->forUrl($url);

        self::assertArrayHasKey('description', $meta);
        self::assertEquals('Qualität', $meta['description']);
    }
}
