<?php

declare(strict_types=1);

namespace Cognesy\Instructor\Laravel\Facades;

use Cognesy\Instructor\Laravel\Testing\StructuredOutputFake;
use Cognesy\Instructor\StructuredOutput as BaseStructuredOutput;
use Illuminate\Support\Facades\Facade;

/**
 * Facade for StructuredOutput
 *
 * @method static \Cognesy\Instructor\StructuredOutput using(string $preset)
 * @method static \Cognesy\Instructor\StructuredOutput with(string|array|\Cognesy\Messages\Message|\Cognesy\Messages\Messages|null $messages = null, string|array|object|null $responseModel = null, ?string $system = null, ?string $prompt = null, ?array $examples = null, ?string $model = null, ?int $maxRetries = null, ?array $options = null, ?string $toolName = null, ?string $toolDescription = null, ?string $retryPrompt = null, ?\Cognesy\Polyglot\Inference\Enums\OutputMode $mode = null, ?\Cognesy\Polyglot\Inference\Enums\ResponseCachePolicy $responseCachePolicy = null)
 * @method static \Cognesy\Instructor\StructuredOutput withMessages(string|array|\Cognesy\Messages\Message|\Cognesy\Messages\Messages $messages)
 * @method static \Cognesy\Instructor\StructuredOutput withResponseModel(string|array|object $responseModel)
 * @method static \Cognesy\Instructor\StructuredOutput withSystem(string $system)
 * @method static \Cognesy\Instructor\StructuredOutput withPrompt(string $prompt)
 * @method static \Cognesy\Instructor\StructuredOutput withExamples(array $examples)
 * @method static \Cognesy\Instructor\StructuredOutput withModel(string $model)
 * @method static \Cognesy\Instructor\StructuredOutput withMaxRetries(int $maxRetries)
 * @method static \Cognesy\Instructor\StructuredOutput withOptions(array $options)
 * @method static \Cognesy\Instructor\StructuredOutput withOutputMode(\Cognesy\Polyglot\Inference\Enums\OutputMode $mode)
 * @method static \Cognesy\Instructor\StructuredOutput withStreaming(bool $streaming = true)
 * @method static \Cognesy\Instructor\StructuredOutput withValidators(\Cognesy\Instructor\Validation\Contracts\CanValidateObject|string ...$validators)
 * @method static \Cognesy\Instructor\StructuredOutput withTransformers(\Cognesy\Instructor\Transformation\Contracts\CanTransformData|string ...$transformers)
 * @method static \Cognesy\Instructor\StructuredOutput withDeserializers(\Cognesy\Instructor\Deserialization\Contracts\CanDeserializeClass|string ...$deserializers)
 * @method static \Cognesy\Instructor\StructuredOutput withLLMConfig(\Cognesy\Polyglot\Inference\Config\LLMConfig $config)
 * @method static \Cognesy\Instructor\StructuredOutput withLLMConfigOverrides(array $overrides)
 * @method static \Cognesy\Instructor\StructuredOutput withDriver(\Cognesy\Polyglot\Inference\Contracts\CanProcessInferenceRequest $driver)
 * @method static \Cognesy\Instructor\StructuredOutput withHttpClient(\Cognesy\Http\HttpClient $httpClient)
 * @method static \Cognesy\Instructor\StructuredOutput withHttpDebugPreset(?string $preset)
 * @method static \Cognesy\Instructor\StructuredOutput withHttpDebug(bool $enabled = true)
 * @method static \Cognesy\Instructor\StructuredOutput withDebugPreset(string $preset)
 * @method static \Cognesy\Instructor\StructuredOutput onPartialUpdate(callable $callback)
 * @method static \Cognesy\Instructor\StructuredOutput onSequenceUpdate(callable $callback)
 * @method static \Cognesy\Instructor\StructuredOutput wiretap(callable $callback)
 * @method static \Cognesy\Instructor\PendingStructuredOutput create()
 * @method static mixed get()
 * @method static \Cognesy\Instructor\StructuredOutputStream stream()
 * @method static \Cognesy\Polyglot\Inference\Data\InferenceResponse response()
 * @method static string getString()
 * @method static float getFloat()
 * @method static int getInt()
 * @method static bool getBoolean()
 * @method static object getObject()
 * @method static array getArray()
 *
 * @see \Cognesy\Instructor\StructuredOutput
 */
class StructuredOutput extends Facade
{
    /**
     * Replace the StructuredOutput instance with a fake for testing.
     */
    public static function fake(array $responses = []): StructuredOutputFake
    {
        $fake = new StructuredOutputFake($responses);
        static::swap($fake);
        return $fake;
    }

    /**
     * Get the registered name of the component.
     */
    protected static function getFacadeAccessor(): string
    {
        return BaseStructuredOutput::class;
    }
}
