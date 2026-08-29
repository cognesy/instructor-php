<?php

declare(strict_types=1);

use Cognesy\Agents\Capability\Cancellation\CanProvideCancellationSignal;
use Cognesy\Agents\Continuation\StopSignal;
use Cognesy\Agents\Data\AgentState;
use Cognesy\Tell\Composition\CanDisposeTellModule;
use Cognesy\Tell\Composition\TellCapabilityProviders;
use Cognesy\Tell\Composition\TellHost;
use Cognesy\Tell\Composition\TellHostBootException;
use Cognesy\Tell\Composition\TellHostBuilder;
use Cognesy\Tell\Composition\TellHostDisposalException;
use Cognesy\Tell\Composition\TellHostDisposedException;
use Cognesy\Tell\Composition\TellHostGraphException;
use Cognesy\Tell\Composition\TellHostProfile;
use Cognesy\Tell\Composition\TellModuleDefinition;
use Cognesy\Tell\Contracts\CanContributeTellCommands;
use Cognesy\Tell\Contracts\CanReadTellBranchConfiguration;
use Cognesy\Tell\Contracts\CanResolveTellPaths;
use Cognesy\Tell\Data\TellCommandDescriptor;
use Cognesy\Tell\Data\TellCommandDescriptors;
use Cognesy\Tell\Data\TellResolvedPaths;
use Cognesy\Tell\Runtime\CanReadTellClock;

interface MissingHostFixtureOne {}

interface MissingHostFixtureTwo {}

final class HostFixturePaths implements CanDisposeTellModule, CanResolveTellPaths
{
    public function __construct(
        private readonly ArrayObject $cleanup,
        private readonly string $label = 'paths',
        private readonly bool $failCleanup = false,
    ) {}

    public function resolve(string $directory): TellResolvedPaths {
        return new TellResolvedPaths(
            project: $directory,
            home: '/tell',
            configDirectory: '/tell/config',
            configFile: '/tell/config/tell.json',
            credentials: '/tell/config/credentials.env',
            connections: '/tell/config/connections',
            packageAgents: '/package/agents',
            userAgents: '/tell/config/agents',
            projectAgents: $directory . '/.claude/agents',
            runtime: '/tell/runtime',
            sessions: '/tell/runtime/sessions',
            logs: '/tell/logs',
            executionTraces: '/tell/logs/executions',
            sessionTraces: '/tell/logs/sessions',
        );
    }

    public function dispose(): void {
        $this->cleanup->append($this->label);
        if ($this->failCleanup) {
            throw new RuntimeException('fixture cleanup failure');
        }
    }
}

final class HostFixtureClock implements CanDisposeTellModule, CanReadTellClock
{
    public function __construct(
        public readonly CanResolveTellPaths $paths,
        private readonly ArrayObject $cleanup,
        private readonly int $time = 42,
        private readonly bool $failCleanup = false,
    ) {}

    public function nowMs(): int {
        return $this->time;
    }

    public function dispose(): void {
        $this->cleanup->append('clock');
        if ($this->failCleanup) {
            throw new RuntimeException('fixture cleanup failure');
        }
    }
}

final readonly class HostFixtureCancellation implements CanProvideCancellationSignal
{
    public function cancellationSignal(AgentState $state): ?StopSignal {
        return null;
    }
}

final readonly class HostCommandContributor implements CanContributeTellCommands
{
    public function __construct(public string $label) {}

    public function commands(): TellCommandDescriptors {
        return new TellCommandDescriptors(new TellCommandDescriptor(
            $this->label,
            static fn (): object => new stdClass(),
        ));
    }
}

final readonly class HostContributionClock implements CanReadTellClock
{
    /** @param list<string> $labels */
    public function __construct(public array $labels) {}

    public function nowMs(): int {
        return count($this->labels);
    }
}

function hostPathsModule(ArrayObject $cleanup, bool $failCleanup = false): TellModuleDefinition {
    return new TellModuleDefinition(
        id: 'paths.fixture',
        provides: [CanResolveTellPaths::class],
        factory: static fn (): object => new HostFixturePaths($cleanup, failCleanup: $failCleanup),
        description: 'Fixture paths',
    );
}

function hostClockModule(ArrayObject $cleanup, int $time = 42, bool $failCleanup = false): TellModuleDefinition {
    return new TellModuleDefinition(
        id: 'clock.fixture',
        provides: [CanReadTellClock::class],
        requires: [CanResolveTellPaths::class],
        factory: static fn (CanResolveTellPaths $paths): object => new HostFixtureClock($paths, $cleanup, $time, $failCleanup),
        description: 'Fixture clock',
    );
}

it('boots a typed graph from fresh factories and disposes in reverse order', function (): void {
    $cleanup = new ArrayObject();
    $profile = new TellHostProfile(
        name: 'fixture',
        modules: [hostClockModule($cleanup), hostPathsModule($cleanup)],
        requiredCapabilities: [CanReadTellClock::class, CanResolveTellPaths::class],
    );
    $builder = TellHostBuilder::fromProfile($profile);

    $first = $builder->boot();
    $second = $builder->boot();

    expect($first->clock())->toBeInstanceOf(HostFixtureClock::class)
        ->and($first->clock())->not->toBe($second->clock())
        ->and($first->clock()->nowMs())->toBe(42)
        ->and($first->paths()->resolve('/project')->project)->toBe('/project')
        ->and($first->describe()->profile)->toBe('fixture')
        ->and($first->describe()->toArray())->not->toHaveKey('providers');

    $first->dispose();
    $second->dispose();

    expect($cleanup->getArrayCopy())->toBe(['clock', 'paths', 'clock', 'paths']);
});

it('supports auditable replacement and removal only before boot', function (): void {
    $cleanup = new ArrayObject();
    $profile = new TellHostProfile('fixture', [hostPathsModule($cleanup), hostClockModule($cleanup)], [CanReadTellClock::class]);
    $replacement = new TellModuleDefinition(
        id: 'clock.custom',
        provides: [CanReadTellClock::class],
        requires: [CanResolveTellPaths::class],
        factory: static fn (CanResolveTellPaths $paths): object => new HostFixtureClock($paths, $cleanup, 99),
    );

    $host = TellHostBuilder::fromProfile($profile)
        ->replace('clock.fixture', $replacement)
        ->boot();

    expect($host->clock()->nowMs())->toBe(99)
        ->and($host->describe()->modules[1]['id'])->toBe('clock.custom')
        ->and(fn (): TellHost => TellHostBuilder::fromProfile($profile)->without('clock.fixture')->boot())
        ->toThrow(TellHostGraphException::class);

    $host->dispose();
});

it('aggregates duplicate invalid and missing graph errors before factories run', function (): void {
    $calls = new ArrayObject();
    $module = new TellModuleDefinition(
        id: 'bad.fixture',
        provides: [CanResolveTellPaths::class, stdClass::class],
        requires: [MissingHostFixtureOne::class, MissingHostFixtureTwo::class],
        factory: static function () use ($calls): object {
            $calls->append('called');

            return new stdClass();
        },
    );
    $duplicate = new TellModuleDefinition(
        id: 'bad.fixture',
        provides: [CanResolveTellPaths::class],
        factory: static fn (): object => new stdClass(),
    );

    try {
        TellHostBuilder::empty()->with($module)->with($duplicate)->boot();
        throw new RuntimeException('Expected graph admission failure.');
    } catch (TellHostGraphException $error) {
        expect($error->getMessage())->toContain('duplicate module id bad.fixture')
            ->toContain('duplicate singleton provider ' . CanResolveTellPaths::class)
            ->toContain('non-interface stdClass')
            ->toContain(MissingHostFixtureOne::class)
            ->toContain(MissingHostFixtureTwo::class)
            ->and($calls)->toHaveCount(0);
    }
});

it('rejects dependency cycles before constructing modules', function (): void {
    $first = new TellModuleDefinition(
        'cycle.paths',
        [CanResolveTellPaths::class],
        static fn (CanReadTellClock $clock): object => new stdClass(),
        [CanReadTellClock::class],
    );
    $second = new TellModuleDefinition(
        'cycle.clock',
        [CanReadTellClock::class],
        static fn (CanResolveTellPaths $paths): object => new stdClass(),
        [CanResolveTellPaths::class],
    );

    expect(fn (): TellHost => TellHostBuilder::empty()->with($first)->with($second)->boot())
        ->toThrow(TellHostGraphException::class, 'construction cycle');
});

it('attempts every reverse cleanup after partial boot failure', function (): void {
    $cleanup = new ArrayObject();
    $failure = new TellModuleDefinition(
        id: 'runtime.failure',
        provides: [CanProvideCancellationSignal::class],
        requires: [CanResolveTellPaths::class, CanReadTellClock::class],
        factory: static fn (CanResolveTellPaths $paths, CanReadTellClock $clock): object => throw new RuntimeException('secret fixture detail'),
    );

    try {
        TellHostBuilder::empty()
            ->with(hostPathsModule($cleanup))
            ->with(hostClockModule($cleanup, failCleanup: true))
            ->with($failure)
            ->boot();
        throw new RuntimeException('Expected boot failure.');
    } catch (TellHostBootException $error) {
        expect($error->module)->toBe('runtime.failure')
            ->and($error->getMessage())->not->toContain('secret fixture detail')
            ->and($error->cleanupErrors)->toHaveCount(1)
            ->and($cleanup->getArrayCopy())->toBe(['clock', 'paths']);
    }
});

it('attempts every reverse cleanup after normal disposal failures', function (): void {
    $cleanup = new ArrayObject();
    $host = TellHostBuilder::empty()
        ->with(hostPathsModule($cleanup, failCleanup: true))
        ->with(hostClockModule($cleanup, failCleanup: true))
        ->boot();

    try {
        $host->dispose();
        throw new RuntimeException('Expected disposal failure.');
    } catch (TellHostDisposalException $error) {
        expect($error->errors)->toHaveCount(2)
            ->and($cleanup->getArrayCopy())->toBe(['clock', 'paths']);
    }

    expect(fn (): CanReadTellClock => $host->clock())
        ->toThrow(TellHostDisposedException::class);
});

it('rejects factories that do not implement every advertised interface', function (): void {
    $cleanup = new ArrayObject();
    $invalid = new TellModuleDefinition(
        'clock.invalid',
        [CanReadTellClock::class],
        static fn (CanResolveTellPaths $paths): object => new HostFixtureCancellation(),
        [CanResolveTellPaths::class],
    );

    try {
        TellHostBuilder::empty()
            ->with(hostPathsModule($cleanup))
            ->with($invalid)
            ->boot();
        throw new RuntimeException('Expected invalid provider failure.');
    } catch (TellHostBootException $error) {
        expect($error->module)->toBe('clock.invalid')
            ->and($error->getPrevious()?->getMessage())->toContain('does not implement');
    }

    expect($cleanup->getArrayCopy())->toBe(['paths']);
});

it('injects contribution providers in deterministic module order', function (): void {
    $first = new TellModuleDefinition(
        'commands.first',
        [CanContributeTellCommands::class],
        static fn (): object => new HostCommandContributor('first'),
    );
    $second = new TellModuleDefinition(
        'commands.second',
        [CanContributeTellCommands::class],
        static fn (): object => new HostCommandContributor('second'),
    );
    $clock = new TellModuleDefinition(
        'clock.contributions',
        [CanReadTellClock::class],
        static function (TellCapabilityProviders $providers): object {
            $labels = array_map(
                static fn (object $provider): string => $provider instanceof HostCommandContributor ? $provider->label : 'invalid',
                $providers->all(),
            );

            return new HostContributionClock($labels);
        },
        [CanContributeTellCommands::class],
    );

    $host = TellHostBuilder::empty()
        ->with($second)
        ->with($clock)
        ->with($first)
        ->require(CanReadTellClock::class)
        ->boot();

    expect($host->clock())->toBeInstanceOf(HostContributionClock::class)
        ->and($host->clock()->labels)->toBe(['second', 'first'])
        ->and($host->commandContributors())->toHaveCount(2);
});

it('injects absent optional singleton dependencies as null', function (): void {
    $clock = new TellModuleDefinition(
        id: 'clock.optional',
        provides: [CanReadTellClock::class],
        optional: [CanReadTellBranchConfiguration::class],
        factory: static fn (?CanReadTellBranchConfiguration $branch): object => new HostContributionClock($branch === null ? [] : ['configured']),
    );
    $host = TellHostBuilder::empty()->with($clock)->boot();

    expect($host->clock()->nowMs())->toBe(0)
        ->and($host->branchConfiguration())->toBeNull();
});

it('rejects every capability access after idempotent disposal', function (): void {
    $cleanup = new ArrayObject();
    $host = TellHostBuilder::empty()->with(hostPathsModule($cleanup))->boot();

    $host->dispose();
    $host->dispose();

    expect(fn (): CanResolveTellPaths => $host->paths())
        ->toThrow(TellHostDisposedException::class)
        ->and(fn () => $host->describe())
        ->toThrow(TellHostDisposedException::class)
        ->and($cleanup->getArrayCopy())->toBe(['paths']);
});

it('exposes no public generic service locator', function (): void {
    $methods = array_map(
        static fn (ReflectionMethod $method): string => $method->getName(),
        (new ReflectionClass(TellHost::class))->getMethods(ReflectionMethod::IS_PUBLIC),
    );

    expect($methods)->not->toContain('get')
        ->not->toContain('has')
        ->not->toContain('service')
        ->not->toContain('replace')
        ->not->toContain('with')
        ->not->toContain('without');
});
