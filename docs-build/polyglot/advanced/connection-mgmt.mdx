---
title: Connection Management
description: How to manage connections in Polyglot
---

One of Polyglot's strengths is the ability to easily switch between different LLM providers, which is made easy by using connections.

More complex applications may need to manage multiple connections and switch between them dynamically to implement fallback strategies or leverage the strengths of different models and providers for various tasks.



## Switching Providers

```php
<?php
use Cognesy\Polyglot\LLM\Inference;

$inference = new Inference();

// Use OpenAI
$openaiResponse = $inference->withConnection('openai')
    ->create(
        messages: 'What is the capital of France?'
    )->toText();

echo "OpenAI response: $openaiResponse\n";

// Switch to Anthropic
$anthropicResponse = $inference->withConnection('anthropic')
    ->create(
        messages: 'What is the capital of Germany?'
    )->toText();

echo "Anthropic response: $anthropicResponse\n";
```



## Implementing Fallbacks

You can implement a fallback mechanism to try alternative providers if one fails:

```php
<?php
use Cognesy\Polyglot\LLM\Inference;
use Cognesy\Http\Exceptions\RequestException;

function withFallback(array $providers, callable $requestFn) {
    $lastException = null;

    foreach ($providers as $provider) {
        try {
            $inference = (new Inference())->withConnection($provider);
            return $requestFn($inference);
        } catch (RequestException $e) {
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
        return $inference->create(
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
use Cognesy\Polyglot\LLM\Inference;

class CostAwareLLM {
    private $inference;
    private $providers = [
        'low' => [
            'connection' => 'ollama',
            'model' => 'llama2',
        ],
        'medium' => [
            'connection' => 'mistral',
            'model' => 'mistral-small-latest',
        ],
        'high' => [
            'connection' => 'openai',
            'model' => 'gpt-4o',
        ],
    ];

    public function __construct() {
        $this->inference = new Inference();
    }

    public function ask(string $question, string $tier = 'medium'): string {
        $provider = $this->providers[$tier] ?? $this->providers['medium'];

        return $this->inference->withConnection($provider['connection'])
            ->create(
                messages: $question,
                model: $provider['model']
            )
            ->toText();
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
use Cognesy\Polyglot\LLM\Inference;

class StrategicLLM {
    private $inference;
    private $providerStrategies = [
        'creative' => 'anthropic',
        'factual' => 'openai',
        'code' => 'mistral',
        'default' => 'openai',
    ];

    public function __construct() {
        $this->inference = new Inference();
    }

    public function ask(string $question, string $taskType = 'default'): string {
        // Select the appropriate provider based on the task type
        $provider = $this->providerStrategies[$taskType] ?? $this->providerStrategies['default'];

        // Use the selected provider
        return $this->inference->withConnection($provider)
            ->create(messages: $question)
            ->toText();
    }
}

// Usage
$strategicLLM = new StrategicLLM();

$creativeTasks = [
    "Write a short poem about the ocean.",
    "Create a brief story about a robot discovering emotions.",
];

$factualTasks = [
    "What is the capital of France?",
    "Who wrote 'Pride and Prejudice'?",
];

$codeTasks = [
    "Write a PHP function to check if a string is a palindrome.",
    "Create a simple JavaScript function to sort an array of objects by a property.",
];

foreach ($creativeTasks as $task) {
    echo "Creative task: $task\n";
    echo "Response: " . $strategicLLM->ask($task, 'creative') . "\n\n";
}

foreach ($factualTasks as $task) {
    echo "Factual task: $task\n";
    echo "Response: " . $strategicLLM->ask($task, 'factual') . "\n\n";
}

foreach ($codeTasks as $task) {
    echo "Code task: $task\n";
    echo "Response: " . $strategicLLM->ask($task, 'code') . "\n\n";
}
```
