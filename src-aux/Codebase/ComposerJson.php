<?php

namespace Cognesy\Aux\Codebase;

class ComposerJson {
    private string $projectPath;
    private string $composerJsonPath;
    private array $composerData = [];
    /** @var string[] */
    private array $psr4Paths;
    /** @var string[] */
    private array $filesPaths;

    public function __construct(string $projectPath) {
        $this->projectPath = $projectPath;
        $this->composerJsonPath = $this->findComposerJson($this->projectPath);
        if (!$this->composerJsonPath) {
            throw new \RuntimeException("composer.json not found in the project path or its parent directories.");
        }
        $this->composerData = $this->readComposerData($this->composerJsonPath);
        $this->psr4Paths = $this->readPsr4Paths($this->composerData);
        $this->filesPaths = $this->readFilesPaths($this->composerData);
    }

    public function getPsr4Paths(): array {
        return $this->psr4Paths;
    }

    public function getFilesPaths(): array {
        return $this->filesPaths;
    }

    // INTERNAL /////////////////////////////////////////////

    private function findComposerJson(string $path): ?string {
        $currentPath = realpath($path);
        while ($currentPath !== false) {
            $composerPath = $currentPath . DIRECTORY_SEPARATOR . 'composer.json';
            if (file_exists($composerPath)) {
                return $composerPath;
            }
            $parentPath = dirname($currentPath);
            if ($parentPath === $currentPath) {
                // We've reached the root directory
                break;
            }
            $currentPath = $parentPath;
        }
        return null;
    }

    private function readComposerData(string $composerJsonPath): array {
        if (!$composerJsonPath) {
            throw new \RuntimeException("composer.json not found in the project path or its parent directories.");
        }
        $composerJson = json_decode(file_get_contents($composerJsonPath), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException("Failed to parse composer.json: " . json_last_error_msg());
        }
        return $composerJson;
    }

    private function readPsr4Paths(array $composerJson): array {
        $paths = [];
        foreach ($composerJson['autoload']['psr-4'] as $namespace => $path) {
            $paths[] = dirname($this->composerJsonPath) . DIRECTORY_SEPARATOR . $path;
        }
        return $paths;
    }

    private function readFilesPaths(array $composerJson): array {
        $paths = [];
        foreach ($composerJson['autoload']['files'] as $file) {
            $paths[] = $file;
        }
        return $paths;
    }
}
