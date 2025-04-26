---
title: Connection Configurations
description: How to troubleshoot provider configurations in Polyglot
---

## Symptoms

- Errors like "connection timeout," "failed to connect," or "network error"
- Long delays before errors appear
- Issues with specific providers (e.g., OpenAI, Anthropic, Mistral)
- Incorrect API keys or permissions
- Missing or incorrect configuration parameters

## Solutions

### 1. Verify API Keys

Make sure your API keys are correct and have the necessary permissions:

```php
<?php
use Cognesy\Polyglot\LLM\Inference;
use Cognesy\Http\Exceptions\RequestException;

function testApiKey(string $connection): bool {
    try {
        $inference = new Inference($connection);
        $response = $inference->create(
            messages: 'Test message',
            options: ['max_tokens' => 5]
        )->toText();

        echo "Connection '$connection' is working.\n";
        return true;
    } catch (RequestException $e) {
        echo "Error with connection '$connection': " . $e->getMessage() . "\n";
        return false;
    }
}

// Test each connection
$connections = ['openai', 'anthropic', 'mistral'];
foreach ($connections as $connection) {
    testApiKey($connection);
}
```

### 2. Enable Debug Mode

Use debug mode to see the actual requests and responses:

```php
<?php
use Cognesy\Polyglot\LLM\Inference;

// Enable debug mode
$inference = new Inference('openai');
$inference->withDebug(true);

// Make a request
$response = $inference->create(
    messages: 'Test message with debug enabled'
)->toText();
```

### 3. Check Provider Status

Some issues might be related to the provider's service status. Check their status pages or documentation.

### 4. Verify Configuration Parameters

Ensure all required configuration parameters are present and correctly formatted:

```php
<?php
use Cognesy\Polyglot\LLM\Data\LLMConfig;

function verifyConfig(string $connection): void {
    try {
        $config = LLMConfig::load($connection);

        echo "Configuration for '$connection':\n";
        echo "API URL: {$config->apiUrl}\n";
        echo "Endpoint: {$config->endpoint}\n";
        echo "Default Model: {$config->model}\n";
        echo "Provider Type: {$config->providerType}\n";

        // Check for empty values
        if (empty($config->apiKey)) {
            echo "Warning: API key is empty\n";
        }

        if (empty($config->model)) {
            echo "Warning: Default model is not set\n";
        }
    } catch (\Exception $e) {
        echo "Error loading configuration for '$connection': " . $e->getMessage() . "\n";
    }
}

// Verify configurations
$connections = ['openai', 'anthropic', 'mistral'];
foreach ($connections as $connection) {
    verifyConfig($connection);
    echo "\n";
}
```
