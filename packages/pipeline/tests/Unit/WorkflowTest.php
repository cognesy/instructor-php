<?php declare(strict_types=1);

use Cognesy\Pipeline\Middleware\TimingMiddleware;
use Cognesy\Pipeline\Pipeline;
use Cognesy\Pipeline\Tags\TimingTag;
use Cognesy\Pipeline\Workflow\Workflow;

describe('Workflow Unit Tests', function () {
    it('creates empty workflow', function () {
        $workflow = Workflow::empty();
        expect($workflow)->toBeInstanceOf(Workflow::class);
    });

    it('processes data through single pipeline', function () {
        $pipeline = Pipeline::for('test')
            ->through(fn($x) => strtoupper($x));

        $workflow = Workflow::empty()
            ->through($pipeline);

        $result = $workflow->process('hello');
        
        expect($result->value())->toBe('HELLO');
        expect($result->success())->toBeTrue();
    });

    it('chains multiple pipelines in sequence', function () {
        $pipeline1 = Pipeline::for('data')
            ->through(fn($x) => $x . '_step1');

        $pipeline2 = Pipeline::for('data')
            ->through(fn($x) => $x . '_step2');

        $pipeline3 = Pipeline::for('data')
            ->through(fn($x) => $x . '_step3');

        $workflow = Workflow::empty()
            ->through($pipeline1)
            ->through($pipeline2)
            ->through($pipeline3);

        $result = $workflow->process('start');
        
        expect($result->value())->toBe('start_step1_step2_step3');
    });

    it('executes conditional steps based on computation state', function () {
        $mainPipeline = Pipeline::for('data')
            ->through(fn($x) => $x * 2);

        $conditionalPipeline = Pipeline::for('data')
            ->through(fn($x) => $x + 100);

        $workflow = Workflow::empty()
            ->through($mainPipeline)
            ->when(
                fn($computation) => $computation->result()->unwrap() > 10,
                $conditionalPipeline
            );

        // Test condition true (20 > 10)
        $result1 = $workflow->process(10);
        expect($result1->value())->toBe(120); // (10 * 2) + 100

        // Test condition false (4 < 10)
        $result2 = $workflow->process(2);
        expect($result2->value())->toBe(4); // 2 * 2, conditional skipped
    });

    it('executes tap steps for side effects without affecting main flow', function () {
        $sideEffectExecuted = false;

        $mainPipeline = Pipeline::for('data')
            ->through(fn($x) => $x . '_main');

        $tapPipeline = Pipeline::for('data')
            ->through(function($x) use (&$sideEffectExecuted) {
                $sideEffectExecuted = true;
                return $x . '_tap';
            });

        $workflow = Workflow::empty()
            ->through($mainPipeline)
            ->tap($tapPipeline);

        $result = $workflow->process('start');
        
        expect($result->value())->toBe('start_main'); // Tap doesn't affect result
        expect($sideEffectExecuted)->toBeTrue(); // But tap was executed
    });

    it('short-circuits on failure', function () {
        $step2Executed = false;
        $step3Executed = false;

        $failingPipeline = Pipeline::for('data')
            ->through(function($x) {
                throw new \RuntimeException('Pipeline failed');
            });

        $step2Pipeline = Pipeline::for('data')
            ->through(function($x) use (&$step2Executed) {
                $step2Executed = true;
                return $x;
            });

        $step3Pipeline = Pipeline::for('data')
            ->through(function($x) use (&$step3Executed) {
                $step3Executed = true;
                return $x;
            });

        $workflow = Workflow::empty()
            ->through($failingPipeline)
            ->through($step2Pipeline)
            ->through($step3Pipeline);

        $result = $workflow->process('test');
        
        expect($result->success())->toBeFalse();
        expect($step2Executed)->toBeFalse(); // Step 2 never executed
        expect($step3Executed)->toBeFalse(); // Step 3 never executed
    });

    it('preserves tags across pipeline boundaries', function () {
        $pipeline1 = Pipeline::for('data')
            ->withMiddleware(TimingMiddleware::for('step1'))
            ->through(fn($x) => $x . '_1');

        $pipeline2 = Pipeline::for('data')
            ->withMiddleware(TimingMiddleware::for('step2'))
            ->through(fn($x) => $x . '_2');

        $workflow = Workflow::empty()
            ->through($pipeline1)
            ->through($pipeline2);

        $result = $workflow->process('start');
        
        expect($result->value())->toBe('start_1_2');
        
        $timingTags = $result->computation()->all(TimingTag::class);
        expect(count($timingTags))->toBeGreaterThanOrEqual(2);
        
        // Check that we have tags from both pipelines
        $operationNames = array_map(fn($tag) => $tag->operationName, $timingTags);
        expect($operationNames)->toContain('step1');
        expect($operationNames)->toContain('step2');
    });

    it('handles computation input directly', function () {
        $initialComputation = Pipeline::for('test')
            ->withMiddleware(TimingMiddleware::for('initial'))
            ->through(fn($x) => $x . '_initial')
            ->process()
            ->computation();

        $pipeline = Pipeline::for('data')
            ->withMiddleware(TimingMiddleware::for('workflow'))
            ->through(fn($x) => $x . '_workflow');

        $workflow = Workflow::empty()
            ->through($pipeline);

        $result = $workflow->process($initialComputation);
        
        expect($result->value())->toBe('test_initial_workflow');
        
        // Should have timing tags from both initial processing and workflow
        $timingTags = $result->computation()->all(TimingTag::class);
        expect(count($timingTags))->toBeGreaterThanOrEqual(2);
    });

    it('supports complex conditional logic', function () {
        $validationPipeline = Pipeline::for('data')
            ->through(function($data) {
                if (!is_array($data) || !isset($data['type'])) {
                    throw new \InvalidArgumentException('Invalid data format');
                }
                return $data;
            });

        $premiumPipeline = Pipeline::for('data')
            ->through(fn($data) => [...$data, 'premium_processed' => true]);

        $standardPipeline = Pipeline::for('data')
            ->through(fn($data) => [...$data, 'standard_processed' => true]);

        $workflow = Workflow::empty()
            ->through($validationPipeline)
            ->when(
                fn($computation) => $computation->result()->unwrap()['type'] === 'premium',
                $premiumPipeline
            )
            ->when(
                fn($computation) => $computation->result()->unwrap()['type'] === 'standard',
                $standardPipeline
            );

        // Test premium processing
        $premiumResult = $workflow->process(['type' => 'premium', 'value' => 100]);
        expect($premiumResult->success())->toBeTrue();
        expect($premiumResult->value()['premium_processed'] ?? false)->toBeTrue();
        expect($premiumResult->value()['standard_processed'] ?? false)->toBeFalse();

        // Test standard processing
        $standardResult = $workflow->process(['type' => 'standard', 'value' => 50]);
        expect($standardResult->success())->toBeTrue();
        expect($standardResult->value()['standard_processed'] ?? false)->toBeTrue();
        expect($standardResult->value()['premium_processed'] ?? false)->toBeFalse();

        // Test invalid data
        $invalidResult = $workflow->process('invalid');
        expect($invalidResult->success())->toBeFalse();
    });

    it('processes with initial tags', function () {
        $pipeline = Pipeline::for('data')
            ->through(fn($x) => strtoupper($x));

        $workflow = Workflow::empty()
            ->through($pipeline);

        $customTag = new class implements \Cognesy\Pipeline\TagInterface {
            public function __construct(public readonly string $value = 'test') {}
        };

        $result = $workflow->process('hello', [$customTag]);
        
        expect($result->value())->toBe('HELLO');
        expect($result->computation()->has($customTag::class))->toBeTrue();
        expect($result->computation()->first($customTag::class)->value)->toBe('test');
    });
});