<?php

use Cognesy\Pipeline\PendingExecution;
use Cognesy\Pipeline\Pipeline;
use Cognesy\Pipeline\PipelineBuilder;

describe('Pipeline static factory methods', function () {
    test('empty returns PipelineBuilder', function () {
        $builder = Pipeline::builder();
        
        expect($builder)->toBeInstanceOf(PipelineBuilder::class);
    });
});

describe('Pipeline main usage patterns', function () {
    test('create and keep pipeline for reuse', function () {
        $pipeline = Pipeline::builder()
            ->through(fn($x) => $x * 2)
            ->through(fn($x) => $x + 10)
            ->create()
            ->executeWith();
        
        expect($pipeline)->toBeInstanceOf(PendingExecution::class);
    });

    test('execute pipeline for single input using for()->value()', function () {
        $pipeline = Pipeline::builder()
            ->through(fn($x) => $x * 2)
            ->through(fn($x) => $x + 10)
            ->create()
            ->executeWith();
        
        $output = $pipeline->for(5)->value();
        
        expect($output)->toBe(20); // (5 * 2) + 10 = 20
    });

    test('execute pipeline for different inputs', function () {
        $pipeline = Pipeline::builder()
            ->through(fn($x) => $x * 2)
            ->through(fn($x) => $x + 1)
            ->create()
            ->executeWith();
        
        expect($pipeline->for(1)->value())->toBe(3);   // (1 * 2) + 1 = 3
        expect($pipeline->for(5)->value())->toBe(11);  // (5 * 2) + 1 = 11
        expect($pipeline->for(10)->value())->toBe(21); // (10 * 2) + 1 = 21
    });

    test('execute pipeline for multiple inputs using each()', function () {
        $pipeline = Pipeline::builder()
            ->through(fn($x) => $x * 2)
            ->create()
            ->executeWith();
        
        $inputs = [1, 2, 3];
        $outputs = [];
        
        foreach($pipeline->each($inputs) as $execution) {
            $outputs[] = $execution->value();
        }
        
        expect($outputs)->toBe([2, 4, 6]);
    });

    test('string processing pipeline', function () {
        $pipeline = Pipeline::builder()
            ->through(fn($text) => trim($text))
            ->through(fn($text) => strtoupper($text))
            ->through(fn($text) => str_replace(' ', '_', $text))
            ->create()
            ->executeWith();
        
        $output = $pipeline->for('  hello world  ')->value();
        
        expect($output)->toBe('HELLO_WORLD');
    });

    test('data transformation pipeline', function () {
        $pipeline = Pipeline::builder()
            ->through(fn($data) => array_filter($data, fn($x) => $x > 0))
            ->through(fn($data) => array_map(fn($x) => $x * 2, $data))
            ->through(fn($data) => array_sum($data))
            ->create()
            ->executeWith();
        
        $input = [-1, 2, 3, -4, 5];
        $output = $pipeline->for($input)->value();
        
        expect($output)->toBe(20); // [2, 3, 5] -> [4, 6, 10] -> 20
    });
});

describe('Pipeline error handling', function () {
    test('handles processor exception', function () {
        $pipeline = Pipeline::builder()
            ->through(fn($value) => throw new RuntimeException('Test error'))
            ->create()
            ->executeWith('test');
        
        expect($pipeline->isFailure())->toBeTrue();
        expect($pipeline->exception())->toBeInstanceOf(RuntimeException::class);
        expect($pipeline->exception()->getMessage())->toBe('Test error');
    });

    test('pipeline continues working after error in one execution', function () {
        $pipeline = Pipeline::builder()
            ->through(function($x) {
                if ($x === 2) {
                    throw new RuntimeException('Error on 2');
                }
                return $x * 2;
            })
            ->create()
            ->executeWith();
        
        expect($pipeline->for(1)->value())->toBe(2);
        expect($pipeline->for(2)->isFailure())->toBeTrue();
        expect($pipeline->for(3)->value())->toBe(6);
    });

    test('valueOr returns default on failure', function () {
        $pipeline = Pipeline::builder()
            ->through(fn($value) => throw new RuntimeException('Test error'))
            ->create()
            ->executeWith('test');
        
        expect($pipeline->valueOr('default'))->toBe('default');
    });
});

describe('Pipeline with conditional and tap operations', function () {
    test('tap does not affect pipeline output', function () {
        $sideEffect = [];
        
        $pipeline = Pipeline::builder()
            ->through(fn($x) => $x * 2)
            ->tap(function($x) use (&$sideEffect) {
                $sideEffect[] = $x;
            })
            ->through(fn($x) => $x + 1)
            ->create()
            ->executeWith();
        
        $result = $pipeline->for(5)->value();
        
        expect($result)->toBe(11); // (5 * 2) + 1 = 11
        expect($sideEffect)->toBe([10]); // tap captured intermediate value
    });

    test('when executes conditionally', function () {
        $pipeline = Pipeline::builder()
            ->when(fn($x) => $x > 10, fn($x) => $x * 2)
            ->through(fn($x) => $x + 1)
            ->create()
            ->executeWith();
        
        expect($pipeline->for(5)->value())->toBe(6);   // 5 + 1 = 6 (condition false)
        expect($pipeline->for(15)->value())->toBe(31); // (15 * 2) + 1 = 31 (condition true)
    });

    test('complex pipeline with multiple operations', function () {
        $log = [];
        
        $pipeline = Pipeline::builder()
            ->through(fn($x) => $x * 2)
            ->tap(function($x) use (&$log) { $log[] = "doubled: $x"; })
            ->when(fn($x) => $x > 10, fn($x) => $x + 100)
            ->tap(function($x) use (&$log) { $log[] = "final: $x"; })
            ->create()
            ->executeWith();
        
        $result1 = $pipeline->for(3)->value();  // 6, no bonus
        $result2 = $pipeline->for(8)->value();  // 16, with bonus = 116
        
        expect($result1)->toBe(6);
        expect($result2)->toBe(116);
        expect($log)->toBe([
            'doubled: 6',
            'final: 6', 
            'doubled: 16',
            'final: 116'
        ]);
    });
});