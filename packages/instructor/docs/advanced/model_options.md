## Changing LLM model and options

You can specify model and other options that will be passed to LLM endpoint.

Commonly used option supported by many providers is `temperature`, which controls randomness of the output.

Lower values make the output more deterministic, while higher values make it more random.

```php
<?php
$person = (new Instructor)->respond(
    messages: [['role' => 'user', 'content' => $text]],
    responseModel: Person::class,
    model: 'gpt-3.5-turbo',
    options: [
        // custom temperature setting
        'temperature' => 0.0
        // ... other options - e.g. provider or model specific
    ],
);
```

> NOTE: Please note that many options might be specific to the provider or even some model that you are using.

## Customizing configuration

You can pass a custom LLM configuration to the Instructor.

This allows you to specify your own API key, base URI or,
which might be helpful in the case you are using OpenAI - organization.

```php
<?php
use Cognesy\Instructor\Instructor;
use Cognesy\Polyglot\LLM\Data\LLMConfig;

// Create instance of OpenAI client initialized with custom parameters
$config = new LLMConfig(
    apiUrl: 'https://api.openai.com/v1',
    apiKey: $yourApiKey,
    endpoint: '/chat/completions',
    metadata: ['organization' => ''],
    model: 'gpt-4o-mini',
    maxTokens: 128,
    httpClient: 'guzzle',
    providerType: 'openai',
));

/// Get Instructor with the default configuration overridden with your own
$instructor = (new Instructor)->withLLMConfig($driver);

$person = $instructor->respond(
    messages: [['role' => 'user', 'content' => $text]],
    responseModel: Person::class,
    options: ['temperature' => 0.0],
);
```
