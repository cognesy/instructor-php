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
final readonly class DirectoryDirsStream implements Stream
{
    public function __construct(
        private string $root,
        private bool $recursive
    ) {}

    #[\Override]
    public function getIterator(): Iterator {
        if ($this->recursive) {
            $it = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($this->root, FilesystemIterator::SKIP_DOTS),
                RecursiveIteratorIterator::SELF_FIRST,
            );
            foreach ($it as $info) {
                if ($info->isDir()) {
                    yield $info->getPathname();
                }
            }
            return;
        }
        foreach (new DirectoryIterator($this->root) as $info) {
            if ($info->isDir() && !$info->isDot()) {
                yield $info->getPathname();
            }
        }
    }
}
