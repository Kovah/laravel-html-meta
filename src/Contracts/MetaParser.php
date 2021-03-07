<?php

namespace Kovah\HtmlMeta\Contracts;

use Illuminate\Http\Client\Response;

interface MetaParser
{
    public function parse(string $url, Response $response): array;
}
