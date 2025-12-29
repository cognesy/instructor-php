<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Connection
    |--------------------------------------------------------------------------
    |
    | The default LLM connection to use for inference and structured output.
    | This can be overridden at runtime using ->using('connection').
    |
    */

    'default' => env('INSTRUCTOR_CONNECTION', 'openai'),

    /*
    |--------------------------------------------------------------------------
    | Connections
    |--------------------------------------------------------------------------
    |
    | Configure your LLM provider connections here. Each connection specifies
    | the driver, API credentials, and default model settings.
    |
    | Supported drivers: openai, anthropic, azure, gemini, mistral, groq,
    | cohere, deepseek, ollama, and 20+ more.
    |
    */

    'connections' => [

        'openai' => [
            'driver' => 'openai',
            'api_url' => env('OPENAI_API_URL', 'https://api.openai.com/v1'),
            'api_key' => env('OPENAI_API_KEY'),
            'organization' => env('OPENAI_ORGANIZATION'),
            'model' => env('OPENAI_MODEL', 'gpt-4o-mini'),
            'max_tokens' => env('OPENAI_MAX_TOKENS', 4096),
        ],

        'anthropic' => [
            'driver' => 'anthropic',
            'api_url' => env('ANTHROPIC_API_URL', 'https://api.anthropic.com/v1'),
            'api_key' => env('ANTHROPIC_API_KEY'),
            'model' => env('ANTHROPIC_MODEL', 'claude-sonnet-4-20250514'),
            'max_tokens' => env('ANTHROPIC_MAX_TOKENS', 4096),
        ],

        'azure' => [
            'driver' => 'azure',
            'api_key' => env('AZURE_OPENAI_API_KEY'),
            'resource_name' => env('AZURE_OPENAI_RESOURCE'),
            'deployment_id' => env('AZURE_OPENAI_DEPLOYMENT'),
            'api_version' => env('AZURE_OPENAI_API_VERSION', '2024-08-01-preview'),
            'model' => env('AZURE_OPENAI_MODEL', 'gpt-4o-mini'),
            'max_tokens' => env('AZURE_OPENAI_MAX_TOKENS', 4096),
        ],

        'gemini' => [
            'driver' => 'gemini',
            'api_url' => env('GEMINI_API_URL', 'https://generativelanguage.googleapis.com/v1beta'),
            'api_key' => env('GEMINI_API_KEY'),
            'model' => env('GEMINI_MODEL', 'gemini-2.0-flash'),
            'max_tokens' => env('GEMINI_MAX_TOKENS', 4096),
        ],

        'ollama' => [
            'driver' => 'ollama',
            'api_url' => env('OLLAMA_API_URL', 'http://localhost:11434/v1'),
            'api_key' => env('OLLAMA_API_KEY', 'ollama'),
            'model' => env('OLLAMA_MODEL', 'llama3.2'),
            'max_tokens' => env('OLLAMA_MAX_TOKENS', 4096),
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Embeddings Connections
    |--------------------------------------------------------------------------
    |
    | Configure embedding model connections for vector generation.
    |
    */

    'embeddings' => [

        'default' => env('INSTRUCTOR_EMBEDDINGS_CONNECTION', 'openai'),

        'connections' => [

            'openai' => [
                'driver' => 'openai',
                'api_url' => env('OPENAI_API_URL', 'https://api.openai.com/v1'),
                'api_key' => env('OPENAI_API_KEY'),
                'model' => env('OPENAI_EMBEDDINGS_MODEL', 'text-embedding-3-small'),
                'dimensions' => env('OPENAI_EMBEDDINGS_DIMENSIONS', 1536),
            ],

            'ollama' => [
                'driver' => 'ollama',
                'api_url' => env('OLLAMA_API_URL', 'http://localhost:11434/v1'),
                'api_key' => env('OLLAMA_API_KEY', 'ollama'),
                'model' => env('OLLAMA_EMBEDDINGS_MODEL', 'nomic-embed-text'),
                'dimensions' => env('OLLAMA_EMBEDDINGS_DIMENSIONS', 768),
            ],

        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Structured Output Settings
    |--------------------------------------------------------------------------
    |
    | Default settings for structured output extraction.
    |
    */

    'extraction' => [

        /*
        | Output mode determines how the LLM returns structured data.
        | Options: json_schema, json, tools, md_json
        */
        'output_mode' => env('INSTRUCTOR_OUTPUT_MODE', 'json_schema'),

        /*
        | Maximum retry attempts when validation fails.
        */
        'max_retries' => env('INSTRUCTOR_MAX_RETRIES', 2),

        /*
        | Prompt template for retry attempts.
        */
        'retry_prompt' => 'The response did not pass validation. Please fix the following errors and try again: {errors}',

    ],

    /*
    |--------------------------------------------------------------------------
    | HTTP Client Settings
    |--------------------------------------------------------------------------
    |
    | Configure the HTTP client used for API requests.
    |
    */

    'http' => [

        /*
        | HTTP driver to use. Set to 'laravel' to use Laravel's HTTP client
        | which enables Http::fake() in tests.
        */
        'driver' => env('INSTRUCTOR_HTTP_DRIVER', 'laravel'),

        /*
        | Request timeout in seconds.
        */
        'timeout' => env('INSTRUCTOR_HTTP_TIMEOUT', 120),

        /*
        | Connection timeout in seconds.
        */
        'connect_timeout' => env('INSTRUCTOR_HTTP_CONNECT_TIMEOUT', 30),

    ],

    /*
    |--------------------------------------------------------------------------
    | Logging Settings
    |--------------------------------------------------------------------------
    |
    | Configure logging for Instructor operations.
    |
    */

    'logging' => [

        /*
        | Enable or disable logging.
        */
        'enabled' => env('INSTRUCTOR_LOGGING_ENABLED', true),

        /*
        | Log channel to use.
        */
        'channel' => env('INSTRUCTOR_LOG_CHANNEL', 'stack'),

        /*
        | Minimum log level.
        */
        'level' => env('INSTRUCTOR_LOG_LEVEL', 'debug'),

        /*
        | Logging preset: default, production, or custom
        */
        'preset' => env('INSTRUCTOR_LOGGING_PRESET', 'default'),

        /*
        | Events to exclude from logging.
        */
        'exclude_events' => [
            // Cognesy\Http\Events\DebugRequestBodyUsed::class,
            // Cognesy\Http\Events\DebugResponseBodyReceived::class,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Events Settings
    |--------------------------------------------------------------------------
    |
    | Configure event dispatching to Laravel's event system.
    |
    */

    'events' => [

        /*
        | Bridge Instructor events to Laravel's event dispatcher.
        | This allows you to listen to events using Laravel's Event facade.
        */
        'dispatch_to_laravel' => env('INSTRUCTOR_DISPATCH_EVENTS', true),

        /*
        | Events to bridge to Laravel. Leave empty to bridge all events.
        */
        'bridge_events' => [
            // Only specific events will be bridged if specified
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Cache Settings
    |--------------------------------------------------------------------------
    |
    | Configure response caching for LLM calls.
    |
    */

    'cache' => [

        /*
        | Enable response caching.
        */
        'enabled' => env('INSTRUCTOR_CACHE_ENABLED', false),

        /*
        | Cache store to use.
        */
        'store' => env('INSTRUCTOR_CACHE_STORE'),

        /*
        | Default TTL in seconds.
        */
        'ttl' => env('INSTRUCTOR_CACHE_TTL', 3600),

        /*
        | Cache key prefix.
        */
        'prefix' => 'instructor',

    ],

    /*
    |--------------------------------------------------------------------------
    | Code Agents Settings
    |--------------------------------------------------------------------------
    |
    | Configure CLI-based code agents (Claude Code, Codex, OpenCode).
    | Use AgentCtrl::claudeCode(), AgentCtrl::codex(), or AgentCtrl::openCode()
    |
    */

    'agents' => [

        /*
        | Default execution timeout in seconds for all agents.
        */
        'timeout' => env('INSTRUCTOR_AGENT_TIMEOUT', 300),

        /*
        | Default working directory for agents.
        | If null, the current directory will be used.
        */
        'directory' => env('INSTRUCTOR_AGENT_DIRECTORY'),

        /*
        | Default sandbox driver for agent execution.
        | Options: host, docker, podman, firejail, bubblewrap
        */
        'sandbox' => env('INSTRUCTOR_AGENT_SANDBOX', 'host'),

        /*
        | Claude Code agent configuration.
        */
        'claude_code' => [
            'model' => env('CLAUDE_CODE_MODEL', 'claude-sonnet-4-20250514'),
            'timeout' => env('CLAUDE_CODE_TIMEOUT'),
            'directory' => env('CLAUDE_CODE_DIRECTORY'),
            'sandbox' => env('CLAUDE_CODE_SANDBOX'),
        ],

        /*
        | OpenAI Codex agent configuration.
        */
        'codex' => [
            'model' => env('CODEX_MODEL', 'codex'),
            'timeout' => env('CODEX_TIMEOUT'),
            'directory' => env('CODEX_DIRECTORY'),
            'sandbox' => env('CODEX_SANDBOX'),
        ],

        /*
        | OpenCode agent configuration.
        */
        'opencode' => [
            'model' => env('OPENCODE_MODEL', 'anthropic/claude-sonnet-4-20250514'),
            'timeout' => env('OPENCODE_TIMEOUT'),
            'directory' => env('OPENCODE_DIRECTORY'),
            'sandbox' => env('OPENCODE_SANDBOX'),
        ],

    ],

];
