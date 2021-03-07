# Laravel HTML Meta Package

Supports: Laravel 7, 8

This package provides a simple helper to retrieve the HTML meta tags of a URL. It properly handles connection and client errors and converts the meta tag contents from the source encoding to UTF-8 if possible.


## Installation

You can install this package via Composer:

```
composer require kovah/laravel-html-meta
```

Laravel automatically detects the package and makes it available in your application.


## Usage

The `HtmlMeta` class is available as a facade and exposes the `forUrl` function. Here is a very basic example.

```php
try {
    $metaTags = \Kovah\HtmlMeta\Facades\HtmlMeta::forUrl('https://kovah.de');
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


## Configuration

By default, the package uses a 10 seconds timeout when trying to fetch the content of the URL. If you want to increase or decrease this timeout, you can publish the HTML Meta configuration.

```
php artisan vendor:publish --provider="Kovah\HtmlMeta\HtmlMetaServiceProvider"
```

The configuration can now be found under `config/html-meta.php`.


## Parsing Details

The default parser shipping with this package extracts the meta tags from the HTML. These are the steps it is going through after the packages received a successful response:

- All meta tags with `name` or `property` properties are parsed from the `<head>` section. The keys are converted to lowercase.
- The `<title>` tag is parsed and all excessive white space is removed from the start and the end of it.
- The package checks for a charset, which can be specified as:
  - the HTML charset meta tag (<meta charset="utf-8">),
  - the HTTP content-type header (content-type: "text/html; charset=utf-8"),
  - or as the HTML http-equiv="content-type" tag (<meta http-equiv="content-type" content="text/html;
  charset=utf-8">) We try to parse the charset in this exact order.
- The value of all parsed meta tags is converted from the source charset (if available) to UTF-8, if it does not match UTF-8. **If the meta tag value cannot be converted, it is replaced by `null`!** The only exception is the title, which will be replaced by the hostname of the URL in case a conversion is not possible.


---


This package is a project by [Kovah](https://kovah.de) | [Contributors](https://github.com/Kovah/laravel-html-meta/graphs/contributors)
