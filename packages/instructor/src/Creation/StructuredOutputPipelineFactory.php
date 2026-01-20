<?php declare(strict_types=1);

namespace Cognesy\Instructor\Creation;

use Cognesy\Events\Contracts\CanHandleEvents;
use Cognesy\Http\Creation\HttpClientBuilder;
use Cognesy\Http\HttpClient;
use Cognesy\Instructor\Config\StructuredOutputConfig;
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
use Cognesy\Polyglot\Inference\LLMProvider;

final readonly class StructuredOutputPipelineFactory
{
    /** @param array<CanValidateObject|class-string<CanValidateObject>> $validators */
    /** @param array<CanTransformData|class-string<CanTransformData>> $transformers */
    /** @param array<CanDeserializeClass|class-string<CanDeserializeClass>> $deserializers */
    /** @param array<CanExtractResponse|class-string<CanExtractResponse>> $extractors */
    public function __construct(
        private CanHandleEvents $events,
        private StructuredOutputConfig $config,
        private LLMProvider $llmProvider,
        private ?HttpClient $httpClient = null,
        private ?string $httpDebugPreset = null,
        private array $validators = [],
        private array $transformers = [],
        private array $deserializers = [],
        private ?CanExtractResponse $extractor = null,
        private array $extractors = [],
    ) {}

    public function createIteratorFactory(): ResponseIteratorFactory {
        $responseDeserializer = new ResponseDeserializer(
            events: $this->events,
            deserializers: $this->resolveDeserializers(),
            config: $this->config,
        );

        $responseValidator = new ResponseValidator(
            events: $this->events,
            validators: $this->resolveValidators(),
            config: $this->config,
        );

        $responseTransformer = new ResponseTransformer(
            events: $this->events,
            transformers: $this->resolveTransformers(),
            config: $this->config,
        );

        $extractor = $this->resolveExtractor();
        $httpClient = $this->resolveHttpClient();

        return new ResponseIteratorFactory(
            llmProvider: $this->llmProvider,
            responseDeserializer: $responseDeserializer,
            responseValidator: $responseValidator,
            responseTransformer: $responseTransformer,
            events: $this->events,
            extractor: $extractor,
            httpClient: $httpClient,
        );
    }

    /**
     * @return array<CanDeserializeClass|class-string<CanDeserializeClass>>
     */
    private function resolveDeserializers(): array {
        return match (true) {
            empty($this->deserializers) => [SymfonyDeserializer::class],
            default => $this->deserializers,
        };
    }

    /**
     * @return array<CanValidateObject|class-string<CanValidateObject>>
     */
    private function resolveValidators(): array {
        return match (true) {
            empty($this->validators) => [SymfonyValidator::class],
            default => $this->validators,
        };
    }

    /**
     * @return array<CanTransformData|class-string<CanTransformData>>
     */
    private function resolveTransformers(): array {
        return match (true) {
            empty($this->transformers) => [],
            default => $this->transformers,
        };
    }

    private function resolveExtractor(): CanExtractResponse {
        return match (true) {
            $this->extractor !== null => $this->extractor,
            !empty($this->extractors) => new ResponseExtractor(
                extractors: $this->extractors,
                events: $this->events,
            ),
            default => new ResponseExtractor(events: $this->events),
        };
    }

    private function resolveHttpClient(): HttpClient {
        return match (true) {
            $this->httpClient !== null => $this->httpClient,
            default => $this->makeDefaultHttpClient(),
        };
    }

    private function makeDefaultHttpClient(): HttpClient {
        $builder = new HttpClientBuilder(events: $this->events);
        if ($this->httpDebugPreset === null) {
            return $builder->create();
        }
        return $builder->withDebugPreset($this->httpDebugPreset)->create();
    }
}
