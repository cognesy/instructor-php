# Connecting to various LLM API providers

Instructor allows you to define multiple API connections in `llm.php` file.
This is useful when you want to use different LLMs or API providers in your application.

Default configuration is located in `/config/llm.php` in the root directory
of Instructor codebase. It contains a set of predefined connections to all LLM APIs
supported out-of-the-box by Instructor.

Config file defines connections to LLM APIs and their parameters. It also specifies
the default connection to be used when calling Instructor without specifying
the client connection.

```php
    // This is fragment of /config/llm.php file
    'defaultConnection' => 'openai',
    //...
    'connections' => [
        'anthropic' => [ ... ],
        'azure' => [ ... ],
        'cohere1' => [ ... ],
        'cohere2' => [ ... ],
        'fireworks' => [ ... ],
        'gemini' => [ ... ],
        'grok' => [ ... ],
        'groq' => [ ... ],
        'mistral' => [ ... ],
        'ollama' => [
            'providerType' => LLMProviderType::Ollama->value,
            'apiUrl' => 'http://localhost:11434/v1',
            'apiKey' => Env::get('OLLAMA_API_KEY', ''),
            'endpoint' => '/chat/completions',
            'defaultModel' => 'qwen2.5:0.5b',
            'defaultMaxTokens' => 1024,
            'httpClient' => 'guzzle-ollama', // use custom HTTP client configuration
        ],
        'openai' => [ ... ],
        'openrouter' => [ ... ],
        'together' => [ ... ],
    // ...
```
To customize the available connections you can either modify existing entries or
add your own.

Connecting to LLM API via predefined connection is as simple as calling `withClient`
method with the connection name.

```php
<?php
// ...
$answer = (new Inference)
    ->withConnection('ollama') // see /config/llm.php
    ->create(
        messages: [['role' => 'user', 'content' => 'What is the capital of France']],
        options: ['max_tokens' => 64]
    )
    ->toText();
// ...
```

You can change the location of the configuration files for Instructor to use via
`INSTRUCTOR_CONFIG_PATH` environment variable. You can use copies of the default
configuration files as a starting point.
