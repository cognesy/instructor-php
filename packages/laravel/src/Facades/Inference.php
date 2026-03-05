<?php

declare(strict_types=1);

namespace Cognesy\Instructor\Laravel\Facades;

use Cognesy\Instructor\Laravel\Testing\InferenceFake;
use Cognesy\Polyglot\Inference\Config\LLMConfig;
use Cognesy\Polyglot\Inference\Inference as BaseInference;
use Cognesy\Polyglot\Inference\InferenceRuntime;
use Cognesy\Polyglot\Inference\LLMProvider;
use Illuminate\Support\Facades\Facade;

/**
 * Facade for Inference
 *
 * @method static \Cognesy\Polyglot\Inference\Inference connection(string $name)
 * @method static \Cognesy\Polyglot\Inference\Inference fromLLMConfig(\Cognesy\Polyglot\Inference\Config\LLMConfig $config)
 * @method static \Cognesy\Polyglot\Inference\Inference withRuntime(\Cognesy\Polyglot\Inference\Contracts\CanCreateInference $runtime)
 * @method static \Cognesy\Polyglot\Inference\Contracts\CanCreateInference runtime()
 * @method static \Cognesy\Polyglot\Inference\Inference with(string|array $messages = [], string $model = '', array $tools = [], string|array $toolChoice = [], array $responseFormat = [], array $options = [], ?\Cognesy\Polyglot\Inference\Enums\OutputMode $mode = null)
 * @method static \Cognesy\Polyglot\Inference\Inference withMessages(string|array|\Cognesy\Messages\Message|\Cognesy\Messages\Messages $messages)
 * @method static \Cognesy\Polyglot\Inference\Inference withModel(string $model)
 * @method static \Cognesy\Polyglot\Inference\Inference withTools(array $tools)
 * @method static \Cognesy\Polyglot\Inference\Inference withToolChoice(string|array $toolChoice)
 * @method static \Cognesy\Polyglot\Inference\Inference withResponseFormat(array $responseFormat)
 * @method static \Cognesy\Polyglot\Inference\Inference withMaxTokens(int $maxTokens)
 * @method static \Cognesy\Polyglot\Inference\Inference withOptions(array $options)
 * @method static \Cognesy\Polyglot\Inference\Inference withOutputMode(\Cognesy\Polyglot\Inference\Enums\OutputMode $mode)
 * @method static \Cognesy\Polyglot\Inference\Inference withStreaming(bool $streaming = true)
 * @method static \Cognesy\Polyglot\Inference\Inference withRetryPolicy(\Cognesy\Polyglot\Inference\Config\InferenceRetryPolicy $retryPolicy)
 * @method static \Cognesy\Polyglot\Inference\Inference withResponseCachePolicy(\Cognesy\Polyglot\Inference\Enums\ResponseCachePolicy $policy)
 * @method static \Cognesy\Polyglot\Inference\Inference withCachedContext(string|array $messages = [], array $tools = [], string|array $toolChoice = [], array $responseFormat = [])
 * @method static \Cognesy\Polyglot\Inference\Inference withRequest(\Cognesy\Polyglot\Inference\Data\InferenceRequest $request)
 * @method static \Cognesy\Polyglot\Inference\PendingInference create()
 * @method static string get()
 * @method static string asJson()
 * @method static array asJsonData()
 * @method static \Cognesy\Polyglot\Inference\Data\InferenceResponse response()
 * @method static \Cognesy\Polyglot\Inference\Streaming\InferenceStream stream()
 * @method static void registerDriver(string $name, string|callable $driver)
 *
 * @see \Cognesy\Polyglot\Inference\Inference
 */
class Inference extends Facade
{
    /**
     * Create facade instance from explicit typed LLM config.
     */
    public static function fromLLMConfig(LLMConfig $config): BaseInference|InferenceFake
    {
        $root = static::getFacadeRoot();
        if ($root instanceof InferenceFake) {
            return $root->withLLMConfig($config);
        }
        if ($root instanceof BaseInference) {
            return new BaseInference(InferenceRuntime::fromProvider(
                provider: LLMProvider::fromLLMConfig($config),
            ));
        }
        throw new \RuntimeException('Inference facade root is not initialized.');
    }

    /**
     * Create facade instance from configured Laravel connection name.
     */
    public static function connection(string $name): BaseInference|InferenceFake
    {
        return static::fromLLMConfig(static::resolveLLMConfig($name));
    }

    /**
     * Replace the Inference instance with a fake for testing.
     */
    public static function fake(array $responses = []): InferenceFake
    {
        $fake = new InferenceFake($responses);
        static::swap($fake);
        return $fake;
    }

    /**
     * Get the registered name of the component.
     */
    protected static function getFacadeAccessor(): string
    {
        return BaseInference::class;
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
