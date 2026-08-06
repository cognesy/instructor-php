<?php

declare(strict_types=1);

use Cognesy\Logging\LogEntry;
use Cognesy\Logging\Observability\FileJsonLogWriter;
use Psr\Log\LogLevel;

/**
 * Lives in Integration rather than Unit because it exercises real file writes -
 * the point of the writer is what actually lands on disk.
 */

function writerEntry(array $context): LogEntry
{
    return LogEntry::create(
        level: LogLevel::INFO,
        message: 'TestEvent',
        channel: 'test',
        context: $context,
        timestamp: new DateTimeImmutable('2026-01-01T00:00:00+00:00'),
    );
}

beforeEach(function () {
    $this->path = sys_get_temp_dir() . '/jsonl-writer-' . bin2hex(random_bytes(6)) . '/out.jsonl';
});

afterEach(function () {
    if (is_file($this->path)) {
        unlink($this->path);
    }
    $dir = dirname($this->path);
    if (is_dir($dir)) {
        @rmdir($dir);
    }
});

it('writes a normal entry as one parseable JSONL line', function () {
    (new FileJsonLogWriter($this->path))(writerEntry(['requestId' => 'req-1']));

    $lines = file($this->path, FILE_IGNORE_NEW_LINES);

    expect($lines)->toHaveCount(1);
    $decoded = json_decode($lines[0], true);
    expect(json_last_error())->toBe(JSON_ERROR_NONE)
        ->and($decoded['message'])->toBe('TestEvent')
        ->and($decoded['level'])->toBe('info')
        ->and($decoded['context']['requestId'])->toBe('req-1');
});

it('never emits a blank or malformed line for unencodable UTF-8', function () {
    // Invalid UTF-8: a lone continuation byte. Previously json_encode() returned
    // false here and the writer appended a bare "\n".
    (new FileJsonLogWriter($this->path))(writerEntry(['bad' => "\xB1\x31 broken"]));

    $raw = file_get_contents($this->path);

    expect($raw)->not->toBe("\n")
        ->and(trim($raw))->not->toBe('');

    $lines = file($this->path, FILE_IGNORE_NEW_LINES);
    expect($lines)->toHaveCount(1);

    $decoded = json_decode($lines[0], true);
    expect(json_last_error())->toBe(JSON_ERROR_NONE)
        ->and($decoded['message'])->toBe('TestEvent')
        ->and($decoded['context'])->toHaveKey('bad');
});

it('keeps every line parseable when good and bad entries interleave', function () {
    $writer = new FileJsonLogWriter($this->path);
    $writer(writerEntry(['n' => 1]));
    $writer(writerEntry(['bad' => "\xB1\x31"]));
    $writer(writerEntry(['n' => 3]));

    $lines = file($this->path, FILE_IGNORE_NEW_LINES);
    expect($lines)->toHaveCount(3);

    foreach ($lines as $i => $line) {
        expect(trim($line))->not->toBe('');
        json_decode($line, true);
        expect(json_last_error())->toBe(JSON_ERROR_NONE, "line {$i} is not valid JSON");
    }
});

it('does not throw when the target cannot be written', function () {
    // Point at an existing directory: dirname() already exists so mkdir() is skipped,
    // and file_put_contents() on a directory fails - exercising the swallow path
    // without tripping PHPUnit's error handler on an unrelated mkdir warning.
    $writer = new FileJsonLogWriter(sys_get_temp_dir());

    // The writer suppresses the underlying warning with @, but PHPUnit's error
    // handler promotes suppressed diagnostics anyway. Swap in a no-op handler so
    // this asserts the contract under test - that nothing propagates to the caller.
    set_error_handler(static fn(): bool => true);
    $threw = false;
    try {
        $writer(writerEntry(['n' => 1]));
    } catch (Throwable) {
        $threw = true;
    } finally {
        restore_error_handler();
    }

    expect($threw)->toBeFalse();
});
