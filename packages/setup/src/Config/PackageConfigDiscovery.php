<?php declare(strict_types=1);

namespace Cognesy\Setup\Config;

use InvalidArgumentException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

final class PackageConfigDiscovery
{
    /**
     * @param list<string> $onlyPackages
     * @param list<string> $excludedPackages
     */
    public function discover(
        string $packagesRoot,
        string $targetRoot,
        array $onlyPackages = [],
        array $excludedPackages = [],
    ): ConfigPublishPlan {
        if (!is_dir($packagesRoot)) {
            throw new InvalidArgumentException("Packages root does not exist: {$packagesRoot}");
        }

        $entries = [];
        $destinations = [];
        foreach ($this->packageDirectories($packagesRoot) as $directory) {
            $package = basename($directory);
            if (!$this->isSelectedPackage($package, $onlyPackages, $excludedPackages)) {
                continue;
            }

            $meta = $this->packageMetadata($directory, $package);
            foreach ($meta['paths'] as $relativeSourcePath) {
                $sourcePath = $directory . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativeSourcePath);
                if (!is_file($sourcePath)) {
                    continue;
                }

                $relativePath = $this->configRelativePath($relativeSourcePath);
                $destinationPath = rtrim($targetRoot, DIRECTORY_SEPARATOR)
                    . DIRECTORY_SEPARATOR
                    . $meta['namespace']
                    . DIRECTORY_SEPARATOR
                    . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);

                if (isset($destinations[$destinationPath])) {
                    throw new InvalidArgumentException(
                        "Config publish destination conflict: {$destinationPath}",
                    );
                }

                $destinations[$destinationPath] = true;
                $entries[] = new ConfigPublishEntry(
                    package: $package,
                    namespace: $meta['namespace'],
                    sourcePath: $sourcePath,
                    relativePath: $relativePath,
                    destinationPath: $destinationPath,
                );
            }
        }

        usort($entries, fn(ConfigPublishEntry $left, ConfigPublishEntry $right): int => match (true) {
            $left->namespace !== $right->namespace => $left->namespace <=> $right->namespace,
            default => $left->relativePath <=> $right->relativePath,
        });

        return new ConfigPublishPlan($entries);
    }

    /** @return list<string> */
    private function packageDirectories(string $packagesRoot): array
    {
        $directories = glob(rtrim($packagesRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . '*', GLOB_ONLYDIR) ?: [];
        $directories = array_values(array_filter(
            $directories,
            fn(string $directory): bool => is_file($directory . DIRECTORY_SEPARATOR . 'composer.json'),
        ));

        sort($directories);

        return $directories;
    }

    /**
     * @param list<string> $onlyPackages
     * @param list<string> $excludedPackages
     */
    private function isSelectedPackage(string $package, array $onlyPackages, array $excludedPackages): bool
    {
        if ($onlyPackages !== [] && !in_array($package, $onlyPackages, true)) {
            return false;
        }

        return !in_array($package, $excludedPackages, true);
    }

    /**
     * @return array{namespace:string, paths:list<string>}
     */
    private function packageMetadata(string $directory, string $package): array
    {
        $composerPath = $directory . DIRECTORY_SEPARATOR . 'composer.json';
        $raw = file_get_contents($composerPath);
        $data = is_string($raw) && $raw !== ''
            ? json_decode($raw, true)
            : [];
        $config = is_array($data['extra']['instructor']['config'] ?? null)
            ? $data['extra']['instructor']['config']
            : [];

        $namespace = is_string($config['namespace'] ?? null) && trim((string) $config['namespace']) !== ''
            ? trim((string) $config['namespace'])
            : $package;

        $paths = array_values(array_filter(
            array_map(
                fn(mixed $path): string => trim((string) $path),
                is_array($config['paths'] ?? null) ? $config['paths'] : [],
            ),
            fn(string $path): bool => $path !== '',
        ));

        if ($paths === []) {
            $paths = $this->fallbackPaths($directory);
        }

        sort($paths);
        $paths = $this->deduplicateLogicalPaths($paths);

        return [
            'namespace' => $namespace,
            'paths' => $paths,
        ];
    }

    /** @return list<string> */
    private function fallbackPaths(string $directory): array
    {
        $configRoot = $directory . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR . 'config';
        if (!is_dir($configRoot)) {
            return [];
        }

        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($configRoot));
        $paths = [];
        foreach ($iterator as $item) {
            if (!$item->isFile()) {
                continue;
            }

            $path = $item->getPathname();
            $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
            if (!in_array($extension, ['yaml', 'yml', 'php'], true)) {
                continue;
            }

            $paths[] = str_replace(
                DIRECTORY_SEPARATOR,
                '/',
                substr($path, strlen($directory) + 1),
            );
        }

        sort($paths);

        return $paths;
    }

    /**
     * @param list<string> $paths
     * @return list<string>
     */
    private function deduplicateLogicalPaths(array $paths): array
    {
        $unique = [];
        foreach ($paths as $path) {
            $key = preg_replace('~\.(?:yaml|yml|php)$~i', '', $this->configRelativePath($path)) ?? $path;
            if (array_key_exists($key, $unique)) {
                continue;
            }
            $unique[$key] = $path;
        }

        return array_values($unique);
    }

    private function configRelativePath(string $path): string
    {
        $normalized = str_replace('\\', '/', $path);
        $prefix = 'resources/config/';

        return match (true) {
            str_starts_with($normalized, $prefix) => substr($normalized, strlen($prefix)),
            default => ltrim($normalized, '/'),
        };
    }
}
