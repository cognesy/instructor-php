<?php declare(strict_types=1);

use Cognesy\Polyglot\Embeddings\Data\EmbeddingsPricing;
use Cognesy\Polyglot\Embeddings\Data\EmbeddingsUsage;
use Cognesy\Polyglot\Embeddings\Pricing\FlatRateCostCalculator;

describe('EmbeddingsPricing', function () {
    it('creates pricing from array with short key', function () {
        $pricing = EmbeddingsPricing::fromArray(['input' => 0.1]);

        expect($pricing->inputPerMToken)->toBe(0.1);
    });

    it('creates pricing from array with long key', function () {
        $pricing = EmbeddingsPricing::fromArray(['inputPerMToken' => 0.1]);

        expect($pricing->inputPerMToken)->toBe(0.1);
    });

    it('returns empty pricing with none()', function () {
        $pricing = EmbeddingsPricing::none();

        expect($pricing->hasAnyPricing())->toBeFalse()
            ->and($pricing->inputPerMToken)->toBe(0.0);
    });

    it('detects when pricing is configured', function () {
        $pricing = EmbeddingsPricing::fromArray(['input' => 0.1]);

        expect($pricing->hasAnyPricing())->toBeTrue();
    });

    it('converts to array', function () {
        $pricing = new EmbeddingsPricing(inputPerMToken: 0.1);

        expect($pricing->toArray())->toBe(['input' => 0.1]);
    });

    it('throws on non-numeric value', function () {
        expect(fn() => EmbeddingsPricing::fromArray(['input' => 'bad']))
            ->toThrow(InvalidArgumentException::class, "Pricing field 'input' must be numeric");
    });

    it('throws on negative value', function () {
        expect(fn() => EmbeddingsPricing::fromArray(['input' => -0.5]))
            ->toThrow(InvalidArgumentException::class, "Pricing field 'input' must be non-negative");
    });

    it('accepts numeric strings', function () {
        $pricing = EmbeddingsPricing::fromArray(['input' => '0.1']);

        expect($pricing->inputPerMToken)->toBe(0.1);
    });

    it('defaults to zero when key missing', function () {
        $pricing = EmbeddingsPricing::fromArray([]);

        expect($pricing->inputPerMToken)->toBe(0.0)
            ->and($pricing->hasAnyPricing())->toBeFalse();
    });
});

describe('Embeddings FlatRateCostCalculator', function () {
    it('calculates cost for input tokens', function () {
        $calculator = new FlatRateCostCalculator();
        $usage = new EmbeddingsUsage(inputTokens: 1_000_000);
        $pricing = new EmbeddingsPricing(inputPerMToken: 0.1);

        $cost = $calculator->calculate($usage, $pricing);

        expect($cost->total)->toBe(0.1)
            ->and($cost->breakdown)->toBe(['input' => 0.1]);
    });

    it('returns zero cost with no usage', function () {
        $calculator = new FlatRateCostCalculator();
        $usage = EmbeddingsUsage::none();
        $pricing = new EmbeddingsPricing(inputPerMToken: 0.1);

        $cost = $calculator->calculate($usage, $pricing);

        expect($cost->total)->toBe(0.0);
    });

    it('calculates realistic text-embedding-3-small pricing', function () {
        $calculator = new FlatRateCostCalculator();
        // text-embedding-3-small: $0.02/1M tokens
        $usage = new EmbeddingsUsage(inputTokens: 50_000);
        $pricing = new EmbeddingsPricing(inputPerMToken: 0.02);

        $cost = $calculator->calculate($usage, $pricing);

        // 0.05 * 0.02 = 0.001
        expect($cost->total)->toBeCloseTo(0.001, 6);
    });

    it('has only input breakdown key', function () {
        $calculator = new FlatRateCostCalculator();
        $usage = new EmbeddingsUsage(inputTokens: 1000);
        $pricing = new EmbeddingsPricing(inputPerMToken: 1.0);

        $cost = $calculator->calculate($usage, $pricing);

        expect($cost->breakdown)->toHaveKeys(['input'])
            ->and($cost->breakdown)->toHaveCount(1);
    });
});
