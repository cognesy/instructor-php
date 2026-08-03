<?php declare(strict_types=1);

use Cognesy\Utils\Tokenization\Contracts\CanTokenizeText;
use Cognesy\Utils\Tokenization\Drivers\TiktokenDriver;
use Yethee\Tiktoken\Encoder\NativeEncoder;
use Yethee\Tiktoken\Vocab\Vocab;

// The encodings shipped with tiktoken are downloaded from OpenAI's public
// storage on first use, which would make these tests depend on the network.
// Instead they run against a vocabulary built in memory: real BPE merging over
// known ranks, no downloads and no filesystem, identical on every machine.

const TIKTOKEN_PATTERN = '/\'s|\'t| ?\p{L}+| ?\p{N}+| ?[^\s\p{L}\p{N}]+|\s+(?!\S)|\s+/u';

function fixtureEncoder(string $name = 'fixture'): NativeEncoder {
    $lines = [];
    // Ranks 0-255 cover every byte, so any input is encodable.
    for ($byte = 0; $byte < 256; $byte++) {
        $lines[] = base64_encode(chr($byte)) . ' ' . $byte;
    }
    // Merges that only exist here: 'ok' collapses to one token, 'ma' to another.
    $lines[] = base64_encode('ok') . ' 256';
    $lines[] = base64_encode('ma') . ' 257';

    $stream = fopen('php://memory', 'r+');
    fwrite($stream, implode("\n", $lines) . "\n");

    try {
        return new NativeEncoder($name, Vocab::fromStream($stream), TIKTOKEN_PATTERN);
    } finally {
        fclose($stream);
    }
}

beforeEach(function () {
    if (!TiktokenDriver::isAvailable()) {
        $this->markTestSkipped('yethee/tiktoken is not installed');
    }
});

test('encodes with the injected encoder', function () {
    $driver = TiktokenDriver::using(fixtureEncoder());

    // 'ok' merges to rank 256; 'oz' has no merge, so it stays two byte tokens.
    expect($driver->encode('ok'))->toBe([256])
        ->and($driver->encode('oz'))->toBe([111, 122])
        ->and($driver->encode(''))->toBe([]);
});

test('counts tokens, not characters', function () {
    $driver = TiktokenDriver::using(fixtureEncoder());

    expect($driver->tokenCount('ok'))->toBe(1)
        ->and($driver->tokenCount('oz'))->toBe(2)
        ->and($driver->tokenCount('mama'))->toBe(2)
        ->and($driver->tokenCount(''))->toBe(0);
});

test('token count equals the number of encoded tokens', function () {
    $driver = TiktokenDriver::using(fixtureEncoder());
    $text = 'ok mama, ok';

    expect($driver->tokenCount($text))->toBe(count($driver->encode($text)));
});

test('reports the encoding of the underlying encoder', function () {
    expect(TiktokenDriver::using(fixtureEncoder('r50k_base'))->encoding())->toBe('r50k_base');
});

test('fulfils the tokenizer contract', function () {
    expect(TiktokenDriver::using(fixtureEncoder()))->toBeInstanceOf(CanTokenizeText::class);
});

test('resolves the encoder lazily', function () {
    // An unknown encoding is rejected by the library as soon as it is asked for
    // an encoder. Constructing without an error therefore proves nothing was
    // resolved yet - no vocabulary loaded, no download attempted.
    $driver = TiktokenDriver::forEncoding('no_such_encoding');

    expect(fn() => $driver->tokenCount('ok'))->toThrow(InvalidArgumentException::class);
});

test('rejects unknown model names', function () {
    expect(fn() => TiktokenDriver::forModel('no-such-model')->tokenCount('ok'))
        ->toThrow(InvalidArgumentException::class);
});
