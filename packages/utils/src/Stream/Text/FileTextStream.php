<?php declare(strict_types=1);

namespace Cognesy\Utils\Stream\Text;

use Cognesy\Utils\Stream\Filesystem\FileStream;
use Cognesy\Utils\Stream\Stream;
use Iterator;

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