<?php

declare(strict_types=1);

use Cognesy\Config\Env;
use Cognesy\Polyglot\Embeddings\Embeddings;

it('embeds live text through the bundled Perplexity presets', function (string $preset): void {
    if (Env::get('POLYGLOT_PERPLEXITY_LIVE') !== '1') {
        test()->markTestSkipped('Set POLYGLOT_PERPLEXITY_LIVE=1 to run the Perplexity live smoke.');
    }
    $apiKey = Env::get('PERPLEXITY_API_KEY');
    if (! is_string($apiKey) || trim($apiKey) === '') {
        throw new RuntimeException('PERPLEXITY_API_KEY is required when POLYGLOT_PERPLEXITY_LIVE=1.');
    }

    $vectors = Embeddings::using($preset)
        ->withInputs(['Cats are small domesticated felines.', 'Kittens are young cats.', 'The stock market fell today.'])
        ->withOptions(['dimensions' => 256])
        ->vectors();
    [$cat, $kitten, $market] = array_map(fn ($vector) => $vector->values(), $vectors);
    $cosine = static function (array $a, array $b): float {
        $dot = array_sum(array_map(static fn (float $x, float $y): float => $x * $y, $a, $b));
        $norm = static fn (array $v): float => sqrt(array_sum(array_map(static fn (float $x): float => $x * $x, $v)));

        return $dot / ($norm($a) * $norm($b));
    };

    expect($vectors)->toHaveCount(3)
        ->and($cat)->toHaveCount(256)
        ->and($cosine($cat, $kitten))->toBeGreaterThan($cosine($cat, $market));
})->with(['perplexity', 'perplexity-context']);
