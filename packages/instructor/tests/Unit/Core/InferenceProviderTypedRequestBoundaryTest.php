<?php

declare(strict_types=1);

use Cognesy\Events\Dispatchers\EventDispatcher;
use Cognesy\Instructor\Config\StructuredOutputConfig;
use Cognesy\Instructor\Contracts\CanMaterializeRequest;
use Cognesy\Instructor\Core\InferenceProvider;
use Cognesy\Instructor\Creation\ResponseModelFactory;
use Cognesy\Instructor\Creation\StructuredOutputSchemaRenderer;
use Cognesy\Instructor\Data\StructuredOutputExecution;
use Cognesy\Instructor\Data\StructuredOutputRequest;
use Cognesy\Instructor\Enums\OutputMode;
use Cognesy\Instructor\Tests\Support\FakeInferenceDriver;
use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\Contracts\CanCreateInference;
use Cognesy\Polyglot\Inference\Data\InferenceExecution;
use Cognesy\Polyglot\Inference\Data\InferenceRequest;
use Cognesy\Polyglot\Inference\Data\ResponseFormat;
use Cognesy\Polyglot\Inference\Data\ToolChoice;
use Cognesy\Polyglot\Inference\Data\ToolDefinitions;
use Cognesy\Polyglot\Inference\PendingInference;

it('forwards typed request pieces from instructor into inference request', function () {
    $config = new StructuredOutputConfig(outputMode: OutputMode::Tools);
    $factory = new ResponseModelFactory(
        new StructuredOutputSchemaRenderer($config),
        $config,
        new EventDispatcher(),
    );
    $responseModel = $factory->fromAny(\stdClass::class);
    $materializedMessages = Messages::fromString('Extract structured data');

    $execution = new StructuredOutputExecution(
        request: new StructuredOutputRequest(model: 'gpt-4o-mini'),
        config: $config,
        responseModel: $responseModel,
    );

    $capturingInference = new class implements CanCreateInference {
        public ?InferenceRequest $captured = null;

        public function create(?InferenceRequest $request = null): PendingInference
        {
            $this->captured = $request;
            assert($request instanceof InferenceRequest);

            return new PendingInference(
                execution: InferenceExecution::fromRequest($request),
                driver: new FakeInferenceDriver(),
                eventDispatcher: new EventDispatcher(),
            );
        }
    };

    $materializer = new class($materializedMessages) implements CanMaterializeRequest {
        public function __construct(private Messages $messages) {}

        public function toMessages(StructuredOutputExecution $execution): Messages
        {
            return $this->messages;
        }
    };

    $pending = (new InferenceProvider($capturingInference, $materializer))->getInference($execution);

    expect($pending)->toBeInstanceOf(PendingInference::class)
        ->and($capturingInference->captured)->toBeInstanceOf(InferenceRequest::class)
        ->and($capturingInference->captured?->messages())->toBe($materializedMessages)
        ->and($capturingInference->captured?->tools())->toBeInstanceOf(ToolDefinitions::class)
        ->and($capturingInference->captured?->tools()->toArray())->toBe($responseModel->toolDefinitions()->toArray())
        ->and($capturingInference->captured?->toolChoice())->toBeInstanceOf(ToolChoice::class)
        ->and($capturingInference->captured?->toolChoice()->toArray())->toBe($responseModel->toolChoice()->toArray())
        ->and($capturingInference->captured?->responseFormat())->toBeInstanceOf(ResponseFormat::class)
        ->and($capturingInference->captured?->responseFormat())->toBe($responseModel->responseFormat())
        ->and($capturingInference->captured?->model())->toBe('gpt-4o-mini');
});
