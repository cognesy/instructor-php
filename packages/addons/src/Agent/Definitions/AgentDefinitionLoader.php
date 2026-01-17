<?php declare(strict_types=1);

namespace Cognesy\Addons\Agent\Definitions;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

final class AgentDefinitionLoader
{
    private AgentDefinitionParser $parser;

    public function __construct(?AgentDefinitionParser $parser = null)
    {
        $this->parser = $parser ?? new AgentDefinitionParser();
    }

    public function loadFromFile(string $path): AgentDefinitionLoadResult
    {
        return $this->loadFile($path);
    }

    public function loadFromDirectory(string $path, bool $recursive = false): AgentDefinitionLoadResult
    {
        if (!is_dir($path)) {
            return new AgentDefinitionLoadResult();
        }

        $definitions = [];
        $errors = [];

        foreach ($this->listYamlFiles($path, $recursive) as $file) {
            $result = $this->loadFile($file);

            foreach ($result->definitions as $id => $definition) {
                $definitions[$id] = $definition;
            }

            $errors = array_merge($errors, $result->errors);
        }

        return new AgentDefinitionLoadResult($definitions, $errors);
    }

    /**
     * @param array<int, string> $paths
     */
    public function loadFromPaths(array $paths, bool $recursive = false): AgentDefinitionLoadResult
    {
        $definitions = [];
        $errors = [];

        foreach ($paths as $path) {
            $result = is_dir($path)
                ? $this->loadFromDirectory($path, $recursive)
                : $this->loadFromFile($path);

            foreach ($result->definitions as $id => $definition) {
                $definitions[$id] = $definition;
            }

            $errors = array_merge($errors, $result->errors);
        }

        return new AgentDefinitionLoadResult($definitions, $errors);
    }

    private function loadFile(string $path): AgentDefinitionLoadResult
    {
        try {
            $definition = $this->parser->parseYamlFile($path);
        } catch (\Throwable $e) {
            return new AgentDefinitionLoadResult(
                definitions: [],
                errors: [$path => $e->getMessage()],
            );
        }

        return new AgentDefinitionLoadResult(
            definitions: [$definition->id => $definition],
            errors: [],
        );
    }

    /**
     * @return array<int, string>
     */
    private function listYamlFiles(string $path, bool $recursive): array
    {
        $files = [];

        if ($recursive) {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($path)
            );

            foreach ($iterator as $file) {
                if (!$file->isFile()) {
                    continue;
                }
                $extension = strtolower($file->getExtension());
                if ($extension === 'yml' || $extension === 'yaml') {
                    $files[] = $file->getPathname();
                }
            }
        } else {
            $matches = glob($path . '/*.{yml,yaml}', GLOB_BRACE);
            if ($matches !== false) {
                $files = $matches;
            }
        }

        sort($files);

        return $files;
    }
}
