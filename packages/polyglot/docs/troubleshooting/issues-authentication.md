---
title: Authentication
description: 'Learn how to troubleshoot authentication errors when using Polyglot.'
---

One of the most common issues when working with LLM APIs is authentication problems.

## Symptoms

- Error messages containing terms like "authentication failed," "invalid API key," or "unauthorized"
- HTTP status codes 401 or 403

## Solutions

1. **Verify API Key**: Ensure your API key is correctly set in your environment variables
```php
// Check if API key is set
if (empty(getenv('OPENAI_API_KEY'))) {
echo "API key is not set in environment variables\n";
}
```

2. **Check API Key Format**: Some providers require specific formats for API keys
```php
// OpenAI keys typically start with 'sk-'
if (!str_starts_with(getenv('OPENAI_API_KEY'), 'sk-')) {
echo "OpenAI API key format is incorrect\n";
}

// Anthropic keys typically start with 'sk-ant-'
if (!str_starts_with(getenv('ANTHROPIC_API_KEY'), 'sk-ant-')) {
echo "Anthropic API key format is incorrect\n";
}
```

3. **Test Keys Directly**: Use a simple script to test your API keys

```php
<?php
use Cognesy\Polyglot\LLM\Inference;
use Cognesy\Http\Exceptions\RequestException;

function testApiKey(string $connection): bool {
    try {
        $inference = new Inference($connection);
        $inference->create(
            messages: 'Test message',
            options: ['max_tokens' => 5]
        )->toText();

        echo "Connection '$connection' is working correctly\n";
        return true;
    } catch (RequestException $e) {
        echo "Error with connection '$connection': " . $e->getMessage() . "\n";
        return false;
    }
}

// Test major providers
testApiKey('openai');
testApiKey('anthropic');
testApiKey('mistral');
?>
```

4. **Environment Variables**: Ensure your environment variables are being loaded correctly
```php
<?php
// If using dotenv
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();
$dotenv->required(['OPENAI_API_KEY'])->notEmpty();
?>
```
