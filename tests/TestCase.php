<?php

namespace Kovah\HtmlMeta\Tests;

use Illuminate\Support\Facades\Http;
use Kovah\HtmlMeta\HtmlMeta;
use Kovah\HtmlMeta\HtmlMetaServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app)
    {
        return [
            HtmlMetaServiceProvider::class,
        ];
    }

    protected function getPackageAliases($app)
    {
        return [
            'HtmlMeta' => HtmlMeta::class,
        ];
    }

    protected function getEnvironmentSetUp($app)
    {
        config()->set('html-meta.timeout', 10);

        config()->set('app.key', 'base64:y1f1BJ742PqhmoZZtALeY8RPs+M2+CRtLslEcgLlLXM=');

        Http::preventStrayRequests();
    }
}
