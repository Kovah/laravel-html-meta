<?php

namespace Kovah\HtmlMeta\Facades;

use Illuminate\Support\Facades\Facade;

class HtmlMeta extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'html-meta';
    }
}
