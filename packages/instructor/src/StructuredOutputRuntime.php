<?php declare(strict_types=1);

namespace Cognesy\Instructor;

use Cognesy\Events\Contracts\CanHandleEvents;
use Cognesy\Instructor\Config\StructuredOutputConfig;
use Cognesy\Instructor\Contracts\CanCreateStructuredOutput;
use Cognesy\Instructor\Creation\StructuredOutputExecutionBuilder;
use Cognesy\Instructor\Creation\StructuredOutputPipelineFactory;
use Cognesy\Instructor\Data\StructuredOutputRequest;
use Cognesy\Instructor\Deserialization\Contracts\CanDeserializeClass;
use Cognesy\Instructor\Events\StructuredOutput\StructuredOutputRequestReceived;
use Cognesy\Instructor\Extraction\Contracts\CanExtractResponse;
use Cognesy\Instructor\Transformation\Contracts\CanTransformData;
use Cognesy\Instructor\Validation\Contracts\CanValidateObject;
use Cognesy\Polyglot\Inference\Contracts\CanCreateInference;

final class StructuredOutputRuntime implements CanCreateStructuredOutput
{
    /** @param array<CanValidateObject|class-string<CanValidateObject>> $validators */
    /** @param array<CanTransformData|class-string<CanTransformData>> $transformers */
    /** @param array<CanDeserializeClass|class-string<CanDeserializeClass>> $deserializers */
    /** @param array<CanExtractResponse|class-string<CanExtractResponse>> $extractors */
    public function __construct(
        private readonly CanCreateInference $inference,
        private readonly CanHandleEvents $events,
        private readonly StructuredOutputConfig $config,
        private readonly array $validators = [],
        private readonly array $transformers = [],
        private readonly array $deserializers = [],
        private readonly ?CanExtractResponse $extractor = null,
        private readonly array $extractors = [],
    ) {}

    #[\Override]
    public function create(StructuredOutputRequest $request): PendingStructuredOutput {
        if (!$request->hasRequestedSchema()) {
            throw new \InvalidArgumentException('Response model cannot be empty. Provide a class name, instance, or schema array.');
        }

        $execution = (new StructuredOutputExecutionBuilder($this->events))->createWith(
            request: $request,
            config: $this->config,
        );

        $this->events->dispatch(new StructuredOutputRequestReceived(['request' => $request->toArray()]));

        $pipelineFactory = new StructuredOutputPipelineFactory(
            events: $this->events,
            config: $this->config,
            inference: $this->inference,
            validators: $this->validators,
            transformers: $this->transformers,
            deserializers: $this->deserializers,
            extractor: $this->extractor,
            extractors: $this->extractors,
        );

        return new PendingStructuredOutput(
            execution: $execution,
            executorFactory: $pipelineFactory->createIteratorFactory(),
            events: $this->events,
        );
    }
}

