<?php

namespace Kovah\HtmlMeta\Facades;

use Illuminate\Support\Facades\Facade;
use Kovah\HtmlMeta\HtmlMetaResult;

/**
 * @method static HtmlMetaResult forUrl(string $url)
 *
 * @see \Kovah\HtmlMeta\HtmlMeta
 */
class HtmlMeta extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'html-meta';
    }
}
