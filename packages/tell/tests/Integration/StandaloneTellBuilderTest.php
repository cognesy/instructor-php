<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/Pest.php';

use Cognesy\Agents\Drivers\Testing\FakeAgentDriver;
use Cognesy\Tell\Capability\Workspace\Memory\InMemoryTellWorkspaceProvider;
use Cognesy\Tell\Composition\Standalone\StandaloneTellBuilder;
use Cognesy\Tell\Core\Agent\TellAgentAssembly;
use Cognesy\Tell\Core\Agent\TellAgentContributions;
use Cognesy\Tell\Core\Contract\Agent\CanContributeTellAgent;
use Cognesy\Tell\Core\Contract\Observation\CanObserveTellExecution;
use Cognesy\Tell\Core\Paths\TellPaths;
use Cognesy\Tell\Data\TellEventEnvelope;
use Cognesy\Tell\Data\TellRequest;
use Symfony\Component\Console\Command\Command;

it('builds isolated Tell roots with independent configured drivers', function (): void {
    $project = tellTestProject();
    $paths = standardHostPaths($project);
    $first = StandaloneTellBuilder::in($project, $paths)
        ->withDriverFactory(static fn () => FakeAgentDriver::fromResponses('first'))
        ->build();
    $second = StandaloneTellBuilder::in($project, $paths)
        ->withDriverFactory(static fn () => FakeAgentDriver::fromResponses('second'))
        ->build();

    expect(trim($first->run(TellRequest::prompt('one'))->text()))->toBe('first')
        ->and(trim($second->run(TellRequest::prompt('two'))->text()))->toBe('second');
});

it('keeps two Tell instances isolated across interleaved native fibers', function (): void {
    $project = tellTestProject();
    $paths = standardHostPaths($project);
    $first = StandaloneTellBuilder::in($project, $paths)
        ->withDriverFactory(static fn () => FakeAgentDriver::fromResponses('fiber one'))
        ->build();
    $second = StandaloneTellBuilder::in($project, $paths)
        ->withDriverFactory(static fn () => FakeAgentDriver::fromResponses('fiber two'))
        ->build();
    $firstFiber = new Fiber(static function () use ($first): string {
        Fiber::suspend();

        return trim($first->run(TellRequest::prompt('one'))->text());
    });
    $secondFiber = new Fiber(static function () use ($second): string {
        Fiber::suspend();

        return trim($second->run(TellRequest::prompt('two'))->text());
    });

    $firstFiber->start();
    $secondFiber->start();
    $secondFiber->resume();
    $firstFiber->resume();

    expect($firstFiber->getReturn())->toBe('fiber one')
        ->and($secondFiber->getReturn())->toBe('fiber two');
});

it('applies an observer replacement before resolving the root', function (): void {
    $project = tellTestProject();
    $received = new ArrayObject();
    $observer = new class($received) implements CanObserveTellExecution {
        public function __construct(private ArrayObject $received) {}

        public function observe(TellEventEnvelope $event): void {
            $this->received->append($event->kind);
        }
    };
    $tell = StandaloneTellBuilder::in($project, standardHostPaths($project))
        ->withDriverFactory(static fn () => FakeAgentDriver::fromResponses('observed'))
        ->withObserver($observer)
        ->build();

    $tell->run(TellRequest::prompt('observe'));

    expect($received)->not->toBeEmpty();
});

it('uses one replacement workspace for every workspace role', function (): void {
    $project = tellTestProject();
    $workspace = new InMemoryTellWorkspaceProvider();
    $tell = StandaloneTellBuilder::in($project, standardHostPaths($project))
        ->withDriverFactory(static fn () => FakeAgentDriver::fromResponses('stored'))
        ->withWorkspace($workspace)
        ->build();

    $tell->workspace()->initialize();
    $tell->run(TellRequest::prompt('persist')->durable());
    $tell->workspace()->configuration()->set('maxToolCalls', 3, 0);

    expect($workspace->read($project)?->values)->toBe(['maxToolCalls' => 3])
        ->and($tell->workspace()->main()->history()->totalCount)->toBe(1);
});

it('keeps the container private and rejects builder reuse', function (): void {
    $project = tellTestProject();
    $builder = StandaloneTellBuilder::in($project, standardHostPaths($project))
        ->withDriverFactory(static fn () => FakeAgentDriver::fromResponses('done'));
    $methods = array_map(
        static fn (ReflectionMethod $method): string => $method->getName(),
        (new ReflectionClass($builder))->getMethods(ReflectionMethod::IS_PUBLIC),
    );
    $customizationMethods = array_values(array_filter(
        $methods,
        static fn (string $method): bool => str_starts_with($method, 'with'),
    ));

    $builder->build();

    expect($methods)->not->toContain('get', 'has', 'container', 'services')
        ->and($customizationMethods)->toBe([
            'withDriverFactory',
            'withAgentBuilder',
            'withCancellation',
            'withModelResolver',
            'withObserver',
            'withWorkspace',
            'withAgentContribution',
            'withAgentContributions',
            'withCommand',
        ])
        ->and(fn () => $builder->build())->toThrow(LogicException::class)
        ->and(fn () => $builder->withCommand(new Command('late')))->toThrow(LogicException::class);
});

it('applies replacement and appended agent contributions in order', function (): void {
    $project = tellTestProject();
    $paths = new TellPaths($project . '/package-agents', $project . '/.tell-host');
    mkdir($paths->packageAgents, 0755, true);
    file_put_contents($paths->packageAgents . '/default.md', <<<'MD'
---
name: default
label: Contribution Test
description: Agent with no required capabilities
capabilities: []
---

You are deterministic.
MD);
    $received = new ArrayObject();
    $contribution = static fn (string $name): CanContributeTellAgent
        => new class($name, $received) implements CanContributeTellAgent {
            public function __construct(
                private readonly string $name,
                private readonly ArrayObject $received,
            ) {}

            public function contribute(TellAgentAssembly $assembly): void {
                $this->received->append($this->name);
            }
        };
    $ignored = $contribution('ignored');
    $first = $contribution('first');
    $second = $contribution('second');

    $tell = StandaloneTellBuilder::in($project, $paths)
        ->withAgentContribution($ignored)
        ->withAgentContributions(new TellAgentContributions($first))
        ->withAgentContribution($second)
        ->withDriverFactory(static fn () => FakeAgentDriver::fromResponses('done'))
        ->build();

    $tell->run(TellRequest::prompt('apply contributions'));

    expect($received->getArrayCopy())->toBe(['first', 'second']);
});

it('preserves typed agent contribution collection order', function (): void {
    $first = new class implements CanContributeTellAgent {
        public function contribute(TellAgentAssembly $assembly): void {}
    };
    $second = new class implements CanContributeTellAgent {
        public function contribute(TellAgentAssembly $assembly): void {}
    };
    $contributions = TellAgentContributions::empty()->with($first, $second);

    expect($contributions->all())->toBe([$first, $second])
        ->and(iterator_to_array($contributions))->toBe([$first, $second]);
});

it('builds the CLI root and accepts custom commands', function (): void {
    $project = tellTestProject();
    $application = StandaloneTellBuilder::in($project, standardHostPaths($project))
        ->withDriverFactory(static fn () => FakeAgentDriver::fromResponses('unused'))
        ->withCommand(new Command('custom:probe'))
        ->buildCli();

    expect(array_keys($application->all()))->toContain('tell', 'agent', 'custom:probe');
});

it('rejects duplicate custom command names while building the CLI root', function (): void {
    $project = tellTestProject();

    StandaloneTellBuilder::in($project, standardHostPaths($project))
        ->withDriverFactory(static fn () => FakeAgentDriver::fromResponses('unused'))
        ->withCommand(new Command('tell'))
        ->buildCli();
})->throws(InvalidArgumentException::class, 'Duplicate Tell command name: tell.');
