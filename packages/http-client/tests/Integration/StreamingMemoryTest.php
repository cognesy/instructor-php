<?php declare(strict_types=1);

use Cognesy\Http\Data\HttpResponse;
use Cognesy\Http\Stream\ArrayStream;

/**
 * Memory profile for HTTP streaming layer.
 *
 * Streams 1 000 chunks through ArrayStream → HttpResponse iteration
 * and asserts that peak memory stays within a fixed ceiling.
 * The result can be extrapolated linearly to 10K / 100K chunks.
 */

it('streams large response (~10K tokens) with bounded memory', function () {
    // Simulate a large single-object response: ~40KB of JSON split into ~10 token-sized SSE chunks per KB
    $totalBytes = 40_000;
    $chunkSize  = 100; // ~25 tokens per chunk at ~4 bytes/token
    $chunkCount = (int) ceil($totalBytes / $chunkSize);
    $payload    = str_repeat('x', $chunkSize);

    $chunks = [];
    for ($i = 0; $i < $chunkCount; $i++) {
        $chunks[] = "data: {$payload}\n\n";
    }

    $response = HttpResponse::streaming(
        statusCode: 200,
        headers: ['content-type' => 'text/event-stream'],
        stream: new ArrayStream($chunks),
    );

    gc_collect_cycles();
    $memBefore = memory_get_usage(false);

    $received = 0;
    foreach ($response->stream() as $chunk) {
        $received++;
    }

    gc_collect_cycles();
    $memAfter = memory_get_usage(false);

    $growth = $memAfter - $memBefore;

    expect($received)->toBe($chunkCount);

    // Stream should not accumulate — overhead should be minimal regardless of payload size
    expect($growth)->toBeLessThan(256 * 1024, sprintf(
        'Memory grew by %s streaming a %s response (expected < 256 KB overhead).',
        number_format($growth),
        number_format($totalBytes),
    ));

    echo sprintf(
        "\n  [http-large] %s payload, %d chunks | growth=%s | overhead ratio=%.2f%%",
        number_format($totalBytes),
        $chunkCount,
        number_format($growth),
        $totalBytes > 0 ? ($growth / $totalBytes) * 100 : 0,
    );
});

it('streams 1000 chunks with bounded memory', function () {
    $chunkCount = 1_000;
    $chunkBody  = str_repeat('x', 256); // 256-byte payload per chunk

    $chunks = array_fill(0, $chunkCount, "data: {$chunkBody}\n\n");

    $response = HttpResponse::streaming(
        statusCode: 200,
        headers: ['content-type' => 'text/event-stream'],
        stream: new ArrayStream($chunks),
    );

    // Baseline: force GC and snapshot memory before iteration
    gc_collect_cycles();
    $memBefore = memory_get_usage(false);

    $received = 0;
    foreach ($response->stream() as $chunk) {
        $received++;
    }

    gc_collect_cycles();
    $memAfter = memory_get_usage(false);

    $growth = $memAfter - $memBefore;

    expect($received)->toBe($chunkCount);

    // The stream should not accumulate data — allow a generous 512 KB ceiling.
    // ArrayStream holds the full array upfront, so we measure iteration overhead only.
    expect($growth)->toBeLessThan(512 * 1024, sprintf(
        'Memory grew by %s during streaming (expected < 512 KB). '
        . 'This suggests the streaming layer is accumulating data.',
        number_format($growth),
    ));

    // Report for extrapolation
    $perChunk = $chunkCount > 0 ? $growth / $chunkCount : 0;
    echo sprintf(
        "\n  [http] %d chunks | growth=%s | per-chunk=%.1f bytes | 10K≈%s | 100K≈%s",
        $chunkCount,
        number_format($growth),
        $perChunk,
        number_format($perChunk * 10_000),
        number_format($perChunk * 100_000),
    );
});
