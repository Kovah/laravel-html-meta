# Laravel HTML Meta Package

![Laravel Support: v9, v10, v11](https://img.shields.io/badge/Laravel%20Support-v9%2C%20v10%2C%20v11-blue) ![PHP Support: 8.x](https://img.shields.io/badge/PHP%20Support-8.0%2C%208.1%2C%208.2%2C%208.3%2C%208.4-blue)  
![GitHub release (latest by date)](https://img.shields.io/github/v/release/kovah/laravel-html-meta) ![GitHub Workflow Status (branch)](https://img.shields.io/github/actions/workflow/status/kovah/laravel-html-meta/test.yml?branch=main&label=Tests) ![GitHub](https://img.shields.io/github/license/kovah/laravel-html-meta)

This package provides a simple helper to retrieve the HTML meta tags of a URL. It properly handles connection and client errors and converts the meta tag contents from the source encoding to UTF-8 if possible.


## Installation & Usage

You can install this package via Composer:

```
composer require kovah/laravel-html-meta
```

Laravel automatically detects the package and makes it available in your application.


## Usage

The `HtmlMeta` class is available as a facade and exposes the `forUrl` function. Here is a very basic example.

```php
try {
    $metaTags = \Kovah\HtmlMeta\Facades\HtmlMeta::forUrl('https://kovah.de')->getMeta();
} catch (\Kovah\HtmlMeta\Exceptions\InvalidUrlException $e) {
    // the provided URL is invalid
} catch (\Kovah\HtmlMeta\Exceptions\UnreachableUrlException $e) {
    // the website under this URL is not reachable
}
```

The `$metaTags` variable now contains the following data:

```php
[
  "title" => "Kovah.de - Web Development and Photography",
  "generator" => "Hugo 0.58.2",
  "viewport" => "width=device-width, initial-scale=1",
  "description" => "Kovah - Web Development by Kevin Woblick",
  "og:title" => "Kovah.de - Web Development by Kevin Woblick",
  "og:description" => "Kovah - Web Development by Kevin Woblick",
  "og:image" => "'https://kovah.de/kvh_social_1200x630.jpg'",
  "og:url" => "'https://kovah.de/'/",
  "og:site_name" => "Portfolio of Kevin Woblick",
  "twitter:card" => "summary_large_image",
  // ...
];
```

If you want to use the response of the original request made to parse the HTML meta, you can get it with the `getResponse()` method like this:

```php
$metaResults = \Kovah\HtmlMeta\Facades\HtmlMeta::forUrl('https://kovah.de');

$response = $metaResults->getResponse(); // Illuminate\Http\Client\Response
$metaTags = $metaResults->getMeta(); // array
$url = $metaResults->getUrl(); // string
```

### Parsing HTML

As an alternative to fetching HTML from a URL, you might also parse HTML directly:

```php
$myHtml = '<!DOCTYPE html><head><meta name=" twitter:description " content="Text Value for Twitter Description"> ...';
$metaTags = \Kovah\HtmlMeta\Facades\HtmlMeta::fromHtml($myHtml)->getMeta();
```

To improve parsing and fallbacks, you might pass HTTP headers and the URL to the `fromHtml()` method like this:

```php
$myUrl = 'https://kovah.de';
$httpHeaders = [
    'content-type' => 'text/html; charset=iso-8859-1',
];
$myHtml = '<!DOCTYPE html><head><meta name=" twitter:description " content="Text Value for Twitter Description"> ...';
$metaTags = \Kovah\HtmlMeta\Facades\HtmlMeta::fromHtml($myHtml, $httpHeaders, $myUrl)->getMeta();
```


## Configuration

By default, the package uses a 10 seconds timeout when trying to fetch the content of the URL. If you want to increase or decrease this timeout, you can publish the HTML Meta configuration.

```
php artisan vendor:publish --provider="Kovah\HtmlMeta\HtmlMetaServiceProvider"
```

The configuration can now be found under `config/html-meta.php`.

### Setting a custom User Agent

The package allows you to set one or more custom User Agents which will be used to send the requests. The User Agent(s) you want to use must be specified as an array in the package configuration html-meta.php like this:

```php
'user_agents' => [
    'Mozilla/5.0 (Windows NT 6.4) AppleWebKit/537.36.0 (KHTML, like Gecko) Chrome/97.0.4692.71 Safari/537.36.0',
    'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_12) AppleWebKit/…4.1.28 (KHTML, like Gecko) Version/15.2.0 Safari/604.1.28',
    'Mozilla/5.0 (compatible; Googlebot/2.1.0; +http://www.google.com/bot.html)',
]
```

The HTML Meta package will randomly choose one of the User Agents for each request. If you want to use only one User Agent, remove all others from the list:

```php
'user_agents' => [
    'Mozilla/5.0 (Windows NT 6.4) AppleWebKit/537.36.0 (KHTML, like Gecko) Chrome/97.0.4692.71 Safari/537.36.0'
]
```

### Adding more custom headers

The custom_headers configuration can contain any headers that should be added to any request, except User-Agent and Accept. It can be either an array of headers, or a pipe-separated string.


```php
'custom_headers' => [
    'Accept-Encoding' => 'gzip, deflate',
    'referer' => 'https://example.com',
],
```

If headers are passed as a string, the following format applies: `[header name]=[value]|[header name]=[value]|...`

Note: Pipes inside the headers as a string must be escaped with a backslash.

```php
'custom_headers' => 'Accept-Encoding=gzip,deflate|referer=https://example.com'
```


## Parsing Details

The default parser shipping with this package extracts the meta tags from the HTML. These are the steps it is going through after the packages received a successful response:

- All meta tags with `name` or `property` properties are parsed from the `<head>` section. The keys are converted to lowercase.
- The `<title>` tag is parsed and all excessive white space is removed from the start and the end of it.
- The package checks for a charset, which can be specified as:
  - the HTML charset meta tag (`<meta charset="utf-8">`),
  - the HTTP content-type header (content-type: "text/html; charset=utf-8"),
  - or as the HTML http-equiv="content-type" tag (`<meta http-equiv="content-type" content="text/html;
  charset=utf-8">`) We try to parse the charset in this exact order.
- The value of all parsed meta tags is converted from the source charset (if available) to UTF-8, if it does not match UTF-8. **If the meta tag value cannot be converted, it is replaced by `null`!** The only exception is the title, which will be replaced by the hostname of the URL in case a conversion is not possible.
- HTML entities such as `&#8212;` are converted to the correct characters, in this example `—`.


---


This package is a project by [Kevin Woblick](https://kovah.de) and [Contributors](https://github.com/Kovah/laravel-html-meta/graphs/contributors)
