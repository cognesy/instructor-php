<?php declare(strict_types=1);

namespace Cognesy\Doctools\Docgen;

use Cognesy\Doctools\Docgen\Data\DiscoveredCheatsheet;

/**
 * Generates a cheatsheets index/listing page.
 */
class CheatsheetsIndexGenerator
{
    /**
     * Generate markdown content for cheatsheets index page.
     * @param DiscoveredCheatsheet[] $cheatsheets
     */
    public function generate(array $cheatsheets): string
    {
        $content = "# Cheatsheets\n\n";
        $content .= "Quick reference guides for each package — code-verified API surfaces and usage patterns.\n\n";

        foreach ($cheatsheets as $cheatsheet) {
            $content .= $this->formatEntry($cheatsheet);
        }

        return $content;
    }

    /**
     * Generate and write the cheatsheets index file.
     * @param DiscoveredCheatsheet[] $cheatsheets
     */
    public function generateToFile(array $cheatsheets, string $targetPath): bool
    {
        $dir = dirname($targetPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $content = $this->generate($cheatsheets);
        return file_put_contents($targetPath, $content) !== false;
    }

    private function formatEntry(DiscoveredCheatsheet $cheatsheet): string
    {
        $link = $cheatsheet->getTargetName() . '.md';
        $description = $cheatsheet->description ?: 'Quick reference for ' . $cheatsheet->title;

        return "## [{$cheatsheet->title}]({$link})\n\n{$description}\n\n";
    }
}
