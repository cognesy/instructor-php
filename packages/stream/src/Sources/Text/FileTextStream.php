<?php declare(strict_types=1);

namespace Cognesy\Stream\Sources\Text;

use Cognesy\Stream\Contracts\Stream;
use Cognesy\Stream\Sources\Filesystem\FileStream;

final readonly class FileTextStream
{
    public function __construct(private FileStream $file) {}

    public function chars(): Stream {
        return $this->file->chars();
    }

    public function lines(bool $dropEmpty = false): Stream {
        return $this->file->lines($dropEmpty);
    }

    public function words(string $pattern = "\\w+"): Stream {
        $lines = $this->file->lines(false);
        return new WordStream($lines, $pattern);
    }
}