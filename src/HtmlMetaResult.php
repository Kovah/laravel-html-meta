<?php

namespace Kovah\HtmlMeta;

use Illuminate\Http\Client\Response;

class HtmlMetaResult
{
    /** @var string */
    protected $url;

    /** @var Response */
    protected $response;

    /** @var array */
    protected $meta;

    public function __construct(string $url, Response $response, array $meta)
    {
        $this->url = $url;
        $this->response = $response;
        $this->meta = $meta;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function getMeta(): array
    {
        return $this->meta;
    }

    public function getResponse(): Response
    {
        return $this->response;
    }
}
