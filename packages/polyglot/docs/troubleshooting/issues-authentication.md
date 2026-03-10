---
title: Authentication Issues
description: Diagnose and resolve API key and credential problems.
---

Authentication failures are among the most common issues when working with LLM APIs. They typically surface as HTTP 401 or 403 responses, or as error messages containing terms like "authentication failed," "invalid API key," or "unauthorized."

## Symptoms

- HTTP status code 401 (Unauthorized) or 403 (Forbidden)
- Error messages mentioning "invalid API key," "authentication failed," or "access denied"
- Requests that work from one machine but fail from another

## Verify the Environment Variable

Each preset references an environment variable for its API key. The variable name is defined in the preset YAML file using the `${VAR_NAME}` syntax. For example, the `openai` preset uses `${OPENAI_API_KEY}` and the `anthropic` preset uses `${ANTHROPIC_API_KEY}`.

Confirm that the variable is set and not empty:

```php
<?php

// Check if the API key is available
$key = getenv('OPENAI_API_KEY');
if (empty($key)) {
    echo "OPENAI_API_KEY is not set or is empty.\n";
}
```

If you use a `.env` file with a library like `vlucas/phpdotenv`, make sure the file is loaded before Polyglot resolves the preset:

```php
<?php

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();
$dotenv->required(['OPENAI_API_KEY'])->notEmpty();
```

## Check the Key Format

Some providers have distinctive key formats. Verifying the prefix can catch copy-paste errors early:

- **OpenAI** keys typically start with `sk-`
- **Anthropic** keys typically start with `sk-ant-`
- **Mistral** keys are UUIDs or short alphanumeric strings

```php
<?php

$openaiKey = (string) getenv('OPENAI_API_KEY');
if ($openaiKey !== '' && !str_starts_with($openaiKey, 'sk-')) {
    echo "Warning: OpenAI key does not start with 'sk-'. Verify it was copied correctly.\n";
}
```

## Confirm the Preset Matches the Provider

When you call `Inference::using('openai')`, Polyglot loads the `openai` preset and uses the API key, URL, and endpoint configured in that file. If you accidentally pass the wrong preset name, the key may not match the provider.

```php
<?php

use Cognesy\Polyglot\Inference\Inference;

// This uses the 'anthropic' preset -- make sure ANTHROPIC_API_KEY is set
$text = Inference::using('anthropic')
    ->withMessages('Hello')
    ->get();
```

## Test the Key Directly

Use a minimal script to confirm that the key works independently of your application logic:

```php
<?php

use Cognesy\Polyglot\Inference\Inference;

function testPreset(string $preset): void {
    try {
        $text = Inference::using($preset)
            ->withMessages('Test')
            ->withMaxTokens(5)
            ->get();

        echo "Preset '$preset' authenticated successfully.\n";
    } catch (\Exception $e) {
        echo "Preset '$preset' failed: " . $e->getMessage() . "\n";
    }
}

testPreset('openai');
testPreset('anthropic');
testPreset('mistral');
```

## Pass the Key Programmatically

If environment variables are not practical, you can supply the API key directly through `LLMConfig`:

```php
<?php

use Cognesy\Polyglot\Inference\Config\LLMConfig;
use Cognesy\Polyglot\Inference\Inference;

$inference = Inference::fromConfig(new LLMConfig(
    driver: 'openai',
    apiUrl: 'https://api.openai.com/v1',
    apiKey: 'sk-your-key-here',
    endpoint: '/chat/completions',
    model: 'gpt-4.1-nano',
));

$text = $inference->withMessages('Hello')->get();
```

> **Security note:** Avoid hard-coding API keys in source files that are committed to version control. Use environment variables, secrets managers, or encrypted configuration files.

## Common Pitfalls

- **Trailing whitespace or newlines** in the environment variable. Trim the value if your loading mechanism adds whitespace.
- **Expired or revoked keys.** Regenerate the key in your provider's dashboard.
- **Organization or project restrictions.** Some OpenAI keys require an `organization` value in the preset metadata. Check the preset YAML for a `metadata.organization` field.
- **IP allowlists.** Some providers or enterprise plans restrict API access to specific IP addresses. Confirm your server's IP is permitted.
