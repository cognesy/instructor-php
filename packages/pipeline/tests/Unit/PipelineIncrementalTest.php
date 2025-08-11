<?php declare(strict_types=1);

use Cognesy\Pipeline\Internal\OperatorStack;
use Cognesy\Pipeline\Operators\Finalize;
use Cognesy\Pipeline\Pipeline;
use Cognesy\Pipeline\ProcessingState;

describe('Pipeline Incremental Tests - Missing Coverage', function () {

    describe('constructor', function () {
        it('creates pipeline with all parameters', function () {
            $processors = new OperatorStack();
            $finalizers = (new OperatorStack())->add(Finalize::with(fn($state) => $state->value()));
            $middleware = new OperatorStack();
            $hooks = new OperatorStack();
            
            $pipeline = new Pipeline(
                processors: $processors,
                finalizers: $finalizers,
                middleware: $middleware,
                hooks: $hooks,
            );
            
            expect($pipeline)->toBeInstanceOf(Pipeline::class);
        });

        it('creates pipeline with default parameters', function () {
            $pipeline = new Pipeline();
            
            expect($pipeline)->toBeInstanceOf(Pipeline::class);
        });
    });

    describe('process method', function () {
        it('processes ProcessingState directly', function () {
            $pipeline = new Pipeline();
            $state = ProcessingState::with(42);
            
            $result = $pipeline->process($state);
            
            expect($result)->toBeInstanceOf(ProcessingState::class);
            expect($result->value())->toBe(42);
        });

        it('handles empty processing state', function () {
            $pipeline = new Pipeline();
            $state = ProcessingState::empty();
            
            $result = $pipeline->process($state);
            
            expect($result)->toBeInstanceOf(ProcessingState::class);
            expect($result->isSuccess())->toBeTrue();
        });

        it('handles failed processing state', function () {
            $pipeline = new Pipeline();
            $state = ProcessingState::empty()->failWith(new RuntimeException('Test error'));
            
            $result = $pipeline->process($state);
            
            expect($result)->toBeInstanceOf(ProcessingState::class);
            expect($result->isFailure())->toBeTrue();
            expect($result->exception()->getMessage())->toBe('Test error');
        });

        it('applies finalizer on successful state', function () {
            $finalizers = (new OperatorStack())->add(Finalize::with(fn($state) => $state->value() * 2));
            $pipeline = new Pipeline(finalizers: $finalizers);
            $state = ProcessingState::with(10);
            
            $result = $pipeline->process($state);
            
            expect($result->value())->toBe(20);
        });

        it('handles finalizer exception', function () {
            $finalizers = (new OperatorStack())->add(Finalize::with(fn($state) => throw new RuntimeException('Finalizer error')));
            $pipeline = new Pipeline(finalizers: $finalizers);
            $state = ProcessingState::with(10);
            
            $result = $pipeline->process($state);
            
            expect($result->isFailure())->toBeTrue();
            expect($result->exception()->getMessage())->toBe('Finalizer error');
        });
    });
});