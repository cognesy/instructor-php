<?php declare(strict_types=1);

namespace Cognesy\Instructor\Traits;

use Cognesy\Instructor\Creation\StructuredOutputRequestBuilder;
use Cognesy\Instructor\Data\OutputFormat;
use Cognesy\Instructor\Deserialization\Contracts\CanDeserializeSelf;
use Cognesy\Instructor\StructuredOutput;
use Cognesy\Messages\Message;
use Cognesy\Messages\Messages;
use Cognesy\Utils\JsonSchema\Contracts\CanProvideJsonSchema;

/**
 * @template TResponse
 */
trait HandlesRequestBuilder
{
    private StructuredOutputRequestBuilder $requestBuilder;
    private ?OutputFormat $outputFormat = null;

    public function withMessages(string|array|Message|Messages $messages): static {
        $this->requestBuilder->withMessages($messages);
        return $this;
    }

    public function withInput(mixed $input): static {
        $messages = Messages::fromInput($input);
        $this->requestBuilder->withMessages($messages);
        return $this;
    }

    public function withResponseModel(string|array|object $responseModel) : static {
        $this->requestBuilder->withResponseModel($responseModel);
        return $this;
    }

    public function withResponseJsonSchema(array|CanProvideJsonSchema $jsonSchema) : static {
        $this->requestBuilder->withResponseModel($jsonSchema);
        return $this;
    }

    /**
     * @param class-string<TResponse> $class
     * @return StructuredOutput<TResponse>
     */
    public function withResponseClass(string $class) : StructuredOutput {
        $this->requestBuilder->withResponseModel($class);
        return $this;
    }

    /**
     * @param object<TResponse> $responseObject
     * @return StructuredOutput<TResponse>
     */
    public function withResponseObject(object $responseObject) : StructuredOutput {
        $this->requestBuilder->withResponseModel($responseObject);
        return $this;
    }

    public function withSystem(string $system): static {
        $this->requestBuilder->withSystem($system);
        return $this;
    }

    public function withPrompt(string $prompt): static {
        $this->requestBuilder->withPrompt($prompt);
        return $this;
    }

    public function withExamples(array $examples): static {
        $this->requestBuilder->withExamples($examples);
        return $this;
    }

    public function withModel(string $model): static {
        $this->requestBuilder->withModel($model);
        return $this;
    }

    public function withOptions(array $options): static {
        $this->requestBuilder->withOptions($options);
        return $this;
    }

    public function withOption(string $key, mixed $value): static {
        $this->requestBuilder->withOption($key, $value);
        return $this;
    }

    public function withStreaming(bool $stream = true): static {
        $this->withOption('stream', $stream);
        return $this;
    }

    public function withCachedContext(
        string|array $messages = '',
        string $system = '',
        string $prompt = '',
        array $examples = [],
    ) : ?self {
        $this->requestBuilder->withCachedContext($messages, $system, $prompt, $examples);
        return $this;
    }

    // OUTPUT FORMAT METHODS ///////////////////////////////////////////////

    /**
     * Return extracted data as raw associative array (skip object deserialization).
     *
     * @return static
     */
    public function intoArray(): static {
        $this->outputFormat = OutputFormat::array();
        return $this;
    }

    /**
     * Hydrate extracted data into the specified class.
     *
     * Allows using one class for schema (sent to LLM) and a different class for output.
     *
     * @param class-string $class Target class for deserialization
     * @return static
     */
    public function intoInstanceOf(string $class): static {
        $this->outputFormat = OutputFormat::instanceOf($class);
        return $this;
    }

    /**
     * Use a self-deserializing object for output.
     *
     * @param CanDeserializeSelf $object Object implementing CanDeserializeSelf
     * @return static
     */
    public function intoObject(CanDeserializeSelf $object): static {
        $this->outputFormat = OutputFormat::selfDeserializing($object);
        return $this;
    }

    /**
     * Get the configured output format (if any).
     *
     * @internal Used by StructuredOutput to apply OutputFormat to ResponseModel
     */
    protected function getOutputFormat(): ?OutputFormat {
        return $this->outputFormat;
    }
}
