<?php declare(strict_types=1);

use Cognesy\Setup\Config\PackageConfigDiscovery;

it('builds a deterministic publish plan from composer metadata', function () {
    $root = setupConfigRoot('metadata');
    setupPackageWithConfigMetadata(
        $root,
        'polyglot',
        'polyglot',
        [
            'resources/config/llm/default.yaml' => "driver: openai\n",
            'resources/config/llm/presets/openai.yaml' => "driver: openai\n",
        ],
    );
    setupPackageWithConfigMetadata(
        $root,
        'http-client',
        'http-client',
        [
            'resources/config/http/default.yaml' => "driver: curl\n",
        ],
    );

    $plan = (new PackageConfigDiscovery())->discover($root . '/packages', $root . '/published');

    expect($plan->packages())->toBe(['http-client', 'polyglot'])
        ->and(array_map(fn($entry) => $entry->destinationPath, $plan->entries()))->toBe([
            $root . '/published/http-client/http/default.yaml',
            $root . '/published/polyglot/llm/default.yaml',
            $root . '/published/polyglot/llm/presets/openai.yaml',
        ]);
});

it('falls back to scanning resources config files when metadata is absent', function () {
    $root = setupConfigRoot('fallback');
    setupPackageWithoutMetadata(
        $root,
        'auxiliary',
        [
            'resources/config/web/default.yaml' => "driver: scraper\n",
            'resources/config/web/scrapers/firecrawl.yaml' => "enabled: true\n",
        ],
    );

    $plan = (new PackageConfigDiscovery())->discover($root . '/packages', $root . '/published');

    expect($plan->count())->toBe(2)
        ->and($plan->entries()[0]->destinationPath)->toBe($root . '/published/auxiliary/web/default.yaml')
        ->and($plan->entries()[1]->destinationPath)->toBe($root . '/published/auxiliary/web/scrapers/firecrawl.yaml');
});

it('supports include and exclude package filters', function () {
    $root = setupConfigRoot('filters');
    setupPackageWithoutMetadata($root, 'polyglot', [
        'resources/config/llm/default.yaml' => "driver: openai\n",
    ]);
    setupPackageWithoutMetadata($root, 'http-client', [
        'resources/config/http/default.yaml' => "driver: curl\n",
    ]);

    $included = (new PackageConfigDiscovery())->discover($root . '/packages', $root . '/published', ['polyglot']);
    $excluded = (new PackageConfigDiscovery())->discover($root . '/packages', $root . '/published', [], ['polyglot']);

    expect($included->packages())->toBe(['polyglot'])
        ->and($excluded->packages())->toBe(['http-client']);
});

it('fails when two packages claim the same published destination', function () {
    $root = setupConfigRoot('conflict');
    setupPackageWithConfigMetadata(
        $root,
        'polyglot',
        'shared',
        ['resources/config/llm/default.yaml' => "driver: openai\n"],
    );
    setupPackageWithConfigMetadata(
        $root,
        'other-polyglot',
        'shared',
        ['resources/config/llm/default.yaml' => "driver: anthropic\n"],
    );

    expect(fn() => (new PackageConfigDiscovery())->discover($root . '/packages', $root . '/published'))
        ->toThrow(InvalidArgumentException::class, 'Config publish destination conflict');
});

it('deduplicates yaml and yml variants for the same logical config key', function () {
    $root = setupConfigRoot('dedupe');
    setupPackageWithoutMetadata($root, 'hub', [
        'resources/config/examples.yaml' => "examples: []\n",
        'resources/config/examples.yml' => "examples: [dup]\n",
    ]);

    $plan = (new PackageConfigDiscovery())->discover($root . '/packages', $root . '/published');

    expect($plan->count())->toBe(1)
        ->and($plan->entries()[0]->sourcePath)->toContain('examples.yaml');
});

function setupConfigRoot(string $suffix): string
{
    $root = sys_get_temp_dir() . '/setup-config-discovery-' . $suffix . '-' . uniqid('', true);
    mkdir($root . '/packages', 0777, true);

    return $root;
}

/**
 * @param array<string, string> $files
 */
function setupPackageWithConfigMetadata(
    string $root,
    string $package,
    string $namespace,
    array $files,
): void {
    $base = $root . '/packages/' . $package;
    mkdir($base, 0777, true);

    $composer = [
        'extra' => [
            'instructor' => [
                'config' => [
                    'namespace' => $namespace,
                    'paths' => array_keys($files),
                ],
            ],
        ],
    ];
    file_put_contents($base . '/composer.json', json_encode($composer, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));

    foreach ($files as $relative => $content) {
        $path = $base . '/' . $relative;
        $directory = dirname($path);
        if (!is_dir($directory)) {
            mkdir($directory, 0777, true);
        }
        file_put_contents($path, $content);
    }
}

/**
 * @param array<string, string> $files
 */
function setupPackageWithoutMetadata(string $root, string $package, array $files): void
{
    $base = $root . '/packages/' . $package;
    mkdir($base, 0777, true);
    file_put_contents($base . '/composer.json', '{}');

    foreach ($files as $relative => $content) {
        $path = $base . '/' . $relative;
        $directory = dirname($path);
        if (!is_dir($directory)) {
            mkdir($directory, 0777, true);
        }
        file_put_contents($path, $content);
    }
}
