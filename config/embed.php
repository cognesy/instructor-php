<?php
use Cognesy\Instructor\ApiClient\Enums\ClientType;
use Cognesy\Instructor\Utils\Env;

return [
    'defaultConnection' => 'openai',
    'connections' => [
        'azure' => [
            'clientType' => ClientType::Azure->value,
            'apiUrl' => Env::get('AZURE_OPENAI_BASE_URI', 'openai.azure.com'),
            'apiKey' => Env::get('AZURE_OPENAI_API_KEY', ''),
            'endpoint' => Env::get('AZURE_OPENAI_EMBED_ENDPOINT', '/embeddings'),
            'metadata' => [
                'apiVersion' => Env::get('AZURE_OPENAI_API_VERSION', '2023-03-15-preview'),
                'resourceName' => Env::get('AZURE_OPENAI_RESOURCE_NAME', 'instructor-dev'),
                'deploymentId' => Env::get('AZURE_OPENAI_DEPLOYMENT_NAME', 'gpt-4o-mini'),
            ],
            'defaultModel' => Env::get('AZURE_OPENAI_EMBED_MODEL', 'text-embedding-3-small'),
            'defaultDimensions' => Env::get('AZURE_OPENAI_EMBED_DIMENSIONS', 1536),
            'maxInputs' => Env::get('AZURE_OPENAI_MAX_INPUTS', 16),
            'connectTimeout' => Env::get('AZURE_OPENAI_CONNECT_TIMEOUT', 3),
            'requestTimeout' => Env::get('AZURE_OPENAI_REQUEST_TIMEOUT', 30),
        ],
        'cohere' => [
            'clientType' => ClientType::Cohere->value,
            'apiUrl' => Env::get('COHERE_API_URL', 'https://api.cohere.ai/v1'),
            'apiKey' => Env::get('COHERE_API_KEY', ''),
            'endpoint' => Env::get('COHERE_EMBED_ENDPOINT', '/embed'),
            'defaultModel' => Env::get('COHERE_EMBED_MODEL', 'embed-multilingual-v3.0'),
            'defaultDimensions' => Env::get('COHERE_EMBED_DIMENSIONS', 1024),
            'maxInputs' => Env::get('COHERE_MAX_INPUTS', 96),
            'connectTimeout' => Env::get('COHERE_CONNECT_TIMEOUT', 3),
            'requestTimeout' => Env::get('COHERE_REQUEST_TIMEOUT', 30),
        ],
        'gemini' => [
            'clientType' => ClientType::Gemini->value,
            'apiUrl' => Env::get('GEMINI_API_URL', 'https://generativelanguage.googleapis.com/v1beta'),
            'apiKey' => Env::get('GEMINI_API_KEY', ''),
            'endpoint' => Env::get('GEMINI_EMBED_ENDPOINT', '/{model}:batchEmbedContents'),
            'defaultModel' => Env::get('GEMINI_EMBED_MODEL', 'models/text-embedding-004'),
            'defaultDimensions' => Env::get('GEMINI_EMBED_DIMENSIONS', 768),
            'maxInputs' => Env::get('GEMINI_MAX_INPUTS', 100), // max 2048 tokens
            'connectTimeout' => Env::get('GEMINI_CONNECT_TIMEOUT', 3),
            'requestTimeout' => Env::get('GEMINI_REQUEST_TIMEOUT', 30),
        ],
        'jina' => [
            'clientType' => ClientType::Jina->value,
            'apiUrl' => Env::get('JINA_API_URL', 'https://api.jina.ai/v1'),
            'apiKey' => Env::get('JINA_API_KEY', ''),
            'endpoint' => Env::get('JINA_EMBED_ENDPOINT', '/embeddings'),
            'metadata' => [
                'organization' => ''
            ],
            'defaultModel' => Env::get('JINA_EMBED_MODEL', 'jina-embeddings-v2-base-en'),
            'defaultDimensions' => Env::get('JINA_EMBED_DIMENSIONS', 768),
            'maxInputs' => Env::get('JINA_MAX_INPUTS', 500), // max 8192 tokens
            'connectTimeout' => Env::get('JINA_CONNECT_TIMEOUT', 3),
            'requestTimeout' => Env::get('JINA_REQUEST_TIMEOUT', 30),
        ],
        'mistral' => [
            'clientType' => ClientType::Mistral->value,
            'apiUrl' => Env::get('MISTRAL_API_URL', 'https://api.mistral.ai/v1'),
            'apiKey' => Env::get('MISTRAL_API_KEY', ''),
            'endpoint' => Env::get('MISTRAL_EMBED_ENDPOINT', '/embeddings'),
            'defaultModel' => Env::get('MISTRAL_EMBED_MODEL', 'mistral-embed'),
            'defaultDimensions' => Env::get('MISTRAL_EMBED_DIMENSIONS', 1024),
            'maxInputs' => Env::get('MISTRAL_MAX_INPUTS', 512), // max 512 tokens
            'connectTimeout' => Env::get('MISTRAL_CONNECT_TIMEOUT', 3),
            'requestTimeout' => Env::get('MISTRAL_REQUEST_TIMEOUT', 30),
        ],
        'ollama' => [
            'clientType' => ClientType::Ollama->value,
            'apiUrl' => Env::get('OLLAMA_API_URL', 'http://localhost:11434/v1'),
            'apiKey' => Env::get('OLLAMA_API_KEY', ''),
            'endpoint' => Env::get('OLLAMA_EMBED_ENDPOINT', '/embeddings'),
            'defaultModel' => Env::get('OLLAMA_EMBED_MODEL', 'nomic-embed-text'),
            'defaultDimensions' => Env::get('OLLAMA_EMBED_DIMENSIONS', 1024),
            'maxInputs' => Env::get('OLLAMA_MAX_INPUTS', 512),
            'connectTimeout' => Env::get('OLLAMA_CONNECT_TIMEOUT', 3),
            'requestTimeout' => Env::get('OLLAMA_REQUEST_TIMEOUT', 30),
        ],
        'openai' => [
            'clientType' => ClientType::OpenAI->value,
            'apiUrl' => Env::get('OPENAI_API_URL', 'https://api.openai.com/v1'),
            'apiKey' => Env::get('OPENAI_API_KEY', ''),
            'endpoint' => Env::get('OPENAI_EMBED_ENDPOINT', '/embeddings'),
            'metadata' => [
                'organization' => ''
            ],
            'defaultModel' => Env::get('OPENAI_EMBED_MODEL', 'text-embedding-3-small'),
            'defaultDimensions' => Env::get('OPENAI_EMBED_DIMENSIONS', 1536),
            'maxInputs' => Env::get('OPENAI_MAX_INPUTS', 2048), // max 8192 tokens
            'connectTimeout' => Env::get('OPENAI_CONNECT_TIMEOUT', 3),
            'requestTimeout' => Env::get('OPENAI_REQUEST_TIMEOUT', 30),
        ],
    ],
];
