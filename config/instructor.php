<?php
use Cognesy\Instructor\ApiClient\Enums\ClientType;
use Cognesy\Instructor\Utils\Env;

return [
    'defaultConnection' => 'openai',
    'cache' => [
        'enabled' => Env::get('INSTRUCTOR_CACHE_ENABLED', false),
        'expiry' => Env::get('INSTRUCTOR_CACHE_EXPIRY', 3600),
        'path' => Env::get('INSTRUCTOR_CACHE_PATH', '/tmp/instructor/cache'),
    ],
    'debug' => [
        'enabled' => Env::get('INSTRUCTOR_DEBUG', false),
        'stopOnDebug' => Env::get('INSTRUCTOR_STOP_ON_DEBUG', false),
        'forceDebug' => Env::get('INSTRUCTOR_FORCE_DEBUG', false),
    ],
    'useObjectReferences' => Env::get('INSTRUCTOR_USE_OBJECT_REFERENCES', false),

    'connections' => [
        'anthropic' => [
            'clientType' => ClientType::Anthropic->value,
            'apiUrl' => Env::get('ANTHROPIC_API_URL', 'https://api.anthropic.com/v1'),
            'apiKey' => Env::get('ANTHROPIC_API_KEY', ''),
            'metadata' => [
                'apiVersion' => Env::get('ANTHROPIC_API_VERSION', '2023-06-01'),
                'beta' => Env::get('ANTHROPIC_BETA', 'prompt-caching-2024-07-31'),
            ],
            'defaultModel' => Env::get('ANTHROPIC_DEFAULT_MODEL', 'claude-3-haiku-20240307'),
            'defaultMaxTokens' => Env::get('ANTHROPIC_DEFAULT_MAX_TOKENS', 1024),
            'connectTimeout' => Env::get('ANTHROPIC_CONNECT_TIMEOUT', 3),
            'requestTimeout' => Env::get('ANTHROPIC_REQUEST_TIMEOUT', 30),
        ],
        'azure' => [
            'clientType' => ClientType::Azure->value,
            'apiUrl' => Env::get('AZURE_OPENAI_BASE_URI', 'https://api.openai.com/v1'),
            'apiKey' => Env::get('AZURE_OPENAI_API_KEY', ''),
            'metadata' => [
                'apiVersion' => Env::get('AZURE_OPENAI_API_VERSION', '2023-03-15-preview'),
                'resourceName' => Env::get('AZURE_OPENAI_RESOURCE_NAME', 'instructor-dev'),
                'deploymentName' => Env::get('AZURE_OPENAI_DEPLOYMENT_NAME', 'gpt-4o-mini'),
            ],
            'defaultModel' => Env::get('AZURE_OPENAI_DEFAULT_MODEL', 'gpt-4o-mini'),
            'defaultMaxTokens' => Env::get('AZURE_OPENAI_DEFAULT_MAX_TOKENS', 1024),
            'connectTimeout' => Env::get('AZURE_OPENAI_CONNECT_TIMEOUT', 3),
            'requestTimeout' => Env::get('AZURE_OPENAI_REQUEST_TIMEOUT', 30),
        ],
        'cohere' => [
            'clientType' => ClientType::Cohere->value,
            'apiUrl' => Env::get('COHERE_API_URL', 'https://api.cohere.ai/v1'),
            'apiKey' => Env::get('COHERE_API_KEY', ''),
            'defaultModel' => Env::get('COHERE_DEFAULT_MODEL', 'cohere-r-plus'),
            'defaultMaxTokens' => Env::get('COHERE_DEFAULT_MAX_TOKENS', 1024),
            'connectTimeout' => Env::get('COHERE_CONNECT_TIMEOUT', 3),
            'requestTimeout' => Env::get('COHERE_REQUEST_TIMEOUT', 30),
        ],
        'fireworks' => [
            'clientType' => ClientType::Fireworks->value,
            'apiUrl' => Env::get('FIREWORKS_API_URL', 'https://api.fireworks.ai/inference/v1'),
            'apiKey' => Env::get('FIREWORKS_API_KEY', ''),
            'defaultModel' => Env::get('FIREWORKS_DEFAULT_MODEL', 'accounts/fireworks/models/mixtral-8x7b-instruct'),
            'defaultMaxTokens' => Env::get('FIREWORKS_DEFAULT_MAX_TOKENS', 1024),
            'connectTimeout' => Env::get('FIREWORKS_CONNECT_TIMEOUT', 3),
            'requestTimeout' => Env::get('FIREWORKS_REQUEST_TIMEOUT', 30),
        ],
        'gemini' => [
            'clientType' => ClientType::Gemini->value,
            'apiUrl' => Env::get('GEMINI_API_URL', 'https://generativelanguage.googleapis.com/v1beta'),
            'apiKey' => Env::get('GEMINI_API_KEY', ''),
            'defaultModel' => Env::get('GEMINI_DEFAULT_MODEL', 'gemini-1.5-flash'),
            'defaultMaxTokens' => Env::get('GEMINI_DEFAULT_MAX_TOKENS', 1024),
            'connectTimeout' => Env::get('GEMINI_CONNECT_TIMEOUT', 3),
            'requestTimeout' => Env::get('GEMINI_REQUEST_TIMEOUT', 30),
        ],
        'groq' => [
            'clientType' => ClientType::Groq->value,
            'apiUrl' => Env::get('GROQ_API_URL', 'https://api.groq.com/openai/v1'),
            'apiKey' => Env::get('GROQ_API_KEY', ''),
            'defaultModel' => Env::get('GROQ_DEFAULT_MODEL', 'llama3-8b-8192'),
            'defaultMaxTokens' => Env::get('GROQ_DEFAULT_MAX_TOKENS', 1024),
            'connectTimeout' => Env::get('GROQ_CONNECT_TIMEOUT', 3),
            'requestTimeout' => Env::get('GROQ_REQUEST_TIMEOUT', 30),
        ],
        'mistral' => [
            'clientType' => ClientType::Mistral->value,
            'apiUrl' => Env::get('MISTRAL_API_URL', 'https://api.mistral.ai/v1'),
            'apiKey' => Env::get('MISTRAL_API_KEY', ''),
            'defaultModel' => Env::get('MISTRAL_DEFAULT_MODEL', 'mistral-small-latest'),
            'defaultMaxTokens' => Env::get('MISTRAL_DEFAULT_MAX_TOKENS', 1024),
            'connectTimeout' => Env::get('MISTRAL_CONNECT_TIMEOUT', 3),
            'requestTimeout' => Env::get('MISTRAL_REQUEST_TIMEOUT', 30),
        ],
        'openai' => [
            'clientType' => ClientType::OpenAI->value,
            'apiUrl' => Env::get('OPENAI_API_URL', 'https://api.openai.com/v1'),
            'apiKey' => Env::get('OPENAI_API_KEY', ''),
            'metadata' => [
                'organization' => ''
            ],
            'defaultModel' => Env::get('OPENAI_DEFAULT_MODEL', 'gpt-4o-mini'),
            'defaultMaxTokens' => Env::get('OPENAI_DEFAULT_MAX_TOKENS', 1024),
            'connectTimeout' => Env::get('OPENAI_CONNECT_TIMEOUT', 3),
            'requestTimeout' => Env::get('OPENAI_REQUEST_TIMEOUT', 30),
        ],
        'openrouter' => [
            'clientType' => ClientType::OpenRouter->value,
            'apiUrl' => Env::get('OPENROUTER_API_URL', 'https://api.openrouter.io/v1'),
            'apiKey' => Env::get('OPENROUTER_API_KEY', ''),
            'defaultModel' => Env::get('OPENROUTER_DEFAULT_MODEL', 'gpt-4o-mini'),
            'defaultMaxTokens' => Env::get('OPENROUTER_DEFAULT_MAX_TOKENS', 1024),
            'connectTimeout' => Env::get('OPENROUTER_CONNECT_TIMEOUT', 3),
            'requestTimeout' => Env::get('OPENROUTER_REQUEST_TIMEOUT', 30),
        ],
        'together' => [
            'clientType' => ClientType::Together->value,
            'apiUrl' => Env::get('TOGETHER_API_URL', 'https://api.together.xyz/v1'),
            'apiKey' => Env::get('TOGETHER_API_KEY', ''),
            'defaultModel' => Env::get('TOGETHER_DEFAULT_MODEL', 'mistralai/Mixtral-8x7B-Instruct-v0.1'),
            'defaultMaxTokens' => Env::get('TOGETHER_DEFAULT_MAX_TOKENS', 1024),
            'connectTimeout' => Env::get('TOGETHER_CONNECT_TIMEOUT', 3),
            'requestTimeout' => Env::get('TOGETHER_REQUEST_TIMEOUT', 30),
        ],
    ],
];
