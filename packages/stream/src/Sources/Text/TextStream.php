<?php declare(strict_types=1);

namespace Cognesy\Stream\Sources\Text;

use Cognesy\Stream\Contracts\Stream;
use Cognesy\Stream\Sources\Filesystem\FileStream;

final readonly class TextStream
{
    private function __construct(private string $text) {}

    public static function from(string $text): self {
        return new self($text);
    }

    public static function fromFile(FileStream $file): FileTextStream {
        return new FileTextStream($file);
    }

    public function chars(): Stream {
        return new TextCharStream($this->text);
    }

    public function lines(string $eol = PHP_EOL, bool $dropEmpty = false): Stream {
        return new TextLineStream($this->text, $eol, $dropEmpty);
    }

    public function words(string $pattern = "\\w+"): Stream {
        return new TextWordStream($this->text, $pattern);
    }
}
