<?php

declare(strict_types=1);

namespace Cognesy\Instructor\Laravel\Facades;

use Cognesy\Instructor\Laravel\Testing\StructuredOutputFake;
use Cognesy\Instructor\StructuredOutput as BaseStructuredOutput;
use Cognesy\Polyglot\Inference\Config\LLMConfig;
use Illuminate\Support\Facades\Facade;

/**
 * Facade for StructuredOutput
 *
 * @method static \Cognesy\Instructor\StructuredOutput connection(string $name)
 * @method static \Cognesy\Instructor\StructuredOutput fromConfig(\Cognesy\Polyglot\Inference\Config\LLMConfig $config)
 * @method static \Cognesy\Instructor\StructuredOutput withRuntime(\Cognesy\Instructor\Contracts\CanCreateStructuredOutput $runtime)
 * @method static \Cognesy\Instructor\StructuredOutput with(string|array|\Cognesy\Messages\Message|\Cognesy\Messages\Messages|null $messages = null, string|array|object|null $responseModel = null, ?string $system = null, ?string $prompt = null, ?array $examples = null, ?string $model = null, ?array $options = null)
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
 * @method static \Cognesy\Instructor\PendingStructuredOutput create(?\Cognesy\Instructor\Data\StructuredOutputRequest $request = null)
 * @method static mixed get()
 * @method static \Cognesy\Instructor\StructuredOutputStream stream()
 * @method static \Cognesy\Instructor\Data\StructuredOutputResponse response()
 * @method static \Cognesy\Polyglot\Inference\Data\InferenceResponse rawResponse()
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
     * Create facade instance from explicit typed LLM config.
     */
    public static function fromConfig(LLMConfig $config): BaseStructuredOutput|StructuredOutputFake
    {
        $root = static::getFacadeRoot();
        if ($root instanceof StructuredOutputFake) {
            return $root->withLLMConfig($config);
        }
        if ($root instanceof BaseStructuredOutput) {
            return $root->withRuntime(\Cognesy\Instructor\StructuredOutputRuntime::fromConfig(
                $config,
            ));
        }
        throw new \RuntimeException('StructuredOutput facade root is not initialized.');
    }

    /**
     * Create facade instance from configured Laravel connection name.
     */
    public static function connection(string $name): BaseStructuredOutput|StructuredOutputFake
    {
        return static::fromConfig(static::resolveLLMConfig($name));
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

    private static function resolveLLMConfig(string $name): LLMConfig
    {
        $raw = config("instructor.connections.{$name}", []);
        $connection = is_array($raw) ? $raw : [];
        $driver = (string) ($connection['driver'] ?? $name ?: 'openai');
        $model = (string) ($connection['model'] ?? '');
        $endpoint = (string) ($connection['endpoint'] ?? static::defaultLlmEndpoint($driver, $model));

        $known = ['driver', 'api_url', 'api_key', 'endpoint', 'model', 'max_tokens', 'options'];
        $extraOptions = array_diff_key($connection, array_flip($known));
        $options = match (true) {
            isset($connection['options']) && is_array($connection['options']) => array_merge($extraOptions, $connection['options']),
            default => $extraOptions,
        };

        return LLMConfig::fromArray([
            'driver' => $driver,
            'apiUrl' => (string) ($connection['api_url'] ?? ''),
            'apiKey' => (string) ($connection['api_key'] ?? ''),
            'endpoint' => $endpoint,
            'model' => $model,
            'maxTokens' => (int) ($connection['max_tokens'] ?? 4096),
            'options' => $options,
        ]);
    }

    private static function defaultLlmEndpoint(string $driver, string $model): string
    {
        return match ($driver) {
            'anthropic' => '/messages',
            'gemini' => "/models/{$model}:generateContent",
            default => '/chat/completions',
        };
    }
}
