<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/Pest.php';

use Cognesy\Agents\Capability\Cancellation\InMemoryCancellationSource;
use Cognesy\Agents\Drivers\Testing\FakeAgentDriver;
use Cognesy\Agents\Enums\ExecutionStatus;
use Cognesy\Polyglot\Inference\Config\LLMConfig;
use Cognesy\Tell\Capability\Workspace\Memory\InMemoryTellWorkspaceProvider;
use Cognesy\Tell\Composition\Standalone\StandaloneTellBuilder;
use Cognesy\Tell\Core\Contract\Model\CanResolveTellModel;
use Cognesy\Tell\Core\Contract\Observation\CanObserveTellExecution;
use Cognesy\Tell\Data\TellEventEnvelope;
use Cognesy\Tell\Data\TellRequest;
use Symfony\Component\Console\Output\BufferedOutput;

it('runs standard Tell with a replaced driver factory', function (): void {
    $project = tellTestProject();
    $tell = StandaloneTellBuilder::in($project, standardHostPaths($project))
        ->withDriverFactory(static fn () => FakeAgentDriver::fromResponses('builder response'))
        ->build();

    $result = $tell->run(TellRequest::prompt('Run via builder'));

    expect(trim($result->text()))->toBe('builder response');
});

it('creates an isolated runtime engine for every run', function (): void {
    $factory = tellTestRuntimeFactory(tellTestFactory());

    expect($factory->create())->not->toBe($factory->create());
});

it('runs headless and CLI roots on the in-memory workspace backend', function (): void {
    $project = tellTestProject();
    $paths = standardHostPaths($project);
    $headlessMemory = new InMemoryTellWorkspaceProvider();
    $tell = StandaloneTellBuilder::in($project, $paths)
        ->withDriverFactory(static fn () => FakeAgentDriver::fromResponses('memory response'))
        ->withWorkspace($headlessMemory)
        ->build();

    $tell->workspace()->initialize();
    $result = $tell->run(TellRequest::prompt('Persist in memory')->durable());
    $branch = $tell->workspace()->branches()->create('review');
    $configured = $tell->workspace()->configuration()->set('maxToolCalls', 7, 0);

    expect(trim($result->text()))->toBe('memory response')
        ->and($result->isPublished())->toBeTrue()
        ->and($tell->workspace()->main()->history()->totalCount)->toBe(1)
        ->and($branch->name)->toBe('review')
        ->and($configured->values)->toBe(['maxToolCalls' => 7])
        ->and($headlessMemory->read($project)?->values)->toBe(['maxToolCalls' => 7]);

    $cliMemory = new InMemoryTellWorkspaceProvider();
    $application = StandaloneTellBuilder::in($project, $paths)
        ->withDriverFactory(static fn () => FakeAgentDriver::fromResponses('unused'))
        ->withWorkspace($cliMemory)
        ->buildCli();
    $application->setAutoExit(false);
    $output = new BufferedOutput();

    $init = $application->runArgv(['tell', 'init', $project, '--json'], $output);
    $output->fetch();
    $create = $application->runArgv(
        ['tell', 'branch', 'create', 'scratch', '--empty', '--dir', $project, '--json'],
        $output,
    );
    $output->fetch();
    $configure = $application->runArgv(
        ['tell', 'config', 'set', 'maxToolCalls', '9', '--if-version', '0', '--dir', $project, '--json'],
        $output,
    );

    expect($init)->toBe(0)
        ->and($create)->toBe(0)
        ->and($configure)->toBe(0, $output->fetch())
        ->and($cliMemory->read($project)?->values)->toBe(['maxToolCalls' => 9]);
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

it('applies model and observation replacements before standard resolution', function (): void {
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
    $received = new ArrayObject();
    $observer = new class($received) implements CanObserveTellExecution {
        public function __construct(private ArrayObject $received) {}

        public function observe(TellEventEnvelope $event): void {
            $this->received->append($event->kind);
        }
    };
    $tell = StandaloneTellBuilder::in($project, $paths)
        ->withModelResolver($model)
        ->withObserver($observer)
        ->withDriverFactory(static fn () => FakeAgentDriver::fromResponses('replacement response'))
        ->build();

    $result = $tell->run(TellRequest::prompt('run'));

    expect(trim($result->text()))->toBe('replacement response')
        ->and($received)->not->toBeEmpty();
});

it('isolates cancellation between independently built Tell instances', function (): void {
    $project = tellTestProject();
    $paths = standardHostPaths($project);
    $cancelled = new InMemoryCancellationSource();
    $cancelled->cancel('first instance only');
    $first = StandaloneTellBuilder::in($project, $paths)
        ->withDriverFactory(static fn () => FakeAgentDriver::fromResponses('must not complete'))
        ->withCancellation($cancelled)
        ->build();
    $second = StandaloneTellBuilder::in($project, $paths)
        ->withDriverFactory(static fn () => FakeAgentDriver::fromResponses('uncancelled response'))
        ->build();

    $firstResult = $first->run(TellRequest::prompt('run'));
    $secondResult = $second->run(TellRequest::prompt('run'));

    expect($firstResult->status())->toBe(ExecutionStatus::Stopped)
        ->and($secondResult->status())->toBe(ExecutionStatus::Completed)
        ->and(trim($secondResult->text()))->toBe('uncancelled response');
});
