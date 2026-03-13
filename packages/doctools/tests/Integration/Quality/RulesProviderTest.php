<?php declare(strict_types=1);

use Cognesy\Doctools\Quality\Data\DocsQualityConfig;
use Cognesy\Doctools\Quality\Services\RulesDiscovery;
use Cognesy\Doctools\Quality\Services\RulesLoader;
use Cognesy\Doctools\Quality\Services\RulesProvider;
use Cognesy\Utils\Files;

beforeEach(function () {
    $this->tempDir = sys_get_temp_dir() . '/doctools_quality_provider_test_' . uniqid();
    mkdir($this->tempDir, 0755, true);
});

afterEach(function () {
    if (is_dir($this->tempDir)) {
        Files::removeDirectory($this->tempDir);
    }
});

function writeRuleYaml(string $path, string $content): void {
    $directory = dirname($path);
    if (!is_dir($directory)) {
        mkdir($directory, 0755, true);
    }
    file_put_contents($path, $content);
}

it('overrides rules by id using discovery precedence', function () {
    $profilesDir = $this->tempDir . '/profiles';
    writeRuleYaml($profilesDir . '/instructor.yaml', <<<'YAML'
version: 1
rules:
  - id: shared.rule
    engine: regex
    scope: markdown
    pattern: '/foo/'
    message: 'base'
YAML);

    $docsRoot = $this->tempDir . '/docs';
    writeRuleYaml($docsRoot . '/.qa/rules.yaml', <<<'YAML'
version: 1
rules:
  - id: shared.rule
    engine: regex
    scope: markdown
    pattern: '/bar/'
    message: 'override'
YAML);

    $config = new DocsQualityConfig(
        docsRoot: $docsRoot,
        repoRoot: $this->tempDir,
        profile: 'instructor',
        extensions: ['md'],
    );

    $provider = new RulesProvider(
        discovery: new RulesDiscovery($profilesDir),
        loader: new RulesLoader(),
    );

    $rules = $provider->rulesFor($config);

    expect($rules)->toHaveCount(1);
    expect($rules[0]->pattern)->toBe('/bar/');
    expect($rules[0]->message)->toBe('override');
});

