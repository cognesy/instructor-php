<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Embeddings\Creation;

use Cognesy\Polyglot\Embeddings\Drivers\Azure\AzureOpenAIDriver;
use Cognesy\Polyglot\Embeddings\Drivers\Cohere\CohereDriver;
use Cognesy\Polyglot\Embeddings\Drivers\Gemini\GeminiDriver;
use Cognesy\Polyglot\Embeddings\Drivers\Jina\JinaDriver;
use Cognesy\Polyglot\Embeddings\Drivers\OpenAI\OpenAIDriver;

final class BundledEmbeddingsDrivers
{
    public static function registry(): EmbeddingsDriverRegistry {
        return EmbeddingsDriverRegistry::fromArray([
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
