<?php declare(strict_types=1);

use Cognesy\Utils\Tokenization\Contracts\CanTokenizeText;
use Cognesy\Utils\Tokenization\Drivers\Gpt3TokenizerDriver;

// Expected token IDs come from OpenAI's `r50k_base` encoding, the vocabulary
// gioni06/gpt3-tokenizer bundles. They are the same IDs tiktoken produces, so
// the assertions below pin real BPE output rather than whatever this driver
// happens to return today.

test('encodes text to r50k_base token ids', function (string $text, array $expected) {
    $driver = new Gpt3TokenizerDriver();

    expect($driver->encode($text))->toBe($expected);
})->with([
    'greeting' => ['Hello world!', [15496, 995, 0]],
    'single token' => ['ok', [482]],
    'multi-word' => ['Instructor for PHP', [43993, 273, 329, 19599]],
    'split word' => ['tokenization', [30001, 1634]],
    'empty' => ['', []],
]);

test('token count equals the number of encoded tokens', function () {
    $driver = new Gpt3TokenizerDriver();
    $text = 'Instructor for PHP builds structured outputs from LLMs.';

    expect($driver->tokenCount($text))->toBe(count($driver->encode($text)));
});

test('counts tokens, not characters or words', function () {
    $driver = new Gpt3TokenizerDriver();

    // 'tokenization' is one word and 12 characters, but two BPE tokens.
    expect($driver->tokenCount('tokenization'))->toBe(2)
        ->and($driver->tokenCount(''))->toBe(0)
        ->and($driver->tokenCount('ok'))->toBe(1);
});

test('reports the encoding its token ids belong to', function () {
    expect((new Gpt3TokenizerDriver())->encoding())->toBe('r50k_base');
});

test('fulfils the tokenizer contract', function () {
    expect(new Gpt3TokenizerDriver())->toBeInstanceOf(CanTokenizeText::class);
});

test('builds the vocabulary lazily and reuses it across calls', function () {
    $driver = new Gpt3TokenizerDriver();
    $inner = fn() => (new ReflectionProperty(Gpt3TokenizerDriver::class, 'tokenizer'))->getValue($driver);

    // Loading the BPE vocabulary costs ~30 ms and ~25 MB, so it must happen once
    // per driver at most - never in the constructor, never per call.
    expect($inner())->toBeNull();

    $driver->tokenCount('first call');
    $first = $inner();
    $driver->encode('second call');

    expect($first)->not->toBeNull()
        ->and($inner())->toBe($first);
});
