<?php declare(strict_types=1);

namespace Cognesy\Setup\Resources;

use InvalidArgumentException;

final class PackageResourceLocator
{
    /**
     * @param list<string> $onlyPackages
     * @param list<string> $excludedPackages
     * @return list<PackageResource>
     */
    public function locate(
        string $packagesRoot,
        string $targetRoot,
        array $onlyPackages = [],
        array $excludedPackages = [],
    ): array {
        if (!is_dir($packagesRoot)) {
            throw new InvalidArgumentException("Packages root does not exist: {$packagesRoot}");
        }

        $directories = glob(rtrim($packagesRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . '*', GLOB_ONLYDIR) ?: [];
        $resources = [];
        foreach ($directories as $directory) {
            if (!$this->isPackageDirectory($directory)) {
                continue;
            }

            $package = basename($directory);
            if (!$this->isSelectedPackage($package, $onlyPackages, $excludedPackages)) {
                continue;
            }

            $sourcePath = $directory . DIRECTORY_SEPARATOR . 'resources';
            if (!$this->hasPublishableResources($sourcePath)) {
                continue;
            }

            $resources[] = new PackageResource(
                package: $package,
                sourcePath: $sourcePath,
                destinationPath: rtrim($targetRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $package,
            );
        }

        usort($resources, fn(PackageResource $a, PackageResource $b) => $a->package <=> $b->package);

        return $resources;
    }

    private function isPackageDirectory(string $directory): bool {
        return is_file($directory . DIRECTORY_SEPARATOR . 'composer.json');
    }

    /**
     * @param list<string> $onlyPackages
     * @param list<string> $excludedPackages
     */
    private function isSelectedPackage(string $package, array $onlyPackages, array $excludedPackages): bool {
        if ($onlyPackages !== [] && !in_array($package, $onlyPackages, true)) {
            return false;
        }

        return !in_array($package, $excludedPackages, true);
    }

    private function hasPublishableResources(string $resourcePath): bool {
        if (!is_dir($resourcePath)) {
            return false;
        }

        $files = scandir($resourcePath);
        if (!is_array($files)) {
            return false;
        }

        return count($files) > 2;
    }
}

