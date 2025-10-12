<?php declare(strict_types=1);

namespace Cognesy\Stream\Sources\Json;

use Cognesy\Stream\Contracts\Stream;
use Cognesy\Stream\Sources\Filesystem\FileStream;
use SplFileObject;

final readonly class JsonlStream
{
    private function __construct(private SplFileObject $file) {}

    public static function fromPath(string $path): self {
        $file = new SplFileObject($path, 'r');
        $file->setFlags(SplFileObject::DROP_NEW_LINE);
        return new self($file);
    }

    public static function fromFileStream(FileStream $fileStream): self {
        $file = $fileStream->file();
        $file->setFlags(SplFileObject::DROP_NEW_LINE);
        return new self($file);
    }

    public function lines(): Stream {
        $file = $this->file;
        return $this->makeRawLineStream($file);
    }

    public function decoded(bool $assoc = true): Stream {
        $file = $this->file;
        return $this->makeDecodedLineStream($file, $assoc);
    }

    // INTERNAL //////////////////////////////////////////////////////

    private function makeRawLineStream(SplFileObject $file) : Stream {
        return new JsonlRawLineStream($file);
    }

    private function makeDecodedLineStream(SplFileObject $file, bool $assoc) : Stream {
        $rawLineStream = $this->makeRawLineStream($file);
        return new JsonlDecodedLineStream($rawLineStream, $assoc);
    }
}
