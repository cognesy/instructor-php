<?php declare(strict_types=1);

namespace Cognesy\Instructor\Creation;

use Cognesy\Events\Contracts\CanHandleEvents;
use Cognesy\Instructor\Config\StructuredOutputConfig;
use Cognesy\Instructor\Contracts\CanMaterializeRequest;
use Cognesy\Instructor\Deserialization\Contracts\CanDeserializeClass;
use Cognesy\Instructor\Deserialization\Deserializers\SymfonyDeserializer;
use Cognesy\Instructor\Deserialization\ResponseDeserializer;
use Cognesy\Instructor\Extraction\Contracts\CanExtractResponse;
use Cognesy\Instructor\Extraction\ResponseExtractor;
use Cognesy\Instructor\Transformation\Contracts\CanTransformData;
use Cognesy\Instructor\Transformation\ResponseTransformer;
use Cognesy\Instructor\Validation\Contracts\CanValidateObject;
use Cognesy\Instructor\Validation\ResponseValidator;
use Cognesy\Instructor\Validation\Validators\SymfonyValidator;
use Cognesy\Polyglot\Inference\Contracts\CanCreateInference;

final readonly class StructuredOutputPipelineFactory
{
    public function __construct(
        private CanHandleEvents $events,
        private StructuredOutputConfig $config,
        private CanCreateInference $inference,
        private CanMaterializeRequest $requestMaterializer,
        private ?CanValidateObject $validator = null,
        private ?CanTransformData $transformer = null,
        private ?CanDeserializeClass $deserializer = null,
        private ?CanExtractResponse $extractor = null,
    ) {}

    public function createExecutionDriverFactory(): ExecutionDriverFactory {
        $responseDeserializer = new ResponseDeserializer(
            events: $this->events,
            deserializer: $this->resolveDeserializer(),
            config: $this->config,
        );

        $responseValidator = new ResponseValidator(
            events: $this->events,
            validator: $this->resolveValidator(),
            config: $this->config,
        );

        $responseTransformer = new ResponseTransformer(
            events: $this->events,
            transformer: $this->transformer,
            config: $this->config,
        );

        $extractor = $this->resolveExtractor();

        return new ExecutionDriverFactory(
            inference: $this->inference,
            responseDeserializer: $responseDeserializer,
            responseValidator: $responseValidator,
            responseTransformer: $responseTransformer,
            events: $this->events,
            extractor: $extractor,
            requestMaterializer: $this->requestMaterializer,
        );
    }

    private function resolveDeserializer(): CanDeserializeClass {
        return $this->deserializer ?? new SymfonyDeserializer();
    }

    private function resolveValidator(): CanValidateObject {
        return $this->validator ?? new SymfonyValidator();
    }

    private function resolveExtractor(): CanExtractResponse {
        return $this->extractor ?? new ResponseExtractor(events: $this->events);
    }

}
