<?php declare(strict_types=1);

namespace Cognesy\Doctools\Docgen;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Strips source-only metadata from generated MDX files.
 *
 * MDX (used by Mintlify) does not support YAML frontmatter delimited by `---`.
 * Source markdown files may contain frontmatter for MkDocs or other tooling,
 * so this class removes it after files are copied into the build directory.
 * Markdownlint directives are HTML comments, which MDX treats as malformed
 * JSX, so those directives are removed from build output as well.
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
     * Remove YAML frontmatter and markdownlint directives from content.
     *
     * Frontmatter is defined as content between opening `---` (at start of file)
     * and closing `---`, optionally preceded by whitespace/newlines.
     */
    public function stripContent(string $content): string
    {
        $stripped = $content;
        if (preg_match('/\A\s*---\s*\n/', $content)) {
            $withoutLeading = preg_replace('/\A\s*/', '', $content) ?? $content;
            $endPos = strpos($withoutLeading, "\n---", 3);
            if ($endPos !== false) {
                $afterFrontmatter = substr($withoutLeading, $endPos + 4);
                $stripped = ltrim($afterFrontmatter, "\r\n");
            }
        }

        return preg_replace(
            '/^[ \t]*<!--\s*markdownlint-(?:disable|enable)\b[^\r\n]*-->[ \t]*(?:\R[ \t]*)*/m',
            '',
            $stripped,
        ) ?? $stripped;
    }
}
