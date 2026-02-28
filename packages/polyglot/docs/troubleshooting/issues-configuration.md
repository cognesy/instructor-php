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
use Cognesy\Polyglot\Inference\Inference;
use Cognesy\Http\Exceptions\HttpRequestException;

function testApiKey(string $preset): bool {
    try {
        $inference = Inference::using($preset);
        $response = $inference->with(
            messages: 'Test message',
            options: ['max_tokens' => 5]
        )->get();

        echo "Connection preset '$preset' is working.\n";
        return true;
    } catch (HttpRequestException $e) {
        echo "Error with connection '$preset': " . $e->getMessage() . "\n";
        return false;
    }
}

// Test each connection
$presets = ['openai', 'anthropic', 'mistral'];
foreach ($presets as $preset) {
    testApiKey($preset);
}
```


### 2. Enable Debug Mode

Use debug mode to see the actual requests and responses:

```php
<?php
use Cognesy\Http\Creation\HttpClientBuilder;
use Cognesy\Polyglot\Inference\Inference;
use Cognesy\Polyglot\Inference\InferenceRuntime;

// Enable debug mode
$http = (new HttpClientBuilder())->withHttpDebugPreset('on')->create();
$inference = Inference::fromRuntime(InferenceRuntime::using(
    preset: 'openai',
    httpClient: $http,
));

// Make a request
$response = $inference->with(
    messages: 'Test message with debug enabled'
)->get();
```



### 3. Check Provider Status

Some issues might be related to the provider's service status. Check their status pages or documentation.



### 4. Verify Configuration Parameters

Ensure all required configuration parameters are present and correctly formatted:

```php
<?php

function verifyConfig(string $preset): void {
    try {
        $provider = new ConfigProvider();
        $config = LLMConfig::fromArray($provider->getConfig($preset));

        echo "Configuration for '$preset':\n";
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
        echo "Error loading configuration for '$preset': " . $e->getMessage() . "\n";
    }
}

// Verify configurations
$presets = ['openai', 'anthropic', 'mistral'];
foreach ($presets as $preset) {
    verifyConfig($preset);
    echo "\n";
}
```
