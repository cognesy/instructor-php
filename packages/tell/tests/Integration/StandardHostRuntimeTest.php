<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/Pest.php';

use Cognesy\Agents\Drivers\Testing\FakeAgentDriver;
use Cognesy\Agents\Capability\Cancellation\InMemoryCancellationSource;
use Cognesy\Agents\Enums\ExecutionStatus;
use Cognesy\Polyglot\Inference\Config\LLMConfig;
use Cognesy\Tell\Composition\Standalone\StandardTellProfile;
use Cognesy\Tell\Composition\Standalone\TellHostBuilder;
use Cognesy\Tell\Composition\Standalone\StandaloneTellHost;
use Cognesy\Tell\Composition\Standalone\TellModuleDefinition;
use Cognesy\Tell\Contracts\CanObserveTellExecution;
use Cognesy\Tell\Contracts\CanResolveTellModel;
use Cognesy\Tell\Data\TellEventEnvelope;
use Cognesy\Tell\Data\TellRequest;
use Cognesy\Tell\Runtime\TellAgentFactory;

it('runs the standard modular host with a replaced driver factory', function (): void {
    $project = tellTestProject();
    $paths = standardHostPaths($project);
    $host = TellHostBuilder::fromProfile(StandardTellProfile::runtime(
        directory: $project,
        paths: $paths,
        driverFactory: static fn () => FakeAgentDriver::fromResponses('host response'),
    ))->boot();

    $result = $host->runner()->run(TellRequest::prompt('Run via host')->withDirectory($project));

    expect(trim($result->text()))->toBe('host response')
        ->and(array_column($host->describe()->modules, 'id'))->toBe([
            'paths.standard',
            'secrets.standard',
            'model.polyglot',
            'clock.system',
            'cancellation.memory',
            'tracing.standard',
            'agent.cognesy',
            'workspace.filesystem',
            'configuration.standard',
            'extensions.composer',
            'tools.standard',
            'observation.standard',
            'runtime.standard',
            'execution.default',
            'conversations.filesystem',
            'protocol.one-run',
        ])
        ->and(json_encode($host->describe()->toArray(), JSON_THROW_ON_ERROR))->not->toContain('host response');

    $host->dispose();
});

it('resolves a model once when an immutable definition is handed to loop construction', function (): void {
    $project = tellTestProject();
    $paths = standardHostPaths($project);
    $calls = new ArrayObject();
    $resolver = new class($calls) implements CanResolveTellModel {
        public function __construct(private ArrayObject $calls) {}

        public function resolve(TellRequest $request): LLMConfig {
            $this->calls->append($request->model);

            return LLMConfig::fromArray([
                'driver' => 'openai',
                'model' => 'gpt-4o-mini',
                'apiUrl' => 'https://api.openai.com/v1',
                'apiKey' => 'not-a-real-key',
            ]);
        }
    };
    $factory = new TellAgentFactory(
        paths: $paths,
        tracer: new \Cognesy\Tell\Observability\StandardTellExecutionTracer($paths),
        modelResolver: $resolver,
    );
    $request = TellRequest::prompt('Resolve exactly once')->withDirectory($project);
    $definition = $factory->definition($request->toOptions());

    $factory->build($request->toOptions(), $definition);

    expect($calls)->toHaveCount(1);
});

it('replaces model resolution and observation independently in the standard profile', function (): void {
    $project = tellTestProject();
    $paths = standardHostPaths($project);
    $model = new class implements CanResolveTellModel {
        public function resolve(TellRequest $request): LLMConfig {
            return LLMConfig::fromArray([
                'driver' => 'openai',
                'model' => 'replacement-model',
                'apiUrl' => 'https://example.test/v1',
                'apiKey' => 'replacement-key',
            ]);
        }
    };
    $observer = new class implements CanObserveTellExecution {
        public function observe(TellEventEnvelope $event): void {}
    };
    $host = TellHostBuilder::fromProfile(StandardTellProfile::runtime(
        directory: $project,
        paths: $paths,
        driverFactory: static fn () => FakeAgentDriver::fromResponses('replacement response'),
    ))
        ->replace('model.polyglot', new TellModuleDefinition(
            id: 'model.replacement',
            provides: [CanResolveTellModel::class],
            factory: static fn (): object => $model,
        ))
        ->replace('observation.standard', new TellModuleDefinition(
            id: 'observation.replacement',
            provides: [CanObserveTellExecution::class],
            factory: static fn (): object => $observer,
        ))
        ->boot();

    expect($host->model())->toBe($model)
        ->and($host->observer())->toBe($observer)
        ->and(trim($host->runner()->run(TellRequest::prompt('run')->withDirectory($project))->text()))
        ->toBe('replacement response');

    $host->dispose();
});

it('keeps the headless profile free of CLI providers while CLI extends it explicitly', function (): void {
    $project = tellTestProject();
    $paths = standardHostPaths($project);
    $headless = StandaloneTellHost::builder($project, $paths)->boot();
    $cli = StandaloneTellHost::cli($project, $paths);

    $headlessModules = array_column($headless->describe()->modules, 'id');
    $cliModules = array_column($cli->describe()->modules, 'id');

    expect($headlessModules)->not->toContain('commands.core', 'application.symfony-console')
        ->and($headless->commandContributors())->toBe([])
        ->and(fn () => $headless->application())->toThrow(LogicException::class)
        ->and($cliModules)->toBe([...$headlessModules, 'commands.core', 'application.symfony-console']);

    $headless->dispose();
    $cli->dispose();
});

it('scopes cancellation overrides to one runtime execution', function (): void {
    $project = tellTestProject();
    $paths = standardHostPaths($project);
    $host = StandaloneTellHost::builder(
        directory: $project,
        paths: $paths,
        driverFactory: static fn () => FakeAgentDriver::fromResponses('uncancelled response'),
    )->boot();
    $cancelled = new InMemoryCancellationSource();
    $cancelled->cancel('first execution only');
    $request = TellRequest::prompt('run')->withDirectory($project);

    $first = $host->runtimeFactory()->create($cancelled)->run($request);
    $second = $host->runtimeFactory()->create()->run($request);

    expect($first->status())->toBe(ExecutionStatus::Stopped)
        ->and($second->status())->toBe(ExecutionStatus::Completed)
        ->and(trim($second->text()))->toBe('uncancelled response');

    $host->dispose();
});
