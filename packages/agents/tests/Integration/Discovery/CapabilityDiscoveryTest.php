<?php declare(strict_types=1);

namespace Cognesy\Agents\Tests\Integration\Discovery;

use Cognesy\Agents\Capability\AgentCapabilityRegistry;
use Cognesy\Agents\Discovery\CapabilityDiscovery;
use Cognesy\Agents\Discovery\ComposerManifestReader;
use Cognesy\Agents\Discovery\Exceptions\CapabilityResolutionException;
use Cognesy\Agents\Discovery\Exceptions\ToolResolutionException;
use Cognesy\Agents\Template\AgentDefinitionLoader;
use Cognesy\Agents\Template\Factory\DefinitionLoopFactory;
use Cognesy\Agents\Tests\Support\Discovery\DiscoveredTool;
use Cognesy\Agents\Tests\Support\Discovery\LazyCapability;
use Cognesy\Agents\Tests\Support\Discovery\ManifestToolCapability;
use Cognesy\Agents\Tests\Support\Discovery\RequiresArgumentCapability;
use Cognesy\Agents\Tests\Support\Discovery\RootCapability;
use Cognesy\Agents\Tool\ToolRegistry;
use InvalidArgumentException;

function discoveryFixture(array $packages, array $rootPackage = ['name' => 'fixture/root']): string {
    $root = sys_get_temp_dir() . '/agents-discovery-' . bin2hex(random_bytes(6));
    mkdir($root . '/vendor/composer', 0777, true);
    file_put_contents($root . '/vendor/composer/installed.json', json_encode(
        ['packages' => $packages],
        JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT,
    ));
    file_put_contents($root . '/composer.json', json_encode(
        $rootPackage,
        JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT,
    ));
    return $root;
}

function removeDiscoveryFixture(string $root): void {
    $iterator = new \RecursiveIteratorIterator(
        new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS),
        \RecursiveIteratorIterator::CHILD_FIRST,
    );
    foreach ($iterator as $path) {
        match ($path->isDir()) {
            true => rmdir($path->getPathname()),
            false => unlink($path->getPathname()),
        };
    }
    rmdir($root);
}

function contributorPackage(array $capabilities = [], array $tools = []): array {
    return [
        'name' => 'fixture/contributor',
        'extra' => [
            'cognesy-agents' => [
                'capabilities' => $capabilities,
                'tools' => $tools,
            ],
        ],
    ];
}

it('parses Composer 2 and legacy installed metadata deterministically', function () {
    $fixtures = dirname(__DIR__, 2) . '/Fixtures/Discovery';
    $composer2 = (new ComposerManifestReader($fixtures . '/composer2/vendor'))->read();
    $legacy = (new ComposerManifestReader($fixtures . '/legacy/vendor'))->read();

    expect($composer2->errors()->all())->toBe([])
        ->and($composer2->manifests()->count())->toBe(1)
        ->and($composer2->manifests()->all()[0]->packageName)->toBe('fixture/contributor')
        ->and($composer2->manifests()->all()[0]->capabilities)->toBe(['lazy' => LazyCapability::class])
        ->and($composer2->manifests()->all()[0]->tools)->toBe(['discovered-tool' => DiscoveredTool::class])
        ->and($legacy->errors()->all())->toBe([])
        ->and($legacy->manifests()->all()[0]->capabilities)->toBe(['legacy-lazy' => LazyCapability::class]);
});

it('registers capability and tool factories without constructing their classes', function () {
    LazyCapability::$constructions = 0;
    DiscoveredTool::$constructions = 0;
    $fixtures = dirname(__DIR__, 2) . '/Fixtures/Discovery';
    $capabilities = new AgentCapabilityRegistry();
    $tools = new ToolRegistry();

    $result = CapabilityDiscovery::discover(
        $capabilities,
        $tools,
        $fixtures . '/composer2/vendor',
        $fixtures . '/root.json',
    );

    expect($result->capabilities()->all())->toBe(['lazy'])
        ->and($result->tools()->all())->toBe(['discovered-tool'])
        ->and($capabilities->has('lazy'))->toBeTrue()
        ->and($tools->has('discovered-tool'))->toBeTrue()
        ->and(LazyCapability::$constructions)->toBe(0)
        ->and(DiscoveredTool::$constructions)->toBe(0);

    expect($capabilities->get('lazy'))->toBeInstanceOf(LazyCapability::class)
        ->and($tools->get('discovered-tool'))->toBeInstanceOf(DiscoveredTool::class)
        ->and(LazyCapability::$constructions)->toBe(1)
        ->and(DiscoveredTool::$constructions)->toBe(1);
});

it('lets the root package override a vendor mapping', function () {
    $root = discoveryFixture(
        [contributorPackage(['shared' => LazyCapability::class])],
        [
            'name' => 'fixture/root',
            'extra' => ['cognesy-agents' => ['capabilities' => ['shared' => RootCapability::class]]],
        ],
    );

    try {
        $capabilities = new AgentCapabilityRegistry();
        CapabilityDiscovery::discover($capabilities, new ToolRegistry(), $root . '/vendor', $root . '/composer.json');
        expect($capabilities->get('shared'))->toBeInstanceOf(RootCapability::class);
    } finally {
        removeDiscoveryFixture($root);
    }
});

it('reports malformed declarations and deterministic vendor duplicates during discovery', function () {
    $duplicate = contributorPackage(['shared' => LazyCapability::class]);
    $duplicate['name'] = 'fixture/second';
    $root = discoveryFixture([
        contributorPackage(['Bad Name' => LazyCapability::class]),
        contributorPackage(['shared' => LazyCapability::class]),
        $duplicate,
        [
            'name' => 'fixture/malformed',
            'extra' => ['cognesy-agents' => ['tools' => ['list-entry']]],
        ],
    ]);

    try {
        $result = CapabilityDiscovery::discover(
            new AgentCapabilityRegistry(),
            new ToolRegistry(),
            $root . '/vendor',
            $root . '/composer.json',
        );
        $errors = implode("\n", $result->errors()->all());
        expect($errors)->toContain('invalid capabilities name')
            ->and($errors)->toContain('tools must be a name-to-class object')
            ->and($errors)->toContain("Duplicate capabilities name 'shared'");
    } finally {
        removeDiscoveryFixture($root);
    }
});

it('isolates a missing class until that specific capability is resolved', function () {
    $root = discoveryFixture([contributorPackage([
        'broken' => 'Fixture\\MissingCapability',
        'healthy' => LazyCapability::class,
    ])]);

    try {
        $capabilities = new AgentCapabilityRegistry();
        $result = CapabilityDiscovery::discover(
            $capabilities,
            new ToolRegistry(),
            $root . '/vendor',
            $root . '/composer.json',
        );

        expect($result->errors()->all())->toBe([])
            ->and($capabilities->get('healthy'))->toBeInstanceOf(LazyCapability::class);
        try {
            $capabilities->get('broken');
            $this->fail('Expected missing capability resolution to fail.');
        } catch (CapabilityResolutionException $exception) {
            expect($exception->getMessage())->toContain('fixture/contributor')
                ->and($exception->getMessage())->toContain('broken')
                ->and($exception->getMessage())->toContain('Fixture\\MissingCapability');
        }
    } finally {
        removeDiscoveryFixture($root);
    }
});

it('reports package name, entry name, and class for a wrong capability interface', function () {
    $root = discoveryFixture([contributorPackage(['wrong' => \stdClass::class])]);

    try {
        $capabilities = new AgentCapabilityRegistry();
        CapabilityDiscovery::discover($capabilities, new ToolRegistry(), $root . '/vendor', $root . '/composer.json');
        try {
            $capabilities->get('wrong');
            $this->fail('Expected capability resolution to fail.');
        } catch (CapabilityResolutionException $exception) {
            expect($exception->getMessage())->toContain('fixture/contributor')
                ->and($exception->getMessage())->toContain('wrong')
                ->and($exception->getMessage())->toContain(\stdClass::class);
        }
    } finally {
        removeDiscoveryFixture($root);
    }
});

it('rejects constructor requirements at resolution time with an actionable message', function () {
    $root = discoveryFixture([contributorPackage(['configured' => RequiresArgumentCapability::class])]);

    try {
        $capabilities = new AgentCapabilityRegistry();
        CapabilityDiscovery::discover($capabilities, new ToolRegistry(), $root . '/vendor', $root . '/composer.json');
        try {
            $capabilities->get('configured');
            $this->fail('Expected configured capability resolution to fail.');
        } catch (CapabilityResolutionException $exception) {
            expect($exception->getMessage())->toContain('fixture/contributor')
                ->and($exception->getMessage())->toContain('configured')
                ->and($exception->getMessage())->toContain(RequiresArgumentCapability::class)
                ->and($exception->getMessage())->toContain('register a factory instead');
        }
    } finally {
        removeDiscoveryFixture($root);
    }
});

it('uses the same lazy package-aware validation for tools', function () {
    $root = discoveryFixture([contributorPackage([], ['wrong-tool' => \stdClass::class])]);

    try {
        $tools = new ToolRegistry();
        CapabilityDiscovery::discover(new AgentCapabilityRegistry(), $tools, $root . '/vendor', $root . '/composer.json');
        expect(fn () => $tools->get('wrong-tool'))
            ->toThrow(ToolResolutionException::class, 'wrong-tool');
    } finally {
        removeDiscoveryFixture($root);
    }
});

it('fails a markdown definition before discovery and builds it after discovery', function () {
    $fixtures = dirname(__DIR__, 2) . '/Fixtures/Discovery';
    $definition = (new AgentDefinitionLoader())->loadFile($fixtures . '/discovered-agent.md');
    $capabilities = new AgentCapabilityRegistry();
    $tools = new ToolRegistry();
    $factory = new DefinitionLoopFactory($capabilities, $tools);

    expect(fn () => $factory->instantiateAgentLoop($definition))
        ->toThrow(InvalidArgumentException::class, 'CapabilityDiscovery::discover()');

    $root = discoveryFixture([contributorPackage([
        'manifest-capability' => ManifestToolCapability::class,
    ])]);
    try {
        CapabilityDiscovery::discover($capabilities, $tools, $root . '/vendor', $root . '/composer.json');
        $loop = $factory->instantiateAgentLoop($definition);
        expect($loop->tools()->names())->toContain('manifest_tool');
    } finally {
        removeDiscoveryFixture($root);
    }
});
