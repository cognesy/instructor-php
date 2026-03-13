<?php declare(strict_types=1);

namespace Cognesy\Doctools\Docgen;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Strips YAML frontmatter from all files in a directory.
 *
 * MDX (used by Mintlify) does not support YAML frontmatter delimited by `---`.
 * Source markdown files may contain frontmatter for MkDocs or other tooling,
 * so this class removes it after files are copied into the build directory.
 */
class FrontmatterStripper
{
    /**
     * Strip YAML frontmatter from all files with the given extension in a directory tree.
     *
     * @return int Number of files modified
     */
    public function stripDirectory(string $directory, string $extension = 'mdx'): int
    {
        if (!is_dir($directory)) {
            return 0;
        }

        $modified = 0;
        $items = new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS);
        $iterator = new RecursiveIteratorIterator($items, RecursiveIteratorIterator::SELF_FIRST);

        foreach ($iterator as $fileInfo) {
            /** @var SplFileInfo $fileInfo */
            if (!$fileInfo->isFile() || $fileInfo->getExtension() !== $extension) {
                continue;
            }

            if ($this->stripFile($fileInfo->getPathname())) {
                $modified++;
            }
        }

        return $modified;
    }

    /**
     * Strip YAML frontmatter from a single file. Returns true if the file was modified.
     */
    public function stripFile(string $filePath): bool
    {
        $content = file_get_contents($filePath);
        if ($content === false) {
            return false;
        }

        $stripped = $this->stripContent($content);
        if ($stripped === $content) {
            return false;
        }

        file_put_contents($filePath, $stripped);
        return true;
    }

    /**
     * Remove YAML frontmatter from content string.
     *
     * Frontmatter is defined as content between opening `---` (at start of file)
     * and closing `---`, optionally preceded by whitespace/newlines.
     */
    public function stripContent(string $content): string
    {
        // Must start with --- (possibly after BOM or whitespace)
        if (!preg_match('/\A\s*---\s*\n/', $content)) {
            return $content;
        }

        // Find closing ---
        $withoutLeading = preg_replace('/\A\s*/', '', $content) ?? $content;
        $endPos = strpos($withoutLeading, "\n---", 3);
        if ($endPos === false) {
            return $content;
        }

        // Skip past the closing --- and any trailing newline
        $afterFrontmatter = substr($withoutLeading, $endPos + 4);
        return ltrim($afterFrontmatter, "\r\n");
    }
}
