<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/Pest.php';

use Cognesy\Agents\Drivers\Testing\FakeAgentDriver;
use Cognesy\Agents\Capability\Cancellation\InMemoryCancellationSource;
use Cognesy\Agents\Enums\ExecutionStatus;
use Cognesy\Polyglot\Inference\Config\LLMConfig;
use Cognesy\Tell\Composition\Standalone\Profile\StandardTellProfile;
use Cognesy\Tell\Composition\Standalone\Host\TellHostBuilder;
use Cognesy\Tell\Composition\Standalone\Profile\StandaloneTellHost;
use Cognesy\Tell\Composition\Standalone\Host\TellModuleDefinition;
use Cognesy\Tell\Adapter\Console\Symfony\TellConsoleApplication;
use Cognesy\Tell\Capability\Workspace\Memory\InMemoryTellWorkspaceProvider;
use Cognesy\Tell\Core\Contract\Observation\CanObserveTellExecution;
use Cognesy\Tell\Core\Contract\Model\CanResolveTellModel;
use Cognesy\Tell\Data\TellEventEnvelope;
use Cognesy\Tell\Data\TellCommandDescriptors;
use Cognesy\Tell\Data\TellRequest;
use Cognesy\Tell\Core\Agent\TellAgentFactory;
use Symfony\Component\Console\Output\BufferedOutput;

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
            'provider-catalogue.polyglot',
            'clock.system',
            'cancellation.memory',
            'tracing.standard',
            'agent-definitions.filesystem',
            'agent-contribution.composer-discovery',
            'agent-contribution.coding-tools',
            'agent-contribution.ask-user',
            'agent-contribution.subagents',
            'agent-contribution.standard',
            'agent.cognesy',
            'workspace.filesystem',
            'workspace.execution',
            'configuration.standard',
            'extensions.composer',
            'tools.standard',
            'observation.standard',
            'runtime.standard',
            'execution.default',
            'conversations.standard',
            'protocol.one-run',
        ])
        ->and(json_encode($host->describe()->toArray(), JSON_THROW_ON_ERROR))->not->toContain('host response');

    $host->dispose();
});

it('runs complete headless and CLI profiles on the in-memory workspace backend', function (): void {
    $project = tellTestProject();
    $paths = standardHostPaths($project);
    $memory = new InMemoryTellWorkspaceProvider();
    $headless = StandaloneTellHost::builder(
        directory: $project,
        paths: $paths,
        driverFactory: static fn () => FakeAgentDriver::fromResponses('memory response'),
        workspaces: $memory,
    )->boot();

    $headless->workspace()->initialize($project);
    $result = $headless->runner()->run(TellRequest::prompt('Persist in memory')
        ->withDirectory($project)
        ->durable());
    $branch = $headless->conversations()->branches($project)->create('review');
    $configured = $headless->conversations()->configuration($project)->set('maxToolCalls', 7, 0);

    expect(trim($result->text()))->toBe('memory response')
        ->and($result->isDurable())->toBeTrue()
        ->and($headless->conversations()->main($project)->history()->totalCount)->toBe(1)
        ->and($branch->name)->toBe('review')
        ->and($configured->values)->toBe(['maxToolCalls' => 7])
        ->and($headless->branchConfiguration()?->read($project)?->values)->toBe(['maxToolCalls' => 7]);

    $headless->dispose();

    $cliMemory = new InMemoryTellWorkspaceProvider();
    $cli = StandaloneTellHost::cliBuilder(
        directory: $project,
        paths: $paths,
        driverFactory: static fn () => FakeAgentDriver::fromResponses('unused'),
        workspaces: $cliMemory,
    )->boot();
    $application = new TellConsoleApplication(TellCommandDescriptors::merge(
        ...array_map(static fn ($contributor) => $contributor->commands(), $cli->commandContributors()),
    ));
    $application->setAutoExit(false);
    $output = new BufferedOutput();

    $init = $application->runArgv(['tell', 'init', $project, '--json'], $output);
    $output->fetch();
    $create = $application->runArgv(['tell', 'branch', 'create', 'scratch', '--empty', '--dir', $project, '--json'], $output);
    $output->fetch();
    $configure = $application->runArgv(['tell', 'config', 'set', 'maxToolCalls', '9', '--if-version', '0', '--dir', $project, '--json'], $output);

    expect($init)->toBe(0)
        ->and($create)->toBe(0)
        ->and($configure)->toBe(0, $output->fetch())
        ->and($cliMemory->read($project)?->values)->toBe(['maxToolCalls' => 9])
        ->and(array_column($cli->describe()->modules, 'id'))->toContain('workspace.execution', 'conversations.standard');

    $cli->dispose();
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
    $factory = tellAgentFactoryForPaths(
        paths: $paths,
        directory: $project,
        modelResolver: $resolver,
    );
    $request = TellRequest::prompt('Resolve exactly once')->withDirectory($project);
    $definition = $factory->definition($request);

    $factory->build($request, definition: $definition);

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

    expect($headlessModules)->not->toContain('credentials.filesystem', 'commands.core', 'application.symfony-console')
        ->and($headless->commandContributors())->toBe([])
        ->and(fn () => $headless->application())->toThrow(LogicException::class)
        ->and($cliModules)->toBe([
            ...$headlessModules,
            'credentials.filesystem',
            'commands.core',
            'application.symfony-console',
        ]);

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
