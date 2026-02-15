<?php declare(strict_types=1);

namespace Cognesy\Doctor\Docgen;

use Cognesy\Doctor\Docgen\Data\DiscoveredPackage;

/**
 * Generates a packages index/listing page.
 * Uses docs/packages.md if it exists, otherwise generates a basic listing.
 */
class PackagesIndexGenerator
{
    private string $sourceFile;

    public function __construct(string $sourceFile = '')
    {
        $this->sourceFile = $sourceFile;
    }

    /**
     * Generate markdown content for packages index page
     * @param DiscoveredPackage[] $packages
     */
    public function generate(array $packages): string
    {
        // Use manually maintained source file if it exists
        if ($this->sourceFile !== '' && file_exists($this->sourceFile)) {
            $content = file_get_contents($this->sourceFile);
            if ($content !== false) {
                return $content;
            }
        }

        // Fallback to auto-generated listing
        return $this->generateListing($packages);
    }

    /**
     * Generate and write the packages index file
     * @param DiscoveredPackage[] $packages
     */
    public function generateToFile(array $packages, string $targetPath): bool
    {
        $dir = dirname($targetPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $content = $this->generate($packages);
        return file_put_contents($targetPath, $content) !== false;
    }

    private function generateListing(array $packages): string
    {
        $content = "# Packages\n\n";
        $content .= "Instructor for PHP is built from modular packages that can be used independently or together.\n\n";

        foreach ($packages as $package) {
            $content .= $this->formatPackageEntry($package);
        }

        return $content;
    }

    private function formatPackageEntry(DiscoveredPackage $package): string
    {
        $title = $package->getTitle();
        $description = $package->description ?: 'Documentation for ' . $title;
        $link = $package->targetDir . '/index';

        return "## [{$title}]({$link})\n\n{$description}\n\n";
    }
}
