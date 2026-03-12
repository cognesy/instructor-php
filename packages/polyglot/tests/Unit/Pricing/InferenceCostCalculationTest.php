<?php declare(strict_types=1);

use Cognesy\Polyglot\Inference\Data\InferencePricing;
use Cognesy\Polyglot\Inference\Data\InferenceUsage;
use Cognesy\Polyglot\Inference\Pricing\FlatRateCostCalculator;

describe('InferencePricing', function () {
    it('creates pricing from array with short keys', function () {
        $pricing = InferencePricing::fromArray([
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
        $pricing = InferencePricing::fromArray([
            'inputPerMToken' => 3.0,
            'outputPerMToken' => 15.0,
        ]);

        expect($pricing->inputPerMToken)->toBe(3.0)
            ->and($pricing->outputPerMToken)->toBe(15.0);
    });

    it('defaults cache and reasoning to input price', function () {
        $pricing = InferencePricing::fromArray([
            'input' => 3.0,
            'output' => 15.0,
        ]);

        expect($pricing->cacheReadPerMToken)->toBe(3.0)
            ->and($pricing->cacheWritePerMToken)->toBe(3.0)
            ->and($pricing->reasoningPerMToken)->toBe(3.0);
    });

    it('returns empty pricing with none()', function () {
        $pricing = InferencePricing::none();

        expect($pricing->hasAnyPricing())->toBeFalse();
    });

    it('detects when pricing is configured', function () {
        $pricing = InferencePricing::fromArray(['input' => 1.0]);

        expect($pricing->hasAnyPricing())->toBeTrue();
    });

    it('converts to array', function () {
        $pricing = new InferencePricing(
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
        expect(fn() => InferencePricing::fromArray(['input' => 'invalid']))
            ->toThrow(InvalidArgumentException::class, "Pricing field 'input' must be numeric");
    });

    it('throws on non-numeric output value', function () {
        expect(fn() => InferencePricing::fromArray(['input' => 1.0, 'output' => 'bad']))
            ->toThrow(InvalidArgumentException::class, "Pricing field 'output' must be numeric");
    });

    it('throws on negative pricing value', function () {
        expect(fn() => InferencePricing::fromArray(['input' => -5.0]))
            ->toThrow(InvalidArgumentException::class, "Pricing field 'input' must be non-negative");
    });

    it('throws on negative cacheRead value', function () {
        expect(fn() => InferencePricing::fromArray(['input' => 1.0, 'cacheRead' => -0.5]))
            ->toThrow(InvalidArgumentException::class, "Pricing field 'cacheRead' must be non-negative");
    });

    it('accepts numeric strings', function () {
        $pricing = InferencePricing::fromArray([
            'input' => '3.0',
            'output' => '15',
        ]);

        expect($pricing->inputPerMToken)->toBe(3.0)
            ->and($pricing->outputPerMToken)->toBe(15.0);
    });
});

describe('Inference FlatRateCostCalculator', function () {
    it('calculates cost for input and output tokens', function () {
        $calculator = new FlatRateCostCalculator();
        $usage = new InferenceUsage(inputTokens: 1_000_000, outputTokens: 500_000);
        $pricing = new InferencePricing(inputPerMToken: 3.0, outputPerMToken: 15.0);

        $cost = $calculator->calculate($usage, $pricing);

        // (1M/1M) * 3.0 + (500K/1M) * 15.0 = 3.0 + 7.5 = 10.5
        expect($cost->total)->toBe(10.5)
            ->and($cost->breakdown['input'])->toBe(3.0)
            ->and($cost->breakdown['output'])->toBe(7.5);
    });

    it('calculates cost including cache tokens', function () {
        $calculator = new FlatRateCostCalculator();
        $usage = new InferenceUsage(
            inputTokens: 1_000_000,
            outputTokens: 500_000,
            cacheReadTokens: 2_000_000,
            cacheWriteTokens: 500_000,
        );
        $pricing = new InferencePricing(
            inputPerMToken: 3.0,
            outputPerMToken: 15.0,
            cacheReadPerMToken: 0.3,
            cacheWritePerMToken: 3.75,
        );

        $cost = $calculator->calculate($usage, $pricing);

        // input: 3.0, output: 7.5, cacheRead: 0.6, cacheWrite: 1.875 = 12.975
        expect($cost->total)->toBeCloseTo(12.975, 6);
    });

    it('calculates cost including reasoning tokens', function () {
        $calculator = new FlatRateCostCalculator();
        $usage = new InferenceUsage(
            inputTokens: 1_000_000,
            outputTokens: 200_000,
            reasoningTokens: 800_000,
        );
        $pricing = new InferencePricing(
            inputPerMToken: 3.0,
            outputPerMToken: 15.0,
            reasoningPerMToken: 60.0,
        );

        $cost = $calculator->calculate($usage, $pricing);

        // input: 3.0, output: 3.0, reasoning: 48.0 = 54.0
        expect($cost->total)->toBe(54.0);
    });

    it('returns zero cost with no usage', function () {
        $calculator = new FlatRateCostCalculator();
        $usage = InferenceUsage::none();
        $pricing = new InferencePricing(inputPerMToken: 3.0, outputPerMToken: 15.0);

        $cost = $calculator->calculate($usage, $pricing);

        expect($cost->total)->toBe(0.0);
    });

    it('calculates realistic OpenRouter Claude pricing', function () {
        $calculator = new FlatRateCostCalculator();
        $usage = new InferenceUsage(inputTokens: 15_000, outputTokens: 2_500);
        $pricing = InferencePricing::fromArray(['input' => 3.0, 'output' => 15.0]);

        $cost = $calculator->calculate($usage, $pricing);

        // input: 0.015 * 3.0 = 0.045, output: 0.0025 * 15.0 = 0.0375 = 0.0825
        expect($cost->total)->toBeCloseTo(0.0825, 6);
    });

    it('has correct breakdown keys', function () {
        $calculator = new FlatRateCostCalculator();
        $usage = new InferenceUsage(inputTokens: 1000);
        $pricing = new InferencePricing(inputPerMToken: 1.0, outputPerMToken: 1.0);

        $cost = $calculator->calculate($usage, $pricing);

        expect($cost->breakdown)->toHaveKeys(['input', 'output', 'cacheRead', 'cacheWrite', 'reasoning']);
    });
});
