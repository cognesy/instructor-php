<?php declare(strict_types=1);

namespace Cognesy\InstructorHub\Config;

use Cognesy\Config\BasePath;
use Symfony\Component\Yaml\Yaml;

final readonly class ExampleSourcesConfig
{
    public function __construct(
        private string $path = 'config/examples.yaml',
    ) {}

    public function load(): ExampleSources
    {
        $config = $this->loadConfig();
        if ($config === null) {
            return ExampleSources::legacy(BasePath::get('examples'));
        }

        $sources = $this->sourcesFromConfig($config);
        if ($sources->isEmpty()) {
            return ExampleSources::legacy(BasePath::get('examples'));
        }

        return $sources;
    }

    private function loadConfig(): ?array
    {
        $fullPath = BasePath::get($this->path);
        if (!file_exists($fullPath)) {
            return null;
        }

        $content = file_get_contents($fullPath);
        if ($content === false) {
            return null;
        }

        $config = Yaml::parse($content);
        if (!is_array($config)) {
            return null;
        }

        return $config;
    }

    private function sourcesFromConfig(array $config): ExampleSources
    {
        $rawSources = $config['sources'] ?? [];
        if (!is_array($rawSources)) {
            return ExampleSources::fromArray([]);
        }

        $sources = [];
        foreach ($rawSources as $rawSource) {
            $source = $this->sourceFromConfig($rawSource);
            if ($source === null) {
                continue;
            }
            $sources[] = $source;
        }

        return ExampleSources::fromArray($sources);
    }

    private function sourceFromConfig(mixed $rawSource): ?ExampleSource
    {
        if (!is_array($rawSource)) {
            return null;
        }

        $path = $rawSource['path'] ?? null;
        if (!is_string($path) || $path === '') {
            return null;
        }

        $id = $this->sourceId($rawSource, $path);
        $resolvedPath = BasePath::get($path);

        return ExampleSource::fromPath($id, $resolvedPath);
    }

    private function sourceId(array $rawSource, string $path): string
    {
        $package = $rawSource['package'] ?? null;
        if (is_string($package) && $package !== '') {
            return $package;
        }

        $id = $rawSource['id'] ?? null;
        if (is_string($id) && $id !== '') {
            return $id;
        }

        return $this->pathFallbackId($path);
    }

    private function pathFallbackId(string $path): string
    {
        $trimmed = trim($path, '/\\');
        $basename = basename($trimmed);
        if ($basename !== '') {
            return $basename;
        }

        return 'examples';
    }
}
