<?php declare(strict_types=1);

use Cognesy\Polyglot\Inference\Data\Pricing;
use Cognesy\Polyglot\Inference\Data\Usage;

describe('Pricing', function () {
    it('creates pricing from array with short keys', function () {
        $pricing = Pricing::fromArray([
            'input' => 3.0,
            'output' => 15.0,
            'cacheRead' => 0.3,
            'cacheWrite' => 3.75,
            'reasoning' => 60.0,
        ]);

        expect($pricing->inputPerMToken)->toBe(3.0)
            ->and($pricing->outputPerMToken)->toBe(15.0)
            ->and($pricing->cacheReadPerMToken)->toBe(0.3)
            ->and($pricing->cacheWritePerMToken)->toBe(3.75)
            ->and($pricing->reasoningPerMToken)->toBe(60.0);
    });

    it('creates pricing from array with long keys', function () {
        $pricing = Pricing::fromArray([
            'inputPerMToken' => 3.0,
            'outputPerMToken' => 15.0,
        ]);

        expect($pricing->inputPerMToken)->toBe(3.0)
            ->and($pricing->outputPerMToken)->toBe(15.0);
    });

    it('defaults cache and reasoning to input price', function () {
        $pricing = Pricing::fromArray([
            'input' => 3.0,
            'output' => 15.0,
        ]);

        expect($pricing->cacheReadPerMToken)->toBe(3.0)
            ->and($pricing->cacheWritePerMToken)->toBe(3.0)
            ->and($pricing->reasoningPerMToken)->toBe(3.0);
    });

    it('returns empty pricing with none()', function () {
        $pricing = Pricing::none();

        expect($pricing->hasAnyPricing())->toBeFalse();
    });

    it('detects when pricing is configured', function () {
        $pricing = Pricing::fromArray(['input' => 1.0]);

        expect($pricing->hasAnyPricing())->toBeTrue();
    });

    it('converts to array', function () {
        $pricing = new Pricing(
            inputPerMToken: 3.0,
            outputPerMToken: 15.0,
        );

        expect($pricing->toArray())->toBe([
            'input' => 3.0,
            'output' => 15.0,
            'cacheRead' => 3.0,
            'cacheWrite' => 3.0,
            'reasoning' => 3.0,
        ]);
    });

    it('throws on non-numeric input value', function () {
        expect(fn() => Pricing::fromArray(['input' => 'invalid']))
            ->toThrow(InvalidArgumentException::class, "Pricing field 'input' must be numeric");
    });

    it('throws on non-numeric output value', function () {
        expect(fn() => Pricing::fromArray(['input' => 1.0, 'output' => 'bad']))
            ->toThrow(InvalidArgumentException::class, "Pricing field 'output' must be numeric");
    });

    it('throws on negative pricing value', function () {
        expect(fn() => Pricing::fromArray(['input' => -5.0]))
            ->toThrow(InvalidArgumentException::class, "Pricing field 'input' must be non-negative");
    });

    it('throws on negative cacheRead value', function () {
        expect(fn() => Pricing::fromArray(['input' => 1.0, 'cacheRead' => -0.5]))
            ->toThrow(InvalidArgumentException::class, "Pricing field 'cacheRead' must be non-negative");
    });

    it('accepts numeric strings', function () {
        $pricing = Pricing::fromArray([
            'input' => '3.0',
            'output' => '15',
        ]);

        expect($pricing->inputPerMToken)->toBe(3.0)
            ->and($pricing->outputPerMToken)->toBe(15.0);
    });
});

describe('Usage::calculateCost', function () {
    it('calculates cost for input and output tokens ($/1M)', function () {
        $usage = new Usage(
            inputTokens: 1_000_000,
            outputTokens: 500_000,
        );
        $pricing = new Pricing(
            inputPerMToken: 3.0,
            outputPerMToken: 15.0,
        );

        // (1M/1M) * 3.0 + (500K/1M) * 15.0 = 3.0 + 7.5 = 10.5
        $cost = $usage->cost($pricing);

        expect($cost)->toBe(10.5);
    });

    it('calculates cost including cache tokens', function () {
        $usage = new Usage(
            inputTokens: 1_000_000,
            outputTokens: 500_000,
            cacheReadTokens: 2_000_000,
            cacheWriteTokens: 500_000,
        );
        $pricing = new Pricing(
            inputPerMToken: 3.0,
            outputPerMToken: 15.0,
            cacheReadPerMToken: 0.3,
            cacheWritePerMToken: 3.75,
        );

        // input: 1 * 3.0 = 3.0
        // output: 0.5 * 15.0 = 7.5
        // cacheRead: 2 * 0.3 = 0.6
        // cacheWrite: 0.5 * 3.75 = 1.875
        // total = 12.975
        $cost = $usage->cost($pricing);

        expect($cost)->toBeCloseTo(12.975, 6);
    });

    it('calculates cost including reasoning tokens', function () {
        $usage = new Usage(
            inputTokens: 1_000_000,
            outputTokens: 200_000,
            reasoningTokens: 800_000,
        );
        $pricing = new Pricing(
            inputPerMToken: 3.0,
            outputPerMToken: 15.0,
            reasoningPerMToken: 60.0,
        );

        // input: 1 * 3.0 = 3.0
        // output: 0.2 * 15.0 = 3.0
        // reasoning: 0.8 * 60.0 = 48.0
        // total = 54.0
        $cost = $usage->cost($pricing);

        expect($cost)->toBe(54.0);
    });

    it('throws when no pricing available and none provided', function () {
        $usage = new Usage(
            inputTokens: 1000,
            outputTokens: 500,
        );

        expect(fn() => $usage->cost())
            ->toThrow(\RuntimeException::class, 'Cannot calculate cost: no pricing information available');
    });

    it('returns zero cost with no usage', function () {
        $usage = Usage::none();
        $pricing = new Pricing(
            inputPerMToken: 3.0,
            outputPerMToken: 15.0,
        );

        $cost = $usage->cost($pricing);

        expect($cost)->toBe(0.0);
    });

    it('uses stored pricing when no argument provided', function () {
        $pricing = new Pricing(
            inputPerMToken: 3.0,
            outputPerMToken: 15.0,
        );
        $usage = (new Usage(
            inputTokens: 1_000_000,
            outputTokens: 500_000,
        ))->withPricing($pricing);

        // No argument - uses stored pricing
        $cost = $usage->cost();

        expect($cost)->toBe(10.5);
    });

    it('calculates realistic OpenRouter Claude pricing', function () {
        // Real Claude 3.5 Sonnet pricing via OpenRouter: $3/1M input, $15/1M output
        $usage = new Usage(
            inputTokens: 15_000,
            outputTokens: 2_500,
        );
        $pricing = Pricing::fromArray([
            'input' => 3.0,    // $/1M tokens
            'output' => 15.0,  // $/1M tokens
        ]);

        // input: 0.015 * 3.0 = 0.045
        // output: 0.0025 * 15.0 = 0.0375
        // total = 0.0825
        $cost = $usage->cost($pricing);

        expect($cost)->toBeCloseTo(0.0825, 6);
    });

    it('preserves pricing through accumulation', function () {
        $pricing = new Pricing(inputPerMToken: 3.0, outputPerMToken: 15.0);

        $usage1 = (new Usage(inputTokens: 500_000))->withPricing($pricing);
        $usage2 = new Usage(inputTokens: 500_000);

        $accumulated = $usage1->withAccumulated($usage2);

        expect($accumulated->pricing())->toBe($pricing)
            ->and($accumulated->cost())->toBe(3.0); // 1M * 3.0
    });
});
