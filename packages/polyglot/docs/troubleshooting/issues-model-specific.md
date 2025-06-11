---
title: Model-Specific Issues
description: 'Learn how to troubleshoot model-specific issues when using Polyglot.'
---

When working with different LLM models, you may encounter issues that are specific to the model you're using, as different models have different capabilities and limitations. This section covers common model-specific issues and how to resolve them.


## Symptoms

- Errors like "model not found," "parameter not supported," or "context length exceeded"
- Unexpected responses or performance from certain models

## Solutions

1. **Check Model Availability**: Ensure the model you're requesting is available from the provider
```php
// Check available models for each provider in their documentation
// Example: For OpenAI 'gpt-4o-mini' is valid, but 'gpt5' is not
```

2. **Context Length**: Be aware of each model's maximum context length
```php
// In config/llm.php, check contextLength for each model
// Example: OpenAI models have different context windows
// - gpt-3.5-turbo: 16K tokens
// - gpt-4-turbo: 128K tokens
// - claude-3-opus: 200K tokens
```

3. **Feature Support**: Different models support different features
```php
// Some features may not work with all models
// Example: Vision capabilities are only available in select models

// Check for vision support before sending images
$modelSupportsVision = in_array($model, [
    'gpt-4-vision', 'gpt-4o', 'claude-3-opus', 'claude-3-sonnet'
]);

if (!$modelSupportsVision) {
    echo "Warning: The selected model doesn't support vision capabilities\n";
}
```

4. **Fallback Models**: Implement fallbacks to other models when preferred models fail

```php
<?php
use Cognesy\Polyglot\Inference\Inference;
use Cognesy\Http\Exceptions\HttpRequestException;

function withModelFallback(array $models, string $prompt): string {
    $inference = new Inference();
    $lastException = null;

    foreach ($models as $model) {
        try {
            return $inference->with(
                messages: $prompt,
                model: $model
            )->get();
        } catch (HttpRequestException $e) {
            $lastException = $e;
            echo "Model '$model' failed: " . $e->getMessage() . "\n";
            echo "Trying next model...\n";
        }
    }

    throw new \Exception("All models failed. Last error: " .
        ($lastException ? $lastException->getMessage() : "Unknown error"));
}

// Try advanced models first, then fall back to simpler ones
$models = ['gpt-4o', 'gpt-4o-mini', 'gpt-3.5-turbo'];

try {
    $response = withModelFallback($models, "What is the capital of France?");
    echo "Response: $response\n";
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
```
