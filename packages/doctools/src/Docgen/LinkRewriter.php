<?php declare(strict_types=1);

namespace Cognesy\Doctools\Docgen;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Rewrites internal documentation links to match the target format's conventions.
 *
 * Source docs use `.md` links (standard for MkDocs). Mintlify requires extensionless
 * paths, so this class strips `.md` from relative links when the target format is mintlify.
 */
class LinkRewriter
{
    private string $extension;
    private bool $shouldRewrite;

    public function __construct(string $format)
    {
        $this->extension = $format === 'mintlify' ? 'mdx' : 'md';
        $this->shouldRewrite = $format === 'mintlify';
    }

    /**
     * Rewrite links in all doc files under the given directory.
     * No-op for formats that use .md links natively (e.g. MkDocs).
     */
    public function rewriteDirectory(string $directory): void
    {
        if (!$this->shouldRewrite || !is_dir($directory)) {
            return;
        }

        $items = new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS);
        $iterator = new RecursiveIteratorIterator($items, RecursiveIteratorIterator::SELF_FIRST);

        foreach ($iterator as $fileInfo) {
            /** @var SplFileInfo $fileInfo */
            if ($fileInfo->isFile() && $fileInfo->getExtension() === $this->extension) {
                $this->rewriteFile($fileInfo->getPathname());
            }
        }
    }

    /**
     * Rewrite links in a single file. Returns true if the file was modified.
     */
    public function rewriteFile(string $filePath): bool
    {
        if (!$this->shouldRewrite) {
            return false;
        }

        $content = file_get_contents($filePath);
        if ($content === false) {
            return false;
        }

        $updated = $this->rewriteContent($content);
        if ($updated === $content) {
            return false;
        }

        file_put_contents($filePath, $updated);
        return true;
    }

    /**
     * Rewrite .md link references in content string.
     *
     * Transforms relative .md links to extensionless links:
     *   (path/to/page.md)        → (path/to/page)
     *   (path/to/page.md#anchor) → (path/to/page#anchor)
     *
     * External links (http://, https://) are left untouched.
     */
    public function rewriteContent(string $content): string
    {
        if (!$this->shouldRewrite) {
            return $content;
        }

        $result = preg_replace(
            '/\((?!https?:\/\/)([^()]*?)\.md(#[^)]*)?\)/',
            '($1$2)',
            $content,
        );

        return $result ?? $content;
    }
}
