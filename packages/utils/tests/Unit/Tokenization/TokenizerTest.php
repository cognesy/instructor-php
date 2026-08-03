<?php declare(strict_types=1);

use Cognesy\Utils\Tokenization\Contracts\CanCountTokens;
use Cognesy\Utils\Tokenization\Drivers\Gpt3TokenizerDriver;
use Cognesy\Utils\Tokenizer;

/**
 * Counts words instead of BPE tokens, so a swapped-in tokenizer produces
 * numbers the bundled one never would - the assertions cannot pass by accident.
 */
final class WordCountingTokenizer implements CanCountTokens
{
    public int $calls = 0;

    #[\Override]
    public function tokenCount(string $text): int {
        $this->calls++;
        return $text === '' ? 0 : count(preg_split('/\s+/', trim($text)) ?: []);
    }
}

// The suite pins INSTRUCTOR_TOKENIZER=gpt3 (see phpunit.xml), so the counts below
// are the bundled r50k_base ones. Resolution itself - including the tiktoken
// default - is covered by TokenizerResolverTest.
afterEach(fn() => Tokenizer::reset());

test('builds its default through the resolver', function () {
    expect(Tokenizer::default())->toBeInstanceOf(Gpt3TokenizerDriver::class);
});

test('reuses one default instance instead of rebuilding per call', function () {
    expect(Tokenizer::default())->toBe(Tokenizer::default());
});

test('counts tokens with the bundled tokenizer', function () {
    // 'tokenization' is a single word but two r50k_base tokens.
    expect(Tokenizer::tokenCount('tokenization'))->toBe(2);
});

test('routes counting through a swapped-in implementation', function () {
    $fake = new WordCountingTokenizer();
    Tokenizer::setDefault($fake);

    // Two words, four BPE tokens - only the injected counter can answer 2.
    expect(Tokenizer::tokenCount('tokenization matters'))->toBe(2)
        ->and($fake->calls)->toBe(1)
        ->and(Tokenizer::default())->toBe($fake);
});

test('restores the bundled tokenizer on reset', function () {
    Tokenizer::setDefault(new WordCountingTokenizer());
    Tokenizer::reset();

    expect(Tokenizer::default())->toBeInstanceOf(Gpt3TokenizerDriver::class)
        ->and(Tokenizer::tokenCount('tokenization matters'))->toBe(3);
});

test('null clears the override', function () {
    Tokenizer::setDefault(new WordCountingTokenizer());
    Tokenizer::setDefault(null);

    expect(Tokenizer::default())->toBeInstanceOf(Gpt3TokenizerDriver::class);
});
