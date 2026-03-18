<?php

declare(strict_types=1);

namespace Cognesy\Logging\Observability;

use Cognesy\Logging\Contracts\LogWriter;
use Cognesy\Logging\LogEntry;

/**
 * Append-only JSONL file sink.
 *
 * Write failures are silently swallowed — logging must never propagate exceptions
 * into the event dispatch chain.
 */
final class FileJsonLogWriter implements LogWriter
{
    public function __construct(private string $path) {}

    public function __invoke(LogEntry $entry): void
    {
        $dir = dirname($this->path);
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        $line = json_encode([
            'timestamp' => $entry->timestamp->format(DATE_ATOM),
            'level'     => $entry->level,
            'channel'   => $entry->channel,
            'message'   => $entry->message,
            'context'   => $entry->context,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";

        @file_put_contents($this->path, $line, FILE_APPEND | LOCK_EX);
    }
}
