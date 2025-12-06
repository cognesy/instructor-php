<?php

declare(strict_types=1);

namespace Cognesy\Logging\Writers;

use Cognesy\Logging\Contracts\LogWriter;
use Cognesy\Logging\LogEntry;

/**
 * Writer that outputs to multiple destinations
 */
final readonly class CompositeWriter implements LogWriter
{
    /** @var LogWriter[] */
    private array $writers;

    public function __construct(LogWriter ...$writers)
    {
        $this->writers = $writers;
    }

    public function __invoke(LogEntry $entry): void
    {
        foreach ($this->writers as $writer) {
            $writer($entry);
        }
    }
}