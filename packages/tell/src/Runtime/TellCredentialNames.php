<?php

declare(strict_types=1);

namespace Cognesy\Tell\Runtime;

use InvalidArgumentException;

final readonly class TellCredentialNames
{
    private const array PROVIDERS = [
        'a21' => 'A21_API_KEY',
        'anthropic' => 'ANTHROPIC_API_KEY',
        'aws-bedrock' => 'AWS_BEDROCK_API_KEY',
        'azure' => 'AZURE_OPENAI_API_KEY',
        'cerebras' => 'CEREBRAS_API_KEY',
        'cohere' => 'COHERE_API_KEY',
        'deepseek' => 'DEEPSEEK_API_KEY',
        'deepseek-r' => 'DEEPSEEK_API_KEY',
        'fireworks' => 'FIREWORKS_API_KEY',
        'gemini' => 'GEMINI_API_KEY',
        'gemini-oai' => 'GEMINI_API_KEY',
        'glm' => 'GLM_API_KEY',
        'groq' => 'GROQ_API_KEY',
        'huggingface' => 'HUGGINGFACE_API_KEY',
        'inception' => 'INCEPTION_API_KEY',
        'meta' => 'OPENROUTER_API_KEY',
        'minimaxi' => 'MINIMAXI_API_KEY',
        'minimaxi-oai' => 'MINIMAXI_API_KEY',
        'mistral' => 'MISTRAL_API_KEY',
        'moonshot-kimi' => 'MOONSHOT_API_KEY',
        'ollama' => 'OLLAMA_API_KEY',
        'openai' => 'OPENAI_API_KEY',
        'openai-responses' => 'OPENAI_API_KEY',
        'openrouter' => 'OPENROUTER_API_KEY',
        'perplexity' => 'PERPLEXITY_API_KEY',
        'qwen' => 'DASHSCOPE_API_KEY',
        'sambanova' => 'SAMBANOVA_API_KEY',
        'test' => 'OPENAI_API_KEY',
        'together' => 'TOGETHER_API_KEY',
        'xai' => 'XAI_API_KEY',
    ];

    public static function forProvider(string $provider): string
    {
        self::assertProvider($provider);

        return self::PROVIDERS[$provider]
            ?? strtoupper((string) preg_replace('/[^A-Za-z0-9]+/', '_', $provider)).'_API_KEY';
    }

    /** @return list<string> */
    public static function known(): array
    {
        $variables = array_values(array_unique(self::PROVIDERS));
        sort($variables);

        return $variables;
    }

    public static function assertVariable(string $variable): void
    {
        if (preg_match('/^[A-Z_][A-Z0-9_]*$/D', $variable) !== 1) {
            throw new InvalidArgumentException("Invalid credential variable: {$variable}");
        }
    }

    private static function assertProvider(string $provider): void
    {
        if (preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]*$/D', $provider) !== 1) {
            throw new InvalidArgumentException("Invalid provider name: {$provider}");
        }
    }
}
