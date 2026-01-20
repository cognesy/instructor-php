<?php declare(strict_types=1);

namespace Cognesy\InstructorHub\Data;

use Cognesy\InstructorHub\Config\ExampleSource;

final readonly class ExampleLocation
{
    public function __construct(
        public ExampleSource $source,
        public string $path,
    ) {}

    public function runPath(): string
    {
        return $this->source->baseDir . $this->path . '/run.php';
    }
}
