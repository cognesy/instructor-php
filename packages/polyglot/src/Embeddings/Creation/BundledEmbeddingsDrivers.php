<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Embeddings\Creation;

use Cognesy\Polyglot\Embeddings\Drivers\Azure\AzureOpenAIDriver;
use Cognesy\Polyglot\Embeddings\Drivers\Cohere\CohereDriver;
use Cognesy\Polyglot\Embeddings\Drivers\Gemini\GeminiDriver;
use Cognesy\Polyglot\Embeddings\Drivers\Jina\JinaDriver;
use Cognesy\Polyglot\Embeddings\Drivers\OpenAI\OpenAIDriver;

final class BundledEmbeddingsDrivers
{
    /**
     * The bundled table is a compile-time constant, so it is built once per process
     * (instructor-eexl.21, mirroring instructor-eexl.7).
     *
     * Sharing the instance is safe precisely because `EmbeddingsDriverRegistry` is immutable:
     * `withDriver()` and `withoutDriver()` return copies, so a caller that customises the
     * bundled registry gets its own object and cannot reach this one. There is no global
     * registration API that would let anyone mutate it either.
     *
     * Deliberately no reset hook -- nothing can mutate a registry in place, so a reset would
     * be a public method with no reachable purpose.
     */
    private static ?EmbeddingsDriverRegistry $instance = null;

    public static function registry(): EmbeddingsDriverRegistry {
        return self::$instance ??= EmbeddingsDriverRegistry::fromArray([
            'azure' => AzureOpenAIDriver::class,
            'cohere' => CohereDriver::class,
            'gemini' => GeminiDriver::class,
            'jina' => JinaDriver::class,
            'mistral' => OpenAIDriver::class,
            'openai' => OpenAIDriver::class,
            'ollama' => OpenAIDriver::class,
        ]);
    }
}
