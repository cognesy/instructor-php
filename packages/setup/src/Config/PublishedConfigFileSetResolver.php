<?php declare(strict_types=1);

namespace Cognesy\Setup\Config;

use Cognesy\Config\ConfigFileSet;
use InvalidArgumentException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

final class PublishedConfigFileSetResolver
{
    public function resolve(string $targetRoot, ConfigPublishPlan $plan): ConfigFileSet
    {
        $packages = [];
        foreach ($plan->entries() as $entry) {
            $packages[$entry->namespace] = true;
        }

        $files = [];
        foreach (array_keys($packages) as $namespace) {
            $namespaceRoot = $targetRoot . DIRECTORY_SEPARATOR . $namespace;
            if (!is_dir($namespaceRoot)) {
                throw new InvalidArgumentException("Published config namespace does not exist: {$namespaceRoot}");
            }

            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($namespaceRoot));
            foreach ($iterator as $item) {
                if (!$item->isFile()) {
                    continue;
                }

                $path = $item->getPathname();
                $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
                if (!in_array($extension, ['yaml', 'yml', 'php'], true)) {
                    continue;
                }

                $relative = substr($path, strlen($namespaceRoot) + 1);
                $key = $namespace . '.' . preg_replace('~\.(?:yaml|yml|php)$~i', '', str_replace(DIRECTORY_SEPARATOR, '.', $relative));
                $files[$key] = $path;
            }
        }

        ksort($files);

        return ConfigFileSet::fromKeyedFiles($files);
    }
}
