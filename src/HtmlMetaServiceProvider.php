<?php

namespace Kovah\HtmlMeta;

use Illuminate\Contracts\Container\Container;
use Illuminate\Support\ServiceProvider as LaravelServiceProvider;
use Kovah\HtmlMeta\Contracts\MetaParser;

class HtmlMetaServiceProvider extends LaravelServiceProvider
{
    public function register(): void
    {
        $configPath = __DIR__ . '/../config/html-meta.php';
        $this->mergeConfigFrom($configPath, 'html-meta');

        $this->app->bind(MetaParser::class, function (Container $app) {
            return $app->make(config('html-meta.parser'));
        });

        $this->app->singleton('html-meta', HtmlMeta::class);
        $this->app->alias(HtmlMeta::class, 'html-meta');
    }

    public function boot(): void
    {
        $configPath = __DIR__ . '/../config/html-meta.php';
        $this->publishes([$configPath => $this->getConfigPath()], 'config');
    }

    protected function getConfigPath(): string
    {
        return config_path('html-meta.php');
    }
}
