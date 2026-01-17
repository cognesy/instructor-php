<?php declare(strict_types=1);

namespace Cognesy\Doctor\Docgen;

use Cognesy\Config\BasePath;
use Cognesy\Doctor\Docgen\Data\DiscoveredPackage;

class PackageDiscovery
{
    private string $packagesDir;
    private array $descriptions;
    private array $targetDirs;

    public function __construct(
        string $packagesDir = 'packages',
        array $descriptions = [],
        array $targetDirs = [],
    ) {
        $this->packagesDir = BasePath::get($packagesDir);
        $this->descriptions = $descriptions;
        $this->targetDirs = $targetDirs;
    }

    /**
     * Discover all packages that have documentation
     * @return DiscoveredPackage[]
     */
    public function discover(): array
    {
        $packages = [];
        $entries = scandir($this->packagesDir);

        if ($entries === false) {
            return $packages;
        }

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $packagePath = $this->packagesDir . '/' . $entry;
            $docsPath = $packagePath . '/docs';

            if (!is_dir($packagePath) || !is_dir($docsPath)) {
                continue;
            }

            $packages[] = new DiscoveredPackage(
                name: $entry,
                docsPath: $docsPath,
                description: $this->getDescription($entry, $packagePath),
                targetDir: $this->getTargetDir($entry),
            );
        }

        // Sort by name
        usort($packages, fn($a, $b) => strcmp($a->name, $b->name));

        return $packages;
    }

    /**
     * Get description from config or composer.json
     */
    private function getDescription(string $packageName, string $packagePath): string
    {
        // Check if override exists in config
        if (isset($this->descriptions[$packageName])) {
            return $this->descriptions[$packageName];
        }

        // Try to read from composer.json
        $composerPath = $packagePath . '/composer.json';
        if (file_exists($composerPath)) {
            $content = file_get_contents($composerPath);
            if ($content !== false) {
                $composer = json_decode($content, true);
                if (isset($composer['description'])) {
                    return $composer['description'];
                }
            }
        }

        return '';
    }

    /**
     * Get target directory name for package docs
     * All packages go under packages/ subdirectory
     */
    private function getTargetDir(string $packageName): string
    {
        $baseName = $this->targetDirs[$packageName] ?? $packageName;
        return 'packages/' . $baseName;
    }
}
