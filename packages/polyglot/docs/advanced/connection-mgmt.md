---
title: Preset Management
description: How to manage LLM connection presets in Polyglot
---

One of Polyglot's strengths is the ability to easily switch between different LLM providers, which is made easy by
using connection presets.

More complex applications may need to manage multiple LLM provider connections and switch between them dynamically to
implement fallback strategies or leverage the strengths of different models and providers for various tasks.



## Switching Providers

```php
<?php
use Cognesy\Polyglot\Inference\Inference;

// Use OpenAI
$openaiResponse = Inference::using('openai')
    ->withMessages('What is the capital of France?')
    ->get();

echo "OpenAI response: $openaiResponse\n";

// Switch to Anthropic
$anthropicResponse = Inference::using('anthropic')
    ->withMessages('What is the capital of Germany?')
    ->get();

echo "Anthropic response: $anthropicResponse\n";
```



## Implementing Fallbacks

You can implement a fallback mechanism to try alternative providers if one fails:

```php
<?php
use Cognesy\Polyglot\Inference\Inference;
use Cognesy\Http\Exceptions\HttpRequestException;

function withFallback(array $providers, callable $requestFn) {
    $lastException = null;

    foreach ($providers as $provider) {
        try {
            $inference = Inference::using($provider);
            return $requestFn($inference);
        } catch (HttpRequestException $e) {
            $lastException = $e;
            echo "Provider '$provider' failed: {$e->getMessage()}. Trying next provider...\n";
        }
    }

    throw new \Exception("All providers failed. Last error: " .
        ($lastException ? $lastException->getMessage() : "Unknown error"));
}

// Usage
try {
    $providers = ['openai', 'anthropic', 'gemini'];

    $response = withFallback($providers, function($inference) {
        return $inference->with(
            messages: 'What is the capital of France?'
        )->toText();
    });

    echo "Response: $response\n";
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
```




## Cost-Aware Provider Selection

You might want to select providers based on cost considerations:

```php
<?php
use Cognesy\Polyglot\Inference\Inference;

class CostAwareLLM {
    private $providers = [
        'low' => [
            'preset' => 'ollama',
            'model' => 'llama2',
        ],
        'medium' => [
            'preset' => 'mistral',
            'model' => 'mistral-small-latest',
        ],
        'high' => [
            'preset' => 'openai',
            'model' => 'gpt-4o',
        ],
    ];

    public function ask(string $question, string $tier = 'medium'): string {
        $provider = $this->providers[$tier] ?? $this->providers['medium'];

        return Inference::using($provider['preset'])
            ->with(
                messages: $question,
                model: $provider['model']
            )
            ->get();
    }
}

// Usage
$costAwareLLM = new CostAwareLLM();

// Simple question - use low-cost tier
$simpleQuestion = "What is the capital of France?";
echo "Simple question (low cost): $simpleQuestion\n";
echo "Response: " . $costAwareLLM->ask($simpleQuestion, 'low') . "\n\n";

// More complex question - use medium-cost tier
$mediumQuestion = "Explain the concept of deep learning in simple terms.";
echo "Medium question (medium cost): $mediumQuestion\n";
echo "Response: " . $costAwareLLM->ask($mediumQuestion, 'medium') . "\n\n";

// Critical question - use high-cost tier
$complexQuestion = "Analyze the ethical implications of AI in healthcare.";
echo "Complex question (high cost): $complexQuestion\n";
echo "Response: " . $costAwareLLM->ask($complexQuestion, 'high') . "\n\n";
```



## Provider Selection Strategy

You can implement a strategy to select the most appropriate provider for each request:

```php
<?php
use Cognesy\Polyglot\Inference\Inference;

class GroupOfExperts {
    private $providerStrategies = [
        'creative' => 'anthropic',
        'factual' => 'openai',
        'code' => 'gemini',
        'default' => 'openai',
    ];

    public function ask(string $question, string $taskType = 'default'): string {
        // Select the appropriate provider based on the task type
        $preset = $this->providerStrategies[$taskType] ?? $this->providerStrategies['default'];

        // Use the selected provider
        return Inference::using($preset)
            ->with(messages: $question)
            ->get();
    }
}

// Usage
$experts = new GroupOfExperts();

$tasks = [
    ["Write a short poem about the ocean.", 'creative'],
    ["Create a brief story about a robot discovering emotions.", 'creative'],
    ["What is the capital of France?", 'factual'],
    ["Who wrote 'Pride and Prejudice'?", 'factual'],
    ["Write a PHP function to check if a string is a palindrome.", 'code'],
    ["Create a simple JavaScript function to sort an array of objects by a property.", 'code'],
];

foreach ($tasks as $task) {
    echo "Task: $task\n";
    echo "Response: " . $experts->ask($task[0], $task[1]) . "\n\n";
}
```
