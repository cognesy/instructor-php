<?php

use Cognesy\Config\Env;

return [
    'defaultPreset' => 'openai',

    'presets' => [
        'a21' => [
            'driver' => 'a21',
            'apiUrl' => 'https://api.ai21.com/studio/v1',
            'apiKey' => Env::get('A21_API_KEY', ''),
            'endpoint' => '/chat/completions',
            'defaultModel' => 'jamba-mini',
            'defaultMaxTokens' => 1024,
            'contextLength' => 256_000,
            'maxOutputLength' => 4096,
        ],
        'anthropic' => [
            'driver' => 'anthropic',
            'apiUrl' => 'https://api.anthropic.com/v1',
            'apiKey' => Env::get('ANTHROPIC_API_KEY', ''),
            'endpoint' => '/messages',
            'metadata' => [
                'apiVersion' => '2023-06-01',
                'beta' => 'prompt-caching-2024-07-31',
            ],
            'defaultModel' => 'claude-3-haiku-20240307',
            'defaultMaxTokens' => 1024,
            'contextLength' => 200_000,
            'maxOutputLength' => 8192,
        ],
        'azure' => [
            'driver' => 'azure',
            'apiUrl' => 'https://{resourceName}.openai.azure.com/openai/deployments/{deploymentId}',
            'apiKey' => Env::get('AZURE_OPENAI_API_KEY', ''),
            'endpoint' => '/chat/completions',
            'metadata' => [
                'apiVersion' => '2024-08-01-preview',
                'resourceName' => 'instructor-dev',
                'deploymentId' => 'gpt-4o-mini',
            ],
            'defaultModel' => 'gpt-4o-mini',
            'defaultMaxTokens' => 1024,
            'contextLength' => 128_000,
            'maxOutputLength' => 16384,
        ],
        'cerebras' => [
            'driver' => 'cerebras',
            'apiUrl' => 'https://api.cerebras.ai/v1',
            'apiKey' => Env::get('CEREBRAS_API_KEY', ''),
            'endpoint' => '/chat/completions',
            'defaultModel' => 'llama3.1-8b',
            'defaultMaxTokens' => 1024,
            'contextLength' => 128_000,
            'maxOutputLength' => 2048,
        ],
        'cohere' => [
            'driver' => 'cohere',
            'apiUrl' => 'https://api.cohere.ai/v2',
            'apiKey' => Env::get('COHERE_API_KEY', ''),
            'endpoint' => '/chat',
            'defaultModel' => 'command-r-plus-08-2024',
            'defaultMaxTokens' => 1024,
            'contextLength' => 128_000,
            'maxOutputLength' => 4096,
        ],
        'deepseek' => [
            'driver' => 'deepseek',
            'apiUrl' => 'https://api.deepseek.com',
            'apiKey' => Env::get('DEEPSEEK_API_KEY', ''),
            'endpoint' => '/chat/completions',
            'defaultModel' => 'deepseek-chat',
            'defaultMaxTokens' => 1024,
            'contextLength' => 128_000,
            'maxOutputLength' => 8192,
        ],
        'deepseek-r' => [
            'driver' => 'deepseek',
            'apiUrl' => 'https://api.deepseek.com',
            'apiKey' => Env::get('DEEPSEEK_API_KEY', ''),
            'endpoint' => '/chat/completions',
            'defaultModel' => 'deepseek-reasoner',
            'defaultMaxTokens' => 1024,
            'contextLength' => 128_000,
            'maxOutputLength' => 8192,
        ],
        'fireworks' => [
            'driver' => 'fireworks',
            'apiUrl' => 'https://api.fireworks.ai/inference/v1',
            'apiKey' => Env::get('FIREWORKS_API_KEY', ''),
            'endpoint' => '/chat/completions',
            'defaultModel' => 'accounts/fireworks/models/llama4-maverick-instruct-basic',
            'defaultMaxTokens' => 1024,
            'contextLength' => 65_000,
            'maxOutputLength' => 4096,
        ],
        'gemini' => [
            'driver' => 'gemini',
            'apiUrl' => 'https://generativelanguage.googleapis.com/v1beta',
            'apiKey' => Env::get('GEMINI_API_KEY', ''),
            'endpoint' => '/models/{model}:generateContent',
            'defaultModel' => 'gemini-1.5-flash',
            'defaultMaxTokens' => 1024,
            'contextLength' => 1_000_000,
            'maxOutputLength' => 8192,
        ],
        'gemini-oai' => [
            'driver' => 'gemini-oai',
            'apiUrl' => 'https://generativelanguage.googleapis.com/v1beta/openai',
            'apiKey' => Env::get('GEMINI_API_KEY', ''),
            'endpoint' => '/chat/completions',
            'defaultModel' => 'gemini-1.5-flash',
            'defaultMaxTokens' => 1024,
            'contextLength' => 1_000_000,
            'maxOutputLength' => 8192,
        ],
        'groq' => [
            'driver' => 'groq',
            'apiUrl' => 'https://api.groq.com/openai/v1',
            'apiKey' => Env::get('GROQ_API_KEY', ''),
            'endpoint' => '/chat/completions',
            'defaultModel' => 'meta-llama/llama-4-scout-17b-16e-instruct', //'llama-3.3-70b-versatile', // 'gemma2-9b-it',
            'defaultMaxTokens' => 1024,
            'contextLength' => 128_000,
            'maxOutputLength' => 2048,
        ],
        'huggingface' => [
            'driver' => 'huggingface',
            'apiUrl' => 'https://router.huggingface.co/{providerId}/v1',
            'apiKey' => Env::get('HUGGINGFACE_API_KEY', ''),
            'endpoint' => '/chat/completions',
            'metadata' => [
                'providerId' => 'hf-inference/models/microsoft/phi-4',
            ],
            'defaultModel' => 'microsoft/phi-4',
            'defaultMaxTokens' => 1024,
            'contextLength' => 32_000,
            'maxOutputLength' => 4096,
        ],
        'meta' => [
            'driver' => 'meta',
            'apiUrl' => 'https://openrouter.ai/api/v1',
            'apiKey' => Env::get('OPENROUTER_API_KEY', ''),
            'endpoint' => '/chat/completions',
            'defaultModel' => 'meta-llama/llama-3.3-8b-instruct:free',
            'defaultMaxTokens' => 1024,
            'contextLength' => 128_000,
            'maxOutputLength' => 8192,
        ],
        'minimaxi' => [
            'driver' => 'minimaxi',
            'apiUrl' => 'https://api.minimaxi.chat/v1',
            'apiKey' => Env::get('MINIMAXI_API_KEY', ''),
            'endpoint' => '/text/chatcompletion_v2',
            'defaultModel' => 'MiniMax-Text-01', // 'MiniMax-Text-01',
            'defaultMaxTokens' => 1024,
            'contextLength' => 1_000_000,
            'maxOutputLength' => 4096,
        ],
        'minimaxi-oai' => [
            'driver' => 'minimaxi',
            'apiUrl' => 'https://api.minimaxi.chat/v1',
            'apiKey' => Env::get('MINIMAXI_API_KEY', ''),
            'endpoint' => '/chat/completions',
            'defaultModel' => 'MiniMax-Text-01', // 'MiniMax-Text-01',
            'defaultMaxTokens' => 1024,
            'contextLength' => 1_000_000,
            'maxOutputLength' => 4096,
        ],
        'mistral' => [
            'driver' => 'mistral',
            'apiUrl' => 'https://api.mistral.ai/v1',
            'apiKey' => Env::get('MISTRAL_API_KEY', ''),
            'endpoint' => '/chat/completions',
            'defaultModel' => 'mistral-small-latest',
            'defaultMaxTokens' => 1024,
            'contextLength' => 128_000,
            'maxOutputLength' => 4096,
        ],
        'moonshot-kimi' => [
            'driver' => 'moonshot',
            'apiUrl' => 'https://api.moonshot.cn/v1',
            'apiKey' => Env::get('MOONSHOT_API_KEY', ''),
            'endpoint' => '/chat/completions',
            'defaultModel' => 'kimi-latest',
            'defaultMaxTokens' => 1024,
            'contextLength' => 128_000,
            'maxOutputLength' => 4096,
        ],
        'ollama' => [
            'driver' => 'ollama',
            'apiUrl' => 'http://localhost:11434/v1',
            'apiKey' => Env::get('OLLAMA_API_KEY', ''),
            'endpoint' => '/chat/completions',
            'defaultModel' => 'gemma3:1b', // 'qwen2.5-coder:3b', //'gemma2:2b',
            'defaultMaxTokens' => 1024,
            'httpClientPreset' => 'http-ollama',
            'contextLength' => 128_000,
            'maxOutputLength' => 8192,
        ],
        'openai' => [
            'driver' => 'openai',
            'apiUrl' => 'https://api.openai.com/v1',
            'apiKey' => Env::get('OPENAI_API_KEY', ''),
            'endpoint' => '/chat/completions',
            'metadata' => [
                'organization' => '',
                'project' => '',
            ],
            'defaultModel' => 'gpt-4o-mini',
            'defaultMaxTokens' => 1024,
            'contextLength' => 128_000,
            'maxOutputLength' => 16384,
        ],
        'openrouter' => [
            'driver' => 'openrouter',
            'apiUrl' => 'https://openrouter.ai/api/v1',
            'apiKey' => Env::get('OPENROUTER_API_KEY', ''),
            'endpoint' => '/chat/completions',
            'defaultModel' => 'google/gemini-2.0-flash-001', // 'qwen/qwen3-0.6b-04-28:free',
            'defaultMaxTokens' => 1024,
            'contextLength' => 128_000,
            'maxOutputLength' => 8192,
        ],
        'perplexity' => [
            'driver' => 'perplexity',
            'apiUrl' => 'https://api.perplexity.ai',
            'apiKey' => Env::get('PERPLEXITY_API_KEY', ''),
            'endpoint' => '/chat/completions',
            'defaultModel' => 'sonar',
            'defaultMaxTokens' => 1024,
            'contextLength' => 128_000,
            'maxOutputLength' => 2048,
        ],
        'sambanova' => [
            'driver' => 'sambanova',
            'apiUrl' => 'https://api.sambanova.ai/v1',
            'apiKey' => Env::get('SAMBANOVA_API_KEY', ''),
            'endpoint' => '/chat/completions',
            'defaultModel' => 'Meta-Llama-3.1-8B-Instruct',
            'defaultMaxTokens' => 1024,
            'contextLength' => 128_000,
            'maxOutputLength' => 2048,
        ],
        'together' => [
            'driver' => 'together',
            'apiUrl' => 'https://api.together.xyz/v1',
            'apiKey' => Env::get('TOGETHER_API_KEY', ''),
            'endpoint' => '/chat/completions',
            'defaultModel' => 'meta-llama/Llama-4-Maverick-17B-128E-Instruct-FP8',
            'defaultMaxTokens' => 1024,
            'contextLength' => 128_000,
            'maxOutputLength' => 4096,
        ],
        'xai' => [
            'driver' => 'xai',
            'apiUrl' => 'https://api.x.ai/v1',
            'apiKey' => Env::get('XAI_API_KEY', ''),
            'endpoint' => '/chat/completions',
            'defaultModel' => 'grok-3',
            'defaultMaxTokens' => 1024,
            'contextLength' => 128_000,
            'maxOutputLength' => 128_000,
        ],
    ],
];
