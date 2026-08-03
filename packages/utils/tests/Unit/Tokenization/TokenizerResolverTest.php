<?php declare(strict_types=1);

use Cognesy\Utils\Tokenization\Drivers\Gpt3TokenizerDriver;
use Cognesy\Utils\Tokenization\Drivers\TiktokenDriver;
use Cognesy\Utils\Tokenization\TokenizerResolver;

// Resolving `auto` or `tiktoken` downloads a vocabulary, so only the offline
// branches are exercised here; the tiktoken branches are covered by
// packages/utils/tests/Integration/Tokenization/TokenizerResolverTest.php.

test('gpt3 selects the bundled tokenizer', function () {
    expect(TokenizerResolver::resolve('gpt3'))->toBeInstanceOf(Gpt3TokenizerDriver::class);
});

test('reads the preference from the environment when none is given', function () {
    // The suite pins this in phpunit.xml; asserting it here is what makes every
    // other test's token counts reproducible rather than machine-dependent.
    expect(getenv(TokenizerResolver::ENV_VAR))->toBe('gpt3')
        ->and(TokenizerResolver::resolve())->toBeInstanceOf(Gpt3TokenizerDriver::class);
});

test('an explicit preference wins over the environment', function () {
    // Same call, different answer than the pinned environment above.
    expect(TokenizerResolver::resolve('tiktoken:cl100k_base'))->toBeInstanceOf(TiktokenDriver::class);
});

test('builds the tiktoken driver without touching its vocabulary', function () {
    // A bad encoding is only rejected once the vocabulary is resolved. Getting a
    // driver back instead of an exception proves resolve() did no I/O - it has
    // not downloaded anything, and would not have on a valid encoding either.
    expect(TokenizerResolver::resolve('tiktoken:no_such_encoding'))->toBeInstanceOf(TiktokenDriver::class);
});

test('rejects an unknown preference', function (string $preference) {
    expect(fn() => TokenizerResolver::resolve($preference))
        ->toThrow(InvalidArgumentException::class);
})->with([
    'unknown name' => 'gpt4',
    'empty encoding' => 'tiktoken:',
    'wrong separator' => 'tiktoken/cl100k_base',
]);

test('ignores surrounding whitespace', function () {
    // Environment variables routinely arrive padded; a stray space must not be
    // the difference between the configured tokenizer and an exception.
    expect(TokenizerResolver::resolve("  gpt3\n"))->toBeInstanceOf(Gpt3TokenizerDriver::class);
});
