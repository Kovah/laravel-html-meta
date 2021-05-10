# Changelog

## 2.0.0

- `HtmlMeta::forUrl('https://kovah.de')` now returns an `` object, which holds  the URI, original response and the meta.
  You can migrate your existing implementation by replacing `HtmlMeta::forUrl('https://kovah.de')` with `HtmlMeta::forUrl('https://kovah.de')->getMeta()`.

## 1.1.1

- Fixes handling of empty meta tags, empty meta tags will be `null` in the resulting array.

## 1.1.0

- The meta parser now converts HTML entities such as `&#8212;` to the correct characters, in this example `—`.

## 1.0.1

- Whitespace is now stripped off the start and end of meta tag names and properties.

## 1.0.0

- Initial release.
