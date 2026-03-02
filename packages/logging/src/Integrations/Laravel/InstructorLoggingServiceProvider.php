<?php

declare(strict_types=1);

namespace Cognesy\Logging\Integrations\Laravel;

use Cognesy\Events\Contracts\CanHandleEvents;
use Cognesy\Events\Event;
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

        if (!$this->configGet('enabled', true)) {
            return;
        }

        $this->configurePipeline();
    }

    private function configurePipeline(): void
    {
        $pipeline = $this->makePipeline();
        $this->attachPipelineToConfiguredEventBus($pipeline);
    }

    /** @return callable(Event): void */
    protected function makePipeline(): callable
    {
        $factory = match ($this->configGet('preset', 'default')) {
            'production' => fn(Application $app) => LaravelLoggingFactory::productionSetup($app),
            'default' => fn(Application $app) => LaravelLoggingFactory::defaultSetup($app),
            'custom' => fn(Application $app) => LaravelLoggingFactory::create(
                $app,
                $this->configGet('config', []),
            ),
            default => fn(Application $app) => LaravelLoggingFactory::defaultSetup($app),
        };

        return $factory($this->app);
    }

    /** @param callable(Event): void $pipeline */
    private function attachPipelineToConfiguredEventBus(callable $pipeline): void
    {
        $eventBusBinding = $this->configGet('event_bus_binding', CanHandleEvents::class);
        if (!is_string($eventBusBinding) || $eventBusBinding === '') {
            return;
        }

        if (!$this->app->bound($eventBusBinding)) {
            return;
        }

        $eventBus = $this->app->make($eventBusBinding);
        if (!$eventBus instanceof CanHandleEvents) {
            return;
        }

        $eventBus->wiretap(static function (object $event) use ($pipeline): void {
            if ($event instanceof Event) {
                $pipeline($event);
            }
        });
    }

    private function configGet(string $path, mixed $default = null): mixed
    {
        if (!$this->app->bound('config')) {
            return $default;
        }

        $config = $this->app->make('config');
        if (!is_object($config) || !method_exists($config, 'get')) {
            return $default;
        }

        return $config->get("instructor-logging.{$path}", $default);
    }
}
