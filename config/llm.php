<?php

use Cognesy\LLM\LLM\Enums\LLMProviderType;
use Cognesy\Utils\Env;

return [
    'useObjectReferences' => false,
    'defaultConnection' => 'openai',

    'defaultToolName' => 'extracted_data',
    'defaultToolDescription' => 'Function call based on user instructions.',
    'defaultRetryPrompt' => "JSON generated incorrectly, fix following errors:\n",
    'defaultMdJsonPrompt' => "Response must validate against this JSON Schema:\n<|json_schema|>\n. Respond correctly with strict JSON object within a ```json {} ``` codeblock.\n",
    'defaultJsonPrompt' => "Response must follow JSON Schema:\n<|json_schema|>\n. Respond correctly with strict JSON object.\n",
    'defaultToolsPrompt' => "Extract correct and accurate data from the input using provided tools.\n",

    'connections' => [
        'a21' => [
            'providerType' => LLMProviderType::A21->value,
            'apiUrl' => 'https://api.ai21.com/studio/v1',
            'apiKey' => Env::get('A21_API_KEY', ''),
            'endpoint' => '/chat/completions',
            'defaultModel' => 'jamba-1.5-mini',
            'defaultMaxTokens' => 1024,
            'contextLength' => 256_000,
            'maxOutputLength' => 4096,
        ],
        'anthropic' => [
            'providerType' => LLMProviderType::Anthropic->value,
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
            'providerType' => LLMProviderType::Azure->value,
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
            'providerType' => LLMProviderType::Cerebras->value,
            'apiUrl' => 'https://api.cerebras.ai/v1',
            'apiKey' => Env::get('CEREBRAS_API_KEY', ''),
            'endpoint' => '/chat/completions',
            'defaultModel' => 'llama3.1-8b', // ''
            'defaultMaxTokens' => 1024,
            'contextLength' => 128_000,
            'maxOutputLength' => 2048,
        ],
        'cohere1' => [
            'providerType' => LLMProviderType::CohereV1->value,
            'apiUrl' => 'https://api.cohere.ai/v1',
            'apiKey' => Env::get('COHERE_API_KEY', ''),
            'endpoint' => '/chat',
            'defaultModel' => 'command-r-plus-08-2024',
            'defaultMaxTokens' => 1024,
            'contextLength' => 128_000,
            'maxOutputLength' => 4096,
        ],
        'cohere2' => [
            'providerType' => LLMProviderType::CohereV2->value,
            'apiUrl' => 'https://api.cohere.ai/v2',
            'apiKey' => Env::get('COHERE_API_KEY', ''),
            'endpoint' => '/chat',
            'defaultModel' => 'command-r-plus-08-2024',
            'defaultMaxTokens' => 1024,
            'contextLength' => 128_000,
            'maxOutputLength' => 4096,
        ],
        'deepseek' => [
            'providerType' => LLMProviderType::DeepSeek->value,
            'apiUrl' => 'https://api.deepseek.com',
            'apiKey' => Env::get('DEEPSEEK_API_KEY', ''),
            'endpoint' => '/chat/completions',
            'defaultModel' => 'deepseek-chat',
            'defaultMaxTokens' => 1024,
            'contextLength' => 128_000,
            'maxOutputLength' => 8192,
        ],
        'deepseek-r' => [
            'providerType' => LLMProviderType::DeepSeek->value,
            'apiUrl' => 'https://api.deepseek.com',
            'apiKey' => Env::get('DEEPSEEK_API_KEY', ''),
            'endpoint' => '/chat/completions',
            'defaultModel' => 'deepseek-reasoner',
            'defaultMaxTokens' => 1024,
            'contextLength' => 128_000,
            'maxOutputLength' => 8192,
        ],
        'fireworks' => [
            'providerType' => LLMProviderType::Fireworks->value,
            'apiUrl' => 'https://api.fireworks.ai/inference/v1',
            'apiKey' => Env::get('FIREWORKS_API_KEY', ''),
            'endpoint' => '/chat/completions',
            'defaultModel' => 'accounts/fireworks/models/mixtral-8x7b-instruct',
            'defaultMaxTokens' => 1024,
            'contextLength' => 65_000,
            'maxOutputLength' => 4096,
        ],
        'gemini' => [
            'providerType' => LLMProviderType::Gemini->value,
            'apiUrl' => 'https://generativelanguage.googleapis.com/v1beta',
            'apiKey' => Env::get('GEMINI_API_KEY', ''),
            'endpoint' => '/models/{model}:generateContent',
            'defaultModel' => 'gemini-1.5-flash',
            'defaultMaxTokens' => 1024,
            'contextLength' => 1_000_000,
            'maxOutputLength' => 8192,
        ],
        'gemini-oai' => [
            'providerType' => LLMProviderType::GeminiOAI->value,
            'apiUrl' => 'https://generativelanguage.googleapis.com/v1beta/openai',
            'apiKey' => Env::get('GEMINI_API_KEY', ''),
            'endpoint' => '/chat/completions',
            'defaultModel' => 'gemini-1.5-flash',
            'defaultMaxTokens' => 1024,
            'contextLength' => 1_000_000,
            'maxOutputLength' => 8192,
        ],
        'groq' => [
            'providerType' => LLMProviderType::Groq->value,
            'apiUrl' => 'https://api.groq.com/openai/v1',
            'apiKey' => Env::get('GROQ_API_KEY', ''),
            'endpoint' => '/chat/completions',
            'defaultModel' => 'llama-3.3-70b-versatile', // 'gemma2-9b-it',
            'defaultMaxTokens' => 1024,
            'contextLength' => 128_000,
            'maxOutputLength' => 2048,
        ],
        'minimaxi' => [
            'providerType' => LLMProviderType::Minimaxi->value,
            'apiUrl' => 'https://api.minimaxi.chat/v1',
            'apiKey' => Env::get('MINIMAXI_API_KEY', ''),
            'endpoint' => '/text/chatcompletion_v2',
            'defaultModel' => 'MiniMax-Text-01', // 'MiniMax-Text-01',
            'defaultMaxTokens' => 1024,
            'contextLength' => 1_000_000,
            'maxOutputLength' => 4096,
        ],
        'mistral' => [
            'providerType' => LLMProviderType::Mistral->value,
            'apiUrl' => 'https://api.mistral.ai/v1',
            'apiKey' => Env::get('MISTRAL_API_KEY', ''),
            'endpoint' => '/chat/completions',
            'defaultModel' => 'mistral-small-latest',
            'defaultMaxTokens' => 1024,
            'contextLength' => 128_000,
            'maxOutputLength' => 4096,
        ],
        'moonshot-kimi' => [
            'providerType' => LLMProviderType::Moonshot->value,
            'apiUrl' => 'https://api.moonshot.ai/v1',
            'apiKey' => Env::get('MOONSHOT_API_KEY', ''),
            'endpoint' => '/chat/completions',
            'defaultModel' => 'kimi-k1.5-preview',
            'defaultMaxTokens' => 1024,
            'contextLength' => 128_000,
            'maxOutputLength' => 4096,
        ],
        'ollama' => [
            'providerType' => LLMProviderType::Ollama->value,
            'apiUrl' => 'http://localhost:11434/v1',
            'apiKey' => Env::get('OLLAMA_API_KEY', ''),
            'endpoint' => '/chat/completions',
            'defaultModel' => 'qwen2.5-coder:3b', //'gemma2:2b',
            'defaultMaxTokens' => 1024,
            'httpClient' => 'http-ollama',
            'contextLength' => 128_000,
            'maxOutputLength' => 8192,
        ],
        'openai' => [
            'providerType' => LLMProviderType::OpenAI->value,
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
            'providerType' => LLMProviderType::OpenRouter->value,
            'apiUrl' => 'https://openrouter.ai/api/v1',
            'apiKey' => Env::get('OPENROUTER_API_KEY', ''),
            'endpoint' => '/chat/completions',
            'defaultModel' => 'deepseek/deepseek-chat', //'microsoft/phi-3.5-mini-128k-instruct',
            'defaultMaxTokens' => 1024,
            'contextLength' => 128_000,
            'maxOutputLength' => 8192,
        ],
        'perplexity' => [
            'providerType' => LLMProviderType::Perplexity->value,
            'apiUrl' => 'https://api.perplexity.ai',
            'apiKey' => Env::get('PERPLEXITY_API_KEY', ''),
            'endpoint' => '/chat/completions',
            'defaultModel' => 'llama-3.1-sonar-small-128k-online',
            'defaultMaxTokens' => 1024,
            'contextLength' => 128_000,
            'maxOutputLength' => 2048,
        ],
        'sambanova' => [
            'providerType' => LLMProviderType::SambaNova->value,
            'apiUrl' => 'https://api.sambanova.ai/v1',
            'apiKey' => Env::get('SAMBANOVA_API_KEY', ''),
            'endpoint' => '/chat/completions',
            'defaultModel' => 'Meta-Llama-3.1-8B-Instruct',
            'defaultMaxTokens' => 1024,
            'contextLength' => 128_000,
            'maxOutputLength' => 2048,
        ],
        'together' => [
            'providerType' => LLMProviderType::Together->value,
            'apiUrl' => 'https://api.together.xyz/v1',
            'apiKey' => Env::get('TOGETHER_API_KEY', ''),
            'endpoint' => '/chat/completions',
            'defaultModel' => 'mistralai/Mixtral-8x7B-Instruct-v0.1',
            'defaultMaxTokens' => 1024,
            'contextLength' => 128_000,
            'maxOutputLength' => 4096,
        ],
        'xai' => [
            'providerType' => LLMProviderType::XAi->value,
            'apiUrl' => 'https://api.x.ai/v1',
            'apiKey' => Env::get('XAI_API_KEY', ''),
            'endpoint' => '/chat/completions',
            'defaultModel' => '-1212',
            'defaultMaxTokens' => 1024,
            'contextLength' => 128_000,
            'maxOutputLength' => 128_000,
        ],
    ],
];
