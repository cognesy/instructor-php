<?php declare(strict_types=1);

namespace Cognesy\Stream\Sources\Filesystem;

use Cognesy\Stream\Contracts\Stream;

final readonly class DirectoryStream
{
    /** @var array<string, true>|null */
    private ?array $extensions;

    private function __construct(
        private string $root,
        ?array $extensions = null,
    ) {
        $this->extensions = $extensions;
    }

    public static function from(string $root): self {
        return new self($root, null);
    }

    public function withExtensions(string ...$extensions): self {
        if (count($extensions) === 0) {
            return new self($this->root, null);
        }
        $set = [];
        foreach ($extensions as $ext) {
            $e = ltrim(strtolower($ext), '.');
            if ($e !== '') {
                $set[$e] = true;
            }
        }
        return new self($this->root, $set);
    }

    public function any(bool $recursive = true): Stream {
        $root = $this->root;
        $exts = $this->extensions;
        return $this->makeAnyStream($root, $exts, $recursive);
    }

    public function files(bool $recursive = true): Stream {
        $root = $this->root;
        $exts = $this->extensions;
        return $this->makeFilesStream($root, $exts, $recursive);
    }

    public function dirs(bool $recursive = true): Stream {
        $root = $this->root;
        return $this->makeDirsStream($root, $recursive);
    }

    // INTERNAL //////////////////////////////////////////////////////

    private function makeFilesStream(string $root, ?array $exts, bool $recursive) : Stream {
        return new DirectoryFilesStream($root, $exts, $recursive);
    }

    private function makeDirsStream(string $root, bool $recursive) : Stream {
        return new DirectoryDirsStream($root, $recursive);
    }

    private function makeAnyStream(string $root, ?array $exts, bool $recursive) : Stream {
        return new DirectoryAnyStream($root, $exts, $recursive);
    }
}
