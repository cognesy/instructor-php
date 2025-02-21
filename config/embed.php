<?php

use Cognesy\Polyglot\LLM\Enums\LLMProviderType;
use Cognesy\Utils\Env;

return [
    'debug' => [
        'enabled' => false,
    ],

    'defaultConnection' => 'openai',
    'connections' => [
        'azure' => [
            'providerType' => LLMProviderType::Azure->value,
            'apiUrl' => 'https://{resourceName}.openai.azure.com/openai/deployments/{deploymentId}',
            'apiKey' => Env::get('AZURE_OPENAI_EMBED_API_KEY', ''),
            'endpoint' => '/embeddings',
            'metadata' => [
                'apiVersion' => '2023-05-15',
                'resourceName' => 'instructor-dev',
                'deploymentId' => 'text-embedding-3-small',
            ],
            'defaultModel' => 'text-embedding-3-small',
            'defaultDimensions' => 1536,
            'maxInputs' => 16,
        ],
        'cohere1' => [
            'providerType' => LLMProviderType::CohereV1->value,
            'apiUrl' => 'https://api.cohere.ai/v1',
            'apiKey' => Env::get('COHERE_API_KEY', ''),
            'endpoint' => '/embed',
            'defaultModel' => 'embed-multilingual-v3.0',
            'defaultDimensions' => 1024,
            'maxInputs' => 96,
        ],
        'gemini' => [
            'providerType' => LLMProviderType::Gemini->value,
            'apiUrl' => 'https://generativelanguage.googleapis.com/v1beta',
            'apiKey' => Env::get('GEMINI_API_KEY', ''),
            'endpoint' => '/{model}:batchEmbedContents',
            'defaultModel' => 'models/text-embedding-004',
            'defaultDimensions' => 768,
            'maxInputs' => 100, // max 2048 tokens
        ],
        'jina' => [
            'providerType' => LLMProviderType::Jina->value,
            'apiUrl' => 'https://api.jina.ai/v1',
            'apiKey' => Env::get('JINA_API_KEY', ''),
            'endpoint' => '/embeddings',
            'metadata' => [
                'organization' => ''
            ],
            'defaultModel' => 'jina-embeddings-v2-base-en',
            'defaultDimensions' => 768,
            'maxInputs' => 500, // max 8192 tokens
        ],
        'mistral' => [
            'providerType' => LLMProviderType::Mistral->value,
            'apiUrl' => 'https://api.mistral.ai/v1',
            'apiKey' => Env::get('MISTRAL_API_KEY', ''),
            'endpoint' => '/embeddings',
            'defaultModel' => 'mistral-embed',
            'defaultDimensions' => 1024,
            'maxInputs' => 512, // max 512 tokens
        ],
        'ollama' => [
            'providerType' => LLMProviderType::Ollama->value,
            'apiUrl' => 'http://localhost:11434/v1',
            'apiKey' => Env::get('OLLAMA_API_KEY', ''),
            'endpoint' => '/embeddings',
            'defaultModel' => 'nomic-embed-text',
            'defaultDimensions' => 1024,
            'maxInputs' => 512,
            'httpClient' => 'http-ollama',
        ],
        'openai' => [
            'providerType' => LLMProviderType::OpenAI->value,
            'apiUrl' => 'https://api.openai.com/v1',
            'apiKey' => Env::get('OPENAI_API_KEY', ''),
            'endpoint' => '/embeddings',
            'metadata' => [
                'organization' => ''
            ],
            'defaultModel' => 'text-embedding-3-small',
            'defaultDimensions' => 1536,
            'maxInputs' => 2048, // max 8192 tokens
        ],
    ],
];
