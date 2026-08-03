<?php declare(strict_types=1);

use Cognesy\Utils\Tokenization\Drivers\TiktokenDriver;
use Cognesy\Utils\Tokenization\TokenizerResolver;

// Resolving tiktoken downloads its vocabulary from OpenAI's public storage the
// first time and caches it on disk, which is why this lives outside the fast
// suite. It is the one place the shipped default is exercised end to end.

test('auto selects tiktoken when its vocabulary can be obtained', function () {
    $tokenizer = TokenizerResolver::resolve('auto');

    expect($tokenizer)->toBeInstanceOf(TiktokenDriver::class)
        ->and($tokenizer->encoding())->toBe(TokenizerResolver::DEFAULT_ENCODING);
});

test('the default encoding counts modern text more tightly than the bundled one', function () {
    // Same string, two vocabularies: o200k_base needs two tokens where the bundled
    // r50k_base needs four. Equal counts would mean auto quietly fell back, and
    // the budgets every consumer computes would still be the old, looser ones.
    $text = 'kubernetes';

    expect(TokenizerResolver::resolve('auto')->tokenCount($text))->toBe(2)
        ->and(TokenizerResolver::resolve('gpt3')->tokenCount($text))->toBe(4);
});

test('an explicit encoding is honoured', function () {
    $tokenizer = TokenizerResolver::resolve('tiktoken:cl100k_base');

    expect($tokenizer)->toBeInstanceOf(TiktokenDriver::class)
        ->and($tokenizer->encoding())->toBe('cl100k_base');
});

test('an explicit tiktoken request fails loudly instead of falling back', function () {
    // auto() would swallow this and hand back the bundled tokenizer. Asking for a
    // specific encoding must not silently change the numbers the caller gets.
    expect(fn() => TokenizerResolver::resolve('tiktoken:no_such_encoding')->tokenCount('x'))
        ->toThrow(InvalidArgumentException::class);
});
