<?php

declare(strict_types=1);

namespace Cognesy\Auxiliary\Beads\Presentation;

use Cognesy\Auxiliary\Beads\Application\Service\AgentContextService;
use Cognesy\Auxiliary\Beads\Application\Service\GraphAnalysisService;
use Cognesy\Auxiliary\Beads\Application\Service\TaskQueryService;
use Cognesy\Auxiliary\Beads\Application\UseCase\ClaimTask\ClaimTaskHandler;
use Cognesy\Auxiliary\Beads\Application\UseCase\CompleteTask\CompleteTaskHandler;
use Cognesy\Auxiliary\Beads\Application\UseCase\CreateEpic\CreateEpicHandler;
use Cognesy\Auxiliary\Beads\Application\UseCase\CreateTask\CreateTaskHandler;
use Cognesy\Auxiliary\Beads\Application\UseCase\GetNextTask\GetNextTaskHandler;
use Cognesy\Auxiliary\Beads\Application\UseCase\RecoverSession\RecoverSessionHandler;
use Cognesy\Auxiliary\Beads\Domain\Repository\GraphRepositoryInterface;
use Cognesy\Auxiliary\Beads\Domain\Repository\TaskRepositoryInterface;
use Cognesy\Auxiliary\Beads\Domain\Service\TaskLifecycleService;
use Cognesy\Auxiliary\Beads\Infrastructure\Client\BdClient;
use Cognesy\Auxiliary\Beads\Infrastructure\Client\BvClient;
use Cognesy\Auxiliary\Beads\Infrastructure\Execution\CommandExecutor;
use Cognesy\Auxiliary\Beads\Infrastructure\Execution\ExecutionPolicy;
use Cognesy\Auxiliary\Beads\Infrastructure\Execution\SandboxCommandExecutor;
use Cognesy\Auxiliary\Beads\Infrastructure\Factory\TaskFactory;
use Cognesy\Auxiliary\Beads\Infrastructure\Parser\CommentParser;
use Cognesy\Auxiliary\Beads\Infrastructure\Parser\GraphParser;
use Cognesy\Auxiliary\Beads\Infrastructure\Parser\TaskParser;
use Cognesy\Auxiliary\Beads\Infrastructure\Repository\BdTaskRepository;
use Cognesy\Auxiliary\Beads\Infrastructure\Repository\BvGraphRepository;
use Cognesy\Auxiliary\Beads\Presentation\Builder\EpicBuilder;
use Cognesy\Auxiliary\Beads\Presentation\Builder\TaskBuilder;
use Cognesy\Auxiliary\Beads\Presentation\Facade\Beads;
use Illuminate\Support\ServiceProvider;

/**
 * Beads Service Provider
 *
 * Registers all Beads integration components with Laravel DI container.
 */
final class BeadsServiceProvider extends ServiceProvider
{
    /**
     * Register services
     */
    #[\Override]
    public function register(): void
    {
        // Load configuration
        $this->mergeConfigFrom(
            __DIR__.'/../../../../config/beads.php',
            'beads'
        );

        // Register execution layer
        $this->app->singleton(CommandExecutor::class, function ($app) {
            $policy = ExecutionPolicy::forBeads();
            $maxRetries = (int) config('beads.retry.max_attempts', 0);

            return new SandboxCommandExecutor($policy, $maxRetries);
        });

        // Register CLI clients
        $this->app->singleton(BdClient::class, function ($app) {
            return new BdClient($app->make(CommandExecutor::class));
        });

        $this->app->singleton(BvClient::class, function ($app) {
            return new BvClient($app->make(CommandExecutor::class));
        });

        // Register parsers
        $this->app->singleton(TaskParser::class);
        $this->app->singleton(CommentParser::class);
        $this->app->singleton(GraphParser::class);

        // Register factory
        $this->app->singleton(TaskFactory::class, function ($app) {
            return new TaskFactory(
                $app->make(BdClient::class),
                $app->make(TaskParser::class),
            );
        });

        // Register repositories
        $this->app->singleton(TaskRepositoryInterface::class, BdTaskRepository::class);
        $this->app->singleton(GraphRepositoryInterface::class, BvGraphRepository::class);

        // Register domain services
        $this->app->singleton(TaskLifecycleService::class);

        // Register use case handlers
        $this->app->singleton(CreateTaskHandler::class);
        $this->app->singleton(CreateEpicHandler::class);
        $this->app->singleton(ClaimTaskHandler::class);
        $this->app->singleton(CompleteTaskHandler::class);
        $this->app->singleton(GetNextTaskHandler::class);
        $this->app->singleton(RecoverSessionHandler::class);

        // Register application services
        $this->app->singleton(TaskQueryService::class);
        $this->app->singleton(GraphAnalysisService::class);
        $this->app->singleton(AgentContextService::class);

        // Register builders (not singleton - need fresh instances)
        $this->app->bind(TaskBuilder::class);
        $this->app->bind(EpicBuilder::class);

        // Register facade
        $this->app->singleton(Beads::class);
    }

    /**
     * Bootstrap services
     */
    public function boot(): void
    {
        // Publish configuration
        $this->publishes([
            __DIR__.'/../../../../config/beads.php' => config_path('beads.php'),
        ], 'beads-config');
    }
}
