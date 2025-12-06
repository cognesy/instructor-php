<?php

declare(strict_types=1);

namespace Cognesy\Logging\Integrations\Laravel;

use Cognesy\Events\Traits\HandlesEvents;
use Cognesy\Logging\Factories\LaravelLoggingFactory;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;

/**
 * Laravel ServiceProvider for Instructor Logging
 */
class InstructorLoggingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/config/instructor-logging.php',
            'instructor-logging'
        );
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/config/instructor-logging.php' => config_path('instructor-logging.php'),
            ], 'instructor-logging-config');
        }

        if (!config('instructor-logging.enabled', true)) {
            return;
        }

        // Auto-configure logging for all Instructor services
        $this->configurePipeline();
    }

    private function configurePipeline(): void
    {
        $factory = match (config('instructor-logging.preset')) {
            'production' => fn(Application $app) => LaravelLoggingFactory::productionSetup($app),
            'default' => fn(Application $app) => LaravelLoggingFactory::defaultSetup($app),
            'custom' => fn(Application $app) => LaravelLoggingFactory::create($app, config('instructor-logging.config', [])),
            default => fn(Application $app) => LaravelLoggingFactory::defaultSetup($app),
        };

        $pipeline = $factory($this->app);

        // Apply to all classes that handle events
        $this->app->afterResolving(function ($resolved) use ($pipeline) {
            if (in_array(HandlesEvents::class, class_uses_recursive($resolved::class))) {
                $resolved->wiretap($pipeline);
            }
        });
    }
}