<?php declare(strict_types=1);

use Cognesy\Doctools\Quality\Data\DocsQualityConfig;
use Cognesy\Doctools\Quality\Services\RulesDiscovery;
use Cognesy\Utils\Files;
use Symfony\Component\Filesystem\Path;

beforeEach(function () {
    $this->tempDir = sys_get_temp_dir() . '/doctools_quality_discovery_test_' . uniqid();
    mkdir($this->tempDir, 0755, true);
});

afterEach(function () {
    if (is_dir($this->tempDir)) {
        Files::removeDirectory($this->tempDir);
    }
});

function writeYaml(string $path, string $contents = "version: 1\nrules: []\n"): void {
    $directory = dirname($path);
    if (!is_dir($directory)) {
        mkdir($directory, 0755, true);
    }
    file_put_contents($path, $contents);
}

it('discovers profile, package-local and explicit rules in order', function () {
    $profilesDir = $this->tempDir . '/profiles';
    $profileFile = $profilesDir . '/instructor.yaml';
    writeYaml($profileFile);

    $docsRoot = $this->tempDir . '/docs';
    $localFile = $docsRoot . '/.qa/rules.yaml';
    writeYaml($localFile);

    $explicitFile = $this->tempDir . '/explicit.yaml';
    writeYaml($explicitFile);

    $config = new DocsQualityConfig(
        docsRoot: $docsRoot,
        repoRoot: $this->tempDir,
        profile: 'instructor',
        extensions: ['md'],
        ruleFiles: [$explicitFile],
    );

    $discovered = (new RulesDiscovery($profilesDir))->discover($config);

    expect($discovered)->toBe([
        Path::canonicalize($profileFile),
        Path::canonicalize($localFile),
        Path::canonicalize($explicitFile),
    ]);
});

it('throws on unknown profile', function () {
    $config = new DocsQualityConfig(
        docsRoot: $this->tempDir . '/docs',
        repoRoot: $this->tempDir,
        profile: 'missing',
        extensions: ['md'],
    );

    expect(fn() => (new RulesDiscovery($this->tempDir . '/profiles'))->discover($config))
        ->toThrow(InvalidArgumentException::class, 'Unknown docs quality profile');
});

