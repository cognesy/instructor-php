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
 *
 * Encoding failures are handled deterministically so the stream stays parseable:
 *   1. encode with JSON_INVALID_UTF8_SUBSTITUTE, so malformed UTF-8 in a payload
 *      degrades to U+FFFD rather than failing the whole record;
 *   2. if that still fails, write a valid fallback record that keeps the entry's
 *      metadata and replaces the context with an error marker;
 *   3. if even the fallback cannot be encoded, skip the write entirely.
 * A line is never appended unless it is complete, valid JSON — in particular the
 * writer never emits the bare "\n" that a failed encode used to produce.
 */
final class FileJsonLogWriter implements LogWriter
{
    private const ENCODE_FLAGS = JSON_UNESCAPED_SLASHES
        | JSON_UNESCAPED_UNICODE
        | JSON_INVALID_UTF8_SUBSTITUTE
        | JSON_PARTIAL_OUTPUT_ON_ERROR;

    public function __construct(private string $path) {}

    public function __invoke(LogEntry $entry): void
    {
        $line = $this->encode([
            'timestamp' => $entry->timestamp->format(DATE_ATOM),
            'level'     => $entry->level,
            'channel'   => $entry->channel,
            'message'   => $entry->message,
            'context'   => $entry->context,
        ]) ?? $this->encode([
            'timestamp'     => $entry->timestamp->format(DATE_ATOM),
            'level'         => $entry->level,
            'channel'       => $entry->channel,
            'message'       => $entry->message,
            'context'       => ['encoding_error' => 'context could not be JSON-encoded'],
        ]);

        if ($line === null) {
            return;
        }

        $dir = dirname($this->path);
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        @file_put_contents($this->path, $line . "\n", FILE_APPEND | LOCK_EX);
    }

    /** @param array<string, mixed> $record */
    private function encode(array $record): ?string
    {
        $encoded = @json_encode($record, self::ENCODE_FLAGS);

        // json_encode() returns at least "{}" for an array, never an empty string, so `false`
        // is the only failure signal worth testing for.
        return $encoded === false ? null : $encoded;
    }
}
