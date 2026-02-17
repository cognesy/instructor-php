<?php

declare(strict_types=1);

namespace Cognesy\Instructor\Laravel\Facades;

use Cognesy\Instructor\Laravel\Testing\InferenceFake;
use Cognesy\Polyglot\Inference\Inference as BaseInference;
use Illuminate\Support\Facades\Facade;

/**
 * Facade for Inference
 *
 * @method static \Cognesy\Polyglot\Inference\Inference using(string $preset)
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
 * @method static \Cognesy\Polyglot\Inference\Inference withLLMProvider(\Cognesy\Polyglot\Inference\LLMProvider $llm)
 * @method static \Cognesy\Polyglot\Inference\Inference withLLMResolver(\Cognesy\Polyglot\Inference\Contracts\CanResolveLLMConfig $resolver)
 * @method static \Cognesy\Polyglot\Inference\Inference withDriver(\Cognesy\Polyglot\Inference\Contracts\CanProcessInferenceRequest $driver)
 * @method static \Cognesy\Polyglot\Inference\Inference withHttpClient(\Cognesy\Http\HttpClient $httpClient)
 * @method static \Cognesy\Polyglot\Inference\Inference withLLMConfig(\Cognesy\Polyglot\Inference\Config\LLMConfig $config)
 * @method static \Cognesy\Polyglot\Inference\Inference withLLMConfigOverrides(array $overrides)
 * @method static \Cognesy\Polyglot\Inference\Inference withDsn(string $dsn)
 * @method static \Cognesy\Polyglot\Inference\Inference withHttpDebugPreset(?string $preset)
 * @method static \Cognesy\Polyglot\Inference\Inference withHttpDebug(bool $enabled = true)
 * @method static \Cognesy\Polyglot\Inference\Inference wiretap(callable $callback)
 * @method static \Cognesy\Polyglot\Inference\Inference onEvent(string $eventClass, callable $callback)
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
}
