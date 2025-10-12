<?php declare(strict_types=1);

namespace Cognesy\Utils\Stream\Filesystem;

use Cognesy\Utils\Stream\Stream;
use DirectoryIterator;
use FilesystemIterator;
use Iterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * @implements Stream<int, string>
 */
final readonly class DirectoryAnyStream implements Stream
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
        return (function () {
            if ($this->recursive) {
                $it = new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator($this->root, FilesystemIterator::SKIP_DOTS),
                    RecursiveIteratorIterator::SELF_FIRST,
                );
                foreach ($it as $info) {
                    if (!$info->isDir()) {
                        if ($this->extensions !== null) {
                            $ext = strtolower(pathinfo($info->getFilename(), PATHINFO_EXTENSION));
                            if ($ext === '' || !isset($this->extensions[$ext])) {
                                continue;
                            }
                        }
                    }
                    yield $info->getPathname();
                }
                return;
            }
            foreach (new DirectoryIterator($this->root) as $info) {
                if ($info->isDot()) {
                    continue;
                }
                if ($info->isFile() && $this->extensions !== null) {
                    $ext = strtolower(pathinfo($info->getFilename(), PATHINFO_EXTENSION));
                    if ($ext === '' || !isset($this->extensions[$ext])) {
                        continue;
                    }
                }
                yield $info->getPathname();
            }
        })();
    }
}
