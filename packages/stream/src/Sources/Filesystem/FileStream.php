<?php declare(strict_types=1);

namespace Cognesy\Stream\Sources\Filesystem;

use Cognesy\Stream\Contracts\Stream;
use SplFileObject;

final readonly class FileStream
{
    private function __construct(private SplFileObject $file) {}

    public static function fromPath(string $path): self {
        return new self(new SplFileObject($path, 'r'));
    }

    public static function fromFile(SplFileObject $file): self {
        return new self($file);
    }

    public function file(): SplFileObject {
        return $this->file;
    }

    public function bytes(): Stream {
        $file = $this->file;
        return $this->makeChunkStream($file, 1);
    }

    public function chars(): Stream {
        return $this->bytes();
    }

    public function lines(bool $dropEmpty = false): Stream {
        $file = $this->file;
        return $this->makeLineStream($file, $dropEmpty);
    }

    public function chunks(int $size = 8192): Stream {
        $file = $this->file;
        return $this->makeChunkStream($file, $size);
    }

    // INTERNAL //////////////////////////////////////////////////////

    private function makeChunkStream(SplFileObject $file, int $size): Stream {
        return new FileChunkStream($file, $size);
    }

    private function makeLineStream(SplFileObject $file, bool $dropEmpty): Stream {
        return new FileLineStream($file, $dropEmpty);
    }
}
