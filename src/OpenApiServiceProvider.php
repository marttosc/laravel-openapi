<?php

declare(strict_types=1);

namespace Vyuldashev\LaravelOpenApi;

use Doctrine\Common\Annotations\AnnotationRegistry;
use Illuminate\Support\Collection;
use Illuminate\Support\ServiceProvider;
use Vyuldashev\LaravelOpenApi\Builders\Components\CallbacksBuilder;
use Vyuldashev\LaravelOpenApi\Builders\Components\RequestBodiesBuilder;
use Vyuldashev\LaravelOpenApi\Builders\Components\ResponsesBuilder;
use Vyuldashev\LaravelOpenApi\Builders\Components\SchemasBuilder;
use Vyuldashev\LaravelOpenApi\Builders\Components\SecuritySchemesBuilder;
use Vyuldashev\LaravelOpenApi\Builders\ComponentsBuilder;
use Vyuldashev\LaravelOpenApi\Builders\InfoBuilder;
use Vyuldashev\LaravelOpenApi\Builders\PathsBuilder;
use Vyuldashev\LaravelOpenApi\Builders\ServersBuilder;
use Vyuldashev\LaravelOpenApi\Builders\TagsBuilder;

class OpenApiServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/openapi.php' => config_path('openapi.php'),
            ], 'openapi-config');
        }

        $this->registerAnnotations();

        CallbacksBuilder::in($this->callbacksIn());
        RequestBodiesBuilder::in($this->requestBodiesIn());
        ResponsesBuilder::in($this->responsesIn());
        SchemasBuilder::in($this->schemasIn());
        SecuritySchemesBuilder::in($this->securitySchemesIn());

        $this->app->singleton(Generator::class, static function ($app) {
            $config = config('openapi');

            return new Generator(
                $config,
                $app[InfoBuilder::class],
                $app[ServersBuilder::class],
                $app[TagsBuilder::class],
                $app[PathsBuilder::class],
                $app[ComponentsBuilder::class]
            );
        });

        $this->loadRoutesFrom(__DIR__.'/../routes/api.php');
    }

    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/openapi.php',
            'openapi'
        );

        $this->commands([
            Console\GenerateCommand::class,
        ]);

        if ($this->app->runningInConsole()) {
            $this->commands([
                Console\CallbackFactoryMakeCommand::class,
                Console\ExtensionFactoryMakeCommand::class,
                Console\ParametersFactoryMakeCommand::class,
                Console\RequestBodyFactoryMakeCommand::class,
                Console\ResponseFactoryMakeCommand::class,
                Console\SchemaFactoryMakeCommand::class,
                Console\SecuritySchemeFactoryMakeCommand::class,
            ]);
        }
    }

    protected function registerAnnotations(): void
    {
        $files = glob(__DIR__.'/Annotations/*.php');

        foreach ($files as $file) {
            AnnotationRegistry::registerFile($file);
        }
    }

    protected function callbacksIn(): array
    {
        $directories = $this->getPathsFromConfig('callbacks');

        return array_merge($directories, [
            app_path('OpenApi/Callbacks'),
        ]);
    }

    protected function requestBodiesIn(): array
    {
        $directories = $this->getPathsFromConfig('request_bodies');

        return array_merge($directories, [
            app_path('OpenApi/RequestBodies'),
        ]);
    }

    protected function responsesIn(): array
    {
        $directories = $this->getPathsFromConfig('responses');

        return array_merge($directories, [
            app_path('OpenApi/Responses'),
        ]);
    }

    protected function schemasIn(): array
    {
        $directories = $this->getPathsFromConfig('schemas');

        return array_merge($directories, [
            app_path('OpenApi/Schemas'),
        ]);
    }

    protected function securitySchemesIn(): array
    {
        $directories = $this->getPathsFromConfig('security_schemes');

        return array_merge($directories, [
            app_path('OpenApi/SecuritySchemes'),
        ]);
    }

    protected function getPathsFromConfig(string $type): array
    {
        $directories = config("openapi.paths.{$type}", []);

        foreach ($directories as &$directory) {
            $directory = glob(app_path($directory), GLOB_ONLYDIR);
        }

        return (new Collection($directories))
            ->flatten()
            ->unique()
            ->toArray();
    }
}
