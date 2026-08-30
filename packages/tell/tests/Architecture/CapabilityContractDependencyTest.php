<?php

declare(strict_types=1);

use Cognesy\Agents\Capability\Cancellation\CanProvideCancellationSignal;
use Cognesy\Tell\Contracts\CanContributeTellCommands;
use Cognesy\Tell\Contracts\CanContributeTellExtensions;
use Cognesy\Tell\Contracts\CanContributeTellTools;
use Cognesy\Tell\Contracts\CanCreateTellRuntime;
use Cognesy\Tell\Contracts\CanReadTellBranchConfiguration;
use Cognesy\Tell\Contracts\CanResolveTellPaths;
use Cognesy\Tell\Contracts\TellCapabilityCardinality;
use Cognesy\Tell\Contracts\TellCapabilityContracts;
use Cognesy\Tell\Data\TellCommandDescriptors;
use Cognesy\Tell\Data\TellResolvedPaths;
use Cognesy\Tell\Runtime\CanReadTellClock;
use Cognesy\Tell\Runtime\TellAgentFactory;

it('keeps capability contracts independent of hosts frameworks and implementations', function (): void {
    $root = dirname(__DIR__, 2) . '/src/Contracts';
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));
    $source = '';
    $workspaceDependencies = [];
    foreach ($iterator as $file) {
        if (!$file->isFile() || $file->getExtension() !== 'php') {
            continue;
        }
        $contents = file_get_contents($file->getPathname());
        $source .= is_string($contents) ? $contents : '';
        if (is_string($contents)) {
            preg_match_all('/use (Cognesy\\\\Tell\\\\Workspace\\\\[^;]+);/', $contents, $matches);
            $workspaceDependencies = [...$workspaceDependencies, ...$matches[1]];
        }
    }
    $workspaceDependencies = array_values(array_unique($workspaceDependencies));
    sort($workspaceDependencies, SORT_STRING);

    expect($source)
        ->not->toContain('Cognesy\\Cordis')
        ->not->toContain('Symfony\\Component')
        ->not->toContain('Cognesy\\Tell\\Command\\')
        ->not->toContain('Cognesy\\Agents\\Drivers\\')
        ->not->toContain('Cognesy\\Polyglot\\Inference\\Drivers\\')
        ->not->toContain('getenv(')
        ->not->toContain('putenv(')
        ->and($workspaceDependencies)->toBe([
            'Cognesy\\Tell\\Workspace\\Branch\\TellBranch',
            'Cognesy\\Tell\\Workspace\\Branch\\TellBranchConfig',
            'Cognesy\\Tell\\Workspace\\Branch\\TellBranchConfiguration',
            'Cognesy\\Tell\\Workspace\\Branch\\TellBranches',
            'Cognesy\\Tell\\Workspace\\TellConversation',
            'Cognesy\\Tell\\Workspace\\TellRef',
        ]);
});

it('keeps the static composition host free of dynamic kernels and shell frameworks', function (): void {
    $paths = [
        dirname(__DIR__, 2) . '/src/Composition',
    ];
    $source = '';
    foreach ($paths as $path) {
        if (is_file($path)) {
            $contents = file_get_contents($path);
            $source .= is_string($contents) ? $contents : '';

            continue;
        }
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path));
        foreach ($iterator as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }
            $contents = file_get_contents($file->getPathname());
            $source .= is_string($contents) ? $contents : '';
        }
    }

    expect($source)->not->toContain('Cognesy\\Cordis')
        ->not->toContain('Symfony\\Component')
        ->not->toContain('Cognesy\\Utils\\Context')
        ->not->toContain('Psr\\Container')
        ->not->toContain('reconcile(')
        ->not->toContain('pendingModules');
});

it('keeps the standalone object graph in one explicit composition namespace', function (): void {
    $compositionRoot = dirname(__DIR__, 2) . '/src/Composition';
    $rootFiles = glob($compositionRoot . '/*.php') ?: [];
    $standaloneFiles = glob($compositionRoot . '/Standalone/*.php') ?: [];

    expect($rootFiles)->toBe([])
        ->and($standaloneFiles)->not->toBeEmpty();

    foreach ($standaloneFiles as $file) {
        $source = file_get_contents($file);

        expect($source)->toBeString()
            ->and($source)->toContain('namespace Cognesy\\Tell\\Composition\\Standalone;');
    }
});

it('keeps runtime capabilities independent of composition mechanisms', function (): void {
    $paths = [
        dirname(__DIR__, 2) . '/src/Contracts',
        dirname(__DIR__, 2) . '/src/Data',
        dirname(__DIR__, 2) . '/src/Runtime',
        dirname(__DIR__, 2) . '/src/Tool',
        dirname(__DIR__, 2) . '/src/Workspace',
    ];
    $source = '';
    foreach ($paths as $path) {
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path));
        foreach ($iterator as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }
            $contents = file_get_contents($file->getPathname());
            $source .= is_string($contents) ? $contents : '';
        }
    }

    expect($source)
        ->not->toContain('Cognesy\\Tell\\Composition\\')
        ->not->toContain('Cognesy\\Cordis\\')
        ->not->toContain('Psr\\Container\\')
        ->not->toContain('Symfony\\Component\\DependencyInjection\\')
        ->not->toContain('Illuminate\\Container\\')
        ->not->toContain('TellAgentFactory::installed()')
        ->not->toContain('Tell::open(');
});

it('keeps Tell product code and its package manifest independent of Cordis', function (): void {
    $root = dirname(__DIR__, 2);
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/src'));
    $source = '';
    foreach ($iterator as $file) {
        if (!$file->isFile() || $file->getExtension() !== 'php') {
            continue;
        }
        $contents = file_get_contents($file->getPathname());
        $source .= is_string($contents) ? $contents : '';
    }
    $manifest = file_get_contents($root . '/composer.json');

    expect($source)->not->toContain('CordisPhp\\')
        ->and($manifest)->toBeString()
        ->not->toContain('cordis-php/cordis');
});

it('boots the standard provider graph only at the standalone composition root', function (): void {
    $root = dirname(__DIR__, 2) . '/src';
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));
    $bootFiles = [];
    $source = '';
    foreach ($iterator as $file) {
        if (!$file->isFile() || $file->getExtension() !== 'php') {
            continue;
        }
        $contents = file_get_contents($file->getPathname());
        if (!is_string($contents)) {
            continue;
        }
        $source .= $contents;
        if (str_contains($contents, '->boot()')) {
            $bootFiles[] = str_replace($root . DIRECTORY_SEPARATOR, '', $file->getPathname());
        }
    }

    expect($source)->not->toContain('Tell::open(')
        ->and($bootFiles)->toBe(['Composition/Standalone/StandaloneTellHost.php']);
});

it('keeps installed agent-factory selection at the standalone compatibility boundary', function (): void {
    $root = dirname(__DIR__, 2) . '/src';
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));
    $callers = [];
    foreach ($iterator as $file) {
        if (!$file->isFile() || $file->getExtension() !== 'php') {
            continue;
        }
        $contents = file_get_contents($file->getPathname());
        if (is_string($contents) && str_contains($contents, 'TellAgentFactory::installed()')) {
            $callers[] = str_replace($root . DIRECTORY_SEPARATOR, '', $file->getPathname());
        }
    }

    expect($callers)->toBe(['Composition/Standalone/StandaloneTellHost.php']);
});

it('publishes explicit cardinality for every graph capability', function (): void {
    $cardinalities = TellCapabilityContracts::cardinalities();

    expect($cardinalities[CanReadTellBranchConfiguration::class])
        ->toBe(TellCapabilityCardinality::OptionalSingleton)
        ->and($cardinalities[CanCreateTellRuntime::class])
        ->toBe(TellCapabilityCardinality::Singleton)
        ->and($cardinalities[CanContributeTellCommands::class])
        ->toBe(TellCapabilityCardinality::OrderedContribution)
        ->and($cardinalities[CanContributeTellExtensions::class])
        ->toBe(TellCapabilityCardinality::OrderedContribution)
        ->and($cardinalities[CanContributeTellTools::class])
        ->toBe(TellCapabilityCardinality::OrderedContribution)
        ->and($cardinalities[CanProvideCancellationSignal::class])
        ->toBe(TellCapabilityCardinality::Singleton)
        ->and($cardinalities[CanReadTellClock::class])
        ->toBe(TellCapabilityCardinality::Singleton);

    foreach (array_keys($cardinalities) as $capability) {
        expect(interface_exists($capability))->toBeTrue("{$capability} must be an interface");
    }
});

it('uses a contract directly without booting a host or shell framework', function (): void {
    $resolver = new class implements CanResolveTellPaths {
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
    };

    $paths = $resolver->resolve('/project');

    expect($paths->project)->toBe('/project')
        ->and($paths->projectAgents)->toBe('/project/.claude/agents')
        ->and($paths->toArray())->toHaveKey('credentials', '/tell/config/credentials.env')
        ->and($resolver)->not->toBeInstanceOf(CanContributeTellCommands::class)
        ->and(new TellCommandDescriptors())->toHaveCount(0);
});

it('does not introduce parallel state status or usage models', function (): void {
    $files = array_map(
        static fn (string $path): string => basename($path),
        glob(dirname(__DIR__, 2) . '/src/Contracts/**/*.php') ?: [],
    );

    expect($files)->not->toContain('TellState.php')
        ->not->toContain('TellStatus.php')
        ->not->toContain('TellUsage.php');
});

it('keeps the concrete agent factory focused on agent construction', function (): void {
    $methods = array_values(array_map(
        static fn (ReflectionMethod $method): string => $method->getName(),
        array_filter(
            (new ReflectionClass(TellAgentFactory::class))->getMethods(ReflectionMethod::IS_PUBLIC),
            static fn (ReflectionMethod $method): bool => $method->getDeclaringClass()->getName() === TellAgentFactory::class,
        ),
    ));
    sort($methods, SORT_STRING);

    expect($methods)->toBe([
        '__construct',
        'assertReady',
        'build',
        'configureDefinition',
        'definition',
        'definitions',
        'installed',
        'paths',
    ]);
});
