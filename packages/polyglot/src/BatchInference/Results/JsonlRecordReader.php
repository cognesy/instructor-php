<?php

declare(strict_types=1);

namespace Cognesy\Polyglot\BatchInference\Results;

use JsonException;
use RuntimeException;

final readonly class JsonlRecordReader
{
    public function __construct(private int $maxRecordBytes = 10000000)
    {
        if ($maxRecordBytes < 1) {
            throw new RuntimeException('JSONL record limit must be positive.');
        }
    }

    /**
     * @param iterable<string> $chunks
     * @return iterable<array<string, mixed>>
     */
    public function records(iterable $chunks): iterable
    {
        $buffer = '';
        foreach ($chunks as $chunk) {
            $buffer .= $chunk;
            while (($newline = strpos($buffer, "\n")) !== false) {
                $line = substr($buffer, 0, $newline);
                $buffer = substr($buffer, $newline + 1);
                if (strlen($line) > $this->maxRecordBytes) {
                    throw new RuntimeException('Batch result record exceeds the configured limit.');
                }
                $record = $this->decode($line);
                if ($record !== null) {
                    yield $record;
                }
            }
            if (strlen($buffer) > $this->maxRecordBytes) {
                throw new RuntimeException('Batch result record exceeds the configured limit.');
            }
        }
        if ($buffer !== '') {
            $record = $this->decode($buffer);
            if ($record !== null) {
                yield $record;
            }
        }
    }

    /** @return ?array<string, mixed> */
    private function decode(string $line): ?array
    {
        $line = rtrim($line, "\r");
        if ($line === '') {
            return null;
        }
        try {
            $record = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $error) {
            throw new RuntimeException('Invalid JSONL batch result record.', previous: $error);
        }
        if (!is_array($record) || array_is_list($record)) {
            throw new RuntimeException('Batch result record must be a JSON object.');
        }

        return $record;
    }
}
