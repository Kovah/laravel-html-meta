# Changelog

## 2.2.0

- Add compatibility with Laravel 10 and PHP 8.2.
- Add option to add custom headers via the configuration. See [the config file](./config/html-meta.php) for more details.

## 2.1.1

- Add compatibility with Laravel 9.

## 2.1.0

- This package now requires at least PHP 7.4, but should be compatible with PHP 8.1 already.
- Adds a new configuration option to use custom User Agents while doing the requests.

## 2.0.0

- `HtmlMeta::forUrl('https://kovah.de')` now returns an `Kovah\HtmlMeta\HtmlMetaResult` object that holds the URI, original response and the meta.
  You can migrate your existing implementation by replacing `HtmlMeta::forUrl('https://kovah.de')` with `HtmlMeta::forUrl('https://kovah.de')->getMeta()`.

## 1.1.1

- Fixes handling of empty meta tags, empty meta tags will be `null` in the resulting array.

## 1.1.0

- The meta parser now converts HTML entities such as `&#8212;` to the correct characters, in this example `—`.

## 1.0.1

- Whitespace is now stripped off the start and end of meta tag names and properties.

## 1.0.0

- Initial release.
