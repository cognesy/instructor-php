<?php declare(strict_types=1);

namespace Cognesy\Utils\Stream\Csv;

use Cognesy\Utils\Stream\Filesystem\FileStream;
use Cognesy\Utils\Stream\Stream;
use Iterator;
use SplFileObject;

final readonly class CsvStream
{
    private function __construct(
        private SplFileObject $file,
    ) {}

    public static function fromPath(
        string $path,
        string $delimiter = ',',
        string $enclosure = '"',
        string $escape = '\\',
    ): self {
        $file = new SplFileObject($path, 'r');
        $file->setFlags(SplFileObject::READ_CSV | SplFileObject::SKIP_EMPTY);
        $file->setCsvControl($delimiter, $enclosure, $escape);
        return new self($file);
    }

    public static function fromFile(SplFileObject $file): self {
        $file->setFlags(SplFileObject::READ_CSV | SplFileObject::SKIP_EMPTY);
        return new self($file);
    }

    public static function fromFileStream(FileStream $fileStream): self
    {
        $file = $fileStream->file();
        $file->setFlags(SplFileObject::READ_CSV | SplFileObject::SKIP_EMPTY);
        return new self($file);
    }

    public function rows(): Stream {
        $file = $this->file;
        return $this->makeRowsStream($file);
    }

    public function rowsAssoc(): Stream {
        $file = $this->file;
        return $this->makeRowsAssocStream($file);
    }

    // INTERNAL //////////////////////////////////////////////////////

    private function makeRowsStream(SplFileObject $file) : Stream {
        return new CsvRowStream($file);
    }

    private function makeRowsAssocStream(SplFileObject $file) : Stream {
        return new CsvRowAssocStream($file);
    }
}
