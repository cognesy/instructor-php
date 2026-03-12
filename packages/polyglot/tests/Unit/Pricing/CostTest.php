<?php declare(strict_types=1);

use Cognesy\Polyglot\Pricing\Cost;

describe('Cost', function () {
    it('creates empty cost with none()', function () {
        $cost = Cost::none();

        expect($cost->total)->toBe(0.0)
            ->and($cost->breakdown)->toBe([]);
    });

    it('stores total and breakdown', function () {
        $cost = new Cost(total: 10.5, breakdown: ['input' => 3.0, 'output' => 7.5]);

        expect($cost->total)->toBe(10.5)
            ->and($cost->breakdown)->toBe(['input' => 3.0, 'output' => 7.5]);
    });

    it('accumulates two costs with matching keys', function () {
        $a = new Cost(total: 3.0, breakdown: ['input' => 1.0, 'output' => 2.0]);
        $b = new Cost(total: 5.0, breakdown: ['input' => 2.0, 'output' => 3.0]);

        $result = $a->withAccumulated($b);

        expect($result->total)->toBe(8.0)
            ->and($result->breakdown)->toBe(['input' => 3.0, 'output' => 5.0]);
    });

    it('accumulates costs with different breakdown keys', function () {
        $a = new Cost(total: 1.0, breakdown: ['input' => 1.0]);
        $b = new Cost(total: 2.0, breakdown: ['output' => 2.0]);

        $result = $a->withAccumulated($b);

        expect($result->total)->toBe(3.0)
            ->and($result->breakdown)->toBe(['input' => 1.0, 'output' => 2.0]);
    });

    it('accumulates with none()', function () {
        $cost = new Cost(total: 5.5, breakdown: ['input' => 5.5]);

        $result = $cost->withAccumulated(Cost::none());

        expect($result->total)->toBe(5.5)
            ->and($result->breakdown)->toBe(['input' => 5.5]);
    });

    it('converts to array', function () {
        $cost = new Cost(total: 10.5, breakdown: ['input' => 3.0, 'output' => 7.5]);

        expect($cost->toArray())->toBe([
            'total' => 10.5,
            'breakdown' => ['input' => 3.0, 'output' => 7.5],
        ]);
    });

    it('formats as string', function () {
        $cost = new Cost(total: 0.0825);

        expect($cost->toString())->toBe('$0.082500');
    });
});
