<?php
return [

    'parser' => \Kovah\HtmlMeta\HtmlMetaParser::class,

    /* ------------------------------------------------------------------------
     * Base request configuration
     *
     * The timeout is specified in seconds.
     *
     * The user_agents option is an array that can hold multiple different
     * user agent strings. If more than one is specified, a random one will
     * be picked for every single request.
     */

    'timeout' => 10,

    'default_accept' => 'text/html',

    'user_agents' => [
        'Laravel HTML Meta/2 (https://github.com/Kovah/laravel-html-meta)'
    ],

    /* ------------------------------------------------------------------------
     * custom_headers can contain any headers that should be added to any
     * request, except User-Agent and Accept.
     * It can be either an array of headers, or a pipe-separated string. If
     * headers are passed as a string, the following format applies:
     * [header name]=[value]|[header name]=[value]|...
     * Pipes inside the headers as a string must be escaped with a backslash.
     *
     * 'custom_headers' => [
     *     'Accept-Encoding' => 'gzip, deflate',
     *     'referer' => 'https://example.com',
     * ],
     *
     * 'custom_headers' => 'Accept-Encoding=gzip,deflate|referer=https://example.com'
     */
    'custom_headers' => null,
];
