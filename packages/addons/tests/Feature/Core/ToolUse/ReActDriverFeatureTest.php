<?php declare(strict_types=1);

use Cognesy\Addons\StepByStep\Continuation\ContinuationCriteria;
use Cognesy\Addons\StepByStep\Continuation\Criteria\ExecutionTimeLimit;
use Cognesy\Addons\StepByStep\Continuation\Criteria\RetryLimit;
use Cognesy\Addons\StepByStep\Continuation\Criteria\StepsLimit;
use Cognesy\Addons\StepByStep\Continuation\Criteria\TokenUsageLimit;
use Cognesy\Addons\ToolUse\Collections\Tools;
use Cognesy\Addons\ToolUse\Collections\ToolUseSteps;
use Cognesy\Addons\ToolUse\Data\ToolUseState;
use Cognesy\Addons\ToolUse\Data\ToolUseStep;
use Cognesy\Addons\ToolUse\Drivers\ReAct\ContinuationCriteria\StopOnFinalDecision;
use Cognesy\Addons\ToolUse\Drivers\ReAct\ReActDriver;
use Cognesy\Addons\ToolUse\Enums\ToolUseStepType;
use Cognesy\Addons\ToolUse\Tools\FunctionTool;
use Cognesy\Addons\ToolUse\ToolUseFactory;
use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\LLMProvider;
use Tests\Addons\Support\FakeInferenceRequestDriver;


function react_add_numbers(int $a, int $b): int {
    return $a + $b;
}

function react_subtract_numbers(int $a, int $b): int {
    return $a - $b;
}

// Helper function to create default tools and continuation criteria
function makeToolsAndCriteria(): array {
    $tools = new Tools(
        FunctionTool::fromCallable(react_add_numbers(...)),
        FunctionTool::fromCallable(react_subtract_numbers(...))
    );

    $continuationCriteria = new ContinuationCriteria(
        new StepsLimit(6, fn(ToolUseState $state) => $state->stepCount()),
        new TokenUsageLimit(8192, fn(ToolUseState $state) => $state->usage()->total()),
        new ExecutionTimeLimit(60, fn(ToolUseState $state) => $state->startedAt()),
        new RetryLimit(2, fn(ToolUseState $state) => $state->steps(), fn(ToolUseStep $step) => $step->hasErrors()),
        new StopOnFinalDecision(),
    );

    return [$tools, $continuationCriteria];
}

describe('ReActDriver Feature Tests', function () {
    describe('Pattern #1: Manual step-by-step execution control', function () {
        it('executes ReAct workflow with manual control', function () {
            // Arrange
            [$tools, $continuationCriteria] = makeToolsAndCriteria();

            $driver = new FakeInferenceRequestDriver([
                new InferenceResponse(content: json_encode([
                    'thought' => 'I need to add 2455 and 3558 first',
                    'type' => 'call_tool',
                    'tool' => 'react_add_numbers',
                    'args' => ['a' => 2455, 'b' => 3558]
                ])),
                new InferenceResponse(content: json_encode([
                    'thought' => 'Now I need to subtract 4344 from 6013',
                    'type' => 'call_tool',
                    'tool' => 'react_subtract_numbers',
                    'args' => ['a' => 6013, 'b' => 4344]
                ])),
                new InferenceResponse(content: json_encode([
                    'thought' => 'I have completed the calculation',
                    'type' => 'final_answer',
                    'answer' => 'The result is 1669'
                ])),
            ]);

            $reactDriver = new ReActDriver(llm: LLMProvider::new()->withDriver($driver));
            $toolUse = ToolUseFactory::default(
                tools: $tools,
                continuationCriteria: $continuationCriteria,
                driver: $reactDriver
            );

            $state = (new ToolUseState())
                ->withMessages(Messages::fromString('Add 2455 and 3558 then subtract 4344 from the result.'));

            // Act: Execute step by step with manual control
            $stepCount = 0;
            while ($toolUse->hasNextStep($state)) {
                $state = $toolUse->nextStep($state);
                $stepCount++;
                expect($state->currentStep())->toBeInstanceOf(ToolUseStep::class);
            }

            // Assert
            expect($stepCount)->toBeGreaterThan(0);
            expect($state->currentStep()->stepType())->toBe(ToolUseStepType::FinalResponse);
            expect($state->currentStep()->outputMessages()->toString())->toContain('1669');
        });

        it('handles errors gracefully in manual control mode', function () {
            // Arrange
            [$tools, $continuationCriteria] = makeToolsAndCriteria();

            $driver = new FakeInferenceRequestDriver([
                new InferenceResponse(content: json_encode([
                    'thought' => 'I will call a non-existent tool',
                    'type' => 'call_tool',
                    'tool' => 'nonexistent_tool',
                    'args' => ['a' => 1, 'b' => 2]
                ])),
                // Second response won't be used since error stops iteration
                new InferenceResponse(content: json_encode([
                    'thought' => 'The tool does not exist, I will provide an answer',
                    'type' => 'final_answer',
                    'answer' => 'Unable to complete calculation'
                ])),
            ]);

            $reactDriver = new ReActDriver(llm: LLMProvider::new()->withDriver($driver));
            $toolUse = ToolUseFactory::default(
                tools: $tools,
                continuationCriteria: $continuationCriteria,
                driver: $reactDriver
            );

            $state = (new ToolUseState())
                ->withMessages(Messages::fromString('Calculate something'));

            // Act
            $errorOccurred = false;
            while ($toolUse->hasNextStep($state)) {
                $state = $toolUse->nextStep($state);
                if ($state->currentStep()->hasErrors()) {
                    $errorOccurred = true;
                }
            }

            // Assert: When tool doesn't exist, we get an Error step type
            // StopOnFinalDecision stops iteration on both Error and FinalResponse
            expect($errorOccurred)->toBeTrue();
            expect($state->currentStep()->stepType())->toBe(ToolUseStepType::Error);
            expect($state->currentStep()->outputMessages()->toString())->toContain('ERROR');
        });
    });

    describe('Pattern #2: Using iterator', function () {
        it('executes ReAct workflow using iterator', function () {
            // Arrange
            [$tools, $continuationCriteria] = makeToolsAndCriteria();

            $driver = new FakeInferenceRequestDriver([
                new InferenceResponse(content: json_encode([
                    'thought' => 'Adding 2455 and 3558',
                    'type' => 'call_tool',
                    'tool' => 'react_add_numbers',
                    'args' => ['a' => 2455, 'b' => 3558]
                ])),
                new InferenceResponse(content: json_encode([
                    'thought' => 'Subtracting 4344 from 6013',
                    'type' => 'call_tool',
                    'tool' => 'react_subtract_numbers',
                    'args' => ['a' => 6013, 'b' => 4344]
                ])),
                new InferenceResponse(content: json_encode([
                    'thought' => 'Calculation complete',
                    'type' => 'final_answer',
                    'answer' => 'The final result is 1669'
                ])),
            ]);

            $reactDriver = new ReActDriver(llm: LLMProvider::new()->withDriver($driver));
            $toolUse = ToolUseFactory::default(
                tools: $tools,
                continuationCriteria: $continuationCriteria,
                driver: $reactDriver
            );

            $initialState = (new ToolUseState())
                ->withMessages(Messages::fromString('Add 2455 and 3558 then subtract 4344 from the result.'));

            // Act: Use iterator pattern
            $stepCount = 0;
            $finalState = null;
            foreach ($toolUse->iterator($initialState) as $currentState) {
                $stepCount++;
                $finalState = $currentState;
                expect($currentState)->toBeInstanceOf(ToolUseState::class);
                expect($currentState->currentStep())->toBeInstanceOf(ToolUseStep::class);
            }

            // Assert
            expect($stepCount)->toBeGreaterThan(0);
            expect($finalState)->not->toBeNull();
            expect($finalState->currentStep()->stepType())->toBe(ToolUseStepType::FinalResponse);
            expect($finalState->currentStep()->outputMessages()->toString())->toContain('1669');
        });

        it('can track progress through iterator', function () {
            // Arrange
            [$tools, $continuationCriteria] = makeToolsAndCriteria();

            $driver = new FakeInferenceRequestDriver([
                new InferenceResponse(content: json_encode([
                    'thought' => 'Step 1',
                    'type' => 'call_tool',
                    'tool' => 'react_add_numbers',
                    'args' => ['a' => 10, 'b' => 20]
                ])),
                new InferenceResponse(content: json_encode([
                    'thought' => 'Step 2',
                    'type' => 'final_answer',
                    'answer' => '30'
                ])),
            ]);

            $reactDriver = new ReActDriver(llm: LLMProvider::new()->withDriver($driver));
            $toolUse = ToolUseFactory::default(
                tools: $tools,
                continuationCriteria: $continuationCriteria,
                driver: $reactDriver
            );

            $state = (new ToolUseState())
                ->withMessages(Messages::fromString('Add 10 and 20'));

            // Act: Track steps
            $steps = [];
            foreach ($toolUse->iterator($state) as $currentState) {
                $steps[] = $currentState->currentStep();
            }

            // Assert: All steps captured
            expect($steps)->not->toBeEmpty();
            expect($steps[0]->stepType())->toBe(ToolUseStepType::ToolExecution);
        });
    });

    describe('Pattern #3: Getting immediate final result', function () {
        it('fast-forwards to final result without iteration', function () {
            // Arrange
            [$tools, $continuationCriteria] = makeToolsAndCriteria();

            $driver = new FakeInferenceRequestDriver([
                new InferenceResponse(content: json_encode([
                    'thought' => 'Adding numbers',
                    'type' => 'call_tool',
                    'tool' => 'react_add_numbers',
                    'args' => ['a' => 2455, 'b' => 3558]
                ])),
                new InferenceResponse(content: json_encode([
                    'thought' => 'Subtracting from result',
                    'type' => 'call_tool',
                    'tool' => 'react_subtract_numbers',
                    'args' => ['a' => 6013, 'b' => 4344]
                ])),
                new InferenceResponse(content: json_encode([
                    'thought' => 'Done',
                    'type' => 'final_answer',
                    'answer' => 'Result: 1669'
                ])),
            ]);

            $reactDriver = new ReActDriver(llm: LLMProvider::new()->withDriver($driver));
            $toolUse = ToolUseFactory::default(
                tools: $tools,
                continuationCriteria: $continuationCriteria,
                driver: $reactDriver
            );

            $initialState = (new ToolUseState())
                ->withMessages(Messages::fromString('Add 2455 and 3558 then subtract 4344 from the result.'));

            // Act
            $finalState = $toolUse->finalStep($initialState);

            // Assert
            expect($finalState->currentStep()->stepType())->toBe(ToolUseStepType::FinalResponse);
            expect($finalState->currentStep()->outputMessages()->toString())->toContain('1669');
            expect($finalState->stepCount())->toBeGreaterThan(0);
        });

        it('uses finalViaInference when configured', function () {
            // Arrange
            [$tools, $continuationCriteria] = makeToolsAndCriteria();

            $driver = new FakeInferenceRequestDriver([
                new InferenceResponse(content: json_encode([
                    'thought' => 'Adding',
                    'type' => 'call_tool',
                    'tool' => 'react_add_numbers',
                    'args' => ['a' => 100, 'b' => 50]
                ])),
                new InferenceResponse(content: json_encode([
                    'thought' => 'Ready to finalize',
                    'type' => 'final_answer',
                    'answer' => 'stub answer'
                ])),
                // Final via inference (plain text)
                new InferenceResponse(content: 'The calculated result is 150'),
            ]);

            $reactDriver = new ReActDriver(
                llm: LLMProvider::new()->withDriver($driver),
                finalViaInference: true,
            );
            $toolUse = ToolUseFactory::default(
                tools: $tools,
                continuationCriteria: $continuationCriteria,
                driver: $reactDriver
            );

            $state = (new ToolUseState())
                ->withMessages(Messages::fromString('Add 100 and 50'));

            // Act
            $finalState = $toolUse->finalStep($state);

            // Assert
            expect($finalState->currentStep()->stepType())->toBe(ToolUseStepType::FinalResponse);
            expect($finalState->currentStep()->outputMessages()->toString())->toContain('150');
        });

        it('returns immediately when already at final state', function () {
            // Arrange
            [$tools, $continuationCriteria] = makeToolsAndCriteria();

            $driver = new FakeInferenceRequestDriver([
                new InferenceResponse(content: json_encode([
                    'thought' => 'Already have answer',
                    'type' => 'final_answer',
                    'answer' => 'The answer is 42'
                ])),
            ]);

            $reactDriver = new ReActDriver(llm: LLMProvider::new()->withDriver($driver));
            $toolUse = ToolUseFactory::default(
                tools: $tools,
                continuationCriteria: $continuationCriteria,
                driver: $reactDriver
            );

            $state = (new ToolUseState())
                ->withMessages(Messages::fromString('What is the answer?'));

            // Act
            $finalState = $toolUse->finalStep($state);

            // Assert
            expect($finalState->stepCount())->toBe(1);
            expect($finalState->currentStep()->stepType())->toBe(ToolUseStepType::FinalResponse);
            expect($finalState->currentStep()->outputMessages()->toString())->toContain('42');
        });
    });

    describe('Cross-pattern validation', function () {
        it('produces same result across all three patterns', function () {
            // Arrange: Shared mock responses
            $mockResponses = [
                new InferenceResponse(content: json_encode([
                    'thought' => 'Adding',
                    'type' => 'call_tool',
                    'tool' => 'react_add_numbers',
                    'args' => ['a' => 5, 'b' => 3]
                ])),
                new InferenceResponse(content: json_encode([
                    'thought' => 'Done',
                    'type' => 'final_answer',
                    'answer' => 'Sum is 8'
                ])),
            ];

            $query = 'Add 5 and 3';

            // Pattern 1: Manual control
            [$tools1, $criteria1] = makeToolsAndCriteria();
            $driver1 = new FakeInferenceRequestDriver($mockResponses);
            $reactDriver1 = new ReActDriver(llm: LLMProvider::new()->withDriver($driver1));
            $toolUse1 = ToolUseFactory::default(
                tools: $tools1,
                continuationCriteria: $criteria1,
                driver: $reactDriver1
            );
            $state1 = (new ToolUseState())->withMessages(Messages::fromString($query));
            while ($toolUse1->hasNextStep($state1)) {
                $state1 = $toolUse1->nextStep($state1);
            }
            $result1 = $state1->currentStep()->outputMessages()->toString();

            // Pattern 2: Iterator
            [$tools2, $criteria2] = makeToolsAndCriteria();
            $driver2 = new FakeInferenceRequestDriver($mockResponses);
            $reactDriver2 = new ReActDriver(llm: LLMProvider::new()->withDriver($driver2));
            $toolUse2 = ToolUseFactory::default(
                tools: $tools2,
                continuationCriteria: $criteria2,
                driver: $reactDriver2
            );
            $state2 = (new ToolUseState())->withMessages(Messages::fromString($query));
            foreach ($toolUse2->iterator($state2) as $currentState) {
                $state2 = $currentState;
            }
            $result2 = $state2->currentStep()->outputMessages()->toString();

            // Pattern 3: Final step
            [$tools3, $criteria3] = makeToolsAndCriteria();
            $driver3 = new FakeInferenceRequestDriver($mockResponses);
            $reactDriver3 = new ReActDriver(llm: LLMProvider::new()->withDriver($driver3));
            $toolUse3 = ToolUseFactory::default(
                tools: $tools3,
                continuationCriteria: $criteria3,
                driver: $reactDriver3
            );
            $state3 = (new ToolUseState())->withMessages(Messages::fromString($query));
            $state3 = $toolUse3->finalStep($state3);
            $result3 = $state3->currentStep()->outputMessages()->toString();

            // Assert: All patterns produce same result
            expect($result1)->toBe($result2);
            expect($result2)->toBe($result3);
            expect($result1)->toContain('Sum is 8');
        });
    });
});
