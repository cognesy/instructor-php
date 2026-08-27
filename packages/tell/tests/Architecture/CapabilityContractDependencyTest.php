<?php

declare(strict_types=1);

use Cognesy\Agents\Capability\Cancellation\CanProvideCancellationSignal;
use Cognesy\Tell\Contracts\CanContributeTellCommands;
use Cognesy\Tell\Contracts\CanContributeTellExtensions;
use Cognesy\Tell\Contracts\CanContributeTellTools;
use Cognesy\Tell\Contracts\CanReadTellBranchConfiguration;
use Cognesy\Tell\Contracts\CanResolveTellPaths;
use Cognesy\Tell\Contracts\Collections\TellCommandDescriptors;
use Cognesy\Tell\Contracts\Data\TellResolvedPaths;
use Cognesy\Tell\Contracts\TellCapabilityCardinality;
use Cognesy\Tell\Contracts\TellCapabilityContracts;
use Cognesy\Tell\Runtime\CanReadTellClock;

it('keeps capability contracts independent of hosts frameworks and implementations', function (): void {
    $root = dirname(__DIR__, 2).'/src/Contracts';
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));
    $source = '';
    foreach ($iterator as $file) {
        if (! $file->isFile() || $file->getExtension() !== 'php') {
            continue;
        }
        $contents = file_get_contents($file->getPathname());
        $source .= is_string($contents) ? $contents : '';
    }

    expect($source)
        ->not->toContain('Cognesy\\Cordis')
        ->not->toContain('Symfony\\Component')
        ->not->toContain('Cognesy\\Tell\\Workspace\\')
        ->not->toContain('Cognesy\\Tell\\Command\\')
        ->not->toContain('Cognesy\\Agents\\Drivers\\')
        ->not->toContain('Cognesy\\Polyglot\\Inference\\Drivers\\')
        ->not->toContain('getenv(')
        ->not->toContain('putenv(');
});

it('keeps the static composition host free of dynamic kernels and shell frameworks', function (): void {
    $paths = [
        dirname(__DIR__, 2).'/src/Composition',
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
            if (! $file->isFile() || $file->getExtension() !== 'php') {
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

it('publishes explicit cardinality for every graph capability', function (): void {
    $cardinalities = TellCapabilityContracts::cardinalities();

    expect($cardinalities[CanReadTellBranchConfiguration::class])
        ->toBe(TellCapabilityCardinality::OptionalSingleton)
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
    $resolver = new class implements CanResolveTellPaths
    {
        public function resolve(string $directory): TellResolvedPaths
        {
            return new TellResolvedPaths(
                project: $directory,
                home: '/tell',
                configDirectory: '/tell/config',
                configFile: '/tell/config/tell.json',
                credentials: '/tell/config/credentials.env',
                connections: '/tell/config/connections',
                packageAgents: '/package/agents',
                userAgents: '/tell/config/agents',
                projectAgents: $directory.'/.claude/agents',
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
        ->and(new TellCommandDescriptors)->toHaveCount(0);
});

it('does not introduce parallel state status or usage models', function (): void {
    $files = array_map(
        static fn (string $path): string => basename($path),
        glob(dirname(__DIR__, 2).'/src/Contracts/**/*.php') ?: [],
    );

    expect($files)->not->toContain('TellState.php')
        ->not->toContain('TellStatus.php')
        ->not->toContain('TellUsage.php');
});
