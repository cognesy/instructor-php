<?php

declare(strict_types=1);

use Cognesy\Tell\Core\Contract\Paths\CanResolveTellPaths;
use Cognesy\Tell\Data\TellResolvedPaths;
use Cognesy\Tell\Tests\Support\Architecture\TellArchitectureRules;

it('keeps capability contracts independent of hosts frameworks and implementations', function (): void {
    $files = TellArchitectureRules::phpFiles(dirname(__DIR__, 2) . '/src/Core/Contract');
    $source = implode('', array_map(
        static fn (string $file): string => (string) file_get_contents($file),
        $files,
    ));

    expect($source)
        ->not->toContain('Cognesy\\Cordis')
        ->not->toContain('Symfony\\Component')
        ->not->toContain('Cognesy\\Tell\\Adapter\\')
        ->not->toContain('Cognesy\\Tell\\Capability\\')
        ->not->toContain('Cognesy\\Agents\\Drivers\\')
        ->not->toContain('Cognesy\\Polyglot\\Inference\\Drivers\\')
        ->not->toContain('getenv(')
        ->not->toContain('putenv(');
});

it('confines the container to standalone composition', function (): void {
    $root = dirname(__DIR__, 2) . '/src';
    $violations = [];
    foreach (TellArchitectureRules::phpFiles($root) as $file) {
        $source = (string) file_get_contents($file);
        if (!str_contains($source, 'Cognesy\\Utils\\Container')) {
            continue;
        }
        $relative = str_replace($root . DIRECTORY_SEPARATOR, '', $file);
        if (!str_starts_with($relative, 'Composition/Standalone/')) {
            $violations[] = $relative;
        }
    }

    expect($violations)->toBe([]);
});

it('keeps standalone resolution synchronous and privately owned by the builder', function (): void {
    $root = dirname(__DIR__, 2) . '/src/Composition/Standalone';
    $builder = (string) file_get_contents($root . '/StandaloneTellBuilder.php');
    $services = (string) file_get_contents($root . '/StandardTellServices.php');

    expect($builder)->toContain('new SimpleContainer()')
        ->not->toContain('static SimpleContainer')
        ->not->toContain('public function get(')
        ->not->toContain('public function has(')
        ->and($services)->not->toContain('Amp\\')
        ->not->toContain('FiberLocal')
        ->not->toContain('suspend(')
        ->not->toContain('await(');
});

it('keeps runtime capabilities independent of composition mechanisms', function (): void {
    $paths = [
        dirname(__DIR__, 2) . '/src/Data',
        dirname(__DIR__, 2) . '/src/Core',
    ];
    $source = '';
    foreach ($paths as $path) {
        $source .= implode('', array_map(
            static fn (string $file): string => (string) file_get_contents($file),
            TellArchitectureRules::phpFiles($path),
        ));
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
    $source = implode('', array_map(
        static fn (string $file): string => (string) file_get_contents($file),
        TellArchitectureRules::phpFiles($root . '/src'),
    ));
    $manifest = file_get_contents($root . '/composer.json');

    expect($source)->not->toContain('CordisPhp\\')
        ->and($manifest)->toBeString()
        ->not->toContain('cordis-php/cordis');
});

it('forbids removed graph descriptor and compatibility APIs', function (): void {
    $source = implode('', array_map(
        static fn (string $file): string => (string) file_get_contents($file),
        TellArchitectureRules::phpFiles(dirname(__DIR__, 2) . '/src'),
    ));

    expect($source)
        ->not->toContain('Composition\\Standalone\\' . 'Host\\')
        ->not->toContain('StandaloneTell' . 'Host')
        ->not->toContain('TellModule' . 'Definition')
        ->not->toContain('TellCommand' . 'Descriptor')
        ->not->toContain('TellAgentFactory::installed()')
        ->not->toContain('TellConsoleApplication::fromHost(')
        ->not->toContain('Tell::open(')
        ->not->toContain('Tell::testing(')
        ->not->toContain('canonicalName')
        ->not->toContain('aliasOf')
        ->not->toContain('invokedAs');
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

it('uses a contract directly without booting a host or shell framework', function (): void {
    $resolver = new class implements CanResolveTellPaths {
        public function resolve(string $directory): TellResolvedPaths {
            return new TellResolvedPaths(
                project: $directory,
                home: '/tell',
                configDirectory: '/tell/config',
                configFile: '/tell/config/tell.json',
                credentials: '/tell/.env',
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
        ->and($paths->toArray())->toHaveKey('credentials', '/tell/.env');
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
