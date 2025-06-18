<?php

use Cognesy\Config\Env;

return [
    'defaultPreset' => 'openai',

    'presets' => [
        'azure' => [
            'driver' => 'azure',
            'apiUrl' => 'https://{resourceName}.openai.azure.com/openai/deployments/{deploymentId}',
            'apiKey' => Env::get('AZURE_OPENAI_EMBED_API_KEY', ''),
            'endpoint' => '/embeddings',
            'metadata' => [
                'apiVersion' => '2023-05-15',
                'resourceName' => 'instructor-dev',
                'deploymentId' => 'text-embedding-3-small',
            ],
            'model' => 'text-embedding-3-small',
            'dimensions' => 1536,
            'maxInputs' => 16,
        ],
        'cohere' => [
            'driver' => 'cohere',
            'apiUrl' => 'https://api.cohere.ai/v2',
            'apiKey' => Env::get('COHERE_API_KEY', ''),
            'endpoint' => '/embed',
            'model' => 'embed-multilingual-v3.0',
            'dimensions' => 1024,
            'maxInputs' => 96,
        ],
        'gemini' => [
            'driver' => 'gemini',
            'apiUrl' => 'https://generativelanguage.googleapis.com/v1beta',
            'apiKey' => Env::get('GEMINI_API_KEY', ''),
            'endpoint' => '/{model}:batchEmbedContents',
            'model' => 'models/text-embedding-004',
            'dimensions' => 768,
            'maxInputs' => 100, // max 2048 tokens
        ],
        'jina' => [
            'driver' => 'jina',
            'apiUrl' => 'https://api.jina.ai/v1',
            'apiKey' => Env::get('JINA_API_KEY', ''),
            'endpoint' => '/embeddings',
            'metadata' => [
                'organization' => ''
            ],
            'model' => 'jina-embeddings-v2-base-en',
            'dimensions' => 768,
            'maxInputs' => 500, // max 8192 tokens
        ],
        'mistral' => [
            'driver' => 'mistral',
            'apiUrl' => 'https://api.mistral.ai/v1',
            'apiKey' => Env::get('MISTRAL_API_KEY', ''),
            'endpoint' => '/embeddings',
            'model' => 'mistral-embed',
            'dimensions' => 1024,
            'maxInputs' => 512, // max 512 tokens
        ],
        'ollama' => [
            'driver' => 'ollama',
            'apiUrl' => 'http://localhost:11434/v1',
            'apiKey' => Env::get('OLLAMA_API_KEY', ''),
            'endpoint' => '/embeddings',
            'model' => 'nomic-embed-text',
            'dimensions' => 1024,
            'maxInputs' => 512,
            'httpClientPreset' => 'http-ollama',
        ],
        'openai' => [
            'driver' => 'openai',
            'apiUrl' => 'https://api.openai.com/v1',
            'apiKey' => Env::get('OPENAI_API_KEY', ''),
            'endpoint' => '/embeddings',
            'metadata' => [
                'organization' => ''
            ],
            'model' => 'text-embedding-3-small',
            'dimensions' => 1536,
            'maxInputs' => 2048, // max 8192 tokens
        ],
    ],
];
