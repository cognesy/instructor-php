<?php declare(strict_types=1);

namespace Cognesy\Stream\Sources\Filesystem;

use Cognesy\Stream\Contracts\Stream;
use DirectoryIterator;
use FilesystemIterator;
use Iterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * @implements Stream<int, string>
 */
final readonly class DirectoryFilesStream implements Stream
{
    /**
     * @param array<string,true>|null $extensions
     */
    public function __construct(
        private string $root,
        private ?array $extensions,
        private bool $recursive
    ) {}

    #[\Override]
    public function getIterator(): Iterator {
        if ($this->recursive) {
            $it = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($this->root, FilesystemIterator::SKIP_DOTS),
            );
            foreach ($it as $info) {
                if (!$info->isFile()) {
                    continue;
                }
                if ($this->extensions !== null) {
                    $ext = strtolower(pathinfo($info->getFilename(), PATHINFO_EXTENSION));
                    if ($ext === '' || !isset($this->extensions[$ext])) {
                        continue;
                    }
                }
                yield $info->getPathname();
            }
            return;
        }
        foreach (new DirectoryIterator($this->root) as $info) {
            if ($info->isFile()) {
                if ($this->extensions !== null) {
                    $ext = strtolower(pathinfo($info->getFilename(), PATHINFO_EXTENSION));
                    if ($ext === '' || !isset($this->extensions[$ext])) {
                        continue;
                    }
                }
                yield $info->getPathname();
            }
        }
    }
}
