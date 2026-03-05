<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Tests\Support;

use Cognesy\Polyglot\Embeddings\Config\EmbeddingsConfig;
use Cognesy\Polyglot\Inference\Config\LLMConfig;
use InvalidArgumentException;

final class TestConfig
{
    public static function llm(string $name): LLMConfig {
        return LLMConfig::fromArray(match ($name) {
            'openai' => [
                'driver' => 'openai',
                'apiUrl' => 'https://api.openai.com/v1',
                'endpoint' => '/chat/completions',
                'apiKey' => 'test',
                'model' => 'gpt-4o-mini',
            ],
            'openai-responses' => [
                'driver' => 'openai-responses',
                'apiUrl' => 'https://api.openai.com/v1',
                'endpoint' => '/responses',
                'apiKey' => 'test',
                'model' => 'gpt-4o-mini',
            ],
            'anthropic' => [
                'driver' => 'anthropic',
                'apiUrl' => 'https://api.anthropic.com/v1',
                'endpoint' => '/messages',
                'apiKey' => 'test',
                'model' => 'claude-3-haiku-20240307',
            ],
            'gemini' => [
                'driver' => 'gemini',
                'apiUrl' => 'https://generativelanguage.googleapis.com/v1beta',
                'endpoint' => '/models/{model}:generateContent',
                'apiKey' => 'test',
                'model' => 'gemini-1.5-flash',
            ],
            'deepseek-r' => [
                'driver' => 'deepseek',
                'apiUrl' => 'https://api.deepseek.com',
                'endpoint' => '/chat/completions',
                'apiKey' => 'test',
                'model' => 'deepseek-reasoner',
            ],
            default => throw new InvalidArgumentException("Unknown test LLM config: {$name}"),
        });
    }

    public static function embeddings(string $name): EmbeddingsConfig {
        return EmbeddingsConfig::fromArray(match ($name) {
            'openai' => [
                'driver' => 'openai',
                'apiUrl' => 'https://api.openai.com/v1',
                'endpoint' => '/embeddings',
                'apiKey' => 'test',
                'model' => 'text-embedding-3-small',
                'dimensions' => 1536,
                'maxInputs' => 2048,
                'metadata' => [],
            ],
            default => throw new InvalidArgumentException("Unknown test embeddings config: {$name}"),
        });
    }
}

