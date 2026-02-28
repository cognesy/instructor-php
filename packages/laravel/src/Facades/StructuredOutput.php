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
 * @method static \Cognesy\Instructor\StructuredOutput withRuntime(\Cognesy\Instructor\Contracts\CanCreateStructuredOutput $runtime)
 * @method static \Cognesy\Instructor\StructuredOutput withConfig(\Cognesy\Instructor\Config\StructuredOutputConfig $config)
 * @method static \Cognesy\Instructor\StructuredOutput withDefaultToStdClass(bool $defaultToStdClass = true)
 * @method static \Cognesy\Instructor\StructuredOutput withOutputMode(\Cognesy\Polyglot\Inference\Enums\OutputMode $mode)
 * @method static \Cognesy\Instructor\StructuredOutput withMaxRetries(int $maxRetries)
 * @method static \Cognesy\Instructor\StructuredOutput with(string|array|\Cognesy\Messages\Message|\Cognesy\Messages\Messages|null $messages = null, string|array|object|null $responseModel = null, ?string $system = null, ?string $prompt = null, ?array $examples = null, ?string $model = null, ?int $maxRetries = null, ?array $options = null, ?\Cognesy\Polyglot\Inference\Enums\OutputMode $mode = null)
 * @method static \Cognesy\Instructor\StructuredOutput withMessages(string|array|\Cognesy\Messages\Message|\Cognesy\Messages\Messages $messages)
 * @method static \Cognesy\Instructor\StructuredOutput withInput(mixed $input)
 * @method static \Cognesy\Instructor\StructuredOutput withResponseModel(string|array|object $responseModel)
 * @method static \Cognesy\Instructor\StructuredOutput withResponseClass(string $class)
 * @method static \Cognesy\Instructor\StructuredOutput withResponseObject(object $object)
 * @method static \Cognesy\Instructor\StructuredOutput withResponseJsonSchema(array|\Cognesy\Utils\JsonSchema\Contracts\CanProvideJsonSchema $jsonSchema)
 * @method static \Cognesy\Instructor\StructuredOutput withSystem(string $system)
 * @method static \Cognesy\Instructor\StructuredOutput withPrompt(string $prompt)
 * @method static \Cognesy\Instructor\StructuredOutput withExamples(array $examples)
 * @method static \Cognesy\Instructor\StructuredOutput withModel(string $model)
 * @method static \Cognesy\Instructor\StructuredOutput withOptions(array $options)
 * @method static \Cognesy\Instructor\StructuredOutput withOption(string $key, mixed $value)
 * @method static \Cognesy\Instructor\StructuredOutput withStreaming(bool $streaming = true)
 * @method static \Cognesy\Instructor\StructuredOutput withCachedContext(string|array $messages = '', string $system = '', string $prompt = '', array $examples = [])
 * @method static \Cognesy\Instructor\StructuredOutput intoArray()
 * @method static \Cognesy\Instructor\StructuredOutput intoInstanceOf(string $class)
 * @method static \Cognesy\Instructor\StructuredOutput intoObject(\Cognesy\Instructor\Deserialization\Contracts\CanDeserializeSelf $object)
 * @method static \Cognesy\Instructor\StructuredOutput withValidators(\Cognesy\Instructor\Validation\Contracts\CanValidateObject|string ...$validators)
 * @method static \Cognesy\Instructor\StructuredOutput withTransformers(\Cognesy\Instructor\Transformation\Contracts\CanTransformData|string ...$transformers)
 * @method static \Cognesy\Instructor\StructuredOutput withDeserializers(\Cognesy\Instructor\Deserialization\Contracts\CanDeserializeClass|string ...$deserializers)
 * @method static \Cognesy\Instructor\StructuredOutput withExtractors(\Cognesy\Instructor\Extraction\Contracts\CanExtractResponse|string ...$extractors)
 * @method static \Cognesy\Instructor\StructuredOutput wiretap(?callable $listener)
 * @method static \Cognesy\Instructor\StructuredOutput onEvent(string $class, ?callable $listener)
 * @method static \Cognesy\Instructor\PendingStructuredOutput create(?\Cognesy\Instructor\Data\StructuredOutputRequest $request = null)
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
     * Shortcut for swapping runtime to the given LLM preset.
     */
    public static function using(string $preset): BaseStructuredOutput|StructuredOutputFake
    {
        $root = static::getFacadeRoot();
        if ($root instanceof StructuredOutputFake) {
            return $root->using($preset);
        }
        if ($root instanceof BaseStructuredOutput) {
            return $root->withRuntime(\Cognesy\Instructor\StructuredOutputRuntime::using($preset));
        }
        throw new \RuntimeException('StructuredOutput facade root is not initialized.');
    }

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
