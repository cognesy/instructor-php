<?php declare(strict_types=1);

namespace Cognesy\Agents\Tests\Unit\Agent;

use Cognesy\Agents\Collections\Tools;
use Cognesy\Agents\Context\AgentContext;
use Cognesy\Agents\Data\AgentState;
use Cognesy\Agents\Drivers\ToolCalling\ToolCallingDriver;
use Cognesy\Agents\Enums\ExecutionStatus;
use Cognesy\Agents\Tool\Contracts\ToolInterface;
use Cognesy\Agents\Tool\ToolDescriptor;
use Cognesy\Events\Dispatchers\EventDispatcher;
use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\Contracts\CanCreateInference;
use Cognesy\Polyglot\Inference\Data\InferenceExecution;
use Cognesy\Polyglot\Inference\Data\InferenceRequest;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\Data\ResponseFormat;
use Cognesy\Polyglot\Inference\Data\ToolChoice;
use Cognesy\Polyglot\Inference\Data\ToolDefinition;
use Cognesy\Polyglot\Inference\PendingInference;
use Cognesy\Utils\Result\Result;
use ReflectionMethod;

it('keeps agent inference boundary typed', function () {
    $driverCtor = new ReflectionMethod(ToolCallingDriver::class, '__construct');
    $contextCtor = new ReflectionMethod(AgentContext::class, '__construct');
    $withResponseFormat = new ReflectionMethod(AgentContext::class, 'withResponseFormat');

    expect((string) $driverCtor->getParameters()[3]->getType())->toBe('?Cognesy\\Polyglot\\Inference\\Data\\ToolChoice')
        ->and((string) $driverCtor->getParameters()[4]->getType())->toBe('?Cognesy\\Polyglot\\Inference\\Data\\ResponseFormat')
        ->and((string) $contextCtor->getParameters()[3]->getType())->toBe('?Cognesy\\Polyglot\\Inference\\Data\\ResponseFormat')
        ->and((string) $withResponseFormat->getParameters()[0]->getType())->toBe('Cognesy\\Polyglot\\Inference\\Data\\ResponseFormat');
});

it('builds tool-calling inference requests entirely from typed objects', function () {
    $capturingInference = new class implements CanCreateInference {
        public ?InferenceRequest $captured = null;

        public function create(?InferenceRequest $request = null): PendingInference
        {
            $this->captured = $request;
            assert($request instanceof InferenceRequest);

            return new PendingInference(
                execution: InferenceExecution::fromRequest($request),
                driver: new \Cognesy\Agents\Tests\Support\FakeInferenceDriver([
                    InferenceResponse::empty()->withContent('done'),
                ]),
                eventDispatcher: new EventDispatcher(),
            );
        }
    };

    $tool = new class implements ToolInterface {
        public function use(mixed ...$args): Result
        {
            return Result::success('ok');
        }

        public function toToolSchema(): ToolDefinition
        {
            return ToolDefinition::fromArray([
                'type' => 'function',
                'function' => [
                    'name' => 'lookup_weather',
                    'description' => 'Look up the weather.',
                    'parameters' => ['type' => 'object'],
                ],
            ]);
        }

        public function descriptor(): \Cognesy\Agents\Tool\Contracts\CanDescribeTool
        {
            return new ToolDescriptor('lookup_weather', 'Look up the weather.');
        }
    };

    $driver = new ToolCallingDriver(
        inference: $capturingInference,
        toolChoice: ToolChoice::required(),
        responseFormat: ResponseFormat::jsonObject(),
        tools: new Tools($tool),
    );

    $state = AgentState::empty()
        ->with(context: (new AgentContext(
            systemPrompt: 'You are a careful assistant.',
            responseFormat: ResponseFormat::jsonObject(),
        )))
        ->withUserMessage('What is the weather?');

    $driver->useTools($state);

    expect($capturingInference->captured)->toBeInstanceOf(InferenceRequest::class)
        ->and($capturingInference->captured?->messages())->toBeInstanceOf(Messages::class)
        ->and($capturingInference->captured?->tools()->count())->toBe(0)
        ->and($capturingInference->captured?->toolChoice())->toEqual(ToolChoice::required())
        ->and($capturingInference->captured?->responseFormat())->toEqual(ResponseFormat::jsonObject())
        ->and($capturingInference->captured?->cachedContext())->not()->toBeNull()
        ->and($capturingInference->captured?->cachedContext()?->messages())->toBeInstanceOf(Messages::class)
        ->and($capturingInference->captured?->cachedContext()?->messages()->first()?->role()->value)->toBe('system')
        ->and($capturingInference->captured?->cachedContext()?->tools()->count())->toBe(1)
        ->and(array_values($capturingInference->captured?->cachedContext()?->tools()->all() ?? [])[0] ?? null)->toBeInstanceOf(ToolDefinition::class)
        ->and($capturingInference->captured?->cachedContext()?->responseFormat())->toEqual(ResponseFormat::jsonObject());
});

it('stamps inference requests with execution and step telemetry correlation when agent execution exists', function () {
    $capturingInference = new class implements CanCreateInference {
        public ?InferenceRequest $captured = null;

        public function create(?InferenceRequest $request = null): PendingInference
        {
            $this->captured = $request;
            assert($request instanceof InferenceRequest);

            return new PendingInference(
                execution: InferenceExecution::fromRequest($request),
                driver: new \Cognesy\Agents\Tests\Support\FakeInferenceDriver([
                    InferenceResponse::empty()->withContent('done'),
                ]),
                eventDispatcher: new EventDispatcher(),
            );
        }
    };

    $driver = new ToolCallingDriver(
        inference: $capturingInference,
    );

    $state = AgentState::empty()
        ->withExecutionStatus(ExecutionStatus::InProgress)
        ->withMessages(Messages::fromString('Hi'));

    $executionId = $state->execution()?->executionId()->toString();
    expect($executionId)->not()->toBeNull()->and($executionId)->not()->toBe('');

    $driver->useTools($state);

    expect($capturingInference->captured?->telemetryCorrelation()?->rootOperationId())->toBe($executionId)
        ->and($capturingInference->captured?->telemetryCorrelation()?->parentOperationId())->toBe("{$executionId}:step:1");
});
