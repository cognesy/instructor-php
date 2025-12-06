<?php

declare(strict_types=1);

namespace Cognesy\Logging\Contracts;

use Cognesy\Logging\LogEntry;

/**
 * Writes log entries to output destinations
 */
interface LogWriter
{
    /**
     * Write a log entry to the destination
     *
     * @param LogEntry $entry The log entry to write
     * @return void
     */
    public function __invoke(LogEntry $entry): void;
}