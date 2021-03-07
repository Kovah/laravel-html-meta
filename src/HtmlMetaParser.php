<?php

namespace Kovah\HtmlMeta;

use Illuminate\Http\Client\Response;
use Kovah\HtmlMeta\Contracts\MetaParser;

class HtmlMetaParser implements MetaParser
{
    /** @var array */
    protected $metaTags;

    /** @var array */
    protected $fallback;

    /** @var string|null */
    protected $charSet;

    /**
     * Returns an array containing all meta tags parsed from the given HTML.
     *
     * @param string   $url
     * @param Response $response
     * @return array
     */
    public function parse(string $url, Response $response): array
    {
        $this->generateFallback($url, $response);

        $this->metaTags = $this->getMetaTags($response->body());

        $this->detectCharSet($response);
        $this->encodeMetaTags();

        return $this->metaTags;
    }

    /**
     * Set some fallbacks for meta tags.
     *
     * @param string   $url
     * @param Response $response
     */
    protected function generateFallback(string $url, Response $response): void
    {
        $this->fallback = [
            'title' => parse_url($url, PHP_URL_HOST),
        ];
    }

    /**
     * Parses the meta tags from HTML by using a specific regex.
     * Returns an array of all found meta tags or an empty array if no tags were found.
     *
     * @param string $html
     * @return array
     */
    protected function getMetaTags(string $html): array
    {
        $tags = [];
        $pattern = '/<[\s]*meta[\s]*(name|property)="?([^>"]*)"?[\s]*content="?([^>"]*)"?[\s]*[\/]?[\s]*>/i';

        if (preg_match_all($pattern, $html, $out)) {
            $keys = array_map('strtolower', $out[2]);
            $tags = array_combine($keys, $out[3]);
        }

        // Parse the title
        $res = preg_match("/<title>(.*)<\/title>/siU", $html, $titleMatches);

        if ($res) {
            $tags['title'] = trim(preg_replace('/\s+/', ' ', $titleMatches[1]));
        } else {
            $tags['title'] = $this->fallback['title'];
        }

        return $tags;
    }

    /**
     * To be able to correctly encode the meta tags in case a non-UTF-8 charset
     * is used, we need to find out the currently used charset. If is either
     * specified as:
     * - the HTML charset meta tag (<meta charset="utf-8">)
     * - the HTTP content-type header (content-type: "text/html; charset=utf-8")
     * - or as the HTML http-equiv="content-type" tag (<meta http-equiv="content-type" content="text/html;
     * charset=utf-8">) We try to parse the charset in this exact order.
     *
     * @param Response $response
     */
    protected function detectCharSet(Response $response): void
    {
        // Try to find the meta charset tag and get its content
        $pattern = '/<[\s]*meta[\s]*(charset)="?([^>"]*)"?[\s]*>/i';

        if (preg_match($pattern, $response->body(), $out)) {
            $this->charSet = strtolower($out[2]);
            return;
        }

        // Check if a content-type HTTP header is present and try to parse the content type from it
        if (strpos($response->header('content-type'), 'charset=') !== false) {
            $this->charSet = explode('charset=', $response->header('content-type'))[1];
            return;
        }

        // Last chance: check if a http-equiv="content-type" tag is present and try to parse it
        $pattern = '/<[\s]*meta[\s]*(http-equiv)="content-type"?[\s]*content="?([^>"]*)"?[\s]*[\/]?[\s]*>/i';

        if (preg_match($pattern, $response->body(), $out) && isset($out[2])) {
            $this->charSet = explode('charset=', strtolower($out[2]))[1] ?? null;
        }
    }

    /**
     * If a charset meta tag was found and it does not contain UTF-8 as a value,
     * the method tries to convert tag values from the given charset into UTF-8.
     * If it fails, it returns null because we most likely can't generate any
     * useful information here.
     *
     * If no charset is available, the method will check if the tag is encoded
     * as UTF-8. If it does not pass the check, the value will be set to null as
     * we will not be able to get any correctly encoded information from the
     * strings.
     */
    protected function encodeMetaTags(): void
    {
        foreach ($this->metaTags as $tag => $content) {
            if ($this->charSet !== 'utf-8') {
                try {
                    $this->metaTags[$tag] = iconv($this->charSet, 'UTF-8', $content) ?: null;
                } catch (\ErrorException $e) {
                    $this->metaTags[$tag] = $this->fallback[$tag] ?? null;
                }
            } elseif (mb_detect_encoding($content, 'UTF-8', true) === false) {
                $this->metaTags[$tag] = $this->fallback[$tag] ?? null;
            }
        }
    }
}
