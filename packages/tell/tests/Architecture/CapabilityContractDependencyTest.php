<?php

declare(strict_types=1);

use Cognesy\Agents\Capability\Cancellation\CanProvideCancellationSignal;
use Cognesy\Tell\Core\Contract\Agent\CanContributeTellAgent;
use Cognesy\Tell\Adapter\Console\Symfony\Contract\CanContributeTellCommands;
use Cognesy\Tell\Core\Contract\Execution\CanCreateTellRuntime;
use Cognesy\Tell\Core\Contract\Workspace\CanReadTellBranchConfiguration;
use Cognesy\Tell\Core\Contract\Paths\CanResolveTellPaths;
use Cognesy\Tell\Composition\Standalone\Host\TellCapabilityCardinality;
use Cognesy\Tell\Composition\Standalone\Host\TellCapabilityContracts;
use Cognesy\Tell\Data\TellCommandDescriptors;
use Cognesy\Tell\Data\TellResolvedPaths;
use Cognesy\Tell\Core\Contract\Execution\CanReadTellClock;

it('keeps capability contracts independent of hosts frameworks and implementations', function (): void {
    $root = dirname(__DIR__, 2) . '/src/Core/Contract';
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
        ->and($workspaceDependencies)->toBe([]);
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

it('splits the standalone object graph into explicit Host and Profile namespaces', function (): void {
    $compositionRoot = dirname(__DIR__, 2) . '/src/Composition';
    $rootFiles = glob($compositionRoot . '/*.php') ?: [];
    $standaloneFiles = glob($compositionRoot . '/Standalone/*.php') ?: [];
    $hostFiles = glob($compositionRoot . '/Standalone/Host/*.php') ?: [];
    $profileFiles = glob($compositionRoot . '/Standalone/Profile/*.php') ?: [];

    expect($rootFiles)->toBe([])
        ->and($standaloneFiles)->toBe([])
        ->and($hostFiles)->not->toBeEmpty()
        ->and($profileFiles)->not->toBeEmpty();

    foreach (['Host' => $hostFiles, 'Profile' => $profileFiles] as $namespace => $files) {
        foreach ($files as $file) {
        $source = file_get_contents($file);

        expect($source)->toBeString()
                ->and($source)->toContain("namespace Cognesy\\Tell\\Composition\\Standalone\\{$namespace};");
        }
    }
});

it('keeps runtime capabilities independent of composition mechanisms', function (): void {
    $paths = [
        dirname(__DIR__, 2) . '/src/Data',
        dirname(__DIR__, 2) . '/src/Core',
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
        ->and($bootFiles)->not->toBeEmpty();

    foreach ($bootFiles as $file) {
        expect($file)->toStartWith('Composition/');
    }
});

it('forbids removed bootstrap and alias APIs', function (): void {
    $root = dirname(__DIR__, 2) . '/src';
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));
    $source = '';
    foreach ($iterator as $file) {
        if (!$file->isFile() || $file->getExtension() !== 'php') {
            continue;
        }
        $contents = file_get_contents($file->getPathname());
        $source .= is_string($contents) ? $contents : '';
    }

    expect($source)
        ->not->toContain('TellAgentFactory::installed()')
        ->not->toContain('TellHost::standard(')
        ->not->toContain('TellConsoleApplication::fromHost(')
        ->not->toContain('TellConsoleApplication::fromDescriptors(')
        ->not->toContain('Tell::open(')
        ->not->toContain('Tell::testing(')
        ->not->toContain('canonicalName')
        ->not->toContain('aliasOf')
        ->not->toContain('invokedAs');
});

it('publishes explicit cardinality for every graph capability', function (): void {
    $cardinalities = TellCapabilityContracts::cardinalities();

    expect($cardinalities[CanReadTellBranchConfiguration::class])
        ->toBe(TellCapabilityCardinality::OptionalSingleton)
        ->and($cardinalities[CanCreateTellRuntime::class])
        ->toBe(TellCapabilityCardinality::Singleton)
        ->and($cardinalities[CanContributeTellAgent::class])
        ->toBe(TellCapabilityCardinality::OrderedContribution)
        ->and($cardinalities[CanContributeTellCommands::class])
        ->toBe(TellCapabilityCardinality::OrderedContribution)
        ->and($cardinalities[CanProvideCancellationSignal::class])
        ->toBe(TellCapabilityCardinality::Singleton)
        ->and($cardinalities[CanReadTellClock::class])
        ->toBe(TellCapabilityCardinality::Singleton);

    foreach (array_keys($cardinalities) as $capability) {
        expect(interface_exists($capability))->toBeTrue("{$capability} must be an interface");
    }
});

it('keeps agent and tool provider selection out of the core factory', function (): void {
    $source = file_get_contents(dirname(__DIR__, 2) . '/src/Core/Agent/TellAgentFactory.php');

    expect($source)->toBeString()
        ->not->toContain('Cognesy\\Tell\\Capability\\')
        ->not->toContain('CapabilityDiscovery::discover')
        ->not->toContain('new TellCodingTools')
        ->not->toContain('new TellAskUserCapability')
        ->not->toContain('new TellSubagentExecutor');
});

it('does not retain speculative contribution contracts without providers or consumers', function (): void {
    expect(interface_exists('Cognesy\\Tell\\Contracts\\CanContributeTellExtensions'))
        ->toBeFalse()
        ->and(interface_exists('Cognesy\\Tell\\Contracts\\CanContributeTellTools'))
        ->toBeFalse();
});

it('documents every Tell-owned interface in the capability census', function (): void {
    $root = dirname(__DIR__, 2);
    $document = file_get_contents($root . '/CONTRACTS.md');
    $interfaces = [];
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/src'));
    foreach ($iterator as $file) {
        if (!$file->isFile() || $file->getExtension() !== 'php') {
            continue;
        }
        $source = file_get_contents($file->getPathname());
        if (is_string($source) && preg_match('/\\binterface\\s+([A-Za-z][A-Za-z0-9_]*)/', $source, $match) === 1) {
            $interfaces[] = $match[1];
        }
    }
    sort($interfaces, SORT_STRING);

    expect($document)->toBeString();
    foreach ($interfaces as $interface) {
        expect($document)->toContain($interface);
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
        glob(dirname(__DIR__, 2) . '/src/Core/Contract/**/*.php') ?: [],
    );

    expect($files)->not->toContain('TellState.php')
        ->not->toContain('TellStatus.php')
        ->not->toContain('TellUsage.php');
});
